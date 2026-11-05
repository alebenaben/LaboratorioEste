<?php
declare(strict_types=1);

/**
 * Imprime, como líneas "export X='valor'" para sh, las variables de conexión ya resueltas
 * (DATABASE_URL, *_FILE y claves derivadas; ver App\Core\Env). Lo usan el arranque del
 * contenedor y el comando "integral":
 *
 *   eval "$(php bin/entorno.php)"
 *
 * Termina con error si no hay datos para conectarse a la base.
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Env;

Env::cargar(dirname(__DIR__) . '/.env');

$vars = [
    'DB_HOST' => Env::get('DB_HOST'),
    'DB_PORT' => Env::get('DB_PORT', '5432'),
    'DB_NAME' => Env::get('DB_NAME', 'integral'),
    'DB_USER' => Env::get('DB_USER', 'integral_app'),
    'DB_PASSWORD' => Env::get('DB_PASSWORD'),
    'DB_BACKUP_PASSWORD' => Env::get('DB_BACKUP_PASSWORD'),
    'DB_OWNER_USER' => Env::get('DB_OWNER_USER', 'integral'),
    'DB_OWNER_PASSWORD' => Env::get('DB_OWNER_PASSWORD'),
];

$faltan = array_keys(array_filter(['DB_HOST' => $vars['DB_HOST'], 'DB_PASSWORD' => $vars['DB_PASSWORD']], fn($v) => $v === null));
if ($faltan) {
    fwrite(STDERR, "Faltan los datos de la base de datos (" . implode(', ', $faltan) . ").\n"
        . "Definí DATABASE_URL=postgres://usuario:clave@host:5432/base (en EasyPanel: la \"Internal connection URL\" del servicio Postgres).\n");
    exit(1);
}

foreach ($vars as $k => $v) {
    if ($v !== null) {
        echo "export $k='" . str_replace("'", "'\\''", $v) . "'\n";
    }
}
