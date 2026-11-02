<?php /** Configuración para usuarios que no son administradores. @var array $versiones */ $u = yo(); ?>
<?= head('Configuración', 'Tu cuenta y las versiones del sistema. El resto de la configuración la maneja un administrador.') ?>
<div class="panel row" style="justify-content:space-between"><div><h2>Mi cuenta</h2><p class="sub" style="margin:0">Ingresaste como <b><?= e(\App\Core\Usuarios::visible($u)) ?></b> · <?= e(\App\Domain\Permisos::ROLES[$u['rol']]) ?>.</p></div>
  <div class="row"><a class="btn" href="/mi-clave">Cambiar mi clave</a><button class="btn" type="button" data-act="salir">Salir</button></div></div>
<div class="panel"><h2>Versiones del sistema</h2><p class="sub">Cada modificación sube el número de versión.</p>
  <ul class="vlist"><?php foreach ($versiones as $x): ?><li><b>Versión <?= (int)$x['v'] ?></b><span><?= fd($x['fecha']) ?></span><span></span><span style="color:var(--ink)"><?= e($x['t']) ?></span></li><?php endforeach ?></ul></div>
