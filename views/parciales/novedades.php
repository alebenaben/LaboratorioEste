<?php /** CU-52 · Versiones del sistema. @var array $versiones */ ?>
<div class="modal" role="dialog" aria-modal="true" aria-label="Versiones del sistema" style="width:min(760px,100%)">
  <h2>Versiones del sistema</h2>
  <p>Novedades de cada versión, la más nueva primero.</p>
  <div style="max-height:60vh;overflow:auto"><ul class="vlist">
  <?php foreach ($versiones as $x): ?>
    <li><b>Versión <?= (int)$x['v'] ?></b><span><?= fd($x['fecha']) ?></span><span></span><span style="color:var(--ink)"><?= e($x['t']) ?></span></li>
  <?php endforeach ?>
  </ul></div>
  <div class="row" style="justify-content:flex-end"><button class="btn" type="button" data-act="modal-no">Cerrar <kbd>Esc</kbd></button></div>
</div>
