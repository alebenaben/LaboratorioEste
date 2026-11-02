<?php
declare(strict_types=1);

namespace Tests\Integracion;

use App\Core\App;
use App\Core\AutorizacionAdmin;
use App\Core\Config;
use App\Core\RequiereAdmin;
use App\Domain\Caja;
use App\Domain\Catalogo;
use App\Domain\Configuracion;
use App\Domain\Fechas;
use App\Domain\Gestion;
use App\Domain\Mails;
use App\Domain\Promociones;
use App\Service\Informes;

/** CU-04, CU-18, CU-19, CU-33 a CU-52. */
final class GestionConfigTest extends CasoIntegracion
{
    /** CU-33: un egreso Factura A de $ 12.100 con IVA 21 % tiene IVA $ 2.100; el IVA no puede igualar el total. */
    public function testEgresoConIva(): void
    {
        $id = Gestion::guardarEgreso(['fecha' => Fechas::hoy(), 'categoria' => 'servicios', 'proveedor' => 'Luz', 'comprobante' => 'Factura A', 'importe' => '12.100', 'iva' => '2.100', 'medio' => 'Efectivo'], 'Katy');
        self::assertSame(2100.0, (float)$this->val('SELECT iva FROM egresos WHERE id = :i', ['i' => $id]));
        $this->esperaFalta(fn() => Gestion::guardarEgreso(['categoria' => 'servicios', 'proveedor' => 'x', 'comprobante' => 'Factura A', 'importe' => '100', 'iva' => '100'], 'Katy'), 'IVA');
        $this->esperaFalta(fn() => Gestion::guardarEgreso(['categoria' => 'servicios', 'proveedor' => 'x', 'importe' => '0'], 'Katy'), 'Cargá el importe');
    }

    /** CU-33: el alquiler fijo del mes pasado aparece en "Gastos fijos sin cargar" hasta que se carga. */
    public function testGastosFijos(): void
    {
        $m = substr(Fechas::hoy(), 0, 7);
        $p = Gestion::resumenEgresos($m)['fijosPend'];
        self::assertContains('Inmobiliaria del Este', array_column($p, 'proveedor'));
        Gestion::guardarEgreso(['fecha' => Fechas::hoy(), 'categoria' => 'alquiler', 'proveedor' => 'INMOBILIARIA DEL ESTE', 'importe' => '12100', 'fijo' => 1], 'Katy');
        self::assertNotContains('Inmobiliaria del Este', array_column(Gestion::resumenEgresos($m)['fijosPend'], 'proveedor'));
    }

    /** CU-34: facturar $ 100.000 y cobrar $ 60.000 en efectivo: 1.3 = 40.000, 1.1 = 60.000; debe = haber. */
    public function testContabilidad(): void
    {
        App::$db->exec('DELETE FROM recibos');
        App::$db->exec('DELETE FROM facturas');
        App::$db->exec('DELETE FROM egresos');
        $g = $this->afi(75)['grupo_id'];
        App::$db->exec("INSERT INTO facturas (letra, pv, numero, grupo_id, plan_id, periodo, emision, vto, importe, base, receptor, lineas) VALUES ('B', 8, 1, :g, 'PLUS', '2030-01', current_date, current_date, 60000, 60000, '{}', '[]'), ('B', 8, 2, :g, 'PLUS', '2030-02', current_date, current_date, 40000, 40000, '{}', '[]')", ['g' => $g]);
        Caja::cobrar((int)$this->val('SELECT id FROM facturas WHERE numero = 1 AND pv = 8'), 'Efectivo', Fechas::hoy(), false, 'Katy');
        $s = Gestion::saldos();
        self::assertSame(40000.0, $s['1.3']);
        self::assertSame(60000.0, $s['1.1']);
        self::assertSame(100000.0, $s['4.1']);
        $d = Gestion::informe('diario', '2000-01-01', Fechas::hoy());
        self::assertEquals($d['tot'][3], $d['tot'][4]);
    }

    /** CU-35: la deuda vencida coincide con la suma de facturas pendientes vencidas. */
    public function testDeudaVencida(): void
    {
        $esperado = (float)$this->val("SELECT sum(importe) FROM facturas WHERE estado = 'pendiente' AND vto < current_date");
        self::assertSame($esperado, Gestion::indicadores()['deuda']);
        self::assertSame(1, Gestion::indicadores()['morosos']);
    }

