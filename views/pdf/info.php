<?php
/**
 * PDF de información (planes, cotización, situación del afiliado, consulta,
 * promoción, resumen de beneficios, ahorro, cumpleaños). Misma estructura que pdfInfo de la maqueta:
 * etiqueta, titulo, subtitulo, saludo, hero{grande,chico}, parrafo, tabla{cols,rows,total},
 * destacado, total, secciones[{titulo,cols,rows}], notas[], cierre.
 * @var array $d
 */
use App\Core\Config;
use App\Domain\Fechas;
use App\Service\Pdf;

$emp = Config::empresa();
?><!doctype html><html><head><meta charset="utf-8"><style>
@page{margin:30mm 14mm 22mm 14mm}
body{font-family:'DejaVu Sans',sans-serif;font-size:10pt;color:#282828}
.cab{position:fixed;top:-30mm;left:-14mm;right:-14mm;height:24mm;background:#34509a;color:#fff}
.cab img{position:absolute;left:14mm;top:5mm;height:14mm}
.cab .t{position:absolute;left:30mm;top:6mm;font-weight:bold;font-size:14pt}
.cab .s{position:absolute;left:30mm;top:13mm;font-size:8.5pt}
.cab .r{position:absolute;right:14mm;top:6mm;text-align:right;font-size:9pt;line-height:1.6}
.pie{position:fixed;bottom:-16mm;left:0;right:0;border-top:1px solid #d2d2d2;padding-top:2mm;text-align:center;font-size:7.5pt;color:#6e6e6e;line-height:1.5}
h1{color:#34509a;font-size:19pt;margin:0 0 2mm}
.sub{color:#5a5a5a;font-size:10.5pt;margin:0 0 3mm}
.saludo{font-size:10.5pt;margin:2mm 0 4mm}
.hero{background:#c23b3f;color:#fff;border-radius:4mm;text-align:center;padding:6mm 6mm;margin:4mm 0 6mm}
.hero .g{font-size:24pt;font-weight:bold}.hero .c{font-size:12.5pt;margin-top:2mm}
.parrafo{font-size:11pt;margin:0 0 4mm}
table{width:100%;border-collapse:collapse;margin:2mm 0 4mm}
th{background:#34509a;color:#fff;text-align:left;font-size:9pt;padding:2mm}
td{font-size:9pt;padding:2mm;border-bottom:0.3mm solid #e1e6ee;vertical-align:top}
tfoot td{background:#e8edf7;font-weight:bold;font-size:10.5pt}
td.r,th.r{text-align:right}
td.b{font-weight:bold;width:38mm}td.car{width:30mm;color:#646464;font-size:8pt}
.dest{background:#fdecec;border:0.4mm solid #c23b3f;color:#c23b3f;font-weight:bold;border-radius:2mm;padding:3mm 4mm;margin:3mm 0}
.total{color:#34509a;font-weight:bold;font-size:15pt;text-align:right;margin:2mm 0 4mm}
h2{color:#34509a;font-size:12.5pt;margin:4mm 0 1mm}
.notas{color:#5a5a5a;font-size:8pt;margin-top:4mm}.notas p{margin:0 0 1.5mm}
.cierre{color:#34509a;font-weight:bold;font-size:11pt;text-align:center;margin-top:6mm}
</style></head><body>
<div class="cab"><img src="<?= Pdf::logo(true) ?>" alt=""><div class="t">INTEGRAL DEL ESTE</div><div class="s">Plan de beneficios de salud</div><div class="r"><?= e($d['etiqueta'] ?? '') ?><br><?= fd(Fechas::hoy()) ?></div></div>
<div class="pie">Integral del Este · Artigas 371, Colonia Avellaneda, Entre Ríos · integraldeleste.com.ar<br><?= e($emp['razon'] ?? 'Grupo del Este Salud S.A.S.') ?> · CUIT <?= e($emp['cuit'] ?? '') ?></div>

<h1><?= e($d['titulo'] ?? '') ?></h1>
<?php if (!empty($d['subtitulo'])): ?><p class="sub"><?= e($d['subtitulo']) ?></p><?php endif ?>
<?php if (!empty($d['saludo'])): ?><p class="saludo"><?= e($d['saludo']) ?></p><?php endif ?>
<?php if (!empty($d['hero'])): ?><div class="hero"><div class="g"><?= e($d['hero']['grande']) ?></div><?php if (!empty($d['hero']['chico'])): ?><div class="c"><?= e($d['hero']['chico']) ?></div><?php endif ?></div><?php endif ?>
<?php if (!empty($d['parrafo'])): ?><p class="parrafo"><?= nl2br(e($d['parrafo'])) ?></p><?php endif ?>
<?php if (!empty($d['tabla']['rows'])): $n = count($d['tabla']['cols']); ?>
<table><thead><tr><?php foreach ($d['tabla']['cols'] as $i => $c): ?><th class="<?= $i === $n - 1 ? 'r' : '' ?>"><?= e($c) ?></th><?php endforeach ?></tr></thead>
<tbody><?php foreach ($d['tabla']['rows'] as $r): ?><tr><?php foreach ($r as $i => $c): ?><td class="<?= $i === $n - 1 ? 'r' : '' ?>"><?= e($c) ?></td><?php endforeach ?></tr><?php endforeach ?></tbody>
<?php if (!empty($d['tabla']['total'])): ?><tfoot><tr><?php foreach ($d['tabla']['total'] as $i => $c): ?><td class="<?= $i === $n - 1 ? 'r' : '' ?>"><?= e($c) ?></td><?php endforeach ?></tr></tfoot><?php endif ?>
</table>
<?php endif ?>
<?php if (!empty($d['destacado'])): ?><div class="dest"><?= e($d['destacado']) ?></div><?php endif ?>
<?php if (!empty($d['total'])): ?><div class="total"><?= e($d['total']) ?></div><?php endif ?>
<?php foreach ($d['secciones'] ?? [] as $sc): if (!$sc['rows']) continue; $cols = $sc['cols'] ?? ['Beneficio', 'Detalle', 'Carencia']; $std = !isset($sc['cols']) || ($sc['std'] ?? false); ?>
<h2><?= e($sc['titulo']) ?></h2>
<table><thead><tr><?php foreach ($cols as $c): ?><th><?= e($c) ?></th><?php endforeach ?></tr></thead><tbody>
<?php foreach ($sc['rows'] as $r): ?><tr><?php foreach ($r as $i => $c): ?><td class="<?= $std ? ($i === 0 ? 'b' : ($i === 2 ? 'car' : '')) : ($i === 0 ? 'b' : '') ?>"><?= e($c) ?></td><?php endforeach ?></tr><?php endforeach ?>
</tbody></table>
<?php endforeach ?>
<?php if (!empty($d['notas'])): ?><div class="notas"><?php foreach ($d['notas'] as $n): ?><p><?= e($n) ?></p><?php endforeach ?></div><?php endif ?>
<?php if (!empty($d['cierre'])): ?><div class="cierre"><?= e($d['cierre']) ?></div><?php endif ?>
</body></html>
