<?php
declare(strict_types=1);

namespace Tests\Integracion;

use App\Core\App;
use App\Domain\Beneficios;
use App\Domain\Catalogo;
use App\Domain\Usos;

/** CU-20 a CU-25 con los afiliados del lote L03. */
final class BeneficiosTest extends CasoIntegracion
{
    private function unidad(string $n): int
    {
        return (int)Catalogo::unidades()[$n]['id'];
    }

    /** CU-20: una factura vencida → "No corresponde autorizar: Cuotas impagas". */
    public function testCuotasImpagas(): void
    {
        $v = Beneficios::verificar($this->afi(68), Catalogo::prestacion('C01'));
        self::assertFalse($v['ok']);
        self::assertSame('Cuotas impagas', $v['motivo']);
        $this->esperaFalta(fn() => Usos::autorizar($this->afi(68), 'C01', true, App::$usuario), 'Cuotas impagas');
    }

    /** CU-20: en carencia y dado de baja. */
    public function testCarenciaYBaja(): void
    {
        $v = Beneficios::verificar($this->afi(507), Catalogo::prestacion('B01'));
        self::assertFalse($v['ok']);
        self::assertStringContainsString('En carencia (faltan 150 días)', $v['motivo']);
        $b = Beneficios::verificar($this->afi(508), Catalogo::prestacion('C01'));
        self::assertStringContainsString('Afiliado dado de baja', $b['motivo']);
    }

    /** CU-20: autorizar una sesión sin cargo deja 4 de 5 en un grupo Plus con más de 6 meses. */
    public function testAutorizacionDescuentaDelCupo(): void
    {
        $a = $this->afi(501);
        $r = Usos::autorizar($a, 'B01', true, App::$usuario);
        self::assertSame('sin cargo', $r['consumo']);
        $s = array_values(array_filter(Beneficios::saldo($a), fn($x) => $x['p']['cod'] === 'B01'))[0];
        self::assertSame(4, $s['quedan']);
        self::assertGreaterThanOrEqual(1001, $r['nro']);
    }

    /** CU-20: la numeración es única y empieza en 1001. */
    public function testNumeracionDeAutorizaciones(): void
    {
        self::assertSame(1001, (int)$this->val('SELECT min(nro) FROM autorizaciones'));
        $n1 = Usos::autorizar($this->afi(70), 'C01', true, App::$usuario)['nro'];
        $n2 = Usos::autorizar($this->afi(70), 'O01', true, App::$usuario)['nro'];
        self::assertSame($n1 + 1, $n2);
    }

    /** CU-20 A2 / CU-40: con "Requiere auditoría" un usuario solo puede enviarla a auditoría. */
    public function testRequiereAuditoria(): void
    {
        App::$db->exec("UPDATE prestaciones SET requiere_auditoria = true WHERE cod = 'B01'");
        Catalogo::limpiar();
        $this->como('Belén');
        $this->esperaFalta(fn() => Usos::autorizar($this->afi(501), 'B01', true, App::$usuario), 'requiere auditoría');
        $this->esperaFalta(fn() => Usos::enviarAuditoria($this->afi(501), 'B01', '', App::$usuario), 'observación');
        $nro = Usos::enviarAuditoria($this->afi(501), 'B01', 'Indicación médica', App::$usuario);
        self::assertSame('pendiente', $this->val('SELECT estado FROM autorizaciones WHERE nro = :n', ['n' => $nro]));
    }

    /** CU-21: aprobar dos veces no descuenta dos veces; rechazar exige motivo; solo administradores. */
    public function testAuditoria(): void
    {
        $id = (int)$this->val("SELECT a.id FROM autorizaciones a JOIN afiliados f ON f.id = a.afiliado_id WHERE a.estado = 'pendiente' AND f.nro = 501");
        $this->como('Belén');
        $this->esperaFalta(fn() => Usos::auditar($id, true, '', App::$usuario), 'Solo un administrador');
        $this->como('Katy');
        $this->esperaFalta(fn() => Usos::auditar($id, false, '', App::$usuario), 'motivo del rechazo');
        Usos::auditar($id, true, 'ok', App::$usuario);
        $this->esperaFalta(fn() => Usos::auditar($id, true, '', App::$usuario), 'ya fue resuelta');
        self::assertSame(1, (int)$this->val('SELECT count(*) FROM usos WHERE autorizacion_id = :i', ['i' => $id]));
        self::assertSame('Katy', $this->val('SELECT auditor FROM autorizaciones WHERE id = :i', ['i' => $id]));
    }

    /** CU-22: Plus con 7 meses ve SIN CARGO en hiperbárica y "Le quedan 5 de 5". */
    public function testHoyDisponeDe(): void
    {
        $it = Beneficios::hoyEnUnidad($this->afi(501), $this->unidad('Biobárica'));
        self::assertSame('SIN CARGO', $it[0]['hoy']['t']);
        self::assertStringContainsString('Le quedan 5 de 5', $it[0]['hoy']['d']);
    }

    /** CU-22: Plus con 8 meses, 2 sesiones con 1 sin cargo disponible → 1 sin cargo y 1 al 50 %. */
    public function testRegistrarUsoEnUnidad(): void
    {
        $r = Usos::registrarEnUnidad($this->afi(502), $this->unidad('Biobárica'), 'B01', 2, 0, 'Biobárica');
        self::assertSame(1, $r['linea']['gratis']);
        self::assertSame(50.0, $r['linea']['pct']);
        self::assertStringContainsString('1 sin cargo y 1 con 50%', $r['mensaje']);
        self::assertMatchesRegularExpression('/^U00004-\d{8}$/', $r['uso']['numero']);
    }

