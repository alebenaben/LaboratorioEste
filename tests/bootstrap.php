<?php
declare(strict_types=1);

/**
 * Prepara la base de pruebas integral_test: la borra y la vuelve a crear con los
 * scripts de database/ y los lotes de database/lotes (php bin/migrar.php --reiniciar --lotes).
 *
 * Necesita las mismas variables que el migrador (DB_HOST, DB_OWNER_USER, DB_OWNER_PASSWORD,
 * DB_PASSWORD, DB_BACKUP_PASSWORD), tomadas del entorno o de .env. Ver docs/PLAN_DE_PRUEBAS.md.
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\App;
use App\Core\Db;
use App\Core\Env;

$raiz = dirname(__DIR__);
Env::cargar($raiz . '/.env');
date_default_timezone_set(Env::get('TZ', 'America/Argentina/Buenos_Aires'));

$base = Env::get('DB_NAME', 'integral_test');
if (!str_ends_with((string)$base, '_test')) {
    fwrite(STDERR, "Las pruebas solo corren contra una base cuyo nombre termina en _test (ahora: $base).\n");
    exit(1);
}

// Crea la base de pruebas si no existe (el dueño necesita permiso CREATEDB).
try {
    $adm = new PDO('pgsql:host=' . Env::get('DB_HOST', '127.0.0.1') . ';port=' . Env::get('DB_PORT', '5432') . ';dbname=postgres', Env::get('DB_OWNER_USER', 'integral'), Env::get('DB_OWNER_PASSWORD', ''));
    if (!$adm->query("SELECT 1 FROM pg_database WHERE datname = " . $adm->quote($base))->fetchColumn()) {
        $adm->exec('CREATE DATABASE "' . $base . '"');
    }
} catch (Throwable $e) {
    fwrite(STDERR, "No se pudo preparar la base de pruebas: {$e->getMessage()}\n");
    exit(1);
}

$cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($raiz . '/bin/migrar.php') . ' --reiniciar --lotes 2>&1';
exec($cmd, $out, $rc);
if ($rc !== 0) {
    fwrite(STDERR, "Falló la creación de la base de pruebas:\n" . implode("\n", $out) . "\n");
    exit(1);
}

App::$db = Db::conectar();
