<?php
declare(strict_types=1);

namespace App\Service\Arca;

use App\Core\App;
use App\Core\Env;

/**
 * Cliente de los web services de ARCA:
 *  - WSAA (LoginCms): ticket de acceso firmado con el certificado de Grupo del Este Salud S.A.S.
 *  - WSFEv1: FECAESolicitar, FECompUltimoAutorizado, FECompConsultar.
 * Arma el SOAP con curl, sin depender de la extensión soap.
 */
final class ArcaWebService implements Facturador
{
    private const WSAA = ['homo' => 'https://wsaahomo.afip.gov.ar/ws/services/LoginCms', 'prod' => 'https://wsaa.afip.gov.ar/ws/services/LoginCms'];
    private const WSFE = ['homo' => 'https://wswhomo.afip.gov.ar/wsfev1/service.asmx', 'prod' => 'https://servicios1.afip.gov.ar/wsfev1/service.asmx'];
    private const NS = 'http://ar.gov.afip.dif.FEV1/';

    /** @var callable|null para pruebas: fn(string $url, string $accion, string $xml): string */
    public $transporte = null;

    public function __construct(private bool $produccion = false)
    {
    }

    private function amb(): string
    {
        return $this->produccion ? 'prod' : 'homo';
    }

    private function cuit(): string
    {
        $c = preg_replace('/\D/', '', (string)Env::get('ARCA_CUIT', ''));
        if (strlen($c) !== 11) {
            throw new ArcaRechazo('Falta configurar ARCA_CUIT (11 dígitos).');
        }
        return $c;
    }

    // ------------------------------------------------------------------ transporte

