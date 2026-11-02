<?php
/** CU-32 · Cuentas corrientes. @var array $lista @var ?array $det */
use App\Domain\Catalogo;

$sel = $det['g']['id'] ?? 0;
?>
<?= head('Cuentas corrientes', 'Lo facturado y lo cobrado de cada grupo. El titular es quien paga por todos.') ?>
<div class="tbl-wrap"><table><thead><tr><th>Titular</th><th class="r">Integrantes</th><th>Paga con</th><th class="r">Cuota actual</th><th class="r">Saldo</th><th>Estado</th></tr></thead><tbody>
<?php foreach ($lista as $x): ?>
  <tr data-href="/cuentas?g=<?= $x['g']['id'] ?>#cta-det" tabindex="0"<?= $sel === $x['g']['id'] ? ' class="selrow"' : '' ?>><td><b><?= e($x['t']['nombre']) ?></b></td><td class="r num"><?= $x['n'] ?></td><td><?= e($x['g']['forma_pago']) ?></td><td class="r num"><?= money($x['cuota']) ?></td><td class="r num"><?= money($x['saldo']) ?></td><td><?= chip_cuenta($x['estado']) ?></td></tr>
<?php endforeach ?>
</tbody></table></div>
<?php if ($det): ?>
<div class="panel" id="cta-det"><div class="row" style="justify-content:space-between;margin-bottom:12px"><div><h2><?= e($det['t']['nombre']) ?></h2><p class="sub" style="margin:0"><?= e(implode(', ', array_column($det['activos'], 'nombre'))) ?></p></div>
  <div style="text-align:right"><small class="muted">Saldo</small><div class="num" style="font-size:20px;font-weight:650"><?= money($det['cuenta']['saldo']) ?></div></div></div>
  <div class="tbl-wrap"><table><thead><tr><th>Fecha</th><th>Comprobante</th><th>Detalle</th><th class="r">Debe</th><th class="r">Haber</th><th class="r">Saldo</th></tr></thead><tbody>
  <?php foreach ($det['mov'] as $m): ?><tr><td class="num"><?= fd($m['fecha']) ?></td><td><?= e($m['tipo']) ?> <span class="mono"><?= e($m['nro']) ?></span><?php if (!empty($m['fid'])): ?> <button class="btn small" type="button" data-modal="/facturas/<?= (int)$m['fid'] ?>">Ver</button><?php endif ?><?php if (!empty($m['rid'])): ?> <button class="btn small" type="button" data-modal="/recibos/<?= (int)$m['rid'] ?>">Ver</button><?php endif ?></td><td><?= e($m['det']) ?></td><td class="r num"><?= $m['debe'] ? money($m['debe']) : '' ?></td><td class="r num"><?= $m['haber'] ? money($m['haber']) : '' ?></td><td class="r num"><?= money($m['saldo']) ?></td></tr><?php endforeach ?>
  </tbody></table></div>
  <?php if ($det['cuenta']['pendientes']): ?>
  <form class="row" style="margin-top:14px;justify-content:flex-end" method="post" action="/caja/cobrar" data-ajax><?= csrf_field() ?><input type="hidden" name="grupo" value="<?= $det['g']['id'] ?>">
    <select name="medio" aria-label="Medio de pago"><?php foreach (Catalogo::MEDIOS as $m): ?><option<?= sel($m, $det['g']['forma_pago']) ?>><?= e($m) ?></option><?php endforeach ?></select>
    <button class="btn primary" type="submit">Cobrar <?= count($det['cuenta']['pendientes']) ?> cuota(s) y emitir recibo</button></form>
  <?php endif ?>
</div>
<?php else: ?><p class="muted">Elegí un grupo para ver su cuenta corriente.</p><?php endif ?>
