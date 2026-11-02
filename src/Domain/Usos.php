<?php
declare(strict_types=1);

namespace App\Domain;

use App\Core\App;
use App\Core\Falta;
use App\Core\Historial;
use App\Core\HttpError;

/**
 * Usos de beneficios en unidades (CU-22), autorizaciones y auditoría (CU-20, CU-21)
 * y consumos en comercios adheridos (CU-23).
 */
final class Usos
{
    /** Numeración atómica: devuelve el siguiente número de la clave, nunca menor que $minimo + 1. */
    public static function siguiente(string $clave, int $minimo = 0): int
    {
        return (int)App::$db->val(
            'INSERT INTO numeradores (clave, ultimo) VALUES (:c, :m + 1)
             ON CONFLICT (clave) DO UPDATE SET ultimo = greatest(numeradores.ultimo, :m) + 1 RETURNING ultimo',
            ['c' => $clave, 'm' => $minimo]
        );
    }

    /** Guarda un uso con sus líneas. */
    private static function guardarUso(array $a, int $unidadId, array $calc, string $usuario, ?int $autId = null, bool $pendiente = false): array
    {
        $db = App::$db;
        $u = Catalogo::unidadPorId($unidadId);
        $n = self::siguiente('U-' . $unidadId);
        $numero = sprintf('U%05d-%08d', (int)$u['punto_venta'], $n);
        $hoy = Fechas::hoy();
        $ahora = Fechas::ahora()->format('H:i:s');
        $usoId = (int)$db->insert('usos', [
            'numero' => $numero, 'unidad_id' => $unidadId, 'afiliado_id' => $a['id'], 'grupo_id' => $a['grupo_id'],
            'plan_id' => $calc['plan'], 'fecha' => $hoy, 'hora' => $ahora, 'usuario' => $usuario,
            'autorizacion_id' => $autId, 'pendiente_unidad' => $pendiente,
        ]);
        foreach ($calc['lineas'] as $l) {
            $db->insert('usos_lineas', [
                'uso_id' => $usoId, 'prestacion_cod' => $l['cod'], 'nombre' => $l['nombre'], 'cantidad' => $l['cantidad'],
                'gratis' => $l['gratis'], 'pct' => $l['pct'], 'precio' => $l['precio'], 'precio_informado' => $l['precio_informado'],
                'lista' => $l['lista'], 'ahorro' => $l['ahorro'], 'total' => $l['total'],
            ]);
        }
        // Saldo de sin cargo después del uso, para el comprobante.
        $saldos = [];
        foreach ($calc['lineas'] as $l) {
            $r = $l['regla'];
            if ($r['gratis']) {
                $us = Beneficios::usadosGratis($a, $l['cod'], $r);
                $saldos[] = ['nombre' => $l['nombre'], 'usados' => $us, 'total' => $r['gratis'], 'quedan' => max(0, $r['gratis'] - $us), 'vent' => Beneficios::VENT_TXT[$r['per']], 'grupo' => $r['por_grupo']];
            }
        }
        $db->exec('UPDATE usos SET saldos = :s WHERE id = :id', ['s' => json_encode($saldos, JSON_UNESCAPED_UNICODE), 'id' => $usoId]);
        return ['id' => $usoId, 'numero' => $numero];
    }

