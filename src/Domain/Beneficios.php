<?php
declare(strict_types=1);

namespace App\Domain;

use App\Core\App;
use App\Core\Config;

/**
 * RT-06: cálculo del beneficio por plan y prestación.
 *  - Solo aplica si el afiliado está activo y su grupo no tiene deuda vencida (RT-05).
 *  - Sin cargo: gratis − usados en la ventana (mes, semestre, año), por grupo si corresponde,
 *    cuando la antigüedad alcanza la carencia del sin cargo.
 *  - Porcentaje: último escalón alcanzado de la escala, o "desc" desde la carencia "car".
 *  - maxDesc: tope de usos con descuento por año calendario.
 *  - Ahorro = valor × sin cargo + valor × con descuento × porcentaje.
 */
final class Beneficios
{
    public const REGLA0 = ['gratis' => 0, 'per' => 'anio', 'por_grupo' => false, 'car_gratis' => 0, 'desc' => 0.0, 'modo' => 'fijo', 'car' => 0, 'escala' => '', 'max_desc' => 0];
    public const VENT_TXT = ['anio' => 'este año', 'mes' => 'este mes', 'semestre' => 'este semestre'];
    public const PER_TXT = ['anio' => 'por año', 'mes' => 'por mes', 'semestre' => 'por semestre'];

    // ------------------------------------------------------------------ reglas (puras)

    /** "0:10, 90:20" → [[0,10],[90,20]] ordenado por días. */
    public static function escala(array $r): array
    {
        $e = [];
        foreach (explode(',', (string)($r['escala'] ?? '')) as $x) {
            $p = array_map('trim', explode(':', $x));
            if (count($p) === 2 && is_numeric($p[0]) && is_numeric($p[1])) {
                $e[] = [(int)$p[0], (float)$p[1]];
            }
        }
        usort($e, fn($a, $b) => $a[0] <=> $b[0]);
        return $e;
    }

    public static function pctPorAntig(array $r, int $dias): float
    {
        $e = self::escala($r);
        if (!$e) {
            return $dias >= (int)$r['car'] ? (float)$r['desc'] : 0.0;
        }
        $p = 0.0;
        foreach ($e as [$d, $v]) {
            if ($dias >= $d) {
                $p = $v;
            }
        }
        return $p;
    }

    public static function tieneBeneficio(array $r): bool
    {
        return $r['gratis'] > 0 || $r['desc'] > 0 || self::escala($r) !== [];
    }

    public static function pct(float $v): string
    {
        return rtrim(rtrim(number_format($v, 2, ',', ''), '0'), ',') . '%';
    }

    /** Descripción en texto de la regla (como textoRegla de la maqueta). */
    public static function textoRegla(array $r): string
    {
        $p = [];
        if ($r['gratis']) {
            $p[] = "{$r['gratis']} sin cargo " . (self::PER_TXT[$r['per']] ?? 'por año') . ($r['por_grupo'] ? ' por grupo' : '') . ($r['car_gratis'] ? ' (desde los ' . round($r['car_gratis'] / 30) . ' meses)' : '');
        }
        $e = self::escala($r);
        if ($e) {
            $p[] = 'escala por antigüedad: ' . implode(', ', array_map(fn($x) => $x[0] ? self::pct($x[1]) . ' desde los ' . round($x[0] / 30) . ' meses' : self::pct($x[1]) . ' al inicio', $e));
        } elseif ($r['desc']) {
            $p[] = ($r['gratis'] ? 'después ' : '') . ($r['modo'] === 'hasta' ? 'hasta ' : '') . self::pct($r['desc']) . ' de descuento' . ($r['car'] ? ' (desde los ' . round($r['car'] / 30) . ' meses)' : '') . ($r['max_desc'] ? ", máximo {$r['max_desc']} por año" : '');
        }
        return $p ? implode('; ', $p) : 'sin beneficio cargado';
    }

