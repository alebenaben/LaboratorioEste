<?php
declare(strict_types=1);

namespace Tests\Integracion;

use App\Core\App;
use App\Core\Config;
use App\Domain\Caja;
use App\Domain\Fechas;
use App\Domain\Fmt;
use App\Domain\Padron;
use App\Service\Arca\Arca;
use App\Service\Arca\ArcaSimulado;
use App\Service\Arca\ArcaWebService;
use App\Service\Comprobantes;
use App\Service\Worker;

/** CU-26 a CU-32. */
final class CajaTest extends CasoIntegracion
{
    private function periodo(): string
    {
        return max(substr(Fechas::hoy(), 0, 7), Config::inicio()['periodo']);
    }

    /** CU-26: correr la facturación dos veces para el mismo mes no duplica ninguna factura. */
    public function testFacturarDosVecesNoDuplica(): void
    {
        $per = $this->periodo();
        $fs = Caja::facturar($per, 'Santiago');
        self::assertNotEmpty($fs);
        $this->esperaFalta(fn() => Caja::facturar($per, 'Santiago') ?: throw new \App\Core\Falta('Todos los grupos ya tienen la factura'), 'ya tienen la factura');
        self::assertSame(count($fs), (int)$this->val('SELECT count(*) FROM facturas WHERE periodo = :p AND pv = 1', ['p' => $per]));
        self::assertSame(0, (int)$this->val('SELECT count(*) - count(DISTINCT grupo_id) FROM facturas WHERE periodo = :p', ['p' => $per]));
    }

    /** CU-26: con último N° B configurado en 120, la primera factura B sale 00001-00000121. */
    public function testNumeracionDesdeElUltimoConfigurado(): void
    {
        Config::set('ultimos_numeros', ['B' => 120, 'A' => 7]);
        self::assertSame('00001-00000121', Caja::proximaFactura('B'));
        Caja::facturar($this->periodo(), 'Santiago');
        self::assertSame(121, (int)$this->val("SELECT min(numero) FROM facturas WHERE letra = 'B' AND pv = 1"));
        self::assertSame(8, (int)$this->val("SELECT min(numero) FROM facturas WHERE letra = 'A' AND pv = 1"));
    }

    /** CU-26: un afiliado dado de alta después del fin del mes no se factura en ese mes. */
    public function testAltaPosteriorNoSeFactura(): void
    {
        $per = $this->periodo();
        $sig = Fechas::perMas($per, 1);
        $g = Padron::grupo($this->afi(62)['grupo_id']);
        App::$db->exec("UPDATE afiliados SET fecha_alta = :f WHERE nro = 62", ['f' => "$sig-05"]);
        self::assertSame(0.0, Padron::cuotaGrupo($g, $per));
        Caja::facturar($per, 'Santiago');
        self::assertSame(0, (int)$this->val('SELECT count(*) FROM facturas WHERE grupo_id = :g AND periodo = :p', ['g' => $g['id'], 'p' => $per]));
    }

    /** CU-26: Factura A para Responsables Inscriptos, B para el resto; vencimiento el día configurado. */
    public function testLetraYVencimiento(): void
    {
        $per = $this->periodo();
        Caja::facturar($per, 'Santiago');
        $a = App::$db->one("SELECT * FROM facturas WHERE grupo_id = :g AND periodo = :p", ['g' => $this->afi(74)['grupo_id'], 'p' => $per]);
        self::assertSame('A', $a['letra']);
        self::assertSame("$per-10", $a['vto']);
        self::assertSame('RI', json_decode($a['receptor'], true)['cond']);
        $b = App::$db->one("SELECT * FROM facturas WHERE grupo_id = :g AND periodo = :p", ['g' => $this->afi(75)['grupo_id'], 'p' => $per]);
        self::assertSame('B', $b['letra']);
    }

    /** CU-26 E1: no se factura antes del inicio del sistema. */
    public function testNoFacturaAntesDelInicio(): void
    {
        $this->esperaFalta(fn() => Caja::facturar('2026-10', 'Santiago'), 'El sistema factura desde noviembre 2026');
    }

    /** CU-27: el worker pide el CAE en orden (ARCA simulado) y una factura sin CAE no se manda por mail. */
    public function testCaeYMail(): void
    {
        $per = $this->periodo();
        Caja::facturar($per, 'Santiago');
        $f = Caja::factura((int)$this->val("SELECT id FROM facturas WHERE grupo_id = :g AND periodo = :p", ['g' => $this->afi(75)['grupo_id'], 'p' => $per]));
        $this->esperaFalta(fn() => Caja::encolarMail('factura', $f['id']), 'todavía no tiene CAE');
        $w = new Worker();
        $w->usarFacturador(new ArcaSimulado());
        $w->procesar(10);
        self::assertSame(0, (int)$this->val("SELECT count(*) FROM facturas WHERE pv = 1 AND cae_estado <> 'autorizada'"));
        self::assertMatchesRegularExpression('/^76\d{12}$/', (string)$this->val('SELECT cae FROM facturas WHERE id = :i', ['i' => $f['id']]));
        Caja::encolarMail('factura', $f['id']);
        self::assertSame('encolado', $this->val('SELECT mail_estado FROM facturas WHERE id = :i', ['i' => $f['id']]));
    }

