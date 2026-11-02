<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Fechas;
use App\Domain\Fmt;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** RT-02, RT-03 y formatos de comprobantes. */
final class FmtTest extends TestCase
{
    public function testDniConSeparadorDeMilesYCuitConGuiones(): void
    {
        self::assertSame('28.913.458', Fmt::doc('28913458'));
        self::assertSame('28.913.458', Fmt::doc('28.913.458'));
        self::assertSame('20-34014173-4', Fmt::doc('20340141734'));
        self::assertSame('6.501.683', Fmt::doc('6501683'));
    }

    public function testDniInternoDelCuit(): void
    {
        self::assertSame('34014173', Fmt::docNorm('20-34014173-4'));
        self::assertSame('28913458', Fmt::docNorm('28.913.458'));
        self::assertSame('6501683', Fmt::docNorm('06.501.683'));
    }

    public function testBusquedaIgnoraTildesYMayusculas(): void
    {
        self::assertSame('katy', Fmt::norm('Káty'));
        self::assertSame('belen', Fmt::norm('BELÉN'));
        self::assertSame(Fmt::norm('KATY'), Fmt::norm('katy'));
    }

    public function testMontosEnPesos(): void
    {
        self::assertSame('$ 25.000', Fmt::money(25000));
        self::assertSame('$ 2.375,57', Fmt::money(2375.57));
        self::assertSame('$ 63.750', Fmt::money(63750.0));
        self::assertSame('$ 1.234,5', Fmt::money(1234.5));
        self::assertSame(1234.5, Fmt::parseMonto('1.234,50'));
        self::assertSame(25000.0, Fmt::parseMonto('$ 25.000'));
        self::assertSame(12100.0, Fmt::parseMonto('12.100'));
    }

    public function testFechasYPeriodos(): void
    {
        self::assertSame('31/10/2026', Fmt::fd('2026-10-31'));
        self::assertSame('noviembre 2026', Fmt::perLabel('2026-11'));
        self::assertSame('2027-02', Fechas::perMas('2027-01', 1));
        self::assertSame('2026-11-30', Fechas::finPeriodo('2026-11'));
        self::assertSame(2, Fechas::perDif('2026-11', '2027-01'));
    }

    /** RT-03: celular para WhatsApp. */
    #[DataProvider('celulares')]
    public function testCelularParaWhatsApp(string $entrada, string $esperado, bool $valido): void
    {
        self::assertSame($esperado, Fmt::telWa($entrada));
        self::assertSame($valido, Fmt::telValido($entrada));
    }

    public static function celulares(): array
    {
        return [
            'CU-15: con 0 y 15' => ['03435 15 013651', '5493435013651', true],
            'diez dígitos' => ['3435013651', '5493435013651', true],
            'ya con 549' => ['+54 9 343 501-3651', '5493435013651', true],
            'faltan números' => ['343501', '343501', false],
            'vacío' => ['', '', false],
        ];
    }

    /** CU-30: el recibo muestra el importe en letras. */
    public function testImporteEnLetras(): void
    {
        self::assertSame('veinticinco mil', Fmt::enLetras(25000));
        self::assertSame('ciento cuarenta mil quinientos', Fmt::enLetras(140500));
        self::assertSame('un millón doscientos mil', Fmt::enLetras(1200000));
        self::assertSame('veintiún mil', Fmt::enLetras(21000));
        self::assertSame('cien', Fmt::enLetras(100));
        self::assertSame('mil con 50/100', Fmt::enLetras(1000.5));
    }

    /** CU-31: IVA contenido de una Factura B de $ 25.000 al 10,5 %. */
    public function testIvaContenido(): void
    {
        self::assertSame(2375.57, Fmt::ivaContenido(25000, 10.5));
        self::assertNull(Fmt::ivaContenido(25000, null));
        self::assertSame('10,5%', Fmt::ivaTxt(10.5));
        self::assertSame('a confirmar', Fmt::ivaTxt(null));
    }

    /** CU-15: "MARÍA JOSÉ GÓMEZ" → apellido GÓMEZ, nombre MARÍA JOSÉ. */
    public function testSeparaApellidoYNombres(): void
    {
        $p = Fmt::partirInteresado('maría josé gómez');
        self::assertSame('GÓMEZ', $p['apellido']);
        self::assertSame(['MARÍA', 'JOSÉ'], $p['nombres']);
        self::assertSame('María', Fmt::capi($p['nombres'][0]));
        $q = Fmt::partirInteresado('GOMEZ MARIA JOSE');
        self::assertSame('GOMEZ', $q['apellido']);
        self::assertSame(['apellido' => '', 'nombres' => ['SOFÍA']], Fmt::partirInteresado('Sofía'));
    }

    public function testEdadYCumple(): void
    {
        self::assertSame(45, Fechas::edad('1981-03-14', '2026-11-02'));
        self::assertSame(44, Fechas::edad('1981-12-14', '2026-11-02'));
        self::assertTrue(Fechas::esCumple('1975-11-02', '2026-11-02'));
        self::assertFalse(Fechas::esCumple(null, '2026-11-02'));
    }

    public function testVentanasDeBeneficio(): void
    {
        self::assertTrue(Fechas::enVentana('mes', '2026-11-01', '2026-11-30'));
        self::assertFalse(Fechas::enVentana('mes', '2026-10-31', '2026-11-01'));
        self::assertTrue(Fechas::enVentana('semestre', '2026-07-01', '2026-11-02'));
        self::assertFalse(Fechas::enVentana('semestre', '2026-06-30', '2026-11-02'));
        self::assertSame('2026-07-01', Fechas::inicioVentana('semestre', '2026-11-02'));
        self::assertSame('2026-01-01', Fechas::inicioVentana('anio', '2026-11-02'));
    }
}
