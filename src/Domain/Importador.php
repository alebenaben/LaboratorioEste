<?php
declare(strict_types=1);

namespace App\Domain;

use App\Core\Falta;

/**
 * CU-41: lee una lista de precios (PDF con texto, Excel, CSV o TXT) y arma los renglones con nombre y precio.
 * Un renglón es válido si tiene un nombre de 3 o más letras y un monto de 100 o más (o con $);
 * se ignoran porcentajes, años, fechas y renglones de total o encabezado.
 */
final class Importador
{
    private const VACIAS = ['de', 'del', 'la', 'las', 'el', 'los', 'en', 'con', 'y', 'por', 'a', 'o', 'al', 'para', 'sin', 'un', 'una'];

    /** @return string[] líneas de texto del archivo */
    public static function lineas(string $ruta, string $nombre): array
    {
        $ext = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
        try {
            if ($ext === 'pdf') {
                $p = (new \Smalot\PdfParser\Parser())->parseFile($ruta);
                $out = [];
                foreach ($p->getPages() as $pg) {
                    foreach (preg_split('/\r?\n/', $pg->getText()) as $l) {
                        $l = trim((string)preg_replace('/[ \t]+/', ' ', $l));
                        if ($l !== '') {
                            $out[] = $l;
                        }
                    }
                }
                return $out;
            }
            if (in_array($ext, ['xlsx', 'xls', 'ods'], true)) {
                $ss = \PhpOffice\PhpSpreadsheet\IOFactory::load($ruta);
                $hojas = $ss->getSheetNames();
                $pref = array_values(array_filter($hojas, fn($n) => preg_match('/nomenclador|precio|lista/i', $n)));
                $out = [];
                foreach ($pref ?: $hojas as $h) {
                    foreach ($ss->getSheetByName($h)->toArray(null, true, false, false) as $fila) {
                        $c = [];
                        foreach ($fila as $v) {
                            if ($v === null || trim((string)$v) === '') {
                                continue;
                            }
                            $c[] = is_float($v) || is_int($v) ? (floor((float)$v) == (float)$v ? number_format((float)$v, 0, ',', '.') : number_format((float)$v, 2, ',', '.')) : (string)$v;
                        }
                        if ($c) {
                            $out[] = implode('  ', $c);
                        }
                    }
                }
                return $out;
            }
            $txt = (string)file_get_contents($ruta);
            if (!mb_check_encoding($txt, 'UTF-8')) {
                $txt = mb_convert_encoding($txt, 'UTF-8', 'Windows-1252');
            }
            return array_map(fn($l) => (string)preg_replace('/[;\t,](?=\s*\$?\s*\d)/', '  ', $l), preg_split('/\r?\n/', $txt));
        } catch (\Throwable $e) {
            throw new Falta('No se pudo leer el archivo: ' . $e->getMessage());
        }
    }

