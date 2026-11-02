<?php
declare(strict_types=1);

namespace App\Http;

use App\Core\App;
use App\Core\AutorizacionAdmin;
use App\Core\Env;
use App\Core\Falta;
use App\Core\Historial;
use App\Core\Request;
use App\Core\Response;
use App\Core\Usuarios;
use App\Core\View;
use App\Domain\CatalogoMaqueta;
use App\Domain\Fmt;
use App\Domain\Permisos;

/** CU-01 a CU-04 y CU-52 (versiones). */
final class Ingreso
{
    public const MAX_INTENTOS_USUARIO = 5;
    public const MAX_INTENTOS_IP = 15;

    public function form(Request $r): Response
    {
        if (App::$usuario) {
            return Response::redirect('/' . Permisos::inicio(App::$usuario));
        }
        $msg = $r->str('vencida') ? 'Se cerró la sesión por inactividad. Volvé a ingresar.' : '';
        return Response::html(View::render('ingreso', ['msg' => $msg], 'layout_simple'));
    }

    public function entrar(Request $r): Response
    {
        $db = App::$db;
        $nombre = $r->str('usuario');
        $clave = (string)$r->get('clave', '');
        $norm = Fmt::norm($nombre);
        $ip = $r->ip();
        if ($nombre === '') {
            throw new Falta('Escribí tu usuario.', ['usuario']);
        }
        $fallos = (int)$db->val("SELECT count(*) FROM intentos_ingreso WHERE usuario_norm = :u AND NOT ok AND fecha > now() - interval '15 minutes'", ['u' => $norm]);
        $fallosIp = (int)$db->val("SELECT count(*) FROM intentos_ingreso WHERE ip = :ip AND NOT ok AND fecha > now() - interval '15 minutes'", ['ip' => $ip]);
        if ($fallos >= self::MAX_INTENTOS_USUARIO || $fallosIp >= self::MAX_INTENTOS_IP) {
            throw new Falta('Demasiados intentos fallidos. Esperá 15 minutos o pedile a un administrador que te ponga una clave nueva.');
        }
        $u = Usuarios::porNombre($db, $nombre);
        $fallo = function (string $m, array $campos) use ($db, $norm, $ip, $fallos) {
            $db->q('INSERT INTO intentos_ingreso (usuario_norm, ip, ok) VALUES (:u, :ip, false)', ['u' => $norm, 'ip' => $ip]);
            if ($fallos >= 2 && !Env::esPrueba()) {
                usleep(min(4, $fallos) * 400000); // espera creciente
            }
            throw new Falta($m, $campos);
        };
        if (!$u) {
            $fallo('No existe el usuario “' . $nombre . '”. Revisá cómo está escrito.', ['usuario']);
        }
        if (!Usuarios::verificar($u, $clave)) {
            $fallo("La clave de {$u['nombre']} no es correcta. Si la cambiaste y no te acordás, pedile a otro administrador que te ponga una nueva (Configuración → Usuarios).", ['clave']);
        }
        if (!$u['activo'] || ($u['rol'] === 'unidad' && !($u['unidad_activa'] ?? true))) {
            $fallo('Este usuario está dado de baja. Hablá con un administrador.', ['usuario']);
        }
        $db->q('INSERT INTO intentos_ingreso (usuario_norm, ip, ok) VALUES (:u, :ip, true)', ['u' => $norm, 'ip' => $ip]);
        $tok = App::$sesion->abrir($u);
        App::$usuario = $u;
        Historial::log($db, 'Sesión', Usuarios::visible($u) . ' ingresó al sistema', Usuarios::visible($u));
        if ($u['admin']) {
            $n = (int)$db->val("SELECT count(*) FROM autorizaciones WHERE estado = 'pendiente'");
            if ($n) {
                App::$sesion->guardarDato('aviso_auditoria', $n);
            }
        }
        $destino = $u['debe_cambiar_clave'] ? '/mi-clave' : '/' . Permisos::inicio($u);
        // Usuarios recordados en el equipo: se muestran las unidades, no los comercios (CU-01).
        $recordar = $u['rol'] === 'comercio' ? null : ['nombre' => $u['nombre'], 'etiqueta' => $u['rol'] === 'unidad' ? 'unidad ' . $u['unidad_nombre'] : ''];
        $res = Response::json(['ok' => true, 'redirect' => $destino, 'recordar' => $recordar]);
        $res->headers['Set-Cookie'] = App::$sesion->cookieHeader($tok);
        return $res;
    }

