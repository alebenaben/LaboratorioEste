<?php /** @var int $codigo @var string $mensaje */ ?>
<div class="panel" style="max-width:560px;margin:40px auto">
  <h2><?= $codigo === 403 ? 'Sin permiso' : ($codigo === 404 ? 'No encontrado' : 'Algo salió mal') ?></h2>
  <p><?= e($mensaje) ?></p>
  <div class="row"><a class="btn primary" href="/">Volver al inicio</a></div>
</div>
