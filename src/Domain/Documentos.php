<?php
declare(strict_types=1);

namespace App\Domain;

use App\Core\App;

/**
 * Contenido de los PDF de información y de los mensajes cortos de WhatsApp (RT-04).
 * Cada info* devuelve la estructura que dibuja views/pdf/info.php.
 */
final class Documentos
{
    /** Filas [rubro, texto, carencia] de los beneficios de un plan. */
    public static function filasBenef(string $plan, ?callable $filtro = null): array
    {
        $r = [];
        foreach (Catalogo::beneficiosPlan() as $b) {
            $x = $b['planes'][$plan] ?? null;
            if ($x && (!$filtro || $filtro($b, $x))) {
                $r[] = [$b['rubro'], $x[0], $x[1]];
            }
        }
        return $r;
    }

    public static function infoPlan(string $plan, string $cot = ''): array
    {
        $p = Catalogo::plan($plan);
        $tit = Catalogo::precio('TIT', $plan);
        $rows = [];
        foreach (Catalogo::categorias() as $c) {
            if ($pr = Catalogo::precio($c['id'], $plan)) {
                $rows[] = [$c['nombre'], Fmt::money($pr)];
            }
        }
        return [
            'etiqueta' => 'Información del plan', 'titulo' => $p['nombre'],
            'subtitulo' => ($p['individual'] ? 'Plan individual. ' : '') . 'Cuota del titular: ' . ($tit ? Fmt::money($tit) . ' por mes' : 'consultar') . '.' . ($cot ? " Cuota para tu grupo: $cot." : ''),
            'tabla' => $p['individual'] ? null : ['cols' => ['Integrante', 'Cuota mensual'], 'rows' => $rows],
            'secciones' => [['titulo' => 'Qué incluye', 'rows' => self::filasBenef($plan)]],
            'notas' => Catalogo::condiciones(), 'cierre' => '¡Sumate a Integral del Este!',
        ];
    }

    /** @param string[] $cats categorías cotizadas @param ?array $promo {pct, meses} */
    public static function cotizacion(string $plan, array $cats, ?array $promo): array
    {
        $p = Catalogo::plan($plan);
        $cs = $p['individual'] ? array_slice($cats, 0, 1) : $cats;
        $tot = array_sum(array_map(fn($c) => Catalogo::precio($c, $plan), $cs));
        $falta = (bool)array_filter($cs, fn($c) => !Catalogo::precio($c, $plan));
        return ['plan' => $p, 'cats' => $cs, 'total' => $tot, 'falta' => $falta, 'conPromo' => $promo ? Padron::conPromo($tot, (int)$promo['pct']) : null];
    }

    public static function infoCot(string $plan, array $cats, ?array $promo, string $interesado = ''): array
    {
        $c = self::cotizacion($plan, $cats, $promo);
        $n = count($c['cats']);
        return [
            'etiqueta' => 'Cotización', 'titulo' => 'Cotización · ' . $c['plan']['nombre'],
            'subtitulo' => 'Preparada el ' . Fmt::fd(Fechas::hoy()) . ($interesado ? " para $interesado" : '') . " · $n " . ($n === 1 ? 'persona' : 'personas') . '.',
            'tabla' => ['cols' => ['Integrante', 'Cuota mensual'], 'rows' => array_map(fn($x) => [Catalogo::categoria($x)['nombre'], Catalogo::precio($x, $plan) ? Fmt::money(Catalogo::precio($x, $plan)) : 'Precio a definir'], $c['cats']), 'total' => ['Cuota mensual del grupo', $c['falta'] ? 'Precio a definir' : Fmt::money($c['total'])]],
            'destacado' => $promo && !$c['falta'] ? "PROMOCIÓN: {$promo['pct']}% de descuento durante {$promo['meses']} " . ($promo['meses'] == 1 ? 'mes' : 'meses') . ': pagás ' . Fmt::money($c['conPromo']) . ' por mes. Después, ' . Fmt::money($c['total']) . '.' : '',
            'secciones' => [['titulo' => 'Beneficios del plan', 'rows' => self::filasBenef($plan)]],
            'notas' => Catalogo::condiciones(), 'cierre' => 'Te esperamos en Integral del Este',
        ];
    }

