<?php
declare(strict_types=1);

use App\Core\App;
use App\Http;

/**
 * Rutas de la aplicación. Opciones:
 *   seccion   la pantalla que exige (Permisos::puedeVer)
 *   admin     solo administradores
 *   roles     roles permitidos
 *   publica   no exige sesión
 */
return function (App $app): void {
    // Acceso y sesión (CU-01 a CU-04)
    $app->get('/', [Http\Ingreso::class, 'raiz'], ['publica' => true]);
    $app->get('/salud', [Http\Ingreso::class, 'salud'], ['publica' => true]);
    $app->get('/ingreso', [Http\Ingreso::class, 'form'], ['publica' => true]);
    $app->post('/ingreso', [Http\Ingreso::class, 'entrar'], ['publica' => true]);
    $app->post('/salir', [Http\Ingreso::class, 'salir'], ['libre_clave' => true]);
    $app->get('/mi-clave', [Http\Ingreso::class, 'formClave'], ['libre_clave' => true]);
    $app->post('/mi-clave', [Http\Ingreso::class, 'cambiarClave'], ['libre_clave' => true]);
    $app->post('/api/autorizar', [Http\Ingreso::class, 'autorizar']);
    $app->get('/api/ping', [Http\Ingreso::class, 'ping'], ['sin_actividad' => true, 'libre_clave' => true]);
    $app->post('/api/actividad', fn() => \App\Core\Response::json(['ok' => true]), ['libre_clave' => true]);
    $app->get('/inicio', [Http\Ingreso::class, 'inicio'], ['seccion' => 'inicio']);
    $app->get('/novedades', [Http\Ingreso::class, 'novedades'], ['libre_clave' => true]);

    foreach (glob(__DIR__ . '/Http/rutas_*.php') ?: [] as $f) {
        (require $f)($app);
    }
};
