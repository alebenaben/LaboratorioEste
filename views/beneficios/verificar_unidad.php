<?php /** CU-22 · Verificar afiliado (unidad). @var array $usos @var array $unidad */ use App\Domain\Beneficios; ?>
<div class="inicio" style="min-height:auto;justify-content:flex-start;padding-top:10px">
  <div class="logo-grande" role="img" aria-label="Integral del Este"><?= logo_svg() ?><span class="wm">Integral<br>del Este</span></div>
  <h1><?= e($unidad['nombre_largo']) ?></h1><p>Unidad de negocio · beneficios para afiliados de Integral del Este</p></div>
<div class="panel" style="max-width:720px;margin:0 auto;width:100%"><h2>Verificar afiliado</h2><p class="sub">Escribí el DNI, el CUIT, el número de afiliado o el apellido y nombre.</p>
  <form id="fver" class="row" style="flex-wrap:nowrap" data-modo="unidad"><input id="ver-q" name="q" autocomplete="off" placeholder="DNI, N° de afiliado o apellido" style="flex:1;font-size:18px" data-sugerir="afiliados" autofocus><button class="btn primary" type="submit">Verificar</button></form>
  <div id="ver-res" style="margin-top:14px"></div></div>
<div class="panel" style="max-width:720px;margin:0 auto;width:100%"><h2>Usos registrados hoy</h2>
<?php if ($usos): ?><div class="tbl-wrap"><table><tbody>
<?php foreach ($usos as $l): ?><tr><td class="mono">N° <?= (int)$l['nro'] ?></td><td><?= e(\App\Domain\Fmt::capi($l['nombre1'])) ?> <?= e(mb_substr($l['apellido'], 0, 1)) ?>.</td><td><?= e($l['nombre']) ?><?= $l['cantidad'] > 1 ? ' × ' . (int)$l['cantidad'] : '' ?></td><td><?= (int)$l['gratis'] === (int)$l['cantidad'] ? chip('Sin cargo', 'ok') : ((float)$l['pct'] ? chip(Beneficios::pct((float)$l['pct'])) : '') ?></td><td class="r muted"><?= e(substr((string)$l['hora'], 0, 5)) ?></td></tr><?php endforeach ?>
</tbody></table></div><?php else: ?><p class="muted" style="margin:0">Todavía no registraste usos hoy.</p><?php endif ?></div>
<span hidden data-esc="reset"></span>
<script src="/assets/verificar.js?v=83" defer></script>
