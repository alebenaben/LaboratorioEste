<?php
declare(strict_types=1);

namespace App\Http;

use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Domain\Catalogo;
use App\Domain\Fechas;
use App\Domain\Padron;

/** CU-06 · Dar de alta un grupo de afiliados. */
final class Altas
{
    public function form(Request $r): Response
    {
        $plan = $r->str('plan', 'PLUS');
        if (!isset(Catalogo::planes()[$plan])) {
            $plan = 'PLUS';
        }
        $cats = array_values(array_filter(explode(',', $r->str('cats')), fn($c) => isset(Catalogo::categorias()[$c])));
        $titCat = $cats && in_array($cats[0], Catalogo::catTitular(), true) ? $cats[0] : 'TIT';
        $fam = [];
        foreach (array_slice($cats, 1) as $c) {
            if (in_array($c, Catalogo::catAdicional(), true)) {
                $fam[] = $c;
            }
        }
        if (Catalogo::plan($plan)['individual']) {
            $fam = [];
        }
        $pct = $r->int('pct');
        $promo = $pct && in_array($pct, Catalogo::PROMO_PCT, true) ? ['pct' => $pct, 'meses' => in_array($r->int('meses'), Catalogo::PROMO_MESES, true) ? $r->int('meses') : 3] : null;
        return Response::html(View::render('altas/form', [
            'vista' => 'altas', 'plan' => $plan, 'titCat' => $titCat, 'fam' => $fam, 'promo' => $promo, 'hoy' => Fechas::hoy(),
        ]));
    }

    public function guardar(Request $r): Response
    {
        $fam = [];
        foreach ($r->arr('fam') as $f) {
            if (is_array($f)) {
                $fam[] = $f;
            }
        }
        $d = [
            'plan' => $r->str('plan'), 'forma_pago' => $r->str('forma_pago'), 'email' => $r->str('email'), 'sin_email' => $r->bool('sin_email'),
            'fecha_alta' => $r->str('fecha_alta'), 'telefono' => $r->str('telefono'), 'obs' => $r->str('obs'),
            'promo_pct' => $r->int('promo_pct'), 'promo_meses' => $r->int('promo_meses'),
            'titular' => ['apellido' => $r->str('t_apellido'), 'nombre1' => $r->str('t_nombre1'), 'nombre2' => $r->str('t_nombre2'), 'doc' => $r->str('t_doc'), 'nac' => $r->str('t_nac'), 'categoria' => $r->str('t_categoria')],
            'familia' => $fam,
        ];
        $res = Padron::alta($d);
        $msg = "Alta registrada: {$res['titular']['nombre']}, afiliado N° {$res['nros'][0]}" . (count($res['nros']) > 1 ? ' (y ' . (count($res['nros']) - 1) . ' familiar' . (count($res['nros']) > 2 ? 'es' : '') . ')' : '') . '.';
        App::$sesion->flash($msg);
        $dest = App::$usuario['admin'] ? '/afiliados?estado=&q=' . $res['nros'][0] : '/inicio';
        return Response::json(['ok' => true, 'redirect' => $dest]);
    }
}
