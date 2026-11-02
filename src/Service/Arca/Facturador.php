<?php
declare(strict_types=1);

namespace App\Service\Arca;

use App\Core\Env;

/**
 * Autorización de comprobantes en ARCA (CU-27).
 * ARCA_MODO = simulado | homologacion | produccion
 */
interface Facturador
{
    /**
     * Pide el CAE de un comprobante.
     * @param array $c {tipo:int, pv:int, numero:int, fecha:'Y-m-d', doc_tipo:int, doc_nro:string, cond_iva:int,
     *                  total:float, neto:float, iva:float, exento:float, alicuota:?float, serv_desde, serv_hasta, vto_pago}
     * @return array{cae:string, vto:string}
     * @throws ArcaRechazo si ARCA rechaza el comprobante
     * @throws ArcaNoResponde si no hay respuesta (se reintenta)
     */
    public function autorizar(array $c): array;

    /** Último número autorizado para el punto de venta y tipo. */
    public function ultimoAutorizado(int $pv, int $tipo): int;

    /** Consulta un comprobante ya autorizado: {cae, vto} o null. */
    public function consultar(int $pv, int $tipo, int $numero): ?array;
}

final class ArcaRechazo extends \RuntimeException
{
}

final class ArcaNoResponde extends \RuntimeException
{
}

final class Arca
{
    /** Tipos de comprobante: Factura A = 1, Factura B = 6. */
    public const TIPO = ['A' => 1, 'B' => 6];
    /** Condición frente al IVA del receptor (RG 5616). */
    public const COND_IVA = ['RI' => 1, 'EX' => 4, 'CF' => 5, 'MT' => 6];
    /** Id de alícuota de IVA en WSFEv1. */
    public const ALIC_ID = ['0' => 3, '10.5' => 4, '21' => 5, '27' => 6, '5' => 8, '2.5' => 9];

    public static function modo(): string
    {
        return Env::get('ARCA_MODO', 'simulado');
    }

    public static function crear(): Facturador
    {
        return match (self::modo()) {
            'homologacion', 'produccion' => new ArcaWebService(self::modo() === 'produccion'),
            default => new ArcaSimulado(),
        };
    }

    /** Arma los datos para ARCA a partir de una factura del sistema. */
    public static function desdeFactura(array $f): array
    {
        $alic = $f['alicuota'];
        if ($alic === null) {
            throw new ArcaRechazo('La alícuota de IVA del plan está "a confirmar": cargala en Configuración → Precios por plan.');
        }
        $total = round((float)$f['importe'], 2);
        $rc = $f['receptor'];
        $docDig = preg_replace('/\D/', '', (string)($rc['doc'] ?? ''));
        if (str_starts_with((string)($rc['doc'] ?? ''), 'CUIT') && strlen($docDig) === 11) {
            [$dt, $dn] = [80, $docDig];
        } elseif ($docDig !== '' && strlen($docDig) <= 8) {
            [$dt, $dn] = [96, $docDig];
        } elseif (strlen($docDig) === 11) {
            [$dt, $dn] = [80, $docDig];
        } else {
            [$dt, $dn] = [99, '0'];
        }
        $exento = (float)$alic === 0.0;
        $iva = $exento ? 0.0 : round($total * $alic / (100 + $alic), 2);
        $neto = $exento ? 0.0 : round($total - $iva, 2);
        $per = $f['periodo'];
        return [
            'tipo' => self::TIPO[$f['letra']], 'pv' => (int)$f['pv'], 'numero' => (int)$f['numero'], 'fecha' => $f['emision'],
            'doc_tipo' => $dt, 'doc_nro' => $dn, 'cond_iva' => self::COND_IVA[$rc['cond'] ?? 'CF'] ?? 5,
            'total' => $total, 'neto' => $neto, 'iva' => $iva, 'exento' => $exento ? $total : 0.0, 'alicuota' => (float)$alic,
            'serv_desde' => "$per-01", 'serv_hasta' => \App\Domain\Fechas::finPeriodo($per), 'vto_pago' => max($f['vto'], $f['emision']),
        ];
    }
}
