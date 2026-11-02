<?php
declare(strict_types=1);

namespace App\Domain;

use App\Core\App;
use App\Core\AutorizacionAdmin;
use App\Core\Config;
use App\Core\Db;
use App\Core\Falta;
use App\Core\Historial;
use App\Core\HttpError;

/**
 * Afiliados y grupos de pago (CU-05 a CU-11), cuotas y promociones,
 * y estado de cuenta (RT-05).
 */
final class Padron
{
    private static function db(): Db
    {
        return App::$db;
    }

    // ------------------------------------------------------------------ lectura

    public static function hidratar(?array $a): ?array
    {
        if (!$a) {
            return null;
        }
        foreach (['id', 'nro', 'grupo_id', 'orden'] as $k) {
            if (isset($a[$k])) {
                $a[$k] = (int)$a[$k];
            }
        }
        $a['cuota'] = (float)($a['cuota'] ?? 0);
        $a['activo'] = ($a['estado'] ?? '') === 'activo';
        return $a;
    }

    public static function afiliado(int $id): ?array
    {
        return self::hidratar(self::db()->one('SELECT * FROM afiliados WHERE id = :id', ['id' => $id]));
    }

    public static function afiliadoPorNro(int $nro): ?array
    {
        return self::hidratar(self::db()->one('SELECT * FROM afiliados WHERE nro = :n', ['n' => $nro]));
    }

    public static function grupo(int $id): ?array
    {
        $g = self::db()->one('SELECT * FROM grupos WHERE id = :id', ['id' => $id]);
        if (!$g) {
            return null;
        }
        $g['id'] = (int)$g['id'];
        $g['promo'] = $g['promo_pct'] ? ['pct' => (int)$g['promo_pct'], 'meses' => (int)$g['promo_meses'], 'desde' => trim((string)$g['promo_desde']), 'hasta' => trim((string)$g['promo_hasta']), 'retencion' => (bool)$g['promo_retencion']] : null;
        return $g;
    }

    /** Integrantes del grupo: el titular (quien paga) primero. */
    public static function miembros(int $grupoId): array
    {
        return array_map([self::class, 'hidratar'], self::db()->all('SELECT * FROM afiliados WHERE grupo_id = :g ORDER BY orden, nro', ['g' => $grupoId]));
    }

    /**
     * Quien paga por el grupo: el primer integrante activo; si no queda ninguno, el primero (P-17).
     */
    public static function titular(int $grupoId): ?array
    {
        $ms = self::miembros($grupoId);
        foreach ($ms as $m) {
            if ($m['activo']) {
                return $m;
            }
        }
        return $ms[0] ?? null;
    }

    /** Teléfono para WhatsApp: el del titular o el del primer integrante que lo tenga (CU-19). */
    public static function telefonoGrupo(int $grupoId): string
    {
        foreach (self::miembros($grupoId) as $m) {
            if (Fmt::telValido($m['telefono'])) {
                return $m['telefono'];
            }
        }
        return '';
    }

    /** Integrantes activos dados de alta hasta el fin del período (CU-26). */
    public static function activosEn(int $grupoId, ?string $periodo = null): array
    {
        return array_values(array_filter(self::miembros($grupoId), fn($a) => $a['activo'] && (!$periodo || $a['fecha_alta'] <= Fechas::finPeriodo($periodo))));
    }

    public static function cuotaBase(int $grupoId, ?string $periodo = null): float
    {
        return array_sum(array_map(fn($a) => $a['cuota'], self::activosEn($grupoId, $periodo)));
    }

    public static function promoVigente(array $g, string $periodo): ?array
    {
        $p = $g['promo'] ?? null;
        return $p && $periodo >= $p['desde'] && $periodo <= $p['hasta'] ? $p : null;
    }

    public static function conPromo(float $monto, int $pct): float
    {
        return round($monto * (100 - $pct) / 100);
    }

    /** Cuota del grupo para un período (con la promoción vigente aplicada, redondeada al peso). */
    public static function cuotaGrupo(array $g, ?string $periodo = null): float
    {
        $periodo ??= substr(Fechas::hoy(), 0, 7);
        $b = self::cuotaBase($g['id'], $periodo);
        $p = self::promoVigente($g, $periodo);
        return $p ? self::conPromo($b, $p['pct']) : $b;
    }

