<?php
declare(strict_types=1);

namespace App\Http;

use App\Core\App;
use App\Core\Falta;
use App\Core\Historial;
use App\Core\HttpError;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Domain\Beneficios;
use App\Domain\Catalogo;
use App\Domain\Documentos;
use App\Domain\Fechas;
use App\Domain\Fmt;
use App\Domain\Padron;
use App\Domain\Permisos;
use App\Domain\Retencion;
use App\Service\Pdf;

/** CU-05 a CU-11, CU-14 y CU-25. */
final class Afiliados
{
    public function index(Request $r): Response
    {
        $q = $r->str('q');
        $estado = $r->get('estado', 'activo');
        $estado = in_array($estado, ['activo', 'baja', ''], true) ? $estado : 'activo';
        return Response::html(View::render('afiliados/index', [
            'vista' => 'afiliados', 'q' => $q, 'estado' => $estado,
            'filas' => Padron::padron($q, $estado), 'cuentas' => Padron::estadosCuenta(),
            'porPlan' => Padron::activosPorPlan(), 'abrir' => $r->int('abrir'),
        ]));
    }

    public function tabla(Request $r): Response
    {
        $estado = $r->get('estado', 'activo');
        return Response::html(View::parcial('afiliados/_filas', ['filas' => Padron::padron($r->str('q'), in_array($estado, ['activo', 'baja', ''], true) ? $estado : 'activo'), 'cuentas' => Padron::estadosCuenta()]));
    }

    /** CU-05: el padrón por la API (solo administradores). */
    public function api(Request $r): Response
    {
        $fs = Padron::padron($r->str('q'), (string)$r->get('estado', 'activo'));
        return Response::json(['ok' => true, 'afiliados' => array_map(fn($a) => [
            'nro' => $a['nro'], 'nombre' => $a['nombre'], 'doc' => $a['doc'], 'categoria' => $a['categoria_id'],
            'plan' => $a['plan_id'], 'cuota' => $a['cuota'], 'alta' => $a['fecha_alta'], 'estado' => $a['estado'],
        ], $fs)]);
    }

    private function afiliado(Request $r): array
    {
        return Padron::afiliado((int)$r->params['id']) ?? throw new HttpError(404, 'No existe ese afiliado.');
    }

    /** CU-07 / CU-08: ficha en el panel lateral. */
    public function ficha(Request $r): Response
    {
        $a = $this->afiliado($r);
        $g = Padron::grupo($a['grupo_id']);
        return Response::html(View::parcial('afiliados/ficha', [
            'a' => $a, 'g' => $g, 'tit' => Padron::titular($g['id']), 'miembros' => Padron::miembros($g['id']),
            'cuenta' => Padron::cuenta($g['id']), 'cuotaGrupo' => Padron::cuotaActual($g), 'editando' => $r->bool('editar'),
            'consumos' => App::$db->all('SELECT * FROM consumos_comercio WHERE afiliado_id = :a ORDER BY fecha DESC, hora DESC LIMIT 15', ['a' => $a['id']]),
            'saldo' => Beneficios::saldo($a), 'hoy' => Fechas::hoy(),
        ]));
    }

    public function editar(Request $r): Response
    {
        $a = $this->afiliado($r);
        $res = Padron::editar($a['id'], $r->post);
        return Response::json(['ok' => true, 'mensaje' => $res['cambios'] ? 'Datos actualizados.' : 'No hubo cambios.', 'panel' => "/afiliados/{$a['id']}/ficha", 'refrescar' => true]);
    }

    /** CU-09 */
    public function promoForm(Request $r): Response
    {
        $g = Padron::grupo((int)$r->params['id']) ?? throw new HttpError(404);
        return Response::html(View::parcial('afiliados/promo_modal', [
            'g' => $g, 'tit' => Padron::titular($g['id']), 'base' => Padron::cuotaBase($g['id']),
            'desde' => Padron::inicioPromoPropuesto($g), 'hoy' => Fechas::hoy(), 'afiliado' => $r->int('a'),
        ]));
    }

