<?php
declare(strict_types=1);

/**
 * Crea o actualiza la base de datos aplicando los scripts de database/ en orden.
 *
 *   php bin/migrar.php               aplica los scripts pendientes
 *   php bin/migrar.php --lotes       además carga los lotes de prueba (database/lotes)
 *   php bin/migrar.php --reiniciar   BORRA todo y vuelve a crear (solo APP_ENV=demo|test|desarrollo)
 *   php bin/migrar.php --estado      muestra qué scripts están aplicados
 *
 * Se conecta como el dueño del esquema (DB_OWNER_USER / DB_OWNER_PASSWORD[_FILE]).
 * Las claves de integral_app e integral_backup se toman de DB_PASSWORD[_FILE]
 * y DB_BACKUP_PASSWORD[_FILE].
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Env;

Env::cargar(dirname(__DIR__) . '/.env');
$args = array_slice($argv, 1);
$dir = dirname(__DIR__) . '/database';

$owner = Env::get('DB_OWNER_USER', 'integral');
$ownerPass = Env::get('DB_OWNER_PASSWORD', '');
$host = Env::get('DB_HOST', '127.0.0.1');
$port = Env::get('DB_PORT', '5432');
$base = Env::get('DB_NAME', 'integral');

$intentos = 0;
while (true) {
    try {
        $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$base", $owner, $ownerPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        break;
    } catch (PDOException $e) {
        if (++$intentos >= 15) {
            fwrite(STDERR, "No se pudo conectar a la base: {$e->getMessage()}\n");
            exit(1);
        }
        sleep(2);
    }
}
$pdo->exec("SET TIME ZONE '" . (Env::get('TZ') ?: 'America/Argentina/Buenos_Aires') . "'");

if (in_array('--reiniciar', $args, true)) {
    $env = Env::get('APP_ENV', 'produccion');
    if (!in_array($env, ['demo', 'test', 'desarrollo'], true)) {
        fwrite(STDERR, "--reiniciar borra todos los datos: solo se permite con APP_ENV=demo, test o desarrollo (ahora: $env).\n");
        exit(1);
    }
    $pdo->exec('DROP SCHEMA IF EXISTS public CASCADE; CREATE SCHEMA public;');
    echo "Esquema borrado.\n";
}

$pdo->exec('CREATE TABLE IF NOT EXISTS schema_migraciones (archivo text PRIMARY KEY, aplicada_en timestamptz NOT NULL DEFAULT now())');
$hechas = $pdo->query('SELECT archivo FROM schema_migraciones')->fetchAll(PDO::FETCH_COLUMN);

$archivos = glob("$dir/[0-9][0-9][0-9]_*.sql") ?: [];
sort($archivos);

if (in_array('--estado', $args, true)) {
    foreach ($archivos as $f) {
        printf("%-32s %s\n", basename($f), in_array(basename($f), $hechas, true) ? 'aplicado' : 'PENDIENTE');
    }
    exit(0);
}

$appPass = Env::get('DB_PASSWORD', '');
$bkPass = Env::get('DB_BACKUP_PASSWORD', '') ?: $appPass;
$aplicadas = 0;
foreach ($archivos as $f) {
    $nombre = basename($f);
    if (in_array($nombre, $hechas, true)) {
        continue;
    }
    $sql = (string)file_get_contents($f);
    if (str_contains($sql, '__APP_PASSWORD__')) {
        if ($appPass === '') {
            fwrite(STDERR, "Falta DB_PASSWORD (o DB_PASSWORD_FILE) para crear el rol integral_app.\n");
            exit(1);
        }
        $sql = str_replace(['__APP_PASSWORD__', '__BACKUP_PASSWORD__'], [str_replace("'", "''", $appPass), str_replace("'", "''", $bkPass)], $sql);
    }
    echo "Aplicando {$nombre}… ";
    $pdo->beginTransaction();
    try {
        $pdo->exec($sql);
        $st = $pdo->prepare('INSERT INTO schema_migraciones (archivo) VALUES (?)');
        $st->execute([$nombre]);
        $pdo->commit();
        echo "listo\n";
        $aplicadas++;
    } catch (Throwable $e) {
        $pdo->rollBack();
        echo "ERROR\n";
        fwrite(STDERR, $e->getMessage() . "\n");
        exit(1);
    }
}
echo $aplicadas ? "Se aplicaron $aplicadas script(s).\n" : "La base ya estaba al día.\n";

if (in_array('--lotes', $args, true)) {
    $lotes = glob("$dir/lotes/L[0-9][0-9]_*.sql") ?: [];
    sort($lotes);
    foreach ($lotes as $f) {
        echo 'Cargando lote ' . basename($f) . '… ';
        $pdo->beginTransaction();
        try {
            $pdo->exec((string)file_get_contents($f));
            $pdo->commit();
            echo "listo\n";
        } catch (Throwable $e) {
            $pdo->rollBack();
            echo "ERROR\n";
            fwrite(STDERR, $e->getMessage() . "\n");
            exit(1);
        }
    }
}
