<?php
/** CU-15 · Cotizador. @var array $nombresPadron */
use App\Core\Config;
use App\Domain\Catalogo;
use App\Domain\Documentos;

$planes = Catalogo::planes();
$datos = [
    'precios' => Catalogo::precios(),
    'planes' => array_map(fn($p) => ['id' => $p['id'], 'nombre' => $p['nombre'], 'individual' => $p['individual']], array_values($planes)),
    'cats' => array_map(fn($c) => $c['nombre'], Catalogo::categorias()),
    'nombres' => array_values(array_unique(array_merge(array_map([\App\Domain\Fmt::class, 'norm'], \App\Domain\CatalogoMaqueta::NOMBRES_AR), $nombresPadron))),
];
?>
<?= head('Cotizador', 'Cuánto pagaría un grupo en cada plan y qué beneficios trae. La cuota depende del plan y de la categoría de cada integrante.', '<span id="promo-badge"></span>') ?>
<div class="panel" style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:14px">
  <div style="display:flex;flex-direction:column;gap:8px"><b style="font-size:14px">Plan a cotizar</b>
    <div class="plan-sel" role="group" aria-label="Plan a cotizar"><?php foreach ($planes as $p): ?><button type="button" data-cot-plan="<?= e($p['id']) ?>" aria-pressed="<?= $p['id'] === 'PLUS' ? 'true' : 'false' ?>"><?= e(str_replace(' del Este', '', $p['nombre'])) ?></button><?php endforeach ?></div></div>
  <div class="row" style="align-items:flex-end;gap:10px;flex-wrap:wrap">
    <label class="f" style="margin:0;width:230px">Apellido y nombre del interesado<input id="cot-nom" class="mayus" placeholder="Ej.: GÓMEZ MARÍA JOSÉ" autocomplete="off"><small id="cot-nom-det" class="muted" style="font-size:12px;min-height:15px"></small></label>
    <label class="f" style="margin:0;width:190px">Celular<input id="cot-tel" inputmode="tel" placeholder="Ej.: 3435013651" autocomplete="off"><small id="cot-tel-det" class="muted" style="font-size:12px;min-height:15px"></small></label>
    <button class="btn btn-wa" type="button" id="wa-cot">Enviar cotización en PDF</button>
    <button class="btn primary" type="button" id="cot-iniciar">Iniciar alta</button>
  </div>
</div>
<div class="grid2" style="grid-template-columns:repeat(auto-fit,minmax(300px,1fr))">
  <div class="panel"><h2>Integrantes</h2><p class="sub">Elegí la categoría de cada persona.</p>
    <div id="cot-list" style="display:flex;flex-direction:column;gap:8px"></div>
    <p class="muted" id="cot-indiv" style="font-size:13px;margin:8px 0 0" hidden>Plan individual: solo cubre al titular.</p>
    <div class="row" style="margin-top:12px"><button class="btn small" type="button" id="cot-add">Agregar integrante</button></div></div>
  <div class="panel" style="min-width:0"><div class="row" style="justify-content:space-between;align-items:flex-start;margin-bottom:10px"><div><h2>Cuota mensual por plan</h2><p class="sub" style="margin:0">Elegí un plan para iniciar el alta.</p></div><button class="btn btn-promo" type="button" id="btn-promo">Promoción</button></div><div class="plans" id="cot-plans"></div></div>
</div>
<div class="grid2">
<?php foreach ($planes as $p): $fs = Documentos::filasBenef($p['id']); if (!$fs) continue; ?>
  <div class="panel"><h2 style="color:<?= $p['id'] === 'PLUS' ? 'var(--logo-red)' : 'var(--accent)' ?>"><?= e($p['nombre']) ?></h2><p class="sub"><?= count($fs) ?> <?= count($fs) === 1 ? 'beneficio' : 'beneficios' ?>. El detalle completo y el buscador están en Beneficios.</p>
    <div class="checks"><?php foreach ($fs as [$rb, $t, $c]): ?><div class="check" style="grid-template-columns:minmax(0,1fr)"><div><b><?= e($rb) ?></b><span><?= e($t) ?></span><span style="color:var(--warn);font-size:12px">Carencia: <?= e($c) ?></span></div></div><?php endforeach ?></div>
    <div class="row" style="margin-top:12px"><button class="btn small btn-wa" type="button" data-wa-plan="<?= e($p['id']) ?>">Enviar este plan por WhatsApp</button></div></div>
<?php endforeach ?>
</div>
<div class="panel"><h2>Precios vigentes</h2><p class="sub">Valor mensual por integrante. Se cambian en Configuración.</p>
<div class="tbl-wrap"><table><thead><tr><th>Categoría</th><?php foreach ($planes as $p): ?><th class="r"><?= e($p['nombre']) ?></th><?php endforeach ?></tr></thead>
<tbody><?php foreach (Catalogo::categorias() as $c): ?><tr><td><b><?= e($c['nombre']) ?></b></td><?php foreach ($planes as $p): $pr = Catalogo::precio($c['id'], $p['id']); ?><td class="r num"><?= $pr ? money($pr) : '<span class="muted">a definir</span>' ?></td><?php endforeach ?></tr><?php endforeach ?></tbody></table></div></div>
<script type="application/json" id="cot-datos"><?= json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
<script src="/assets/cotizador.js?v=83" defer></script>
