<?php
/**
 * CU-07 / CU-08 · Ficha del afiliado (panel lateral).
 * @var array $a @var array $g @var array $tit @var array $miembros @var array $cuenta @var float $cuotaGrupo
 * @var bool $editando @var array $consumos @var array $saldo @var string $hoy
 */
use App\Core\Config;
use App\Domain\Catalogo;
use App\Domain\Fechas;
use App\Domain\Fmt;

$esTit = $tit && $tit['id'] === $a['id'];
$admin = es_admin();
$fz = ['cond' => $g['fiscal_cond'], 'cuit' => $g['fiscal_cuit'], 'razon' => $g['fiscal_razon']];
$letra = $fz['cond'] === 'RI' ? 'A' : 'B';
$lock = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" width="14" height="14" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>';
$montoBloq = !$admin && Config::requiereAdmin('montos');
$edad = Fechas::edad($a['nac'], $hoy);
?>
<div class="drawer" role="dialog" aria-modal="true" aria-label="Ficha de <?= e($a['nombre']) ?>">
  <div class="row" style="justify-content:space-between;align-items:flex-start"><div><span class="mono">Afiliado N° <?= $a['nro'] ?></span><h2><?= e($a['nombre']) ?></h2></div><button class="btn small" type="button" data-act="panel-no">Cerrar</button></div>
  <div class="row"><?= $a['activo'] ? chip_cuenta($cuenta['estado']) : chip('Baja') ?></div>

<?php if ($editando): ?>
  <form class="panel" method="post" action="/afiliados/<?= $a['id'] ?>" data-ajax style="display:flex;flex-direction:column;gap:12px" id="fedit">
    <?= csrf_field() ?>
    <div class="form-grid" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr))">
      <label class="f">Apellido<input name="apellido" class="mayus" required value="<?= e($a['apellido']) ?>" data-sugerir="apellido"></label>
      <label class="f">Primer nombre<input name="nombre1" class="mayus" required value="<?= e($a['nombre1']) ?>" data-sugerir="nombre"></label>
      <label class="f">Segundo nombre<input name="nombre2" class="mayus" value="<?= e($a['nombre2']) ?>" data-sugerir="nombre"></label>
      <label class="f">DNI o CUIT<input name="doc" class="docfmt" inputmode="numeric" required value="<?= e($a['doc']) ?>"></label>
      <label class="f">Fecha de nacimiento<input name="nac" type="date" max="<?= $hoy ?>" value="<?= e($a['nac']) ?>"></label>
      <label class="f">Categoría<select name="categoria" data-precio-cat><?php foreach (Catalogo::categorias() as $c): $pr = Catalogo::precio($c['id'], $g['plan_id']); ?><option value="<?= e($c['id']) ?>"<?= sel($c['id'], $a['categoria_id']) ?>><?= e($c['nombre']) ?> · <?= $pr ? money($pr) : 'precio a definir' ?></option><?php endforeach ?></select></label>
      <label class="f"><span>Cuota mensual<?= $montoBloq ? ' <span class="lockhint">' . $lock . ' requiere administrador</span>' : '' ?></span><input name="cuota" class="monto" inputmode="decimal" value="<?= e(Fmt::num($a['cuota'])) ?>"></label>
      <label class="f">Teléfono<input name="telefono" inputmode="tel" value="<?= e($a['telefono']) ?>"></label>
      <label class="f">Fecha de alta<input name="fecha_alta" type="date" value="<?= e($a['fecha_alta']) ?>"></label>
      <?php if ($esTit): ?>
      <label class="f">Plan del grupo<select name="plan" data-precio-plan><?php foreach (Catalogo::planes() as $p): ?><option value="<?= e($p['id']) ?>"<?= sel($p['id'], $g['plan_id']) ?>><?= e($p['nombre']) ?></option><?php endforeach ?></select></label>
      <label class="f">Forma de pago del grupo<select name="forma_pago"><?php foreach (Catalogo::MEDIOS as $m): ?><option<?= sel($m, $g['forma_pago']) ?>><?= e($m) ?></option><?php endforeach ?></select></label>
      <label class="f">Email del grupo<input name="email" type="email" value="<?= e($g['email']) ?>"></label>
      <label class="f">Factura a nombre de (condición IVA)<select name="fcond"><?php foreach (Catalogo::COND_IVA as $k => $t): ?><option value="<?= $k ?>"<?= sel($k, $fz['cond']) ?>><?= e($t) ?></option><?php endforeach ?></select></label>
      <label class="f">CUIT para facturar<input name="fcuit" class="docfmt" value="<?= e($fz['cuit']) ?>" placeholder="Solo si no es consumidor final"></label>
      <label class="f">Razón social<input name="frazon" class="mayus" value="<?= e($fz['razon']) ?>" placeholder="Solo si no es consumidor final"></label>
      <?php endif ?>
    </div>
    <label class="f">Observaciones<input name="obs" value="<?= e($a['obs']) ?>"></label>
    <p class="form-error" style="display:none"></p>
    <div class="row" style="justify-content:flex-end"><button class="btn" type="button" data-panel="/afiliados/<?= $a['id'] ?>/ficha" data-esc="reset">Cancelar <kbd>Esc</kbd></button><button class="btn primary" type="submit">Guardar cambios</button></div>
    <script type="application/json" data-precios><?= json_encode(Catalogo::precios(), JSON_HEX_TAG) ?></script>
  </form>
