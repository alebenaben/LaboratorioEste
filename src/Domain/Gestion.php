<?php
declare(strict_types=1);

namespace App\Domain;

use App\Core\App;
use App\Core\Falta;
use App\Core\Historial;
use App\Core\HttpError;

/** CU-33 a CU-38: egresos, contabilidad, estadísticas e informes. */
final class Gestion
{
    // ------------------------------------------------------------------ CU-33 Egresos

    public static function categoriasEgreso(): array
    {
        return array_column(App::$db->all('SELECT * FROM categorias_egreso ORDER BY orden'), null, 'clave');
    }

    public static function guardarEgreso(array $d, string $usuario): int
    {
        $cats = self::categoriasEgreso();
        $imp = Fmt::parseMonto((string)($d['importe'] ?? ''));
        $comp = in_array($d['comprobante'] ?? '', CatalogoMaqueta::TIPO_COMP, true) ? $d['comprobante'] : 'Sin comprobante';
        $iva = $comp === 'Factura A' ? Fmt::parseMonto((string)($d['iva'] ?? '')) : 0.0;
        $prov = trim((string)($d['proveedor'] ?? ''));
        if ($imp <= 0) {
            throw new Falta('Cargá el importe.', ['importe']);
        }
        if ($iva >= $imp) {
            throw new Falta('El IVA no puede ser mayor o igual que el total.', ['iva']);
        }
        if ($prov === '') {
            throw new Falta('Escribí el proveedor.', ['proveedor']);
        }
        if (!isset($cats[$d['categoria'] ?? ''])) {
            throw new Falta('Elegí la categoría.', ['categoria']);
        }
        $fecha = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($d['fecha'] ?? '')) ? $d['fecha'] : Fechas::hoy();
        $id = (int)App::$db->insert('egresos', [
            'fecha' => $fecha, 'categoria' => $d['categoria'], 'proveedor' => $prov, 'cuit' => Fmt::doc((string)($d['cuit'] ?? '')),
            'detalle' => trim((string)($d['detalle'] ?? '')), 'comprobante' => $comp, 'numero' => trim((string)($d['numero'] ?? '')),
            'importe' => $imp, 'iva' => $iva, 'medio' => in_array($d['medio'] ?? '', Catalogo::MEDIOS, true) ? $d['medio'] : 'Efectivo',
            'fijo' => !empty($d['fijo']), 'usuario' => $usuario,
        ]);
        Historial::log(App::$db, 'Egresos', $cats[$d['categoria']]['nombre'] . " · $prov · " . Fmt::money($imp));
        return $id;
    }

    public static function borrarEgreso(int $id): void
    {
        $e = App::$db->one('SELECT e.*, c.nombre AS cat FROM egresos e JOIN categorias_egreso c ON c.clave = e.categoria WHERE e.id = :id', ['id' => $id]) ?? throw new HttpError(404);
        App::$db->exec('DELETE FROM egresos WHERE id = :id', ['id' => $id]);
        Historial::log(App::$db, 'Egresos', "Egreso borrado: {$e['cat']} · {$e['proveedor']} · " . Fmt::money($e['importe']) . ' del ' . Fmt::fd($e['fecha']) . ($e['comprobante'] !== 'Sin comprobante' ? " · {$e['comprobante']} {$e['numero']}" : ''));
    }

    /** Resumen de un mes y gastos fijos que faltan cargar. */
    public static function resumenEgresos(string $m): array
    {
        $d = "$m-01";
        $h = Fechas::finPeriodo($m);
        $es = App::$db->all('SELECT * FROM egresos WHERE fecha BETWEEN :d AND :h ORDER BY fecha DESC, id DESC', ['d' => $d, 'h' => $h]);
        $ing = (float)App::$db->val('SELECT coalesce(sum(importe),0) FROM recibos WHERE fecha BETWEEN :d AND :h', ['d' => $d, 'h' => $h]);
        $tot = array_sum(array_map(fn($e) => (float)$e['importe'], $es));
        $porC = [];
        foreach ($es as $e) {
            $porC[$e['categoria']] = ($porC[$e['categoria']] ?? 0) + (float)$e['importe'];
        }
        arsort($porC);
        // Un gasto fijo se reconoce por proveedor + categoría; se toma el último cargado.
        $fijos = [];
        foreach (App::$db->all('SELECT * FROM egresos WHERE fijo AND fecha < :d ORDER BY fecha DESC, id DESC', ['d' => $d]) as $e) {
            $k = Fmt::norm($e['proveedor']) . '|' . $e['categoria'];
            $fijos[$k] ??= $e;
        }
        $cargados = array_flip(array_map(fn($e) => Fmt::norm($e['proveedor']) . '|' . $e['categoria'], $es));
        $pend = array_values(array_filter($fijos, fn($e, $k) => !isset($cargados[$k]), ARRAY_FILTER_USE_BOTH));
        return ['es' => $es, 'ing' => $ing, 'tot' => $tot, 'res' => $ing - $tot, 'porC' => $porC, 'fijosPend' => $pend];
    }

    // ------------------------------------------------------------------ CU-34 Contabilidad

    public static function cuentas(): array
    {
        return App::$db->all('SELECT * FROM cuentas ORDER BY orden');
    }

    /** Saldos por cuenta a una fecha (rubros = suma de sus cuentas). Activo y egresos: debe − haber; el resto al revés. */
    public static function saldos(?string $hasta = null): array
    {
        $hasta ??= Fechas::hoy();
        $s = [];
        foreach (App::$db->all('SELECT cuenta, sum(debe) - sum(haber) AS s FROM v_asientos WHERE fecha <= :h GROUP BY cuenta', ['h' => $hasta]) as $x) {
            $s[$x['cuenta']] = (float)$x['s'];
        }
        $r = [];
        foreach (self::cuentas() as $c) {
            $nat = in_array($c['codigo'][0], ['2', '3', '4'], true) ? -1 : 1;
            if ($c['imputable']) {
                $r[$c['codigo']] = ($s[$c['codigo']] ?? 0) * $nat;
            } else {
                $t = 0;
                foreach ($s as $k => $v) {
                    if (str_starts_with($k, $c['codigo'] . '.')) {
                        $t += $v * $nat;
                    }
                }
                $r[$c['codigo']] = $t;
            }
        }
        return $r;
    }

    /** Asientos con sus líneas (para el libro diario). */
    public static function asientos(?string $desde = null, ?string $hasta = null, ?int $ultimos = null): array
    {
        $w = [];
        $p = [];
        if ($desde) {
            $w[] = 'fecha >= :d';
            $p['d'] = $desde;
        }
        if ($hasta) {
            $w[] = 'fecha <= :h';
            $p['h'] = $hasta;
        }
        $rows = App::$db->all('SELECT * FROM v_asientos' . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . ' ORDER BY fecha, orden_tipo, ref, linea', $p);
        $A = [];
        foreach ($rows as $r) {
            $A[$r['asiento']] ??= ['fecha' => $r['fecha'], 'det' => $r['detalle'], 'l' => []];
            $A[$r['asiento']]['l'][] = [$r['cuenta'], (float)$r['debe'], (float)$r['haber']];
        }
        $A = array_values($A);
        return $ultimos ? array_slice($A, -$ultimos) : $A;
    }

    // ------------------------------------------------------------------ CU-35 Estadísticas

    public static function indicadores(?string $hoy = null): array
    {
        $hoy ??= Fechas::hoy();
        $db = App::$db;
        $ult = $db->val('SELECT max(periodo) FROM facturas');
        $venc = $db->one("SELECT coalesce(sum(importe),0) AS t, count(DISTINCT grupo_id) AS g FROM facturas WHERE estado = 'pendiente' AND vto < :h", ['h' => $hoy]);
        return [
            'activos' => (int)$db->val("SELECT count(*) FROM afiliados WHERE estado = 'activo'"),
            'grupos' => (int)$db->val("SELECT count(DISTINCT grupo_id) FROM afiliados WHERE estado = 'activo'"),
            'cuotas' => (float)$db->val("SELECT coalesce(sum(cuota),0) FROM afiliados WHERE estado = 'activo'"),
            'ult' => $ult, 'facUlt' => $ult ? $db->one('SELECT count(*) AS n, coalesce(sum(importe),0) AS t FROM facturas WHERE periodo = :p', ['p' => $ult]) : ['n' => 0, 't' => 0],
            'deuda' => (float)$venc['t'], 'morosos' => (int)$venc['g'],
            'porCat' => $db->all("SELECT c.nombre AS n, count(a.id) AS v FROM categorias c LEFT JOIN afiliados a ON a.categoria_id = c.id AND a.estado = 'activo' GROUP BY c.id, c.nombre, c.orden ORDER BY c.orden"),
            'porMedio' => array_map(fn($m) => ['n' => $m, 'v' => (float)$db->val('SELECT coalesce(sum(importe),0) FROM recibos WHERE medio = :m', ['m' => $m])], Catalogo::MEDIOS),
            'porMes' => $db->all("SELECT periodo AS p, sum(importe) AS fac, sum(importe) FILTER (WHERE estado = 'pagada') AS cob FROM facturas GROUP BY periodo ORDER BY periodo"),
        ];
    }

    public static function consultas(?string $hoy = null): array
    {
        $hoy ??= Fechas::hoy();
        $db = App::$db;
        $planes = [];
        foreach ($db->col('SELECT planes FROM consultas') as $p) {
            foreach (\App\Core\Db::leerArray($p) as $x) {
                $planes[$x] = ($planes[$x] ?? 0) + 1;
            }
        }
        arsort($planes);
        return [
            'mes' => (int)$db->val('SELECT count(*) FROM consultas WHERE fecha >= :d', ['d' => substr($hoy, 0, 7) . '-01']),
            'total' => (int)$db->val('SELECT count(*) FROM consultas'),
            'canal' => $db->all('SELECT canal AS n, count(*) AS v FROM consultas GROUP BY canal ORDER BY v DESC'),
            'noAfiliados' => (int)$db->val('SELECT count(*) FROM consultas WHERE afiliado_id IS NULL'),
            'planes' => $planes,
            'ultimas' => $db->all('SELECT * FROM consultas ORDER BY id DESC LIMIT 15'),
        ];
    }

    public static function consumoComercios(?string $hoy = null): array
    {
        $hoy ??= Fechas::hoy();
        $m = substr($hoy, 0, 7) . '-01';
        return [
            'porComercio' => App::$db->all('SELECT comercio, count(*) FILTER (WHERE fecha >= :m) AS mes, count(*) AS total, count(DISTINCT afiliado_id) AS af, sum(importe) AS vendido, sum(ahorro) AS ahorro FROM consumos_comercio GROUP BY comercio ORDER BY total DESC', ['m' => $m]),
            'top' => App::$db->all('SELECT a.nombre, a.nro, count(*) AS n, sum(c.ahorro) AS ahorro FROM consumos_comercio c JOIN afiliados a ON a.id = c.afiliado_id GROUP BY a.id, a.nombre, a.nro ORDER BY n DESC, ahorro DESC LIMIT 10'),
        ];
    }

    /** Consumo de beneficios por unidad o comercio y beneficio, por plan; y los que nadie usó. */
    public static function consumoBeneficios(string $d, string $h): array
    {
        $f = [];
        $add = function (string $lugar, string $serv, int $af, string $plan, int $cant, int $sc, int $cd, float $aho) use (&$f) {
            $k = "$lugar|$serv";
            $f[$k] ??= ['lugar' => $lugar, 'serv' => $serv, 'usos' => 0, 'sc' => 0, 'cd' => 0, 'af' => [], 'aho' => 0.0, 'pl' => []];
            $f[$k]['usos'] += $cant;
            $f[$k]['sc'] += $sc;
            $f[$k]['cd'] += $cd;
            $f[$k]['af'][$af] = true;
            $f[$k]['aho'] += $aho;
            $f[$k]['pl'][$plan] = ($f[$k]['pl'][$plan] ?? 0) + $cant;
        };
        foreach (App::$db->all('SELECT l.*, un.nombre_largo AS lugar FROM v_usos_lineas l JOIN unidades un ON un.id = l.unidad_id WHERE l.fecha BETWEEN :d AND :h', ['d' => $d, 'h' => $h]) as $l) {
            $add($l['lugar'], $l['nombre'], (int)$l['afiliado_id'], $l['plan_id'], (int)$l['cantidad'], (int)$l['gratis'], (float)$l['pct'] ? (int)$l['cantidad'] - (int)$l['gratis'] : 0, (float)$l['ahorro_ef']);
        }
        foreach (App::$db->all('SELECT c.*, g.plan_id FROM consumos_comercio c JOIN afiliados a ON a.id = c.afiliado_id JOIN grupos g ON g.id = a.grupo_id WHERE c.fecha BETWEEN :d AND :h', ['d' => $d, 'h' => $h]) as $c) {
            $add($c['comercio'], $c['rubro'], (int)$c['afiliado_id'], $c['plan_id'], 1, 0, (float)$c['pct'] ? 1 : 0, (float)$c['ahorro']);
        }
        $L = array_values($f);
        usort($L, fn($a, $b) => $b['usos'] <=> $a['usos']);
        $usados = array_flip(App::$db->col('SELECT DISTINCT prestacion_cod FROM v_usos_lineas WHERE fecha BETWEEN :d AND :h', ['d' => $d, 'h' => $h]));
        $nadie = [];
        foreach (Catalogo::prestaciones() as $p) {
            if (isset($usados[$p['cod']])) {
                continue;
            }
            foreach (array_keys(Catalogo::planes()) as $pl) {
                if (Beneficios::tieneBeneficio(Catalogo::regla($pl, $p['cod']))) {
                    $nadie[] = $p;
                    break;
                }
            }
        }
        return ['L' => $L, 'nadie' => $nadie];
    }

    /** CU-35: ahorro por período, por grupo y por unidad. */
    public static function ahorro(string $k): array
    {
        [$d, $h] = Documentos::rangoAhorro($k);
        $filas = [];
        foreach (App::$db->col('SELECT id FROM grupos') as $gid) {
            $ids = App::$db->col('SELECT id FROM afiliados WHERE grupo_id = :g', ['g' => $gid]);
            $ah = Beneficios::ahorro(array_map('intval', $ids), $d, $h);
            if ($ah['total'] > 0 || $ah['det']) {
                $filas[] = ['g' => Padron::grupo((int)$gid), 'ah' => $ah, 'cp' => Documentos::cuotasPagadas((int)$gid, $d, $h)];
            }
        }
        usort($filas, fn($a, $b) => $b['ah']['total'] <=> $a['ah']['total']);
        $tot = array_sum(array_map(fn($x) => $x['ah']['total'], $filas));
        $cuo = (float)App::$db->val('SELECT coalesce(sum(importe),0) FROM recibos WHERE fecha BETWEEN :d AND :h', ['d' => $d, 'h' => $h]);
        $porU = [];
        foreach (App::$db->all('SELECT un.nombre_largo AS n, sum(l.ahorro_ef) AS v FROM v_usos_lineas l JOIN unidades un ON un.id = l.unidad_id WHERE l.fecha BETWEEN :d AND :h GROUP BY un.nombre_largo', ['d' => $d, 'h' => $h]) as $x) {
            $porU[$x['n']] = (float)$x['v'];
        }
        foreach (App::$db->all('SELECT comercio AS n, sum(ahorro) AS v FROM consumos_comercio WHERE fecha BETWEEN :d AND :h GROUP BY comercio', ['d' => $d, 'h' => $h]) as $x) {
            $porU[$x['n']] = ($porU[$x['n']] ?? 0) + (float)$x['v'];
        }
        arsort($porU);
        return ['filas' => array_slice($filas, 0, 20), 'grupos' => count($filas), 'tot' => $tot, 'cuo' => $cuo, 'porU' => $porU, 'sinValor' => array_sum(array_map(fn($x) => $x['ah']['sinValor'], $filas)), 'k' => $k];
    }

    /** CU-36: grupos que usaron beneficios en el mes, con su estado de envío. */
    public static function gruposConUso(string $per): array
    {
        $r = [];
        foreach (App::$db->col('SELECT DISTINCT grupo_id FROM (SELECT grupo_id FROM usos WHERE to_char(fecha, \'YYYY-MM\') = :p UNION SELECT a.grupo_id FROM consumos_comercio c JOIN afiliados a ON a.id = c.afiliado_id WHERE to_char(c.fecha, \'YYYY-MM\') = :p) x', ['p' => $per]) as $gid) {
            $g = Padron::grupo((int)$gid);
            $res = Documentos::resumenGrupo($g['id'], $per);
            if ($res['det']) {
                $r[] = ['g' => $g, 't' => Padron::titular($g['id']), 'r' => $res, 'envio' => App::$db->one('SELECT * FROM resumenes_enviados WHERE grupo_id = :g AND periodo = :p', ['g' => $g['id'], 'p' => $per])];
            }
        }
        usort($r, fn($a, $b) => strcmp($a['t']['nombre'], $b['t']['nombre']));
        return $r;
    }

    public static function marcarResumen(int $grupoId, string $per, string $canal): bool
    {
        return App::$db->exec('INSERT INTO resumenes_enviados (grupo_id, periodo, canal) VALUES (:g, :p, :c) ON CONFLICT DO NOTHING', ['g' => $grupoId, 'p' => $per, 'c' => $canal]) > 0;
    }

    /** CU-36: un resumen por grupo y mes, por mail a los que tienen email. */
    public static function enviarResumenes(string $per): int
    {
        $n = 0;
        foreach (self::gruposConUso($per) as $x) {
            if ($x['envio'] || !$x['g']['email']) {
                continue;
            }
            if (self::marcarResumen($x['g']['id'], $per, 'Mail')) {
                $m = Mails::armar('resumen', ['nombre' => Padron::nomPila($x['t']), 'periodo' => Fmt::perLabel($per), 'importe' => Fmt::money($x['r']['total']), 'detalle' => Documentos::detalleResumen($x['r'])]);
                Mails::encolar($x['g']['email'], $m['asunto'], $m['cuerpo'], ['tipo' => 'resumen', 'grupo' => $x['g']['id'], 'periodo' => $per]);
                $n++;
            }
        }
        if ($n) {
            Historial::log(App::$db, 'Mails', 'Resumen de beneficios de ' . Fmt::perLabel($per) . " enviado por mail a $n");
        }
        return $n;
    }

    // ------------------------------------------------------------------ CU-38 Informes

    /** Rango del período del informe. @return array{0:string,1:string,2:string} desde, hasta, etiqueta */
    public static function rango(string $p, string $mes = '', string $d = '', string $h = '', ?string $hoy = null): array
    {
        $hoy ??= Fechas::hoy();
        $y = (int)substr($hoy, 0, 4);
        $m = substr($hoy, 0, 7);
        switch ($p) {
            case 'mesant':
                $pm = Fechas::mesAnterior($hoy);
                return ["$pm-01", Fechas::finPeriodo($pm), Fmt::perLabel($pm)];
            case 'unmes':
                $pm = preg_match('/^\d{4}-\d{2}$/', $mes) ? $mes : $m;
                return ["$pm-01", Fechas::finPeriodo($pm), Fmt::perLabel($pm)];
            case 'anio':
                return ["$y-01-01", $hoy, "Año $y"];
            case 'anioant':
                return [($y - 1) . '-01-01', ($y - 1) . '-12-31', 'Año ' . ($y - 1)];
            case 'entre':
                $d = preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) ? $d : "$m-01";
                $h = preg_match('/^\d{4}-\d{2}-\d{2}$/', $h) ? $h : $hoy;
                if ($d > $h) {
                    [$d, $h] = [$h, $d]; // las fechas invertidas se ordenan solas
                }
                return [$d, $h, 'Del ' . Fmt::fd($d) . ' al ' . Fmt::fd($h)];
            default:
                return ["$m-01", $hoy, Fmt::perLabel($m)];
        }
    }

    /**
     * Datos de un informe: columnas, filas, totales y qué columnas son montos.
     * @return array{cols:array,filas:array,tot:?array,num:int[]}
     */
    public static function informe(string $tipo, string $d, string $h): array
    {
        $db = App::$db;
        $cols = [];
        $filas = [];
        $tot = null;
        $num = [];
        $tit = fn($gid) => Padron::titular((int)$gid)['nombre'];
        $cats = self::categoriasEgreso();
        switch ($tipo) {
            case 'caja':
                $cols = ['Fecha', 'Tipo', 'Concepto', 'Medio', 'Ingreso', 'Egreso', 'Saldo'];
                $num = [4, 5, 6];
                $mv = [];
                foreach ($db->all('SELECT * FROM recibos WHERE fecha BETWEEN :d AND :h', ['d' => $d, 'h' => $h]) as $r) {
                    $mv[] = ['f' => $r['fecha'], 'o' => 1, 't' => 'Ingreso', 'c' => 'Cuota · ' . $tit($r['grupo_id']) . ' · recibo ' . Caja::nroRecibo($r), 'm' => $r['medio'], 'i' => (float)$r['importe'], 'e' => 0.0];
                }
                foreach ($db->all('SELECT * FROM egresos WHERE fecha BETWEEN :d AND :h', ['d' => $d, 'h' => $h]) as $e) {
                    $mv[] = ['f' => $e['fecha'], 'o' => 2, 't' => 'Egreso', 'c' => ($cats[$e['categoria']]['nombre'] ?? '') . " · {$e['proveedor']}" . ($e['detalle'] ? " · {$e['detalle']}" : ''), 'm' => $e['medio'], 'i' => 0.0, 'e' => (float)$e['importe']];
                }
                usort($mv, fn($a, $b) => [$a['f'], $a['o']] <=> [$b['f'], $b['o']]);
                $s = 0;
                foreach ($mv as $x) {
                    $s += $x['i'] - $x['e'];
                    $filas[] = [Fmt::fd($x['f']), $x['t'], $x['c'], $x['m'], $x['i'] ?: '', $x['e'] ?: '', $s];
                }
                $tot = ['', '', 'Totales', '', array_sum(array_column($mv, 'i')), array_sum(array_column($mv, 'e')), $s];
                break;
            case 'facturacion':
                $cols = ['Fecha', 'Comprobante', 'Titular', 'DNI o CUIT', 'Período', 'Neto', 'IVA', 'Total', 'Estado'];
                $num = [5, 6, 7];
                foreach ($db->all('SELECT * FROM facturas WHERE emision BETWEEN :d AND :h ORDER BY emision, letra, numero', ['d' => $d, 'h' => $h]) as $f) {
                    $f = Caja::hidratarFactura($f);
                    $iv = Fmt::ivaContenido($f['importe'], $f['alicuota']) ?? 0;
                    $filas[] = [Fmt::fd($f['emision']), "Factura {$f['letra']} {$f['nro']}", $f['receptor']['nombre'] ?? '', preg_replace('/^(DNI|CUIT) /', '', $f['receptor']['doc'] ?? ''), Fmt::perLabel($f['periodo']), $f['importe'] - $iv, $iv, $f['importe'], $f['estado'] === 'pagada' ? 'Pagada' : 'Pendiente'];
                }
                $tot = ['', '', 'Totales', '', '', array_sum(array_column($filas, 5)), array_sum(array_column($filas, 6)), array_sum(array_column($filas, 7)), ''];
                break;
            case 'egresos':
                $cols = ['Fecha', 'Categoría', 'Proveedor', 'CUIT', 'Comprobante', 'Detalle', 'Medio', 'IVA', 'Importe'];
                $num = [7, 8];
                foreach ($db->all('SELECT * FROM egresos WHERE fecha BETWEEN :d AND :h ORDER BY fecha, id', ['d' => $d, 'h' => $h]) as $e) {
                    $filas[] = [Fmt::fd($e['fecha']), $cats[$e['categoria']]['nombre'] ?? '', $e['proveedor'], $e['cuit'], trim($e['comprobante'] . ' ' . $e['numero']), $e['detalle'], $e['medio'], (float)$e['iva'] ?: '', (float)$e['importe']];
                }
                $tot = ['', 'Totales', '', '', '', '', '', array_sum(array_map(fn($x) => (float)$x[7], $filas)), array_sum(array_column($filas, 8))];
                break;
            case 'diario':
                $cols = ['Fecha', 'Asiento', 'Cuenta', 'Debe', 'Haber'];
                $num = [3, 4];
                $cn = array_column(self::cuentas(), 'nombre', 'codigo');
                foreach (self::asientos($d, $h) as $a) {
                    foreach ($a['l'] as $i => [$c, $de, $ha]) {
                        $filas[] = [$i ? '' : Fmt::fd($a['fecha']), $i ? '' : $a['det'], "$c " . ($cn[$c] ?? ''), $de ?: '', $ha ?: ''];
                    }
                }
                $tot = ['', '', 'Totales', array_sum(array_map(fn($x) => (float)$x[3], $filas)), array_sum(array_map(fn($x) => (float)$x[4], $filas))];
                break;
            case 'usos':
                $cols = ['Fecha', 'Unidad o comercio', 'Afiliado', 'N°', 'Plan', 'Prestación', 'Cant.', 'Beneficio', 'Precio informado', 'Ahorro', 'Registró'];
                $num = [8, 9];
                foreach ($db->all('SELECT l.*, un.nombre_largo AS unidad, a.nombre AS afi, a.nro FROM v_usos_lineas l JOIN unidades un ON un.id = l.unidad_id JOIN afiliados a ON a.id = l.afiliado_id WHERE l.fecha BETWEEN :d AND :h ORDER BY l.fecha, l.hora', ['d' => $d, 'h' => $h]) as $l) {
                    $filas[] = [Fmt::fd($l['fecha']), $l['unidad'], $l['afi'], (int)$l['nro'], Catalogo::plan($l['plan_id'])['nombre'], $l['nombre'], (int)$l['cantidad'], ucfirst(Beneficios::textoAplicado(['gratis' => (int)$l['gratis'], 'cantidad' => (int)$l['cantidad'], 'pct' => (float)$l['pct']])), $l['precio_informado'] ? (float)$l['lista'] : '', (float)$l['ahorro_ef'] ?: '', $l['usuario']];
                }
                foreach ($db->all('SELECT c.*, a.nombre AS afi, a.nro, g.plan_id FROM consumos_comercio c JOIN afiliados a ON a.id = c.afiliado_id JOIN grupos g ON g.id = a.grupo_id WHERE c.fecha BETWEEN :d AND :h ORDER BY c.fecha, c.hora', ['d' => $d, 'h' => $h]) as $c) {
                    $filas[] = [Fmt::fd($c['fecha']), $c['comercio'], $c['afi'], (int)$c['nro'], Catalogo::plan($c['plan_id'])['nombre'], $c['detalle'] ?: $c['rubro'], 1, (float)$c['pct'] ? Fmt::num($c['pct']) . '%' : '—', (float)$c['importe'] ?: '', (float)$c['ahorro'] ?: '', $c['comercio']];
                }
                $tot = ['Totales', '', '', '', '', '', array_sum(array_column($filas, 6)), '', array_sum(array_map(fn($x) => (float)$x[8], $filas)), array_sum(array_map(fn($x) => (float)$x[9], $filas)), ''];
                break;
            case 'altasbajas':
                $cols = ['Fecha', 'Movimiento', 'N°', 'Apellido y nombre', 'DNI', 'Plan', 'Categoría', 'Cuota'];
                $num = [7];
                $mv = [];
                foreach ($db->all('SELECT a.*, g.plan_id FROM afiliados a JOIN grupos g ON g.id = a.grupo_id WHERE a.fecha_alta BETWEEN :d AND :h OR a.fecha_baja BETWEEN :d AND :h', ['d' => $d, 'h' => $h]) as $a) {
                    foreach ([['fecha_alta', 'Alta'], ['fecha_baja', 'Baja']] as [$k, $t]) {
                        if ($a[$k] && $a[$k] >= $d && $a[$k] <= $h) {
                            $mv[] = [$a[$k], $t, (int)$a['nro'], $a['nombre'], $a['doc'], Catalogo::plan($a['plan_id'])['nombre'], Catalogo::categoria($a['categoria_id'])['nombre'], (float)$a['cuota']];
                        }
                    }
                }
                usort($mv, fn($a, $b) => [$a[0], $a[2]] <=> [$b[0], $b[2]]);
                $filas = array_map(fn($x) => array_replace($x, [0 => Fmt::fd($x[0])]), $mv);
                $na = count(array_filter($mv, fn($x) => $x[1] === 'Alta'));
                $tot = ['', "$na altas · " . (count($mv) - $na) . ' bajas', '', '', '', '', '', ''];
                break;
            case 'padron':
                $cols = ['N°', 'Apellido y nombre', 'DNI', 'Titular del grupo', 'Plan', 'Categoría', 'Cuota', 'Alta', 'Teléfono', 'Forma de pago'];
                $num = [6];
                foreach ($db->all("SELECT a.*, g.plan_id, g.forma_pago FROM afiliados a JOIN grupos g ON g.id = a.grupo_id WHERE a.estado = 'activo' ORDER BY a.nombre") as $a) {
                    $t = Padron::titular((int)$a['grupo_id']);
                    $filas[] = [(int)$a['nro'], $a['nombre'], $a['doc'], $t['id'] === (int)$a['id'] ? 'Titular' : $t['nombre'], Catalogo::plan($a['plan_id'])['nombre'], Catalogo::categoria($a['categoria_id'])['nombre'], (float)$a['cuota'], Fmt::fd($a['fecha_alta']), $a['telefono'] ?: $t['telefono'], $a['forma_pago']];
                }
                $tot = ['', count($filas) . ' afiliados activos', '', '', '', '', array_sum(array_column($filas, 6)), '', '', ''];
                break;
            case 'resumen':
                $cols = ['Indicador', 'Valor'];
                $p = ['d' => $d, 'h' => $h];
                $hoy = Fechas::hoy();
                $fac = $db->one('SELECT count(*) AS n, coalesce(sum(importe),0) AS t FROM facturas WHERE emision BETWEEN :d AND :h', $p);
                $cob = (float)$db->val('SELECT coalesce(sum(importe),0) FROM recibos WHERE fecha BETWEEN :d AND :h', $p);
                $eg = (float)$db->val('SELECT coalesce(sum(importe),0) FROM egresos WHERE fecha BETWEEN :d AND :h', $p);
                $aho = (float)$db->val('SELECT coalesce(sum(ahorro_ef),0) FROM v_usos_lineas WHERE fecha BETWEEN :d AND :h', $p) + (float)$db->val('SELECT coalesce(sum(ahorro),0) FROM consumos_comercio WHERE fecha BETWEEN :d AND :h', $p);
                $filas = [
                    ['Afiliados activos (hoy)', (int)$db->val("SELECT count(*) FROM afiliados WHERE estado = 'activo'")],
                    ['Grupos activos (hoy)', (int)$db->val("SELECT count(DISTINCT grupo_id) FROM afiliados WHERE estado = 'activo'")],
                    ['Altas en el período', (int)$db->val('SELECT count(*) FROM afiliados WHERE fecha_alta BETWEEN :d AND :h', $p)],
                    ['Bajas en el período', (int)$db->val('SELECT count(*) FROM afiliados WHERE fecha_baja BETWEEN :d AND :h', $p)],
                    ['Facturas emitidas', (int)$fac['n']], ['Facturado', Fmt::money2($fac['t'])], ['Cobrado', Fmt::money2($cob)], ['Egresos', Fmt::money2($eg)],
                    ['Resultado (cobrado − egresos)', Fmt::money2($cob - $eg)],
                    ['Deuda vencida (hoy)', Fmt::money2((float)$db->val("SELECT coalesce(sum(importe),0) FROM facturas WHERE estado = 'pendiente' AND vto < :h", ['h' => $hoy]))],
                    ['Usos de beneficios en unidades', (int)$db->val('SELECT coalesce(sum(cantidad),0) FROM v_usos_lineas WHERE fecha BETWEEN :d AND :h', $p)],
                    ['Ahorro de los afiliados', Fmt::money2($aho)],
                    ['Consultas atendidas', (int)$db->val('SELECT count(*) FROM consultas WHERE fecha BETWEEN :d AND :h', $p)],
                    ['Autorizaciones emitidas', (int)$db->val('SELECT count(*) FROM autorizaciones WHERE fecha BETWEEN :d AND :h', $p)],
                ];
                break;
            default:
                throw new Falta('Elegí el informe.');
        }
        return ['cols' => $cols, 'filas' => $filas, 'tot' => $tot, 'num' => $num];
    }

    public static function nombreInforme(string $tipo): string
    {
        foreach (CatalogoMaqueta::INFORMES as [$k, $n]) {
            if ($k === $tipo) {
                return $n;
            }
        }
        return $tipo;
    }
}
