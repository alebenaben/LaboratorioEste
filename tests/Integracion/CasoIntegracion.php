<?php
declare(strict_types=1);

namespace Tests\Integracion;

use App\Core\App;
use App\Core\Config;
use App\Core\Usuarios;
use App\Domain\Catalogo;
use App\Domain\Padron;
use PHPUnit\Framework\TestCase;

/**
 * Cada prueba corre dentro de una transacción que se deshace al terminar:
 * todas parten de la base con el padrón inicial y los lotes L01 a L08.
 */
abstract class CasoIntegracion extends TestCase
{
    protected function setUp(): void
    {
        App::$db->iniciarPrueba();
        Catalogo::limpiar();
        Config::limpiar();
        $this->como('Santiago');
        App::$autorizo = null;
        App::$req = null;
    }

    protected function tearDown(): void
    {
        App::$db->terminarPrueba();
        Catalogo::limpiar();
        Config::limpiar();
    }

    /** Actúa como el usuario indicado. */
    protected function como(string $nombre): array
    {
        $u = Usuarios::porNombre(App::$db, $nombre);
        self::assertNotNull($u, "No existe el usuario $nombre");
        App::$usuario = $u;
        return $u;
    }

    protected function afi(int $nro): array
    {
        $a = Padron::afiliadoPorNro($nro);
        self::assertNotNull($a, "No existe el afiliado N° $nro");
        return $a;
    }

    protected function val(string $sql, array $p = []): mixed
    {
        return App::$db->val($sql, $p);
    }

    /** Ejecuta y espera un error de validación con un texto. */
    protected function esperaFalta(callable $fn, string $texto): void
    {
        try {
            $fn();
            self::fail("Se esperaba el error: $texto");
        } catch (\App\Core\HttpError $e) {
            self::assertStringContainsString($texto, $e->getMessage());
        }
    }
}
