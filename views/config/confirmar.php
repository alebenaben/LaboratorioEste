<?php /** CU-49 · Confirmá los cambios. @var string $bloque @var array $datos @var array $prep */ $n = count($prep['cambios']); ?>
<form class="modal" role="dialog" aria-modal="true" aria-label="Confirmar cambios" style="width:min(680px,100%)" method="post" action="/config/<?= e($bloque) ?>/confirmar" data-ajax>
  <?= csrf_field() ?><input type="hidden" name="datos" value="<?= e(json_encode($datos, JSON_UNESCAPED_UNICODE)) ?>">
  <h2>Confirmá los cambios</h2>
  <p style="margin:0">Se van a modificar <b><?= $n ?></b> <?= $n === 1 ? 'dato' : 'datos' ?>. Revisalos antes de guardar:</p>
  <div style="max-height:300px;overflow:auto;border:1px solid var(--line);border-radius:10px">
  <?php foreach ($prep['cambios'] as $c): ?><div style="padding:8px 12px;border-bottom:1px solid var(--line);font-size:14px"><span class="muted"><?= e($c['t']) ?> ·</span> <?= e($c['antes']) ?> <b style="color:var(--accent)">→</b> <?= e($c['despues']) ?></div><?php endforeach ?>
  </div>
  <?php if ($prep['alertas']): ?><div class="verdict bad" style="font-size:14px"><b>Atención, revisá esto:</b><br><?= implode('<br>', array_map('e', $prep['alertas'])) ?></div><?php endif ?>
  <label class="f">Tu clave, para confirmar<input name="clave" id="conf-pw" type="password" required autocomplete="current-password"></label>
  <p class="form-error" style="display:none"></p>
  <div class="row" style="justify-content:flex-end"><button class="btn" type="button" data-act="modal-no">Cancelar <kbd>Esc</kbd></button><button class="btn primary" type="submit">Sí, guardar los cambios</button></div>
</form>
