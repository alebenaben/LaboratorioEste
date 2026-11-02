<?php /** CU-01 · Ingreso. @var string $msg */ $ver = \App\Domain\Version::actual(); ?>
<div id="login"><div class="login"><form id="flogin" method="post" action="/ingreso" data-ajax data-login aria-label="Ingreso">
  <div class="logo" role="img" aria-label="Integral del Este"><?= logo_svg() ?><span class="wm">Integral<br>del Este</span></div>
  <p style="margin:0;color:var(--muted)">Ingresá con tu usuario y clave.</p>
  <div class="f" style="display:flex;flex-direction:column;gap:5px;font-size:13px;color:var(--muted);font-weight:500"><label for="lg-u">Usuario</label>
    <div class="combo"><input id="lg-u" name="usuario" autocomplete="username" autocapitalize="off" autocorrect="off" spellcheck="false" required role="combobox" aria-expanded="false" aria-controls="lg-list" aria-autocomplete="list">
      <button class="tab" type="button" id="lg-tab" aria-label="Ver usuarios" aria-expanded="false" hidden><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></button>
      <ul id="lg-list" role="listbox" hidden></ul></div></div>
  <label class="f">Clave<input id="lg-p" name="clave" type="password" autocomplete="current-password" required></label>
  <p id="lg-err" class="form-error" style="margin:0;color:var(--bad);<?= $msg ? '' : 'display:none' ?>"><?= e($msg) ?></p>
  <button class="btn primary" type="submit">Ingresar</button>
  <span class="muted" style="font-size:12.5px">Versión <?= $ver['v'] ?> · <?= fd($ver['fecha']) ?></span>
</form></div></div>