    /** CU-35: el ahorro total coincide con el informe "Usos de beneficios" del mismo período. */
    public function testAhorroCoincideConElInforme(): void
    {
        $a = Gestion::ahorro('anio');
        [$d, $h] = \App\Domain\Documentos::rangoAhorro('anio');
        $inf = Gestion::informe('usos', $d, $h);
        self::assertEqualsWithDelta($inf['tot'][9], $a['tot'], 0.01);
    }

    /** CU-36: un grupo sin usos no aparece; repetir el envío no duplica mails. */
    public function testResumenMensual(): void
    {
        $per = substr(Fechas::hoy(), 0, 7);
        $L = Gestion::gruposConUso($per);
        $ids = array_map(fn($x) => $x['t']['nro'], $L);
        self::assertContains(75, $ids);
        self::assertNotContains(62, $ids);
        $n = Gestion::enviarResumenes($per);
        self::assertGreaterThan(0, $n);
        self::assertSame(0, Gestion::enviarResumenes($per));
    }

    /** CU-38: entre dos fechas invertidas se ordenan solas; el Excel trae todas las filas. */
    public function testInformes(): void
    {
        [$d, $h] = Gestion::rango('entre', '', '2026-12-31', '2026-01-01');
        self::assertSame(['2026-01-01', '2026-12-31'], [$d, $h]);
        $p = Gestion::informe('padron', $d, $h);
        self::assertCount((int)$this->val("SELECT count(*) FROM afiliados WHERE estado = 'activo'"), $p['filas']);
        $x = Informes::excel('Padrón de afiliados', 'hoy', $p);
        self::assertStringStartsWith('PK', $x);
        $vacio = Gestion::informe('egresos', '2000-01-01', '2000-01-31');
        self::assertSame([], $vacio['filas']);
        $pdf = \App\Service\Pdf::generar('informe', ['titulo' => 'Egresos', 'periodo' => 'x', 'd' => $vacio, 'usuario' => 'Katy', 'emision' => 'hoy']);
        self::assertStringStartsWith('%PDF', $pdf);
    }

    /** CU-18: una promoción con "hasta" ayer está terminada; la vigente de Óptica la ve la unidad. */
    public function testEstadoDePromociones(): void
    {
        $t = array_values(array_filter(Promociones::todas(), fn($p) => $p['titulo'] === 'Invierno saludable'))[0];
        self::assertSame('Terminada', Promociones::estado($t)[0]);
        self::assertSame('Programada', Promociones::estado(array_values(array_filter(Promociones::todas(), fn($p) => $p['titulo'] === 'Mes del corazón'))[0])[0]);
        self::assertSame(['Llegó el verano'], array_column(Promociones::vigentesDeUnidad('Óptica'), 'titulo'));
        $this->esperaFalta(fn() => Promociones::guardar(null, ['titulo' => 'x', 'beneficio' => 'y', 'desde' => '2026-12-10', 'hasta' => '2026-12-01']), 'hasta');
    }

    /** CU-19: repetir el envío masivo no duplica; cada mail lleva el PDF con el nombre de ese afiliado. */
    public function testEnvioDePromocion(): void
    {
        $p = array_values(array_filter(Promociones::todas(), fn($x) => $x['titulo'] === 'Llegó el verano'))[0];
        $dest = Promociones::destinatarios($p);
        $con = array_filter($dest, fn($x) => !$x['envio'] && $x['g']['email']);
        $n = 0;
        foreach ($con as $x) {
            $n += (int)Promociones::marcarEnvio($p['id'], $x['g']['id'], 'Mail');
        }
        self::assertSame(count($con), $n);
        foreach ($con as $x) {
            self::assertFalse(Promociones::marcarEnvio($p['id'], $x['g']['id'], 'Mail'));
        }
        $m = Mails::armar('promo', Promociones::vars($p, $dest[0]['t']));
        self::assertStringContainsString('Llegó el verano', $m['asunto']);
    }

