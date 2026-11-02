<?php
declare(strict_types=1);

namespace App\Service;

use App\Core\Response;
use App\Core\View;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * PDF generados en el servidor con una sola plantilla por tipo (ADR-10):
 * el mismo PDF se descarga, se comparte por WhatsApp o va adjunto al mail.
 */
final class Pdf
{
    /** Renderiza una plantilla de views/pdf a PDF. */
    public static function generar(string $tpl, array $vars, string $papel = 'A4', string $orientacion = 'portrait'): string
    {
        $html = View::parcial('pdf/' . $tpl, $vars + ['papel' => $papel]);
        return self::desdeHtml($html, $papel, $orientacion);
    }

    public static function desdeHtml(string $html, string $papel = 'A4', string $orientacion = 'portrait'): string
    {
        $o = new Options();
        $o->set('isRemoteEnabled', false);
        $o->set('isHtml5ParserEnabled', true);
        $o->set('defaultFont', 'DejaVu Sans');
        $tmp = sys_get_temp_dir() . '/integral-dompdf';
        if (!is_dir($tmp)) {
            @mkdir($tmp, 0775, true);
        }
        $o->set('tempDir', $tmp);
        $o->set('fontCache', $tmp);
        $d = new Dompdf($o);
        $d->loadHtml($html, 'UTF-8');
        $d->setPaper($papel, $orientacion);
        $d->render();
        return (string)$d->output();
    }

    /** Respuesta HTTP con el PDF. */
    public static function respuesta(string $bytes, string $nombre, bool $descargar = true): Response
    {
        return Response::archivo($bytes, self::nombre($nombre), 'application/pdf', $descargar);
    }

    /** "Cotización - Plan Plus del Este.pdf" sin caracteres inválidos. */
    public static function nombre(string $n): string
    {
        $n = preg_replace('/[\\\\\/:*?"<>|]/u', '-', $n);
        return str_ends_with(strtolower($n), '.pdf') ? $n : $n . '.pdf';
    }

    /** Logo de Integral del Este como imagen embebida. */
    public static function logo(bool $blanco = false): string
    {
        $c = $blanco ? '#ffffff' : '#34509a';
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 150 156"><rect x="48" y="0" width="40" height="156" fill="' . $c . '"/><rect x="0" y="58" width="48" height="40" fill="' . $c . '"/><polygon points="98,53 145,61 148,93 98,100" fill="#c23b3f"/></svg>';
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}