    /**
     * Cálculo puro de una línea (sin base de datos).
     * @param array $r regla
     * @param bool $aplica activo y al día
     * @param int $dias antigüedad
     * @param int $usados sin cargo ya usados en la ventana
     * @param int $conDesc usos con descuento ya hechos en el año
     * @param float $pu precio unitario (informado o de referencia)
     * @param ?float $pctPedido porcentaje que aplicó la unidad en reglas "hasta" (null = el máximo)
     */
    public static function calcularLinea(array $r, bool $aplica, int $dias, int $cant, int $usados, int $conDesc, float $pu, ?float $pctPedido = null): array
    {
        $cant = max(1, $cant);
        $gratisOk = $aplica && $r['gratis'] > 0 && $dias >= $r['car_gratis'];
        $disp = $gratisOk ? max(0, $r['gratis'] - $usados) : 0;
        $gratis = min($cant, $disp);
        $max = $aplica ? self::pctPorAntig($r, $dias) : 0.0;
        $pagas = $cant - $gratis;
        $tope = false;
        if ($max && $r['max_desc']) {
            $libres = max(0, $r['max_desc'] - $conDesc);
            if ($libres < $pagas) {
                $tope = true;
                if (!$libres) {
                    $max = 0.0;
                }
            }
        }
        $pct = (!$max || !$pagas) ? 0.0 : ($r['modo'] === 'hasta' ? min($max, max(0.0, $pctPedido ?? $max)) : $max);
        $conPct = $r['max_desc'] ? min($pagas, max(0, $r['max_desc'] - $conDesc)) : $pagas;
        $lista = $pu * $cant;
        $ahorro = $pu * $gratis + round($pu * ($pct ? $conPct : 0) * $pct) / 100;
        return [
            'cantidad' => $cant, 'gratis' => $gratis, 'pct' => $pct, 'precio' => $pu, 'lista' => $lista,
            'ahorro' => round($ahorro, 2), 'total' => round($lista - $ahorro, 2), 'max' => $pagas ? $max : 0.0,
            'tope' => $tope, 'usados_despues' => $usados + $gratis, 'pagas' => $pagas,
        ];
    }

    // ------------------------------------------------------------------ consultas

    /** Sin cargo usados en la ventana de la regla (a todo el grupo si por_grupo). */
    public static function usadosGratis(array $a, string $cod, array $r, ?string $hoy = null): int
    {
        $hoy ??= Fechas::hoy();
        $desde = Fechas::inicioVentana($r['per'] ?: 'anio', $hoy);
        $cond = $r['por_grupo'] ? 'u.grupo_id = :x' : 'u.afiliado_id = :x';
        return (int)App::$db->val(
            "SELECT coalesce(sum(l.gratis),0) FROM usos_lineas l JOIN usos u ON u.id = l.uso_id
             WHERE $cond AND l.prestacion_cod = :c AND u.fecha >= :d AND u.fecha <= :h",
            ['x' => $r['por_grupo'] ? $a['grupo_id'] : $a['id'], 'c' => $cod, 'd' => $desde, 'h' => $hoy]
        );
    }

    /** Usos con descuento en el año calendario (para maxDesc). */
    public static function usadosConDesc(array $a, string $cod, ?string $hoy = null): int
    {
        $hoy ??= Fechas::hoy();
        return (int)App::$db->val(
            "SELECT coalesce(sum(l.cantidad - l.gratis),0) FROM usos_lineas l JOIN usos u ON u.id = l.uso_id
             WHERE u.afiliado_id = :a AND l.prestacion_cod = :c AND l.pct > 0 AND u.fecha >= :d AND u.fecha <= :h",
            ['a' => $a['id'], 'c' => $cod, 'd' => substr($hoy, 0, 4) . '-01-01', 'h' => $hoy]
        );
    }

    /** Antigüedad en días desde el alta del afiliado. */
    public static function dias(array $a, ?string $hoy = null): int
    {
        return max(0, Fechas::dias($a['fecha_alta'], $hoy ?? Fechas::hoy()));
    }