    public function salir(Request $r): Response
    {
        if (App::$usuario) {
            Historial::log(App::$db, 'Sesión', Usuarios::visible(App::$usuario) . ' salió del sistema');
        }
        App::$sesion->cerrar();
        $res = $r->esFetch() ? Response::json(['ok' => true, 'redirect' => '/ingreso']) : Response::redirect('/ingreso');
        $res->headers['Set-Cookie'] = App::$sesion->cookieHeader(null);
        return $res;
    }

    public function formClave(Request $r): Response
    {
        return Response::html(View::render('mi_clave', ['vista' => 'config', 'obligatorio' => App::$usuario['debe_cambiar_clave']]));
    }

    /** CU-03 */
    public function cambiarClave(Request $r): Response
    {
        $u = App::$usuario;
        $actual = (string)$r->get('actual', '');
        $k1 = (string)$r->get('nueva', '');
        $k2 = (string)$r->get('repetir', '');
        if (!Usuarios::verificar($u, $actual)) {
            throw new Falta('La clave actual no es correcta.', ['actual']);
        }
        if ($k1 !== $k2) {
            throw new Falta('Las claves nuevas no coinciden.', ['nueva', 'repetir']);
        }
        if (mb_strlen(trim($k1)) < 4) {
            throw new Falta('La clave tiene que tener al menos 4 caracteres.', ['nueva']);
        }
        if ($u['debe_cambiar_clave'] && Fmt::norm($k1) === Fmt::norm($u['nombre'])) {
            throw new Falta('La clave nueva no puede ser igual a tu nombre de usuario.', ['nueva']);
        }
        App::$db->exec('UPDATE usuarios SET clave_hash = :h, debe_cambiar_clave = false WHERE id = :id', ['h' => Usuarios::hash($k1), 'id' => $u['id']]);
        // Las otras sesiones del mismo usuario se cierran.
        App::$db->exec('DELETE FROM sesiones WHERE usuario_id = :u AND id <> :id', ['u' => $u['id'], 'id' => App::$sesion->fila['id']]);
        Historial::log(App::$db, 'Usuarios', Usuarios::visible($u) . ' cambió su clave');
        App::$sesion->flash('Clave cambiada.');
        return Response::json(['ok' => true, 'redirect' => '/' . Permisos::inicio($u)]);
    }

    /** CU-04: emite el token de autorización de un administrador. */
    public function autorizar(Request $r): Response
    {
        $accion = $r->str('accion');
        if (!isset(Permisos::ACCIONES[$accion])) {
            throw new Falta('Acción desconocida.');
        }
        $tok = AutorizacionAdmin::emitir($r->str('usuario'), (string)$r->get('clave', ''), $accion);
        return Response::json(['ok' => true, 'token' => $tok]);
    }

    /** Estado de la sesión para el aviso de inactividad (no cuenta como actividad). */
    public function ping(Request $r): Response
    {
        $f = App::$sesion->fila;
        $min = \App\Core\Sesion::minutosInactividad();
        $resta = $min * 60 - (int)($f['inactivo'] ?? 0);
        return Response::json(['ok' => true, 'resta' => max(0, $resta)]);
    }

    public function raiz(Request $r): Response
    {
        return Response::redirect(App::$usuario ? '/' . Permisos::inicio(App::$usuario) : '/ingreso');
    }

    public function inicio(Request $r): Response
    {
        if (App::$usuario['admin']) {
            return Response::redirect('/afiliados');
        }
        $ops = array_values(array_filter(['altas', 'cotizador', 'autorizaciones', 'beneficios', 'retencion'], fn($id) => Permisos::puedeVer(App::$usuario, $id)));
        return Response::html(View::render('inicio', ['vista' => 'inicio', 'ops' => $ops, 'textos' => CatalogoMaqueta::INICIO_TXT]));
    }

    public function novedades(Request $r): Response
    {
        return Response::html(View::parcial('parciales/novedades', ['versiones' => self::versiones()]));
    }

    /** Versiones del sistema: las del servidor más el historial de la maqueta. */
    public static function versiones(): array
    {
        return array_merge(\App\Domain\Version::LISTA, CatalogoMaqueta::VERSIONES);
    }

    public function salud(Request $r): Response
    {
        try {
            App::$db->val('SELECT 1');
            return Response::json(['ok' => true, 'version' => \App\Domain\Version::actual()['v']]);
        } catch (\Throwable $e) {
            return Response::json(['ok' => false], 503);
        }
    }
}
