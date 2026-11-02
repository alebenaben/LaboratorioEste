<?php
/**
 * Layout de las pantallas internas: menú lateral para administradores; barra
 * superior y pantalla de inicio para usuarios de atención, unidades y comercios.
 * @var string $contenido @var string $vista
 */
use App\Core\App;
use App\Core\Usuarios;
use App\Domain\Fechas;
use App\Domain\Fmt;
use App\Domain\Permisos;
use App\Domain\Version;
use App\Http\Comun;

$u = App::$usuario;
$vista = $vista ?? '';
$admin = $u && $u['admin'];
$externo = $u && Permisos::esExterno($u);
$modoUsuario = $u && !$admin;
$ver = Version::actual();
$flash = App::$sesion?->tomarFlash() ?? [];
$csrf = App::$sesion?->csrf() ?? '';
$pend = $admin ? Comun::pendientesAuditoria() : 0;
$avisoAud = $admin ? (int)(App::$sesion?->dato('aviso_auditoria') ?? 0) : 0;
if ($avisoAud) {
    App::$sesion->guardarDato('aviso_auditoria', null);
}
$salirSvg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5M21 12H9"/></svg>';
$bodyClass = trim(($modoUsuario ? 'modo-usuario' : '') . ($modoUsuario && $vista === 'inicio' ? ' en-inicio' : ''));
?><!doctype html>
<html lang="es-AR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="csrf" content="<?= e($csrf) ?>">
<meta name="inactividad" content="<?= \App\Core\Sesion::minutosInactividad() ?>">
<title><?= e(Permisos::nombreVista($vista ?: 'inicio')) ?> · Integral del Este</title>
<link rel="icon" href="/assets/logo.svg" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500&family=Newsreader:opsz,wght@6..72,500;6..72,600&display=swap">
<link rel="stylesheet" href="/assets/app.css?v=<?= $ver['v'] ?>">
<link rel="stylesheet" href="/assets/extra.css?v=<?= $ver['v'] ?>">
<script src="/assets/app.js?v=<?= $ver['v'] ?>" defer></script>
<script src="/assets/widgets.js?v=<?= $ver['v'] ?>" defer></script>
</head>
<body class="<?= e($bodyClass) ?>" data-vista="<?= e($vista) ?>" data-rol="<?= e($u['rol'] ?? '') ?>">
<div class="app">
  <aside>
    <div class="brand"><div class="logo" role="img" aria-label="Integral del Este"><?= logo_svg() ?><span class="wm">Integral<br>del Este</span></div><span>Gestión de afiliados</span></div>
    <nav id="nav">
    <?php if ($u): foreach (Permisos::menu($u) as [$id, $n]): ?>
      <a href="/<?= e($id) ?>" data-view="<?= e($id) ?>"<?= $id === $vista ? ' aria-current="page"' : '' ?>><?= icono($id) ?><?= e($n) ?><?php if ($id === 'auditoria' && $pend): ?><span class="nav-badge" aria-label="<?= $pend ?> pendientes"><?= $pend ?></span><?php endif ?></a>
    <?php endforeach; endif ?>
    </nav>
    <div class="side-foot">
      <?php if ($u): ?>
      <div class="userbox"><b><?= e($u['nombre']) ?></b><small><?= $admin ? 'Administrador' : 'Usuario' ?></small>
        <div class="row" style="align-items:center"><a class="linklike" href="/mi-clave">Cambiar mi clave</a><button class="btn small btn-salir" type="button" data-act="salir" title="Cerrar sesión"><?= $salirSvg ?>Salir</button></div></div>
      <?php endif ?>
      <span id="esc-hint"><kbd>Esc</kbd> <?= $modoUsuario ? 'cancela o vuelve al inicio.' : 'cancela lo que estés haciendo.' ?></span>
      <button class="linklike" type="button" data-act="novedades">Versión <?= $ver['v'] ?> · <?= fd($ver['fecha']) ?></button>
    </div>
  </aside>
  <main id="main">
  <?php if ($modoUsuario): ?>
    <div class="ubar"><div class="izq"><a class="logo" href="/<?= $externo ? 'verificar' : 'inicio' ?>" aria-label="Ir al inicio"><?= logo_svg() ?><span class="wm">Integral<br>del Este</span></a>
      <?php if ($vista !== 'inicio' && !$externo): ?><a class="btn small" href="/inicio" data-esc-inicio>‹ Inicio <kbd>Esc</kbd></a><?php endif ?></div>
      <div class="der"><button class="linklike" type="button" data-act="novedades">v<?= $ver['v'] ?></button><a class="linklike" href="/mi-clave">Cambiar mi clave</a><span class="quien"><?= e(Usuarios::visible($u)) ?></span><button class="btn small btn-salir" type="button" data-act="salir" title="Cerrar sesión"><?= $salirSvg ?>Salir</button></div></div>
  <?php elseif ($u): ?>
    <div class="abar"><a class="linklike" href="/mi-clave">Cambiar mi clave</a><span class="quien"><?= e($u['nombre']) ?> <small>· Administrador</small></span><button class="btn small btn-salir" type="button" data-act="salir" title="Cerrar sesión"><?= $salirSvg ?>Salir</button></div>
  <?php endif ?>

  <?php if ($u && !$externo && !$u['debe_cambiar_clave']):
      $cumple = Comun::cumpleanios();
      if ($cumple): $hoy = Fechas::hoy(); ?>
    <div class="alerta-aud" role="status" style="background:var(--ok-soft);color:var(--ok);border-color:var(--ok)">
      <span>🎂 Hoy <?= count($cumple) === 1 ? 'cumple' : 'cumplen' ?> años: <?= e(implode(', ', array_map(fn($a) => $a['nombre'] . ' (' . Fechas::edad($a['nac'], $hoy) . ')', $cumple))) ?></span>
      <span class="row" style="gap:6px"><?php foreach (array_slice($cumple, 0, 4) as $a): ?><button class="btn small btn-wa" type="button" data-act="cumple-wa" data-a="<?= (int)$a['id'] ?>">Saludar a <?= e(Fmt::capi($a['nombre1'] ?: $a['nombre'])) ?></button><?php endforeach ?></span>
    </div>
  <?php endif; endif ?>
  <?php if ($admin && $vista !== 'estadisticas' && ($nres = Comun::resumenesPendientes())): ?>
    <div class="alerta-aud" role="status" style="background:var(--accent-soft);color:var(--accent);border-color:var(--accent)"><span>📩 Hay <?= $nres ?> <?= $nres === 1 ? 'afiliado' : 'afiliados' ?> para mandarles el resumen de beneficios de <?= e(Fmt::perLabel(Fechas::mesAnterior(Fechas::hoy()))) ?>.</span><a class="btn small" href="/estadisticas#resumen">Ver y enviar</a></div>
  <?php endif ?>
  <?php if ($admin && $vista !== 'auditoria' && $pend): ?>
    <div class="alerta-aud" role="alert"><span>⚠ Tenés <?= $pend ?> <?= $pend === 1 ? 'prestación pendiente' : 'prestaciones pendientes' ?> de auditoría.</span><a class="btn small" href="/auditoria">Ir a auditoría</a></div>
  <?php endif ?>
  <?php if ($admin): foreach (Comun::avisosSinLeer() as $av): ?>
    <div class="alerta-aud" role="status" style="background:var(--<?= e($av['nivel']) ?>-soft);color:var(--<?= e($av['nivel'] === 'info' ? 'accent' : $av['nivel']) ?>);border-color:currentColor"><span><?= e($av['texto']) ?></span><button class="btn small" type="button" data-post="/api/avisos/<?= (int)$av['id'] ?>/leido">Entendido</button></div>
  <?php endforeach; endif ?>

  <?= $contenido ?>
  </main>
</div>
<div id="layer"></div>
<div id="layer2"><?php if ($avisoAud): ?><div class="overlay center" data-close-modal><div class="modal" role="alertdialog" aria-modal="true" aria-label="Auditoría pendiente" style="border:2px solid var(--logo-red)"><h2 style="color:var(--logo-red)">⚠ Prestaciones para auditar</h2><p>Hay <b><?= $avisoAud ?></b> <?= $avisoAud === 1 ? 'solicitud' : 'solicitudes' ?> de autorización esperando tu aprobación.</p><div class="row" style="justify-content:flex-end"><button class="btn" type="button" data-act="modal-no">Más tarde</button><a class="btn primary" href="/auditoria">Ir a auditoría</a></div></div></div><?php endif ?></div>
<?php if ($flash): ?><script type="application/json" id="flash"><?= json_encode($flash, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script><?php endif ?>
</body>
</html>
