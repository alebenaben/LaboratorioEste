<?php
declare(strict_types=1);

namespace App\Domain;

use App\Core\App;
use App\Core\Falta;
use App\Core\Historial;
use App\Core\HttpError;

/** Retención de bajas (CU-10, CU-12, CU-13). */
final class Retencion
{
    /** Propuestas que puede cerrar un usuario de atención; el resto lo decide administración. */
    public const TIPOS_USUARIO = ['charla', 'regalo'];

    public static function motivos(): array
    {
        return array_column(CatalogoMaqueta::MOTIVOS_BAJA, 1, 0);
    }

    public static function motivoTexto(string $k): string
    {
        return self::motivos()[$k] ?? $k;
    }

    /** Antigüedad en texto: "1 año y 3 meses". */
    public static function antTxt(string $alta, ?string $hoy = null): string
    {
        $m = max(0, (int)floor(Fechas::dias($alta, $hoy ?? Fechas::hoy()) / 30.44));
        $y = intdiv($m, 12);
        $r = $m % 12;
        return $y ? "$y " . ($y === 1 ? 'año' : 'años') . ($r ? " y $r " . ($r === 1 ? 'mes' : 'meses') : '') : "$m " . ($m === 1 ? 'mes' : 'meses');
    }

    /** Retención aceptada en los últimos 365 días (no se vuelven a ofrecer descuentos). */
    public static function previa(int $grupoId, ?string $hoy = null): ?array
    {
        $hoy ??= Fechas::hoy();
        return App::$db->one("SELECT * FROM retenciones WHERE grupo_id = :g AND resultado = 'retenido' AND fecha > :d ORDER BY fecha DESC LIMIT 1", ['g' => $grupoId, 'd' => Fechas::sumarDias($hoy, -365)]);
    }

    /**
     * Propuestas en orden según el motivo (ofertasRetencion de la maqueta).
     * @return array{L:array,prev:?array,ant:int,alDia:bool,bajo:?string,cuotaBajo:float}
     */
    public static function ofertas(array $a, string $motivo, ?string $hoy = null): array
    {
        $hoy ??= Fechas::hoy();
        $g = Padron::grupo($a['grupo_id']);
        $pl = $g['plan_id'];
        $base = Padron::cuotaActual($g);
        $ant = Fechas::dias($a['fecha_alta'], $hoy);
        $alDia = !Padron::cuenta($g['id'], $hoy)['vencidas'];
        $prev = self::previa($g['id'], $hoy);
        $bajo = Catalogo::plan($pl)['inferior'] ?? null;
        $activos = Padron::activosEn($g['id']);
        $cuotaBajo = 0.0;
        $bajoDisp = false;
        if ($bajo) {
            $pb = Catalogo::plan($bajo);
            if ($pb['individual']) {
                $t = Padron::titular($g['id']);
                $cuotaBajo = Catalogo::precio($t['categoria_id'] === 'TIT64' ? 'TIT64' : 'TIT', $bajo);
                // P-09: a un plan individual solo se ofrece si el grupo tiene un único integrante activo.
                $bajoDisp = $cuotaBajo > 0 && count($activos) === 1;
            } else {
                $cuotaBajo = array_sum(array_map(fn($m) => Catalogo::precio($m['categoria_id'], $bajo), $activos));
                $bajoDisp = $cuotaBajo > 0 && !in_array(0.0, array_map(fn($m) => Catalogo::precio($m['categoria_id'], $bajo), $activos), true);
            }
        }
        $nb = $bajo ? Catalogo::plan($bajo)['nombre'] : '';
        $O = [
            'valor' => ['t' => 'Recordarle todo lo que ya tiene', 'd' => 'Contale cuánto ahorró, qué beneficios le quedan sin usar este año y cómo pedir un turno. Sin costo para Integral.', 'tipo' => 'charla'],
            'extra' => ['t' => 'Un beneficio de regalo para que lo use', 'd' => 'Una consulta médica o una sesión de hiperbárica sin cargo extra este mes, para que pruebe el plan.', 'tipo' => 'regalo'],
            'cambio' => ['t' => $bajo ? "Pasar al $nb" : 'Revisar el plan', 'd' => $bajo && $cuotaBajo ? 'Mantiene la cobertura pagando menos: ' . Fmt::money($cuotaBajo) . ' por mes en lugar de ' . Fmt::money($base) . '.' : 'Ver si un plan individual le conviene más.', 'tipo' => 'cambio', 'plan' => $bajo, 'disponible' => $bajoDisp],
            'd10' => ['t' => '10% de descuento en la cuota por 3 meses', 'd' => 'Paga ' . Fmt::money(Padron::conPromo($base, 10)) . ' durante 3 meses.', 'tipo' => 'promo', 'pct' => 10, 'meses' => 3],
            'd20' => ['t' => '20% de descuento en la cuota por 3 meses (última propuesta)', 'd' => 'Paga ' . Fmt::money(Padron::conPromo($base, 20)) . ' durante 3 meses. Solo si lleva 6 meses o más y está al día.', 'tipo' => 'promo', 'pct' => 20, 'meses' => 3, 'disponible' => $ant >= 180 && $alDia],
            'llamada' => ['t' => 'Llamado de un administrador', 'd' => 'Un responsable lo llama, escucha qué pasó y lo resuelve. Se le puede ofrecer después un descuento.', 'tipo' => 'charla'],
        ];
        $orden = [
            'economico' => ['valor', 'cambio', 'd10', 'd20'], 'nouso' => ['valor', 'extra', 'd10'], 'otra' => ['valor', 'extra', 'd10'],
            'mudanza' => ['cambio', 'valor'], 'experiencia' => ['llamada', 'extra', 'd10', 'd20'], 'otro' => ['valor', 'extra', 'd10', 'd20'],
            'fallecimiento' => [],
        ][$motivo] ?? ['valor', 'd10'];
        $L = [];
        foreach ($orden as $k) {
            $o = ['k' => $k] + $O[$k];
            if (($o['disponible'] ?? true) === false) {
                continue;
            }
            if ($prev && $o['tipo'] === 'promo') {
                continue;
            }
            $L[] = $o;
        }
        return ['L' => $L, 'prev' => $prev, 'ant' => $ant, 'alDia' => $alDia, 'bajo' => $bajo, 'cuotaBajo' => $cuotaBajo];
    }