    /** Cuota actual del grupo sin filtrar por período de alta (ficha, cuentas). */
    public static function cuotaActual(array $g): float
    {
        $b = self::cuotaBase($g['id']);
        $p = self::promoVigente($g, substr(Fechas::hoy(), 0, 7));
        return $p ? self::conPromo($b, $p['pct']) : $b;
    }

    /**
     * RT-05: estado de cuenta de un grupo.
     * @return array{saldo:float,estado:string,vencidas:array,pendientes:array}
     */
    public static function cuenta(int $grupoId, ?string $hoy = null): array
    {
        $hoy ??= Fechas::hoy();
        $fs = self::db()->all("SELECT id, periodo, vto, importe, estado FROM facturas WHERE grupo_id = :g ORDER BY periodo", ['g' => $grupoId]);
        $cob = (float)self::db()->val('SELECT coalesce(sum(importe),0) FROM recibos WHERE grupo_id = :g', ['g' => $grupoId]);
        $fac = array_sum(array_map(fn($f) => (float)$f['importe'], $fs));
        $pend = array_values(array_filter($fs, fn($f) => $f['estado'] === 'pendiente'));
        $venc = array_values(array_filter($pend, fn($f) => $f['vto'] < $hoy));
        $saldo = round($fac - $cob, 2);
        return [
            'saldo' => $saldo, 'vencidas' => $venc, 'pendientes' => $pend,
            'estado' => $venc ? 'vencida' : ($saldo > 0 ? 'por_vencer' : 'al_dia'),
        ];
    }

    /** Estados de cuenta de todos los grupos en una consulta (listados). @return array<int,string> */
    public static function estadosCuenta(?string $hoy = null): array
    {
        $hoy ??= Fechas::hoy();
        $r = [];
        foreach (self::db()->all(
            "SELECT g.id,
                    coalesce((SELECT sum(importe) FROM facturas f WHERE f.grupo_id = g.id),0) - coalesce((SELECT sum(importe) FROM recibos r WHERE r.grupo_id = g.id),0) AS saldo,
                    (SELECT count(*) FROM facturas f WHERE f.grupo_id = g.id AND f.estado = 'pendiente' AND f.vto < :hoy) AS vencidas
             FROM grupos g",
            ['hoy' => $hoy]
        ) as $x) {
            $r[(int)$x['id']] = (int)$x['vencidas'] > 0 ? 'vencida' : ((float)$x['saldo'] > 0 ? 'por_vencer' : 'al_dia');
        }
        return $r;
    }

    // ------------------------------------------------------------------ búsqueda (RT-01)

    /** Busca un afiliado por "Nombre · N° 75", N° de afiliado, DNI o CUIT (desde 6 dígitos). */
    public static function buscarUno(string $q): ?array
    {
        $q = trim($q);
        if ($q === '') {
            return null;
        }
        if (preg_match('/N°\s*(\d+)\s*$/u', $q, $m)) {
            return self::afiliadoPorNro((int)$m[1]);
        }
        if (ctype_digit($q) && strlen($q) <= 6) {
            $a = self::afiliadoPorNro((int)$q);
            if ($a) {
                return $a;
            }
        }
        $d = Fmt::docNorm($q);
        if (strlen($d) >= 6) {
            return self::hidratar(self::db()->one("SELECT * FROM afiliados WHERE doc_norm = :d ORDER BY (estado = 'activo') DESC, nro DESC LIMIT 1", ['d' => $d]));
        }
        return null;
    }

