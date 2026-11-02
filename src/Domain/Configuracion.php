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
use App\Core\Usuarios;

/**
 * Configuración (CU-40 a CU-49): cada bloque marcado (C) calcula los cambios sin aplicarlos,
 * los confirma el administrador con su clave y se guarda una instantánea para deshacer.
 */
final class Configuracion
{
    public const BLOQUES = [
        'prestaciones' => 'Prestaciones', 'reglas' => 'Reglas de beneficios', 'rubros' => 'Comercios adheridos',
        'precios' => 'Precios', 'pv' => 'Puntos de venta', 'cfg' => 'Vencimiento y carencias',
        'permisos' => 'Qué requiere administrador', 'mail' => 'Mails a los afiliados', 'importar' => 'Importación de precios',
    ];
    /** Bloques que modifican montos: requieren el permiso "Modificar montos". */
    public const DE_MONTOS = ['prestaciones', 'reglas', 'precios', 'importar'];

    // ------------------------------------------------------------------ instantáneas (CU-49)

    /** Estado de la configuración que se puede deshacer (no incluye afiliados, facturas ni cobros). */
    public static function snapshot(): array
    {
        $db = App::$db;
        return [
            'planes' => $db->all('SELECT id, alicuota FROM planes ORDER BY id'),
            'precios' => $db->all('SELECT * FROM precios ORDER BY plan_id, categoria_id'),
            'prestaciones' => $db->all('SELECT cod, nombre, tipo, unidad_id, valor_ref, requiere_auditoria, activo, iva, orden, actualizado_en, actualizado_por FROM prestaciones ORDER BY cod'),
            'reglas' => $db->all('SELECT * FROM reglas_beneficio ORDER BY plan_id, prestacion_cod'),
            'rubros' => $db->all('SELECT * FROM rubros_comercio ORDER BY nombre'),
            'config' => array_column(array_map(fn($r) => ['k' => $r['clave'], 'v' => json_decode((string)$r['valor'], true)], $db->all("SELECT clave, valor FROM config WHERE clave IN ('dia_vto','carencias','punto_venta','ultimos_numeros','permisos','mail')")), 'v', 'k'),
        ];
    }

    public static function restaurar(array $s): void
    {
        $db = App::$db;
        foreach ($s['planes'] as $p) {
            $db->exec('UPDATE planes SET alicuota = :a WHERE id = :id', ['a' => $p['alicuota'], 'id' => $p['id']]);
        }
        foreach ($s['precios'] as $p) {
            $db->exec('INSERT INTO precios (plan_id, categoria_id, precio) VALUES (:p, :c, :v) ON CONFLICT (plan_id, categoria_id) DO UPDATE SET precio = EXCLUDED.precio', ['p' => $p['plan_id'], 'c' => $p['categoria_id'], 'v' => $p['precio']]);
        }
        $cods = [];
        foreach ($s['prestaciones'] as $p) {
            $cods[] = $p['cod'];
            $p['requiere_auditoria'] = (bool)$p['requiere_auditoria'];
            $p['activo'] = (bool)$p['activo'];
            $db->exec(
                'INSERT INTO prestaciones (cod, nombre, tipo, unidad_id, valor_ref, requiere_auditoria, activo, iva, orden, actualizado_en, actualizado_por)
                 VALUES (:cod, :nombre, :tipo, :unidad_id, :valor_ref, :requiere_auditoria, :activo, :iva, :orden, :actualizado_en, :actualizado_por)
                 ON CONFLICT (cod) DO UPDATE SET nombre = EXCLUDED.nombre, tipo = EXCLUDED.tipo, valor_ref = EXCLUDED.valor_ref, requiere_auditoria = EXCLUDED.requiere_auditoria,
                   activo = EXCLUDED.activo, iva = EXCLUDED.iva, actualizado_en = EXCLUDED.actualizado_en, actualizado_por = EXCLUDED.actualizado_por',
                $p
            );
        }
        // Las prestaciones agregadas después de la instantánea se quitan (sus usos se conservan).
        if ($cods) {
            $db->exec('UPDATE prestaciones SET activo = false WHERE NOT (cod = ANY(:c))', ['c' => Db::arrayPg($cods)]);
        }
        $db->exec('DELETE FROM reglas_beneficio');
        foreach ($s['reglas'] as $r) {
            $r['por_grupo'] = (bool)$r['por_grupo'];
            $db->insert('reglas_beneficio', $r, 'plan_id');
        }
        foreach ($s['rubros'] as $r) {
            $db->exec('INSERT INTO rubros_comercio (nombre, max_pct, limite, per, orden) VALUES (:nombre, :max_pct, :limite, :per, :orden) ON CONFLICT (nombre) DO UPDATE SET max_pct = EXCLUDED.max_pct, limite = EXCLUDED.limite, per = EXCLUDED.per', $r);
        }
        foreach ($s['config'] as $k => $v) {
            Config::set($k, $v);
        }
        Catalogo::limpiar();
        Config::limpiar();
    }

    // ------------------------------------------------------------------ cálculo de cambios por bloque

    /**
     * Calcula los cambios de un bloque sin aplicarlos.
     * @return array{cambios:array<int,array{t:string,antes:string,despues:string}>,alertas:string[],aplicar:callable}
     */
    public static function preparar(string $bloque, array $d): array
    {
        $m = 'b' . ucfirst($bloque);
        if (!isset(self::BLOQUES[$bloque]) || !method_exists(self::class, $m)) {
            throw new HttpError(404);
        }
        return self::$m($d);
    }

    private static function money(float $v): string
    {
        return Fmt::money($v);
    }

    /** Alertas de valores fuera de lo común (CU-49). */
    private static function alertaPrecio(float $a, float $b): ?string
    {
        if ($a > 0 && $b === 0.0) {
            return 'Un precio pasa de ' . Fmt::money($a) . ' a $ 0.';
        }
        if ($a > 0 && abs($b - $a) / $a > 0.5) {
            return 'Un precio cambia más de 50% (' . Fmt::money($a) . ' → ' . Fmt::money($b) . ').';
        }
        return null;
    }