    /** Datos del afiliado para la guía (datosRet). */
    public static function datos(array $a, ?string $hoy = null): array
    {
        $hoy ??= Fechas::hoy();
        $g = Padron::grupo($a['grupo_id']);
        $ms = Padron::miembros($g['id']);
        $ids = array_column($ms, 'id');
        $of = self::ofertas($a, 'economico', $hoy);
        $sb = Beneficios::saldo($a, $hoy);
        $filas = array_values(array_filter(Catalogo::beneficiosPlan(), fn($b) => isset($b['planes'][$g['plan_id']])));
        $aho = Beneficios::ahorro($ids, '2000-01-01', $hoy)['total'];
        $ahoY = Beneficios::ahorro($ids, substr($hoy, 0, 4) . '-01-01', $hoy)['total'];
        $sinUso = array_values(array_filter($sb, fn($x) => $x['r']['gratis'] && $x['quedan']));
        return [
            'g' => $g, 'pl' => $g['plan_id'], 'ant' => self::antTxt($a['fecha_alta'], $hoy),
            'cuotaN' => Padron::cuotaActual($g), 'cuota' => Fmt::money(Padron::cuotaActual($g)),
            'aho' => $aho ? Fmt::money($aho) : '', 'ahoY' => $ahoY ? Fmt::money($ahoY) : '', 'ahoN' => $aho,
            'usados' => array_values(array_filter($sb, fn($x) => $x['veces'])), 'sinUso' => $sinUso,
            'sinUsoTxt' => implode(', ', array_map(fn($x) => mb_strtolower($x['p']['nombre']) . " ({$x['quedan']} sin cargo)", $sinUso)),
            'nBen' => count($filas), 'ejemplos' => implode(', ', array_map(fn($r) => mb_strtolower($r['rubro']), array_slice($filas, 0, 4))),
            'bajo' => $of['bajo'] ? Catalogo::plan($of['bajo'])['nombre'] : '', 'cuotaBajo' => $of['cuotaBajo'] ? Fmt::money($of['cuotaBajo']) : '',
            'sepelio' => (bool)array_filter($filas, fn($b) => stripos($b['rubro'], 'sepelio') !== false),
            'previas' => App::$db->all('SELECT * FROM retenciones WHERE grupo_id = :g ORDER BY creado', ['g' => $g['id']]),
            'fam' => array_values(array_filter($ms, fn($m) => $m['activo'])),
        ];
    }