    /**
     * Calcula las líneas de un uso (calcVenta de la maqueta).
     * @param array $items [{cod, cant, precio?, pct?}]
     */
    public static function calcular(?array $a, array $items, ?string $hoy = null): array
    {
        $hoy ??= Fechas::hoy();
        $activo = $a && $a['activo'];
        $alDia = $a && !Padron::cuenta($a['grupo_id'], $hoy)['vencidas'];
        $aplica = $activo && $alDia;
        $plan = $a ? Padron::grupo($a['grupo_id'])['plan_id'] : 'PLUS';
        $dias = $a ? self::dias($a, $hoy) : 0;
        $usados = [];
        $conDesc = [];
        $lineas = [];
        foreach ($items as $it) {
            $p = Catalogo::prestacion((string)$it['cod']);
            if (!$p) {
                continue;
            }
            $r = Catalogo::regla($plan, $p['cod']);
            if (!isset($usados[$p['cod']])) {
                $usados[$p['cod']] = $a && $r['gratis'] ? self::usadosGratis($a, $p['cod'], $r, $hoy) : 0;
                $conDesc[$p['cod']] = $a ? self::usadosConDesc($a, $p['cod'], $hoy) : 0;
            }
            $informado = (float)($it['precio'] ?? 0);
            $pu = $informado > 0 ? $informado : $p['valor_ref'];
            $l = self::calcularLinea($r, $aplica, $dias, (int)($it['cant'] ?? 1), $usados[$p['cod']], $conDesc[$p['cod']], $pu, isset($it['pct']) && $it['pct'] !== '' ? (float)$it['pct'] : null);
            $usados[$p['cod']] += $l['gratis'];
            if ($l['pct']) {
                $conDesc[$p['cod']] += $l['pagas'];
            }
            $notas = [];
            if ($a && !$aplica) {
                $notas[] = 'Sin beneficio';
            } elseif ($a) {
                if ($r['gratis']) {
                    $notas[] = $dias < $r['car_gratis'] ? 'Sin cargo desde el ' . Fmt::fd(Fechas::sumarDias($a['fecha_alta'], $r['car_gratis'])) : ($l['gratis'] ? "{$l['gratis']} sin cargo" : 'Sin cargo agotado') . " (usadas {$usados[$p['cod']]} de {$r['gratis']} " . self::VENT_TXT[$r['per']] . ($r['por_grupo'] ? ' en el grupo' : '') . ')';
                }
                if ($l['pct'] && $l['pagas']) {
                    $notas[] = self::pct($l['pct']) . ' de descuento';
                } elseif ($l['pagas'] && self::tieneBeneficio($r) && !$l['max'] && !$l['tope'] && ($r['desc'] || self::escala($r))) {
                    $notas[] = 'Descuento desde el ' . Fmt::fd(Fechas::sumarDias($a['fecha_alta'], $r['car']));
                }
                if ($l['tope']) {
                    $notas[] = "tope de {$r['max_desc']} con descuento por año alcanzado";
                }
                if (!$notas) {
                    $notas[] = 'Sin beneficio para esta prestación';
                }
            }
            $lineas[] = $l + ['cod' => $p['cod'], 'nombre' => $p['nombre'], 'precio_informado' => $informado > 0, 'nota' => implode(' · ', $notas), 'modo' => $r['modo'], 'regla' => $r];
        }
        $t = fn($k) => array_sum(array_column($lineas, $k));
        return ['aplica' => $aplica, 'activo' => $activo, 'al_dia' => $alDia, 'plan' => $plan, 'lineas' => $lineas, 'lista' => $t('lista'), 'ahorro' => $t('ahorro'), 'total' => $t('total')];
    }

    /** Texto del beneficio aplicado: "sin cargo", "30%" o "sin beneficio". */
    public static function textoAplicado(array $l): string
    {
        if ($l['gratis'] && $l['gratis'] === $l['cantidad']) {
            return 'sin cargo';
        }
        if ($l['gratis']) {
            return "{$l['gratis']} sin cargo y " . ($l['cantidad'] - $l['gratis']) . ' con ' . self::pct($l['pct']);
        }
        return $l['pct'] ? self::pct($l['pct']) . ' de descuento' : 'sin beneficio';
    }

