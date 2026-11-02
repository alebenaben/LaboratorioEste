<?php
declare(strict_types=1);

namespace App\Http;

use App\Core\App;
use App\Core\Falta;
use App\Core\HttpError;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Domain\Documentos;
use App\Domain\Fechas;
use App\Domain\Fmt;
use App\Domain\Gestion;
use App\Domain\Padron;
use App\Domain\Retencion;
use App\Service\Informes;
use App\Service\Pdf;

/** CU-33 a CU-38. */
final class GestionCtl
{
    // ------------------------------------------------------------------ CU-33

    public function egresos(Request $r): Response
    {
        $m = preg_match('/^\d{4}-\d{2}$/', $r->str('m')) ? $r->str('m') : substr(Fechas::hoy(), 0, 7);
        $pre = $r->int('fijo') ? App::$db->one('SELECT * FROM egresos WHERE id = :id', ['id' => $r->int('fijo')]) : null;
        return Response::html(View::render('gestion/egresos', ['vista' => 'egresos', 'm' => $m, 'res' => Gestion::resumenEgresos($m), 'cats' => Gestion::categoriasEgreso(), 'pre' => $pre, 'hoy' => Fechas::hoy()]));
    }

    public function guardarEgreso(Request $r): Response
    {
        Gestion::guardarEgreso($r->post, App::$usuario['nombre']);
        App::$sesion->flash('Egreso de ' . Fmt::money(Fmt::parseMonto($r->str('importe'))) . ' guardado.');
        return Response::json(['ok' => true, 'redirect' => '/egresos?m=' . substr($r->str('fecha') ?: Fechas::hoy(), 0, 7)]);
    }

    public function borrarEgreso(Request $r): Response
    {
        Gestion::borrarEgreso((int)$r->params['id']);
        return Response::json(['ok' => true, 'mensaje' => 'Egreso borrado.', 'recargar' => true]);
    }

    public function proveedores(Request $r): Response
    {
        $q = Fmt::norm($r->str('q'));
        $items = [];
        $cats = Gestion::categoriasEgreso();
        foreach (App::$db->all('SELECT DISTINCT ON (lower(proveedor)) proveedor, categoria, cuit, comprobante FROM egresos ORDER BY lower(proveedor), id DESC') as $e) {
            if ($q === '' || str_contains(Fmt::norm($e['proveedor']), $q)) {
                $items[] = ['label' => $e['proveedor'], 'sub' => $cats[$e['categoria']]['nombre'] ?? '', 'categoria' => $e['categoria'], 'cuit' => $e['cuit'], 'comprobante' => $e['comprobante']];
            }
        }
        return Response::json(['ok' => true, 'items' => array_slice($items, 0, 8)]);
    }

    // ------------------------------------------------------------------ CU-34

    public function contabilidad(Request $r): Response
    {
        return Response::html(View::render('gestion/contabilidad', ['vista' => 'contabilidad', 'cuentas' => Gestion::cuentas(), 'saldos' => Gestion::saldos(), 'asientos' => array_reverse(Gestion::asientos(null, null, 12)), 'hoy' => Fechas::hoy()]));
    }

    // ------------------------------------------------------------------ CU-35, CU-36, CU-37

    public function estadisticas(Request $r): Response
    {
        $hoy = Fechas::hoy();
        $resM = preg_match('/^\d{4}-\d{2}$/', $r->str('resm')) && $r->str('resm') <= substr($hoy, 0, 7) ? $r->str('resm') : Fechas::mesAnterior($hoy);
        $k = in_array($r->str('aho'), ['mes', 'trim', 'anio'], true) ? $r->str('aho') : 'anio';
        [$d, $h] = Documentos::rangoAhorro($k);
        return Response::html(View::render('gestion/estadisticas', [
            'vista' => 'estadisticas', 'hoy' => $hoy, 'resM' => $resM, 'resumen' => Gestion::gruposConUso($resM),
            'ret' => Retencion::panel(), 'aho' => Gestion::ahorro($k), 'ind' => Gestion::indicadores(),
            'cons' => Gestion::consultas(), 'com' => Gestion::consumoComercios(), 'ben' => Gestion::consumoBeneficios($d, $h), 'k' => $k,
        ]));
    }