    /** CU-17 y CU-20: situación del afiliado (lo disponible, lo usado y el ahorro del año). */
    public static function infoAfi(array $a): array
    {
        $g = Padron::grupo($a['grupo_id']);
        $pl = $g['plan_id'];
        $hoy = Fechas::hoy();
        $y = substr($hoy, 0, 4);
        $aho = Beneficios::ahorro([$a['id']], "$y-01-01", $hoy)['total'];
        $disp = [];
        foreach (self::filasBenef($pl) as [$r, $x, $c]) {
            $m = Usos::mesesCarencia($c);
            $desde = $m ? Fechas::sumarMeses($a['fecha_alta'], $m) : null;
            $disp[] = [$r, $x, $m === 0 ? 'Ya disponible' : ($desde ? ($desde <= $hoy ? 'Ya disponible' : 'Disponible desde el ' . Fmt::fd($desde)) : $c)];
        }
        $uso = [];
        foreach (Beneficios::saldo($a) as $x) {
            if ($x['r']['gratis'] || $x['veces']) {
                $uso[] = [$x['p']['nombre'], $x['r']['gratis'] ? "{$x['usados']} de {$x['r']['gratis']} sin cargo " . Beneficios::VENT_TXT[$x['r']['per']] . ($x['r']['por_grupo'] ? ' (grupo)' : '') : '—', $x['r']['gratis'] ? (string)$x['quedan'] : '—', (string)$x['veces']];
            }
        }
        $sec = [];
        if ($uso) {
            $sec[] = ['titulo' => "Tu uso en $y", 'cols' => ['Beneficio', 'Sin cargo usados', 'Te quedan', 'Usos'], 'rows' => $uso];
        }
        $sec[] = ['titulo' => 'Tus beneficios', 'cols' => ['Beneficio', 'Detalle', 'Estado'], 'rows' => $disp, 'std' => true];
        return [
            'etiqueta' => 'Mis beneficios', 'titulo' => trim(Padron::nomPila($a) . ' ' . Fmt::capi($a['apellido'])),
            'subtitulo' => "Afiliado N° {$a['nro']} · " . Catalogo::plan($pl)['nombre'] . ' · Alta ' . Fmt::fd($a['fecha_alta']),
            'destacado' => $aho ? "En $y ahorraste " . Fmt::money($aho) . ' gracias a Integral del Este.' : '',
            'secciones' => $sec, 'cierre' => 'Gracias por ser parte de Integral del Este',
        ];
    }

    /** CU-17: información para un interesado sobre los planes que eligió. */
    public static function infoConsulta(string $nombre, array $planes): array
    {
        $pls = array_values(array_filter(Catalogo::planes(), fn($p) => in_array($p['id'], $planes, true)));
        $n = trim($nombre);
        $pi = $n !== '' ? Fmt::partirInteresado($n, Padron::nombresPadron()) : null;
        $saludo = $pi ? Fmt::capi($pi['nombres'][0] ?? $pi['apellido']) : '';
        return [
            'etiqueta' => 'Información solicitada', 'titulo' => count($pls) === 1 ? $pls[0]['nombre'] : 'Planes de Integral del Este',
            'saludo' => 'Hola' . ($saludo ? " $saludo" : '') . ', gracias por comunicarte con Integral del Este. Te enviamos la información que nos pediste.',
            'tabla' => ['cols' => ['Plan', 'Cuota del titular'], 'rows' => array_map(fn($p) => [$p['nombre'], Catalogo::precio('TIT', $p['id']) ? Fmt::money(Catalogo::precio('TIT', $p['id'])) . ' por mes' : 'consultar'], $pls)],
            'secciones' => array_map(fn($p) => ['titulo' => $p['nombre'], 'rows' => self::filasBenef($p['id'])], $pls),
            'notas' => Catalogo::condiciones(), 'cierre' => 'Si querés sumar a tu familia, te armamos la cotización',
        ];
    }

    public static function lugarPromo(array $p): string
    {
        if (!$p['unidad']) {
            return 'Integral del Este';
        }
        return Catalogo::unidades(true)[$p['unidad']]['nombre_largo'] ?? $p['unidad'];
    }