    private static function bPrestaciones(array $d): array
    {
        $cambios = [];
        $al = [];
        $upd = [];
        foreach (Catalogo::prestaciones(false) as $p) {
            $cod = $p['cod'];
            if (!isset($d['valor'][$cod]) && !isset($d['activo'][$cod])) {
                continue;
            }
            $nv = isset($d['valor'][$cod]) ? round(Fmt::parseMonto((string)$d['valor'][$cod]), 2) : $p['valor_ref'];
            $na = !empty($d['auditoria'][$cod]);
            $nAct = isset($d['activo'][$cod]) ? (bool)$d['activo'][$cod] : (bool)App::$db->val('SELECT activo FROM prestaciones WHERE cod = :c', ['c' => $cod]);
            $actAntes = (bool)App::$db->val('SELECT activo FROM prestaciones WHERE cod = :c', ['c' => $cod]);
            $c = [];
            if (abs($nv - $p['valor_ref']) > 0.004) {
                $cambios[] = ['t' => "{$p['nombre']}: valor de referencia", 'antes' => self::money($p['valor_ref']), 'despues' => self::money($nv)];
                $c['valor_ref'] = $nv;
                $c['actualizado_en'] = date('c');
                $c['actualizado_por'] = App::$usuario['nombre'];
                if ($x = self::alertaPrecio($p['valor_ref'], $nv)) {
                    $al[] = $x;
                }
            }
            if ($na !== $p['requiere_auditoria']) {
                $cambios[] = ['t' => "{$p['nombre']}: requiere auditoría", 'antes' => $p['requiere_auditoria'] ? 'sí' : 'no', 'despues' => $na ? 'sí' : 'no'];
                $c['requiere_auditoria'] = $na;
            }
            if ($nAct !== $actAntes) {
                $cambios[] = ['t' => $p['nombre'], 'antes' => $actAntes ? 'en uso' : 'quitada', 'despues' => $nAct ? 'vuelve a usarse' : 'quitada'];
                $c['activo'] = $nAct;
            }
            if ($c) {
                $upd[$cod] = $c;
            }
        }
        return ['cambios' => $cambios, 'alertas' => array_values(array_unique($al)), 'aplicar' => function () use ($upd) {
            foreach ($upd as $cod => $c) {
                App::$db->update('prestaciones', $c, 'cod = :cod', ['cod' => $cod]);
            }
            if (array_filter($upd, fn($c) => isset($c['valor_ref']))) {
                Config::set('precios_actualizados', ['fecha' => Fechas::hoy(), 'usuario' => App::$usuario['nombre']]);
            }
        }];
    }

    private static function bReglas(array $d): array
    {
        $cambios = [];
        $al = [];
        $nuevas = [];
        $et = ['gratis' => 'sin cargo', 'per' => 'cada', 'por_grupo' => 'por grupo', 'car_gratis' => 'carencia sin cargo', 'desc' => 'descuento %', 'modo' => 'cómo', 'car' => 'carencia descuento', 'escala' => 'escala', 'max_desc' => 'máximo con descuento por año'];
        foreach (Catalogo::planes() as $pl) {
            foreach (Catalogo::prestaciones() as $p) {
                $k = $pl['id'] . ':' . $p['cod'];
                $x = $d['r'][$k] ?? null;
                if (!is_array($x)) {
                    continue;
                }
                $a = Catalogo::regla($pl['id'], $p['cod']);
                $n = [
                    'gratis' => max(0, min(99, (int)($x['gratis'] ?? 0))), 'per' => in_array($x['per'] ?? '', ['mes', 'semestre', 'anio'], true) ? $x['per'] : 'anio',
                    'por_grupo' => !empty($x['por_grupo']), 'car_gratis' => max(0, min(730, (int)($x['car_gratis'] ?? 0))),
                    'desc' => max(0, min(100, (float)str_replace(',', '.', (string)($x['desc'] ?? 0)))), 'modo' => ($x['modo'] ?? '') === 'hasta' ? 'hasta' : 'fijo',
                    'car' => max(0, min(730, (int)($x['car'] ?? 0))), 'escala' => trim((string)($x['escala'] ?? '')), 'max_desc' => max(0, min(99, (int)($x['max_desc'] ?? 0))),
                ];
                if ($n['escala'] !== '' && !Beneficios::escala($n)) {
                    throw new Falta("La escala de {$p['nombre']} ({$pl['nombre']}) no se entiende: usá días:porcentaje separados por coma, por ejemplo 0:10, 90:20.");
                }
                foreach ($n as $f => $v) {
                    $va = $a[$f];
                    $dif = is_float($v) || is_float($va) ? abs((float)$v - (float)$va) > 0.004 : $v !== $va;
                    if (!$dif) {
                        continue;
                    }
                    $fmt = fn($z) => is_bool($z) ? ($z ? 'sí' : 'no') : ($f === 'per' ? (['anio' => 'año', 'mes' => 'mes', 'semestre' => 'semestre'][$z]) : ($z === '' ? '—' : (string)(is_float($z) ? Fmt::num($z) : $z)));
                    $cambios[] = ['t' => "{$pl['nombre']} · {$p['nombre']}: {$et[$f]}", 'antes' => $fmt($va), 'despues' => $fmt($v)];
                    if ($f === 'gratis' && ($v > 10 || abs($v - $va) >= 3)) {
                        $al[] = "Sesiones sin cargo: $va → $v.";
                    }
                    if ($f === 'desc' && $v > 60) {
                        $al[] = 'Descuento de ' . Fmt::num($v) . '% (más de 60%).';
                    }
                    if (in_array($f, ['car', 'car_gratis'], true) && ($v > 365 || ($va && !$v))) {
                        $al[] = "Carencia: $va → $v días.";
                    }
                }
                $nuevas[$k] = ['plan' => $pl['id'], 'cod' => $p['cod'], 'n' => $n];
            }
        }
        return ['cambios' => $cambios, 'alertas' => array_values(array_unique($al)), 'aplicar' => function () use ($nuevas) {
            foreach ($nuevas as $x) {
                $n = $x['n'];
                $vacia = !$n['gratis'] && !$n['desc'] && $n['escala'] === '' && !$n['max_desc'];
                if ($vacia) {
                    App::$db->exec('DELETE FROM reglas_beneficio WHERE plan_id = :p AND prestacion_cod = :c', ['p' => $x['plan'], 'c' => $x['cod']]);
                    continue;
                }
                App::$db->exec(
                    'INSERT INTO reglas_beneficio (plan_id, prestacion_cod, gratis, per, por_grupo, car_gratis, descuento, modo, car, escala, max_desc)
                     VALUES (:p, :c, :gratis, :per, :por_grupo, :car_gratis, :desc, :modo, :car, :escala, :max_desc)
                     ON CONFLICT (plan_id, prestacion_cod) DO UPDATE SET gratis = EXCLUDED.gratis, per = EXCLUDED.per, por_grupo = EXCLUDED.por_grupo, car_gratis = EXCLUDED.car_gratis,
                       descuento = EXCLUDED.descuento, modo = EXCLUDED.modo, car = EXCLUDED.car, escala = EXCLUDED.escala, max_desc = EXCLUDED.max_desc',
                    ['p' => $x['plan'], 'c' => $x['cod']] + $n
                );
            }
        }];
    }

