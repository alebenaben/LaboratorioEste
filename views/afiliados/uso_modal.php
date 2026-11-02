<?php
/** Comprobante de un uso en una unidad. @var array $u @var array $lineas */
use App\Domain\Beneficios;

$saldos = json_decode((string)$u['saldos'], true) ?: [];
?>
<div class="modal comp" role="dialog" aria-modal="true" aria-label="Uso" style="width:min(620px,100%)">
  <h2>Uso de beneficio <span class="mono" style="font-size:14px"><?= e($u['numero']) ?></span></h2>
  <p><?= e($u['unidad']) ?> · <?= fd($u['fecha']) ?> <?= e(substr((string)$u['hora'], 0, 5)) ?> · <?= e($u['nombre']) ?> (N° <?= (int)$u['nro'] ?>)</p>
  <div class="tbl-wrap"><table><thead><tr><th>Prestación</th><th class="r">Cant.</th><th>Beneficio</th><th class="r">Precio</th><th class="r">Ahorro</th></tr></thead><tbody>
  <?php foreach ($lineas as $l): ?><tr><td><?= e($l['nombre']) ?></td><td class="r num"><?= (int)$l['cantidad'] ?></td><td><?= e(Beneficios::textoAplicado(['gratis' => (int)$l['gratis'], 'cantidad' => (int)$l['cantidad'], 'pct' => (float)$l['pct']])) ?></td><td class="r num"><?= (float)$l['precio'] ? money($l['precio']) : '—' ?></td><td class="r num"><?= money($l['ahorro_ef']) ?></td></tr><?php endforeach ?>
  </tbody></table></div>
  <?php foreach ($saldos as $s): ?><p class="muted" style="margin:0;font-size:13px"><?= e($s['nombre']) ?>: usadas <?= (int)$s['usados'] ?> de <?= (int)$s['total'] ?> sin cargo <?= e($s['vent']) ?><?= $s['grupo'] ? ' en el grupo' : '' ?> · le quedan <?= (int)$s['quedan'] ?>.</p><?php endforeach ?>
  <p class="muted" style="font-size:12.5px;margin:0">Registró: <?= e($u['usuario']) ?><?= $u['confirmo'] ? ' · confirmó ' . e($u['confirmo']) . ' el ' . fd($u['fecha_conf']) : '' ?><?= $u['pendiente_unidad'] ? ' · pendiente de registrar en la unidad' : '' ?></p>
  <div class="row" style="justify-content:flex-end"><button class="btn" type="button" data-act="modal-no">Cerrar <kbd>Esc</kbd></button></div>
</div>