    /**
     * CU-25: saldo de beneficios del afiliado en el año (saldoBeneficios).
     * @return array<int,array{p:array,r:array,usados:int,quedan:int,veces:int,ahorro:float,est:string}>
     */
    public static function saldo(array $a, ?string $hoy = null): array
    {
        $hoy ??= Fechas::hoy();
        $plan = Padron::grupo($a['grupo_id'])['plan_id'];
        $dias = self::dias($a, $hoy);
        $y = substr($hoy, 0, 4);
        $usos = [];
        foreach (App::$db->all(
            "SELECT prestacion_cod, sum(cantidad) AS veces, sum(ahorro_ef) AS ahorro FROM v_usos_lineas
             WHERE afiliado_id = :a AND fecha >= :d AND fecha <= :h GROUP BY prestacion_cod",
            ['a' => $a['id'], 'd' => "$y-01-01", 'h' => $hoy]
        ) as $u) {
            $usos[$u['prestacion_cod']] = $u;
        }
        $r = [];
        foreach (Catalogo::prestaciones() as $p) {
            $rg = Catalogo::regla($plan, $p['cod']);
            $veces = (int)($usos[$p['cod']]['veces'] ?? 0);
            if (!self::tieneBeneficio($rg) && !$veces) {
                continue;
            }
            $usados = $rg['gratis'] ? self::usadosGratis($a, $p['cod'], $rg, $hoy) : 0;
            $est = [];
            if ($rg['gratis'] && $dias < $rg['car_gratis']) {
                $est[] = 'Sin cargo desde el ' . Fmt::fd(Fechas::sumarDias($a['fecha_alta'], $rg['car_gratis']));
            }
            $esc = self::escala($rg);
            if ($esc) {
                $hoyPct = self::pctPorAntig($rg, $dias);
                $prox = null;
                foreach ($esc as $x) {
                    if ($x[0] > $dias) {
                        $prox = $x;
                        break;
                    }
                }
                $est[] = 'Hoy ' . self::pct($hoyPct) . ($prox ? ' · ' . self::pct($prox[1]) . ' desde el ' . Fmt::fd(Fechas::sumarDias($a['fecha_alta'], $prox[0])) : '');
            } elseif ($rg['desc'] && $dias < $rg['car']) {
                $est[] = 'Descuento desde el ' . Fmt::fd(Fechas::sumarDias($a['fecha_alta'], $rg['car']));
            }
            $r[] = ['p' => $p, 'r' => $rg, 'usados' => $usados, 'quedan' => max(0, $rg['gratis'] - $usados), 'veces' => $veces, 'ahorro' => (float)($usos[$p['cod']]['ahorro'] ?? 0), 'est' => implode(' · ', $est)];
        }
        return $r;
    }

