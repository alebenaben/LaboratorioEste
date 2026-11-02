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
use App\Domain\Catalogo;
use App\Domain\Documentos;
use App\Domain\Fechas;
use App\Domain\Fmt;
use App\Domain\Mails;
use App\Domain\Padron;
use App\Domain\Permisos;
use App\Domain\Promociones;
use App\Service\Pdf;

/** CU-15 a CU-19: cotizador, beneficios, envío de información y promociones. */
final class Comercial
{
    // ------------------------------------------------------------------ CU-15 Cotizador

    public function cotizador(Request $r): Response
    {
        return Response::html(View::render('comercial/cotizador', ['vista' => 'cotizador', 'nombresPadron' => Padron::nombresPadron()]));
    }

    /** Lee plan, categorías y promoción de la cotización. */
    private function leerCot(Request $r): array
    {
        $plan = $r->str('plan', 'PLUS');
        if (!isset(Catalogo::planes()[$plan])) {
            throw new Falta('Elegí el plan.');
        }
        $cats = array_values(array_filter(explode(',', $r->str('cats')), fn($c) => isset(Catalogo::categorias()[$c])));
        if (!$cats) {
            $cats = ['TIT'];
        }
        $pct = $r->int('pct');
        $promo = in_array($pct, Catalogo::PROMO_PCT, true) ? ['pct' => $pct, 'meses' => in_array($r->int('meses'), Catalogo::PROMO_MESES, true) ? $r->int('meses') : 3] : null;
        return [$plan, $cats, $promo];
    }

    /** "Enviar cotización en PDF": valida nombre y celular, registra la consulta y abre WhatsApp. */
    public function enviarCotizacion(Request $r): Response
    {
        [$plan, $cats, $promo] = $this->leerCot($r);
        $nom = Fmt::may($r->str('nombre'));
        $tel = Fmt::telWa($r->str('celular'));
        $f = [];
        if ($nom === '') {
            $f[] = 'cot-nom';
        }
        if (!Fmt::telValido($tel)) {
            $f[] = 'cot-tel';
        }
        if ($f) {
            throw new Falta(implode(' ', array_filter([$nom === '' ? 'Escribí el apellido y nombre del interesado.' : '', !Fmt::telValido($tel) ? 'El celular tiene que tener 10 números con la característica, sin 0 ni 15 (ej.: 3435013651).' : ''])), $f);
        }
        $pi = Fmt::partirInteresado($nom, Padron::nombresPadron());
        App::$db->insert('consultas', [
            'usuario' => App::$usuario['nombre'], 'canal' => 'WhatsApp', 'tipo' => 'Cotización',
            'nombre' => implode(' ', array_filter([$pi['apellido'], ...$pi['nombres']])), 'celular' => $tel,
            'planes' => \App\Core\Db::arrayPg([$plan]), 'como' => 'Cotización',
        ]);
        $saludo = Fmt::capi($pi['nombres'][0] ?? $pi['apellido']);
        $q = http_build_query(['plan' => $plan, 'cats' => implode(',', $cats), 'pct' => $promo['pct'] ?? '', 'meses' => $promo['meses'] ?? '', 'nombre' => $nom]);
        return Response::json(['ok' => true, 'mensaje' => 'Cotización registrada como consulta.', 'whatsapp' => [
            'tel' => $tel, 'texto' => Documentos::mensajeCorto('cotizacion', $saludo, ['plan' => Catalogo::plan($plan)['nombre']]), 'pdf' => '/pdf/cotizacion?' . $q,
        ]]);
    }

    public function pdfCotizacion(Request $r): Response
    {
        [$plan, $cats, $promo] = $this->leerCot($r);
        $info = Documentos::infoCot($plan, $cats, $promo, Fmt::may($r->str('nombre')));
        return Pdf::respuesta(Pdf::generar('info', ['d' => $info]), 'Cotización - ' . Catalogo::plan($plan)['nombre']);
    }

    /** "Enviar este plan por WhatsApp" (CU-15 A3 y CU-16 A1). */
    public function pdfPlan(Request $r): Response
    {
        $plan = (string)$r->params['id'];
        if (!isset(Catalogo::planes()[$plan])) {
            throw new HttpError(404);
        }
        return Pdf::respuesta(Pdf::generar('info', ['d' => Documentos::infoPlan($plan, $r->str('cot'))]), Catalogo::plan($plan)['nombre']);
    }

    public function waPlan(Request $r): Response
    {
        $plan = (string)$r->params['id'];
        if (!isset(Catalogo::planes()[$plan])) {
            throw new HttpError(404);
        }
        $tel = Fmt::telWa($r->str('celular'));
        return Response::json(['ok' => true, 'whatsapp' => ['tel' => Fmt::telValido($tel) ? $tel : '', 'texto' => Documentos::mensajeCorto('plan', '', ['plan' => Catalogo::plan($plan)['nombre']]), 'pdf' => "/pdf/plan/$plan?" . http_build_query(['cot' => $r->str('cot')])]]);
    }

