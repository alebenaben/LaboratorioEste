<?php
/**
 * Cuerpo de una factura de cuota o un recibo para la pantalla (encFiscal de la maqueta).
 * @var string $tipo factura|recibo
 */
use App\Core\Config;
use App\Domain\Catalogo;
use App\Domain\Fmt;

$E = Config::empresa();
if ($tipo === 'factura') {
    $letra = $f['letra'];
    $titulo = 'Factura';
    $nro = $f['nro'];
    $fecha = $f['emision'];
} else {
    $letra = 'X';
    $titulo = 'Recibo';
    $nro = $r['nro'];
    $fecha = $r['fecha'];
}
?>
<div class="comp">
  <div class="enc-fiscal">
    <div><div class="logo" style="color:var(--logo-blue)"><?= logo_svg() ?><span class="wm">Integral del Este</span></div>
      <div style="font-size:12px;line-height:1.45;margin-top:6px"><b><?= e($E['razon'] ?? '') ?></b><br><?= e($E['domicilio'] ?? '') ?><br><?= e($E['localidad'] ?? '') ?><br><?= e($E['iva'] ?? '') ?></div></div>
    <div class="letra"><b><?= $letra ?></b><small><?= $letra === 'B' ? 'Cód. 006' : ($letra === 'A' ? 'Cód. 001' : 'No válido<br>como factura') ?></small></div>
    <div style="font-size:12px;line-height:1.45;text-align:right"><b style="font-size:15px"><?= $titulo ?></b><br><span class="mono">N° <?= e($nro) ?></span><br>Fecha: <?= fd($fecha) ?><br>CUIT: <?= e($E['cuit'] ?? '') ?><br>Ingresos Brutos: <?= e($E['iibb'] ?? '') ?><br>Inicio de actividades: <?= e($E['inicio'] ?? '') ?></div>
  </div>
<?php if ($tipo === 'factura'): $rc = $f['receptor']; ?>
  <div><?php if (($rc['cond'] ?? 'CF') !== 'CF'): ?><span class="muted">Factura a:</span> <b><?= e($rc['nombre']) ?></b> · <?= e($rc['doc']) ?> · <?= e(Catalogo::COND_IVA[$rc['cond']] ?? '') ?><br><span class="muted">Titular:</span> <?= e($t['nombre']) ?> · N° <?= $t['nro'] ?><br>
    <?php else: ?><span class="muted">Titular:</span> <b><?= e($rc['nombre'] ?? $t['nombre']) ?></b> · N° <?= $t['nro'] ?> · <?= e($rc['doc'] ?? '') ?> · Consumidor Final<br><?php endif ?>
    <span class="muted">Plan:</span> <?= e($plan['nombre']) ?> · <span class="muted">Período:</span> <b><?= e(Fmt::perLabel($f['periodo'])) ?></b> · <span class="muted">Vence:</span> <?= fd($f['vto']) ?></div>
  <table><thead><tr><th>Integrante</th><th>Categoría</th><th class="r">Cuota</th></tr></thead><tbody>
    <?php foreach ($f['lineas'] as $l): ?><tr><td><?= e($l['nombre']) ?></td><td><?= e($cats[$l['cat']] ?? '') ?></td><td class="r num"><?= money($l['cuota']) ?></td></tr><?php endforeach ?>
    <tr><td colspan="2"><b>Subtotal</b></td><td class="r num"><b><?= money($f['base']) ?></b></td></tr>
    <?php if ($f['promo_pct']): ?><tr><td colspan="2" style="color:var(--accent)"><b>Bonificación por promoción: <?= (int)$f['promo_pct'] ?>%</b><?php if ($f['promo_mes']): ?><div style="font-size:12.5px">Mes <?= (int)$f['promo_mes'] ?> de <?= (int)$f['promo_meses'] ?><?= $f['promo_hasta'] ? ' · vigente hasta ' . e(Fmt::perLabel(trim($f['promo_hasta']))) : '' ?></div><?php endif ?></td><td class="r num" style="color:var(--accent)"><b>− <?= money($bon) ?></b></td></tr><?php endif ?>
  </tbody></table>
  <div class="row" style="justify-content:space-between"><span class="muted"><?= $f['promo_pct'] ? 'Ahorro este mes: ' . money($bon) : '' ?></span><span style="font-size:20px;font-weight:650">Total <?= money($f['importe']) ?></span></div>
  <div class="iva-box">
  <?php if ($letra === 'A'): ?>
    <div class="row" style="justify-content:space-between"><span>Neto gravado<?= $f['alicuota'] === null ? ' (alícuota a confirmar)' : ((float)$f['alicuota'] === 0.0 ? ' exento' : ' ' . Fmt::ivaTxt($f['alicuota'])) ?></span><span class="num"><?= money($neto ?? $f['importe']) ?></span></div>
    <?php if ($f['alicuota']): ?><div class="row" style="justify-content:space-between"><span>IVA <?= Fmt::ivaTxt($f['alicuota']) ?></span><span class="num"><?= money($iva) ?></span></div><?php endif ?>
  <?php else: ?>
    <b style="font-size:12px">Régimen de Transparencia Fiscal al Consumidor (Ley 27.743)</b>
    <div class="row" style="justify-content:space-between"><span>IVA contenido</span><span class="num"><?= $iva === null ? '<span style="color:var(--warn)">alícuota a confirmar</span>' : money($iva) ?></span></div>
    <div class="row" style="justify-content:space-between"><span>Otros impuestos nacionales indirectos</span><span class="num"><?= money(0) ?></span></div>
  <?php endif ?>
  <?php if ($f['alicuota'] === null): ?><div style="color:var(--warn);font-size:12.5px">Falta cargar la alícuota de IVA en Configuración.</div><?php endif ?>
  </div>
  <div class="muted" style="font-size:12.5px"><?= $f['cae'] ? 'CAE ' . e($f['cae']) . ' · vence ' . fd($f['cae_vto']) : ($f['cae_estado'] === 'rechazada' ? '<span style="color:var(--bad)">CAE rechazado: ' . e($f['cae_error']) . '</span>' : 'CAE pendiente de ARCA') ?> · <?= $f['estado'] === 'pagada' ? 'Pagada' : 'Pendiente de pago' ?></div>
<?php else: ?>
  <p style="margin:0;font-size:15px;line-height:1.6">Recibimos de <b><?= e($t['nombre']) ?></b> (DNI <?= e($t['doc']) ?>, afiliado N° <?= $t['nro'] ?>) la suma de <b><?= money($r['importe']) ?></b> — son pesos <i><?= e(Fmt::enLetras((float)$r['importe'])) ?></i> — en concepto de:</p>
  <table><thead><tr><th>Concepto</th><th>Comprobante</th><th class="r">Importe</th></tr></thead><tbody><tr><td>Cuota <?= e(Fmt::perLabel($f['periodo'])) ?> · <?= e($plan['nombre']) ?></td><td class="mono" style="font-size:13px">Factura <?= e($f['letra'] . ' ' . $f['nro']) ?></td><td class="r num"><?= money($r['importe']) ?></td></tr></tbody></table>
  <div class="row" style="justify-content:space-between"><span>Medio de pago: <b><?= e($r['medio']) ?></b></span><b style="font-size:20px">Total <?= money($r['importe']) ?></b></div>
  <div class="muted" style="font-size:12.5px">Documento no válido como factura<?= $r['masivo'] ? ' · Cobro por débito automático' : '' ?></div>
<?php endif ?>
</div>