    /**
     * CU-20: verificación de una prestación para un afiliado.
     * @return array{a:array,p:array,checks:array,ok:bool,motivo:string,regla:array}
     */
    public static function verificar(array $a, array $p, ?string $hoy = null): array
    {
        $hoy ??= Fechas::hoy();
        $g = Padron::grupo($a['grupo_id']);
        $ct = Padron::cuenta($g['id'], $hoy);
        $rg = Catalogo::regla($g['plan_id'], $p['cod']);
        $dias = self::dias($a, $hoy);
        $carGral = (int)(Config::get('carencias', [])[$p['tipo']] ?? 0);
        // Carencia exigida: la mayor entre la general del tipo y la de la regla (P-20).
        $carRegla = self::escala($rg) ? 0 : min($rg['car'], $rg['gratis'] ? $rg['car_gratis'] : $rg['car']);
        $necesita = max($carGral, $carRegla);
        $falta = max(0, $necesita - $dias);
        $tipo = Catalogo::TIPOS[$p['tipo']] ?? $p['tipo'];
        $checks = [
            ['ok' => $a['activo'], 't' => 'Afiliado activo', 'd' => $a['activo'] ? "N° {$a['nro']} · " . Catalogo::categoria($a['categoria_id'])['nombre'] : 'Dado de baja el ' . Fmt::fd($a['fecha_baja'])],
            ['ok' => $falta === 0, 't' => 'Carencia', 'd' => $necesita ? "$tipo: requiere $necesita días desde el alta. Lleva $dias" . ($falta ? ", faltan $falta" : '') . '.' : "$tipo: sin carencia."],
            ['ok' => true, 't' => 'Beneficio del ' . Catalogo::plan($g['plan_id'])['nombre'], 'd' => self::textoRegla($rg) . '.'],
            ['ok' => !$ct['vencidas'], 't' => 'Pagos al día', 'd' => $ct['vencidas'] ? count($ct['vencidas']) . ' cuota(s) vencida(s): ' . implode(', ', array_map(fn($f) => Fmt::perLabel($f['periodo']), $ct['vencidas'])) . '.' : 'Sin cuotas vencidas.'],
        ];
        $motivos = [];
        foreach ($checks as $c) {
            if (!$c['ok']) {
                $motivos[] = match ($c['t']) {
                    'Carencia' => "En carencia (faltan $falta días)",
                    'Pagos al día' => 'Cuotas impagas',
                    default => 'Afiliado dado de baja',
                };
            }
        }
        return ['a' => $a, 'p' => $p, 'checks' => $checks, 'ok' => !$motivos, 'motivo' => implode('; ', $motivos), 'regla' => $rg];
    }

    /**
     * CU-22: qué le corresponde hoy al afiliado en cada prestación de la unidad.
     */
    public static function hoyEnUnidad(array $a, int $unidadId, ?string $hoy = null): array
    {
        $hoy ??= Fechas::hoy();
        $plan = Padron::grupo($a['grupo_id'])['plan_id'];
        $dias = self::dias($a, $hoy);
        $items = [];
        foreach (Catalogo::prestaciones() as $p) {
            if ((int)$p['unidad_id'] !== $unidadId) {
                continue;
            }
            $r = Catalogo::regla($plan, $p['cod']);
            $c = self::calcular($a, [['cod' => $p['cod'], 'cant' => 1]], $hoy);
            $L = $c['lineas'][0];
            $usados = $r['gratis'] ? self::usadosGratis($a, $p['cod'], $r, $hoy) : 0;
            $quedan = max(0, $r['gratis'] - $usados);
            if ($L['gratis']) {
                $pc = self::pctPorAntig($r, $dias);
                $despues = $pc ? self::pct($pc) . ' de descuento' : ($r['desc'] ? self::pct($r['desc']) . ' de descuento desde el ' . Fmt::fd(Fechas::sumarDias($a['fecha_alta'], $r['car'])) : 'precio normal');
                $h = ['t' => 'SIN CARGO', 'c' => 'ok', 'd' => 'Le ' . ($quedan === 1 ? 'queda' : 'quedan') . " $quedan de {$r['gratis']} sin cargo " . self::VENT_TXT[$r['per']] . ($r['por_grupo'] ? ' (en su grupo)' : '') . ". Después: $despues."];
            } elseif ($L['max']) {
                $h = ['t' => ($r['modo'] === 'hasta' ? 'HASTA ' : '') . self::pct($L['max']) . ' DE DESCUENTO', 'c' => 'ok', 'd' => $r['gratis'] ? "Ya usó {$r['gratis']} sin cargo " . self::VENT_TXT[$r['per']] . '.' : ''];
            } else {
                $d = ($r['gratis'] && $usados >= $r['gratis'] && $r['desc'] && $dias < $r['car'])
                    ? "Ya usó las {$r['gratis']} sin cargo. El " . self::pct($r['desc']) . ' de descuento en las siguientes empieza el ' . Fmt::fd(Fechas::sumarDias($a['fecha_alta'], $r['car'])) . ' (carencia del plan). Hasta entonces se cobra precio normal.'
                    : ($L['nota'] ?: 'Se cobra precio normal.');
                $h = ['t' => 'SIN BENEFICIO HOY', 'c' => 'warn', 'd' => $d];
            }
            $prox = '';
            foreach (self::escala($r) as $x) {
                if ($x[0] > $dias) {
                    $prox = 'Desde el ' . Fmt::fd(Fechas::sumarDias($a['fecha_alta'], $x[0])) . ' pasa a ' . self::pct($x[1]) . '.';
                    break;
                }
            }
            // Autorización emitida por Integral en los últimos 30 días, pendiente de registrar.
            $pa = self::autorizacionPendiente($a['id'], $p['cod'], $hoy);
            if ($pa) {
                $h = ['t' => $pa['gratis'] ? 'AUTORIZADA SIN CARGO' : ((float)$pa['pct'] ? 'AUTORIZADA ' . self::pct((float)$pa['pct']) : 'AUTORIZADA'), 'c' => 'ok', 'd' => 'Integral lo autorizó el ' . Fmt::fd($pa['fecha']) . '. Al registrar el uso se confirma esa autorización.'];
            }
            $items[] = ['p' => $p, 'hoy' => $h, 'prox' => $prox, 'modo' => $r['modo'], 'max' => $L['max']];
        }
        return $items;
    }