    // ------------------------------------------------------------------ CU-16 / CU-17 Beneficios

    public function beneficios(Request $r): Response
    {
        return Response::html(View::render('comercial/beneficios', ['vista' => 'beneficios', 'nombresPadron' => Padron::nombresPadron()]));
    }

    /** CU-17: enviar información a un interesado o a un afiliado. */
    public function enviarInfo(Request $r): Response
    {
        $tipo = $r->str('tipo', 'int');
        $modo = $r->str('modo', 'compartir');
        $tel = Fmt::telWa($r->str('celular'));
        if ($tipo === 'afi') {
            $a = Padron::buscarUno($r->str('afiliado'));
            if (!$a) {
                throw new Falta('Elegí el afiliado.', ['at-busca']);
            }
            $pdf = "/pdf/afiliado/{$a['id']}";
            $nombre = $a['nombre'];
            $planes = [Padron::grupo($a['grupo_id'])['plan_id']];
            $texto = Documentos::mensajeCorto('afiliado', Padron::nomPila($a));
            $afiId = $a['id'];
        } else {
            $nom = Fmt::may($r->str('nombre'));
            $planes = array_values(array_intersect($r->arr('planes'), array_keys(Catalogo::planes())));
            $f = [];
            if ($nom === '') {
                $f[] = 'at-nom';
            }
            if (!$planes) {
                $f[] = 'at-planes';
            }
            if ($f) {
                throw new Falta(implode(' ', array_filter([$nom === '' ? 'Escribí el apellido y nombre.' : '', !$planes ? 'Marcá al menos un plan.' : ''])), $f);
            }
            $pi = Fmt::partirInteresado($nom, Padron::nombresPadron());
            $nombre = implode(' ', array_filter([$pi['apellido'], ...$pi['nombres']]));
            $pdf = '/pdf/consulta?' . http_build_query(['nombre' => $nom, 'planes' => implode(',', $planes)]);
            $texto = Documentos::mensajeCorto('consulta', Fmt::capi($pi['nombres'][0] ?? $pi['apellido']));
            $afiId = null;
        }
        if ($modo === 'bajar') {
            return Response::json(['ok' => true, 'descargar' => $pdf]);
        }
        if (!Fmt::telValido($tel)) {
            throw new Falta('Falta un celular válido: 10 números con la característica, sin 0 ni 15 (ej.: 3435013651).', ['at-tel']);
        }
        App::$db->insert('consultas', [
            'usuario' => App::$usuario['nombre'], 'canal' => 'WhatsApp', 'tipo' => $tipo === 'afi' ? 'Afiliado' : 'Información',
            'afiliado_id' => $afiId, 'nombre' => $nombre, 'celular' => $tel, 'planes' => \App\Core\Db::arrayPg($planes), 'como' => 'PDF',
        ]);
        return Response::json(['ok' => true, 'mensaje' => 'Listo: el envío quedó registrado.', 'whatsapp' => ['tel' => $tel, 'texto' => $texto, 'pdf' => $pdf], 'limpiar' => true]);
    }

    public function pdfConsulta(Request $r): Response
    {
        $planes = array_values(array_intersect(explode(',', $r->str('planes')), array_keys(Catalogo::planes())));
        $info = Documentos::infoConsulta($r->str('nombre'), $planes ?: ['PLUS']);
        return Pdf::respuesta(Pdf::generar('info', ['d' => $info]), 'Integral del Este - ' . $info['titulo']);
    }

    /** Datos de un afiliado para completar el celular (CU-17 paso 2). */
    public function afiliadoInfo(Request $r): Response
    {
        $a = Padron::buscarUno($r->str('q'));
        if (!$a) {
            return Response::json(['ok' => true, 'a' => null]);
        }
        $g = Padron::grupo($a['grupo_id']);
        $tit = Padron::titular($g['id']);
        return Response::json(['ok' => true, 'a' => ['id' => $a['id'], 'nombre' => $a['nombre'], 'nro' => $a['nro'], 'plan' => Catalogo::plan($g['plan_id'])['nombre'], 'activo' => $a['activo'], 'tel' => $a['telefono'] ?: ($tit['telefono'] ?? '')]]);
    }

    // ------------------------------------------------------------------ CU-18 / CU-19 Promociones

    public function promociones(Request $r): Response
    {
        $env = $r->int('enviar');
        if ($env) {
            $p = Promociones::una($env);
            $dest = Promociones::destinatarios($p);
            $t0 = $dest[0]['t'] ?? null;
            $ejemplo = $t0 ? Mails::armar('promo', Promociones::vars($p, $t0)) : null;
            return Response::html(View::render('comercial/promo_envio', ['vista' => 'promociones', 'p' => $p, 'dest' => $dest, 'ejemplo' => $ejemplo, 'hoy' => Fechas::hoy()]));
        }
        $edit = $r->int('editar') ? Promociones::una($r->int('editar')) : null;
        $lista = Promociones::todas();
        $cant = [];
        foreach ($lista as $p) {
            $d = Promociones::destinatarios($p);
            $cant[$p['id']] = [count(array_filter($d, fn($x) => $x['envio'])), count($d)];
        }
        return Response::html(View::render('comercial/promociones', ['vista' => 'promociones', 'lista' => $lista, 'edit' => $edit, 'cant' => $cant, 'hoy' => Fechas::hoy()]));
    }