    /** Guion por motivo: frase de apertura, argumentos con datos y qué evitar. */
    public static function guion(string $motivo, array $d, string $n): array
    {
        $G = [
            'economico' => ['Te entiendo, {n}. Antes de que decidas, dejame mostrarte algo de tu cuenta.', [
                $d['aho'] ? "En {$d['ant']} con nosotros ahorraste {$d['aho']}" . ($d['ahoY'] && $d['ahoY'] !== $d['aho'] ? ", y solo este año {$d['ahoY']}" : '') . '.' : "Con tu cuota de {$d['cuota']} tenés {$d['nBen']} beneficios para vos y tu familia.",
                $d['bajo'] && $d['cuotaBajo'] ? 'Si lo que pesa es la cuota, hay forma de seguir cubierto pagando menos (eso lo ve administración).' : '',
                '¿Qué te complica más: el monto o la fecha de pago?'], 'No discutas el precio ni lo compares con otras prepagas.'],
            'nouso' => ['¡Qué bueno que estés bien de salud, {n}! Igual mirá todo lo que tenés para usar.', [
                $d['sinUsoTxt'] ? "Este año todavía tenés sin usar: {$d['sinUsoTxt']}." : '', "Con tu plan tenés {$d['nBen']} beneficios, por ejemplo: {$d['ejemplos']}.", '¿Querés que te saquemos ahora mismo un turno para un control?'], 'No lo hagas sentir culpable por no usarlo.'],
            'otra' => ['Perfecto, {n}, tener obra social está muy bien. Lo nuestro no la reemplaza: la complementa.', [
                "Con Integral tenés cosas que la obra social no te da directo: {$d['ejemplos']}.", $d['aho'] ? "De hecho, ya ahorraste {$d['aho']} con nosotros." : '', 'Muchos afiliados tienen las dos cosas.'], 'No hables mal de su obra social.'],
            'mudanza' => ['¡Éxitos con la mudanza, {n}! ¿A dónde te vas?', [
                'Si vas a seguir viniendo seguido (familia, controles, trámites), te conviene no perder la cobertura ni la antigüedad.', $d['sinUsoTxt'] ? "Antes de irte, aprovechá lo que te queda sin usar: {$d['sinUsoTxt']}." : ''], 'Si se va lejos, no insistas: despedilo con buena onda.'],
            'experiencia' => ['{n}, lamento mucho lo que pasó. Contame bien, quiero anotarlo para que se resuelva.', [
                'Anotá lo que dice, con detalle, en “Lo que dijo”.', 'Decile que un responsable lo va a llamar para solucionarlo.'], 'No justifiques ni le eches la culpa a nadie.'],
            'fallecimiento' => ['Lo acompañamos mucho en el sentimiento.', [
                $d['sepelio'] ? 'El plan tiene cobertura de sepelio: explicale a la familia cómo usarla.' : 'Explicale a la familia qué trámites hay que hacer.', 'No se ofrece nada: solo acompañar.'], 'No ofrezcas promociones ni descuentos.'],
            'otro' => ['Contame, {n}, ¿qué te pasó? Quiero entender bien.', ['Escuchá hasta el final y anotá lo que dice.', $d['aho'] ? "Recordale que ya ahorró {$d['aho']} con Integral." : ''], ''],
        ];
        [$abre, $dec, $evitar] = $G[$motivo] ?? $G['otro'];
        return ['abre' => str_replace('{n}', $n, $abre), 'dec' => array_values(array_filter($dec)), 'evitar' => $evitar];
    }

    /** Cada registro nuevo cierra los casos pendientes anteriores del mismo afiliado. */
    private static function cerrarPendientes(int $afiliadoId): void
    {
        App::$db->exec("UPDATE retenciones SET cerrado = :h WHERE afiliado_id = :a AND cerrado IS NULL AND resultado IN ('derivado','baja_pedida','piensa')", ['h' => Fechas::hoy(), 'a' => $afiliadoId]);
    }

