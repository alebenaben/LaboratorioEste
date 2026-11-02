<?php /** CU-23 · Verificar afiliado (comercio). @var array $usos @var ?array $benef @var ?array $rubro */ use App\Domain\Catalogo; use App\Domain\Fmt; $u = yo(); ?>
<div class="inicio" style="min-height:auto;justify-content:flex-start;padding-top:10px">
  <div class="logo-grande" role="img" aria-label="Integral del Este"><?= logo_svg() ?><span class="wm">Integral<br>del Este</span></div>
  <h1><?= e($u['comercio_nombre']) ?></h1><p>Comercio adherido · <?= e($u['comercio_rubro']) ?></p></div>
<div class="panel" style="max-width:640px;margin:0 auto;width:100%"><h2>Verificar afiliado</h2><p class="sub">Pedile el DNI o el número de afiliado de la credencial.</p>
  <form id="fver" class="row" style="flex-wrap:nowrap" data-modo="comercio"><input id="ver-q" name="q" class="docfmt" inputmode="numeric" autocomplete="off" placeholder="DNI o N° de afiliado" style="flex:1;font-size:18px" autofocus><button class="btn primary" type="submit">Verificar</button></form>
  <div id="ver-res" style="margin-top:14px"></div></div>
<div class="panel" style="max-width:640px;margin:0 auto;width:100%"><h2>Beneficio que ofrecen</h2>
<?php $hay = false; foreach (Catalogo::planes() as $p): $x = $benef['planes'][$p['id']] ?? null; if (!$x) continue; $hay = true; ?><p style="margin:6px 0"><b><?= e($p['nombre']) ?>:</b> <?= e($x[0]) ?></p><?php endforeach ?>
<?= $hay ? '' : '<p class="muted">Sin beneficio cargado.</p>' ?>
<?php if ($rubro): ?><p class="muted" style="font-size:13px;margin:6px 0 0">Descuento máximo <?= Fmt::num($rubro['max_pct']) ?>%<?= (int)$rubro['limite'] ? ' · hasta ' . (int)$rubro['limite'] . ' ' . ((int)$rubro['limite'] === 1 ? 'uso' : 'usos') . ' ' . \App\Domain\Beneficios::PER_TXT[$rubro['per']] : '' ?>.</p><?php endif ?></div>
<div class="panel" style="max-width:640px;margin:0 auto;width:100%"><h2>Consumos registrados hoy</h2>
<?php if ($usos): ?><div class="tbl-wrap"><table><tbody>
<?php foreach ($usos as $c): ?><tr><td class="mono">N° <?= (int)$c['nro'] ?></td><td><?= e(Fmt::capi($c['nombre1'])) ?> <?= e(mb_substr($c['apellido'], 0, 1)) ?>.</td><td><?= e($c['detalle'] ?: '—') ?></td><td class="r num"><?= (float)$c['importe'] ? money($c['total']) : '' ?><?= (float)$c['pct'] ? '<div class="muted" style="font-size:12px">−' . Fmt::num($c['pct']) . '%</div>' : '' ?></td><td class="r muted"><?= e(substr((string)$c['hora'], 0, 5)) ?></td></tr><?php endforeach ?>
</tbody></table></div><?php else: ?><p class="muted" style="margin:0">Todavía no registraste consumos hoy.</p><?php endif ?></div>
<span hidden data-esc="reset"></span>
<script src="/assets/verificar.js?v=83"></script>