    public function guardarPromo(Request $r): Response
    {
        $id = Promociones::guardar($r->int('id') ?: null, $r->post);
        App::$sesion->flash($r->int('id') ? 'Cambios guardados.' : 'Promoción guardada.');
        return Response::json(['ok' => true, 'redirect' => '/promociones']);
    }

    public function eliminarPromo(Request $r): Response
    {
        Promociones::eliminar((int)$r->params['id']);
        return Response::json(['ok' => true, 'mensaje' => 'Promoción eliminada.', 'redirect' => '/promociones']);
    }

    /** Vista previa del PDF (sin nombre) o personalizado para un grupo. */
    public function pdfPromo(Request $r): Response
    {
        $p = Promociones::una((int)$r->params['id']);
        $t = $r->int('g') ? Padron::titular($r->int('g')) : null;
        return Pdf::respuesta(Pdf::generar('info', ['d' => Documentos::infoPromo($p, $t)]), 'Promoción - ' . $p['titulo'] . ($t ? ' - ' . Padron::nomPila($t) : ''), !$r->bool('ver'));
    }

    /** "Ver cómo queda el PDF" con los datos del formulario sin guardar. */
    public function pdfPromoBorrador(Request $r): Response
    {
        $p = ['titulo' => $r->str('titulo') ?: 'Título', 'beneficio' => $r->str('beneficio') ?: 'Beneficio', 'unidad' => $r->str('unidad'), 'desde' => $r->str('desde') ?: Fechas::hoy(), 'hasta' => $r->str('hasta') ?: Fechas::hoy(), 'texto' => $r->str('texto'), 'cond' => $r->str('cond')];
        return Pdf::respuesta(Pdf::generar('info', ['d' => Documentos::infoPromo($p, null)]), 'Promoción - vista previa');
    }

    /** CU-19: mail a todos los que tienen email y no la recibieron. */
    public function promoMail(Request $r): Response
    {
        $p = Promociones::una((int)$r->params['id']);
        if (Promociones::estado($p)[0] === 'Terminada') {
            throw new Falta('La promoción ya terminó.');
        }
        $n = 0;
        foreach (Promociones::destinatarios($p) as $x) {
            if ($x['envio'] || !$x['g']['email']) {
                continue;
            }
            if (Promociones::marcarEnvio($p['id'], $x['g']['id'], 'Mail')) {
                $m = Mails::armar('promo', Promociones::vars($p, $x['t']));
                Mails::encolar($x['g']['email'], $m['asunto'], $m['cuerpo'], ['tipo' => 'promo', 'id' => $p['id'], 'grupo' => $x['g']['id']], ['tabla' => 'promociones_envios', 'promo' => $p['id'], 'grupo' => $x['g']['id']]);
                $n++;
            }
        }
        if (!$n) {
            throw new Falta('No hay afiliados con email que falten recibirla.');
        }
        Historial::log(App::$db, 'Promociones', "Promoción {$p['titulo']} enviada por mail a $n");
        App::$sesion->flash("Se están enviando $n mails.");
        return Response::json(['ok' => true, 'recargar' => true]);
    }

    /** CU-19: WhatsApp de a uno (al siguiente o a un grupo puntual). */
    public function promoWa(Request $r): Response
    {
        $p = Promociones::una((int)$r->params['id']);
        if (Promociones::estado($p)[0] === 'Terminada') {
            throw new Falta('La promoción ya terminó.');
        }
        $gid = $r->int('g');
        $sel = null;
        foreach (Promociones::destinatarios($p) as $x) {
            if ($gid ? $x['g']['id'] === $gid : (!$x['envio'] && Fmt::telValido($x['tel']))) {
                $sel = $x;
                break;
            }
        }
        if (!$sel) {
            throw new Falta($gid ? 'No se encontró ese grupo.' : 'A los que faltan no les tenemos un celular cargado.');
        }
        Promociones::marcarEnvio($p['id'], $sel['g']['id'], 'WhatsApp');
        $tel = Fmt::telWa($sel['tel']);
        return Response::json(['ok' => true, 'recargar' => true, 'whatsapp' => [
            'tel' => Fmt::telValido($tel) ? $tel : '', 'texto' => Documentos::mensajeCorto('promo', Padron::nomPila($sel['t']), ['beneficio' => $p['beneficio'], 'lugar' => Documentos::lugarPromo($p)]),
            'pdf' => "/pdf/promo/{$p['id']}?g={$sel['g']['id']}",
        ]]);
    }

    public static function puede(string $seccion): bool
    {
        return Permisos::puedeVer(App::$usuario, $seccion);
    }
}