    public function promo(Request $r): Response
    {
        $gid = (int)$r->params['id'];
        if ($r->str('accion') === 'quitar') {
            Padron::quitarPromo($gid);
            $msg = 'Promoción quitada.';
        } else {
            Padron::aplicarPromo($gid, $r->int('pct'), $r->int('meses'), $r->str('desde'));
            $msg = 'Promoción aplicada.';
        }
        $a = $r->int('a') ?: Padron::titular($gid)['id'];
        return Response::json(['ok' => true, 'mensaje' => $msg, 'cerrar' => true, 'panel' => "/afiliados/$a/ficha"]);
    }

    /** CU-10: modal "Antes de dar de baja a …". */
    public function bajaForm(Request $r): Response
    {
        $a = $this->afiliado($r);
        if (!$a['activo']) {
            throw new Falta('Este afiliado ya está dado de baja.');
        }
        $g = Padron::grupo($a['grupo_id']);
        $ofertas = [];
        foreach (array_keys(Retencion::motivos()) as $m) {
            $ofertas[$m] = Retencion::ofertas($a, $m)['L'];
        }
        $aho = Beneficios::ahorro([$a['id']], '2000-01-01', Fechas::hoy())['total'];
        $sinUso = array_filter(Beneficios::saldo($a), fn($x) => $x['r']['gratis'] && $x['quedan']);
        return Response::html(View::parcial('afiliados/baja_modal', [
            'a' => $a, 'g' => $g, 'ofertas' => $ofertas, 'prev' => Retencion::previa($g['id']), 'aho' => $aho, 'sinUso' => $sinUso,
        ]));
    }

    public function reactivar(Request $r): Response
    {
        $a = $this->afiliado($r);
        Padron::reactivar($a['id']);
        return Response::json(['ok' => true, 'mensaje' => 'Afiliado reactivado.', 'panel' => "/afiliados/{$a['id']}/ficha"]);
    }

    /** CU-25: modal "Historial de beneficios". */
    public function historial(Request $r): Response
    {
        $a = $this->afiliado($r);
        $g = Padron::grupo($a['grupo_id']);
        $hoy = Fechas::hoy();
        return Response::html(View::parcial('afiliados/historial_modal', [
            'a' => $a, 'g' => $g, 'saldo' => Beneficios::saldo($a), 'hoy' => $hoy,
            'ahorroY' => Beneficios::ahorro([$a['id']], substr($hoy, 0, 4) . '-01-01', $hoy)['total'],
            'usos' => App::$db->all('SELECT l.*, un.nombre AS unidad FROM v_usos_lineas l JOIN unidades un ON un.id = l.unidad_id WHERE l.afiliado_id = :a ORDER BY l.fecha DESC, l.hora DESC', ['a' => $a['id']]),
        ]));
    }

    /** Comprobante de un uso (botón "Ver" del historial). */
    public function uso(Request $r): Response
    {
        $u = App::$db->one('SELECT u.*, un.nombre_largo AS unidad, a.nombre, a.nro FROM usos u JOIN unidades un ON un.id = u.unidad_id JOIN afiliados a ON a.id = u.afiliado_id WHERE u.id = :id', ['id' => (int)$r->params['id']]) ?? throw new HttpError(404);
        if (App::$usuario['rol'] === 'unidad' && (int)App::$usuario['unidad_id'] !== (int)$u['unidad_id']) {
            throw new HttpError(403);
        }
        $lineas = App::$db->all('SELECT * FROM v_usos_lineas WHERE uso_id = :id ORDER BY id', ['id' => $u['id']]);
        return Response::html(View::parcial('afiliados/uso_modal', ['u' => $u, 'lineas' => $lineas]));
    }

    /** PDF "Mis beneficios" del afiliado (RT-04, CU-12, CU-17, CU-20). */
    public function pdfAfiliado(Request $r): Response
    {
        $a = $this->afiliado($r);
        $info = Documentos::infoAfi($a);
        return Pdf::respuesta(Pdf::generar('info', ['d' => $info]), 'Mis beneficios - ' . $info['titulo']);
    }

    /** Mensaje y teléfono para mandar el PDF del afiliado por WhatsApp. */
    public function waAfiliado(Request $r): Response
    {
        $a = $this->afiliado($r);
        $tel = Fmt::telWa($a['telefono'] ?: Padron::telefonoGrupo($a['grupo_id']));
        return Response::json(['ok' => true, 'whatsapp' => ['tel' => Fmt::telValido($tel) ? $tel : '', 'texto' => Documentos::mensajeCorto('afiliado', Padron::nomPila($a)), 'pdf' => "/pdf/afiliado/{$a['id']}"]]);
    }

