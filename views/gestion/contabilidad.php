<?php /** CU-34 · Contabilidad. @var array $cuentas @var array $saldos @var array $asientos @var string $hoy */
$cn = array_column($cuentas, 'nombre', 'codigo'); ?>
<?= head('Contabilidad', 'Las facturas, los recibos y los egresos generan sus asientos automáticamente.') ?>
<div class="grid2">
  <div class="panel"><h2>Plan de cuentas</h2><p class="sub">Saldos al <?= fd($hoy) ?>.</p><div class="tbl-wrap"><table><thead><tr><th>Código</th><th>Cuenta</th><th class="r">Saldo</th></tr></thead><tbody>
  <?php foreach ($cuentas as $c): $l = $c['imputable']; ?><tr><td class="mono"><?= e($c['codigo']) ?></td><td style="<?= $l ? 'padding-left:26px' : 'font-weight:600' ?>"><?= e($c['nombre']) ?></td><td class="r num" style="<?= $l ? '' : 'font-weight:600' ?>"><?= money($saldos[$c['codigo']] ?? 0) ?></td></tr><?php endforeach ?>
  </tbody></table></div></div>
  <div class="panel"><h2>Libro diario</h2><p class="sub">Últimos 12 asientos. El libro completo está en Informes.</p><div class="tbl-wrap"><table><thead><tr><th>Fecha</th><th>Cuenta</th><th class="r">Debe</th><th class="r">Haber</th></tr></thead><tbody>
  <?php foreach ($asientos as $a): ?><tr><td colspan="4" style="background:var(--sunken);font-size:13.5px"><span class="num"><?= fd($a['fecha']) ?></span> · <?= e($a['det']) ?></td></tr>
    <?php foreach ($a['l'] as [$c, $d, $h]): ?><tr><td></td><td style="<?= $h ? 'padding-left:28px' : '' ?>"><?= e($c . ' ' . ($cn[$c] ?? '')) ?></td><td class="r num"><?= $d ? money($d) : '' ?></td><td class="r num"><?= $h ? money($h) : '' ?></td></tr><?php endforeach ?>
  <?php endforeach;
  if (!$asientos): ?><tr><td colspan="4" class="muted">Todavía no hay asientos.</td></tr><?php endif ?>
  </tbody></table></div></div>
</div>
