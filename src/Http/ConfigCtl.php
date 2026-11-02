<?php
declare(strict_types=1);

namespace App\Http;

use App\Core\App;
use App\Core\AutorizacionAdmin;
use App\Core\Env;
use App\Core\Falta;
use App\Core\Historial;
use App\Core\HttpError;
use App\Core\Request;
use App\Core\Response;
use App\Core\Usuarios;
use App\Core\View;
use App\Domain\Caja;
use App\Domain\Catalogo;
use App\Domain\Configuracion;
use App\Domain\Fechas;
use App\Domain\Importador;

/** CU-39 a CU-52. */
final class ConfigCtl
{
    public function index(Request $r): Response
    {
        $u = App::$usuario;
        $vars = ['vista' => 'config', 'versiones' => Ingreso::versiones()];
        if (!$u['admin']) {
            return Response::html(View::render('config/usuario', $vars));
        }
        $db = App::$db;
        $vars += [
            'usuarios' => array_map([Usuarios::class, 'hidratar'], $db->all('SELECT u.*, un.nombre AS unidad FROM usuarios u LEFT JOIN unidades un ON un.id = u.unidad_id ORDER BY (u.rol = \'admin\') DESC, u.rol, u.nombre')),
            'prest' => Catalogo::prestaciones(false),
            'prestActivo' => array_column($db->all('SELECT cod, activo FROM prestaciones'), 'activo', 'cod'),
            'unidades' => Catalogo::unidades(true), 'verBajas' => $r->bool('bajas'),
            'usuariosUnidad' => $db->all("SELECT unidad_id, nombre, activo FROM usuarios WHERE rol = 'unidad'"),
            'comercios' => $db->all("SELECT comercio_rubro, comercio_nombre FROM usuarios WHERE rol = 'comercio'"),
            'cambios' => $db->all('SELECT * FROM config_cambios ORDER BY id DESC LIMIT 10'),
            'historial' => $db->all('SELECT * FROM historial ORDER BY id DESC LIMIT 60'),
            'backup' => $db->one('SELECT * FROM backups_estado WHERE ok ORDER BY fecha DESC LIMIT 1'),
            'backupFalla' => $db->one('SELECT * FROM backups_estado WHERE NOT ok ORDER BY fecha DESC LIMIT 1'),
            'proxB' => Caja::proximaFactura('B'), 'proxA' => Caja::proximaFactura('A'), 'demo' => Env::esDemo(), 'hoy' => Fechas::hoy(),
        ];
        return Response::html(View::render('config/admin', $vars));
    }

    // ------------------------------------------------------------------ bloques (C) con confirmación

    /** Calcula los cambios y devuelve el modal "Confirmá los cambios". */
    public function previa(Request $r): Response
    {
        $bloque = (string)$r->params['bloque'];
        $d = $r->post;
        unset($d['_csrf'], $d['_adm']);
        if (isset($d['json'])) {
            $d = json_decode((string)$d['json'], true) ?: [];
        }
        $prep = Configuracion::preparar($bloque, $d);
        if (!$prep['cambios']) {
            throw new Falta('No hubo cambios para guardar.');
        }
        return Response::json(['ok' => true, 'modalHtml' => View::parcial('config/confirmar', ['bloque' => $bloque, 'datos' => $d, 'prep' => $prep])]);
    }

    public function confirmar(Request $r): Response
    {
        $bloque = (string)$r->params['bloque'];
        $d = json_decode($r->str('datos'), true) ?: [];
        $msg = Configuracion::confirmar($bloque, $d, (string)$r->get('clave', ''));
        App::$sesion->flash($msg);
        return Response::json(['ok' => true, 'recargar' => true]);
    }

    public function deshacer(Request $r): Response
    {
        Configuracion::deshacer((int)$r->params['id']);
        App::$sesion->flash('Cambio deshecho.');
        return Response::json(['ok' => true, 'recargar' => true]);
    }

    // ------------------------------------------------------------------ CU-41 importar