    /** @return array{nombre:string,montos:float[],txt:string}|null */
    public static function renglon(string $t): ?array
    {
        $montos = [];
        $primero = -1;
        if (!preg_match_all('/(\$\s*)?(\d{1,3}(?:[.\s]\d{3})+|\d+)(,\d{1,2})?(\s*%)?/u', $t, $mm, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            return null;
        }
        foreach ($mm as $m) {
            $pesos = isset($m[1]) && $m[1][0] !== '' && $m[1][1] >= 0;
            $dec = isset($m[3]) && $m[3][0] !== '' && $m[3][1] >= 0;
            if (isset($m[4]) && $m[4][0] !== '' && $m[4][1] >= 0) {
                continue; // porcentaje
            }
            $v = (float)(preg_replace('/[.\s]/', '', $m[2][0]) . ($dec ? '.' . substr($m[3][0], 1) : ''));
            if (!($v >= 100) && !$pesos) {
                continue;
            }
            if (!$pesos && !$dec && preg_match('/^\d{4}$/', $m[2][0]) && $v >= 1900 && $v <= 2100) {
                continue; // año
            }
            if ($v < 1) {
                continue;
            }
            $ini = $m[0][1];
            if (preg_match('/\d{2}\/\d{2}/', substr($t, max(0, $ini - 3), strlen($m[0][0]) + 6))) {
                continue; // fecha
            }
            $montos[] = $v;
            if ($primero < 0) {
                $primero = $ini;
            }
        }
        if (!$montos) {
            return null;
        }
        $nom = substr($t, 0, $primero);
        $nom = (string)preg_replace('/[\$:.·\-–|]+\s*$/u', '', $nom);
        $nom = trim((string)preg_replace('/^\s*(\d+[\.\)\-]?|[A-Z]{1,3}\d{1,4}|[•\-–*])\s+/u', '', $nom));
        if (!preg_match('/\pL{3}/u', $nom) || preg_match('/^(sub)?total|^precio|^importe|^p[aá]gina/iu', $nom)) {
            return null;
        }
        return ['nombre' => $nom, 'montos' => $montos, 'txt' => $t];
    }

    /** Lee el archivo y detecta la unidad si el archivo nombra una sola. */
    public static function leer(string $ruta, string $nombre): array
    {
        $lin = self::lineas($ruta, $nombre);
        $reng = array_values(array_filter(array_map([self::class, 'renglon'], $lin)));
        if (!$reng) {
            $sinTexto = strtolower(pathinfo($nombre, PATHINFO_EXTENSION)) === 'pdf' && trim(implode('', $lin)) === '';
            throw new Falta($sinTexto ? 'No encontré precios: este PDF parece una foto o un escaneo, no tiene texto para leer.' : 'No encontré precios: no hay renglones con un nombre y un precio.');
        }
        $todo = Fmt::norm(implode(' ', $lin));
        $unis = array_values(array_filter(array_keys(Catalogo::unidades()), fn($u) => str_contains($todo, Fmt::norm($u))));
        return ['archivo' => $nombre, 'reng' => $reng, 'unidad' => count($unis) === 1 ? $unis[0] : '*'];
    }

    private static function toks(string $t): array
    {
        $t = (string)preg_replace('/[^a-z0-9ñ ]/u', ' ', Fmt::norm($t));
        return array_values(array_filter(preg_split('/\s+/', $t) ?: [], fn($x) => mb_strlen($x) > 1 && !in_array($x, self::VACIAS, true)));
    }

    /** Parecido entre dos nombres (0 a 1). */
    public static function parecido(string $a, string $b): float
    {
        $A = self::toks($a);
        $B = self::toks($b);
        if (!$A || !$B) {
            return 0.0;
        }
        $na = implode(' ', $A);
        $nb = implode(' ', $B);
        if ($na === $nb) {
            return 1.0;
        }
        if (str_contains($na, $nb) || str_contains($nb, $na)) {
            return 0.85;
        }
        $c = 0;
        foreach ($A as $x) {
            foreach ($B as $y) {
                if ($x === $y || (mb_strlen($y) > 4 && mb_strlen($x) > 4 && (str_starts_with($y, mb_substr($x, 0, 5)) || str_starts_with($x, mb_substr($y, 0, 5))))) {
                    $c++;
                    break;
                }
            }
        }
        return 2 * $c / (count($A) + count($B));
    }

    /** Relaciona cada renglón con la prestación más parecida (sin repetir). */
    public static function relacionar(array $reng, int $col, string $unidad): array
    {
        $cand = array_filter(Catalogo::prestaciones(false), fn($p) => $unidad === '*' || $p['unidad'] === $unidad);
        $pares = [];
        foreach ($reng as $i => $r) {
            foreach ($cand as $p) {
                $sc = self::parecido($r['nombre'], $p['nombre']);
                if ($sc >= 0.5) {
                    $pares[] = ['i' => $i, 'cod' => $p['cod'], 'sc' => $sc];
                }
            }
        }
        usort($pares, fn($a, $b) => $b['sc'] <=> $a['sc']);
        $dest = [];
        $usados = [];
        foreach ($pares as $x) {
            if (!isset($dest[$x['i']]) && !isset($usados[$x['cod']])) {
                $dest[$x['i']] = $x['cod'];
                $usados[$x['cod']] = true;
            }
        }
        $unis = array_keys(Catalogo::unidades());
        return array_map(function ($r, $i) use ($dest, $col, $unidad, $unis) {
            $uni = $unidad;
            if ($uni === '*') {
                $uni = $unis[0];
                foreach ($unis as $u) {
                    if (str_contains(Fmt::norm($r['txt']), Fmt::norm($u))) {
                        $uni = $u;
                    }
                }
            }
            $tipo = preg_match('/eco|radiog|rx\b|laborat|an[aá]lisis|electro|estudio|tomogra|resonan|mamogra|doppler|holter|ergometr|espirom|pr[aá]ctica/iu', $r['nombre']) ? 'estudio' : (preg_match('/anteojo|lente|armaz|c[aá]mara|hiperb|sesi[oó]n/iu', $r['nombre']) ? 'beneficio' : 'consulta');
            return ['nombre' => $r['nombre'], 'precio' => $r['montos'][min($col, count($r['montos']) - 1)], 'dest' => $dest[$i] ?? 'nueva', 'unidad' => $uni, 'tipo' => $tipo];
        }, $reng, array_keys($reng));
    }
}