    /** Sugerencias por nombre desde la primera letra (hasta 8, activos primero). */
    public static function sugerir(string $q, int $max = 8): array
    {
        $q = trim($q);
        if ($q === '' || !preg_match('/\pL/u', $q)) {
            return [];
        }
        $nq = Fmt::norm($q);
        $toks = array_values(array_filter(preg_split('/\s+/', $nq) ?: []));
        $where = [];
        $p = [];
        foreach ($toks as $i => $t) {
            $where[] = "nombre_norm LIKE :t$i";
            $p["t$i"] = '%' . $t . '%';
        }
        $rows = self::db()->all('SELECT id, nro, nombre, nombre_norm, estado FROM afiliados WHERE ' . implode(' AND ', $where) . ' LIMIT 200', $p);
        $items = [];
        foreach ($rows as $r) {
            $pt = self::puntaje($r['nombre_norm'], $nq, $toks);
            if ($pt >= 0) {
                $items[] = ['pt' => $pt, 'act' => $r['estado'] === 'activo' ? 0 : 1, 'label' => $r['nombre'], 'sub' => 'N° ' . $r['nro'] . ($r['estado'] !== 'activo' ? ' · baja' : ''), 'nro' => (int)$r['nro'], 'id' => (int)$r['id']];
            }
        }
        usort($items, fn($a, $b) => [$a['pt'], $a['label']] <=> [$b['pt'], $b['label']]);
        $items = array_slice($items, 0, $max);
        usort($items, fn($a, $b) => $a['act'] <=> $b['act']);
        return array_map(fn($x) => array_diff_key($x, ['pt' => 1, 'act' => 1]), $items);
    }

    /** Puntaje de coincidencia de la maqueta: 0 empieza igual, 1 todas las palabras empiezan, 2 contiene. */
    public static function puntaje(string $labelNorm, string $qNorm, array $toks): int
    {
        if ($toks === []) {
            return -1;
        }
        $ws = preg_split('/[\s\/·,.-]+/u', $labelNorm) ?: [];
        if (str_starts_with($labelNorm, trim($qNorm))) {
            return 0;
        }
        $todas = true;
        foreach ($toks as $t) {
            $ok = false;
            foreach ($ws as $w) {
                if (str_starts_with($w, $t)) {
                    $ok = true;
                    break;
                }
            }
            if (!$ok) {
                $todas = false;
                break;
            }
        }
        if ($todas) {
            return 1;
        }
        if (mb_strlen(str_replace(' ', '', $qNorm)) >= 3) {
            foreach ($toks as $t) {
                if (!str_contains($labelNorm, $t)) {
                    return -1;
                }
            }
            return 2;
        }
        return -1;
    }