    public static function infoPromo(array $p, ?array $t): array
    {
        $l = self::lugarPromo($p);
        return [
            'etiqueta' => 'Promoción', 'titulo' => $p['titulo'], 'subtitulo' => 'Válida del ' . Fmt::fd($p['desde']) . ' al ' . Fmt::fd($p['hasta']) . " · $l",
            'saludo' => $t ? 'Hola ' . Padron::nomPila($t) . ', por ser afiliado de Integral del Este tenés esta promoción especial.' : 'Por ser afiliado de Integral del Este tenés esta promoción especial.',
            'hero' => ['grande' => $p['beneficio'], 'chico' => "en $l"], 'parrafo' => $p['texto'] ?? '',
            'notas' => array_values(array_filter([$p['cond'] ? 'Condiciones: ' . $p['cond'] : '', 'Para usarla presentá tu DNI. Es para afiliados de Integral del Este con la cuota al día.', 'Promoción válida del ' . Fmt::fd($p['desde']) . ' al ' . Fmt::fd($p['hasta']) . '.'])),
            'cierre' => '¡Aprovechala!',
        ];
    }

    public static function infoCumple(array $a): array
    {
        return [
            'etiqueta' => '¡Feliz cumpleaños!', 'titulo' => '¡Feliz cumpleaños, ' . Padron::nomPila($a) . '!',
            'saludo' => 'Todo el equipo de Integral del Este te desea un día lleno de alegría, salud y momentos lindos con los tuyos. Gracias por ser parte de nuestra familia.',
            'destacado' => '¡Que tengas un muy feliz cumpleaños!', 'cierre' => 'Con cariño, Integral del Este',
        ];
    }

    public static function textoCumple(array $a): string
    {
        return '¡Feliz cumpleaños, ' . Padron::nomPila($a) . "! 🎉🎂\n\nTodo el equipo de *Integral del Este* te desea un día lleno de alegría, salud y momentos lindos con los tuyos.\n\nGracias por ser parte de nuestra familia. ¡Que lo disfrutes mucho!";
    }

    /** CU-36: detalle de beneficios usados por el grupo en un mes. */
    public static function resumenGrupo(int $grupoId, string $per): array
    {
        $ids = array_column(Padron::miembros($grupoId), 'id');
        return Beneficios::ahorro($ids, "$per-01", Fechas::finPeriodo($per));
    }

    public static function infoResumen(array $g, string $per): array
    {
        $t = Padron::titular($g['id']);
        $r = self::resumenGrupo($g['id'], $per);
        return [
            'etiqueta' => 'Resumen de beneficios', 'titulo' => 'Tus beneficios de ' . Fmt::perLabel($per),
            'subtitulo' => "{$t['nombre']} · Afiliado N° {$t['nro']} · " . Catalogo::plan($g['plan_id'])['nombre'],
            'saludo' => 'Hola ' . Padron::nomPila($t) . ', este es el detalle de los beneficios que usaste este mes y cuánto ahorraste por ser parte de Integral del Este.',
            'tabla' => ['cols' => ['Beneficio', 'Usos', 'Sin cargo', 'Ahorro'], 'rows' => array_map(fn($x) => [$x['nombre'], (string)$x['veces'], $x['sc'] ? (string)$x['sc'] : '—', $x['monto'] ? Fmt::money($x['monto']) : '—'], $r['det']), 'total' => ['Ahorro total del mes', '', '', Fmt::money($r['total'])]],
            'destacado' => $r['total'] ? 'En ' . Fmt::perLabel($per) . ' ahorraste ' . Fmt::money($r['total']) . ' gracias a Integral del Este.' : '',
            'cierre' => '¡Gracias por ser parte de Integral del Este!',
        ];
    }

    /** Detalle para la variable {detalle} del mail del resumen. */
    public static function detalleResumen(array $r): string
    {
        return implode("\n", array_map(fn($x) => "• {$x['nombre']}: {$x['veces']} " . ($x['veces'] === 1 ? 'vez' : 'veces') . ($x['sc'] ? " ({$x['sc']} sin cargo)" : '') . ($x['monto'] ? ' → ahorro ' . Fmt::money($x['monto']) : ''), $r['det']));
    }

    public const PER_AHO = ['mes' => 'este mes', 'trim' => 'los últimos 3 meses', 'anio' => 'este año'];