    public function resumenMail(Request $r): Response
    {
        $n = Gestion::enviarResumenes($r->str('per'));
        if (!$n) {
            throw new Falta('No hay afiliados con email que falten recibir el resumen.');
        }
        App::$sesion->flash("Se están enviando $n mails con el resumen.");
        return Response::json(['ok' => true, 'recargar' => true]);
    }

    public function resumenWa(Request $r): Response
    {
        $g = Padron::grupo($r->int('g')) ?? throw new HttpError(404);
        $per = $r->str('per');
        Gestion::marcarResumen($g['id'], $per, 'WhatsApp');
        $t = Padron::titular($g['id']);
        $tel = Fmt::telWa(Padron::telefonoGrupo($g['id']));
        return Response::json(['ok' => true, 'recargar' => true, 'whatsapp' => ['tel' => Fmt::telValido($tel) ? $tel : '', 'texto' => Documentos::mensajeCorto('resumen', Padron::nomPila($t), ['periodo' => Fmt::perLabel($per)]), 'pdf' => "/pdf/resumen/{$g['id']}/$per"]]);
    }

    public function pdfResumen(Request $r): Response
    {
        $g = Padron::grupo((int)$r->params['g']) ?? throw new HttpError(404);
        $per = (string)$r->params['per'];
        return Pdf::respuesta(Pdf::generar('info', ['d' => Documentos::infoResumen($g, $per)]), 'Tus beneficios de ' . Fmt::perLabel($per));
    }

    /** CU-37: "Tu ahorro" por WhatsApp. */
    public function ahorroWa(Request $r): Response
    {
        $g = Padron::grupo($r->int('g')) ?? throw new HttpError(404);
        $k = in_array($r->str('k'), ['mes', 'trim', 'anio'], true) ? $r->str('k') : 'anio';
        $info = Documentos::infoAhorro($g, $k);
        $t = Padron::titular($g['id']);
        $tel = Fmt::telWa(Padron::telefonoGrupo($g['id']));
        return Response::json(['ok' => true, 'whatsapp' => ['tel' => Fmt::telValido($tel) ? $tel : '', 'texto' => Documentos::mensajeCorto('ahorro', Padron::nomPila($t), ['periodo' => Documentos::PER_AHO[$k], 'mas' => $info['_pagado'] && $info['_total'] > $info['_pagado']]), 'pdf' => "/pdf/ahorro/{$g['id']}/$k"]]);
    }

    public function pdfAhorro(Request $r): Response
    {
        $g = Padron::grupo((int)$r->params['g']) ?? throw new HttpError(404);
        $k = in_array($r->params['k'], ['mes', 'trim', 'anio'], true) ? $r->params['k'] : 'anio';
        return Pdf::respuesta(Pdf::generar('info', ['d' => Documentos::infoAhorro($g, $k)]), 'Tu ahorro - ' . Padron::titular($g['id'])['nombre']);
    }

    // ------------------------------------------------------------------ CU-38

    private function informeDatos(Request $r): array
    {
        $tipo = $r->str('tipo', 'caja');
        [$d, $h, $et] = Gestion::rango($r->str('p', 'mes'), $r->str('mes'), $r->str('d'), $r->str('h'));
        if ($tipo === 'padron') {
            $et = 'Activos al ' . Fmt::fd(Fechas::hoy());
        }
        return [$tipo, Gestion::nombreInforme($tipo), $et, Gestion::informe($tipo, $d, $h)];
    }

    public function informes(Request $r): Response
    {
        [$tipo, $nombre, $et, $d] = $this->informeDatos($r);
        return Response::html(View::render('gestion/informes', ['vista' => 'informes', 'tipo' => $tipo, 'nombre' => $nombre, 'et' => $et, 'd' => $d, 'q' => $r->query]));
    }

    public function informeExcel(Request $r): Response
    {
        [, $nombre, $et, $d] = $this->informeDatos($r);
        return Response::archivo(Informes::excel($nombre, $et, $d), Informes::nombreArchivo($nombre, $et, 'xlsx'), 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function informePdf(Request $r): Response
    {
        [, $nombre, $et, $d] = $this->informeDatos($r);
        return Response::archivo(Informes::pdf($nombre, $et, $d, App::$usuario['nombre']), Informes::nombreArchivo($nombre, $et, 'pdf'), 'application/pdf');
    }
}
