<?php
/** CU-38 · Informe en PDF. @var string $titulo @var string $periodo @var array $d @var string $usuario @var string $emision */
use App\Core\Config;
use App\Domain\Fmt;
use App\Service\Pdf;

$E = Config::empresa();
$v = fn($x, $i) => in_array($i, $d['num'], true) && $x !== '' && is_numeric($x) ? Fmt::money2((float)$x) : (string)$x;
?><!doctype html><html><head><meta charset="utf-8"><style>
@page{margin:24mm 10mm 14mm 10mm}
body{font-family:'DejaVu Sans',sans-serif;font-size:7.6pt;color:#282828}
.cab{position:fixed;top:-20mm;left:0;right:0;border-bottom:0.4mm solid #34509a;padding-bottom:2mm}
.cab img{height:10mm;float:left;margin-right:3mm}
.cab .t{color:#34509a;font-weight:bold;font-size:13pt}
.cab .s{font-size:8pt;color:#555}
.cab .r{position:absolute;right:0;top:0;text-align:right;font-size:7.5pt;color:#555;line-height:1.5}
.pie{position:fixed;bottom:-10mm;left:0;right:0;text-align:center;font-size:7pt;color:#888}
table{width:100%;border-collapse:collapse}
th{background:#34509a;color:#fff;text-align:left;padding:1.6mm 1.4mm;font-size:7.4pt}
td{padding:1.3mm 1.4mm;border-bottom:0.2mm solid #e1e6ee;vertical-align:top}
tr.tot td{background:#e8edf7;font-weight:bold}
.r{text-align:right;white-space:nowrap}
.vacio{padding:10mm;text-align:center;color:#777;font-size:10pt}
</style></head><body>
<div class="cab"><img src="<?= Pdf::logo() ?>" alt=""><div class="t"><?= e($titulo) ?></div><div class="s"><?= e($E['razon'] ?? '') ?> · CUIT <?= e($E['cuit'] ?? '') ?> · Período: <?= e($periodo) ?></div>
  <div class="r">Emitido el <?= e($emision) ?><br>por <?= e($usuario) ?></div></div>
<div class="pie">Integral del Este · <?= e($titulo) ?> · <?= e($periodo) ?></div>
<?php if (!$d['filas']): ?><div class="vacio">No hay datos en este período.</div><?php else: ?>
<table><thead><tr><?php foreach ($d['cols'] as $i => $c): ?><th class="<?= in_array($i, $d['num'], true) ? 'r' : '' ?>"><?= e($c) ?></th><?php endforeach ?></tr></thead><tbody>
<?php foreach ($d['filas'] as $r): ?><tr><?php foreach ($r as $i => $x): ?><td class="<?= in_array($i, $d['num'], true) ? 'r' : '' ?>"><?= e($v($x, $i)) ?></td><?php endforeach ?></tr><?php endforeach ?>
<?php if ($d['tot']): ?><tr class="tot"><?php foreach ($d['tot'] as $i => $x): ?><td class="<?= in_array($i, $d['num'], true) ? 'r' : '' ?>"><?= e($v($x, $i)) ?></td><?php endforeach ?></tr><?php endif ?>
</tbody></table>
<?php endif ?>
</body></html>
