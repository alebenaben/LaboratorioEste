<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Variables de entorno. Un secreto se lee de un archivo cuando existe la
 * variable NOMBRE_FILE (secretos de Docker Compose en /run/secrets).
 */
final class Env
{
    private static bool $cargado = false;

    /** Carga un archivo .env (si existe) sin pisar variables ya definidas. */
    public static function cargar(string $archivo): void
    {
        if (self::$cargado || !is_file($archivo)) {
            self::$cargado = true;
            return;
        }
        foreach (file($archivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $l) {
            $l = trim($l);
            if ($l === '' || $l[0] === '#' || !str_contains($l, '=')) {
                continue;
            }
            [$k, $v] = array_map('trim', explode('=', $l, 2));
            $v = trim($v, "\"'");
            if (getenv($k) === false) {
                putenv("$k=$v");
                $_ENV[$k] = $v;
            }
        }
        self::$cargado = true;
    }

    public static function get(string $k, ?string $def = null): ?string
    {
        $f = getenv($k . '_FILE');
        if ($f && is_readable($f)) {
            return trim((string)file_get_contents($f));
        }
        $v = getenv($k);
        if ($v !== false && $v !== '') {
            return $v;
        }
        return self::derivado($k) ?? $def;
    }

    /**
     * Valores que se deducen cuando no están definidos (despliegue con una sola variable,
     * por ejemplo en EasyPanel):
     *   - DB_HOST, DB_PORT, DB_NAME, DB_OWNER_USER y DB_OWNER_PASSWORD salen de
     *     DATABASE_URL (postgres://usuario:clave@host:puerto/base);
     *   - las claves de integral_app e integral_backup se derivan de la clave del dueño
     *     (HMAC-SHA256), así no hay que generarlas ni guardarlas. El migrador se las pone
     *     a los roles en cada corrida.
     * Lo que se define explícitamente siempre tiene prioridad.
     */
    private static function derivado(string $k): ?string
    {
        $url = in_array($k, ['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_OWNER_USER', 'DB_OWNER_PASSWORD'], true)
            ? self::urlBase() : [];
        switch ($k) {
            case 'DB_HOST':
                return $url['host'] ?? null;
            case 'DB_PORT':
                return isset($url['port']) ? (string)$url['port'] : null;
            case 'DB_NAME':
                $b = isset($url['path']) ? urldecode(ltrim($url['path'], '/')) : '';
                return $b !== '' ? $b : null;
            case 'DB_OWNER_USER':
                return isset($url['user']) ? urldecode($url['user']) : null;
            case 'DB_OWNER_PASSWORD':
                return isset($url['pass']) ? urldecode($url['pass']) : null;
            case 'DB_PASSWORD':
                return self::claveInterna('integral_app');
            case 'DB_BACKUP_PASSWORD':
                return self::claveInterna('integral_backup');
        }
        return null;
    }

    /** @return array<string, string|int> partes de DATABASE_URL, o [] si no está. */
    private static function urlBase(): array
    {
        $u = getenv('DATABASE_URL');
        if ($u === false || $u === '') {
            return [];
        }
        $p = parse_url($u);
        return is_array($p) && in_array($p['scheme'] ?? '', ['postgres', 'postgresql', 'pgsql'], true) ? $p : [];
    }

    /** Clave de un rol interno, derivada de la clave del dueño de la base. */
    private static function claveInterna(string $rol): ?string
    {
        $duenio = self::get('DB_OWNER_PASSWORD');
        return $duenio === null ? null : substr(hash_hmac('sha256', "integral-del-este:$rol", $duenio), 0, 40);
    }

    public static function int(string $k, int $def): int
    {
        $v = self::get($k);
        return $v === null ? $def : (int)$v;
    }

    public static function bool(string $k, bool $def = false): bool
    {
        $v = self::get($k);
        return $v === null ? $def : in_array(strtolower($v), ['1', 'true', 'si', 'sí', 'yes', 'on'], true);
    }

    public static function esDemo(): bool
    {
        return self::get('APP_ENV', 'produccion') === 'demo';
    }

    public static function esPrueba(): bool
    {
        return self::get('APP_ENV', 'produccion') === 'test';
    }
}
