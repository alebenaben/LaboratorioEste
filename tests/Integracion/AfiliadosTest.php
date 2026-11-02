<?php
declare(strict_types=1);

namespace Tests\Integracion;

use App\Core\App;
use App\Core\RequiereAdmin;
use App\Domain\Catalogo;
use App\Domain\Fechas;
use App\Domain\Padron;
use App\Domain\Retencion;
use App\Http\Comun;

/** CU-05 a CU-14. */
final class AfiliadosTest extends CasoIntegracion
{
    private function alta(array $extra = []): array
    {
        return Padron::alta(array_replace_recursive([
            'plan' => 'PLUS', 'forma_pago' => 'Efectivo', 'email' => 'nuevo@example.com', 'fecha_alta' => Fechas::hoy(),
            'titular' => ['apellido' => 'nuevo', 'nombre1' => 'titular', 'nombre2' => '', 'doc' => '41.222.333', 'nac' => '1990-01-01', 'categoria' => 'TIT'],
            'familia' => [],
        ], $extra));
    }

    /** CU-05: "ramallo 75" encuentra al N° 75; "2891" encuentra el DNI 28.913.458. */
    public function testBusquedaEnElPadron(): void
    {
        $r = Padron::padron('ramallo 75', 'activo');
        self::assertCount(1, $r);
        self::assertSame(75, $r[0]['nro']);
        $d = Padron::padron('2891', '');
        self::assertContains(75, array_column($d, 'nro'));
        self::assertSame([], Padron::padron('zzzz inexistente', ''));
    }

    /** RT-01: N°, DNI con o sin puntos, CUIT y sugerencias por nombre (activos primero). */
    public function testBuscarUnAfiliado(): void
    {
        self::assertSame(75, Padron::buscarUno('75')['nro']);
        self::assertSame(75, Padron::buscarUno('28.913.458')['nro']);
        self::assertSame(75, Padron::buscarUno('28913458')['nro']);
        self::assertSame(89, Padron::buscarUno('20-34014173-4')['nro']);
        self::assertSame(75, Padron::buscarUno('RAMALLO JULIETA ILEANA · N° 75')['nro']);
        $s = Padron::sugerir('alt');
        self::assertNotEmpty($s);
        self::assertLessThanOrEqual(8, count($s));
        self::assertStringStartsWith('ALTERINI', $s[0]['label']);
        $b = Padron::sugerir('baja');
        self::assertStringContainsString('baja', end($b)['sub']);
    }

    /** CU-06: Plus titular + cónyuge 30 años + hijo 10 años = $ 63.750; con 20 % × 3 meses = $ 51.000. */
    public function testCuotaEstimadaDeUnGrupo(): void
    {
        $tot = Catalogo::precio('TIT', 'PLUS') + Catalogo::precio('CON', 'PLUS') + Catalogo::precio('HIJ', 'PLUS');
        self::assertSame(63750.0, $tot);
        self::assertSame(51000.0, Padron::conPromo($tot, 20));
        $r = $this->alta(['promo_pct' => 20, 'promo_meses' => 3, 'familia' => [
            ['apellido' => 'nuevo', 'nombre1' => 'conyuge', 'doc' => '41222334', 'nac' => '1996-01-01', 'categoria' => 'CON'],
            ['apellido' => 'nuevo', 'nombre1' => 'hijo', 'doc' => '55222335', 'nac' => '2016-01-01', 'categoria' => 'HIJ'],
        ]]);
        $g = Padron::grupo($r['grupo_id']);
        self::assertSame(63750.0, Padron::cuotaBase($g['id']));
        $p = max(substr(Fechas::hoy(), 0, 7), '2026-11');
        self::assertSame(51000.0, Padron::cuotaGrupo($g, $p));
        self::assertSame(63750.0, Padron::cuotaGrupo($g, Fechas::perMas($p, 3)));
    }

    /** CU-06: N° correlativos y consecutivos; el titular paga. */
    public function testAltaConNumerosConsecutivos(): void
    {
        $r = $this->alta(['familia' => [['apellido' => 'x', 'nombre1' => 'y', 'doc' => '41222339', 'nac' => '2000-01-01', 'categoria' => 'CON']]]);
        self::assertSame($r['nros'][0] + 1, $r['nros'][1]);
        $r2 = $this->alta(['titular' => ['doc' => '41222340']]);
        self::assertSame($r['nros'][1] + 1, $r2['nros'][0]);
        self::assertSame('NUEVO TITULAR', $r['titular']['nombre']);
        self::assertSame('41.222.333', $r['titular']['doc']);
    }