    private static function bRubros(array $d): array
    {
        $cambios = [];
        $upd = [];
        foreach (Catalogo::rubros() as $r) {
            $x = $d['r'][$r['nombre']] ?? null;
            if (!is_array($x)) {
                continue;
            }
            $n = ['max_pct' => max(0, min(100, (float)($x['max'] ?? 0))), 'limite' => max(0, min(99, (int)($x['lim'] ?? 0))), 'per' => in_array($x['per'] ?? '', ['mes', 'semestre', 'anio'], true) ? $x['per'] : 'anio'];
            $a = ['max_pct' => (float)$r['max_pct'], 'limite' => (int)$r['limite'], 'per' => $r['per']];
            foreach ($n as $f => $v) {
                if ($v != $a[$f]) {
                    $cambios[] = ['t' => "{$r['nombre']}: " . ['max_pct' => 'descuento máximo %', 'limite' => 'límite de usos', 'per' => 'cada'][$f], 'antes' => (string)$a[$f], 'despues' => (string)$v];
                }
            }
            $upd[$r['nombre']] = $n;
        }
        return ['cambios' => $cambios, 'alertas' => [], 'aplicar' => function () use ($upd) {
            foreach ($upd as $nombre => $n) {
                App::$db->update('rubros_comercio', $n, 'nombre = :nombre', ['nombre' => $nombre]);
            }
        }];
    }

    private static function bPrecios(array $d): array
    {
        $cambios = [];
        $al = [];
        $nuevos = [];
        foreach (Catalogo::planes() as $pl) {
            foreach (Catalogo::categorias() as $c) {
                $k = $pl['id'] . ':' . $c['id'];
                if (!isset($d['precio'][$k])) {
                    continue;
                }
                $v = round(Fmt::parseMonto((string)$d['precio'][$k]), 2);
                $a = Catalogo::precio($c['id'], $pl['id']);
                if (abs($v - $a) > 0.004) {
                    $cambios[] = ['t' => "{$pl['nombre']} · {$c['nombre']}", 'antes' => self::money($a), 'despues' => self::money($v)];
                    if ($x = self::alertaPrecio($a, $v)) {
                        $al[] = $x;
                    }
                    $nuevos[] = ['plan' => $pl['id'], 'cat' => $c['id'], 'antes' => $a, 'v' => $v];
                }
            }
        }
        $ivas = [];
        foreach (Catalogo::planes() as $pl) {
            if (!array_key_exists($pl['id'], $d['iva'] ?? [])) {
                continue;
            }
            $raw = (string)$d['iva'][$pl['id']];
            $v = $raw === '' ? null : (float)$raw;
            if ($v !== $pl['alicuota'] && !($v !== null && $pl['alicuota'] !== null && abs($v - $pl['alicuota']) < 0.001)) {
                $cambios[] = ['t' => "{$pl['nombre']}: alícuota de IVA", 'antes' => Fmt::ivaTxt($pl['alicuota']), 'despues' => Fmt::ivaTxt($v)];
                $ivas[$pl['id']] = $v;
            }
        }
        $aplicarAct = !empty($d['aplicar_actuales']);
        if ($aplicarAct && $nuevos) {
            $n = 0;
            foreach ($nuevos as $x) {
                if ($x['v'] > 0) {
                    $n += (int)App::$db->val("SELECT count(*) FROM afiliados a JOIN grupos g ON g.id = a.grupo_id WHERE g.plan_id = :p AND a.categoria_id = :c AND a.estado = 'activo' AND abs(a.cuota - :ant) < 0.005", ['p' => $x['plan'], 'c' => $x['cat'], 'ant' => $x['antes']]);
                }
            }
            $cambios[] = ['t' => 'Cuotas de los afiliados actuales', 'antes' => 'sin cambios', 'despues' => "se actualizan $n cuota(s) que pagaban el precio anterior"];
        }
        return ['cambios' => $cambios, 'alertas' => array_values(array_unique($al)), 'aplicar' => function () use ($nuevos, $ivas, $aplicarAct) {
            foreach ($nuevos as $x) {
                App::$db->exec('INSERT INTO precios (plan_id, categoria_id, precio) VALUES (:p, :c, :v) ON CONFLICT (plan_id, categoria_id) DO UPDATE SET precio = EXCLUDED.precio', ['p' => $x['plan'], 'c' => $x['cat'], 'v' => $x['v']]);
                // CU-45: solo los que pagaban el precio anterior, y nunca a 0.
                if ($aplicarAct && $x['v'] > 0) {
                    App::$db->exec("UPDATE afiliados a SET cuota = :v FROM grupos g WHERE g.id = a.grupo_id AND g.plan_id = :p AND a.categoria_id = :c AND a.estado = 'activo' AND abs(a.cuota - :ant) < 0.005", ['v' => $x['v'], 'p' => $x['plan'], 'c' => $x['cat'], 'ant' => $x['antes']]);
                }
            }
            foreach ($ivas as $pl => $v) {
                App::$db->exec('UPDATE planes SET alicuota = :a WHERE id = :id', ['a' => $v, 'id' => $pl]);
            }
        }, 'mensaje' => $aplicarAct ? 'Precios guardados y aplicados a las cuotas de los afiliados actuales.' : 'Precios guardados: rigen para las próximas altas.'];
    }

