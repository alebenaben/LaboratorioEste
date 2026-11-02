<?php
declare(strict_types=1);

namespace App\Domain;

/**
 * Roles, secciones y acciones que requieren administrador (CU-04, CU-39, CU-47).
 * La API valida con estas reglas en cada pedido (S-06 de Casos de uso).
 */
final class Permisos
{
    /** Pantallas en el orden del menú: [clave, nombre]. */
    public const VISTAS = [
        ['verificar', 'Verificar afiliado'], ['inicio', 'Inicio'], ['afiliados', 'Afiliados'], ['altas', 'Altas'],
        ['cotizador', 'Cotizador'], ['autorizaciones', 'Autorizaciones'], ['auditoria', 'Auditoría'],
        ['prestaciones', 'Usos en unidades'], ['beneficios', 'Beneficios'], ['retencion', 'Quiere darse de baja'],
        ['caja', 'Caja'], ['egresos', 'Egresos'], ['informes', 'Informes'], ['cuentas', 'Cuentas corrientes'],
        ['contabilidad', 'Contabilidad'], ['estadisticas', 'Estadísticas'], ['promociones', 'Promociones'],
        ['config', 'Configuración'],
    ];

    /** Secciones que solo ve un administrador. */
    public const SOLO_ADMIN = ['promociones', 'informes', 'egresos', 'prestaciones', 'afiliados', 'auditoria', 'caja', 'cuentas', 'contabilidad', 'estadisticas'];

    /** Secciones que un administrador puede habilitar a un usuario de atención. */
    public const USR_VISTAS = ['altas', 'cotizador', 'autorizaciones', 'beneficios', 'retencion'];

    /** CU-47: acciones configurables que requieren administrador. */
    public const ACCIONES = [
        'editar' => 'Modificar datos de afiliados',
        'descuentos' => 'Aplicar un descuento adicional en un recibo de prestaciones',
        'montos' => 'Modificar montos (precios y cuotas)',
        'padron' => 'Volver a cargar el padrón del Excel',
    ];

    public const ROLES = ['admin' => 'Administrador', 'usuario' => 'Usuario', 'unidad' => 'Unidad de negocio', 'comercio' => 'Comercio adherido'];

    public static function nombreVista(string $id): string
    {
        foreach (self::VISTAS as [$k, $n]) {
            if ($k === $id) {
                return $n;
            }
        }
        return $id;
    }

    public static function esExterno(array $u): bool
    {
        return in_array($u['rol'], ['unidad', 'comercio'], true);
    }

    /**
     * ¿Puede el usuario abrir esta sección?
     * @param array{rol:string,secciones:array} $u
     */
    public static function puedeVer(array $u, string $id): bool
    {
        if (self::esExterno($u)) {
            return $id === 'verificar';
        }
        if ($id === 'verificar') {
            return false;
        }
        if ($id === 'config' || $id === 'sinacceso') {
            return true; // los usuarios solo ven "Mi cuenta" y versiones
        }
        if ($u['rol'] === 'admin') {
            return $id !== 'inicio';
        }
        if ($id === 'inicio') {
            return true;
        }
        return !in_array($id, self::SOLO_ADMIN, true) && in_array($id, $u['secciones'], true);
    }

    /** Primera pantalla al ingresar (CU-01). */
    public static function inicio(array $u): string
    {
        if (self::esExterno($u)) {
            return 'verificar';
        }
        return $u['rol'] === 'admin' ? 'afiliados' : 'inicio';
    }

    /** Menú lateral visible para el usuario. */
    public static function menu(array $u): array
    {
        return array_values(array_filter(self::VISTAS, fn($v) => self::puedeVer($u, $v[0])));
    }
}
