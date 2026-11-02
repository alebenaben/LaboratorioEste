<?php
declare(strict_types=1);

namespace App\Http;

use App\Core\App;
use App\Core\Falta;
use App\Core\HttpError;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Domain\Beneficios;
use App\Domain\Catalogo;
use App\Domain\Fechas;
use App\Domain\Fmt;
use App\Domain\Padron;
use App\Domain\Promociones;
use App\Domain\Usos;

/** CU-20 a CU-24: autorizaciones, auditoría, verificación en unidades y comercios, usos. */
final class BeneficiosCtl
{
    // ------------------------------------------------------------------ CU-20

    public function autorizaciones(Request $r): Response
    {
        $u = App::$usuario;
        $hoy = Fechas::hoy();
        // El administrador ve todas; el usuario, las suyas de hoy y todas las que mandó a auditoría.
        $hist = $u['admin']
            ? App::$db->all('SELECT x.*, a.nombre, p.nombre AS prest FROM autorizaciones x JOIN afiliados a ON a.id = x.afiliado_id JOIN prestaciones p ON p.cod = x.prestacion_cod ORDER BY x.id DESC LIMIT 200')
            : App::$db->all("SELECT x.*, a.nombre, p.nombre AS prest FROM autorizaciones x JOIN afiliados a ON a.id = x.afiliado_id JOIN prestaciones p ON p.cod = x.prestacion_cod
                             WHERE x.usuario_id = :u AND (x.fecha = :h OR x.estado IN ('pendiente','aprobada','desaprobada')) ORDER BY x.id DESC", ['u' => $u['id'], 'h' => $hoy]);
        $lista = $u['admin'] ? App::$db->all("SELECT id, nro, nombre, estado FROM afiliados ORDER BY nombre") : [];
        return Response::html(View::render('beneficios/autorizaciones', ['vista' => 'autorizaciones', 'hist' => $hist, 'lista' => $lista, 'prest' => Catalogo::prestaciones()]));
    }

    private function afiliadoDe(Request $r): ?array
    {
        if ($r->int('afiliado_id') && App::$usuario['admin']) {
            return Padron::afiliado($r->int('afiliado_id'));
        }
        return Padron::buscarUno($r->str('afiliado'));
    }

    /** Panel de verificación (HTML parcial). */
    public function verificar(Request $r): Response
    {
        $a = $this->afiliadoDe($r);
        $p = Catalogo::prestacion($r->str('prest'));
        if (!$a || !$p) {
            $q = $r->str('afiliado');
            $msg = $q !== '' && !$a ? (preg_match('/\pL/u', $q) && !preg_match('/\d/', $q) ? 'Elegí de la lista que aparece.' : 'No se encontró ningún afiliado con ese dato.') : 'Elegí un afiliado para ver si corresponde autorizar.';
            return Response::html('<h2>Verificación</h2><p class="sub">' . e($msg) . '</p>');
        }
        $v = Beneficios::verificar($a, $p);
        return Response::html(View::parcial('beneficios/_verificacion', ['v' => $v, 'saldo' => Beneficios::saldo($a), 'admin' => App::$usuario['admin'], 'hoy' => Fechas::hoy()]));
    }

    public function autorizar(Request $r): Response
    {
        $a = $this->afiliadoDe($r) ?? throw new Falta('No se encontró ningún afiliado con ese dato.');
        $emitir = $r->str('accion') === 'autorizar';
        $res = Usos::autorizar($a, $r->str('prest'), $emitir, App::$usuario);
        $msg = $emitir ? "Autorización N° {$res['nro']} emitida" . ($res['consumo'] ? " · se descontó del beneficio ({$res['consumo']})" : '') . '.' : "Rechazo N° {$res['nro']} registrado.";
        App::$sesion->flash($msg);
        return Response::json(['ok' => true, 'redirect' => '/autorizaciones']);
    }

    public function enviarAuditoria(Request $r): Response
    {
        $a = $this->afiliadoDe($r) ?? throw new Falta('No se encontró ningún afiliado con ese dato.');
        $nro = Usos::enviarAuditoria($a, $r->str('prest'), $r->str('obs'), App::$usuario);
        App::$sesion->flash("Solicitud N° $nro enviada a auditoría.");
        return Response::json(['ok' => true, 'redirect' => '/autorizaciones']);
    }

    // ------------------------------------------------------------------ CU-21

    public function auditoria(Request $r): Response
    {
        $sql = 'SELECT x.*, a.nombre, a.nro AS anro, a.fecha_alta, g.plan_id, p.nombre AS prest, un.nombre_largo AS unidad
                FROM autorizaciones x JOIN afiliados a ON a.id = x.afiliado_id JOIN grupos g ON g.id = a.grupo_id
                JOIN prestaciones p ON p.cod = x.prestacion_cod JOIN unidades un ON un.id = p.unidad_id';
        return Response::html(View::render('beneficios/auditoria', [
            'vista' => 'auditoria',
            'pend' => App::$db->all("$sql WHERE x.estado = 'pendiente' ORDER BY x.id"),
            'hechas' => App::$db->all("$sql WHERE x.estado IN ('aprobada','desaprobada') ORDER BY x.resuelta_en DESC LIMIT 20"),
        ]));
    }

    public function decidirForm(Request $r): Response
    {
        $x = App::$db->one('SELECT x.*, a.nombre, p.nombre AS prest FROM autorizaciones x JOIN afiliados a ON a.id = x.afiliado_id JOIN prestaciones p ON p.cod = x.prestacion_cod WHERE x.id = :id', ['id' => (int)$r->params['id']]) ?? throw new HttpError(404);
        return Response::html(View::parcial('beneficios/decidir_modal', ['x' => $x, 'ok' => $r->str('ok') === '1']));
    }

    public function decidir(Request $r): Response
    {
        $res = Usos::auditar((int)$r->params['id'], $r->str('ok') === '1', $r->str('texto'), App::$usuario);
        App::$sesion->flash("Solicitud N° {$res['nro']} " . ($r->str('ok') === '1' ? 'aprobada' : 'rechazada') . '.');
        return Response::json(['ok' => true, 'redirect' => '/auditoria']);
    }

    // ------------------------------------------------------------------ CU-22 / CU-23 Verificar afiliado

    public function verificarPantalla(Request $r): Response
    {
        $u = App::$usuario;
        $hoy = Fechas::hoy();
        if ($u['rol'] === 'unidad') {
            $usos = App::$db->all("SELECT l.*, a.nro, a.nombre1, a.apellido FROM v_usos_lineas l JOIN afiliados a ON a.id = l.afiliado_id WHERE l.unidad_id = :u AND l.fecha = :h ORDER BY l.hora DESC", ['u' => $u['unidad_id'], 'h' => $hoy]);
            return Response::html(View::render('beneficios/verificar_unidad', ['vista' => 'verificar', 'usos' => $usos, 'unidad' => Catalogo::unidadPorId((int)$u['unidad_id'])]));
        }
        $usos = App::$db->all('SELECT c.*, a.nro, a.nombre1, a.apellido FROM consumos_comercio c JOIN afiliados a ON a.id = c.afiliado_id WHERE c.usuario_id = :u AND c.fecha = :h ORDER BY c.hora DESC', ['u' => $u['id'], 'h' => $hoy]);
        $benef = null;
        foreach (Catalogo::beneficiosPlan() as $b) {
            if ($b['rubro'] === $u['comercio_rubro']) {
                $benef = $b;
            }
        }
        return Response::html(View::render('beneficios/verificar_comercio', ['vista' => 'verificar', 'usos' => $usos, 'benef' => $benef, 'rubro' => Catalogo::rubros()[$u['comercio_rubro']] ?? null]));
    }

    /** Busca al afiliado como la unidad: por nombre (con sugerencias), DNI, CUIT o N°. */
    private function buscarUnidad(string $t): array
    {
        $t = trim($t);
        if ($t === '') {
            return ['a' => null];
        }
        if (preg_match('/N°\s*\d+\s*$/u', $t)) {
            return ['a' => Padron::buscarUno($t)];
        }
        if (preg_match('/\pL/u', $t)) {
            $toks = array_values(array_filter(explode(' ', Fmt::norm($t))));
            $c = [];
            $p = [];
            foreach ($toks as $i => $k) {
                $c[] = "(' ' || nombre_norm) LIKE :t$i";
                $p["t$i"] = '% ' . $k . '%';
            }
            $ms = App::$db->col('SELECT id FROM afiliados WHERE ' . implode(' AND ', $c), $p);
            return count($ms) === 1 ? ['a' => Padron::afiliado((int)$ms[0])] : ['a' => null, 'varios' => count($ms)];
        }
        return ['a' => Padron::buscarUno(preg_replace('/\D/', '', $t))];
    }

    /** CU-22: resultado de la verificación en la unidad (HTML parcial). */
    public function verificarUnidad(Request $r): Response
    {
        $u = App::$usuario;
        $t = $r->str('q');
        $b = $this->buscarUnidad($t);
        $a = $b['a'];
        $hoy = Fechas::hoy();
        $res = ['ok' => false, 'a' => $a];
        if (!$t) {
            return Response::html('');
        }
        if (!$a) {
            $res['txt'] = !empty($b['varios']) ? "Hay {$b['varios']} afiliados con ese nombre: elegí uno de la lista o escribí también el nombre." : (preg_match('/\pL/u', $t) ? 'No encontré ningún afiliado con ese nombre.' : 'No es afiliado de Integral del Este.');
        } elseif (!$a['activo']) {
            $res['txt'] = 'Está dado de baja: no tiene beneficios. Se le cobra como particular.';
        } elseif (Padron::cuenta($a['grupo_id'], $hoy)['vencidas']) {
            $res['txt'] = 'Su afiliación no está al día: hoy no tiene beneficios. Puede regularizar en Integral del Este.';
        } else {
            $g = Padron::grupo($a['grupo_id']);
            $un = Catalogo::unidadPorId((int)$u['unidad_id']);
            $rub = ['Biobárica' => ['Medicina hiperbárica'], 'Óptica' => ['Óptica'], 'Laboratorio' => ['Laboratorio'], 'CMDE' => ['Consultas médicas', 'Consulta médica virtual', 'Enfermería']][$un['nombre']] ?? [];
            $benef = [];
            foreach (Catalogo::beneficiosPlan() as $bp) {
                if (in_array($bp['rubro'], $rub, true) && isset($bp['planes'][$g['plan_id']])) {
                    $benef[] = ['r' => $bp['rubro'], 't' => $bp['planes'][$g['plan_id']][0], 'c' => $bp['planes'][$g['plan_id']][1]];
                }
            }
            if (!$un['original'] && $un['texto_beneficio']) {
                $benef[] = ['r' => $un['nombre'], 't' => $un['texto_beneficio'], 'c' => $un['carencia_meses'] ? $un['carencia_meses'] . ' meses' : 'Sin carencia'];
            }
            $res = ['ok' => true, 'a' => $a, 'plan' => Catalogo::plan($g['plan_id']), 'items' => Beneficios::hoyEnUnidad($a, (int)$u['unidad_id'], $hoy),
                'benef' => $benef, 'promos' => Promociones::vigentesDeUnidad($un['nombre'], $hoy),
                'ahoY' => Beneficios::ahorro([$a['id']], substr($hoy, 0, 4) . '-01-01', $hoy)['total']];
        }
        return Response::html(View::parcial('beneficios/_res_unidad', ['r' => $res]));
    }

    public function registrarUso(Request $r): Response
    {
        $u = App::$usuario;
        $b = $this->buscarUnidad($r->str('q'));
        $a = $b['a'] ?? throw new Falta('Verificá al afiliado primero.');
        if (Padron::cuenta($a['grupo_id'])['vencidas']) {
            throw new Falta('Su afiliación no está al día: hoy no tiene beneficios.');
        }
        $res = Usos::registrarEnUnidad($a, (int)$u['unidad_id'], $r->str('cod'), max(1, $r->int('cant', 1)), Fmt::parseMonto($r->str('precio')), Catalogo::unidadPorId((int)$u['unidad_id'])['nombre_largo']);
        App::$sesion->flash($res['mensaje']);
        return Response::json(['ok' => true, 'redirect' => '/verificar']);
    }

    /** CU-23: resultado en el comercio (HTML parcial). */
    public function verificarComercio(Request $r): Response
    {
        $v = Usos::verificarComercio($r->str('q'), App::$usuario);
        return Response::html($v ? View::parcial('beneficios/_res_comercio', ['r' => $v, 'q' => $r->str('q')]) : '');
    }

    public function registrarConsumo(Request $r): Response
    {
        $res = Usos::registrarConsumo($r->str('q'), App::$usuario, $r->str('detalle'), Fmt::parseMonto($r->str('importe')), (float)str_replace(',', '.', $r->str('pct', '0')));
        App::$sesion->flash('Consumo registrado: cobrar ' . Fmt::money($res['total']) . '.');
        return Response::json(['ok' => true, 'redirect' => '/verificar']);
    }

    // ------------------------------------------------------------------ CU-24 Usos en unidades

    public function usos(Request $r): Response
    {
        $un = $r->str('u');
        $m = preg_match('/^\d{4}-\d{2}$/', $r->str('m')) ? $r->str('m') : substr(Fechas::hoy(), 0, 7);
        $p = ['d' => "$m-01", 'h' => Fechas::finPeriodo($m)];
        $w = 'l.fecha BETWEEN :d AND :h';
        if ($un !== '') {
            $w .= ' AND un.nombre = :u';
            $p['u'] = $un;
        }
        $ls = App::$db->all("SELECT l.*, un.nombre AS unidad, a.nombre AS afiliado, a.nro FROM v_usos_lineas l JOIN unidades un ON un.id = l.unidad_id JOIN afiliados a ON a.id = l.afiliado_id WHERE $w ORDER BY l.fecha DESC, l.hora DESC", $p);
        return Response::html(View::render('beneficios/usos', ['vista' => 'prestaciones', 'ls' => $ls, 'u' => $un, 'm' => $m]));
    }
}
