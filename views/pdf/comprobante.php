<?php
/**
 * Factura de cuota o recibo en PDF (A5). Sin el nombre de quien cobró (RT-10).
 * @var array $d Comprobantes::datosFactura|datosRecibo
 */
use App\Core\Config;
use App\Domain\Catalogo;
use App\Domain\Fmt;
use App\Service\Pdf;

$E = Config::empresa();
$esF = $d['tipo'] === 'factura';
$f = $d['f'];
$t = $d['t'];
$letra = $esF ? $f['letra'] : 'X';
$nro = $esF ? $f['nro'] : $d['r']['nro'];
$fecha = $esF ? $f['emision'] : $d['r']['fecha'];
$m = fn($v) => str_replace(' ', "\u{a0}", Fmt::money((float)$v));
?><!doctype html><html><head><meta charset="utf-8"><style>
@page{margin:12mm 12mm 18mm 12mm}
body{font-family:'DejaVu Sans',sans-serif;font-size:8.6pt;color:#282828}
.enc{width:100%;border-collapse:collapse;border-bottom:0.3mm solid #d2d2d2;margin-bottom:5mm}
.enc td{vertical-align:top;padding:0 0 3mm}
.marca{color:#34509a;font-weight:bold;font-size:12pt;line-height:1.1}
.letra{border:0.4mm solid #282828;border-radius:1.5mm;width:16mm;text-align:center;padding:1mm 0}
.letra b{font-size:20pt;display:block}.letra small{font-size:5pt}
.der{text-align:right;line-height:1.5}.der .t{color:#34509a;font-weight:bold;font-size:13pt}
table.det{width:100%;border-collapse:collapse;margin:3mm 0}
table.det th{background:#e8edf7;text-align:left;padding:1.8mm 2mm;font-size:8.2pt}
table.det td{padding:1.6mm 2mm;border-bottom:0.2mm solid #eee}
.r{text-align:right}
.total{color:#34509a;font-weight:bold;font-size:14pt;text-align:right;margin:2mm 0}
.iva{font-size:7.8pt;margin-top:3mm}
.pie{position:fixed;bottom:-12mm;left:0;right:0;border-top:0.3mm solid #d2d2d2;padding-top:1.5mm;text-align:center;font-size:7pt;color:#787878;line-height:1.5}
.bon{color:#34509a}
</style></head><body>
<table class="enc"><tr>
  <td style="width:42%"><img src="<?= Pdf::logo() ?>" style="height:12mm;vertical-align:middle"> <span class="marca">INTEGRAL<br>DEL ESTE</span>
    <div style="margin-top:2mm;line-height:1.45"><b><?= e($E['razon'] ?? '') ?></b><br><?= e($E['domicilio'] ?? '') ?><br><?= e($E['localidad'] ?? '') ?><br><?= e($E['iva'] ?? '') ?></div></td>
  <td style="width:16%;text-align:center"><div class="letra"><b><?= $letra ?></b><small><?= $letra === 'B' ? 'COD. 006' : ($letra === 'A' ? 'COD. 001' : 'NO VÁLIDO<br>COMO FACTURA') ?></small></div></td>
  <td class="der" style="width:42%"><span class="t"><?= $esF ? 'FACTURA' : 'RECIBO' ?></span><br><b>N° <?= e($nro) ?></b><br>Fecha: <?= fd($fecha) ?><br>CUIT: <?= e($E['cuit'] ?? '') ?><br>Ingresos Brutos: <?= e($E['iibb'] ?? '') ?><br>Inicio de actividades: <?= e($E['inicio'] ?? '') ?></td>
</tr></table>
<?php if ($esF): $rc = $f['receptor']; ?>
  <div><b><?= e(($rc['cond'] ?? 'CF') !== 'CF' ? $rc['nombre'] : $t['nombre']) ?></b><br><?= e($rc['doc'] ?? '') ?> · <?= e(Catalogo::COND_IVA[$rc['cond'] ?? 'CF'] ?? 'Consumidor Final') ?><?= ($rc['cond'] ?? 'CF') !== 'CF' ? ' · Titular: ' . e($t['nombre']) . ' (N° ' . $t['nro'] . ')' : ' · Afiliado N° ' . $t['nro'] ?><br>
    <?= e($d['plan']['nombre']) ?> · Período <?= e(Fmt::perLabel($f['periodo'])) ?> · Vence <?= fd($f['vto']) ?></div>
  <table class="det"><thead><tr><th>Integrante</th><th>Categoría</th><th class="r">Cuota</th></tr></thead><tbody>
  <?php foreach ($f['lineas'] as $l): ?><tr><td><?= e($l['nombre']) ?></td><td><?= e($d['cats'][$l['cat']] ?? '') ?></td><td class="r"><?= $m($l['cuota']) ?></td></tr><?php endforeach ?>
  <?php if ($f['promo_pct']): ?><tr class="bon"><td colspan="2">Bonificación promoción <?= (int)$f['promo_pct'] ?>%<?= $f['promo_mes'] ? ' · mes ' . (int)$f['promo_mes'] . ' de ' . (int)$f['promo_meses'] : '' ?></td><td class="r">- <?= $m($d['bon']) ?></td></tr><?php endif ?>
  </tbody></table>
  <div class="total">Total <?= $m($f['importe']) ?></div>
  <div class="iva"><?php if ($letra === 'A'): ?>Neto gravado: <?= $m($d['neto'] ?? $f['importe']) ?> · IVA <?= Fmt::ivaTxt($f['alicuota']) ?>: <?= $d['iva'] === null ? 'a confirmar' : $m($d['iva']) ?>
  <?php else: ?>Régimen de Transparencia Fiscal al Consumidor (Ley 27.743)<br>IVA contenido: <?= $d['iva'] === null ? 'a confirmar' : $m($d['iva']) ?> · Otros impuestos nacionales indirectos: <?= $m(0) ?><?php endif ?></div>
  <div class="pie">CAE <?= e($f['cae'] ?: 'pendiente') ?><?= $f['cae_vto'] ? ' · Vto. CAE ' . fd($f['cae_vto']) : '' ?> · <?= $f['estado'] === 'pagada' ? 'Pagada' : 'Pendiente de pago' ?><br>Integral del Este · Artigas 371, Colonia Avellaneda · integraldeleste.com.ar</div>
<?php else: $r = $d['r']; ?>
  <p style="font-size:10pt;line-height:1.55">Recibimos de <?= e($t['nombre']) ?> (DNI <?= e($t['doc']) ?>, afiliado N° <?= $t['nro'] ?>) la suma de <b><?= $m($r['importe']) ?></b> — son pesos <?= e(Fmt::enLetras((float)$r['importe'])) ?> — en concepto de:</p>
  <table class="det"><thead><tr><th>Concepto</th><th>Comprobante</th><th class="r">Importe</th></tr></thead><tbody><tr><td>Cuota <?= e(Fmt::perLabel($f['periodo'])) ?> · <?= e($d['plan']['nombre']) ?></td><td>Factura <?= e($f['letra'] . ' ' . $f['nro']) ?></td><td class="r"><?= $m($r['importe']) ?></td></tr></tbody></table>
  <div style="font-size:9.5pt">Medio de pago: <?= e($r['medio']) ?></div>
  <div class="total">Total <?= $m($r['importe']) ?></div>
  <p style="text-align:center;margin-top:8mm">¡Gracias por ser parte de Integral del Este!</p>
  <div class="pie">Documento no válido como factura<?= $r['masivo'] ? ' · Cobro por débito automático' : '' ?><br>Integral del Este · Artigas 371, Colonia Avellaneda · integraldeleste.com.ar</div>
<?php endif ?>
</body></html>