    /** Uso pendiente de confirmar por la unidad (creado por una autorización en los últimos 30 días). */
    public static function autorizacionPendiente(int $afiliadoId, string $cod, ?string $hoy = null): ?array
    {
        $hoy ??= Fechas::hoy();
        return App::$db->one(
            "SELECT u.id AS uso_id, u.fecha, l.id AS linea_id, l.gratis, l.pct, l.cantidad FROM usos u JOIN usos_lineas l ON l.uso_id = u.id
             WHERE u.pendiente_unidad AND u.afiliado_id = :a AND l.prestacion_cod = :c AND u.fecha >= :d ORDER BY u.fecha LIMIT 1",
            ['a' => $afiliadoId, 'c' => $cod, 'd' => Fechas::sumarDias($hoy, -30)]
        );
    }

    /**
     * Ahorro de un conjunto de afiliados en un período (usos en unidades y consumos en comercios).
     * @return array{total:float,det:array,sinValor:int}
     */
    public static function ahorro(array $ids, string $desde, string $hasta): array
    {
        if (!$ids) {
            return ['total' => 0.0, 'det' => [], 'sinValor' => 0];
        }
        $in = implode(',', array_map('intval', $ids));
        $det = [];
        $total = 0.0;
        $sinValor = 0;
        foreach (App::$db->all("SELECT nombre, sum(cantidad) AS veces, sum(gratis) AS sc, sum(ahorro_ef) AS monto, sum(CASE WHEN sin_valor THEN 1 ELSE 0 END) AS sv FROM v_usos_lineas WHERE afiliado_id IN ($in) AND fecha BETWEEN :d AND :h GROUP BY nombre", ['d' => $desde, 'h' => $hasta]) as $x) {
            $det[$x['nombre']] = ['nombre' => $x['nombre'], 'veces' => (int)$x['veces'], 'sc' => (int)$x['sc'], 'monto' => (float)$x['monto']];
            $total += (float)$x['monto'];
            $sinValor += (int)$x['sv'];
        }
        foreach (App::$db->all("SELECT comercio, count(*) AS veces, sum(ahorro) AS monto FROM consumos_comercio WHERE afiliado_id IN ($in) AND fecha BETWEEN :d AND :h GROUP BY comercio", ['d' => $desde, 'h' => $hasta]) as $x) {
            $k = $x['comercio'];
            $det[$k] ??= ['nombre' => $k, 'veces' => 0, 'sc' => 0, 'monto' => 0.0];
            $det[$k]['veces'] += (int)$x['veces'];
            $det[$k]['monto'] += (float)$x['monto'];
            $total += (float)$x['monto'];
        }
        $det = array_values($det);
        usort($det, fn($a, $b) => $b['monto'] <=> $a['monto']);
        return ['total' => round($total, 2), 'det' => $det, 'sinValor' => $sinValor];
    }
}