    private static function bPv(array $d): array
    {
        $pv = (int)($d['pv'] ?? Config::pvCuotas());
        if ($pv < 1 || $pv > 99999) {
            throw new Falta('El punto de venta va de 1 a 99.999.', ['pv']);
        }
        $ult = Config::get('ultimos_numeros', ['B' => 0, 'A' => 0]);
        $nb = max(0, (int)($d['ult_b'] ?? $ult['B']));
        $na = max(0, (int)($d['ult_a'] ?? $ult['A']));
        $cambios = [];
        if ($pv !== Config::pvCuotas()) {
            $cambios[] = ['t' => 'Punto de venta de cuotas', 'antes' => (string)Config::pvCuotas(), 'despues' => (string)$pv];
        }
        if ($nb !== (int)$ult['B']) {
            $cambios[] = ['t' => 'Último N° de Factura B', 'antes' => (string)$ult['B'], 'despues' => (string)$nb];
        }
        if ($na !== (int)$ult['A']) {
            $cambios[] = ['t' => 'Último N° de Factura A', 'antes' => (string)$ult['A'], 'despues' => (string)$na];
        }
        return ['cambios' => $cambios, 'alertas' => [], 'aplicar' => function () use ($pv, $nb, $na) {
            Config::set('punto_venta', ['cuotas' => $pv]);
            Config::set('ultimos_numeros', ['B' => $nb, 'A' => $na]);
        }];
    }

    private static function bCfg(array $d): array
    {
        // CU-46: un día de vencimiento mayor que 28 se guarda como 28.
        $vto = max(1, min(28, (int)($d['dia_vto'] ?? Config::diaVto())));
        $car = Config::get('carencias', []);
        $cambios = [];
        $al = [];
        if ($vto !== Config::diaVto()) {
            $cambios[] = ['t' => 'Día de vencimiento', 'antes' => (string)Config::diaVto(), 'despues' => (string)$vto];
        }
        $nc = $car;
        foreach (Catalogo::TIPOS as $k => $t) {
            if (!isset($d['car'][$k])) {
                continue;
            }
            $v = max(0, min(730, (int)$d['car'][$k]));
            $a = (int)($car[$k] ?? 0);
            if ($v !== $a) {
                $cambios[] = ['t' => "Carencia general de $t (días)", 'antes' => (string)$a, 'despues' => (string)$v];
                if ($v > 365 || ($a && !$v)) {
                    $al[] = "Carencia: $a → $v días.";
                }
            }
            $nc[$k] = $v;
        }
        return ['cambios' => $cambios, 'alertas' => $al, 'aplicar' => function () use ($vto, $nc) {
            Config::set('dia_vto', $vto);
            Config::set('carencias', $nc);
        }];
    }

    private static function bPermisos(array $d): array
    {
        $a = Config::get('permisos', []);
        $n = [];
        $cambios = [];
        foreach (Permisos::ACCIONES as $k => $t) {
            $n[$k] = !empty($d['perm'][$k]);
            if ($n[$k] !== (bool)($a[$k] ?? true)) {
                $cambios[] = ['t' => $t, 'antes' => ($a[$k] ?? true) ? 'requiere administrador' : 'libre', 'despues' => $n[$k] ? 'requiere administrador' : 'libre'];
            }
        }
        return ['cambios' => $cambios, 'alertas' => [], 'aplicar' => fn() => Config::set('permisos', $n)];
    }

    private static function bMail(array $d): array
    {
        $a = Config::get('mail', []);
        $n = $a;
        $cambios = [];
        foreach (Mails::NOMBRES as $k => $t) {
            foreach (['asunto', 'cuerpo'] as $f) {
                if (!isset($d[$k][$f])) {
                    continue;
                }
                $v = str_replace("\r\n", "\n", (string)$d[$k][$f]);
                if ($v !== ($a[$k][$f] ?? '')) {
                    $cambios[] = ['t' => "$t: " . ($f === 'asunto' ? 'asunto' : 'texto'), 'antes' => mb_strimwidth($a[$k][$f] ?? '', 0, 60, '…'), 'despues' => mb_strimwidth($v, 0, 60, '…')];
                }
                $n[$k][$f] = $v;
            }
        }
        $auto = !empty($d['recibo_auto']);
        if ($auto !== (bool)($a['recibo_auto'] ?? false)) {
            $cambios[] = ['t' => 'Mandar el recibo por mail al cobrar', 'antes' => ($a['recibo_auto'] ?? false) ? 'sí' : 'no', 'despues' => $auto ? 'sí' : 'no'];
        }
        $n['recibo_auto'] = $auto;
        return ['cambios' => $cambios, 'alertas' => [], 'aplicar' => fn() => Config::set('mail', $n)];
    }