    /** CU-06 E4: no se puede confirmar un Plan Salud con un familiar. */
    public function testPlanIndividualSinFamiliares(): void
    {
        $this->esperaFalta(fn() => $this->alta(['plan' => 'SALUD', 'familia' => [['apellido' => 'x', 'nombre1' => 'y', 'doc' => '41222338', 'nac' => '2000-01-01', 'categoria' => 'CON']]]), 'es individual');
    }

    /** CU-06 E1/E2/E3: documentos repetidos. */
    public function testDocumentosRepetidos(): void
    {
        $this->esperaFalta(fn() => $this->alta(['titular' => ['doc' => '28913458']]), 'ya está en el padrón (afiliado N° 75)');
        $this->esperaFalta(fn() => $this->alta(['familia' => [['apellido' => 'x', 'nombre1' => 'y', 'doc' => '27618173', 'nac' => '2000-01-01', 'categoria' => 'CON']]]), 'ya está afiliado');
        $this->esperaFalta(fn() => $this->alta(['familia' => [['apellido' => 'x', 'nombre1' => 'y', 'doc' => '41.222.333', 'nac' => '2000-01-01', 'categoria' => 'CON']]]), 'mismo documento');
    }

    /** CU-06 A1 y E5: email obligatorio salvo "No tiene email"; precio de todas las categorías. */
    public function testEmailYPrecio(): void
    {
        $this->esperaFalta(fn() => $this->alta(['email' => '']), 'email es obligatorio');
        $r = $this->alta(['email' => '', 'sin_email' => true]);
        self::assertTrue((bool)Padron::grupo($r['grupo_id'])['sin_email']);
        App::$db->exec("UPDATE precios SET precio = 0 WHERE plan_id = 'INT' AND categoria_id = 'TIT'");
        Catalogo::limpiar();
        $this->esperaFalta(fn() => $this->alta(['plan' => 'INT', 'titular' => ['doc' => '41222350']]), 'todavía no tiene precio para todas las categorías');
    }

    /** CU-07: la cuota del grupo es la suma de los activos con la promoción vigente. */
    public function testCuotaDelGrupoEnLaFicha(): void
    {
        $a = $this->afi(70);
        $g = Padron::grupo($a['grupo_id']);
        $suma = array_sum(array_map(fn($m) => $m['cuota'], Padron::activosEn($g['id'])));
        self::assertSame($suma, Padron::cuotaActual($g));
        self::assertSame(140500.0, $suma);
    }

    /** CU-08: cambiar el teléfono registra "teléfono: … → …" con el usuario. */
    public function testEdicionRegistraAntesYDespues(): void
    {
        $a = $this->afi(75);
        Padron::editar($a['id'], ['apellido' => $a['apellido'], 'nombre1' => $a['nombre1'], 'nombre2' => $a['nombre2'], 'doc' => $a['doc'], 'categoria' => $a['categoria_id'], 'cuota' => (string)$a['cuota'], 'telefono' => '3435999999', 'fecha_alta' => $a['fecha_alta'], 'obs' => $a['obs'], 'nac' => $a['nac']]);
        $h = App::$db->one("SELECT * FROM historial WHERE accion = 'Modificación' ORDER BY id DESC LIMIT 1");
        self::assertStringContainsString('teléfono: 03435 15 401234 → 3435999999', $h['detalle']);
        self::assertSame('Santiago', $h['usuario']);
    }