    /** CU-27: ARCA real con respuestas de ejemplo (WSFEv1): CAE aprobado y rechazo con observaciones. */
    public function testClienteWsfeConRespuestasDeEjemplo(): void
    {
        App::$db->insert('config', ['clave' => 'arca_ta_homo', 'valor' => json_encode(['token' => 'T', 'sign' => 'S', 'vence' => date('c', time() + 3600)])], 'clave');
        Config::limpiar();
        putenv('ARCA_CUIT=30716447495');
        $ws = new ArcaWebService(false);
        $pedidos = [];
        $ws->transporte = function (string $url, string $accion, string $xml) use (&$pedidos) {
            $pedidos[] = $xml;
            if (str_contains($accion, 'FECompUltimoAutorizado')) {
                return '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body><FECompUltimoAutorizadoResponse xmlns="http://ar.gov.afip.dif.FEV1/"><FECompUltimoAutorizadoResult><PtoVta>1</PtoVta><CbteTipo>6</CbteTipo><CbteNro>120</CbteNro></FECompUltimoAutorizadoResult></FECompUltimoAutorizadoResponse></soap:Body></soap:Envelope>';
            }
            if (str_contains($xml, '<ar:ImpTotal>-1')) {
                return '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body><FECAESolicitarResponse xmlns="http://ar.gov.afip.dif.FEV1/"><FECAESolicitarResult><FeCabResp><Resultado>R</Resultado></FeCabResp><FeDetResp><FECAEDetResponse><Resultado>R</Resultado><Observaciones><Obs><Code>10016</Code><Msg>El campo ImpTotal es invalido</Msg></Obs></Observaciones><CAE></CAE></FECAEDetResponse></FeDetResp></FECAESolicitarResult></FECAESolicitarResponse></soap:Body></soap:Envelope>';
            }
            return '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body><FECAESolicitarResponse xmlns="http://ar.gov.afip.dif.FEV1/"><FECAESolicitarResult><FeCabResp><Resultado>A</Resultado></FeCabResp><FeDetResp><FECAEDetResponse><Resultado>A</Resultado><CAE>76123456789012</CAE><CAEFchVto>20261112</CAEFchVto></FECAEDetResponse></FeDetResp></FECAESolicitarResult></FECAESolicitarResponse></soap:Body></soap:Envelope>';
        };
        self::assertSame(120, $ws->ultimoAutorizado(1, 6));
        $c = ['tipo' => 6, 'pv' => 1, 'numero' => 121, 'fecha' => '2026-11-02', 'doc_tipo' => 96, 'doc_nro' => '28913458', 'cond_iva' => 5, 'total' => 25000.0, 'neto' => 22624.43, 'iva' => 2375.57, 'exento' => 0.0, 'alicuota' => 10.5, 'serv_desde' => '2026-11-01', 'serv_hasta' => '2026-11-30', 'vto_pago' => '2026-11-10'];
        self::assertSame(['cae' => '76123456789012', 'vto' => '2026-11-12'], $ws->autorizar($c));
        self::assertStringContainsString('<ar:Id>4</ar:Id><ar:BaseImp>22624.43</ar:BaseImp><ar:Importe>2375.57</ar:Importe>', end($pedidos));
        self::assertStringContainsString('<ar:CondicionIVAReceptorId>5</ar:CondicionIVAReceptorId>', end($pedidos));
        try {
            $ws->autorizar(['total' => -1.0] + $c);
            self::fail('Se esperaba el rechazo');
        } catch (\App\Service\Arca\ArcaRechazo $e) {
            self::assertStringContainsString('10016', $e->getMessage());
        }
    }

    /** Datos que se mandan a ARCA desde una factura: neto e IVA del 10,5 %. */
    public function testDatosParaArca(): void
    {
        $f = Caja::factura((int)$this->val("SELECT id FROM facturas WHERE pv = 9 AND letra = 'B' AND numero = 2"));
        $c = Arca::desdeFactura($f);
        self::assertSame(6, $c['tipo']);
        self::assertSame(96, $c['doc_tipo']);
        self::assertSame(2375.57, $c['iva']);
        self::assertSame(22624.43, $c['neto']);
        $fa = Caja::factura((int)$this->val("SELECT id FROM facturas WHERE pv = 9 AND letra = 'A'"));
        self::assertSame(80, Arca::desdeFactura($fa)['doc_tipo']);
        self::assertSame(1, Arca::desdeFactura($fa)['cond_iva']);
    }

