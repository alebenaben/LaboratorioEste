<?php
declare(strict_types=1);

use App\Core\App;
use App\Http\Afiliados;
use App\Http\Altas;
use App\Http\RetencionCtl;

/** Rutas del módulo Afiliados (CU-05 a CU-14, CU-25). */
return function (App $app): void {
    $int = ['roles' => ['admin', 'usuario']];
    $app->get('/afiliados', [Afiliados::class, 'index'], ['seccion' => 'afiliados']);
    $app->get('/afiliados/tabla', [Afiliados::class, 'tabla'], ['seccion' => 'afiliados']);
    $app->get('/api/afiliados', [Afiliados::class, 'api'], ['seccion' => 'afiliados']);
    $app->get('/afiliados/{id}/ficha', [Afiliados::class, 'ficha'], ['seccion' => 'afiliados']);
    $app->post('/afiliados/{id}', [Afiliados::class, 'editar'], ['seccion' => 'afiliados']);
    $app->get('/grupos/{id}/promo', [Afiliados::class, 'promoForm'], ['admin' => true]);
    $app->post('/grupos/{id}/promo', [Afiliados::class, 'promo'], ['admin' => true]);
    $app->get('/afiliados/{id}/baja', [Afiliados::class, 'bajaForm'], ['admin' => true]);
    $app->post('/afiliados/{id}/reactivar', [Afiliados::class, 'reactivar'], ['admin' => true]);
    $app->get('/afiliados/{id}/historial', [Afiliados::class, 'historial'], $int);
    $app->get('/usos/{id}', [Afiliados::class, 'uso'], ['roles' => ['admin', 'usuario', 'unidad']]);
    $app->get('/pdf/afiliado/{id}', [Afiliados::class, 'pdfAfiliado'], $int);
    $app->post('/afiliados/{id}/whatsapp', [Afiliados::class, 'waAfiliado'], $int);
    $app->get('/api/afiliados/sugerir', [Afiliados::class, 'sugerir'], ['roles' => ['admin', 'usuario', 'unidad']]);
    $app->get('/api/afiliados/buscar', [Afiliados::class, 'buscar'], $int);
    $app->get('/api/padron/{campo}', [Afiliados::class, 'valores'], ['seccion' => 'altas']);
    $app->get('/api/afiliados/doc', [Afiliados::class, 'docActivo'], ['seccion' => 'altas']);
    $app->post('/api/cumple/{id}/saludar', [Afiliados::class, 'saludar'], $int);
    $app->get('/pdf/cumple/{id}', [Afiliados::class, 'pdfCumple'], $int);
    $app->get('/sinacceso', [Afiliados::class, 'sinAcceso'], ['libre_clave' => true]);

    $app->get('/altas', [Altas::class, 'form'], ['seccion' => 'altas']);
    $app->post('/altas', [Altas::class, 'guardar'], ['seccion' => 'altas']);

    $app->get('/retencion', [RetencionCtl::class, 'index'], ['seccion' => 'retencion']);
    $app->post('/retencion/registrar', [RetencionCtl::class, 'registrar'], $int);
    $app->post('/retencion/{id}/confirmar-baja', [RetencionCtl::class, 'confirmarBaja'], ['admin' => true]);
};