    /** CU-25: con cupo por grupo, el uso de un integrante baja lo que le queda a los demás. */
    public function testCupoPorGrupo(): void
    {
        $hijo = $this->afi(503);
        $s = array_values(array_filter(Beneficios::saldo($hijo), fn($x) => $x['p']['cod'] === 'B01'))[0];
        self::assertSame(1, $s['quedan']);
        self::assertSame(4, $s['usados']);
    }

    /** CU-22 A1: con una autorización pendiente, registrar 1 la confirma y no descuenta dos veces. */
    public function testConfirmaLaAutorizacion(): void
    {
        $a = $this->afi(501);
        Usos::autorizar($a, 'B01', true, App::$usuario);
        $antes = (int)$this->val('SELECT count(*) FROM usos WHERE afiliado_id = :a', ['a' => $a['id']]);
        $r = Usos::registrarEnUnidad($a, $this->unidad('Biobárica'), 'B01', 1, 15000, 'Biobárica');
        self::assertTrue($r['confirmada']);
        self::assertSame($antes, (int)$this->val('SELECT count(*) FROM usos WHERE afiliado_id = :a', ['a' => $a['id']]));
        self::assertSame(15000.0, (float)$this->val('SELECT l.ahorro FROM usos_lineas l JOIN usos u ON u.id = l.uso_id WHERE u.afiliado_id = :a', ['a' => $a['id']]));
        $it = Beneficios::hoyEnUnidad($a, $this->unidad('Biobárica'));
        self::assertStringNotContainsString('AUTORIZADA', $it[0]['hoy']['t']);
    }

    /** CU-24: las autorizaciones aparecen como uso con "(autorización N° …)" en "Registró". */
    public function testAutorizacionComoUso(): void
    {
        $r = Usos::autorizar($this->afi(70), 'C01', true, App::$usuario);
        $u = $this->val('SELECT usuario FROM usos WHERE autorizacion_id = (SELECT id FROM autorizaciones WHERE nro = :n)', ['n' => $r['nro']]);
        self::assertSame("Santiago (autorización N° {$r['nro']})", $u);
    }

    /** RT-06: óptica Integral con escala: 10 % al día 89 y 20 % desde el 90. */
    public function testEscalaDeOptica(): void
    {
        $c89 = Beneficios::calcular($this->afi(504), [['cod' => 'O01', 'cant' => 1]]);
        $c90 = Beneficios::calcular($this->afi(505), [['cod' => 'O01', 'cant' => 1]]);
        self::assertSame(10.0, $c89['lineas'][0]['pct']);
        self::assertSame(20.0, $c90['lineas'][0]['pct']);
    }

    /** RT-06 maxDesc: con 6 consultas con descuento en el año, la séptima va sin descuento. */
    public function testTopeAnualDeDescuentos(): void
    {
        $c = Beneficios::calcular($this->afi(506), [['cod' => 'C02', 'cant' => 1]]);
        self::assertSame(0.0, $c['lineas'][0]['pct']);
        self::assertStringContainsString('tope de 6', $c['lineas'][0]['nota']);
    }

    /** CU-23: gimnasio con máximo 15 %: un 20 % queda en 15 %. */
    public function testComercioRespetaElMaximo(): void
    {
        $com = $this->como('sanbenito');
        $r = Usos::registrarConsumo('28913458', $com, 'Cuota', 20000, 20);
        self::assertSame(15.0, $r['pct']);
        self::assertSame(3000.0, $r['ahorro']);
    }

    /** CU-23: con límite 1 por mes, el segundo intento del mes se rechaza. */
    public function testLimiteDeUsosDelComercio(): void
    {
        App::$db->exec("UPDATE rubros_comercio SET limite = 1, per = 'mes' WHERE nombre = 'Gimnasio'");
        Catalogo::limpiar();
        $com = $this->como('sanbenito');
        $v = Usos::verificarComercio('28913458', $com);
        self::assertFalse($v['ok']);
        self::assertSame('Ya usó el beneficio 1 vez este mes (límite: 1).', $v['txt']);
    }

    /** CU-44: con límite 2 por semestre, el tercer consumo del semestre se rechaza. */
    public function testLimitePorSemestre(): void
    {
        $com = $this->como('barberia');
        $v = Usos::verificarComercio('32105117', $com);
        self::assertFalse($v['ok']);
        self::assertStringContainsString('(límite: 2)', $v['txt']);
    }

    /** CU-23: un afiliado del Plan Integral no tiene el beneficio de gimnasio. */
    public function testPlanSinElRubro(): void
    {
        $v = Usos::verificarComercio('510', $this->como('sanbenito'));
        self::assertFalse($v['ok']);
        self::assertStringContainsString('no incluye este beneficio', $v['txt']);
        $n = Usos::verificarComercio('99999999', App::$usuario);
        self::assertSame('No es afiliado de Integral del Este.', $n['txt']);
    }

    /** RT-06: el ahorro de un uso sin precio se calcula con el valor de referencia. */
    public function testAhorroConValorDeReferencia(): void
    {
        $r = Usos::registrarEnUnidad($this->afi(501), $this->unidad('Biobárica'), 'B01', 1, 0, 'Biobárica');
        $ah = (float)$this->val('SELECT ahorro_ef FROM v_usos_lineas WHERE numero = :n', ['n' => $r['uso']['numero']]);
        self::assertSame(12000.0, $ah);
    }
}
