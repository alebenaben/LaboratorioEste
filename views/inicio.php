<?php /** Inicio de los usuarios de atención. @var array $ops @var array $textos */ use App\Domain\Permisos; ?>
<div class="inicio"><div class="logo-grande" role="img" aria-label="Integral del Este"><?= logo_svg() ?><span class="wm">Integral<br>del Este</span></div>
  <h1>Hola, <?= e(yo()['nombre']) ?></h1><p>¿Qué querés hacer?</p>
  <div class="tiles-inicio">
  <?php foreach ($ops as $id): ?>
    <a class="tile-inicio" href="/<?= e($id) ?>"><span class="ico"><?= icono($id) ?></span><span class="tx"><b><?= e(Permisos::nombreVista($id)) ?></b><span><?= e($textos[$id] ?? '') ?></span></span></a>
  <?php endforeach ?>
  <?php if (!$ops): ?><p>No tenés secciones asignadas. Pedile a un administrador.</p><?php endif ?>
  </div>
  <p style="margin:0;font-size:13.5px"><kbd>Esc</kbd> te trae de vuelta a esta pantalla.</p>
</div>
