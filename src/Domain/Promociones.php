<?php
declare(strict_types=1);

namespace App\Domain;

use App\Core\App;
use App\Core\Falta;
use App\Core\Historial;
use App\Core\HttpError;

/** CU-18 y CU-19: promociones armadas y su envío a los afiliados. */
final class Promociones
{
    public static function hidratar(array $p): array
    {
        $p['id'] = (int)$p['id'];
        $p['planes'] = \App\Core\Db::leerArray($p['planes'] ?? '{}');
        return $p;
    }

    public static function todas(): array
    {
        return array_map([self::class, 'hidratar'], App::$db->all('SELECT * FROM promociones ORDER BY desde DESC, id DESC'));
    }

    public static function una(int $id): array
    {
        $p = App::$db->one('SELECT * FROM promociones WHERE id = :id', ['id' => $id]) ?? throw new HttpError(404, 'No existe esa promoción.');
        return self::hidratar($p);
    }

    /** [texto, tipo de chip]: Programada, Vigente o Terminada. */
    public static function estado(array $p, ?string $hoy = null): array
    {
        $hoy ??= Fechas::hoy();
        return $hoy < $p['desde'] ? ['Programada', 'neu'] : ($hoy > $p['hasta'] ? ['Terminada', 'bad'] : ['Vigente', 'ok']);
    }

    public static function vigentesParaPlan(string $plan, ?string $hoy = null): array
    {
        $hoy ??= Fechas::hoy();
        return array_values(array_filter(self::todas(), fn($p) => $p['desde'] <= $hoy && $hoy <= $p['hasta'] && (!$p['planes'] || in_array($plan, $p['planes'], true))));
    }

    /** CU-22: promociones vigentes de una unidad. */
    public static function vigentesDeUnidad(string $unidad, ?string $hoy = null): array
    {
        $hoy ??= Fechas::hoy();
        return array_values(array_filter(self::todas(), fn($p) => $p['unidad'] === $unidad && $p['desde'] <= $hoy && $hoy <= $p['hasta']));
    }

    public static function guardar(?int $id, array $d): int
    {
        $o = [
            'titulo' => trim((string)($d['titulo'] ?? '')), 'beneficio' => trim((string)($d['beneficio'] ?? '')),
            'unidad' => (string)($d['unidad'] ?? ''), 'desde' => (string)($d['desde'] ?? ''), 'hasta' => (string)($d['hasta'] ?? ''),
            'texto' => trim((string)($d['texto'] ?? '')), 'cond' => trim((string)($d['cond'] ?? '')),
            'planes' => array_values(array_intersect((array)($d['planes'] ?? []), array_keys(Catalogo::planes()))),
        ];
        $f = [];
        if ($o['titulo'] === '') {
            $f[] = 'titulo';
        }
        if ($o['beneficio'] === '') {
            $f[] = 'beneficio';
        }
        $okD = preg_match('/^\d{4}-\d{2}-\d{2}$/', $o['desde']);
        $okH = preg_match('/^\d{4}-\d{2}-\d{2}$/', $o['hasta']);
        if (!$okD) {
            $f[] = 'desde';
        }
        if (!$okH || ($okD && $o['hasta'] < $o['desde'])) {
            $f[] = 'hasta';
        }
        if ($f) {
            throw new Falta('Falta ' . implode(', ', array_map(fn($x) => ['titulo' => 'el título', 'beneficio' => 'el beneficio', 'desde' => 'la fecha desde', 'hasta' => 'las fechas (el “hasta” no puede ser antes del “desde”)'][$x], $f)) . '.', $f);
        }
        if ($o['unidad'] !== '' && !isset(Catalogo::unidades()[$o['unidad']])) {
            throw new Falta('Elegí una unidad activa.', ['unidad']);
        }
        $o['planes'] = \App\Core\Db::arrayPg($o['planes']);
        if ($id) {
            App::$db->update('promociones', $o, 'id = :id', ['id' => $id]);
            Historial::log(App::$db, 'Promociones', "Promoción modificada: {$o['titulo']}");
            return $id;
        }
        $id = (int)App::$db->insert('promociones', $o + ['por' => App::$usuario['nombre'], 'creada' => Fechas::hoy()]);
        Historial::log(App::$db, 'Promociones', "Promoción armada: {$o['titulo']} · {$o['beneficio']}");
        return $id;
    }

    public static function eliminar(int $id): void
    {
        $p = self::una($id);
        App::$db->exec('DELETE FROM promociones WHERE id = :id', ['id' => $id]);
        Historial::log(App::$db, 'Promociones', "Promoción eliminada: {$p['titulo']}");
    }

    /**
     * Destinatarios: titulares de grupos con integrantes activos de los planes elegidos.
     * @return array<int,array{g:array,t:array,tel:string,envio:?array}>
     */
    public static function destinatarios(array $p): array
    {
        $env = [];
        foreach (App::$db->all('SELECT * FROM promociones_envios WHERE promocion_id = :p', ['p' => $p['id']]) as $e) {
            $env[(int)$e['grupo_id']] = $e;
        }
        $r = [];
        foreach (App::$db->col("SELECT DISTINCT g.id FROM grupos g JOIN afiliados a ON a.grupo_id = g.id AND a.estado = 'activo' ORDER BY g.id") as $gid) {
            $g = Padron::grupo((int)$gid);
            if ($p['planes'] && !in_array($g['plan_id'], $p['planes'], true)) {
                continue;
            }
            $t = Padron::titular($g['id']);
            $r[] = ['g' => $g, 't' => $t, 'tel' => Padron::telefonoGrupo($g['id']), 'envio' => $env[$g['id']] ?? null];
        }
        usort($r, fn($a, $b) => strcmp($a['t']['nombre'], $b['t']['nombre']));
        return $r;
    }

    public static function marcarEnvio(int $promoId, int $grupoId, string $canal): bool
    {
        return App::$db->exec('INSERT INTO promociones_envios (promocion_id, grupo_id, canal) VALUES (:p, :g, :c) ON CONFLICT DO NOTHING', ['p' => $promoId, 'g' => $grupoId, 'c' => $canal]) > 0;
    }

    public static function vars(array $p, array $t): array
    {
        return ['nombre' => Padron::nomPila($t), 'titulo' => $p['titulo'], 'beneficio' => $p['beneficio'], 'lugar' => Documentos::lugarPromo($p), 'texto' => $p['texto'], 'desde' => Fmt::fd($p['desde']), 'hasta' => Fmt::fd($p['hasta'])];
    }
}
