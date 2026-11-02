<?php
declare(strict_types=1);

use App\Core\App;
use App\Http\CajaCtl;

/** Rutas de Caja y cobranza (CU-26 a CU-32). */
return function (App $app): void {
    $c = ['seccion' => 'caja'];
    $app->get('/caja', [CajaCtl::class, 'index'], $c);
    $app->post('/caja/facturar', [CajaCtl::class, 'facturar'], $c);
    $app->post('/caja/mails', [CajaCtl::class, 'mailsPeriodo'], $c);
    $app->post('/caja/debito', [CajaCtl::class, 'debito'], $c);
    $app->post('/caja/cobrar', [CajaCtl::class, 'cobrar'], ['admin' => true]);
    $app->get('/facturas/{id}', [CajaCtl::class, 'verFactura'], ['admin' => true]);
    $app->get('/recibos/{id}', [CajaCtl::class, 'verRecibo'], ['admin' => true]);
    $app->get('/pdf/factura/{id}', [CajaCtl::class, 'pdfFactura'], ['admin' => true]);
    $app->get('/pdf/recibo/{id}', [CajaCtl::class, 'pdfRecibo'], ['admin' => true]);
    foreach (['factura', 'recibo'] as $t) {
        $app->post("/{$t}s/{id}/mail", function ($r) use ($t) { $r->params['tipo'] = $t; return (new CajaCtl())->mailUno($r); }, ['admin' => true]);
        $app->post("/{$t}s/{id}/whatsapp", function ($r) use ($t) { $r->params['tipo'] = $t; return (new CajaCtl())->waComprobante($r); }, ['admin' => true]);
    }
    $app->get('/cuentas', [CajaCtl::class, 'cuentas'], ['seccion' => 'cuentas']);
    $app->post('/api/avisos/{id}/leido', function () {
        App::$db->exec('UPDATE avisos SET leido = now() WHERE id = :id', ['id' => (int)App::$req->params['id']]);
        return \App\Core\Response::json(['ok' => true, 'recargar' => true]);
    }, ['admin' => true]);
};
