<?php
/** CU-33 · Egresos. @var string $m @var array $res @var array $cats @var ?array $pre @var string $hoy */
use App\Domain\Catalogo;
use App\Domain\CatalogoMaqueta;
use App\Domain\Fmt;

$mx = max([1, ...array_values($res['porC'])]);
$p = $pre ?? [];
?>
<?= head('Egresos', 'Los gastos de Integral del Este. Cada egreso se asienta solo en Contabilidad.') ?>
<div class="grid2">
  <form id="feg" class="panel" method="post" action="/egresos" data-ajax style="display:flex;flex-direction:column;gap:10px"><h2>Cargar egreso</h2>
    <?= csrf_field() ?>
    <div class="form-grid" style="grid-template-columns:repeat(auto-fit,minmax(170px,1fr))">
      <label class="f">Fecha<input name="fecha" type="date" value="<?= $hoy ?>" required></label>
      <label class="f">Categoría<select name="categoria" id="categoria"><?php foreach ($cats as $c): ?><option value="<?= e($c['clave']) ?>"<?= sel($c['clave'], $p['categoria'] ?? '') ?>><?= e($c['nombre']) ?></option><?php endforeach ?></select></label>
      <label class="f">Proveedor<input name="proveedor" id="proveedor" required autocomplete="off" data-sugerir="proveedor" placeholder="Ej.: Inmobiliaria, Edenor, Librería" value="<?= e($p['proveedor'] ?? '') ?>"></label>
      <label class="f">CUIT del proveedor<input name="cuit" id="cuit" class="docfmt" inputmode="numeric" placeholder="Opcional" value="<?= e($p['cuit'] ?? '') ?>"></label>
      <label class="f" style="grid-column:1/-1">Detalle<input name="detalle" placeholder="Ej.: alquiler de noviembre, resmas y tóner" value="<?= e($p['detalle'] ?? '') ?>"></label>
      <label class="f">Comprobante<select name="comprobante" id="comprobante"><?php foreach (CatalogoMaqueta::TIPO_COMP as $t): ?><option<?= sel($t, $p['comprobante'] ?? '') ?>><?= e($t) ?></option><?php endforeach ?></select></label>
      <label class="f">N° de comprobante<input name="numero" placeholder="Ej.: 0002-00001234"></label>
      <label class="f">Importe total<input name="importe" id="importe" class="monto" inputmode="decimal" required placeholder="$" value="<?= $p ? e(Fmt::num($p['importe'])) : '' ?>"<?= $p ? ' autofocus' : '' ?>></label>
      <label class="f" id="iva-l" hidden>IVA incluido (crédito fiscal)<span class="row" style="gap:6px;flex-wrap:nowrap"><input name="iva" id="iva" class="monto" inputmode="decimal" placeholder="$" style="flex:1"><button class="btn small" type="button" data-egiva="21">21%</button><button class="btn small" type="button" data-egiva="10.5">10,5%</button></span></label>
      <label class="f">Medio de pago<select name="medio"><?php foreach (Catalogo::MEDIOS as $mm): ?><option<?= sel($mm, $p['medio'] ?? 'Transferencia bancaria') ?>><?= e($mm) ?></option><?php endforeach ?></select></label>
    </div>
    <label class="chk"><input type="checkbox" name="fijo" value="1"<?= $p ? ' checked' : '' ?>> Es un gasto fijo que se repite todos los meses</label>
    <p class="form-error" style="display:none"></p>
    <div class="row" style="justify-content:flex-end"><button class="btn primary" type="submit">Guardar egreso</button></div>
  </form>
  <div class="panel"><form class="row" style="justify-content:space-between;align-items:flex-end" method="get" action="/egresos"><h2 style="margin:0">Resumen de <?= e(Fmt::perLabel($m)) ?></h2><label class="f" style="margin:0">Mes<input type="month" name="m" value="<?= e($m) ?>" data-autosubmit></label></form>
    <div class="tiles" style="margin-top:12px"><div class="tile"><small>Cobrado de cuotas</small><b><?= money($res['ing']) ?></b><span>ingresos del mes</span></div><div class="tile"><small>Egresos</small><b><?= money($res['tot']) ?></b><span><?= count($res['es']) ?> <?= count($res['es']) === 1 ? 'gasto' : 'gastos' ?></span></div><div class="tile"><small>Resultado</small><b style="color:<?= $res['res'] >= 0 ? 'var(--ok)' : 'var(--bad)' ?>"><?= money($res['res']) ?></b><span><?= $res['res'] >= 0 ? 'ganancia' : 'pérdida' ?> del mes</span></div></div>
    <?php if ($res['porC']): ?><div class="bars" style="margin-top:12px"><?php foreach ($res['porC'] as $c => $v): ?><div class="bar" style="grid-template-columns:190px minmax(0,1fr) 100px"><span><?= e($cats[$c]['nombre'] ?? $c) ?></span><div class="track"><div class="fill" style="width:<?= round($v / $mx * 100) ?>%"></div></div><span class="num r"><?= money($v) ?></span></div><?php endforeach ?></div>
    <?php else: ?><p class="muted">Sin egresos cargados en este mes.</p><?php endif ?>
    <?php if ($res['fijosPend']): ?><div style="margin-top:14px;border-top:1px solid var(--line);padding-top:12px"><b>Gastos fijos sin cargar este mes</b><div style="display:flex;flex-direction:column;gap:6px;margin-top:8px">
      <?php foreach ($res['fijosPend'] as $f): ?><div class="row" style="justify-content:space-between"><span><?= e($f['proveedor']) ?> · <span class="muted"><?= e($cats[$f['categoria']]['nombre'] ?? '') ?> · último <?= money($f['importe']) ?></span></span><a class="btn small" href="/egresos?m=<?= e($m) ?>&fijo=<?= (int)$f['id'] ?>">Cargar</a></div><?php endforeach ?></div></div><?php endif ?>
  </div>