    /** CU-41: aplica la importación revisada (filas y prestaciones a quitar). */
    private static function bImportar(array $d): array
    {
        $filas = $d['filas'] ?? [];
        $quitar = $d['quitar'] ?? [];
        $cambios = [];
        $al = [];
        $ops = [];
        $prs = Catalogo::prestaciones(false);
        foreach ($filas as $f) {
            $dest = (string)($f['dest'] ?? 'no');
            $precio = round((float)($f['precio'] ?? 0), 2);
            if ($dest === 'no' || $precio < 0) {
                continue;
            }
            if ($dest === 'nueva') {
                $u = Catalogo::unidades()[$f['unidad'] ?? ''] ?? null;
                if (!$u) {
                    continue;
                }
                $nom = Fmt::capi(trim((string)$f['nombre']));
                $cambios[] = ['t' => "Nueva: $nom ({$u['nombre']})", 'antes' => '—', 'despues' => self::money($precio)];
                $ops[] = ['nueva', $nom, $u['id'], in_array($f['tipo'] ?? '', array_keys(Catalogo::TIPOS), true) ? $f['tipo'] : 'consulta', $precio];
            } elseif (isset($prs[$dest])) {
                $p = $prs[$dest];
                $activo = (bool)App::$db->val('SELECT activo FROM prestaciones WHERE cod = :c', ['c' => $dest]);
                if (!$activo) {
                    $cambios[] = ['t' => $p['nombre'], 'antes' => 'quitada', 'despues' => 'vuelve a usarse'];
                }
                if (abs($p['valor_ref'] - $precio) > 0.004) {
                    $cambios[] = ['t' => "{$p['nombre']}: valor de referencia", 'antes' => self::money($p['valor_ref']), 'despues' => self::money($precio)];
                    if ($x = self::alertaPrecio($p['valor_ref'], $precio)) {
                        $al[] = $x;
                    }
                }
                if (!$activo || abs($p['valor_ref'] - $precio) > 0.004) {
                    $ops[] = ['precio', $dest, $precio];
                }
            }
        }
        foreach ($quitar as $cod => $q) {
            if ($q && isset($prs[$cod]) && App::$db->val('SELECT activo FROM prestaciones WHERE cod = :c', ['c' => $cod])) {
                $cambios[] = ['t' => $prs[$cod]['nombre'], 'antes' => 'en uso', 'despues' => 'quitada'];
                $ops[] = ['quitar', $cod];
            }
        }
        return ['cambios' => $cambios, 'alertas' => array_values(array_unique($al)), 'aplicar' => function () use ($ops) {
            $hoy = date('c');
            $usr = App::$usuario['nombre'];
            foreach ($ops as $o) {
                if ($o[0] === 'nueva') {
                    $u = Catalogo::unidadPorId($o[2]);
                    $pre = mb_strtoupper(mb_substr(Fmt::norm($u['nombre']), 0, 1));
                    $n = 1;
                    while (App::$db->val('SELECT 1 FROM prestaciones WHERE cod = :c', ['c' => $pre . sprintf('%02d', $n)])) {
                        $n++;
                    }
                    App::$db->insert('prestaciones', ['cod' => $pre . sprintf('%02d', $n), 'nombre' => $o[1], 'tipo' => $o[3], 'unidad_id' => $o[2], 'valor_ref' => $o[4], 'iva' => 21, 'orden' => 100 + $n, 'actualizado_en' => $hoy, 'actualizado_por' => $usr], 'cod');
                } elseif ($o[0] === 'precio') {
                    App::$db->exec('UPDATE prestaciones SET valor_ref = :v, activo = true, actualizado_en = :f, actualizado_por = :u WHERE cod = :c', ['v' => $o[2], 'f' => $hoy, 'u' => $usr, 'c' => $o[1]]);
                } else {
                    App::$db->exec('UPDATE prestaciones SET activo = false WHERE cod = :c', ['c' => $o[1]]);
                }
            }
            Config::set('precios_actualizados', ['fecha' => Fechas::hoy(), 'usuario' => $usr]);
        }];
    }

    // ------------------------------------------------------------------ confirmar y deshacer

    /** Aplica un bloque después de confirmar la clave. Guarda la instantánea para deshacer. */
    public static function confirmar(string $bloque, array $d, string $clave): string
    {
        $u = App::$usuario;
        if (!Usuarios::verificar($u, $clave)) {
            throw new Falta('La clave no es correcta.', ['conf-pw']);
        }
        if (in_array($bloque, self::DE_MONTOS, true)) {
            AutorizacionAdmin::exigir('montos');
        }
        $prep = self::preparar($bloque, $d);
        if (!$prep['cambios']) {
            throw new Falta('No hubo cambios para guardar.');
        }
        App::$db->tx(function () use ($bloque, $prep) {
            $snap = self::snapshot();
            ($prep['aplicar'])();
            Catalogo::limpiar();
            Config::limpiar();
            $resumen = implode('; ', array_map(fn($c) => "{$c['t']}: {$c['antes']} → {$c['despues']}", $prep['cambios']));
            App::$db->insert('config_cambios', ['usuario' => App::$usuario['nombre'], 'seccion' => $bloque, 'cambios' => json_encode($prep['cambios'], JSON_UNESCAPED_UNICODE), 'snapshot' => json_encode($snap, JSON_UNESCAPED_UNICODE)]);
            App::$db->exec('DELETE FROM config_cambios WHERE id NOT IN (SELECT id FROM config_cambios ORDER BY id DESC LIMIT 10)');
            Historial::log(App::$db, self::BLOQUES[$bloque], $resumen);
        });
        return $prep['mensaje'] ?? 'Cambios guardados.';
    }

    /** CU-49: solo el cambio más reciente (y no deshecho) se puede deshacer. */
    public static function deshacer(int $id): void
    {
        App::$db->tx(function () use ($id) {
            $ult = App::$db->one('SELECT * FROM config_cambios WHERE deshecho IS NULL ORDER BY id DESC LIMIT 1 FOR UPDATE');
            if (!$ult || (int)$ult['id'] !== $id) {
                throw new Falta('Solo se puede deshacer el cambio más reciente.');
            }
            self::restaurar(json_decode((string)$ult['snapshot'], true));
            App::$db->exec('UPDATE config_cambios SET deshecho = now() WHERE id = :id', ['id' => $id]);
            $c = json_decode((string)$ult['cambios'], true);
            Historial::log(App::$db, 'Deshacer', 'Se deshizo: ' . implode('; ', array_map(fn($x) => "{$x['t']}: {$x['antes']} → {$x['despues']}", array_slice($c, 0, 6))) . (count($c) > 6 ? ' y ' . (count($c) - 6) . ' más' : ''));
        });
    }