    /** CU-48: una variable desconocida queda escrita tal cual; cambiar el asunto se refleja en la vista previa. */
    public function testPlantillasDeMail(): void
    {
        self::assertSame('Hola Ana {raro}', Mails::llenar('Hola {nombre} {raro}', ['nombre' => 'Ana']));
        $mail = Config::get('mail');
        $mail['factura']['asunto'] = 'Tu cuota {periodo}';
        Config::set('mail', $mail);
        $f = Caja::factura((int)$this->val("SELECT id FROM facturas WHERE pv = 9 AND letra = 'B' AND numero = 2"));
        self::assertStringStartsWith('Tu cuota ', Mails::armar('factura', Mails::varsFactura($f))['asunto']);
    }

    /** CU-04: con "Modificar montos" activado un usuario necesita la clave; el token es de un solo uso y de una acción. */
    public function testAutorizacionDeAdministrador(): void
    {
        $this->como('Belén');
        try {
            AutorizacionAdmin::exigir('montos');
            self::fail('Se esperaba que pida un administrador');
        } catch (RequiereAdmin $e) {
            self::assertSame('montos', $e->accion);
        }
        $tok = AutorizacionAdmin::emitir('KATY', 'Katy', 'montos');
        self::assertFalse(AutorizacionAdmin::consumir($tok, 'editar', App::$usuario['id']));
        self::assertTrue(AutorizacionAdmin::consumir($tok, 'montos', App::$usuario['id']));
        self::assertSame('Katy', App::$autorizo);
        self::assertFalse(AutorizacionAdmin::consumir($tok, 'montos', App::$usuario['id']));
        $this->esperaFalta(fn() => AutorizacionAdmin::emitir('Belén', 'Belén', 'montos'), 'no es administrador');
        // CU-47: desmarcado, no se pide.
        Config::set('permisos', ['editar' => true, 'descuentos' => true, 'montos' => false, 'padron' => true]);
        AutorizacionAdmin::exigir('montos');
        self::assertTrue(true);
    }

    /** CU-39: nombre único sin tildes ni mayúsculas; siempre queda un administrador; nadie se da de baja solo. */
    public function testUsuarios(): void
    {
        $this->esperaFalta(fn() => Configuracion::crearUsuario(['nombre' => 'belen']), 'Ya existe');
        $this->esperaFalta(fn() => Configuracion::crearUsuario(['nombre' => 'nuevo', 'clave' => 'abc']), '4 caracteres');
        $yo = App::$usuario;
        $this->esperaFalta(fn() => Configuracion::editarUsuario($yo['id'], 'estado', []), 'a vos mismo');
        $this->esperaFalta(fn() => Configuracion::editarUsuario($yo['id'], 'rol', []), 'tu propio rol');
        App::$db->exec("UPDATE usuarios SET rol = 'usuario' WHERE rol = 'admin' AND nombre <> 'Katy' AND nombre <> 'Santiago'");
        $katy = \App\Core\Usuarios::porNombre(App::$db, 'Katy');
        App::$db->exec("UPDATE usuarios SET rol = 'usuario' WHERE nombre = 'Santiago'");
        $this->como('Katy');
        $s = \App\Core\Usuarios::porNombre(App::$db, 'Santiago');
        $this->esperaFalta(fn() => Configuracion::editarUsuario($katy['id'], 'estado', []), 'a vos mismo');
        Configuracion::crearUsuario(['nombre' => 'Nueva Persona', 'tipo' => 'usuario']);
        $n = \App\Core\Usuarios::porNombre(App::$db, 'nueva persona');
        self::assertSame(['altas', 'cotizador', 'autorizaciones', 'beneficios', 'retencion'], $n['secciones']);
        self::assertTrue($n['debe_cambiar_clave']);
        Configuracion::editarUsuario($n['id'], 'secciones', ['secciones' => ['altas', 'afiliados']]);
        self::assertSame(['altas'], \App\Core\Usuarios::porId(App::$db, $n['id'])['secciones']);
    }

