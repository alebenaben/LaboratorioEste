<?php
declare(strict_types=1);

/**
 * Worker de Integral del Este: CAE de ARCA y envío de mails.
 *   php bin/worker.php           corre en loop (servicio "worker" de Docker Compose)
 *   php bin/worker.php --una-vez procesa lo pendiente y termina (útil para pruebas o cron)
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\App;
use App\Core\Db;
use App\Core\Env;
use App\Service\Worker;

Env::cargar(dirname(__DIR__) . '/.env');
date_default_timezone_set(Env::get('TZ', 'America/Argentina/Buenos_Aires'));
App::$db = Db::conectar();
$w = new Worker();
if (in_array('--una-vez', $argv, true)) {
    $n = $w->procesar(1000);
    echo "Tareas procesadas: $n\n";
    exit(0);
}
$w->correr();