    // ------------------------------------------------------------------ CU-39 usuarios

    public static function crearUsuario(array $d): void
    {
        $n = preg_replace('/\s+/', ' ', trim((string)($d['nombre'] ?? '')));
        $clave = (string)($d['clave'] ?? '') ?: $n;
        $tipo = (string)($d['tipo'] ?? 'usuario');
        if ($n === '') {
            throw new Falta('Escribí el nombre del usuario.', ['nu-n']);
        }
        if (Usuarios::porNombre(App::$db, $n)) {
            throw new Falta("Ya existe el usuario $n.", ['nu-n']);
        }
        if (mb_strlen(trim($clave)) < 4) {
            throw new Falta('La clave tiene que tener al menos 4 caracteres.', ['nu-p']);
        }
        $fila = ['nombre' => $n, 'nombre_norm' => Fmt::norm($n), 'clave_hash' => Usuarios::hash($clave), 'debe_cambiar_clave' => true];
        switch ($tipo) {
            case 'admin':
                $fila += ['rol' => 'admin', 'secciones' => Db::arrayPg(array_column(Permisos::VISTAS, 0))];
                break;
            case 'unidad':
                $u = Catalogo::unidades()[$d['unidad'] ?? ''] ?? throw new Falta('Elegí una unidad de negocio existente.', ['nu-un']);
                $fila += ['rol' => 'unidad', 'secciones' => '{verificar}', 'unidad_id' => $u['id']];
                break;
            case 'comercio':
                $cn = trim((string)($d['comercio'] ?? ''));
                $rub = (string)($d['rubro'] ?? '');
                if ($cn === '') {
                    throw new Falta('Escribí el nombre del comercio.', ['nu-cn']);
                }
                if (!isset(Catalogo::rubros()[$rub])) {
                    App::$db->insert('rubros_comercio', ['nombre' => $rub ?: 'Otros', 'max_pct' => 0, 'orden' => 99], 'nombre');
                }
                $fila += ['rol' => 'comercio', 'secciones' => '{verificar}', 'comercio_nombre' => $cn, 'comercio_rubro' => $rub ?: 'Otros'];
                break;
            default:
                $fila += ['rol' => 'usuario', 'secciones' => Db::arrayPg(Permisos::USR_VISTAS)];
        }
        App::$db->insert('usuarios', $fila);
        Historial::log(App::$db, 'Usuarios', "Alta del usuario $n" . ($tipo === 'admin' ? ' (administrador)' : ($tipo === 'comercio' ? " (comercio {$fila['comercio_nombre']}, {$fila['comercio_rubro']})" : ($tipo === 'unidad' ? ' (unidad)' : ''))));
    }

    /** Opciones de un usuario: clave, rol, estado y secciones. */
    public static function editarUsuario(int $id, string $accion, array $d): string
    {
        $u = Usuarios::porId(App::$db, $id) ?? throw new HttpError(404);
        $yo = App::$usuario;
        $soy = $u['id'] === $yo['id'];
        $adminsActivos = fn() => (int)App::$db->val("SELECT count(*) FROM usuarios WHERE rol = 'admin' AND activo");
        switch ($accion) {
            case 'clave':
                $k = (string)($d['clave'] ?? '');
                if (mb_strlen(trim($k)) < 4) {
                    throw new Falta('La clave tiene que tener al menos 4 caracteres.', ['clave']);
                }
                App::$db->exec('UPDATE usuarios SET clave_hash = :h, debe_cambiar_clave = :d WHERE id = :id', ['h' => Usuarios::hash($k), 'd' => !$soy, 'id' => $id]);
                App::$db->exec('DELETE FROM sesiones WHERE usuario_id = :id', ['id' => $id]);
                Historial::log(App::$db, 'Usuarios', "Se cambió la clave de {$u['nombre']}");
                return "Clave de {$u['nombre']} cambiada. Va a tener que cambiarla al ingresar.";
            case 'rol':
                if ($soy) {
                    throw new Falta('No podés cambiar tu propio rol.');
                }
                if (!in_array($u['rol'], ['admin', 'usuario'], true)) {
                    throw new Falta('Las unidades y los comercios no pueden ser administradores.');
                }
                if ($u['rol'] === 'admin' && $adminsActivos() <= 1) {
                    throw new Falta('Tiene que quedar al menos un administrador.');
                }
                $nuevo = $u['rol'] === 'admin' ? 'usuario' : 'admin';
                App::$db->exec('UPDATE usuarios SET rol = :r, secciones = :s WHERE id = :id', ['r' => $nuevo, 's' => Db::arrayPg($nuevo === 'admin' ? array_column(Permisos::VISTAS, 0) : Permisos::USR_VISTAS), 'id' => $id]);
                Historial::log(App::$db, 'Usuarios', "{$u['nombre']}: " . ($nuevo === 'admin' ? 'pasa a ser administrador' : 'deja de ser administrador'));
                return $nuevo === 'admin' ? "{$u['nombre']} ahora es administrador." : "{$u['nombre']} ya no es administrador.";
            case 'estado':
                if ($soy) {
                    throw new Falta('No podés darte de baja a vos mismo.');
                }
                if ($u['activo'] && $u['rol'] === 'admin' && $adminsActivos() <= 1) {
                    throw new Falta('Tiene que quedar al menos un administrador.');
                }
                App::$db->exec('UPDATE usuarios SET activo = :a WHERE id = :id', ['a' => !$u['activo'], 'id' => $id]);
                if ($u['activo']) {
                    App::$db->exec('DELETE FROM sesiones WHERE usuario_id = :id', ['id' => $id]);
                }
                Historial::log(App::$db, 'Usuarios', ($u['activo'] ? 'Baja' : 'Reactivación') . " del usuario {$u['nombre']}");
                return $u['activo'] ? "Usuario {$u['nombre']} dado de baja." : "Usuario {$u['nombre']} reactivado.";
            case 'secciones':
                if ($u['rol'] !== 'usuario') {
                    throw new Falta('Las secciones se eligen solo para usuarios internos.');
                }
                $s = array_values(array_intersect((array)($d['secciones'] ?? []), Permisos::USR_VISTAS));
                App::$db->exec('UPDATE usuarios SET secciones = :s WHERE id = :id', ['s' => Db::arrayPg($s), 'id' => $id]);
                Historial::log(App::$db, 'Usuarios', "{$u['nombre']} ve: " . ($s ? implode(', ', array_map([Permisos::class, 'nombreVista'], $s)) : 'ninguna sección'));
                return 'Secciones guardadas.';
        }
        throw new Falta('Acción desconocida.');
    }

