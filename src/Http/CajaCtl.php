<?php
declare(strict_types=1);

namespace App\Http;

use App\Core\App;
use App\Core\Config;
use App\Core\Falta;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Domain\Caja;
use App\Domain\Documentos;
use App\Domain\Fechas;
use App\Domain\Fmt;
use App\Domain\Mails;
use App\Domain\Padron;
use App\Service\Comprobantes;
use App\Service\Pdf;

/** CU-26 a CU-32. */
final class CajaCtl
{
    private function periodo(Request $r): string
    {
        $hoy = substr(Fechas::hoy(), 0, 7);
        $ini = Config::inicio()['periodo'];
        $p = $r->str('per');
        return preg_match('/^\d{4}-\d{2}$/', $p) ? $p : max($hoy, $ini);
    }

    public function index(Request $r): Response
    {
        $per = $this->periodo($r);
        $filtro = $r->str('fper');
        $aF = Caja::aFacturar($per);
        $fs = App::$db->all('SELECT f.*, (SELECT t.nombre FROM afiliados t WHERE t.grupo_id = f.grupo_id ORDER BY (t.estado = \'activo\') DESC, t.orden, t.nro LIMIT 1) AS titular FROM facturas f' . ($filtro ? ' WHERE f.periodo = :p' : '') . ' ORDER BY f.id DESC LIMIT 60', $filtro ? ['p' => $filtro] : []);
        $recs = App::$db->all('SELECT r.*, f.periodo, (SELECT t.nombre FROM afiliados t WHERE t.grupo_id = r.grupo_id ORDER BY (t.estado = \'activo\') DESC, t.orden, t.nro LIMIT 1) AS titular FROM recibos r JOIN facturas f ON f.id = r.factura_id ORDER BY r.id DESC LIMIT 30');
        return Response::html(View::render('caja/index', [
            'vista' => 'caja', 'per' => $per, 'aFacturar' => $aF, 'pend' => Caja::pendientes(), 'debito' => Caja::pendientesDebito(),
            'facturas' => array_map([Caja::class, 'hidratarFactura'], $fs), 'recibos' => $recs, 'filtro' => $filtro,
            'periodos' => App::$db->col('SELECT DISTINCT periodo FROM facturas ORDER BY periodo DESC'),
            'ultima' => Config::get('ultima_emision'), 'mails' => Caja::estadoMails($per), 'hoy' => Fechas::hoy(),
            'cola' => App::$db->one("SELECT count(*) FILTER (WHERE tipo = 'cae') AS cae, count(*) FILTER (WHERE tipo = 'mail') AS mail FROM cola_tareas WHERE estado IN ('pendiente','en_curso')"),
            'ejemplo' => ($x = Caja::estadoMails($per)['aEnviar'][0] ?? null) ? Mails::armar('factura', Mails::varsFactura(Caja::factura((int)$x['id']))) : null,
        ]));
    }

    /** CU-26 */
    public function facturar(Request $r): Response
    {
        $per = $r->str('per');
        $fs = Caja::facturar($per, App::$usuario['nombre']);
        if (!$fs) {
            throw new Falta('Todos los grupos ya tienen la factura de ' . Fmt::perLabel($per) . '.');
        }
        App::$sesion->flash(count($fs) . ' facturas emitidas. ARCA las autoriza en segundo plano (CAE).');
        return Response::json(['ok' => true, 'redirect' => '/caja?per=' . $per]);
    }

    /** CU-28 */
    public function mailsPeriodo(Request $r): Response
    {
        $n = Caja::enviarMailsPeriodo($r->str('per'));
        if (!$n) {
            throw new Falta('No hay facturas con CAE y email pendientes de enviar.');
        }
        App::$sesion->flash("Se están enviando $n mails.");
        return Response::json(['ok' => true, 'recargar' => true]);
    }

    /** CU-29: registra un cobro por débito automático (una o todas). */
    public function debito(Request $r): Response
    {
        $usr = App::$usuario['nombre'];
        if ($r->bool('todas')) {
            $n = 0;
            foreach (Caja::pendientesDebito() as $f) {
                Caja::cobrar($f['id'], 'Débito automático', Fechas::hoy(), true, $usr);
                $n++;
            }
            App::$sesion->flash("$n recibos emitidos por débito automático.");
            return Response::json(['ok' => true, 'recargar' => true]);
        }
        $rec = Caja::cobrar($r->int('factura'), 'Débito automático', $r->str('fecha') ?: Fechas::hoy(), true, $usr);
        return Response::json(['ok' => true, 'mensaje' => "Recibo {$rec['nro']} emitido.", 'modalUrl' => "/recibos/{$rec['id']}"]);
    }

