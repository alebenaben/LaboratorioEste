<?php
/** CU-16 · Beneficios y CU-17 · Enviar información a una persona. @var array $nombresPadron */
use App\Domain\Catalogo;
use App\Domain\Fmt;

$planes = Catalogo::planes();
$benef = Catalogo::beneficiosPlan();
$datos = ['nombres' => array_values(array_unique(array_merge(array_map([Fmt::class, 'norm'], \App\Domain\CatalogoMaqueta::NOMBRES_AR), $nombresPadron)))];
?>
<?= head('Beneficios', 'Todo lo que incluye cada plan, con sus carencias. Escribí para buscar (por ejemplo: óptica, sesiones, farmacia, 30%).') ?>
<div class="panel" id="at-panel"><h2>Enviar información a una persona</h2><p class="sub">Cuando alguien pregunta por los planes o un afiliado quiere saber sus beneficios: se le manda un PDF prolijo por WhatsApp.</p>
  <p class="at-paso">1 · ¿A quién se lo mandás?</p>
  <div class="seg" role="group" aria-label="Tipo" style="margin-bottom:12px"><button type="button" data-at-tipo="int" class="on">A alguien que pregunta por los planes</button><button type="button" data-at-tipo="afi">A un afiliado</button></div>
  <div class="at-grid"><div style="display:flex;flex-direction:column;gap:12px">
    <div data-at="int"><p class="at-paso">2 · ¿Quién es y qué le interesa?</p>
      <label class="f" style="margin:0">Apellido y nombre<input id="at-nom" class="mayus" placeholder="Ej.: GÓMEZ MARÍA" autocomplete="off"><small id="at-nom-det" class="muted" style="font-size:12px"></small></label>
      <div id="at-planes" style="margin-top:10px"><div class="muted" style="font-size:13px;margin-bottom:6px">Planes que le interesan</div><div class="row" style="gap:14px"><?php foreach ($planes as $p): ?><label style="display:flex;gap:6px;align-items:center;cursor:pointer"><input type="checkbox" data-atpl="<?= e($p['id']) ?>"<?= $p['id'] === 'PLUS' ? ' checked' : '' ?>><?= e($p['nombre']) ?></label><?php endforeach ?></div></div>
    </div>
    <div data-at="afi" hidden><p class="at-paso">2 · ¿Qué afiliado?</p>
      <label class="f" style="margin:0">Apellido, DNI o N° de afiliado<input id="at-busca" placeholder="Ej.: Gómez, 30.123.456 o 75" autocomplete="off" data-sugerir="afiliados"></label>
      <div id="at-quien" style="font-size:14px;margin-top:6px"></div>
    </div>
  </div><div style="display:flex;flex-direction:column;gap:12px">
    <p class="at-paso">3 · ¿A qué celular?</p>
    <label class="f" style="margin:0">Celular (con característica, sin 0 ni 15)<input id="at-tel" inputmode="tel" placeholder="Ej.: 3435013651" autocomplete="off"><small id="at-tel-det" class="muted" style="font-size:12px"></small></label>
    <div class="pdf-card"><div class="pdf-ico" aria-hidden="true">PDF</div><div style="flex:1;min-width:0"><b id="pdf-tit">Planes de Integral del Este</b><div class="muted" style="font-size:13px" id="pdf-sub">Cuotas y todos los beneficios de los planes elegidos.</div></div></div>
    <div class="row" style="gap:10px"><button class="btn btn-wa" type="button" data-at-enviar="compartir" style="flex:2 1 220px;padding:12px 16px;font-size:16px">Enviar PDF por WhatsApp</button><button class="btn" type="button" data-at-enviar="bajar" style="flex:1 1 120px">Ver PDF</button></div>
  </div></div>
  <?php if (!es_admin()): ?><p class="muted" style="font-size:12.5px;margin:12px 0 0">Desde acá solo se informa. Autorizaciones, excepciones y descuentos los decide administración.</p><?php endif ?>
</div>

<div class="row"><input id="bq" type="search" placeholder="Buscar un beneficio…" style="flex:1 1 260px" autocomplete="off">
  <select id="bpl" aria-label="Plan"><option value="">Todos los planes</option><?php foreach ($planes as $p): ?><option value="<?= e($p['id']) ?>"><?= e($p['nombre']) ?></option><?php endforeach ?></select></div>
<div class="row"><?php foreach ($planes as $p): ?><button class="btn small btn-wa" type="button" data-wa='<?= e(json_encode(['post' => "/cotizador/plan/{$p['id']}/whatsapp"])) ?>'>Enviar <?= e($p['nombre']) ?> por WhatsApp</button><?php endforeach ?></div>
<div class="tbl-wrap"><table id="tb-benef"><thead><tr><th style="width:18%">Beneficio</th><?php foreach ($planes as $p): ?><th data-col="<?= e($p['id']) ?>"><?= e($p['nombre']) ?></th><?php endforeach ?></tr></thead><tbody>
<?php foreach ($benef as $b): $txt = Fmt::norm($b['rubro'] . ' ' . implode(' ', array_map(fn($x) => implode(' ', $x), $b['planes']))); ?>
  <tr data-txt="<?= e($txt) ?>" data-planes="<?= e(implode(',', array_keys($b['planes']))) ?>"><td style="vertical-align:top"><b><?= e($b['rubro']) ?></b></td>
  <?php foreach ($planes as $p): $x = $b['planes'][$p['id']] ?? null; ?><td data-col="<?= e($p['id']) ?>" style="vertical-align:top" data-txt="<?= e(Fmt::norm($b['rubro'] . ' ' . ($x ? implode(' ', $x) : ''))) ?>"><?php if ($x): ?><?= e($x[0]) ?><div style="margin-top:6px"><?= chip('Carencia: ' . $x[1], stripos($x[1], 'sin carencia') !== false ? 'ok' : 'warn') ?></div><?php else: ?><span class="muted">No incluido</span><?php endif ?></td><?php endforeach ?></tr>
<?php endforeach ?>
  <tr id="sin-benef" hidden><td colspan="<?= count($planes) + 1 ?>" class="muted"></td></tr>
</tbody></table></div>
<div class="panel"><h2>Condiciones</h2><?php foreach (Catalogo::condiciones() as $c): ?><p style="margin:6px 0"><?= e($c) ?></p><?php endforeach ?></div>
<script type="application/json" id="ben-datos"><?= json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
<script src="/assets/beneficios.js?v=83" defer></script>
