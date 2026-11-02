<?php
declare(strict_types=1);

namespace App\Domain;

use App\Core\App;

/** Planes, categorías, precios, prestaciones, unidades y reglas (lectura con caché por pedido). */
final class Catalogo
{
    private static array $c = [];

    public static function limpiar(): void
    {
        self::$c = [];
    }

    private static function memo(string $k, callable $fn): mixed
    {
        return self::$c[$k] ??= $fn();
    }

    /** @return array<string,array> planes por id, en el orden de la pantalla (Plus, Integral, Salud, Sepelio) */
    public static function planes(): array
    {
        return self::memo('planes', function () {
            $r = [];
            foreach (App::$db->all('SELECT * FROM planes ORDER BY orden') as $p) {
                $p['individual'] = (bool)$p['individual'];
                $p['alicuota'] = $p['alicuota'] === null ? null : (float)$p['alicuota'];
                $r[$p['id']] = $p;
            }
            return $r;
        });
    }

    public static function plan(string $id): array
    {
        return self::planes()[$id] ?? ['id' => $id, 'nombre' => $id, 'individual' => false, 'alicuota' => null, 'inferior' => null];
    }

    public static function planCorto(string $id): string
    {
        return ['INT' => 'Integral', 'PLUS' => 'Plus', 'SALUD' => 'Salud', 'SEPELIO' => 'Sepelio'][$id] ?? $id;
    }

    /** @return array<string,array> */
    public static function categorias(): array
    {
        return self::memo('cats', function () {
            $r = [];
            foreach (App::$db->all('SELECT * FROM categorias ORDER BY orden') as $c) {
                $c['titular'] = (bool)$c['titular'];
                $r[$c['id']] = $c;
            }
            return $r;
        });
    }

    public static function categoria(string $id): array
    {
        return self::categorias()[$id] ?? ['id' => $id, 'nombre' => $id, 'titular' => false];
    }

    public static function catTitular(): array
    {
        return ['TIT', 'TIT64'];
    }

    public static function catAdicional(): array
    {
        return ['HIJ', 'CON', 'A50', 'CON64'];
    }

    /** @return array<string,array<string,float>> precio[plan][categoria] */
    public static function precios(): array
    {
        return self::memo('precios', function () {
            $r = [];
            foreach (App::$db->all('SELECT * FROM precios') as $p) {
                $r[$p['plan_id']][$p['categoria_id']] = (float)$p['precio'];
            }
            return $r;
        });
    }

    public static function precio(string $cat, string $plan): float
    {
        return self::precios()[$plan][$cat] ?? 0.0;
    }

    /** @return array<string,array> unidades activas (y las de baja si $todas) por nombre */
    public static function unidades(bool $todas = false): array
    {
        $all = self::memo('unidades', fn() => array_column(App::$db->all('SELECT * FROM unidades ORDER BY original DESC, id'), null, 'nombre'));
        return $todas ? $all : array_filter($all, fn($u) => $u['activo']);
    }

    public static function unidadPorId(int $id): ?array
    {
        foreach (self::unidades(true) as $u) {
            if ((int)$u['id'] === $id) {
                return $u;
            }
        }
        return null;
    }

    /** @return array<string,array> prestaciones por código (activas si $soloActivas) */
    public static function prestaciones(bool $soloActivas = true): array
    {
        $all = self::memo('prest', function () {
            $r = [];
            foreach (App::$db->all('SELECT p.*, u.nombre AS unidad, u.activo AS unidad_activa FROM prestaciones p JOIN unidades u ON u.id = p.unidad_id ORDER BY p.orden, p.cod') as $p) {
                $p['activo'] = (bool)$p['activo'] && (bool)$p['unidad_activa'];
                $p['requiere_auditoria'] = (bool)$p['requiere_auditoria'];
                $p['valor_ref'] = (float)$p['valor_ref'];
                $r[$p['cod']] = $p;
            }
            return $r;
        });
        return $soloActivas ? array_filter($all, fn($p) => $p['activo']) : $all;
    }

    public static function prestacion(string $cod): ?array
    {
        return self::prestaciones(false)[$cod] ?? null;
    }

    /** RT-06: regla por plan y prestación (con valores por defecto). */
    public static function regla(string $plan, string $cod): array
    {
        $todas = self::memo('reglas', function () {
            $r = [];
            foreach (App::$db->all('SELECT * FROM reglas_beneficio') as $x) {
                $r[$x['plan_id']][$x['prestacion_cod']] = [
                    'gratis' => (int)$x['gratis'], 'per' => $x['per'], 'por_grupo' => (bool)$x['por_grupo'],
                    'car_gratis' => (int)$x['car_gratis'], 'desc' => (float)$x['descuento'], 'modo' => $x['modo'],
                    'car' => (int)$x['car'], 'escala' => $x['escala'], 'max_desc' => (int)$x['max_desc'],
                ];
            }
            return $r;
        });
        return $todas[$plan][$cod] ?? Beneficios::REGLA0;
    }

    public static function rubros(): array
    {
        return self::memo('rubros', fn() => array_column(App::$db->all('SELECT * FROM rubros_comercio ORDER BY orden, nombre'), null, 'nombre'));
    }

    /** CU-16: beneficios por rubro y plan [{rubro, planes:{PLUS:[texto,carencia],…}}] */
    public static function beneficiosPlan(): array
    {
        return self::memo('benef', function () {
            $r = [];
            foreach (App::$db->all('SELECT * FROM beneficios_plan ORDER BY orden, id') as $b) {
                $r[$b['rubro']]['rubro'] = $b['rubro'];
                $r[$b['rubro']]['planes'][$b['plan_id']] = [$b['texto'], $b['carencia']];
            }
            return array_values($r);
        });
    }

    public static function condiciones(): array
    {
        return self::memo('cond', fn() => App::$db->col('SELECT texto FROM condiciones ORDER BY orden'));
    }

    public const MEDIOS = ['Efectivo', 'Transferencia bancaria', 'Débito automático', 'Tarjeta de crédito', 'Mercado Pago'];
    public const TIPOS = ['consulta' => 'Consultas', 'estudio' => 'Estudios y prácticas', 'beneficio' => 'Óptica y biobárica'];
    public const COND_IVA = ['CF' => 'Consumidor Final', 'RI' => 'IVA Responsable Inscripto', 'MT' => 'Responsable Monotributo', 'EX' => 'IVA Sujeto Exento'];
    public const PROMO_PCT = [5, 10, 15, 20, 25, 30, 35, 40];
    public const PROMO_MESES = [1, 2, 3, 6];
}
