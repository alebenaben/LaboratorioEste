<?php
/** CU-24 · Usos en unidades. @var array $ls @var string $u @var string $m */
use App\Domain\Beneficios;
use App\Domain\Catalogo;

$cant = array_sum(array_map(fn($l) => (int)$l['cantidad'], $ls));
$sc = array_sum(array_map(fn($l) => (int)$l['gratis'], $ls));
$cd = array_sum(array_map(fn($l) => (float)$l['pct'] ? (int)$l['cantidad'] - (int)$l['gratis'] : 0, $ls));
$af = count(array_unique(array_map(fn($l) => $l['afiliado_id'], $ls)));
$ah = array_sum(array_map(fn($l) => (float)$l['ahorro_ef'], $ls));
?>
<?= head('Usos en unidades', 'Lo que registran la Biobárica, la Óptica, el Laboratorio y el CMDE cuando atienden a un afiliado. Integral no factura prestaciones: solo lleva la cuenta de los beneficios.') ?>
<form class="row" style="gap:12px;align-items:flex-end" method="get" action="/prestaciones">
  <label class="f" style="margin:0">Unidad<select name="u" data-autosubmit><option value="">Todas</option><?php foreach (Catalogo::unidades(true) as $x): ?><option<?= sel($x['nombre'], $u) ?>><?= e($x['nombre']) ?></option><?php endforeach ?></select></label>
  <label class="f" style="margin:0">Mes<input type="month" name="m" value="<?= e($m) ?>" data-autosubmit></label>
</form>
<div class="kpis" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px">
<?php foreach ([['Usos registrados', $cant], ['Sin cargo', $sc], ['Con descuento', $cd], ['Afiliados distintos', $af], ['Ahorro informado', money($ah)]] as [$t, $v]): ?><div class="panel" style="padding:14px"><div class="muted" style="font-size:13px"><?= $t ?></div><b style="font-size:22px"><?= $v ?></b></div><?php endforeach ?>
</div>
<div class="panel"><div class="tbl-wrap"><table><thead><tr><th>Fecha</th><th>Unidad</th><th>Afiliado</th><th>Prestación</th><th>Beneficio</th><th class="r">Precio informado</th><th class="r">Ahorro</th><th>Registró</th><th></th></tr></thead><tbody>
<?php foreach ($ls as $l): $g = (int)$l['gratis']; $c = (int)$l['cantidad']; $p = (float)$l['pct']; ?>
  <tr><td class="num nw"><?= fd($l['fecha']) ?> <span class="muted"><?= e(substr((string)$l['hora'], 0, 5)) ?></span></td><td><?= e($l['unidad']) ?></td><td><b><?= e($l['afiliado']) ?></b> <span class="muted">N° <?= (int)$l['nro'] ?></span></td><td><?= e($l['nombre']) ?><?= $c > 1 ? " × $c" : '' ?></td>
    <td class="nw"><?= $g === $c ? chip('Sin cargo', 'ok') : ($g ? chip("$g sin cargo", 'ok') . ' ' . chip(Beneficios::pct($p)) : ($p ? chip(Beneficios::pct($p)) : '<span class="muted">Sin beneficio</span>')) ?><?= $l['pendiente_unidad'] ? '<div class="muted" style="font-size:12px">falta que la unidad lo registre</div>' : '' ?></td>
    <td class="r num"><?= $l['precio_informado'] ? money($l['lista']) : '—' ?></td><td class="r num"><?= (float)$l['ahorro_ef'] ? money($l['ahorro_ef']) : '—' ?></td><td><?= e($l['usuario']) ?></td>
    <td><button class="btn small" type="button" data-modal="/afiliados/<?= (int)$l['afiliado_id'] ?>/historial">Historial</button></td></tr>
<?php endforeach;
if (!$ls): ?><tr><td colspan="9" class="muted">No hay usos registrados en este mes.</td></tr><?php endif ?>
</tbody></table></div></div>
