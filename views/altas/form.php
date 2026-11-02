<?php
/** CU-06 · Nueva alta. @var string $plan @var string $titCat @var array $fam @var ?array $promo @var string $hoy */
use App\Domain\Catalogo;

$planes = Catalogo::planes();
$datos = [
    'precios' => Catalogo::precios(),
    'planes' => array_map(fn($p) => ['nombre' => $p['nombre'], 'individual' => $p['individual']], $planes),
    'cats' => array_map(fn($c) => $c['nombre'], Catalogo::categorias()),
    'catTit' => Catalogo::catTitular(), 'catAdic' => Catalogo::catAdicional(),
    'fam' => $fam, 'promo' => $promo, 'hoy' => $hoy,
];
?>
<?= head('Nueva alta', 'Cargá al titular (quien paga) y, si corresponde, a su grupo familiar. Todos quedan con la misma forma de pago.', '<span id="promo-badge"></span><a class="btn" href="' . (es_admin() ? '/afiliados' : '/inicio') . '" data-esc="' . (es_admin() ? '/afiliados' : '/inicio') . '" data-cancelar-alta>Cancelar <kbd>Esc</kbd></a>') ?>
<form id="falta" class="panel" method="post" action="/altas" data-ajax style="display:flex;flex-direction:column;gap:18px">
  <?= csrf_field() ?>
  <input type="hidden" name="promo_pct" id="promo_pct" value="<?= (int)($promo['pct'] ?? 0) ?>"><input type="hidden" name="promo_meses" id="promo_meses" value="<?= (int)($promo['meses'] ?? 0) ?>">
  <div><h2>Titular</h2></div>
  <div class="form-grid">
    <label class="f">Apellido<input id="t_apellido" name="t_apellido" class="mayus" required autocomplete="off" data-sugerir="apellido" autofocus></label>
    <label class="f">Primer nombre<input id="t_nombre1" name="t_nombre1" class="mayus" required autocomplete="off" data-sugerir="nombre"></label>
    <label class="f">Segundo nombre (opcional)<input id="t_nombre2" name="t_nombre2" class="mayus" autocomplete="off" data-sugerir="nombre"></label>
    <label class="f">DNI o CUIT<input id="t_doc" name="t_doc" class="docfmt" inputmode="numeric" required autocomplete="off"><small id="t_doc_aviso" style="color:var(--bad)"></small></label>
    <label class="f">Fecha de nacimiento<input id="t_nac" name="t_nac" type="date" required max="<?= $hoy ?>"></label>
    <label class="f">Plan<select id="plan" name="plan"><?php foreach ($planes as $p): ?><option value="<?= e($p['id']) ?>"<?= sel($p['id'], $plan) ?>><?= e($p['nombre']) ?></option><?php endforeach ?></select></label>
    <label class="f">Categoría<select id="t_categoria" name="t_categoria" data-cats="tit" data-valor="<?= e($titCat) ?>"></select></label>
    <label class="f">Teléfono<input name="telefono" inputmode="tel"></label>
    <label class="f">Email (para enviarle facturas y recibos)<input id="email" name="email" type="email" required placeholder="nombre@correo.com"><span style="display:flex;gap:6px;align-items:center;font-size:12.5px;color:var(--muted);margin-top:4px"><input type="checkbox" id="sin_email" name="sin_email" value="1"> No tiene email</span></label>
    <label class="f">Forma de pago<select name="forma_pago"><?php foreach (Catalogo::MEDIOS as $m): ?><option><?= e($m) ?></option><?php endforeach ?></select></label>
    <label class="f">Fecha de alta<input id="fecha_alta" name="fecha_alta" type="date" required value="<?= $hoy ?>"></label>
    <label class="f">Observaciones<input name="obs"></label>
  </div>
  <div class="row" style="justify-content:space-between;border-top:1px solid var(--line);padding-top:14px"><div><h2>Promoción</h2><p class="sub" style="margin:0" id="promo-txt">Sin promoción.</p></div><button class="btn btn-promo" type="button" id="btn-promo">Promoción</button></div>
  <div class="row" style="justify-content:space-between"><h2>Grupo familiar</h2><span id="fam-acc"></span></div>
  <div id="fams" style="display:flex;flex-direction:column;gap:10px"></div>
  <p class="form-error" style="display:none"></p>
  <div class="row" style="justify-content:space-between;border-top:1px solid var(--line);padding-top:14px">
    <div>Cuota mensual del grupo: <b class="num" id="cuota-est" style="font-size:18px"></b></div>
    <button class="btn primary" type="submit">Confirmar alta</button>
  </div>
  <script type="application/json" id="alta-datos"><?= json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
</form>
<script src="/assets/altas.js?v=83" defer></script>
