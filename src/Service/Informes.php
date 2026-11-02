<?php
declare(strict_types=1);

namespace App\Service;

use App\Core\Config;
use App\Domain\Fechas;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/** CU-38: informes en Excel y PDF con encabezado de la empresa. */
final class Informes
{
    public static function nombreArchivo(string $informe, string $periodo, string $ext): string
    {
        return preg_replace('/[\\\\\/:*?"<>|]/u', '-', "Integral del Este - $informe - $periodo") . ".$ext";
    }

    public static function excel(string $titulo, string $periodo, array $d): string
    {
        $E = Config::empresa();
        $ss = new Spreadsheet();
        $sh = $ss->getActiveSheet();
        $sh->setTitle(mb_substr(preg_replace('/[\\\\\/?*\[\]:]/u', '', $titulo), 0, 31));
        $sh->setCellValue('A1', $titulo);
        $sh->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sh->setCellValue('A2', ($E['razon'] ?? '') . ' · CUIT ' . ($E['cuit'] ?? ''));
        $sh->setCellValue('A3', 'Período: ' . $periodo);
        $fila = 5;
        foreach ($d['cols'] as $i => $c) {
            $sh->setCellValue([$i + 1, $fila], $c);
        }
        $n = count($d['cols']);
        $rango = 'A' . $fila . ':' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($n) . $fila;
        $sh->getStyle($rango)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sh->getStyle($rango)->getFill()->setFillType('solid')->getStartColor()->setRGB('34509A');
        $filas = $d['filas'];
        if (!$filas) {
            $sh->setCellValue([1, $fila + 1], 'No hay datos en este período');
        }
        foreach ($filas as $r) {
            $fila++;
            foreach ($r as $i => $v) {
                $sh->setCellValue([$i + 1, $fila], $v === '' ? null : $v);
            }
        }
        if ($d['tot'] && $filas) {
            $fila++;
            foreach ($d['tot'] as $i => $v) {
                $sh->setCellValue([$i + 1, $fila], $v === '' ? null : $v);
            }
            $sh->getStyle('A' . $fila . ':' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($n) . $fila)->getFont()->setBold(true);
        }
        foreach ($d['num'] as $c) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c + 1);
            $sh->getStyle("{$col}6:{$col}$fila")->getNumberFormat()->setFormatCode('"$" #,##0.00');
            $sh->getStyle("{$col}5:{$col}$fila")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
        for ($i = 1; $i <= $n; $i++) {
            $sh->getColumnDimensionByColumn($i)->setAutoSize(true);
        }
        $w = new Xlsx($ss);
        ob_start();
        $w->save('php://output');
        return (string)ob_get_clean();
    }

    public static function pdf(string $titulo, string $periodo, array $d, string $usuario): string
    {
        $horizontal = count($d['cols']) > 6;
        return Pdf::generar('informe', ['titulo' => $titulo, 'periodo' => $periodo, 'd' => $d, 'usuario' => $usuario, 'emision' => Fechas::ahora()->format('d/m/Y H:i')], 'A4', $horizontal ? 'landscape' : 'portrait');
    }
}
