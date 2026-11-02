<?php /** CU-03 · Cambiar mi clave. @var bool $obligatorio */ ?>
<?= head('Cambiar mi clave', $obligatorio ? 'Es tu primer ingreso: elegí una clave nueva para seguir. Tiene que tener al menos 4 caracteres y no puede ser tu nombre de usuario.' : 'Escribí tu clave actual y la nueva dos veces.') ?>
<form class="panel" method="post" action="/mi-clave" data-ajax style="max-width:460px;display:flex;flex-direction:column;gap:14px">
  <?= csrf_field() ?>
  <label class="f">Clave actual<input name="actual" type="password" autocomplete="current-password" required autofocus></label>
  <label class="f">Clave nueva<input name="nueva" type="password" autocomplete="new-password" required minlength="4"></label>
  <label class="f">Repetir clave nueva<input name="repetir" type="password" autocomplete="new-password" required minlength="4"></label>
  <p class="form-error" style="color:var(--bad);display:none"></p>
  <div class="row" style="justify-content:flex-end"><?php if (!$obligatorio): ?><a class="btn" href="/">Cancelar <kbd>Esc</kbd></a><?php endif ?><button class="btn primary" type="submit">Guardar clave</button></div>
</form>