    /**
     * Registra cómo terminó la atención (CU-10 y CU-12).
     * @param string $accion acepto | derivar | piensa | pedir_baja | baja
     * @param int $paso índice de la propuesta en curso (las anteriores no le interesaron)
     * @param string $origen guia (pantalla Quiere darse de baja) | ficha (modal de baja del administrador)
     */
    public static function registrar(int $afiliadoId, string $motivo, string $comentario, int $paso, string $accion, array $usuario, string $origen = 'guia'): array
    {
        $a = Padron::afiliado($afiliadoId) ?? throw new HttpError(404);
        if (!$a['activo']) {
            throw new Falta('Este afiliado ya está dado de baja.');
        }
        if (!isset(self::motivos()[$motivo])) {
            throw new Falta('Elegí el motivo.', ['motivo']);
        }
        $of = self::ofertas($a, $motivo);
        $L = $of['L'];
        $paso = max(0, min($paso, count($L)));
        $cur = $L[$paso] ?? null;
        $g = Padron::grupo($a['grupo_id']);
        $admin = $usuario['admin'];
        $hoy = Fechas::hoy();
        $ofertasReg = function (bool $acepto) use ($L, $paso) {
            return array_map(fn($x, $i) => ['k' => $x['k'], 't' => $x['t'], 'res' => $i < $paso ? 'no' : 'acepto'], array_slice($L, 0, $paso + ($acepto ? 1 : 0)), array_keys(array_slice($L, 0, $paso + ($acepto ? 1 : 0))));
        };
        $reg = function (string $resultado, ?array $oferta, array $extra = []) use ($a, $g, $motivo, $comentario, $usuario, $ofertasReg, $hoy) {
            self::cerrarPendientes($a['id']);
            App::$db->insert('retenciones', [
                'fecha' => $hoy, 'afiliado_id' => $a['id'], 'grupo_id' => $g['id'], 'motivo' => $motivo, 'comentario' => trim($comentario),
                'ofertas' => json_encode($ofertasReg($resultado === 'retenido'), JSON_UNESCAPED_UNICODE), 'resultado' => $resultado,
                'aceptada' => $oferta['t'] ?? null, 'usuario' => $usuario['nombre'],
            ] + $extra);
        };
        $motTxt = self::motivoTexto($motivo);
        return App::$db->tx(function () use ($accion, $cur, $admin, $reg, $a, $g, $motTxt, $L, $paso, $motivo, $origen, $comentario, $hoy) {
            switch ($accion) {
                case 'acepto':
                    if (!$cur) {
                        throw new Falta('No hay una propuesta para aceptar.');
                    }
                    if (!$admin && !in_array($cur['tipo'], self::TIPOS_USUARIO, true)) {
                        throw new HttpError(403, 'Esa propuesta la decide administración: pasala a administración.');
                    }
                    if ($cur['tipo'] === 'promo') {
                        $d = Fechas::perMas(substr($hoy, 0, 7), 1);
                        $d = max($d, \App\Core\Config::inicio()['periodo']);
                        App::$db->update('grupos', ['promo_pct' => $cur['pct'], 'promo_meses' => $cur['meses'], 'promo_desde' => $d, 'promo_hasta' => Fechas::perMas($d, $cur['meses'] - 1), 'promo_retencion' => true], 'id = :id', ['id' => $g['id']]);
                    }
                    if ($cur['tipo'] === 'cambio' && $cur['plan']) {
                        App::$db->exec('UPDATE grupos SET plan_id = :p WHERE id = :id', ['p' => $cur['plan'], 'id' => $g['id']]);
                        foreach (Padron::miembros($g['id']) as $m) {
                            $nv = Catalogo::precio($m['categoria_id'], $cur['plan']);
                            if ($nv > 0) {
                                App::$db->exec('UPDATE afiliados SET cuota = :c WHERE id = :id', ['c' => $nv, 'id' => $m['id']]);
                            }
                        }
                    }
                    $reg('retenido', $cur);
                    Historial::log(App::$db, 'Retención', "{$a['nombre']} (N° {$a['nro']}) se quedó · motivo: $motTxt · aceptó: {$cur['t']}");
                    return ['resultado' => 'retenido', 'mensaje' => "¡Retenido! {$cur['t']}."];
                case 'derivar':
                    if (!$cur) {
                        throw new Falta('No hay una propuesta para derivar.');
                    }
                    $reg('derivado', $cur);
                    Historial::log(App::$db, 'Retención', "{$a['nombre']} (N° {$a['nro']}) pasado a administración · motivo: $motTxt · pide: {$cur['t']}");
                    return ['resultado' => 'derivado', 'mensaje' => 'Quedó para administración. Lo van a llamar.'];
                case 'piensa':
                    $llamar = Fechas::sumarDias($hoy, 3);
                    $reg('piensa', null, ['llamar' => $llamar]);
                    Historial::log(App::$db, 'Retención', "{$a['nombre']} (N° {$a['nro']}) lo va a pensar · llamar el " . Fmt::fd($llamar));
                    return ['resultado' => 'piensa', 'mensaje' => 'Anotado: hay que llamarlo el ' . Fmt::fd($llamar) . '.'];
                case 'pedir_baja':
                    $reg('baja_pedida', null);
                    Historial::log(App::$db, 'Retención', "{$a['nombre']} (N° {$a['nro']}) insiste en darse de baja · motivo: $motTxt");
                    return ['resultado' => 'baja_pedida', 'mensaje' => 'Pedido de baja registrado: lo confirma un administrador.'];
                case 'baja':
                    if (!$admin) {
                        throw new HttpError(403, 'Solo un administrador da de baja.');
                    }
                    $todas = $paso >= count($L);
                    if ($motivo !== 'fallecimiento' && !$todas && ($origen === 'ficha' || $paso === 0)) {
                        throw new Falta('Ofrecé las propuestas en orden antes de dar de baja.');
                    }
                    $reg('baja', null);
                    Padron::darDeBaja($a['id'], $motivo, $comentario);
                    return ['resultado' => 'baja', 'mensaje' => 'Baja registrada.'];
            }
            throw new Falta('Acción desconocida.');
        });
    }

