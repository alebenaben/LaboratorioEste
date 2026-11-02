<?php
declare(strict_types=1);

use App\Core\App;
use App\Http\GestionCtl;

/** Rutas de Gestión (CU-33 a CU-38). */
return function (App $app): void {
    $app->get('/egresos', [GestionCtl::class, 'egresos'], ['seccion' => 'egresos']);
    $app->post('/egresos', [GestionCtl::class, 'guardarEgreso'], ['seccion' => 'egresos']);
    $app->post('/egresos/{id}/borrar', [GestionCtl::class, 'borrarEgreso'], ['seccion' => 'egresos']);
    $app->get('/api/egresos/proveedores', [GestionCtl::class, 'proveedores'], ['seccion' => 'egresos']);
    $app->get('/contabilidad', [GestionCtl::class, 'contabilidad'], ['seccion' => 'contabilidad']);
    $app->get('/estadisticas', [GestionCtl::class, 'estadisticas'], ['seccion' => 'estadisticas']);
    $app->post('/estadisticas/resumen/mail', [GestionCtl::class, 'resumenMail'], ['seccion' => 'estadisticas']);
    $app->post('/estadisticas/resumen/whatsapp', [GestionCtl::class, 'resumenWa'], ['seccion' => 'estadisticas']);
    $app->post('/estadisticas/ahorro/whatsapp', [GestionCtl::class, 'ahorroWa'], ['seccion' => 'estadisticas']);
    $app->get('/pdf/resumen/{g}/{per}', [GestionCtl::class, 'pdfResumen'], ['seccion' => 'estadisticas']);
    $app->get('/pdf/ahorro/{g}/{k}', [GestionCtl::class, 'pdfAhorro'], ['seccion' => 'estadisticas']);
    $app->get('/informes', [GestionCtl::class, 'informes'], ['seccion' => 'informes']);
    $app->get('/informes/excel', [GestionCtl::class, 'informeExcel'], ['seccion' => 'informes']);
    $app->get('/informes/pdf', [GestionCtl::class, 'informePdf'], ['seccion' => 'informes']);
};