    // ------------------------------------------------------------------ CU-43 unidades de negocio

    public static function crearUnidad(array $d): void
    {
        $n = trim((string)($d['nombre'] ?? ''));
        $serv = trim((string)($d['servicio'] ?? ''));
        $car = max(0, min(24, (int)($d['carencia'] ?? 0)));
        if ($n === '' || $serv === '') {
            throw new Falta('Escribí el nombre de la unidad y el servicio que presta.', array_keys(array_filter(['un-n' => $n === '', 'un-p' => $serv === ''])));
        }
        if (App::$db->val('SELECT 1 FROM unidades WHERE lower(nombre) = lower(:n)', ['n' => $n]) || Usuarios::porNombre(App::$db, $n)) {
            throw new Falta('Ya existe una unidad o un usuario con ese nombre.', ['un-n']);
        }
        App::$db->tx(function () use ($n, $serv, $car, $d) {
            $pv = (int)App::$db->val('SELECT coalesce(max(punto_venta),1) + 1 FROM unidades');
            $uid = (int)App::$db->insert('unidades', ['nombre' => $n, 'nombre_largo' => $n, 'servicio' => $serv, 'carencia_meses' => $car, 'texto_beneficio' => trim((string)($d['texto'] ?? '')), 'punto_venta' => $pv]);
            $cod = 'U' . sprintf('%02d', $uid);
            App::$db->insert('prestaciones', ['cod' => $cod, 'nombre' => $serv, 'tipo' => 'beneficio', 'unidad_id' => $uid, 'iva' => 21, 'orden' => 200 + $uid], 'cod');
            $per = in_array($d['per'] ?? '', ['mes', 'semestre', 'anio'], true) ? $d['per'] : 'anio';
            foreach (array_keys(Catalogo::planes()) as $pl) {
                $pct = max(0, min(100, (float)($d['pct'][$pl] ?? 0)));
                $g = max(0, min(99, (int)($d['gratis'][$pl] ?? 0)));
                if ($pct || $g) {
                    App::$db->insert('reglas_beneficio', ['plan_id' => $pl, 'prestacion_cod' => $cod, 'gratis' => $g, 'per' => $per, 'car_gratis' => $car * 30, 'descuento' => $pct, 'modo' => 'fijo', 'car' => $car * 30], 'plan_id');
                }
            }
            App::$db->insert('usuarios', ['nombre' => $n, 'nombre_norm' => Fmt::norm($n), 'clave_hash' => Usuarios::hash($n), 'rol' => 'unidad', 'secciones' => '{verificar}', 'unidad_id' => $uid, 'debe_cambiar_clave' => true]);
            Historial::log(App::$db, 'Unidades', "Nueva unidad de negocio $n ($serv), con su usuario");
        });
        Catalogo::limpiar();
    }

    /** Editar una unidad: pide la clave del administrador (CU-43). */
    public static function editarUnidad(int $id, array $d): void
    {
        $u = Catalogo::unidadPorId($id) ?? throw new HttpError(404);
        if (!Usuarios::verificar(App::$usuario, (string)($d['clave_admin'] ?? ''))) {
            throw new Falta('La clave del administrador no es correcta.', ['clave_admin']);
        }
        $cambios = [];
        $upd = [];
        if (!$u['original']) {
            $n = trim((string)($d['nombre'] ?? $u['nombre']));
            if ($n !== $u['nombre']) {
                if ($n === '' || App::$db->val('SELECT 1 FROM unidades WHERE lower(nombre) = lower(:n) AND id <> :id', ['n' => $n, 'id' => $id])) {
                    throw new Falta('Ya existe una unidad con ese nombre.', ['nombre']);
                }
                $upd['nombre'] = $n;
                $upd['nombre_largo'] = $n;
                $cambios[] = "nombre: {$u['nombre']} → $n";
            }
            $car = max(0, min(24, (int)($d['carencia'] ?? $u['carencia_meses'])));
            if ($car !== (int)$u['carencia_meses']) {
                $upd['carencia_meses'] = $car;
                $cambios[] = "carencia: {$u['carencia_meses']} → $car meses";
            }
            $tx = trim((string)($d['texto'] ?? $u['texto_beneficio']));
            if ($tx !== $u['texto_beneficio']) {
                $upd['texto_beneficio'] = $tx;
                $cambios[] = 'texto del beneficio';
            }
        }
        $us = App::$db->one("SELECT * FROM usuarios WHERE unidad_id = :u AND rol = 'unidad' ORDER BY id LIMIT 1", ['u' => $id]);
        $nu = trim((string)($d['usuario'] ?? ($us['nombre'] ?? '')));
        $k = (string)($d['clave'] ?? '');
        if ($k !== '' && mb_strlen(trim($k)) < 4) {
            throw new Falta('La clave nueva tiene que tener al menos 4 caracteres.', ['clave']);
        }
        $usUpd = [];
        if ($us && $nu !== '' && $nu !== $us['nombre']) {
            if (Usuarios::porNombre(App::$db, $nu)) {
                throw new Falta("Ya existe el usuario $nu.", ['usuario']);
            }
            $usUpd = ['nombre' => $nu, 'nombre_norm' => Fmt::norm($nu)];
            // Si se renombra sin clave nueva, la clave pasa a ser el nuevo nombre.
            $k = $k !== '' ? $k : $nu;
            $cambios[] = "usuario: {$us['nombre']} → $nu";
        }
        if ($us && $k !== '') {
            $usUpd += ['clave_hash' => Usuarios::hash($k), 'debe_cambiar_clave' => true];
            $cambios[] = 'clave del usuario';
        }
        if (!$cambios) {
            throw new Falta('No cambiaste nada.');
        }
        App::$db->tx(function () use ($id, $upd, $usUpd, $us, $cambios, $u) {
            if ($upd) {
                App::$db->update('unidades', $upd, 'id = :id', ['id' => $id]);
                if (isset($upd['carencia_meses'])) {
                    // La carencia de la unidad actualiza las reglas de sus prestaciones (meses × 30 días).
                    App::$db->exec('UPDATE reglas_beneficio SET car = :c, car_gratis = :c WHERE prestacion_cod IN (SELECT cod FROM prestaciones WHERE unidad_id = :u)', ['c' => $upd['carencia_meses'] * 30, 'u' => $id]);
                }
            }
            if ($usUpd && $us) {
                App::$db->update('usuarios', $usUpd, 'id = :uid', ['uid' => $us['id']]);
            }
            Historial::log(App::$db, 'Unidades', "{$u['nombre']}: " . implode('; ', $cambios));
        });
        Catalogo::limpiar();
    }