    /**
     * CU-22: la unidad registra el uso. Si hay una autorización pendiente y la cantidad es 1,
     * confirma esa autorización y no descuenta dos veces.
     */
    public static function registrarEnUnidad(array $a, int $unidadId, string $cod, int $cant, float $precio, string $usuario): array
    {
        $p = Catalogo::prestacion($cod);
        if (!$p || !$p['activo'] || (int)$p['unidad_id'] !== $unidadId) {
            throw new Falta('Esa prestación no es de tu unidad.');
        }
        if (!$a['activo']) {
            throw new Falta('Está dado de baja: no tiene beneficios. Se le cobra como particular.');
        }
        $cant = max(1, $cant);
        return App::$db->tx(function () use ($a, $unidadId, $cod, $cant, $precio, $usuario, $p) {
            App::$db->exec('SELECT pg_advisory_xact_lock(:k)', ['k' => 700000 + $a['grupo_id']]);
            $pend = Beneficios::autorizacionPendiente($a['id'], $cod);
            if ($pend && $cant === 1) {
                App::$db->exec('UPDATE usos SET pendiente_unidad = false, confirmo = :c, fecha_conf = :f WHERE id = :id', ['c' => $usuario, 'f' => Fechas::hoy(), 'id' => $pend['uso_id']]);
                if ($precio > 0) {
                    $ah = round($precio * ((int)$pend['gratis'] ? 1 : (float)$pend['pct'] / 100), 2);
                    App::$db->exec('UPDATE usos_lineas SET precio = :p, precio_informado = true, lista = :p, ahorro = :a, total = :t WHERE id = :id', ['p' => $precio, 'a' => $ah, 't' => $precio - $ah, 'id' => $pend['linea_id']]);
                }
                Historial::log(App::$db, 'Uso en unidad', "{$a['nombre']} (N° {$a['nro']}) · {$p['nombre']} · confirmó la autorización ya emitida", $usuario);
                return ['confirmada' => true, 'mensaje' => 'Uso registrado: ya estaba autorizado por Integral, no se descuenta dos veces.'];
            }
            $calc = Beneficios::calcular($a, [['cod' => $cod, 'cant' => $cant, 'precio' => $precio]]);
            $u = self::guardarUso($a, $unidadId, $calc, $usuario);
            $L = $calc['lineas'][0];
            $txt = $L['gratis'] === $L['cantidad'] ? 'sin cargo' : ($L['gratis'] ? "{$L['gratis']} sin cargo y " . ($L['cantidad'] - $L['gratis']) . ' con ' . Beneficios::pct($L['pct']) : ($L['pct'] ? Beneficios::pct($L['pct']) . ' de descuento' : 'sin beneficio'));
            Historial::log(App::$db, 'Uso en unidad', "{$u['numero']} · {$a['nombre']} (N° {$a['nro']}) · {$p['nombre']} × $cant · $txt", $usuario);
            return ['confirmada' => false, 'uso' => $u, 'linea' => $L, 'mensaje' => "Uso registrado: $txt."];
        });
    }

    // ------------------------------------------------------------------ CU-20 autorizaciones

    /** Emite la autorización (o registra el rechazo) y descuenta el beneficio. */
    public static function autorizar(array $a, string $cod, bool $emitir, array $usuario): array
    {
        $p = Catalogo::prestacion($cod) ?? throw new Falta('Elegí la prestación.');
        if (!$p['activo']) {
            throw new Falta('Esa prestación ya no está activa.');
        }
        $v = Beneficios::verificar($a, $p);
        if ($emitir && !$v['ok']) {
            throw new Falta('No corresponde autorizar: ' . $v['motivo'] . '.');
        }
        if ($emitir && $p['requiere_auditoria'] && !$usuario['admin']) {
            throw new Falta('Esta prestación requiere auditoría: solo podés enviarla a auditoría.');
        }
        return App::$db->tx(function () use ($a, $p, $v, $emitir, $usuario) {
            $estado = $emitir ? 'autorizada' : 'rechazada';
            $id = (int)App::$db->insert('autorizaciones', [
                'afiliado_id' => $a['id'], 'prestacion_cod' => $p['cod'], 'fecha' => Fechas::hoy(), 'estado' => $estado,
                'motivo' => $emitir ? '' : ($v['motivo'] ?: 'Rechazada'), 'controles' => json_encode(self::controles($v), JSON_UNESCAPED_UNICODE),
                'usuario' => $usuario['nombre'], 'usuario_id' => $usuario['id'],
            ]);
            $aut = App::$db->one('SELECT * FROM autorizaciones WHERE id = :id', ['id' => $id]);
            $consumo = '';
            if ($emitir) {
                $consumo = self::consumoAutorizacion($aut, $usuario['nombre']);
            }
            Historial::log(App::$db, 'Autorización', ($emitir ? "N° {$aut['nro']} emitida" : "Rechazo N° {$aut['nro']}") . ": {$p['nombre']} para {$a['nombre']}" . ($consumo ? " ($consumo)" : ($emitir ? '' : " · {$v['motivo']}")));
            return ['nro' => (int)$aut['nro'], 'consumo' => $consumo, 'ok' => $emitir];
        });
    }

    private static function controles(array $v): array
    {
        return array_map(fn($c) => ['ok' => $c['ok'], 't' => $c['t'], 'd' => $c['d']], $v['checks']);
    }

    /** Descuenta el beneficio de una autorización: crea el uso pendiente de confirmar por la unidad. */
    public static function consumoAutorizacion(array $aut, string $usuario): string
    {
        if (App::$db->val('SELECT 1 FROM usos WHERE autorizacion_id = :id', ['id' => $aut['id']])) {
            return (string)$aut['consumo'];
        }
        $a = Padron::afiliado((int)$aut['afiliado_id']);
        $p = Catalogo::prestacion($aut['prestacion_cod']);
        $calc = Beneficios::calcular($a, [['cod' => $p['cod'], 'cant' => 1]]);
        self::guardarUso($a, (int)$p['unidad_id'], $calc, "$usuario (autorización N° {$aut['nro']})", (int)$aut['id'], true);
        $L = $calc['lineas'][0];
        $consumo = $L['gratis'] ? 'sin cargo' : ($L['pct'] ? Beneficios::pct($L['pct']) : 'sin beneficio');
        App::$db->exec('UPDATE autorizaciones SET consumo = :c WHERE id = :id', ['c' => $consumo, 'id' => $aut['id']]);
        return $consumo;
    }