    /** CU-30 / CU-32: cobro en mostrador (una o todas las pendientes de un grupo). */
    public function cobrar(Request $r): Response
    {
        $usr = App::$usuario['nombre'];
        $medio = $r->str('medio');
        if ($r->int('grupo')) {
            $c = Padron::cuenta($r->int('grupo'));
            $recs = [];
            foreach ($c['pendientes'] as $f) {
                $recs[] = Caja::cobrar((int)$f['id'], $medio, Fechas::hoy(), false, $usr);
            }
            if (!$recs) {
                throw new Falta('No hay cuotas pendientes.');
            }
            if (count($recs) === 1) {
                return Response::json(['ok' => true, 'mensaje' => "Recibo {$recs[0]['nro']} emitido.", 'modalUrl' => "/recibos/{$recs[0]['id']}"]);
            }
            App::$sesion->flash(count($recs) . ' recibos emitidos. Los ves en la cuenta corriente.');
            return Response::json(['ok' => true, 'recargar' => true]);
        }
        $rec = Caja::cobrar($r->int('factura'), $medio, Fechas::hoy(), false, $usr);
        return Response::json(['ok' => true, 'mensaje' => "Recibo {$rec['nro']} emitido por " . Fmt::money($rec['importe']) . '.', 'modalUrl' => "/recibos/{$rec['id']}"]);
    }

    // ------------------------------------------------------------------ CU-31 ver, imprimir, enviar

    public function verFactura(Request $r): Response
    {
        $f = Caja::factura((int)$r->params['id']);
        return Response::html(View::parcial('caja/modal', ['tipo' => 'factura', 'id' => $f['id'], 'html' => Comprobantes::htmlFactura($f), 'tieneMail' => (bool)Padron::grupo($f['grupo_id'])['email']]));
    }

    public function verRecibo(Request $r): Response
    {
        $rec = Caja::recibo((int)$r->params['id']);
        return Response::html(View::parcial('caja/modal', ['tipo' => 'recibo', 'id' => $rec['id'], 'html' => Comprobantes::htmlRecibo($rec), 'tieneMail' => (bool)Padron::grupo((int)$rec['grupo_id'])['email']]));
    }

    public function pdfFactura(Request $r): Response
    {
        $f = Caja::factura((int)$r->params['id']);
        return Pdf::respuesta(Comprobantes::pdfFactura($f), "Factura {$f['letra']} {$f['nro']}");
    }

    public function pdfRecibo(Request $r): Response
    {
        $rec = Caja::recibo((int)$r->params['id']);
        return Pdf::respuesta(Comprobantes::pdfRecibo($rec), "Recibo {$rec['nro']}");
    }

    public function mailUno(Request $r): Response
    {
        Caja::encolarMail($r->params['tipo'] === 'factura' ? 'factura' : 'recibo', (int)$r->params['id']);
        return Response::json(['ok' => true, 'mensaje' => 'Mail en camino.']);
    }

    public function waComprobante(Request $r): Response
    {
        $tipo = $r->params['tipo'];
        $id = (int)$r->params['id'];
        if ($tipo === 'factura') {
            $f = Caja::factura($id);
            $gid = $f['grupo_id'];
            $txt = Documentos::mensajeCorto('factura', Padron::nomPila(Padron::titular($gid)), ['periodo' => Fmt::perLabel($f['periodo'])]);
            $pdf = "/pdf/factura/$id";
        } else {
            $rec = Caja::recibo($id);
            $gid = (int)$rec['grupo_id'];
            $txt = Documentos::mensajeCorto('recibo', Padron::nomPila(Padron::titular($gid)));
            $pdf = "/pdf/recibo/$id";
        }
        $tel = Fmt::telWa(Padron::telefonoGrupo($gid));
        return Response::json(['ok' => true, 'whatsapp' => ['tel' => Fmt::telValido($tel) ? $tel : '', 'texto' => $txt, 'pdf' => $pdf]]);
    }

    // ------------------------------------------------------------------ CU-32 Cuentas corrientes

    public function cuentas(Request $r): Response
    {
        $estados = Padron::estadosCuenta();
        $lista = [];
        foreach (App::$db->col('SELECT id FROM grupos ORDER BY id') as $gid) {
            $g = Padron::grupo((int)$gid);
            $act = Padron::activosEn($g['id']);
            $c = Padron::cuenta($g['id']);
            if (!$act && !$c['saldo']) {
                continue;
            }
            $lista[] = ['g' => $g, 't' => Padron::titular($g['id']), 'n' => count($act), 'cuota' => Padron::cuotaActual($g), 'saldo' => $c['saldo'], 'estado' => $estados[$g['id']] ?? 'al_dia'];
        }
        usort($lista, fn($a, $b) => $a['t']['nro'] <=> $b['t']['nro']);
        $sel = $r->int('g') ? Padron::grupo($r->int('g')) : null;
        $det = $sel ? ['g' => $sel, 't' => Padron::titular($sel['id']), 'activos' => Padron::activosEn($sel['id']), 'mov' => Caja::cuentaCorriente($sel['id']), 'cuenta' => Padron::cuenta($sel['id'])] : null;
        return Response::html(View::render('caja/cuentas', ['vista' => 'cuentas', 'lista' => $lista, 'det' => $det]));
    }
}