    /** CU-08: pasar a Responsable Inscripto hace que la próxima factura sea A con el CUIT. */
    public function testResponsableInscriptoFacturaA(): void
    {
        $a = $this->afi(91);
        Padron::editar($a['id'], ['apellido' => $a['apellido'], 'nombre1' => $a['nombre1'], 'doc' => $a['doc'], 'categoria' => $a['categoria_id'], 'cuota' => (string)$a['cuota'], 'telefono' => $a['telefono'], 'fecha_alta' => $a['fecha_alta'], 'fcond' => 'RI', 'fcuit' => '23135347884', 'frazon' => 'brun mabel', 'email' => 'mabel.brun@example.com', 'forma_pago' => 'Mercado Pago', 'plan' => 'PLUS']);
        $g = Padron::grupo($a['grupo_id']);
        self::assertSame('A', \App\Domain\Caja::letra($g));
        self::assertSame('CUIT 23-13534788-4', \App\Domain\Caja::receptor($g, $a)['doc']);
    }

    /** CU-08 E1 y CU-04: un usuario no cambia la cuota sin la clave de un administrador. */
    public function testCambioDeCuotaRequiereAdministrador(): void
    {
        $this->como('Belén');
        $a = $this->afi(62);
        $this->expectException(RequiereAdmin::class);
        Padron::editar($a['id'], ['apellido' => $a['apellido'], 'nombre1' => $a['nombre1'], 'doc' => $a['doc'], 'categoria' => 'TIT', 'cuota' => '20000', 'fecha_alta' => $a['fecha_alta']]);
    }

    /** CU-09: con promoción hasta enero 2027 la propuesta de inicio es febrero 2027. */
    public function testInicioPropuestoDeLaPromocion(): void
    {
        $g = Padron::grupo($this->afi(62)['grupo_id']);
        $g['promo'] = ['pct' => 10, 'meses' => 3, 'desde' => '2026-11', 'hasta' => '2027-01', 'retencion' => false];
        self::assertSame('2027-02', Padron::inicioPromoPropuesto($g, '2026-11-15'));
        $g['promo'] = null;
        self::assertSame('2026-12', Padron::inicioPromoPropuesto($g, '2026-11-15'));
        $this->esperaFalta(fn() => Padron::aplicarPromo($g['id'], 10, 3, '2026-10'), 'no puede empezar antes');
    }

    /** CU-10: "Dar de baja igual" solo después de rechazar todas (salvo fallecimiento). */
    public function testBajaConRetencion(): void
    {
        $a = $this->afi(62);
        $yo = App::$usuario;
        $this->esperaFalta(fn() => Retencion::registrar($a['id'], 'economico', '', 0, 'baja', $yo, 'ficha'), 'en orden');
        $n = count(Retencion::ofertas($a, 'economico')['L']);
        Retencion::registrar($a['id'], 'economico', '', $n, 'baja', $yo, 'ficha');
        $a = $this->afi(62);
        self::assertFalse($a['activo']);
        self::assertSame(Fechas::hoy(), $a['fecha_baja']);
        $b = $this->afi(65);
        Retencion::registrar($b['id'], 'fallecimiento', '', 0, 'baja', $yo, 'ficha');
        self::assertFalse($this->afi(65)['activo']);
        self::assertSame('baja', $this->val("SELECT resultado FROM retenciones WHERE afiliado_id = :a ORDER BY id DESC LIMIT 1", ['a' => $b['id']]));
    }

    /** CU-10: un usuario de atención no da de baja. CU-11: reactivar conserva N° y alta. */
    public function testBajaSoloAdministradorYReactivacion(): void
    {
        $a = $this->afi(62);
        $this->como('Belén');
        $this->esperaFalta(fn() => Retencion::registrar($a['id'], 'fallecimiento', '', 0, 'baja', App::$usuario), 'Solo un administrador');
        $this->como('Santiago');
        Padron::darDeBaja($a['id'], 'otro');
        Padron::reactivar($a['id']);
        $r = $this->afi(62);
        self::assertTrue($r['activo']);
        self::assertNull($r['fecha_baja']);
        self::assertSame($a['fecha_alta'], $r['fecha_alta']);
    }

    /** CU-12: con motivo económico y 4 meses de antigüedad no aparece el 20 %. */
    public function testSinVeintePorCientoConPocaAntiguedad(): void
    {
        $ks = array_column(Retencion::ofertas($this->afi(509), 'economico')['L'], 'k');
        self::assertNotContains('d20', $ks);
        self::assertContains('d10', $ks);
        $ks70 = array_column(Retencion::ofertas($this->afi(70), 'economico')['L'], 'k'); // 6 meses o más y al día
        self::assertContains('d20', $ks70);
    }

