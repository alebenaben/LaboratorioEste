<?php
declare(strict_types=1);

/**
 * Crea un administrador o le pone una clave nueva (primer ingreso o si nadie puede entrar).
 *   php bin/crear-admin.php <usuario> <clave>
 *   docker compose run --rm migrar php bin/crear-admin.php Santiago 'ClaveSegura123'
 * La clave queda marcada para cambiarse en el próximo ingreso.
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\App;
use App\Core\Db;
use App\Core\Env;
use App\Core\Historial;
use App\Core\Usuarios;
use App\Domain\Fmt;
use App\Domain\Permisos;

Env::cargar(dirname(__DIR__) . '/.env');
[$_, $nombre, $clave] = array_pad($argv, 3, null);
if (!$nombre || !$clave || mb_strlen($clave) < 4) {
    fwrite(STDERR, "Uso: php bin/crear-admin.php <usuario> <clave de 4 o más caracteres>\n");
    exit(1);
}
$db = Env::get('DB_OWNER_PASSWORD') ? Db::conectar(Env::get('DB_OWNER_USER', 'integral'), Env::get('DB_OWNER_PASSWORD')) : Db::conectar();
App::$db = $db;
$u = Usuarios::porNombre($db, $nombre);
if ($u) {
    $db->exec("UPDATE usuarios SET rol = 'admin', activo = true, clave_hash = :h, debe_cambiar_clave = true, secciones = :s WHERE id = :id",
        ['h' => Usuarios::hash($clave), 's' => Db::arrayPg(array_column(Permisos::VISTAS, 0)), 'id' => $u['id']]);
    Historial::log($db, 'Usuarios', "Clave de administrador de {$u['nombre']} puesta desde la consola", 'consola');
    echo "Listo: {$u['nombre']} es administrador y tiene clave nueva (la cambia al ingresar).\n";
} else {
    $db->insert('usuarios', ['nombre' => $nombre, 'nombre_norm' => Fmt::norm($nombre), 'clave_hash' => Usuarios::hash($clave), 'rol' => 'admin', 'secciones' => Db::arrayPg(array_column(Permisos::VISTAS, 0)), 'debe_cambiar_clave' => true]);
    Historial::log($db, 'Usuarios', "Alta del administrador $nombre desde la consola", 'consola');
    echo "Listo: se creó el administrador $nombre (cambia la clave al ingresar).\n";
}
