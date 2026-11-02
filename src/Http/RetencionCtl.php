<?php
declare(strict_types=1);

namespace App\Http;

use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Domain\Fechas;
use App\Domain\Padron;
use App\Domain\Promociones;
use App\Domain\Retencion;

/** CU-12 · Quiere darse de baja, y CU-13 · Resolver casos pendientes. */
final class RetencionCtl
{
    public function index(Request $r): Response
    {
        $a = null;
        $q = $r->str('q');
        if ($r->int('a')) {
            $a = Padron::afiliado($r->int('a'));
        } elseif ($q !== '') {
            $a = Padron::buscarUno($q);
        }
        $fin = $r->str('fin');
        $vars = ['vista' => 'retencion', 'q' => $q ?: ($a ? "{$a['nombre']} · N° {$a['nro']}" : ''), 'a' => $a, 'fin' => $fin, 'motivo' => $r->str('m'), 'llamar' => $a ? [] : Retencion::paraLlamar()];
        if ($a && $a['activo'] && !$fin) {
            $d = Retencion::datos($a);
            $ofertas = [];
            foreach (array_keys(Retencion::motivos()) as $m) {
                $ofertas[$m] = Retencion::ofertas($a, $m)['L'];
            }
            $guiones = [];
            foreach (array_keys(Retencion::motivos()) as $m) {
                $guiones[$m] = Retencion::guion($m, $d, Padron::nomPila($a));
            }
            $vars += ['d' => $d, 'ofertas' => $ofertas, 'guiones' => $guiones, 'prev' => Retencion::previa($a['grupo_id']),
                'promos' => Promociones::vigentesParaPlan($d['g']['plan_id']), 'cuenta' => Padron::cuenta($a['grupo_id']), 'hoy' => Fechas::hoy()];
        }
        return Response::html(View::render('retencion/index', $vars));
    }

    public function registrar(Request $r): Response
    {
        $res = Retencion::registrar($r->int('afiliado'), $r->str('motivo'), $r->str('comentario'), $r->int('paso'), $r->str('accion'), App::$usuario, $r->str('origen', 'guia'));
        return Response::json(['ok' => true] + $res);
    }

    public function confirmarBaja(Request $r): Response
    {
        Retencion::confirmarBaja((int)$r->params['id'], App::$usuario);
        return Response::json(['ok' => true, 'mensaje' => 'Baja confirmada.', 'recargar' => true]);
    }
}