    /** CU-49: cambios con alertas, clave incorrecta, deshacer solo el último y sin tocar las cuotas (P-12). */
    public function testConfirmarYDeshacer(): void
    {
        $p = Configuracion::preparar('reglas', ['r' => ['PLUS:B01' => ['gratis' => 12, 'per' => 'anio', 'por_grupo' => 1, 'car_gratis' => 180, 'desc' => 50, 'modo' => 'fijo', 'car' => 210, 'escala' => '', 'max_desc' => 0]]]);
        self::assertContains('Sesiones sin cargo: 5 → 12.', $p['alertas']);
        $this->esperaFalta(fn() => Configuracion::confirmar('cfg', ['dia_vto' => 15], 'mala'), 'La clave no es correcta');
        $this->esperaFalta(fn() => Configuracion::confirmar('cfg', ['dia_vto' => 10], 'Santiago'), 'No hubo cambios');
        Configuracion::confirmar('cfg', ['dia_vto' => 31], 'Santiago');
        Config::limpiar();
        self::assertSame(28, Config::diaVto());
        Configuracion::confirmar('precios', ['precio' => ['PLUS:TIT' => '26.000'], 'aplicar_actuales' => '1'], 'Santiago');
        Catalogo::limpiar();
        self::assertSame(26000.0, Catalogo::precio('TIT', 'PLUS'));
        self::assertSame(26000.0, (float)$this->val('SELECT cuota FROM afiliados WHERE nro = 75'));
        $ids = array_map('intval', App::$db->col('SELECT id FROM config_cambios ORDER BY id DESC'));
        $this->esperaFalta(fn() => Configuracion::deshacer($ids[1]), 'más reciente');
        Configuracion::deshacer($ids[0]);
        Catalogo::limpiar();
        self::assertSame(25000.0, Catalogo::precio('TIT', 'PLUS'));
        self::assertSame(26000.0, (float)$this->val('SELECT cuota FROM afiliados WHERE nro = 75'));
    }

    /** CU-45: sin "aplicar a actuales" las cuotas no cambian; una cuota negociada nunca se toca. */
    public function testPreciosSinAplicarAActuales(): void
    {
        App::$db->exec('UPDATE afiliados SET cuota = 23000 WHERE nro = 62');
        $r = Configuracion::confirmar('precios', ['precio' => ['PLUS:TIT' => '27.000']], 'Santiago');
        self::assertStringContainsString('próximas altas', $r);
        self::assertSame(25000.0, (float)$this->val('SELECT cuota FROM afiliados WHERE nro = 75'));
        Configuracion::confirmar('precios', ['precio' => ['PLUS:TIT' => '28.000'], 'aplicar_actuales' => '1'], 'Santiago');
        self::assertSame(23000.0, (float)$this->val('SELECT cuota FROM afiliados WHERE nro = 62'));
    }

    /** CU-40 / CU-43: unidad nueva con su usuario; una unidad con usos no se elimina; dada de baja no ingresa. */
    public function testUnidades(): void
    {
        Configuracion::crearUnidad(['nombre' => 'Kinesiología', 'servicio' => 'Sesión de kinesiología', 'carencia' => 2, 'pct' => ['PLUS' => 20], 'gratis' => ['PLUS' => 1], 'per' => 'anio']);
        Catalogo::limpiar();
        $u = Catalogo::unidades()['Kinesiología'];
        $us = \App\Core\Usuarios::porNombre(App::$db, 'kinesiologia');
        self::assertSame('unidad', $us['rol']);
        $r = Catalogo::regla('PLUS', 'U' . sprintf('%02d', $u['id']));
        self::assertSame(60, $r['car']);
        Configuracion::estadoUnidad((int)$u['id'], false);
        self::assertFalse(\App\Core\Usuarios::porNombre(App::$db, 'kinesiologia')['activo']);
        Configuracion::eliminarUnidad((int)$u['id']);
        $bio = Catalogo::unidades()['Biobárica'];
        $this->esperaFalta(fn() => Configuracion::eliminarUnidad((int)$bio['id']), 'originales');
    }

    /** CU-41: aplicar una importación actualiza el valor y guarda fecha y usuario. */
    public function testImportacionDePrecios(): void
    {
        $f = \App\Domain\Importador::relacionar([['nombre' => 'Sesión de medicina hiperbárica', 'montos' => [14000.0], 'txt' => 'Biobárica']], 0, 'Biobárica');
        self::assertSame('B01', $f[0]['dest']);
        Configuracion::confirmar('importar', ['filas' => $f, 'quitar' => []], 'Santiago');
        Catalogo::limpiar();
        $p = Catalogo::prestacion('B01');
        self::assertSame(14000.0, $p['valor_ref']);
        self::assertSame('Santiago', $p['actualizado_por']);
    }

    /** CU-52: el historial no se puede editar ni borrar desde la aplicación. */
    public function testHistorialSoloAgregado(): void
    {
        $this->expectException(\PDOException::class);
        App::$db->exec("UPDATE historial SET detalle = 'x'");
    }
}