<?php else: ?>
  <div class="panel"><dl class="kv">
    <dt>CUIT/DNI</dt><dd class="num"><?= e($a['doc']) ?></dd>
    <dt>Plan</dt><dd><?= e(Catalogo::plan($g['plan_id'])['nombre']) ?></dd>
    <dt>Categoría</dt><dd><?= e(Catalogo::categoria($a['categoria_id'])['nombre']) ?></dd>
    <dt>Cuota mensual</dt><dd class="num"><?= money($a['cuota']) ?></dd>
    <dt>Nacimiento</dt><dd><?= $a['nac'] ? fd($a['nac']) . " · $edad años" . (Fechas::esCumple($a['nac'], $hoy) ? ' 🎂 <b>¡Hoy cumple años!</b>' : '') : '<span class="muted">Sin cargar</span>' ?></dd>
    <dt>Fecha de alta</dt><dd><?= fd($a['fecha_alta']) ?></dd>
    <dt>Teléfono</dt><dd><?= $a['telefono'] !== '' ? e($a['telefono']) : '<span class="muted">Sin cargar</span>' ?></dd>
    <dt>Paga</dt><dd><?= $esTit ? 'Titular del grupo' : e($tit['nombre']) ?> · <?= e($g['forma_pago']) ?></dd>
    <dt>Factura</dt><dd><?= $letra ?> · <?= e(Catalogo::COND_IVA[$fz['cond']]) ?><?= $fz['cuit'] ? ' · CUIT ' . e($fz['cuit']) : '' ?><?= $fz['razon'] ? ' · ' . e($fz['razon']) : '' ?></dd>
    <?php if ($g['email'] && $esTit): ?><dt>Email</dt><dd><?= e($g['email']) ?></dd><?php endif ?>
    <?php if ($a['obs'] !== ''): ?><dt>Observaciones</dt><dd><?= e($a['obs']) ?></dd><?php endif ?>
    <?php if (!$a['activo']): ?><dt>Baja</dt><dd><?= fd($a['fecha_baja']) ?></dd><?php endif ?>
  </dl><div class="row" style="margin-top:14px"><button class="btn small" type="button" data-panel="/afiliados/<?= $a['id'] ?>/ficha?editar=1">Editar datos</button></div></div>
<?php endif ?>

<?php if ($consumos): ?>
  <div class="panel"><h2>Consumos en comercios adheridos</h2><div class="tbl-wrap"><table><tbody>
  <?php foreach ($consumos as $x): ?><tr><td class="num nw"><?= fd($x['fecha']) ?></td><td><?= e($x['comercio']) ?><div class="muted" style="font-size:12px"><?= e($x['detalle']) ?></div></td><td class="r num"><?= (float)$x['importe'] ? money($x['total']) : '' ?><?php if ((float)$x['ahorro']): ?><div class="muted" style="font-size:12px">ahorró <?= money($x['ahorro']) ?></div><?php endif ?></td></tr><?php endforeach ?>
  </tbody></table></div></div>
<?php endif ?>

  <div class="panel"><h2>Beneficios usados</h2><p class="sub">Año <?= substr($hoy, 0, 4) ?>, según el <?= e(Catalogo::plan($g['plan_id'])['nombre']) ?>.</p>
    <?= \App\Core\View::parcial('parciales/beneficios_tabla', ['a' => $a, 'saldo' => $saldo, 'compacto' => false, 'hoy' => $hoy]) ?></div>

  <div class="panel"><h2>Grupo de pago</h2>
    <p class="sub">Cuota mensual del grupo <?= money($cuotaGrupo) ?> · saldo <?= money($cuenta['saldo']) ?><?php if ($g['promo']): ?> · promoción <?= $g['promo']['pct'] ?>% de <?= e(Fmt::perLabel($g['promo']['desde'])) ?> a <?= e(Fmt::perLabel($g['promo']['hasta'])) ?><?= $g['promo']['hasta'] < substr($hoy, 0, 7) ? ' (terminada)' : '' ?><?php endif ?><?= $g['email'] ? ' · email ' . e($g['email']) : ' · <span style="color:var(--warn)">sin email</span>' ?></p>
    <?php if ($admin): ?><div class="row" style="margin-bottom:8px"><button class="btn small" type="button" data-modal="/grupos/<?= $g['id'] ?>/promo?a=<?= $a['id'] ?>"><?= $g['promo'] ? 'Renovar, extender o quitar promoción' : 'Agregar promoción' ?></button></div><?php endif ?>
    <div class="tbl-wrap"><table><tbody>
    <?php foreach ($miembros as $m): ?>
      <tr<?= $m['id'] !== $a['id'] ? ' data-panel="/afiliados/' . $m['id'] . '/ficha" tabindex="0"' : '' ?>><td class="mono"><?= $m['nro'] ?></td><td><?= $m['id'] === $a['id'] ? '<b>' . e($m['nombre']) . '</b>' : e($m['nombre']) ?><?= !$m['activo'] ? ' ' . chip('Baja') : '' ?></td><td><?= e(Catalogo::categoria($m['categoria_id'])['nombre']) ?></td><td class="r num"><?= money($m['activo'] ? $m['cuota'] : 0) ?></td></tr>
    <?php endforeach ?>
    </tbody></table></div></div>
  <div class="row">
    <a class="btn" href="/cuentas?g=<?= $g['id'] ?>">Ver cuenta corriente</a>
    <?php if ($admin): ?><?= $a['activo'] ? '<button class="btn danger" type="button" data-modal="/afiliados/' . $a['id'] . '/baja">Dar de baja</button>' : '<button class="btn" type="button" data-post="/afiliados/' . $a['id'] . '/reactivar">Reactivar</button>' ?><?php endif ?>
  </div>
</div>