    private function post(string $url, string $accion, string $xml): string
    {
        if ($this->transporte) {
            return ($this->transporte)($url, $accion, $xml);
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => $xml, CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60, CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_HTTPHEADER => ['Content-Type: text/xml; charset=utf-8', 'SOAPAction: "' . $accion . '"'],
        ]);
        $r = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($r === false || $code === 0) {
            throw new ArcaNoResponde("ARCA no responde: $err");
        }
        if ($code >= 500 && !str_contains((string)$r, 'Fault')) {
            throw new ArcaNoResponde("ARCA respondió $code");
        }
        return (string)$r;
    }

    private static function xml(string $s): \SimpleXMLElement
    {
        $x = @simplexml_load_string($s);
        if ($x === false) {
            throw new ArcaNoResponde('Respuesta de ARCA ilegible.');
        }
        return $x;
    }

    // ------------------------------------------------------------------ WSAA

    /** Ticket de acceso (token y sign), guardado en config hasta que vence. */
    public function ticket(): array
    {
        $ta = isset(App::$db) ? \App\Core\Config::get('arca_ta_' . $this->amb()) : null;
        if ($ta && strtotime($ta['vence']) > time() + 600) {
            return $ta;
        }
        $cert = Env::get('ARCA_CERT_FILE', '/run/secrets/arca_cert');
        $key = Env::get('ARCA_KEY_FILE', '/run/secrets/arca_key');
        if (!is_readable($cert) || !is_readable($key)) {
            throw new ArcaRechazo('No se encuentran el certificado o la clave de ARCA (ARCA_CERT_FILE / ARCA_KEY_FILE).');
        }
        $now = new \DateTimeImmutable('now', new \DateTimeZone('America/Argentina/Buenos_Aires'));
        $tra = '<?xml version="1.0" encoding="UTF-8"?><loginTicketRequest version="1.0"><header><uniqueId>' . time() . '</uniqueId>'
            . '<generationTime>' . $now->modify('-5 minutes')->format('c') . '</generationTime><expirationTime>' . $now->modify('+10 hours')->format('c') . '</expirationTime>'
            . '</header><service>wsfe</service></loginTicketRequest>';
        $cms = self::firmar($tra, $cert, $key, (string)Env::get('ARCA_KEY_PASSPHRASE', ''));
        $env = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:wsaa="http://wsaa.view.sua.dvadac.desein.afip.gov"><soapenv:Header/><soapenv:Body><wsaa:loginCms><wsaa:in0>'
            . $cms . '</wsaa:in0></wsaa:loginCms></soapenv:Body></soapenv:Envelope>';
        $r = $this->post(self::WSAA[$this->amb()], '', $env);
        if (preg_match('/<faultstring>(.*?)<\/faultstring>/s', $r, $m)) {
            // "El CEE ya posee un TA valido" → reintentar más tarde con el TA guardado.
            throw new ArcaNoResponde('WSAA: ' . html_entity_decode($m[1]));
        }
        if (!preg_match('/<loginCmsReturn>(.*?)<\/loginCmsReturn>/s', $r, $m)) {
            throw new ArcaNoResponde('WSAA: respuesta inesperada.');
        }
        $x = self::xml(html_entity_decode($m[1]));
        $ta = ['token' => (string)$x->credentials->token, 'sign' => (string)$x->credentials->sign, 'vence' => (string)$x->header->expirationTime];
        if (isset(App::$db)) {
            \App\Core\Config::set('arca_ta_' . $this->amb(), $ta, 'worker');
        }
        return $ta;
    }

    /** Firma el TRA en formato CMS (PKCS#7) y devuelve el base64 sin encabezados MIME. */
    public static function firmar(string $tra, string $cert, string $key, string $pass = ''): string
    {
        $dir = sys_get_temp_dir();
        $in = tempnam($dir, 'tra');
        $out = tempnam($dir, 'cms');
        file_put_contents($in, $tra);
        $ok = openssl_pkcs7_sign($in, $out, 'file://' . realpath($cert), ['file://' . realpath($key), $pass], [], 0);
        $smime = (string)@file_get_contents($out);
        @unlink($in);
        @unlink($out);
        if (!$ok) {
            throw new ArcaRechazo('No se pudo firmar el pedido de acceso con el certificado de ARCA: ' . openssl_error_string());
        }
        $partes = preg_split("/\r?\n\r?\n/", $smime, 2);
        return trim(str_replace(["\r", "\n"], '', $partes[1] ?? ''));
    }

    // ------------------------------------------------------------------ WSFEv1

    private function auth(): string
    {
        $ta = $this->ticket();
        return '<ar:Auth><ar:Token>' . htmlspecialchars($ta['token']) . '</ar:Token><ar:Sign>' . htmlspecialchars($ta['sign']) . '</ar:Sign><ar:Cuit>' . $this->cuit() . '</ar:Cuit></ar:Auth>';
    }

    private function llamar(string $metodo, string $cuerpo): \SimpleXMLElement
    {
        $xml = '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ar="' . self::NS . '"><soap:Body><ar:' . $metodo . '>'
            . $this->auth() . $cuerpo . '</ar:' . $metodo . '></soap:Body></soap:Envelope>';
        $r = $this->post(self::WSFE[$this->amb()], self::NS . $metodo, $xml);
        if (preg_match('/<faultstring>(.*?)<\/faultstring>/s', $r, $m)) {
            throw new ArcaNoResponde('WSFEv1: ' . html_entity_decode($m[1]));
        }
        $x = self::xml($r);
        $x->registerXPathNamespace('ar', self::NS);
        $res = $x->xpath('//ar:' . $metodo . 'Result');
        if (!$res) {
            throw new ArcaNoResponde('WSFEv1: respuesta inesperada.');
        }
        return $res[0];
    }

    private static function errores(\SimpleXMLElement $r): string
    {
        $m = [];
        foreach ($r->Errors->Err ?? [] as $e) {
            $m[] = (string)$e->Code . ': ' . (string)$e->Msg;
        }
        return implode(' · ', $m);
    }

    private static function f(string $iso): string
    {
        return str_replace('-', '', $iso);
    }

    private static function n(float $v): string
    {
        return number_format($v, 2, '.', '');
    }

    public function autorizar(array $c): array
    {
        $iva = '';
        if ($c['iva'] > 0) {
            $id = Arca::ALIC_ID[rtrim(rtrim(number_format($c['alicuota'], 2, '.', ''), '0'), '.')] ?? null;
            if (!$id) {
                throw new ArcaRechazo("Alícuota de IVA {$c['alicuota']}% no soportada por ARCA.");
            }
            $iva = '<ar:Iva><ar:AlicIva><ar:Id>' . $id . '</ar:Id><ar:BaseImp>' . self::n($c['neto']) . '</ar:BaseImp><ar:Importe>' . self::n($c['iva']) . '</ar:Importe></ar:AlicIva></ar:Iva>';
        }
        $det = '<ar:FECAEDetRequest><ar:Concepto>2</ar:Concepto><ar:DocTipo>' . $c['doc_tipo'] . '</ar:DocTipo><ar:DocNro>' . $c['doc_nro'] . '</ar:DocNro>'
            . '<ar:CbteDesde>' . $c['numero'] . '</ar:CbteDesde><ar:CbteHasta>' . $c['numero'] . '</ar:CbteHasta><ar:CbteFch>' . self::f($c['fecha']) . '</ar:CbteFch>'
            . '<ar:ImpTotal>' . self::n($c['total']) . '</ar:ImpTotal><ar:ImpTotConc>0.00</ar:ImpTotConc><ar:ImpNeto>' . self::n($c['neto']) . '</ar:ImpNeto>'
            . '<ar:ImpOpEx>' . self::n($c['exento']) . '</ar:ImpOpEx><ar:ImpTrib>0.00</ar:ImpTrib><ar:ImpIVA>' . self::n($c['iva']) . '</ar:ImpIVA>'
            . '<ar:FchServDesde>' . self::f($c['serv_desde']) . '</ar:FchServDesde><ar:FchServHasta>' . self::f($c['serv_hasta']) . '</ar:FchServHasta><ar:FchVtoPago>' . self::f($c['vto_pago']) . '</ar:FchVtoPago>'
            . '<ar:MonId>PES</ar:MonId><ar:MonCotiz>1</ar:MonCotiz><ar:CondicionIVAReceptorId>' . $c['cond_iva'] . '</ar:CondicionIVAReceptorId>' . $iva . '</ar:FECAEDetRequest>';
        $r = $this->llamar('FECAESolicitar', '<ar:FeCAEReq><ar:FeCabReq><ar:CantReg>1</ar:CantReg><ar:PtoVta>' . $c['pv'] . '</ar:PtoVta><ar:CbteTipo>' . $c['tipo'] . '</ar:CbteTipo></ar:FeCabReq><ar:FeDetReq>' . $det . '</ar:FeDetReq></ar:FeCAEReq>');
        $d = $r->FeDetResp->FECAEDetResponse ?? null;
        $res = (string)($r->FeCabResp->Resultado ?? '');
        if ($res === 'A' && $d && (string)$d->CAE !== '') {
            $v = (string)$d->CAEFchVto;
            return ['cae' => (string)$d->CAE, 'vto' => substr($v, 0, 4) . '-' . substr($v, 4, 2) . '-' . substr($v, 6, 2)];
        }
        $obs = [];
        foreach ($d->Observaciones->Obs ?? [] as $o) {
            $obs[] = (string)$o->Code . ': ' . (string)$o->Msg;
        }
        throw new ArcaRechazo(trim('ARCA rechazó el comprobante. ' . implode(' · ', array_filter([self::errores($r), implode(' · ', $obs)]))));
    }

    public function ultimoAutorizado(int $pv, int $tipo): int
    {
        $r = $this->llamar('FECompUltimoAutorizado', '<ar:PtoVta>' . $pv . '</ar:PtoVta><ar:CbteTipo>' . $tipo . '</ar:CbteTipo>');
        if ($e = self::errores($r)) {
            throw new ArcaRechazo("FECompUltimoAutorizado: $e");
        }
        return (int)$r->CbteNro;
    }

    public function consultar(int $pv, int $tipo, int $numero): ?array
    {
        $r = $this->llamar('FECompConsultar', '<ar:FeCompConsReq><ar:CbteTipo>' . $tipo . '</ar:CbteTipo><ar:CbteNro>' . $numero . '</ar:CbteNro><ar:PtoVta>' . $pv . '</ar:PtoVta></ar:FeCompConsReq>');
        $g = $r->ResultGet ?? null;
        if (!$g || (string)$g->CodAutorizacion === '') {
            return null;
        }
        $v = (string)$g->FchVto;
        return ['cae' => (string)$g->CodAutorizacion, 'vto' => substr($v, 0, 4) . '-' . substr($v, 4, 2) . '-' . substr($v, 6, 2)];
    }

    /** Días que faltan para que venza el certificado de ARCA (alerta a 30 días). */
    public static function diasCertificado(): ?int
    {
        $cert = Env::get('ARCA_CERT_FILE', '/run/secrets/arca_cert');
        if (!is_readable($cert)) {
            return null;
        }
        $d = openssl_x509_parse((string)file_get_contents($cert));
        return $d ? (int)floor(($d['validTo_time_t'] - time()) / 86400) : null;
    }
}
