<?php
declare(strict_types=1);

namespace App\Domain;

/** Aritmética de fechas ISO (AAAA-MM-DD) y períodos (AAAA-MM), como en la maqueta. */
final class Fechas
{
    /** Fecha de hoy del sistema. APP_HOY permite fijarla en pruebas. */
    public static function hoy(): string
    {
        $f = getenv('APP_HOY');
        if ($f && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f)) {
            return $f;
        }
        return (new \DateTimeImmutable('now', new \DateTimeZone(self::tz())))->format('Y-m-d');
    }

    public static function ahora(): \DateTimeImmutable
    {
        $n = new \DateTimeImmutable('now', new \DateTimeZone(self::tz()));
        $f = getenv('APP_HOY');
        if ($f && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f)) {
            $n = $n->setDate((int)substr($f, 0, 4), (int)substr($f, 5, 2), (int)substr($f, 8, 2));
        }
        return $n;
    }

    public static function tz(): string
    {
        return getenv('TZ') ?: 'America/Argentina/Buenos_Aires';
    }

    private static function ts(string $iso): int
    {
        [$y, $m, $d] = array_map('intval', explode('-', substr($iso, 0, 10)));
        return gmmktime(0, 0, 0, $m, $d, $y);
    }

    /** Días entre a y b (b − a). */
    public static function dias(string $a, string $b): int
    {
        return (int)round((self::ts($b) - self::ts($a)) / 86400);
    }

    public static function sumarDias(string $iso, int $n): string
    {
        return gmdate('Y-m-d', self::ts($iso) + $n * 86400);
    }

    public static function sumarMeses(string $iso, int $n): string
    {
        [$y, $m, $d] = array_map('intval', explode('-', substr($iso, 0, 10)));
        return gmdate('Y-m-d', gmmktime(0, 0, 0, $m + $n, $d, $y));
    }

    /** Último día del período "AAAA-MM". */
    public static function finPeriodo(string $p): string
    {
        [$y, $m] = array_map('intval', explode('-', $p));
        return gmdate('Y-m-d', gmmktime(0, 0, 0, $m + 1, 0, $y));
    }

    /** Diferencia en meses entre dos períodos (b − a). */
    public static function perDif(string $a, string $b): int
    {
        [$y1, $m1] = array_map('intval', explode('-', $a));
        [$y2, $m2] = array_map('intval', explode('-', $b));
        return ($y2 * 12 + $m2) - ($y1 * 12 + $m1);
    }

    public static function perMas(string $p, int $n): string
    {
        [$y, $m] = array_map('intval', explode('-', $p));
        $t = $y * 12 + ($m - 1) + $n;
        return sprintf('%d-%02d', intdiv($t, 12), $t % 12 + 1);
    }

    public static function periodo(string $iso): string
    {
        return substr($iso, 0, 7);
    }

    /** Edad en años cumplidos a una fecha. */
    public static function edad(?string $nac, string $hoy): ?int
    {
        if (!$nac) {
            return null;
        }
        $e = (int)substr($hoy, 0, 4) - (int)substr($nac, 0, 4);
        if (substr($hoy, 5, 5) < substr($nac, 5, 5)) {
            $e--;
        }
        return $e;
    }

    public static function esCumple(?string $nac, string $hoy): bool
    {
        return $nac !== null && $nac !== '' && substr($nac, 5, 5) === substr($hoy, 5, 5);
    }

    /** RT-06: ¿la fecha cae en la ventana (mes, semestre o año calendario) de hoy? */
    public static function enVentana(string $per, string $fecha, string $hoy): bool
    {
        $y = substr($hoy, 0, 4);
        $m = (int)substr($hoy, 5, 2);
        if ($per === 'mes') {
            return substr($fecha, 0, 7) === substr($hoy, 0, 7);
        }
        if ($per === 'semestre') {
            return substr($fecha, 0, 4) === $y && (((int)substr($fecha, 5, 2)) <= 6) === ($m <= 6);
        }
        return substr($fecha, 0, 4) === $y;
    }

    /** Inicio de la ventana para consultas SQL. */
    public static function inicioVentana(string $per, string $hoy): string
    {
        $y = substr($hoy, 0, 4);
        $m = (int)substr($hoy, 5, 2);
        return match ($per) {
            'mes' => substr($hoy, 0, 7) . '-01',
            'semestre' => $y . ($m <= 6 ? '-01-01' : '-07-01'),
            default => $y . '-01-01',
        };
    }

    public static function mesAnterior(string $hoy): string
    {
        return self::perMas(substr($hoy, 0, 7), -1);
    }
}