    /**
     * CU-05: padrón filtrado. El texto busca todas las palabras dentro de nombre + N°;
     * desde 4 dígitos también busca dentro del documento.
     */
    public static function padron(string $q, string $estado = 'activo'): array
    {
        $q = trim($q);
        $p = [];
        $w = [];
        if ($estado !== '') {
            $w[] = 'a.estado = :estado';
            $p['estado'] = $estado;
        }
        if ($q !== '') {
            $toks = array_values(array_filter(preg_split('/\s+/', Fmt::norm($q)) ?: []));
            $c = [];
            foreach ($toks as $i => $t) {
                $c[] = "(a.nombre_norm || ' ' || a.nro) LIKE :t$i";
                $p["t$i"] = '%' . $t . '%';
            }
            $cond = '(' . implode(' AND ', $c) . ')';
            $d = Fmt::docNorm($q);
            if (strlen($d) >= 4) {
                $cond = "($cond OR a.doc_norm LIKE :doc)";
                $p['doc'] = '%' . $d . '%';
            }
            $w[] = $cond;
        }
        $rows = self::db()->all(
            'SELECT a.*, g.plan_id, g.forma_pago,
                    (SELECT t.nombre FROM afiliados t WHERE t.grupo_id = a.grupo_id ORDER BY (t.estado = \'activo\') DESC, t.orden, t.nro LIMIT 1) AS paga,
                    (SELECT t.id FROM afiliados t WHERE t.grupo_id = a.grupo_id ORDER BY (t.estado = \'activo\') DESC, t.orden, t.nro LIMIT 1) AS paga_id
             FROM afiliados a JOIN grupos g ON g.id = a.grupo_id'
            . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . ' ORDER BY a.nro',
            $p
        );
        return array_map([self::class, 'hidratar'], $rows);
    }

    /** Activos por plan para el encabezado del padrón. */
    public static function activosPorPlan(): array
    {
        return array_column(self::db()->all("SELECT g.plan_id, count(*) AS n FROM afiliados a JOIN grupos g ON g.id = a.grupo_id WHERE a.estado = 'activo' GROUP BY g.plan_id"), 'n', 'plan_id');
    }

    /** ¿Hay un afiliado ACTIVO con ese documento? (P-08: solo contra activos) */
    public static function activoConDoc(string $doc, ?int $excluir = null): ?array
    {
        $d = Fmt::docNorm($doc);
        if ($d === '') {
            return null;
        }
        return self::hidratar(self::db()->one("SELECT * FROM afiliados WHERE doc_norm = :d AND estado = 'activo'" . ($excluir ? ' AND id <> :x' : '') . ' LIMIT 1', $excluir ? ['d' => $d, 'x' => $excluir] : ['d' => $d]));
    }

    // ------------------------------------------------------------------ CU-06 alta

    /**
     * Da de alta un grupo con su titular y familiares.
     * @param array $d plan, forma_pago, email, sin_email, fecha_alta, telefono, obs, promo_pct, promo_meses,
     *                 titular{apellido,nombre1,nombre2,doc,nac,categoria}, familia[{…}]
     * @return array{grupo_id:int, nros:int[], titular:array}
     */
    public static function alta(array $d): array
    {
        $plan = (string)($d['plan'] ?? '');
        $planes = Catalogo::planes();
        if (!isset($planes[$plan])) {
            throw new Falta('Elegí el plan.', ['plan']);
        }
        $p = $planes[$plan];
        $t = $d['titular'] ?? [];
        $fam = array_values($d['familia'] ?? []);
        $campos = [];
        $req = ['apellido' => 't_apellido', 'nombre1' => 't_nombre1', 'doc' => 't_doc', 'nac' => 't_nac'];
        foreach ($req as $k => $campo) {
            if (trim((string)($t[$k] ?? '')) === '') {
                $campos[] = $campo;
            }
        }
        if (!in_array($t['categoria'] ?? '', Catalogo::catTitular(), true)) {
            $campos[] = 't_categoria';
        }
        $sinEmail = !empty($d['sin_email']);
        $email = trim((string)($d['email'] ?? ''));
        if (!$sinEmail && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $campos[] = 'email';
        }
        $alta = (string)($d['fecha_alta'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $alta)) {
            $campos[] = 'fecha_alta';
        }
        $fi = fn(int $i) => 'f' . ($fam[$i]['_i'] ?? $i);
        foreach ($fam as $i => $f) {
            foreach (['apellido', 'nombre1', 'doc', 'nac'] as $k) {
                if (trim((string)($f[$k] ?? '')) === '') {
                    $campos[] = $fi($i) . "_$k";
                }
            }
            if (!in_array($f['categoria'] ?? '', Catalogo::catAdicional(), true)) {
                $campos[] = $fi($i) . '_categoria';
            }
        }
        if ($campos) {
            throw new Falta('Faltan datos obligatorios' . (in_array('email', $campos, true) ? ' (el email es obligatorio salvo que marques "No tiene email")' : '') . '.', $campos);
        }
        if ($p['individual'] && $fam) {
            throw new Falta("El {$p['nombre']} es individual: no se suman integrantes.");
        }
        // E5: precio para todas las categorías.
        foreach (array_merge([$t], $fam) as $m) {
            if (Catalogo::precio($m['categoria'], $plan) <= 0) {
                throw new Falta("El {$p['nombre']} todavía no tiene precio para todas las categorías (" . Catalogo::categoria($m['categoria'])['nombre'] . ').');
            }
        }
        // E1, E2, E3: documentos.
        $docs = [];
        foreach (array_merge([$t], $fam) as $i => $m) {
            $dn = Fmt::docNorm($m['doc']);
            if (isset($docs[$dn])) {
                throw new Falta('Dos integrantes tienen el mismo documento.', [$i === 0 ? 't_doc' : $fi($i - 1) . '_doc']);
            }
            $docs[$dn] = true;
            if ($ya = self::activoConDoc($m['doc'])) {
                if ($i === 0) {
                    throw new Falta("{$ya['nombre']} ya está en el padrón (afiliado N° {$ya['nro']}). No hace falta darlo de alta.", ['t_doc']);
                }
                throw new Falta("{$ya['nombre']} ya está afiliado (N° {$ya['nro']}).", [$fi($i - 1) . '_doc']);
            }
        }
        $promo = null;
        $pct = (int)($d['promo_pct'] ?? 0);
        if ($pct) {
            $meses = (int)($d['promo_meses'] ?? 0);
            if (!in_array($pct, Catalogo::PROMO_PCT, true) || !in_array($meses, Catalogo::PROMO_MESES, true)) {
                throw new Falta('La promoción tiene que ser de 5 a 40 % durante 1, 2, 3 o 6 meses.');
            }
            $desde = max(substr($alta, 0, 7), Config::inicio()['periodo']);
            $promo = ['pct' => $pct, 'meses' => $meses, 'desde' => $desde, 'hasta' => Fechas::perMas($desde, $meses - 1)];
        }
        $db = self::db();
        return $db->tx(function () use ($db, $plan, $d, $t, $fam, $sinEmail, $email, $alta, $promo, $p) {
            // N° correlativos y consecutivos para el grupo aunque haya dos altas a la vez.
            $db->exec('SELECT pg_advisory_xact_lock(424242)');
            $gid = (int)$db->insert('grupos', [
                'plan_id' => $plan, 'forma_pago' => in_array($d['forma_pago'] ?? '', Catalogo::MEDIOS, true) ? $d['forma_pago'] : 'Efectivo',
                'email' => $sinEmail ? '' : $email, 'sin_email' => $sinEmail, 'fecha_alta' => $alta,
                'promo_pct' => $promo['pct'] ?? null, 'promo_meses' => $promo['meses'] ?? null,
                'promo_desde' => $promo['desde'] ?? null, 'promo_hasta' => $promo['hasta'] ?? null,
            ]);
            $nros = [];
            foreach (array_merge([$t], $fam) as $i => $m) {
                $ap = Fmt::may($m['apellido']);
                $n1 = Fmt::may($m['nombre1']);
                $n2 = Fmt::may($m['nombre2'] ?? '');
                $nom = Fmt::nombreCompleto($ap, $n1, $n2);
                $nros[] = (int)$db->insert('afiliados', [
                    'grupo_id' => $gid, 'orden' => $i, 'apellido' => $ap, 'nombre1' => $n1, 'nombre2' => $n2,
                    'nombre' => $nom, 'nombre_norm' => Fmt::norm($nom), 'doc' => Fmt::doc($m['doc']) ?: trim($m['doc']),
                    'doc_norm' => Fmt::docNorm($m['doc']), 'nac' => $m['nac'] ?: null, 'categoria_id' => $m['categoria'],
                    'cuota' => Catalogo::precio($m['categoria'], $plan), 'fecha_alta' => $alta,
                    'telefono' => $i === 0 ? trim((string)($d['telefono'] ?? '')) : trim((string)($m['telefono'] ?? '')),
                    'obs' => $i === 0 ? trim((string)($d['obs'] ?? '')) : '',
                ], 'nro');
            }
            $tit = self::afiliadoPorNro($nros[0]);
            Historial::log($db, 'Alta', "{$tit['nombre']} (N° {$nros[0]})" . (count($nros) > 1 ? ' con ' . (count($nros) - 1) . ' familiar(es): N° ' . implode(', ', array_slice($nros, 1)) : '') . " · {$p['nombre']}");
            if ($promo) {
                Historial::log($db, 'Promoción', "{$tit['nombre']}: {$promo['pct']}% durante {$promo['meses']} " . ($promo['meses'] === 1 ? 'mes' : 'meses') . ' desde ' . Fmt::perLabel($promo['desde']));
            }
            return ['grupo_id' => $gid, 'nros' => $nros, 'titular' => $tit];
        });
    }

    // ------------------------------------------------------------------ CU-08 edición

    public static function editar(int $id, array $d): array
    {
        AutorizacionAdmin::exigir('editar');
        $a = self::afiliado($id) ?? throw new HttpError(404);
        $g = self::grupo($a['grupo_id']);
        $tit = self::titular($g['id']);
        $esTit = $tit && $tit['id'] === $a['id'];
        $ap = Fmt::may($d['apellido'] ?? '');
        $n1 = Fmt::may($d['nombre1'] ?? '');
        $n2 = Fmt::may($d['nombre2'] ?? '');
        if ($ap === '' || $n1 === '' || trim((string)($d['doc'] ?? '')) === '') {
            throw new Falta('Apellido, primer nombre y documento son obligatorios.', array_keys(array_filter(['apellido' => $ap === '', 'nombre1' => $n1 === '', 'doc' => trim((string)($d['doc'] ?? '')) === ''])));
        }
        $cat = (string)($d['categoria'] ?? $a['categoria_id']);
        if (!isset(Catalogo::categorias()[$cat])) {
            throw new Falta('Categoría inválida.', ['categoria']);
        }
        if ($a['activo'] && ($ya = self::activoConDoc((string)$d['doc'], $a['id']))) {
            throw new Falta("Ese documento ya es de {$ya['nombre']} (N° {$ya['nro']}).", ['doc']);
        }
        $nuevo = [
            'apellido' => $ap, 'nombre1' => $n1, 'nombre2' => $n2,
            'nombre' => Fmt::nombreCompleto($ap, $n1, $n2),
            'doc' => Fmt::doc((string)$d['doc']) ?: trim((string)$d['doc']),
            'nac' => ($d['nac'] ?? '') ?: null,
            'categoria_id' => $cat,
            'cuota' => round(Fmt::parseMonto((string)($d['cuota'] ?? $a['cuota'])), 2),
            'telefono' => trim((string)($d['telefono'] ?? '')),
            'fecha_alta' => ($d['fecha_alta'] ?? '') ?: $a['fecha_alta'],
            'obs' => trim((string)($d['obs'] ?? '')),
        ];
        $planNuevo = $esTit && !empty($d['plan']) ? (string)$d['plan'] : $g['plan_id'];
        if (!isset(Catalogo::planes()[$planNuevo])) {
            throw new Falta('Plan inválido.', ['plan']);
        }
        $cambiaMonto = abs($nuevo['cuota'] - $a['cuota']) > 0.004 || $planNuevo !== $g['plan_id'];
        if ($cambiaMonto) {
            AutorizacionAdmin::exigir('montos');
        }
        $etiq = ['nombre' => 'nombre', 'doc' => 'CUIT/DNI', 'categoria_id' => 'categoría', 'cuota' => 'cuota', 'telefono' => 'teléfono', 'fecha_alta' => 'fecha de alta', 'nac' => 'fecha de nacimiento', 'obs' => 'observaciones'];
        $cambios = [];
        foreach ($etiq as $k => $t) {
            $antes = $a[$k] ?? null;
            $desp = $nuevo[$k];
            if ($k === 'cuota' ? abs((float)$antes - (float)$desp) > 0.004 : (string)$antes !== (string)$desp) {
                $f = fn($v) => $k === 'cuota' ? Fmt::money((float)$v) : ($k === 'categoria_id' ? Catalogo::categoria((string)$v)['nombre'] : (($v ?? '') === '' ? '—' : (in_array($k, ['nac', 'fecha_alta'], true) ? Fmt::fd((string)$v) : (string)$v)));
                $cambios[] = "$t: " . $f($antes) . ' → ' . $f($desp);
            }
        }
        $gNuevo = [];
        if ($esTit) {
            if ($planNuevo !== $g['plan_id']) {
                $cambios[] = 'plan del grupo: ' . Catalogo::plan($g['plan_id'])['nombre'] . ' → ' . Catalogo::plan($planNuevo)['nombre'];
                $gNuevo['plan_id'] = $planNuevo;
            }
            if (isset($d['forma_pago']) && in_array($d['forma_pago'], Catalogo::MEDIOS, true) && $d['forma_pago'] !== $g['forma_pago']) {
                $cambios[] = "forma de pago: {$g['forma_pago']} → {$d['forma_pago']}";
                $gNuevo['forma_pago'] = $d['forma_pago'];
            }
            if (array_key_exists('email', $d)) {
                $em = trim((string)$d['email']);
                if ($em !== '' && !filter_var($em, FILTER_VALIDATE_EMAIL)) {
                    throw new Falta('El email no es válido.', ['email']);
                }
                if ($em !== $g['email']) {
                    $cambios[] = 'email: ' . ($g['email'] ?: '—') . ' → ' . ($em ?: '—');
                    $gNuevo['email'] = $em;
                    $gNuevo['sin_email'] = $em === '';
                }
            }
            if (isset($d['fcond'])) {
                $nf = ['fiscal_cond' => isset(Catalogo::COND_IVA[$d['fcond']]) ? $d['fcond'] : 'CF', 'fiscal_cuit' => Fmt::doc((string)($d['fcuit'] ?? '')), 'fiscal_razon' => Fmt::may($d['frazon'] ?? '')];
                if ($nf['fiscal_cond'] !== 'CF' && strlen(preg_replace('/\D/', '', $nf['fiscal_cuit'])) !== 11) {
                    throw new Falta('Para facturar a un Responsable Inscripto, Monotributista o Exento hace falta el CUIT de 11 dígitos.', ['fcuit']);
                }
                if ($nf['fiscal_cond'] !== $g['fiscal_cond'] || $nf['fiscal_cuit'] !== $g['fiscal_cuit'] || $nf['fiscal_razon'] !== $g['fiscal_razon']) {
                    $cambios[] = 'facturar a: ' . Catalogo::COND_IVA[$g['fiscal_cond']] . ($g['fiscal_cuit'] ? ' ' . $g['fiscal_cuit'] : '') . ' → ' . Catalogo::COND_IVA[$nf['fiscal_cond']] . ($nf['fiscal_cuit'] ? ' ' . $nf['fiscal_cuit'] : '') . ($nf['fiscal_razon'] ? " ({$nf['fiscal_razon']})" : '');
                    $gNuevo += $nf;
                }
            }
        }
        if (!$cambios) {
            return ['cambios' => []];
        }
        $db = self::db();
        $db->tx(function () use ($db, $a, $g, $nuevo, $gNuevo, $planNuevo, $cambios) {
            $db->update('afiliados', $nuevo + ['nombre_norm' => Fmt::norm($nuevo['nombre']), 'doc_norm' => Fmt::docNorm($nuevo['doc'])], 'id = :id', ['id' => $a['id']]);
            if ($gNuevo) {
                $db->update('grupos', $gNuevo, 'id = :gid', ['gid' => $g['id']]);
            }
            // Al cambiar el plan, se actualiza la cuota de los otros integrantes que pagaban el precio de lista.
            if ($planNuevo !== $g['plan_id']) {
                foreach (self::miembros($g['id']) as $m) {
                    if ($m['id'] === $a['id']) {
                        continue;
                    }
                    $nv = Catalogo::precio($m['categoria_id'], $planNuevo);
                    if ($nv > 0 && abs($m['cuota'] - Catalogo::precio($m['categoria_id'], $g['plan_id'])) < 0.005) {
                        $db->exec('UPDATE afiliados SET cuota = :c WHERE id = :id', ['c' => $nv, 'id' => $m['id']]);
                    }
                }
            }
            Historial::log($db, 'Modificación', "{$nuevo['nombre']} (N° {$a['nro']}): " . implode('; ', $cambios));
        });
        return ['cambios' => $cambios];
    }

    // ------------------------------------------------------------------ CU-09 promoción del grupo

    /** Mes de inicio propuesto: el siguiente al fin de la promoción vigente, o el mes que viene; nunca antes del inicio. */
    public static function inicioPromoPropuesto(array $g, ?string $hoy = null): string
    {
        $hoy ??= Fechas::hoy();
        $mesQueViene = Fechas::perMas(substr($hoy, 0, 7), 1);
        $base = $g['promo'] && $g['promo']['hasta'] >= substr($hoy, 0, 7) ? Fechas::perMas($g['promo']['hasta'], 1) : $mesQueViene;
        return max($base, Config::inicio()['periodo']);
    }

    public static function aplicarPromo(int $grupoId, int $pct, int $meses, string $desde, bool $retencion = false): void
    {
        AutorizacionAdmin::exigir('montos');
        if (!in_array($pct, Catalogo::PROMO_PCT, true) || !in_array($meses, Catalogo::PROMO_MESES, true) || !preg_match('/^\d{4}-\d{2}$/', $desde)) {
            throw new Falta('Elegí el porcentaje (5 a 40 %), la duración (1, 2, 3 o 6 meses) y el mes de inicio.');
        }
        if ($desde < Config::inicio()['periodo']) {
            throw new Falta('La promoción no puede empezar antes de ' . Fmt::perLabel(Config::inicio()['periodo']) . '.');
        }
        $g = self::grupo($grupoId) ?? throw new HttpError(404);
        $hasta = Fechas::perMas($desde, $meses - 1);
        $antes = $g['promo'] ? "{$g['promo']['pct']}% de " . Fmt::perLabel($g['promo']['desde']) . ' a ' . Fmt::perLabel($g['promo']['hasta']) : 'sin promoción';
        self::db()->update('grupos', ['promo_pct' => $pct, 'promo_meses' => $meses, 'promo_desde' => $desde, 'promo_hasta' => $hasta, 'promo_retencion' => $retencion], 'id = :id', ['id' => $grupoId]);
        $t = self::titular($grupoId);
        Historial::log(self::db(), 'Promoción', "{$t['nombre']}: $antes → $pct% de " . Fmt::perLabel($desde) . ' a ' . Fmt::perLabel($hasta) . ($retencion ? ' (retención)' : ''));
    }

    public static function quitarPromo(int $grupoId): void
    {
        AutorizacionAdmin::exigir('montos');
        $g = self::grupo($grupoId) ?? throw new HttpError(404);
        if (!$g['promo']) {
            throw new Falta('El grupo no tiene promoción.');
        }
        self::db()->update('grupos', ['promo_pct' => null, 'promo_meses' => null, 'promo_desde' => null, 'promo_hasta' => null, 'promo_retencion' => false], 'id = :id', ['id' => $grupoId]);
        $t = self::titular($grupoId);
        Historial::log(self::db(), 'Promoción', "{$t['nombre']}: {$g['promo']['pct']}% de " . Fmt::perLabel($g['promo']['desde']) . ' a ' . Fmt::perLabel($g['promo']['hasta']) . ' → sin promoción');
    }

    // ------------------------------------------------------------------ CU-10, CU-11

    public static function darDeBaja(int $id, string $motivo, string $comentario = ''): void
    {
        $a = self::afiliado($id) ?? throw new HttpError(404);
        if (!$a['activo']) {
            throw new Falta('Este afiliado ya está dado de baja.');
        }
        self::db()->exec("UPDATE afiliados SET estado = 'baja', fecha_baja = :f WHERE id = :id", ['f' => Fechas::hoy(), 'id' => $id]);
        $mot = Retencion::motivoTexto($motivo);
        Historial::log(self::db(), 'Baja', "{$a['nombre']} (N° {$a['nro']})" . ($mot ? " · motivo: $mot" : '') . ($comentario ? " · $comentario" : ''));
    }

    public static function reactivar(int $id): void
    {
        $a = self::afiliado($id) ?? throw new HttpError(404);
        if ($a['activo']) {
            throw new Falta('El afiliado ya está activo.');
        }
        if ($ya = self::activoConDoc($a['doc'], $a['id'])) {
            throw new Falta("No se puede reactivar: el documento ya es de {$ya['nombre']} (N° {$ya['nro']}), que está activo.");
        }
        self::db()->exec("UPDATE afiliados SET estado = 'activo', fecha_baja = NULL WHERE id = :id", ['id' => $id]);
        Historial::log(self::db(), 'Reactivación', "{$a['nombre']} (N° {$a['nro']})");
    }

    /** Nombres de pila del padrón (para separar apellido y nombre de un interesado). */
    public static function nombresPadron(): array
    {
        $r = [];
        foreach (self::db()->all('SELECT DISTINCT nombre1, nombre2 FROM afiliados') as $x) {
            foreach ([$x['nombre1'], $x['nombre2']] as $n) {
                if ($n) {
                    $r[Fmt::norm($n)] = true;
                }
            }
        }
        return array_keys($r);
    }

    /** Primer nombre con mayúscula inicial ("María"). */
    public static function nomPila(array $a): string
    {
        $n = $a['nombre1'] ?: (explode(' ', (string)$a['nombre'])[1] ?? $a['nombre']);
        return Fmt::capi($n);
    }
}
