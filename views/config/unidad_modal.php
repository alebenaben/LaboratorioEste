<?php /** CU-43 · Editar unidad de negocio. @var array $u @var ?array $us @var bool $conUsos */ ?>
<div class="modal" role="dialog" aria-modal="true" aria-label="Unidad <?= e($u['nombre']) ?>" style="width:min(600px,100%)">
  <h2><?= e($u['nombre_largo']) ?></h2>
  <?php if (!$u['activo']): ?>
    <div class="verdict warn" style="font-size:14px">Esta unidad está dada de baja: no puede ingresar ni verificar afiliados. Su historial se conserva.</div>
    <div class="row" style="justify-content:space-between"><span class="row" style="gap:8px"><button class="btn primary" type="button" data-post="/config/unidades/<?= (int)$u['id'] ?>/reactivar">Reactivar</button><?php if (!$u['original'] && !$conUsos): ?><button class="btn danger" type="button" data-post="/config/unidades/<?= (int)$u['id'] ?>/eliminar" data-confirmar="¿Eliminar la unidad <?= e($u['nombre']) ?>? No se puede deshacer.">Eliminar</button><?php endif ?></span><button class="btn" type="button" data-act="modal-no">Cerrar <kbd>Esc</kbd></button></div>
  <?php else: ?>
  <form method="post" action="/config/unidades/<?= (int)$u['id'] ?>/editar" data-ajax style="display:flex;flex-direction:column;gap:10px">
    <?= csrf_field() ?>
    <div class="form-grid" style="grid-template-columns:1fr 1fr">
      <label class="f">Nombre<input name="nombre" value="<?= e($u['nombre']) ?>"<?= $u['original'] ? ' readonly title="Las unidades originales no cambian de nombre"' : '' ?>></label>
      <label class="f">Carencia (meses)<input name="carencia" type="number" min="0" max="24" value="<?= (int)$u['carencia_meses'] ?>"<?= $u['original'] ? ' readonly' : '' ?>></label>
      <label class="f" style="grid-column:1/-1">Texto del beneficio<input name="texto" value="<?= e($u['texto_beneficio']) ?>"<?= $u['original'] ? ' readonly title="El beneficio de las unidades originales sale del PDF de beneficios"' : '' ?>></label>
      <label class="f">Usuario<input name="usuario" value="<?= e($us['nombre'] ?? '') ?>"></label>
      <label class="f">Clave nueva (opcional)<input name="clave" id="clave" type="password" autocomplete="new-password" placeholder="Si renombrás sin clave, la clave es el nombre"></label>
      <label class="f" style="grid-column:1/-1">Tu clave de administrador, para confirmar<input name="clave_admin" id="clave_admin" type="password" required autocomplete="current-password"></label>
    </div>
    <p class="form-error" style="display:none"></p>
    <div class="row" style="justify-content:space-between"><span class="row" style="gap:8px"><button class="btn danger" type="button" data-post="/config/unidades/<?= (int)$u['id'] ?>/baja" data-confirmar="¿Dar de baja la unidad <?= e($u['nombre']) ?>? Sus prestaciones y su usuario quedan inactivos.">Dar de baja</button><?php if (!$u['original'] && !$conUsos): ?><button class="btn danger" type="button" data-post="/config/unidades/<?= (int)$u['id'] ?>/eliminar" data-confirmar="¿Eliminar la unidad <?= e($u['nombre']) ?>?">Eliminar</button><?php endif ?></span>
      <span class="row" style="gap:8px"><button class="btn" type="button" data-act="modal-no">Cancelar <kbd>Esc</kbd></button><button class="btn primary" type="submit">Guardar</button></span></div>
  </form>
  <?php endif ?>
</div>
