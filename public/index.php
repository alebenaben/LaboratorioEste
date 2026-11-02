<?php
declare(strict_types=1);

/**
 * Integral del Este — punto de entrada web.
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\App;
use App\Core\Env;
use App\Core\Request;

Env::cargar(dirname(__DIR__) . '/.env');
date_default_timezone_set(Env::get('TZ', 'America/Argentina/Buenos_Aires'));

// Archivos estáticos cuando se usa el servidor incorporado de PHP (desarrollo).
if (PHP_SAPI === 'cli-server') {
    $f = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file($f) && !str_ends_with($f, '.php')) {
        return false;
    }
}

$req = Request::desdeGlobales();
$app = new App();
(require dirname(__DIR__) . '/src/rutas.php')($app);
$res = $app->manejar($req);

// Cabeceras de seguridad (el resto las agrega Caddy).
$res->headers += [
    'Content-Security-Policy' => "default-src 'self'; img-src 'self' data: blob:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; script-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'",
    'X-Content-Type-Options' => 'nosniff',
    'Referrer-Policy' => 'strict-origin-when-cross-origin',
    'X-Frame-Options' => 'DENY',
];
if (App::$sesion && App::$sesion->token() && !isset($res->headers['Set-Cookie'])) {
    $res->headers['Set-Cookie'] = App::$sesion->cookieHeader(App::$sesion->token());
}
$res->send();
