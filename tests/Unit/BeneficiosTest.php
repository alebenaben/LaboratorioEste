<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Beneficios;
use App\Domain\Importador;
use App\Domain\Usos;
use PHPUnit\Framework\TestCase;

/** RT-06: cálculo del beneficio, sin base de datos. */
final class BeneficiosTest extends TestCase
{
    private function r(array $x): array
    {
        return array_merge(Beneficios::REGLA0, $x);
    }

    /** CU-42: con la escala "0:10, 90:20", 10 % hasta el día 89 y 20 % desde el 90. */
    public function testEscalaPorAntiguedad(): void
    {
        $r = $this->r(['escala' => '0:10, 90:20', 'modo' => 'hasta']);
        self::assertSame(10.0, Beneficios::pctPorAntig($r, 89));
        self::assertSame(20.0, Beneficios::pctPorAntig($r, 90));
        self::assertSame([[0, 10.0], [90, 20.0]], Beneficios::escala($r));
    }

    public function testDescuentoConCarencia(): void
    {
        $r = $this->r(['desc' => 30, 'car' => 180]);
        self::assertSame(0.0, Beneficios::pctPorAntig($r, 179));
        self::assertSame(30.0, Beneficios::pctPorAntig($r, 180));
    }

    /** CU-22: Plus con 8 meses, 2 sesiones teniendo 1 sin cargo → 1 sin cargo y 1 al 50 %. */
    public function testUnaSinCargoYUnaConDescuento(): void
    {
        $b01 = $this->r(['gratis' => 5, 'per' => 'anio', 'por_grupo' => true, 'car_gratis' => 180, 'desc' => 50, 'car' => 210]);
        $l = Beneficios::calcularLinea($b01, true, 240, 2, 4, 0, 12000);
        self::assertSame(1, $l['gratis']);
        self::assertSame(50.0, $l['pct']);
        self::assertSame(12000 + 6000.0, $l['ahorro']);
        self::assertSame(6000.0, $l['total']);
    }

    /** CU-22: Plus con 7 meses: sin cargo, sin descuento todavía (carencia del 50 % = 210 días). */
    public function testSieteMesesSinCargoPeroSinDescuento(): void
    {
        $b01 = $this->r(['gratis' => 5, 'por_grupo' => true, 'car_gratis' => 180, 'desc' => 50, 'car' => 210]);
        $l = Beneficios::calcularLinea($b01, true, 210 - 1, 6, 0, 0, 12000);
        self::assertSame(5, $l['gratis']);
        self::assertSame(0.0, $l['pct']);
    }

    /** RT-06: sin deuda vencida ni baja no hay beneficio. */
    public function testSinBeneficioSiNoAplica(): void
    {
        $l = Beneficios::calcularLinea($this->r(['gratis' => 1, 'desc' => 30]), false, 400, 1, 0, 0, 1000);
        self::assertSame(0, $l['gratis']);
        self::assertSame(0.0, $l['pct']);
        self::assertSame(1000.0, $l['total']);
    }

    /** maxDesc: con 6 usos con descuento en el año, el porcentaje es 0. */
    public function testTopeDeUsosConDescuento(): void
    {
        $c02 = $this->r(['desc' => 30, 'car' => 180, 'max_desc' => 6]);
        self::assertSame(30.0, Beneficios::calcularLinea($c02, true, 400, 1, 0, 5, 20000)['pct']);
        $l = Beneficios::calcularLinea($c02, true, 400, 1, 0, 6, 20000);
        self::assertSame(0.0, $l['pct']);
        self::assertTrue($l['tope']);
    }

    /** Modo "hasta": la unidad puede aplicar menos, nunca más que el máximo. */
    public function testModoHasta(): void
    {
        $r = $this->r(['desc' => 40, 'modo' => 'hasta']);
        self::assertSame(25.0, Beneficios::calcularLinea($r, true, 400, 1, 0, 0, 100, 25)['pct']);
        self::assertSame(40.0, Beneficios::calcularLinea($r, true, 400, 1, 0, 0, 100, 60)['pct']);
        self::assertSame(40.0, Beneficios::calcularLinea($r, true, 400, 1, 0, 0, 100)['pct']);
    }

    public function testTextoDeLaRegla(): void
    {
        $t = Beneficios::textoRegla($this->r(['gratis' => 5, 'por_grupo' => true, 'car_gratis' => 180, 'desc' => 50, 'car' => 210]));
        self::assertSame('5 sin cargo por año por grupo (desde los 6 meses); después 50% de descuento (desde los 7 meses)', $t);
        self::assertSame('sin beneficio cargado', Beneficios::textoRegla(Beneficios::REGLA0));
    }

    /** CU-23: la carencia de los comercios sale del texto del beneficio. */
    public function testMesesDeCarencia(): void
    {
        self::assertSame(0, Usos::mesesCarencia('Sin carencias'));
        self::assertSame(6, Usos::mesesCarencia('6 meses'));
        self::assertSame(12, Usos::mesesCarencia('1 año'));
        self::assertNull(Usos::mesesCarencia('Sesión sin costo: 6 meses · Descuento: 3 meses'));
    }

    /** CU-41: renglones válidos de una lista de precios. */
    public function testRenglonesDeUnaListaDePrecios(): void
    {
        $r = Importador::renglon('Consulta clínica $ 18.500');
        self::assertSame('Consulta clínica', $r['nombre']);
        self::assertSame([18500.0], $r['montos']);
        self::assertNull(Importador::renglon('Total $ 150.000'));
        self::assertNull(Importador::renglon('Descuento 30%'));
        self::assertNull(Importador::renglon('Lista vigente 2026'));
        self::assertSame([12000.0, 9600.0], Importador::renglon('3. Sesión hiperbárica 12.000 9.600')['montos']);
    }

    /** CU-41: "Consulta clínica" se relaciona con "Consulta clínica, cardiología o pediatría". */
    public function testParecidoDeNombres(): void
    {
        self::assertGreaterThanOrEqual(0.5, Importador::parecido('Consulta clínica', 'Consulta clínica, cardiología o pediatría'));
        self::assertLessThan(0.5, Importador::parecido('Anteojos', 'Consulta clínica, cardiología o pediatría'));
    }
}