</div>
<div class="panel"><h2>Egresos de <?= e(Fmt::perLabel($m)) ?></h2><div class="tbl-wrap"><table><thead><tr><th>Fecha</th><th>Categoría</th><th>Proveedor</th><th>Detalle</th><th>Comprobante</th><th>Medio</th><th class="r">Importe</th><th>Cargó</th><th></th></tr></thead><tbody>
<?php foreach ($res['es'] as $e): ?>
  <tr><td class="num nw"><?= fd($e['fecha']) ?></td><td><?= e($cats[$e['categoria']]['nombre'] ?? '') ?><?= $e['fijo'] ? ' ' . chip('fijo') : '' ?></td><td><b><?= e($e['proveedor']) ?></b><?= $e['cuit'] ? '<div class="muted" style="font-size:12px">' . e($e['cuit']) . '</div>' : '' ?></td><td><?= e($e['detalle']) ?></td>
    <td class="nw"><?= e($e['comprobante']) ?><?= $e['numero'] ? ' <span class="mono" style="font-size:12px">' . e($e['numero']) . '</span>' : '' ?><?= (float)$e['iva'] ? '<div class="muted" style="font-size:12px">IVA ' . money($e['iva']) . '</div>' : '' ?></td><td><?= e($e['medio']) ?></td><td class="r num"><b><?= money($e['importe']) ?></b></td><td><?= e($e['usuario']) ?></td>
    <td><button class="btn small danger" type="button" data-post="/egresos/<?= (int)$e['id'] ?>/borrar" data-confirmar="¿Borrar este egreso? Queda registrado en el historial.">Borrar</button></td></tr>
<?php endforeach;
if (!$res['es']): ?><tr><td colspan="9" class="muted">No hay egresos en este mes.</td></tr><?php endif ?>
</tbody></table></div></div>
<script src="/assets/egresos.js?v=83"></script>
