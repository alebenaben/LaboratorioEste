<?php /** CU-39 · Opciones de un usuario. @var array $u @var bool $soy */ use App\Domain\Permisos; ?>
<div class="modal" role="dialog" aria-modal="true" aria-label="Opciones de <?= e($u['nombre']) ?>" style="width:min(560px,100%)">
  <h2><?= e($u['nombre']) ?></h2>
  <p class="muted" style="margin:0"><?= e(Permisos::ROLES[$u['rol']]) ?><?= $u['rol'] === 'comercio' ? ' · ' . e($u['comercio_nombre']) . ' (' . e($u['comercio_rubro']) . ')' : '' ?><?= $u['rol'] === 'unidad' ? ' · ' . e($u['unidad_nombre']) : '' ?> · <?= $u['activo'] ? 'Activo' : 'Dado de baja' ?></p>
  <form method="post" action="/config/usuarios/<?= $u['id'] ?>/clave" data-ajax class="row" style="align-items:flex-end;gap:8px">
    <?= csrf_field() ?><label class="f" style="flex:1;margin:0">Clave nueva<input name="clave" id="clave" type="password" minlength="4" required autocomplete="new-password"></label><button class="btn" type="submit">Cambiar clave</button>
  </form>
  <p class="muted" style="font-size:12.5px;margin:0"><?= $soy ? 'Es tu propia clave.' : 'Va a tener que cambiarla en su próximo ingreso.' ?></p>
  <p class="form-error" style="display:none"></p>
  <?php if ($u['rol'] === 'usuario'): ?>
  <form method="post" action="/config/usuarios/<?= $u['id'] ?>/secciones" data-ajax style="display:flex;flex-direction:column;gap:8px;border-top:1px solid var(--line);padding-top:12px">
    <?= csrf_field() ?><b>Qué ve</b>
    <div class="row" style="gap:14px"><?php foreach (Permisos::USR_VISTAS as $s): ?><label class="chk"><input type="checkbox" name="secciones[]" value="<?= e($s) ?>"<?= in_array($s, $u['secciones'], true) ? ' checked' : '' ?>> <?= e(Permisos::nombreVista($s)) ?></label><?php endforeach ?></div>
    <div class="row" style="justify-content:flex-end"><button class="btn" type="submit">Guardar secciones</button></div>
  </form>
  <?php endif ?>
  <div class="row" style="justify-content:space-between;border-top:1px solid var(--line);padding-top:12px">
    <span class="row" style="gap:8px">
      <?php if (!$soy && in_array($u['rol'], ['admin', 'usuario'], true)): ?><button class="btn" type="button" data-post="/config/usuarios/<?= $u['id'] ?>/rol" data-confirmar="<?= $u['rol'] === 'admin' ? '¿Quitarle el rol de administrador?' : '¿Hacerlo administrador? Va a ver y poder cambiar todo.' ?>"><?= $u['rol'] === 'admin' ? 'Quitar administrador' : 'Hacer administrador' ?></button><?php endif ?>
      <?php if (!$soy): ?><button class="btn <?= $u['activo'] ? 'danger' : '' ?>" type="button" data-post="/config/usuarios/<?= $u['id'] ?>/estado" data-confirmar="<?= $u['activo'] ? '¿Dar de baja a ' . e($u['nombre']) . '? No va a poder ingresar.' : '¿Reactivar a ' . e($u['nombre']) . '?' ?>"><?= $u['activo'] ? 'Dar de baja' : 'Reactivar' ?></button><?php endif ?>
    </span>
    <button class="btn" type="button" data-act="modal-no">Cerrar <kbd>Esc</kbd></button>
  </div>
</div>