    // ------------------------------------------------------------------ API de búsqueda

    /** RT-01: sugerencias por nombre (no para comercios). */
    public function sugerir(Request $r): Response
    {
        return Response::json(['ok' => true, 'items' => Padron::sugerir($r->str('q'))]);
    }

    /** Valores del padrón para autocompletar apellidos y nombres en el alta. */
    public function valores(Request $r): Response
    {
        $campo = $r->params['campo'] ?? '';
        $q = Fmt::norm($r->str('q'));
        if ($q === '') {
            return Response::json(['ok' => true, 'items' => []]);
        }
        $sql = $campo === 'apellido'
            ? 'SELECT DISTINCT apellido AS v FROM afiliados WHERE apellido <> \'\''
            : 'SELECT DISTINCT v FROM (SELECT nombre1 AS v FROM afiliados UNION SELECT nombre2 FROM afiliados) x WHERE v <> \'\'';
        $items = [];
        foreach (App::$db->col($sql) as $v) {
            $n = Fmt::norm($v);
            $pt = Padron::puntaje($n, $q, array_values(array_filter(explode(' ', $q))));
            if ($pt >= 0) {
                $items[] = ['pt' => $pt, 'label' => $v];
            }
        }
        usort($items, fn($a, $b) => [$a['pt'], $a['label']] <=> [$b['pt'], $b['label']]);
        return Response::json(['ok' => true, 'items' => array_map(fn($x) => ['label' => $x['label']], array_slice($items, 0, 8))]);
    }

    /** CU-06 E1/E2: avisa si el documento ya está activo en el padrón. */
    public function docActivo(Request $r): Response
    {
        $a = Padron::activoConDoc($r->str('doc'));
        return Response::json(['ok' => true, 'ya' => $a ? ['nombre' => $a['nombre'], 'nro' => $a['nro']] : null]);
    }

    /** Busca un afiliado puntual (RT-01) para las pantallas de usuarios (sin ver el padrón). */
    public function buscar(Request $r): Response
    {
        $a = Padron::buscarUno($r->str('q'));
        return Response::json(['ok' => true, 'a' => $a ? ['id' => $a['id'], 'nombre' => $a['nombre'], 'nro' => $a['nro'], 'activo' => $a['activo']] : null]);
    }

    // ------------------------------------------------------------------ CU-14 cumpleaños

    public function saludar(Request $r): Response
    {
        $a = $this->afiliado($r);
        $y = (int)substr(Fechas::hoy(), 0, 4);
        App::$db->exec('INSERT INTO saludos_cumple (afiliado_id, anio, usuario) VALUES (:a, :y, :u) ON CONFLICT DO NOTHING', ['a' => $a['id'], 'y' => $y, 'u' => App::$usuario['nombre']]);
        Historial::log(App::$db, 'Cumpleaños', "Saludo a {$a['nombre']} (N° {$a['nro']})");
        $tel = Fmt::telWa($a['telefono'] ?: Padron::telefonoGrupo($a['grupo_id']));
        return Response::json(['ok' => true, 'whatsapp' => ['tel' => Fmt::telValido($tel) ? $tel : '', 'texto' => Documentos::textoCumple($a), 'pdf' => "/pdf/cumple/{$a['id']}"]]);
    }

    public function pdfCumple(Request $r): Response
    {
        $a = $this->afiliado($r);
        return Pdf::respuesta(Pdf::generar('info', ['d' => Documentos::infoCumple($a)]), 'Feliz cumpleaños - ' . Padron::nomPila($a));
    }

    /** Pantalla para usuarios sin secciones. */
    public function sinAcceso(Request $r): Response
    {
        return Response::html(View::render('sinacceso', ['vista' => 'sinacceso']));
    }

    public static function puedeVerBeneficiosDe(): bool
    {
        $u = App::$usuario;
        return $u['admin'] || Permisos::puedeVer($u, 'autorizaciones') || Permisos::puedeVer($u, 'beneficios') || Permisos::puedeVer($u, 'retencion');
    }
}
