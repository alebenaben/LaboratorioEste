<?php
declare(strict_types=1);

namespace App\Core;

use App\Domain\Fmt;

/**
 * Sesión del lado del servidor (Arquitectura, sección 6): cookie HttpOnly,
 * Secure y SameSite=Strict; se cierra a los 15 minutos sin actividad.
 */
final class Sesion
{
    public const COOKIE = 'integral_sid';
    public ?array $usuario = null;
    public ?array $fila = null;
    public bool $vencida = false;
    private ?string $token = null;

    public function __construct(private Db $db, private Request $req)
    {
    }

    public static function minutosInactividad(): int
    {
        return Env::int('SESSION_IDLE_MINUTES', 15);
    }

    /** Carga la sesión desde la cookie. Si venció por inactividad la cierra y lo registra (CU-02 A1). */
    public function cargar(): void
    {
        $tok = $this->req->cookies[self::COOKIE] ?? '';
        if (!is_string($tok) || strlen($tok) < 32) {
            return;
        }
        $id = hash('sha256', $tok);
        $f = $this->db->one(
            "SELECT s.*, extract(epoch FROM now() - s.ultima_actividad) AS inactivo FROM sesiones s WHERE s.id = :id",
            ['id' => $id]
        );
        if (!$f) {
            return;
        }
        $u = Usuarios::porId($this->db, (int)$f['usuario_id']);
        if (!$u || !$u['activo'] || (float)$f['inactivo'] > self::minutosInactividad() * 60) {
            $this->db->exec('DELETE FROM sesiones WHERE id = :id', ['id' => $id]);
            if ($u && $u['activo']) {
                Historial::log($this->db, 'Sesión', "Sesión de {$u['nombre']} cerrada por inactividad", $u['nombre']);
            }
            $this->vencida = true;
            return;
        }
        $this->token = $tok;
        $this->fila = $f;
        $this->fila['datos'] = json_decode((string)$f['datos'], true) ?: [];
        $this->usuario = $u;
    }

    /** Registra actividad (cada pedido del usuario, no los sondeos automáticos). */
    public function tocar(): void
    {
        if ($this->fila) {
            $this->db->exec('UPDATE sesiones SET ultima_actividad = now() WHERE id = :id', ['id' => $this->fila['id']]);
        }
    }

    public function abrir(array $usuario): string
    {
        $tok = bin2hex(random_bytes(32));
        $csrf = bin2hex(random_bytes(24));
        $id = hash('sha256', $tok);
        $this->db->insert('sesiones', [
            'id' => $id, 'usuario_id' => $usuario['id'], 'csrf' => $csrf,
            'ip' => $this->req->ip(), 'user_agent' => substr((string)($this->req->server['HTTP_USER_AGENT'] ?? ''), 0, 250),
        ], 'id');
        $this->token = $tok;
        $this->usuario = $usuario;
        $this->fila = ['id' => $id, 'csrf' => $csrf, 'datos' => []];
        return $tok;
    }

    public function cerrar(): void
    {
        if ($this->fila) {
            $this->db->exec('DELETE FROM sesiones WHERE id = :id', ['id' => $this->fila['id']]);
        }
        $this->fila = null;
        $this->usuario = null;
        $this->token = null;
    }

    public function token(): ?string
    {
        return $this->token;
    }

    public function csrf(): string
    {
        return (string)($this->fila['csrf'] ?? '');
    }

    public function cookieHeader(?string $valor): string
    {
        $seg = $this->req->esHttps() || Env::bool('COOKIE_SECURE', false);
        $partes = [self::COOKIE . '=' . ($valor ?? 'x'), 'Path=/', 'HttpOnly', 'SameSite=Strict'];
        if ($seg) {
            $partes[] = 'Secure';
        }
        if ($valor === null) {
            $partes[] = 'Max-Age=0';
        }
        return implode('; ', $partes);
    }

    // ---- datos de la sesión (avisos de una vez) ----
    public function dato(string $k, mixed $def = null): mixed
    {
        return $this->fila['datos'][$k] ?? $def;
    }

    public function guardarDato(string $k, mixed $v): void
    {
        if (!$this->fila) {
            return;
        }
        if ($v === null) {
            unset($this->fila['datos'][$k]);
        } else {
            $this->fila['datos'][$k] = $v;
        }
        $this->db->exec('UPDATE sesiones SET datos = :d WHERE id = :id', ['d' => json_encode($this->fila['datos'], JSON_UNESCAPED_UNICODE), 'id' => $this->fila['id']]);
    }

    public function flash(string $msg, string $tipo = 'ok'): void
    {
        $f = $this->dato('flash', []);
        $f[] = ['m' => $msg, 't' => $tipo];
        $this->guardarDato('flash', $f);
    }

    public function tomarFlash(): array
    {
        $f = $this->dato('flash', []);
        if ($f) {
            $this->guardarDato('flash', null);
        }
        return $f;
    }
}

/** Usuarios del sistema. */
final class Usuarios
{
    public static function hidratar(?array $u): ?array
    {
        if (!$u) {
            return null;
        }
        $u['id'] = (int)$u['id'];
        $u['activo'] = (bool)$u['activo'];
        $u['secciones'] = Db::leerArray($u['secciones'] ?? '{}');
        $u['debe_cambiar_clave'] = (bool)($u['debe_cambiar_clave'] ?? false);
        $u['admin'] = $u['rol'] === 'admin';
        return $u;
    }

    public static function porId(Db $db, int $id): ?array
    {
        return self::hidratar($db->one(
            'SELECT u.*, un.nombre AS unidad, un.nombre_largo AS unidad_nombre, un.activo AS unidad_activa
             FROM usuarios u LEFT JOIN unidades un ON un.id = u.unidad_id WHERE u.id = :id',
            ['id' => $id]
        ));
    }

    public static function porNombre(Db $db, string $nombre): ?array
    {
        $r = $db->one('SELECT id FROM usuarios WHERE nombre_norm = :n', ['n' => Fmt::norm($nombre)]);
        return $r ? self::porId($db, (int)$r['id']) : null;
    }

    public static function hash(string $clave): string
    {
        return password_hash($clave, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT);
    }

    public static function verificar(array $u, string $clave): bool
    {
        return password_verify($clave, (string)$u['clave_hash']);
    }

    /** Nombre visible: el comercio usa su nombre comercial. */
    public static function visible(array $u): string
    {
        return $u['rol'] === 'comercio' ? (string)$u['comercio_nombre'] : (string)$u['nombre'];
    }
}

/** RT-07: historial de cambios (solo agregado). */
final class Historial
{
    public static function log(Db $db, string $accion, string $detalle, ?string $usuario = null, string $autorizo = ''): void
    {
        $usuario ??= App::$usuario ? Usuarios::visible(App::$usuario) : 'sistema';
        $autorizo = $autorizo ?: (App::$autorizo ?? '');
        $db->q('INSERT INTO historial (accion, detalle, usuario, autorizo) VALUES (:a, :d, :u, :au)', ['a' => $accion, 'd' => $detalle, 'u' => $usuario, 'au' => $autorizo]);
    }
}