    /** CU-20 A3: envía la solicitud a auditoría con una observación. */
    public static function enviarAuditoria(array $a, string $cod, string $obs, array $usuario): int
    {
        $p = Catalogo::prestacion($cod) ?? throw new Falta('Elegí la prestación.');
        $v = Beneficios::verificar($a, $p);
        if ((!$v['ok'] || $p['requiere_auditoria']) && trim($obs) === '') {
            throw new Falta('Escribí una observación para el auditor.', ['obs']);
        }
        $nro = (int)App::$db->val(
            "INSERT INTO autorizaciones (afiliado_id, prestacion_cod, fecha, estado, observacion, controles, usuario, usuario_id)
             VALUES (:a, :c, :f, 'pendiente', :o, :ch, :u, :uid) RETURNING nro",
            ['a' => $a['id'], 'c' => $p['cod'], 'f' => Fechas::hoy(), 'o' => trim($obs), 'ch' => json_encode(self::controles($v), JSON_UNESCAPED_UNICODE), 'u' => $usuario['nombre'], 'uid' => $usuario['id']]
        );
        Historial::log(App::$db, 'Auditoría', "Solicitud N° $nro: {$p['nombre']} para {$a['nombre']}");
        return $nro;
    }

    /** CU-21: aprobar o rechazar una solicitud. Aprobar dos veces no descuenta dos veces. */
    public static function auditar(int $id, bool $aprobar, string $texto, array $usuario): array
    {
        if (!$usuario['admin']) {
            throw new HttpError(403, 'Solo un administrador puede auditar.');
        }
        if (!$aprobar && trim($texto) === '') {
            throw new Falta('Escribí el motivo del rechazo.', ['texto']);
        }
        return App::$db->tx(function () use ($id, $aprobar, $texto, $usuario) {
            $x = App::$db->one('SELECT * FROM autorizaciones WHERE id = :id FOR UPDATE', ['id' => $id]) ?? throw new HttpError(404);
            if ($x['estado'] !== 'pendiente') {
                throw new Falta("La solicitud N° {$x['nro']} ya fue resuelta.");
            }
            App::$db->exec(
                'UPDATE autorizaciones SET estado = :e, respuesta = :r, auditor = :au, resuelta_en = now() WHERE id = :id',
                ['e' => $aprobar ? 'aprobada' : 'desaprobada', 'r' => trim($texto), 'au' => $usuario['nombre'], 'id' => $id]
            );
            $consumo = $aprobar ? self::consumoAutorizacion($x, $usuario['nombre']) : '';
            Historial::log(App::$db, 'Auditoría', "Solicitud N° {$x['nro']} " . ($aprobar ? 'aprobada' : 'rechazada') . (trim($texto) ? ': ' . trim($texto) : ''));
            return ['nro' => (int)$x['nro'], 'consumo' => $consumo];
        });
    }

    // ------------------------------------------------------------------ CU-23 comercios

    /** Busca al afiliado por DNI o N° (los comercios solo buscan así). */
    public static function buscarParaComercio(string $q): ?array
    {
        $t = trim($q);
        if ($t === '') {
            return null;
        }
        $d = preg_replace('/\D/', '', $t);
        if ($d !== '' && strlen($d) <= 6) {
            $a = Padron::afiliadoPorNro((int)$d);
            if ($a) {
                return $a;
            }
        }
        $dn = Fmt::docNorm($t);
        return strlen($dn) >= 6 ? Padron::buscarUno($dn) : null;
    }

