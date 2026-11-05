<?php
declare(strict_types=1);

/**
 * Chequeo de salud del contenedor (HEALTHCHECK del Dockerfile).
 *
 *   - Servicio web (app): consulta http://127.0.0.1/salud; sale con 1 si no responde.
 *   - Worker (SERVICIO=worker): no tiene servidor web, así que sale con 0. Sin esto,
 *     Docker Swarm (EasyPanel) lo marcaría "unhealthy" y lo reiniciaría sin parar.
 */

if (getenv('SERVICIO') === 'worker') {
    exit(0);
}

$ctx = stream_context_create(['http' => ['timeout' => 4]]);
exit(@file_get_contents('http://127.0.0.1/salud', false, $ctx) === false ? 1 : 0);
