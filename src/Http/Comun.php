<?php
declare(strict_types=1);

namespace App\Http;

use App\Core\App;
use App\Domain\Fechas;

/** Datos que comparten todas las pantallas: franjas de aviso y menú. */
final class Comun
{
    /** Pendientes de auditoría (contador del menú y franja roja). */
    public static function pendientesAuditoria(): int
    {
        static $n = null;
        return $n ??= (int)App::$db->val("SELECT count(*) FROM autorizaciones WHERE estado = 'pendiente'");
    }

    /** CU-14: afiliados activos que cumplen hoy y no fueron saludados este año. */
    public static function cumpleanios(): array
    {
        $hoy = Fechas::hoy();
        return App::$db->all(
            "SELECT a.id, a.nro, a.nombre, a.nombre1, a.apellido, a.nac, a.telefono
             FROM afiliados a
             WHERE a.estado = 'activo' AND a.nac IS NOT NULL
               AND to_char(a.nac, 'MM-DD') = :md
               AND NOT EXISTS (SELECT 1 FROM saludos_cumple s WHERE s.afiliado_id = a.id AND s.anio = :y)
             ORDER BY a.nombre",
            ['md' => substr($hoy, 5, 5), 'y' => (int)substr($hoy, 0, 4)]
        );
    }

    /** CU-35: grupos que usaron beneficios el mes anterior y no recibieron el resumen. */
    public static function resumenesPendientes(): int
    {
        $hoy = Fechas::hoy();
        if ((int)substr($hoy, 8, 2) > 15) {
            return 0;
        }
        $per = Fechas::mesAnterior($hoy);
        return (int)App::$db->val(
            "SELECT count(DISTINCT u.grupo_id) FROM usos u
             WHERE to_char(u.fecha, 'YYYY-MM') = :p AND NOT u.pendiente_unidad
               AND NOT EXISTS (SELECT 1 FROM resumenes_enviados r WHERE r.grupo_id = u.grupo_id AND r.periodo = :p)",
            ['p' => $per]
        );
    }

    public static function avisosSinLeer(): array
    {
        return App::$db->all('SELECT * FROM avisos WHERE leido IS NULL ORDER BY fecha DESC LIMIT 5');
    }
}
