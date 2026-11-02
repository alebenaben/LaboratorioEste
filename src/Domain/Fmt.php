<?php
declare(strict_types=1);

namespace App\Domain;

/**
 * Formatos y normalizaciones (RT-02, RT-03) y montos en letras.
 * Funciones puras: se pueden probar sin base de datos.
 */
final class Fmt
{
    public const MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    /** Texto sin tildes y en minúsculas (búsquedas y nombres de usuario). */
    public static function norm(?string $v): string
    {
        $v = (string)$v;
        if (class_exists(\Normalizer::class)) {
            $v = (string)\Normalizer::normalize($v, \Normalizer::FORM_D);
            $v = (string)preg_replace('/\p{Mn}/u', '', $v);
        } else {
            $v = strtr($v, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n', 'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N']);
        }
        return trim(mb_strtolower($v));
    }

    /** Mayúsculas en castellano (RT-02). */
    public static function may(?string $v): string
    {
        return (string)preg_replace('/\s{2,}/', ' ', mb_strtoupper(trim((string)$v)));
    }

    /** "$ 25.000" · "$ 2.375,57" (sin decimales si son cero). */
    public static function money(float|int|string|null $n): string
    {
        $n = round((float)$n, 2);
        $neg = $n < 0;
        $n = abs($n);
        $dec = abs($n - floor($n)) > 0.004;
        $s = number_format($n, $dec ? 2 : 0, ',', '.');
        if ($dec) {
            $s = rtrim($s, '0');
            $s = rtrim($s, ',');
        }
        return ($neg ? '− ' : '') . '$ ' . $s;
    }

    /** Monto con dos decimales fijos, para informes: "$ 1.234,50". */
    public static function money2(float|int|string|null $n): string
    {
        return '$ ' . number_format((float)$n, 2, ',', '.');
    }

    /** Número con separador de miles. */
    public static function num(float|int|string|null $n): string
    {
        $n = (float)$n;
        $dec = abs($n - round($n)) > 0.004;
        $s = number_format($n, $dec ? 2 : 0, ',', '.');
        return $dec ? rtrim(rtrim($s, '0'), ',') : $s;
    }

    /** Lee un monto escrito en formato argentino: "1.234,50" → 1234.5 */
    public static function parseMonto(?string $v): float
    {
        $v = str_replace([' ', '$', "\u{a0}"], '', (string)$v);
        if ($v === '') {
            return 0.0;
        }
        if (str_contains($v, ',')) {
            $v = str_replace('.', '', $v);
            $v = str_replace(',', '.', $v);
        } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $v)) {
            $v = str_replace('.', '', $v);
        }
        return is_numeric($v) ? (float)$v : 0.0;
    }

    /** dd/mm/aaaa */
    public static function fd(?string $iso): string
    {
        if (!$iso) {
            return '—';
        }
        $iso = substr($iso, 0, 10);
        [$y, $m, $d] = array_pad(explode('-', $iso), 3, '');
        return "$d/$m/$y";
    }

    /** "2026-11" → "noviembre 2026" */
    public static function perLabel(string $p): string
    {
        [$y, $m] = explode('-', $p);
        return self::MESES[(int)$m - 1] . ' ' . $y;
    }

    /** DNI con separador de miles (28.913.458); CUIT con guiones (20-34014173-4). */
    public static function doc(?string $v): string
    {
        $d = preg_replace('/\D/', '', (string)$v);
        if ($d === '') {
            return '';
        }
        if (strlen($d) <= 8) {
            return number_format((int)$d, 0, ',', '.');
        }
        if (strlen($d) === 11) {
            return substr($d, 0, 2) . '-' . substr($d, 2, 8) . '-' . substr($d, 10);
        }
        return $d;
    }

    /** RT-01: DNI interno (del CUIT se toman los 8 dígitos centrales), sin ceros a la izquierda. */
    public static function docNorm(?string $v): string
    {
        $d = preg_replace('/\D/', '', (string)$v);
        if (strlen($d) === 11) {
            $d = substr($d, 2, 8);
        }
        return ltrim($d, '0');
    }

    /**
     * RT-03: celular para WhatsApp. Se normaliza a 10 dígitos con característica,
     * sin 0 ni 15, y se devuelve 549 + número. Si no queda válido devuelve lo que pudo.
     */
    public static function telWa(?string $t): string
    {
        $d = preg_replace('/\D/', '', (string)$t);
        if ($d === '') {
            return '';
        }
        if (str_starts_with($d, '54')) {
            $d = substr($d, 2);
            if (str_starts_with($d, '9')) {
                $d = substr($d, 1);
            }
        }
        $d = (string)preg_replace('/^0/', '', $d);
        if (preg_match('/^(\d{2,4})15(\d{6,8})$/', $d, $m) && strlen($m[1] . $m[2]) === 10) {
            $d = $m[1] . $m[2];
        }
        return strlen($d) === 10 ? '549' . $d : $d;
    }

    public static function telValido(?string $t): bool
    {
        return (bool)preg_match('/^549\d{10}$/', self::telWa($t));
    }

    /** Nombre completo en mayúsculas a partir de sus partes. */
    public static function nombreCompleto(string $apellido, string $n1, string $n2 = ''): string
    {
        return implode(' ', array_filter([self::may($apellido), self::may($n1), self::may($n2)], fn($x) => $x !== ''));
    }

    /** "MARÍA" → "María" */
    public static function capi(?string $x): string
    {
        $x = (string)$x;
        return $x === '' ? '' : mb_strtoupper(mb_substr($x, 0, 1)) . mb_strtolower(mb_substr($x, 1));
    }

    /** Importe en letras para el recibo: 25000 → "veinticinco mil". */
    public static function enLetras(float $n): string
    {
        $U = ['', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve', 'diez', 'once', 'doce', 'trece', 'catorce', 'quince', 'dieciséis', 'diecisiete', 'dieciocho', 'diecinueve', 'veinte', 'veintiuno', 'veintidós', 'veintitrés', 'veinticuatro', 'veinticinco', 'veintiséis', 'veintisiete', 'veintiocho', 'veintinueve'];
        $D = ['', '', '', 'treinta', 'cuarenta', 'cincuenta', 'sesenta', 'setenta', 'ochenta', 'noventa'];
        $C = ['', 'ciento', 'doscientos', 'trescientos', 'cuatrocientos', 'quinientos', 'seiscientos', 'setecientos', 'ochocientos', 'novecientos'];
        $c999 = function (int $x) use ($U, $D, $C): string {
            if ($x === 0) {
                return '';
            }
            if ($x === 100) {
                return 'cien';
            }
            $c = intdiv($x, 100);
            $r = $x % 100;
            $t = $C[$c];
            if ($r) {
                $t .= ($t ? ' ' : '') . ($r < 30 ? $U[$r] : $D[intdiv($r, 10)] . ($r % 10 ? ' y ' . $U[$r % 10] : ''));
            }
            return $t;
        };
        $apocope = fn(string $s) => (string)preg_replace(['/veintiuno$/', '/uno$/'], ['veintiún', 'un'], $s);
        $ent = (int)floor($n + 1e-9);
        $cent = (int)round(($n - $ent) * 100);
        $t = '';
        $mill = intdiv($ent, 1000000);
        $mil = intdiv($ent % 1000000, 1000);
        $r = $ent % 1000;
        if ($mill) {
            $t .= ($mill === 1 ? 'un millón' : $apocope($c999($mill)) . ' millones') . ' ';
        }
        if ($mil) {
            $t .= ($mil === 1 ? 'mil' : $apocope($c999($mil)) . ' mil') . ' ';
        }
        if ($r) {
            $t .= $c999($r);
        }
        $t = trim($t) ?: 'cero';
        return $t . ($cent ? " con $cent/100" : '');
    }

    /**
     * CU-15: separa apellido y nombres de "Apellido y nombre" aunque se escriban al revés,
     * usando una lista de nombres frecuentes y los nombres del padrón.
     * @param string[] $nombresPadron nombres de pila ya normalizados
     * @return array{apellido:string,nombres:string[]}|null
     */
    public static function partirInteresado(string $txt, array $nombresPadron = []): ?array
    {
        $w = array_values(array_filter(preg_split('/\s+/', self::may($txt)) ?: [], fn($x) => $x !== ''));
        if (!$w) {
            return null;
        }
        static $comunes = null;
        $comunes ??= array_flip(array_map([self::class, 'norm'], CatalogoMaqueta::NOMBRES_AR));
        $np = array_flip($nombresPadron);
        $esNom = fn(string $x) => isset($comunes[self::norm($x)]) || isset($np[self::norm($x)]);
        if (count($w) === 1) {
            return $esNom($w[0]) ? ['apellido' => '', 'nombres' => $w] : ['apellido' => $w[0], 'nombres' => []];
        }
        $ult = $w[count($w) - 1];
        if ($esNom($w[0]) && !$esNom($ult)) {
            return ['apellido' => $ult, 'nombres' => array_slice($w, 0, -1)];
        }
        return ['apellido' => $w[0], 'nombres' => array_slice($w, 1)];
    }

    /** IVA contenido en un total con IVA incluido: 25000 al 10,5 % → 2375,57 */
    public static function ivaContenido(float $total, ?float $alicuota): ?float
    {
        if ($alicuota === null) {
            return null;
        }
        return round($total * $alicuota / (100 + $alicuota), 2);
    }

    public static function ivaTxt(?float $a): string
    {
        if ($a === null) {
            return 'a confirmar';
        }
        if ((float)$a === 0.0) {
            return 'exento';
        }
        return str_replace('.', ',', rtrim(rtrim(number_format($a, 2, '.', ''), '0'), '.')) . '%';
    }

    /** "hace 3 días", "ayer", "hoy" */
    public static function hace(string $fecha, string $hoy): string
    {
        $d = Fechas::dias(substr($fecha, 0, 10), $hoy);
        if ($d <= 0) {
            return 'hoy';
        }
        if ($d === 1) {
            return 'ayer';
        }
        if ($d < 31) {
            return "hace $d días";
        }
        if ($d < 365) {
            $m = (int)round($d / 30);
            return "hace $m " . ($m === 1 ? 'mes' : 'meses');
        }
        $a = intdiv($d, 365);
        return "hace $a " . ($a === 1 ? 'año' : 'años');
    }

    /** Escapa HTML. */
    public static function e(mixed $s): string
    {
        return htmlspecialchars((string)($s ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