    /** CU-12: si el grupo fue retenido en los últimos 365 días no se ofrecen descuentos. */
    public function testSinDescuentosSiYaFueRetenido(): void
    {
        $of = Retencion::ofertas($this->afi(77), 'economico');
        self::assertNotNull($of['prev']);
        self::assertSame([], array_values(array_filter($of['L'], fn($o) => $o['tipo'] === 'promo')));
    }

    /** CU-12: un usuario no puede aplicar el 10 %: lo deriva. Aceptar un descuento crea la promoción. */
    public function testUsuarioDerivaYAdministradorAplica(): void
    {
        $a = $this->afi(509);
        $L = Retencion::ofertas($a, 'nouso')['L'];
        $i = array_search('d10', array_column($L, 'k'), true);
        $this->como('Belén');
        $this->esperaFalta(fn() => Retencion::registrar($a['id'], 'nouso', '', $i, 'acepto', App::$usuario), 'administración');
        $r = Retencion::registrar($a['id'], 'nouso', '', $i, 'derivar', App::$usuario);
        self::assertSame('derivado', $r['resultado']);
        $this->como('Santiago');
        Retencion::registrar($a['id'], 'nouso', '', $i, 'acepto', App::$usuario);
        $g = Padron::grupo($a['grupo_id']);
        self::assertSame(10, $g['promo']['pct']);
        self::assertTrue($g['promo']['retencion']);
        self::assertSame(3, $g['promo']['meses']);
        // Cada registro nuevo cierra los pendientes anteriores.
        self::assertSame(0, (int)$this->val("SELECT count(*) FROM retenciones WHERE afiliado_id = :a AND cerrado IS NULL AND resultado = 'derivado'", ['a' => $a['id']]));
    }

    /** CU-12 A2: "Lo va a pensar" aparece en "Para llamar hoy" a los 3 días. */
    public function testLoVaAPensar(): void
    {
        self::assertContains(69, array_map('intval', array_column(Retencion::paraLlamar(), 'nro')));
        $a = $this->afi(76);
        Retencion::registrar($a['id'], 'otro', '', 0, 'piensa', App::$usuario);
        $llamar = $this->val("SELECT llamar FROM retenciones WHERE afiliado_id = :a ORDER BY id DESC LIMIT 1", ['a' => $a['id']]);
        self::assertSame(Fechas::sumarDias(Fechas::hoy(), 3), $llamar);
        self::assertNotContains(76, array_map('intval', array_column(Retencion::paraLlamar(), 'nro')));
        self::assertContains(76, array_map('intval', array_column(Retencion::paraLlamar(Fechas::sumarDias(Fechas::hoy(), 3)), 'nro')));
    }

    /** CU-13: un caso confirmado desaparece de "Para resolver" y suma en "Se fueron". */
    public function testConfirmarBajaPedida(): void
    {
        $p = Retencion::panel();
        $caso = array_values(array_filter($p['pend'], fn($r) => $r['resultado'] === 'baja_pedida'))[0];
        Retencion::confirmarBaja((int)$caso['id'], App::$usuario);
        $p2 = Retencion::panel();
        self::assertNotContains((int)$caso['id'], array_map('intval', array_column($p2['pend'], 'id')));
        self::assertSame($p['bajas'] + 1, $p2['bajas']);
        self::assertFalse($this->afi(72)['activo']);
    }

    /** CU-14: un afiliado saludado no vuelve a aparecer; los dados de baja no aparecen. */
    public function testCumpleanios(): void
    {
        $ids = array_column(Comun::cumpleanios(), 'nro');
        self::assertContains(80, array_map('intval', $ids));
        $a = $this->afi(80);
        App::$db->exec('INSERT INTO saludos_cumple (afiliado_id, anio) VALUES (:a, :y)', ['a' => $a['id'], 'y' => (int)substr(Fechas::hoy(), 0, 4)]);
        self::assertNotContains(80, array_map('intval', array_column(Comun::cumpleanios(), 'nro')));
        App::$db->exec("UPDATE afiliados SET nac = :n, estado = 'baja' WHERE nro = 76", ['n' => $a['nac']]);
        self::assertNotContains(76, array_map('intval', array_column(Comun::cumpleanios(), 'nro')));
    }
}
