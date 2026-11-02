<?php
/** CU-25 · Historial de beneficios. @var array $a @var array $g @var array $saldo @var float $ahorroY @var array $usos @var string $hoy */
use App\Domain\Beneficios;
use App\Domain\Catalogo;
?>
<div class="modal" role="dialog" aria-modal="true" aria-label="Historial" style="width:min(760px,100%)">
  <h2>Historial de beneficios</h2>
  <p><?= e($a['nombre']) ?> · N° <?= $a['nro'] ?> · <?= e(Catalogo::plan($g['plan_id'])['nombre']) ?></p>
  <?php if ($ahorroY): ?><div class="verdict ok">Ahorro en <?= substr($hoy, 0, 4) ?>: <b><?= money($ahorroY) ?></b></div><?php endif ?>
  <div style="max-height:62vh;overflow:auto;display:flex;flex-direction:column;gap:12px">
  <?= \App\Core\View::parcial('parciales/beneficios_tabla', ['a' => $a, 'saldo' => $saldo, 'compacto' => false, 'hoy' => $hoy, 'sinBotones' => true]) ?>
  <div class="tbl-wrap"><table><thead><tr><th>Fecha</th><th>Unidad</th><th>Prestación</th><th class="r">Cant.</th><th>Beneficio</th><th class="r">Pagó</th><th></th></tr></thead><tbody>
  <?php foreach ($usos as $l): $g2 = (int)$l['gratis']; $c = (int)$l['cantidad']; ?>
    <tr><td class="num nw"><?= fd($l['fecha']) ?></td><td><?= e($l['unidad']) ?></td><td><?= e($l['nombre']) ?><?= $l['pendiente_unidad'] ? '<div class="muted" style="font-size:12px">autorizado, falta que la unidad lo registre</div>' : '' ?></td><td class="r num"><?= $c ?></td>
      <td class="nw"><?= e(implode(' + ', array_filter([$g2 ? "$g2 sin cargo" : '', (float)$l['pct'] && $c > $g2 ? Beneficios::pct((float)$l['pct']) : ''])) ?: '—') ?></td>
      <td class="r num"><?= (float)$l['lista'] ? money($l['total']) : '—' ?></td><td><button class="btn small" type="button" data-modal="/usos/<?= (int)$l['uso_id'] ?>">Ver</button></td></tr>
  <?php endforeach;
  if (!$usos): ?><tr><td colspan="7" class="muted">Todavía no usó beneficios.</td></tr><?php endif ?>
  </tbody></table></div></div>
  <div class="row" style="justify-content:flex-end"><button class="btn" type="button" data-act="modal-no">Cerrar <kbd>Esc</kbd></button></div>
</div>