    public static function rangoAhorro(string $k, ?string $hoy = null): array
    {
        $hoy ??= Fechas::hoy();
        return match ($k) {
            'mes' => [substr($hoy, 0, 7) . '-01', $hoy],
            'trim' => [Fechas::perMas(substr($hoy, 0, 7), -2) . '-01', $hoy],
            default => [substr($hoy, 0, 4) . '-01-01', $hoy],
        };
    }

    public static function cuotasPagadas(int $grupoId, string $desde, string $hasta): float
    {
        return (float)App::$db->val('SELECT coalesce(sum(importe),0) FROM recibos WHERE grupo_id = :g AND fecha BETWEEN :d AND :h', ['g' => $grupoId, 'd' => $desde, 'h' => $hasta]);
    }

    /** CU-37: "Tu ahorro" de un grupo. */
    public static function infoAhorro(array $g, string $k): array
    {
        [$d, $h] = self::rangoAhorro($k);
        $t = Padron::titular($g['id']);
        $r = Beneficios::ahorro(array_column(Padron::miembros($g['id']), 'id'), $d, $h);
        $cp = self::cuotasPagadas($g['id'], $d, $h);
        return [
            'etiqueta' => 'Tu ahorro', 'titulo' => 'Lo que ahorraste ' . self::PER_AHO[$k],
            'subtitulo' => "{$t['nombre']} · Afiliado N° {$t['nro']} · " . Catalogo::plan($g['plan_id'])['nombre'],
            'saludo' => 'Hola ' . Padron::nomPila($t) . ', este es el detalle de lo que ahorraste ' . self::PER_AHO[$k] . ' por ser parte de Integral del Este.',
            'tabla' => ['cols' => ['Beneficio', 'Usos', 'Sin cargo', 'Ahorro'], 'rows' => array_map(fn($x) => [$x['nombre'], (string)$x['veces'], $x['sc'] ? (string)$x['sc'] : '—', $x['monto'] ? Fmt::money($x['monto']) : '—'], $r['det']), 'total' => ['Ahorro total', '', '', Fmt::money($r['total'])]],
            'destacado' => ($r['total'] ? 'Ahorraste ' . Fmt::money($r['total']) . ' gracias a Integral del Este.' : '') . ($cp ? ' Cuotas pagadas en el período: ' . Fmt::money($cp) . '.' : '') . ($cp && $r['total'] > $cp ? ' ¡Ahorraste más de lo que pagaste!' : ''),
            'cierre' => '¡Gracias por ser parte de Integral del Este!',
            '_total' => $r['total'], '_pagado' => $cp,
        ];
    }

    /** Mensaje corto que acompaña al PDF por WhatsApp. */
    public static function mensajeCorto(string $tipo, string $nombre = '', array $x = []): string
    {
        $hola = 'Hola' . ($nombre !== '' ? " $nombre" : '') . ' 👋 ';
        return match ($tipo) {
            'cotizacion' => $hola . "Te mandamos la cotización del {$x['plan']} de Integral del Este en el PDF. Cualquier consulta, escribinos.",
            'plan' => $hola . "Te mandamos la información del {$x['plan']} de Integral del Este en el PDF.",
            'afiliado' => $hola . 'Te mandamos en el PDF todos tus beneficios de Integral del Este: lo que tenés disponible, lo que usaste y cuánto ahorraste.',
            'consulta' => $hola . 'Gracias por comunicarte con Integral del Este. Te mandamos la información en el PDF.',
            'promo' => $hola . "Tenemos una promoción especial para vos: {$x['beneficio']} en {$x['lugar']}. Te mandamos el detalle en el PDF. ¡Aprovechala!",
            'resumen' => $hola . 'Te mandamos el resumen de los beneficios que usaste en ' . ($x['periodo'] ?? '') . ' con Integral del Este.',
            'ahorro' => $hola . 'Te contamos cuánto ahorraste ' . ($x['periodo'] ?? '') . ' gracias a Integral del Este' . (!empty($x['mas']) ? '. ¡Ahorraste más de lo que pagaste! 🎉' : '.'),
            'factura' => $hola . 'Te enviamos la factura de tu cuota de ' . ($x['periodo'] ?? '') . ' de Integral del Este.',
            'recibo' => $hola . 'Te enviamos el recibo de tu pago. ¡Gracias!',
            default => $hola,
        };
    }
}
