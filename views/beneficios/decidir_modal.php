<?php /** CU-21 · Aprobar o rechazar. @var array $x @var bool $ok */ ?>
<form class="modal" role="dialog" aria-modal="true" aria-label="Decisión" method="post" action="/auditoria/<?= (int)$x['id'] ?>" data-ajax>
  <?= csrf_field() ?><input type="hidden" name="ok" value="<?= $ok ? 1 : 0 ?>">
  <h2 style="color:<?= $ok ? 'var(--ok)' : 'var(--bad)' ?>"><?= $ok ? 'Aprobar' : 'Rechazar' ?> solicitud N° <?= (int)$x['nro'] ?></h2>
  <p><?= e($x['nombre']) ?> · <?= e($x['prest']) ?></p>
  <label class="f"><?= $ok ? 'Comentario (opcional)' : 'Motivo del rechazo' ?><textarea name="texto" id="texto" rows="3" <?= $ok ? '' : 'required' ?>></textarea></label>
  <p class="form-error" style="display:none"></p>
  <div class="row" style="justify-content:flex-end"><button class="btn" type="button" data-act="modal-no">Cancelar <kbd>Esc</kbd></button><button class="btn <?= $ok ? 'primary' : 'danger' ?>" type="submit"><?= $ok ? 'Aprobar' : 'Rechazar' ?></button></div>
</form>