    /** CU-30: cobrar la misma factura dos veces genera un solo recibo. */
    public function testCobrarDosVeces(): void
    {
        $fid = (int)$this->val("SELECT id FROM facturas WHERE pv = 9 AND letra = 'B' AND numero = 1");
        $r = Caja::cobrar($fid, 'Efectivo', Fechas::hoy(), false, 'Katy');
        self::assertMatchesRegularExpression('/^R00001-\d{8}$/', $r['nro']);
        $this->esperaFalta(fn() => Caja::cobrar($fid, 'Efectivo', Fechas::hoy(), false, 'Katy'), 'ya está pagada');
        self::assertSame(1, (int)$this->val('SELECT count(*) FROM recibos WHERE factura_id = :f', ['f' => $fid]));
        self::assertSame('al_dia', Padron::cuenta($this->afi(68)['grupo_id'])['estado']);
    }

    /** CU-29: un recibo con fecha 12/11 aparece en la cuenta corriente el 12/11, no el día de carga. */
    public function testDebitoConFechaDePago(): void
    {
        $fid = (int)$this->val("SELECT id FROM facturas WHERE pv = 9 AND letra = 'B' AND numero = 2");
        $fecha = Fechas::sumarDias(Fechas::hoy(), -2);
        Caja::cobrar($fid, 'Débito automático', $fecha, true, 'Katy');
        $mov = array_values(array_filter(Caja::cuentaCorriente($this->afi(80)['grupo_id']), fn($m) => $m['tipo'] === 'Recibo'));
        self::assertSame($fecha, $mov[0]['fecha']);
    }

    /** CU-30 / CU-31: el recibo dice el importe en letras y no menciona quién cobró (RT-10). */
    public function testReciboSinQuienCobro(): void
    {
        $fid = (int)$this->val("SELECT id FROM facturas WHERE pv = 9 AND letra = 'B' AND numero = 2");
        $r = Caja::cobrar($fid, 'Efectivo', Fechas::hoy(), false, 'Katy');
        $html = Comprobantes::htmlRecibo($r);
        self::assertStringContainsString('veinticinco mil', $html);
        self::assertStringNotContainsString('Katy', $html);
        $pdf = Comprobantes::pdfRecibo($r);
        self::assertStringStartsWith('%PDF', $pdf);
    }

    /** CU-31: el PDF de una factura B de $ 25.000 al 10,5 % muestra IVA contenido $ 2.375,57. */
    public function testIvaContenidoEnLaFactura(): void
    {
        $f = Caja::factura((int)$this->val("SELECT id FROM facturas WHERE pv = 9 AND letra = 'B' AND numero = 2"));
        $html = Comprobantes::htmlFactura($f);
        self::assertStringContainsString('Ley 27.743', $html);
        self::assertStringContainsString(Fmt::money(2375.57), $html);
        $a = Caja::factura((int)$this->val("SELECT id FROM facturas WHERE pv = 9 AND letra = 'A'"));
        self::assertStringContainsString('Neto gravado', Comprobantes::htmlFactura($a));
    }

    /** CU-32: el saldo coincide con facturas menos recibos; cobrar 3 cuotas genera 3 recibos. */
    public function testCuentaCorriente(): void
    {
        $g = $this->afi(68)['grupo_id'];
        foreach ([2, 3] as $n) {
            App::$db->exec("INSERT INTO facturas (letra, pv, numero, grupo_id, plan_id, periodo, emision, vto, importe, base, receptor, lineas, estado) VALUES ('B', 8, :n, :g, 'PLUS', :p, current_date, current_date + 10, 45000, 45000, '{}', '[]', 'pendiente')", ['n' => $n, 'g' => $g, 'p' => "2030-0$n"]);
        }
        $c = Padron::cuenta($g);
        $mov = Caja::cuentaCorriente($g);
        self::assertSame($c['saldo'], end($mov)['saldo']);
        self::assertSame(135000.0, $c['saldo']);
        $antes = (int)$this->val('SELECT count(*) FROM recibos');
        foreach ($c['pendientes'] as $f) {
            Caja::cobrar((int)$f['id'], 'Efectivo', Fechas::hoy(), false, 'Santiago');
        }
        self::assertSame($antes + 3, (int)$this->val('SELECT count(*) FROM recibos'));
        self::assertSame(0.0, Padron::cuenta($g)['saldo']);
    }

    /** CU-28: de las facturas con email y CAE se encolan solo las no enviadas. */
    public function testEnvioMasivoNoReenvia(): void
    {
        $per = $this->periodo();
        Caja::facturar($per, 'Santiago');
        $w = new Worker();
        $w->usarFacturador(new ArcaSimulado());
        $w->procesar(5);
        $e = Caja::estadoMails($per);
        $n = Caja::enviarMailsPeriodo($per);
        self::assertSame(count($e['aEnviar']), $n);
        self::assertSame(0, Caja::enviarMailsPeriodo($per) ?: 0);
        self::assertSame($n, (int)$this->val("SELECT count(*) FROM cola_tareas WHERE tipo = 'mail'"));
    }
}