    public static function estadoUnidad(int $id, bool $activar): void
    {
        $u = Catalogo::unidadPorId($id) ?? throw new HttpError(404);
        App::$db->tx(function () use ($id, $activar, $u) {
            App::$db->exec('UPDATE unidades SET activo = :a WHERE id = :id', ['a' => $activar, 'id' => $id]);
            App::$db->exec('UPDATE prestaciones SET activo = :a WHERE unidad_id = :id', ['a' => $activar, 'id' => $id]);
            App::$db->exec("UPDATE usuarios SET activo = :a WHERE unidad_id = :id AND rol = 'unidad'", ['a' => $activar, 'id' => $id]);
            if (!$activar) {
                App::$db->exec('DELETE FROM sesiones WHERE usuario_id IN (SELECT id FROM usuarios WHERE unidad_id = :id)', ['id' => $id]);
            }
            Historial::log(App::$db, 'Unidades', ($activar ? 'Reactivación' : 'Baja') . " de la unidad {$u['nombre']}");
        });
        Catalogo::limpiar();
    }

    public static function eliminarUnidad(int $id): void
    {
        $u = Catalogo::unidadPorId($id) ?? throw new HttpError(404);
        if ($u['original']) {
            throw new Falta('Las unidades originales no se pueden eliminar.');
        }
        if (App::$db->val('SELECT 1 FROM usos WHERE unidad_id = :id LIMIT 1', ['id' => $id])) {
            throw new Falta('La unidad ya registró usos: se puede dar de baja, pero no eliminar.');
        }
        App::$db->tx(function () use ($id, $u) {
            App::$db->exec('DELETE FROM reglas_beneficio WHERE prestacion_cod IN (SELECT cod FROM prestaciones WHERE unidad_id = :id)', ['id' => $id]);
            App::$db->exec('DELETE FROM autorizaciones WHERE prestacion_cod IN (SELECT cod FROM prestaciones WHERE unidad_id = :id)', ['id' => $id]);
            App::$db->exec('DELETE FROM prestaciones WHERE unidad_id = :id', ['id' => $id]);
            App::$db->exec('DELETE FROM sesiones WHERE usuario_id IN (SELECT id FROM usuarios WHERE unidad_id = :id)', ['id' => $id]);
            App::$db->exec('DELETE FROM tokens_admin WHERE usuario_id IN (SELECT id FROM usuarios WHERE unidad_id = :id)', ['id' => $id]);
            App::$db->exec('DELETE FROM usuarios WHERE unidad_id = :id', ['id' => $id]);
            App::$db->exec('DELETE FROM unidades WHERE id = :id', ['id' => $id]);
            Historial::log(App::$db, 'Unidades', "Se eliminó la unidad {$u['nombre']}");
        });
        Catalogo::limpiar();
    }

    /** Alta de una prestación nueva (CU-40 "Agregar"). */
    public static function agregarPrestacion(array $d): void
    {
        $n = trim((string)($d['nombre'] ?? ''));
        $u = Catalogo::unidades()[$d['unidad'] ?? ''] ?? throw new Falta('Elegí la unidad.');
        $tipo = isset(Catalogo::TIPOS[$d['tipo'] ?? '']) ? $d['tipo'] : 'consulta';
        if ($n === '') {
            throw new Falta('Escribí el nombre de la prestación.', ['np-n']);
        }
        if (App::$db->val('SELECT 1 FROM prestaciones WHERE unidad_id = :u AND lower(nombre) = lower(:n)', ['u' => $u['id'], 'n' => $n])) {
            throw new Falta('Esa prestación ya existe en esa unidad.', ['np-n']);
        }
        $pre = ['consulta' => 'C', 'estudio' => 'E', 'beneficio' => 'B'][$tipo];
        $i = 1;
        while (App::$db->val('SELECT 1 FROM prestaciones WHERE cod = :c', ['c' => $pre . sprintf('%02d', $i)])) {
            $i++;
        }
        App::$db->insert('prestaciones', ['cod' => $pre . sprintf('%02d', $i), 'nombre' => $n, 'tipo' => $tipo, 'unidad_id' => $u['id'], 'iva' => 21, 'orden' => 100 + $i], 'cod');
        Historial::log(App::$db, 'Prestaciones', "Nueva prestación: $n ({$u['nombre']})");
        Catalogo::limpiar();
    }
}