    /**
     * CU-23: verifica si el afiliado tiene el beneficio del rubro del comercio.
     * @return array{ok:bool,txt:string,a?:array,nom?:string,plan?:array,car?:string,rubro?:array,enPer?:int,anio?:array}
     */
    public static function verificarComercio(string $q, array $comercio, ?string $hoy = null): ?array
    {
        $hoy ??= Fechas::hoy();
        if (trim($q) === '') {
            return null;
        }
        $a = self::buscarParaComercio($q);
        if (!$a) {
            return ['ok' => false, 'txt' => 'No es afiliado de Integral del Este.'];
        }
        $g = Padron::grupo($a['grupo_id']);
        $pl = Catalogo::plan($g['plan_id']);
        $nom = trim(Padron::nomPila($a) . ' ' . Fmt::capi($a['apellido']));
        $base = ['a' => $a, 'nom' => $nom];
        if (!$a['activo']) {
            return $base + ['ok' => false, 'txt' => 'No tiene el beneficio: el afiliado está dado de baja.'];
        }
        if (Padron::cuenta($g['id'], $hoy)['vencidas']) {
            return $base + ['ok' => false, 'txt' => 'No tiene el beneficio en este momento: su afiliación no está al día. Puede consultar en Integral del Este.'];
        }
        $b = null;
        foreach (Catalogo::beneficiosPlan() as $x) {
            if ($x['rubro'] === $comercio['comercio_rubro']) {
                $b = $x['planes'][$g['plan_id']] ?? null;
            }
        }
        if (!$b) {
            return $base + ['ok' => false, 'txt' => "Su plan ({$pl['nombre']}) no incluye este beneficio."];
        }
        $m = self::mesesCarencia($b[1]);
        $desde = $m ? Fechas::sumarMeses($a['fecha_alta'], $m) : null;
        if ($desde && $desde > $hoy) {
            return $base + ['ok' => false, 'txt' => 'Todavía no tiene el beneficio: lo tiene desde el ' . Fmt::fd($desde) . '.'];
        }
        $rr = Catalogo::rubros()[$comercio['comercio_rubro']] ?? ['max_pct' => 0, 'limite' => 0, 'per' => 'anio'];
        $enPer = (int)App::$db->val('SELECT count(*) FROM consumos_comercio WHERE afiliado_id = :a AND rubro = :r AND fecha >= :d AND fecha <= :h', ['a' => $a['id'], 'r' => $comercio['comercio_rubro'], 'd' => Fechas::inicioVentana($rr['per'], $hoy), 'h' => $hoy]);
        if ((int)$rr['limite'] && $enPer >= (int)$rr['limite']) {
            return $base + ['ok' => false, 'txt' => "Ya usó el beneficio $enPer " . ($enPer === 1 ? 'vez' : 'veces') . ' ' . Beneficios::VENT_TXT[$rr['per']] . " (límite: {$rr['limite']})."];
        }
        $anio = App::$db->one('SELECT count(*) AS n, coalesce(sum(total),0) AS total, coalesce(sum(ahorro),0) AS ahorro FROM consumos_comercio WHERE afiliado_id = :a AND rubro = :r AND fecha >= :d', ['a' => $a['id'], 'r' => $comercio['comercio_rubro'], 'd' => substr($hoy, 0, 4) . '-01-01']);
        return $base + ['ok' => true, 'plan' => $pl, 'txt' => $b[0], 'car' => $m === null ? $b[1] : '', 'rubro' => $rr, 'enPer' => $enPer, 'anio' => $anio];
    }

    /** "6 meses" → 6; "1 año" → 12; "Sin carencias" → 0; texto compuesto → null */
    public static function mesesCarencia(string $t): ?int
    {
        $t = Fmt::norm($t);
        if (str_contains($t, 'sin carencia')) {
            return 0;
        }
        if (!preg_match('/^(\d+)\s*(mes|meses|ano|anos)/', $t, $m)) {
            return null;
        }
        return str_starts_with($m[2], 'ano') ? (int)$m[1] * 12 : (int)$m[1];
    }

    /** CU-23: registra el consumo. El porcentaje no puede superar el máximo del rubro. */
    public static function registrarConsumo(string $q, array $comercio, string $detalle, float $importe, float $pct): array
    {
        $v = self::verificarComercio($q, $comercio);
        if (!$v || !$v['ok']) {
            throw new Falta($v['txt'] ?? 'Verificá al afiliado primero.');
        }
        if (trim($detalle) === '') {
            throw new Falta('Escribí qué compró o qué servicio usó.', ['detalle']);
        }
        if ($importe <= 0) {
            throw new Falta('Cargá el importe.', ['importe']);
        }
        $max = (float)$v['rubro']['max_pct'];
        $pct = max(0.0, $max > 0 ? min($pct, $max) : $pct);
        $ah = round($importe * $pct) / 100;
        App::$db->insert('consumos_comercio', [
            'fecha' => Fechas::hoy(), 'hora' => Fechas::ahora()->format('H:i:s'), 'usuario_id' => $comercio['id'],
            'comercio' => $comercio['comercio_nombre'], 'rubro' => $comercio['comercio_rubro'], 'afiliado_id' => $v['a']['id'],
            'detalle' => trim($detalle), 'importe' => $importe, 'pct' => $pct, 'ahorro' => $ah, 'total' => $importe - $ah,
        ]);
        Historial::log(App::$db, 'Consumo en comercio', "{$comercio['comercio_nombre']} · {$v['a']['nombre']} (N° {$v['a']['nro']}) · " . Fmt::money($importe) . ' − ' . Beneficios::pct($pct));
        return ['pct' => $pct, 'ahorro' => $ah, 'total' => $importe - $ah];
    }
}
