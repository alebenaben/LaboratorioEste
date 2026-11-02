<?php
declare(strict_types=1);

namespace App\Service;

use App\Core\View;
use App\Domain\Caja;
use App\Domain\Catalogo;
use App\Domain\Fmt;
use App\Domain\Padron;

/**
 * Factura y recibo (CU-31): la misma plantilla sirve para la pantalla, el PDF y el adjunto del mail.
 * No figura quién cobró ni quién emitió (RT-10).
 */
final class Comprobantes
{
    /** Datos para dibujar la factura. */
    public static function datosFactura(array $f): array
    {
        $g = Padron::grupo($f['grupo_id']);
        $t = Padron::titular($g['id']);
        $alic = $f['alicuota'];
        $iva = Fmt::ivaContenido($f['importe'], $alic);
        return [
            'tipo' => 'factura', 'f' => $f, 'g' => $g, 't' => $t, 'plan' => Catalogo::plan($f['plan_id']),
            'bon' => round($f['base'] - $f['importe'], 2), 'iva' => $iva, 'neto' => $iva === null ? null : round($f['importe'] - $iva, 2),
            'cats' => array_map(fn($c) => $c['nombre'], Catalogo::categorias()),
        ];
    }

    public static function datosRecibo(array $r): array
    {
        $g = Padron::grupo((int)$r['grupo_id']);
        $t = Padron::titular($g['id']);
        $f = Caja::factura((int)$r['factura_id']);
        return ['tipo' => 'recibo', 'r' => $r, 'g' => $g, 't' => $t, 'f' => $f, 'plan' => Catalogo::plan($g['plan_id'])];
    }

    public static function htmlFactura(array $f): string
    {
        return View::parcial('caja/comprobante', self::datosFactura($f));
    }

    public static function htmlRecibo(array $r): string
    {
        return View::parcial('caja/comprobante', self::datosRecibo($r));
    }

    public static function pdfFactura(array $f): string
    {
        return Pdf::generar('comprobante', ['d' => self::datosFactura($f)], 'A5');
    }

    public static function pdfRecibo(array $r): string
    {
        return Pdf::generar('comprobante', ['d' => self::datosRecibo($r)], 'A5');
    }
}
