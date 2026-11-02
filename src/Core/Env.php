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
        return $v === false || $v === '' ? $def : $v;
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