    /** CU-13: un administrador confirma una baja pedida. */
    public static function confirmarBaja(int $retId, array $usuario): void
    {
        if (!$usuario['admin']) {
            throw new HttpError(403, 'Solo un administrador da de baja.');
        }
        App::$db->tx(function () use ($retId, $usuario) {
            $r = App::$db->one("SELECT * FROM retenciones WHERE id = :id AND resultado = 'baja_pedida' AND cerrado IS NULL FOR UPDATE", ['id' => $retId]) ?? throw new Falta('Ese caso ya fue resuelto.');
            App::$db->exec('UPDATE retenciones SET cerrado = :h WHERE id = :id', ['h' => Fechas::hoy(), 'id' => $retId]);
            App::$db->insert('retenciones', [
                'fecha' => Fechas::hoy(), 'afiliado_id' => $r['afiliado_id'], 'grupo_id' => $r['grupo_id'], 'motivo' => $r['motivo'],
                'comentario' => $r['comentario'], 'ofertas' => $r['ofertas'], 'resultado' => 'baja', 'usuario' => $usuario['nombre'], 'cerrado' => Fechas::hoy(),
            ]);
            $a = Padron::afiliado((int)$r['afiliado_id']);
            if ($a['activo']) {
                Padron::darDeBaja($a['id'], $r['motivo'], $r['comentario']);
            }
        });
    }

    /** CU-13: resumen y casos para resolver. */
    public static function panel(): array
    {
        $R = App::$db->all('SELECT r.*, a.nombre, a.nro FROM retenciones r JOIN afiliados a ON a.id = r.afiliado_id ORDER BY r.creado');
        // Un caso = un pedido de baja: se cuentan los registros que no cierran un caso derivado anterior.
        $pend = array_values(array_filter($R, fn($r) => !$r['cerrado'] && in_array($r['resultado'], ['derivado', 'baja_pedida', 'piensa'], true)));
        $ret = array_values(array_filter($R, fn($r) => $r['resultado'] === 'retenido'));
        $bajas = array_values(array_filter($R, fn($r) => $r['resultado'] === 'baja'));
        $por = [];
        $con = [];
        foreach ($R as $r) {
            $por[$r['motivo']] = ($por[$r['motivo']] ?? 0) + 1;
        }
        foreach ($ret as $r) {
            $con[$r['aceptada']] = ($con[$r['aceptada']] ?? 0) + 1;
        }
        arsort($por);
        arsort($con);
        return ['total' => count($R), 'retenidos' => count($ret), 'bajas' => count($bajas), 'pend' => $pend, 'por' => $por, 'con' => $con];
    }

    /** "Para llamar hoy": los que lo iban a pensar y ya llegó la fecha. */
    public static function paraLlamar(?string $hoy = null): array
    {
        return App::$db->all("SELECT r.*, a.nombre, a.nro, a.telefono, a.id AS afiliado_id FROM retenciones r JOIN afiliados a ON a.id = r.afiliado_id WHERE r.resultado = 'piensa' AND r.cerrado IS NULL AND r.llamar <= :h ORDER BY r.llamar", ['h' => $hoy ?? Fechas::hoy()]);
    }
}