    public function importarLeer(Request $r): Response
    {
        $f = $r->files['archivo'] ?? null;
        if (!$f || ($f['error'] ?? 1) !== UPLOAD_ERR_OK) {
            throw new Falta('Elegí el archivo con la lista de precios.');
        }
        $ext = strtolower(pathinfo((string)$f['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['pdf', 'xlsx', 'xls', 'ods', 'csv', 'txt'], true)) {
            throw new Falta('El archivo tiene que ser PDF, Excel (xlsx, xls), CSV o TXT.');
        }
        $imp = Importador::leer((string)$f['tmp_name'], (string)$f['name']);
        $imp['filas'] = Importador::relacionar($imp['reng'], 0, $imp['unidad']);
        return Response::json(['ok' => true, 'imp' => $imp]);
    }

    /** Recalcula las relaciones al cambiar la unidad o la columna de precio. */
    public function importarRelacionar(Request $r): Response
    {
        $reng = json_decode($r->str('reng'), true) ?: [];
        return Response::json(['ok' => true, 'filas' => Importador::relacionar($reng, $r->int('col'), $r->str('unidad', '*'))]);
    }

    // ------------------------------------------------------------------ CU-39 usuarios

    public function crearUsuario(Request $r): Response
    {
        Configuracion::crearUsuario($r->post);
        App::$sesion->flash('Usuario ' . $r->str('nombre') . ' creado. Va a tener que cambiar la clave al ingresar.');
        return Response::json(['ok' => true, 'recargar' => true]);
    }

    public function usuarioModal(Request $r): Response
    {
        $u = Usuarios::porId(App::$db, (int)$r->params['id']) ?? throw new HttpError(404);
        return Response::html(View::parcial('config/usuario_modal', ['u' => $u, 'soy' => $u['id'] === App::$usuario['id']]));
    }

    public function usuarioAccion(Request $r): Response
    {
        $msg = Configuracion::editarUsuario((int)$r->params['id'], (string)$r->params['accion'], $r->post);
        App::$sesion->flash($msg);
        return Response::json(['ok' => true, 'recargar' => true]);
    }

    // ------------------------------------------------------------------ CU-40 prestaciones (agregar)

    public function agregarPrestacion(Request $r): Response
    {
        Configuracion::agregarPrestacion($r->post);
        App::$sesion->flash('Prestación agregada. Cargá su valor y las reglas por plan.');
        return Response::json(['ok' => true, 'recargar' => true]);
    }

    // ------------------------------------------------------------------ CU-43 unidades

    public function crearUnidad(Request $r): Response
    {
        Configuracion::crearUnidad($r->post);
        App::$sesion->flash('Unidad ' . $r->str('nombre') . ' creada con su usuario (clave igual al nombre).');
        return Response::json(['ok' => true, 'recargar' => true]);
    }

    public function unidadModal(Request $r): Response
    {
        $u = Catalogo::unidadPorId((int)$r->params['id']) ?? throw new HttpError(404);
        $us = App::$db->one("SELECT * FROM usuarios WHERE unidad_id = :u AND rol = 'unidad' ORDER BY id LIMIT 1", ['u' => $u['id']]);
        $conUsos = (bool)App::$db->val('SELECT 1 FROM usos WHERE unidad_id = :u LIMIT 1', ['u' => $u['id']]);
        return Response::html(View::parcial('config/unidad_modal', ['u' => $u, 'us' => $us, 'conUsos' => $conUsos]));
    }

    public function unidadAccion(Request $r): Response
    {
        $id = (int)$r->params['id'];
        $msg = match ((string)$r->params['accion']) {
            'editar' => (Configuracion::editarUnidad($id, $r->post) ?? 'Unidad actualizada.'),
            'baja' => (Configuracion::estadoUnidad($id, false) ?? 'Unidad dada de baja: ya no puede ingresar ni verificar afiliados.'),
            'reactivar' => (Configuracion::estadoUnidad($id, true) ?? 'Unidad reactivada.'),
            'eliminar' => (Configuracion::eliminarUnidad($id) ?? 'Unidad eliminada.'),
            default => throw new HttpError(404),
        };
        App::$sesion->flash($msg);
        return Response::json(['ok' => true, 'recargar' => true]);
    }

    // ------------------------------------------------------------------ CU-50 copias de seguridad

    /**
     * Descarga una copia completa en JSON (P-13: se mantiene para administradores).
     * La restauración se hace por consola (docs/OPERACION.md), no desde la pantalla.
     */
    public function copia(Request $r): Response
    {
        $db = App::$db;
        $tablas = ['unidades', 'planes', 'categorias', 'precios', 'prestaciones', 'reglas_beneficio', 'rubros_comercio', 'config', 'grupos', 'afiliados',
            'facturas', 'recibos', 'autorizaciones', 'usos', 'usos_lineas', 'consumos_comercio', 'consultas', 'promociones', 'promociones_envios',
            'retenciones', 'saludos_cumple', 'resumenes_enviados', 'egresos'];
        $out = ['sistema' => 'Integral del Este', 'fecha' => date('c'), 'por' => App::$usuario['nombre'], 'tablas' => []];
        foreach ($tablas as $t) {
            $out['tablas'][$t] = $db->all("SELECT * FROM $t");
        }
        $out['tablas']['usuarios'] = $db->all('SELECT id, nombre, rol, activo, secciones, unidad_id, comercio_nombre, comercio_rubro, creado FROM usuarios');
        $db->insert('backups_estado', ['ok' => true, 'origen' => 'descarga', 'detalle' => 'Descarga manual desde Configuración', 'usuario' => App::$usuario['nombre']]);
        Historial::log($db, 'Copias', 'Descarga de una copia completa de los datos');
        return Response::archivo(json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), 'Integral del Este - copia ' . date('Y-m-d H-i') . '.json', 'application/json');
    }

    // ------------------------------------------------------------------ CU-51 volver a cargar el padrón (solo demo)

    public function padron(Request $r): Response
    {
        if (!Env::esDemo()) {
            throw new HttpError(403, 'Volver a cargar el padrón solo está habilitado en el ambiente de demostración (S-03).');
        }
        AutorizacionAdmin::exigir('padron');
        if (!\App\Core\Usuarios::verificar(App::$usuario, (string)$r->get('clave', ''))) {
            throw new Falta('La clave no es correcta.');
        }
        $sql = (string)file_get_contents(dirname(__DIR__, 2) . '/database/011_padron_inicial.sql');
        $sql = substr($sql, (int)strpos($sql, '-- Grupos de pago y afiliados'));
        $sql = substr($sql, 0, (int)strpos($sql, 'INSERT INTO historial'));
        App::$db->tx(function () use ($sql) {
            foreach (['usos_lineas', 'usos', 'autorizaciones', 'consumos_comercio', 'recibos', 'facturas', 'retenciones', 'saludos_cumple', 'resumenes_enviados', 'promociones_envios', 'consultas', 'afiliados', 'grupos', 'cola_tareas'] as $t) {
                App::$db->exec("DELETE FROM $t");
            }
            App::$db->exec('DELETE FROM numeradores');
            App::$db->pdo->exec($sql);
            App::$db->exec("DELETE FROM config WHERE clave = 'ultima_emision'");
            Historial::log(App::$db, 'Padrón', 'Se volvió a cargar el padrón inicial: 24 afiliados al día al 31/10/2026, sin facturas ni cobros');
        });
        App::$sesion->flash('Padrón inicial cargado de nuevo.');
        return Response::json(['ok' => true, 'redirect' => '/afiliados']);
    }
}
