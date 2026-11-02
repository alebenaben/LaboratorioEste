<?php
declare(strict_types=1);

namespace App\Core;

use App\Domain\Permisos;

/** Configuración general guardada en la tabla config (clave → JSON). */
final class Config
{
    private static array $cache = [];

    public static function limpiar(): void
    {
        self::$cache = [];
    }

    public static function get(string $clave, mixed $def = null): mixed
    {
        if (!array_key_exists($clave, self::$cache)) {
            $v = App::$db->val('SELECT valor FROM config WHERE clave = :c', ['c' => $clave]);
            self::$cache[$clave] = $v === null ? null : json_decode((string)$v, true);
        }
        return self::$cache[$clave] ?? $def;
    }

    public static function set(string $clave, mixed $valor, ?string $usuario = null): void
    {
        App::$db->exec(
            'INSERT INTO config (clave, valor, actualizado, actualizado_por) VALUES (:c, :v, now(), :u)
             ON CONFLICT (clave) DO UPDATE SET valor = EXCLUDED.valor, actualizado = now(), actualizado_por = EXCLUDED.actualizado_por',
            ['c' => $clave, 'v' => json_encode($valor, JSON_UNESCAPED_UNICODE), 'u' => $usuario ?? (App::$usuario['nombre'] ?? 'sistema')]
        );
        self::$cache[$clave] = $valor;
    }

    public static function diaVto(): int
    {
        return max(1, min(28, (int)self::get('dia_vto', 10)));
    }

    public static function inicio(): array
    {
        return self::get('inicio', ['fecha' => '2026-10-31', 'periodo' => '2026-11']);
    }

    public static function empresa(): array
    {
        return self::get('empresa', []);
    }

    public static function pvCuotas(): int
    {
        return (int)(self::get('punto_venta', [])['cuotas'] ?? 1);
    }

    /** CU-47: ¿la acción está marcada como "requiere administrador"? */
    public static function requiereAdmin(string $accion): bool
    {
        return (bool)(self::get('permisos', [])[$accion] ?? true);
    }
}

/**
 * CU-04: autorización de un administrador para una sola acción.
 * El token es de un solo uso, vale 10 minutos y está atado a la acción y al usuario.
 */
final class AutorizacionAdmin
{
    /** Exige autorización si quien actúa no es administrador y la acción está marcada. */
    public static function exigir(string $accion, ?Request $req = null): void
    {
        $u = App::$usuario;
        if (!$u || $u['admin'] || !Config::requiereAdmin($accion)) {
            return;
        }
        $req ??= App::$req;
        $tok = (string)($req?->post['_adm'] ?? '');
        if ($tok === '' || !self::consumir($tok, $accion, $u['id'])) {
            $texto = (Permisos::ACCIONES[$accion] ?? 'Esta acción') . ' solo lo puede hacer un administrador. Puede autorizarlo ahora con su usuario y clave.';
            throw new RequiereAdmin($accion, $texto);
        }
    }

    /** Valida al administrador y emite el token (POST /api/autorizar). */
    public static function emitir(string $usuario, string $clave, string $accion): string
    {
        $adm = Usuarios::porNombre(App::$db, $usuario);
        if (!$adm || !$adm['activo'] || !$adm['admin'] || !Usuarios::verificar($adm, $clave)) {
            throw new Falta('Usuario o clave incorrectos, o el usuario no es administrador.');
        }
        $tok = bin2hex(random_bytes(24));
        App::$db->insert('tokens_admin', [
            'token_hash' => hash('sha256', $tok), 'accion' => $accion,
            'admin_id' => $adm['id'], 'usuario_id' => App::$usuario['id'],
        ], 'token_hash');
        return $tok;
    }

    public static function consumir(string $tok, string $accion, int $usuarioId): bool
    {
        $f = App::$db->one(
            "UPDATE tokens_admin t SET usado = now() FROM usuarios a
             WHERE t.token_hash = :h AND t.accion = :ac AND t.usuario_id = :u AND t.usado IS NULL
               AND t.creado > now() - interval '10 minutes' AND a.id = t.admin_id
             RETURNING a.nombre",
            ['h' => hash('sha256', $tok), 'ac' => $accion, 'u' => $usuarioId]
        );
        if (!$f) {
            return false;
        }
        App::$autorizo = $f['nombre'];
        return true;
    }
}
