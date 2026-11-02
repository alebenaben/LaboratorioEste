<?php
declare(strict_types=1);

use App\Core\App;
use App\Http\ConfigCtl;

/** Rutas de Configuración (CU-39 a CU-52). */
return function (App $app): void {
    $a = ['admin' => true];
    $app->get('/config', [ConfigCtl::class, 'index'], ['seccion' => 'config']);
    $app->post('/config/importar/leer', [ConfigCtl::class, 'importarLeer'], $a);
    $app->post('/config/importar/relacionar', [ConfigCtl::class, 'importarRelacionar'], $a);
    $app->post('/config/prestaciones/nueva', [ConfigCtl::class, 'agregarPrestacion'], $a);
    $app->post('/config/usuarios', [ConfigCtl::class, 'crearUsuario'], $a);
    $app->get('/config/usuarios/{id}', [ConfigCtl::class, 'usuarioModal'], $a);
    $app->post('/config/usuarios/{id}/{accion}', [ConfigCtl::class, 'usuarioAccion'], $a);
    $app->post('/config/unidades', [ConfigCtl::class, 'crearUnidad'], $a);
    $app->get('/config/unidades/{id}', [ConfigCtl::class, 'unidadModal'], $a);
    $app->post('/config/unidades/{id}/{accion}', [ConfigCtl::class, 'unidadAccion'], $a);
    $app->post('/config/deshacer/{id}', [ConfigCtl::class, 'deshacer'], $a);
    $app->get('/config/copia', [ConfigCtl::class, 'copia'], $a);
    $app->post('/config/padron', [ConfigCtl::class, 'padron'], $a);
    $app->post('/config/{bloque}/previa', [ConfigCtl::class, 'previa'], $a);
    $app->post('/config/{bloque}/confirmar', [ConfigCtl::class, 'confirmar'], $a);
};
