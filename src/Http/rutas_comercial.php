<?php
declare(strict_types=1);

use App\Core\App;
use App\Http\BeneficiosCtl;
use App\Http\Comercial;

/** Rutas de Comercial (CU-15 a CU-19) y Beneficios (CU-20 a CU-24). */
return function (App $app): void {
    $app->get('/cotizador', [Comercial::class, 'cotizador'], ['seccion' => 'cotizador']);
    $app->post('/cotizador/enviar', [Comercial::class, 'enviarCotizacion'], ['seccion' => 'cotizador']);
    $app->get('/pdf/cotizacion', [Comercial::class, 'pdfCotizacion'], ['seccion' => 'cotizador']);
    $app->get('/pdf/plan/{id}', [Comercial::class, 'pdfPlan'], ['roles' => ['admin', 'usuario']]);
    $app->post('/cotizador/plan/{id}/whatsapp', [Comercial::class, 'waPlan'], ['roles' => ['admin', 'usuario']]);

    $app->get('/beneficios', [Comercial::class, 'beneficios'], ['seccion' => 'beneficios']);
    $app->post('/beneficios/enviar', [Comercial::class, 'enviarInfo'], ['seccion' => 'beneficios']);
    $app->get('/pdf/consulta', [Comercial::class, 'pdfConsulta'], ['seccion' => 'beneficios']);
    $app->get('/api/beneficios/afiliado', [Comercial::class, 'afiliadoInfo'], ['seccion' => 'beneficios']);

    $app->get('/promociones', [Comercial::class, 'promociones'], ['seccion' => 'promociones']);
    $app->post('/promociones', [Comercial::class, 'guardarPromo'], ['seccion' => 'promociones']);
    $app->post('/promociones/{id}/eliminar', [Comercial::class, 'eliminarPromo'], ['seccion' => 'promociones']);
    $app->get('/pdf/promo/{id}', [Comercial::class, 'pdfPromo'], ['seccion' => 'promociones']);
    $app->get('/pdf/promo-borrador', [Comercial::class, 'pdfPromoBorrador'], ['seccion' => 'promociones']);
    $app->post('/promociones/{id}/mail', [Comercial::class, 'promoMail'], ['seccion' => 'promociones']);
    $app->post('/promociones/{id}/whatsapp', [Comercial::class, 'promoWa'], ['seccion' => 'promociones']);

    $app->get('/autorizaciones', [BeneficiosCtl::class, 'autorizaciones'], ['seccion' => 'autorizaciones']);
    $app->get('/autorizaciones/verificar', [BeneficiosCtl::class, 'verificar'], ['seccion' => 'autorizaciones']);
    $app->post('/autorizaciones', [BeneficiosCtl::class, 'autorizar'], ['seccion' => 'autorizaciones']);
    $app->post('/autorizaciones/auditoria', [BeneficiosCtl::class, 'enviarAuditoria'], ['seccion' => 'autorizaciones']);
    $app->get('/auditoria', [BeneficiosCtl::class, 'auditoria'], ['seccion' => 'auditoria']);
    $app->get('/auditoria/{id}/decidir', [BeneficiosCtl::class, 'decidirForm'], ['admin' => true]);
    $app->post('/auditoria/{id}', [BeneficiosCtl::class, 'decidir'], ['roles' => ['admin', 'usuario']]);

    $app->get('/verificar', [BeneficiosCtl::class, 'verificarPantalla'], ['seccion' => 'verificar']);
    $app->get('/verificar/unidad', [BeneficiosCtl::class, 'verificarUnidad'], ['roles' => ['unidad']]);
    $app->post('/verificar/uso', [BeneficiosCtl::class, 'registrarUso'], ['roles' => ['unidad']]);
    $app->get('/verificar/comercio', [BeneficiosCtl::class, 'verificarComercio'], ['roles' => ['comercio']]);
    $app->post('/verificar/consumo', [BeneficiosCtl::class, 'registrarConsumo'], ['roles' => ['comercio']]);

    $app->get('/prestaciones', [BeneficiosCtl::class, 'usos'], ['seccion' => 'prestaciones']);
};
