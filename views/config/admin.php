<?php
/**
 * CU-39 a CU-52 · Configuración (administradores).
 * @var array $usuarios @var array $prest @var array $prestActivo @var array $unidades @var bool $verBajas @var array $usuariosUnidad
 * @var array $comercios @var array $cambios @var array $historial @var ?array $backup @var ?array $backupFalla
 * @var string $proxB @var string $proxA @var bool $demo @var string $hoy @var array $versiones
 */
use App\Core\Config;
use App\Domain\Beneficios;
use App\Domain\Catalogo;
use App\Domain\Fechas;
use App\Domain\Fmt;
use App\Domain\Mails;
use App\Domain\Permisos;

$u = yo();
$planes = Catalogo::planes();
$pa = Config::get('precios_actualizados', []);
$perms = Config::get('permisos', []);
$mail = Config::get('mail', []);
$car = Config::get('carencias', []);
$ult = Config::get('ultimos_numeros', ['B' => 0, 'A' => 0]);
$sel = fn($opts, $v) => implode('', array_map(fn($k, $t) => '<option value="' . e($k) . '"' . sel($k, $v) . '>' . e($t) . '</option>', array_keys($opts), $opts));
$PER = ['anio' => 'Año', 'semestre' => 'Semestre', 'mes' => 'Mes'];
$usuariosDe = [];
foreach ($usuariosUnidad as $x) {
    $usuariosDe[(int)$x['unidad_id']][] = $x;
}
$comDe = [];
foreach ($comercios as $x) {
    $comDe[$x['comercio_rubro']][] = $x['comercio_nombre'];
}
?>
<?= head('Configuración', 'Sos administrador: podés cambiar todo. Los cambios quedan registrados en el historial.') ?>
<div class="panel row" style="justify-content:space-between"><div><h2>Mi cuenta</h2><p class="sub" style="margin:0">Ingresaste como <b><?= e($u['nombre']) ?></b> · Administrador.</p></div>
  <div class="row"><a class="btn" href="/mi-clave">Cambiar mi clave</a><button class="btn" type="button" data-act="salir">Salir</button></div></div>

<div class="grid2">
<!-- CU-39 Usuarios -->
<div class="panel" style="grid-column:1/-1" id="usuarios"><h2>Usuarios</h2><p class="sub">Quiénes pueden ingresar al sistema.</p>
  <div class="tbl-wrap"><table><thead><tr><th>Usuario</th><th>Rol</th><th>Ve</th><th>Estado</th><th></th></tr></thead><tbody>
  <?php foreach ($usuarios as $x): $ve = $x['rol'] === 'admin' ? 'Todo' : (in_array($x['rol'], ['unidad', 'comercio'], true) ? 'Verificar afiliado' : count(array_intersect($x['secciones'], Permisos::USR_VISTAS)) . ' de ' . count(Permisos::USR_VISTAS)); ?>
    <tr><td><b><?= e($x['nombre']) ?></b><?= $x['id'] === $u['id'] ? ' <span class="muted">(vos)</span>' : '' ?><?= $x['debe_cambiar_clave'] ? '<div class="muted" style="font-size:12px">clave inicial sin cambiar</div>' : '' ?></td>
      <td class="nw"><?= e(Permisos::ROLES[$x['rol']]) ?><?= $x['rol'] === 'comercio' ? '<div class="muted" style="font-size:12px">' . e($x['comercio_nombre']) . '</div>' : ($x['rol'] === 'unidad' ? '<div class="muted" style="font-size:12px">' . e($x['unidad']) . '</div>' : '') ?></td>
      <td class="nw muted"><?= e($ve) ?></td><td><?= $x['activo'] ? chip('Activo', 'ok') : chip('Baja') ?></td>
      <td class="r nw"><button class="btn small" type="button" data-modal="/config/usuarios/<?= $x['id'] ?>">Opciones</button></td></tr>
  <?php endforeach ?></tbody></table></div>
  <form class="row" style="margin-top:14px;align-items:flex-end" method="post" action="/config/usuarios" data-ajax id="fnu">
    <?= csrf_field() ?>
    <label class="f" style="flex:1 1 140px">Nuevo usuario<input name="nombre" id="nu-n" required placeholder="Nombre"></label>
    <label class="f" style="flex:1 1 120px">Clave inicial<input name="clave" id="nu-p" placeholder="Igual al nombre"></label>
    <label class="f" style="flex:1 1 150px">Tipo<select name="tipo" data-nu-tipo><option value="usuario">Usuario interno</option><option value="admin">Administrador</option><option value="unidad">Unidad de negocio</option><option value="comercio">Comercio adherido</option></select></label>
    <label class="f" style="flex:1 1 150px" data-nu="unidad" hidden>Unidad<select name="unidad" id="nu-un"><?php foreach (Catalogo::unidades() as $un): ?><option value="<?= e($un['nombre']) ?>"><?= e($un['nombre_largo']) ?></option><?php endforeach ?></select></label>
    <label class="f" style="flex:1 1 170px" data-nu="comercio" hidden>Comercio<input name="comercio" id="nu-cn" placeholder="Nombre del comercio"></label>
    <label class="f" style="flex:1 1 170px" data-nu="comercio" hidden>Rubro<select name="rubro"><?php foreach (Catalogo::beneficiosPlan() as $b): ?><option><?= e($b['rubro']) ?></option><?php endforeach ?></select></label>
    <button class="btn primary" type="submit">Agregar</button>
    <p class="form-error" style="display:none;width:100%"></p>
  </form></div>

<!-- CU-40 Prestaciones -->
<form class="panel" style="grid-column:1/-1" id="prestaciones" method="post" action="/config/prestaciones/previa" data-ajax data-marcar>
  <?= csrf_field() ?>
  <h2>Prestaciones y descuentos de afiliado</h2>
  <p style="margin:2px 0 6px;font-weight:600;color:<?= !empty($pa['fecha']) ? 'var(--accent)' : 'var(--warn)' ?>"><?= !empty($pa['fecha']) ? 'Última actualización de precios: ' . fd($pa['fecha']) . ' (' . e(Fmt::hace($pa['fecha'], $hoy)) . ')' . (!empty($pa['usuario']) ? ' por ' . e($pa['usuario']) : '') : 'Todavía no se registró ninguna actualización de precios.' ?></p>
  <p class="sub">Prestaciones de las unidades con beneficio para los afiliados. Integral no las factura: el valor de referencia sirve para calcular cuánto ahorra el afiliado. Los descuentos y sesiones sin cargo se cargan por plan, más abajo.</p>
  <div class="tbl-wrap"><table><thead><tr><th>Prestación o beneficio</th><th>Unidad</th><th>Tipo</th><th class="r">Valor de referencia</th><th>Última actualización</th><th>Requiere auditoría</th><th>En uso</th></tr></thead><tbody>
  <?php foreach ($prest as $p): $act = (bool)$prestActivo[$p['cod']]; ?>
    <tr<?= $act ? '' : ' style="opacity:.6"' ?>><td><?= e($p['nombre']) ?></td><td class="nw"><?= e($p['unidad']) ?></td><td class="nw"><?= e(Catalogo::TIPOS[$p['tipo']]) ?></td>
      <td class="r"><input class="monto" inputmode="decimal" name="valor[<?= e($p['cod']) ?>]" value="<?= e(Fmt::num($p['valor_ref'])) ?>" style="width:120px" aria-label="Valor <?= e($p['nombre']) ?>"></td>
      <td class="nw"><?= $p['actualizado_en'] ? fd(substr($p['actualizado_en'], 0, 10)) . '<div class="muted" style="font-size:12px">' . e(Fmt::hace(substr($p['actualizado_en'], 0, 10), $hoy)) . ($p['actualizado_por'] ? ' · ' . e($p['actualizado_por']) : '') . '</div>' : '<span class="muted">Sin registro</span>' ?></td>
      <td><input type="hidden" name="auditoria[<?= e($p['cod']) ?>]" value="0"><input type="checkbox" name="auditoria[<?= e($p['cod']) ?>]" value="1"<?= chk($p['requiere_auditoria']) ?> aria-label="Requiere auditoría"></td>
      <td><input type="hidden" name="activo[<?= e($p['cod']) ?>]" value="0"><input type="checkbox" name="activo[<?= e($p['cod']) ?>]" value="1"<?= chk($act) ?> aria-label="En uso"></td></tr>
  <?php endforeach ?></tbody></table></div>
  <div class="row" style="margin-top:12px;justify-content:space-between"><span class="row" style="gap:8px;align-items:center"><button class="btn" type="button" data-importar>Actualizar desde PDF o Excel</button><input type="file" id="imp-file" accept=".pdf,.xlsx,.xls,.ods,.csv,.txt" hidden><span class="muted" style="font-size:13px">Subís la lista que te mandan y el sistema detecta los precios.</span></span><button class="btn primary" type="submit">Guardar cambios</button></div>
  <p class="form-error" style="display:none"></p>
</form>
<form class="panel" style="grid-column:1/-1;padding-top:0;margin-top:-10px" method="post" action="/config/prestaciones/nueva" data-ajax>
  <?= csrf_field() ?>
  <div class="row" style="align-items:flex-end"><label class="f" style="flex:2 1 200px">Nueva prestación o beneficio<input name="nombre" id="np-n" required placeholder="Ej.: Consulta de cardiología"></label>
  <label class="f" style="flex:1 1 120px">Unidad<select name="unidad"><?php foreach (Catalogo::unidades() as $un): ?><option value="<?= e($un['nombre']) ?>"><?= e($un['nombre']) ?></option><?php endforeach ?></select></label>
  <label class="f" style="flex:1 1 150px">Tipo<select name="tipo"><?= $sel(Catalogo::TIPOS, 'consulta') ?></select></label>
  <button class="btn primary" type="submit">Agregar</button></div><p class="form-error" style="display:none"></p></form>

<!-- CU-42 Reglas -->
<form class="panel" style="grid-column:1/-1" id="reglas" method="post" action="/config/reglas/previa" data-ajax data-marcar>
  <?= csrf_field() ?>
  <h2>Reglas de beneficios por plan</h2><p class="sub">Cargadas del PDF de beneficios. Carencias en días desde el alta. "Hasta" deja elegir el porcentaje sin pasar el máximo. Escala: días:porcentaje separados por coma (por ejemplo 0:10, 90:20).</p>
  <?php foreach ($planes as $pl): ?><h3 style="font-size:15px;margin:16px 0 8px"><?= e($pl['nombre']) ?></h3><div class="tbl-wrap"><table><thead><tr><th>Prestación</th><th class="r">Sin cargo</th><th>Cada</th><th>Por grupo</th><th class="r">Carencia sin cargo</th><th class="r">Desc. %</th><th>Cómo</th><th class="r">Carencia desc.</th><th>Escala</th><th class="r">Máx. con desc./año</th></tr></thead><tbody>
    <?php foreach (Catalogo::prestaciones() as $p): $r = Catalogo::regla($pl['id'], $p['cod']); $k = 'r[' . $pl['id'] . ':' . $p['cod'] . ']'; $n = fn($f, $w = 64, $mx = 730) => '<input type="number" min="0" max="' . $mx . '" name="' . $k . '[' . $f . ']" value="' . e((string)(0 + $r[$f])) . '" style="width:' . $w . 'px;text-align:right">'; ?>
    <tr><td><?= e($p['nombre']) ?><div class="muted" style="font-size:12px"><?= e($p['unidad']) ?></div></td><td class="r"><?= $n('gratis', 58, 99) ?></td><td><select name="<?= $k ?>[per]"><?= $sel($PER, $r['per']) ?></select></td>
      <td><input type="hidden" name="<?= $k ?>[por_grupo]" value="0"><input type="checkbox" name="<?= $k ?>[por_grupo]" value="1"<?= chk($r['por_grupo']) ?> aria-label="Por grupo"></td><td class="r"><?= $n('car_gratis') ?></td><td class="r"><?= $n('desc', 58, 100) ?></td>
      <td><select name="<?= $k ?>[modo]"><?= $sel(['fijo' => 'Fijo', 'hasta' => 'Hasta'], $r['modo']) ?></select></td><td class="r"><?= $n('car') ?></td><td><input name="<?= $k ?>[escala]" value="<?= e($r['escala']) ?>" placeholder="—" style="width:150px"></td><td class="r"><?= $n('max_desc', 58, 99) ?></td></tr>
    <?php endforeach ?></tbody></table></div><?php endforeach ?>
  <p class="form-error" style="display:none"></p>
  <div class="row" style="margin-top:14px;justify-content:flex-end"><button class="btn primary" type="submit">Guardar reglas</button></div>
</form>

<!-- CU-43 Unidades -->
<div class="panel" style="grid-column:1/-1" id="unidades"><h2>Unidades de negocio</h2><p class="sub">Lugares donde el afiliado usa su beneficio. Cada unidad entra con su usuario, verifica al afiliado, ve qué le corresponde y registra el uso.</p>
  <div class="tbl-wrap"><table><thead><tr><th>Unidad</th><th>Usuario</th><th>Prestaciones</th><?php foreach ($planes as $pl): ?><th><?= e(str_replace(' del Este', '', $pl['nombre'])) ?></th><?php endforeach ?></tr></thead><tbody>
  <?php foreach ($unidades as $un): if (!$un['activo'] && !$verBajas) continue; $ps = array_filter(Catalogo::prestaciones(false), fn($p) => (int)$p['unidad_id'] === (int)$un['id']); ?>
    <tr<?= $un['activo'] ? '' : ' style="opacity:.55"' ?>><td><b><?= e($un['nombre_largo']) ?></b><?= $un['activo'] ? '' : ' ' . chip('dada de baja', 'bad') ?><div style="margin-top:6px"><button class="btn small" type="button" data-modal="/config/unidades/<?= (int)$un['id'] ?>">Editar</button></div></td>
      <td><?= e(implode(', ', array_map(fn($x) => $x['nombre'] . ($x['activo'] ? '' : ' (baja)'), $usuariosDe[(int)$un['id']] ?? []))) ?: '<span class="muted">sin usuario</span>' ?></td>
      <td><?= implode('<br>', array_map(fn($p) => e($p['nombre']), $ps)) ?: '<span class="muted">—</span>' ?></td>
      <?php foreach ($planes as $pl): $t = []; foreach ($ps as $p) { $r = Catalogo::regla($pl['id'], $p['cod']); $e2 = Beneficios::escala($r); $t[] = implode(' + ', array_filter([$r['gratis'] ? $r['gratis'] . ' sin cargo' : '', $e2 ? 'hasta ' . Fmt::num(max(array_column($e2, 1))) . '%' : ($r['desc'] ? Fmt::num($r['desc']) . '%' : '')])); } $t = array_unique(array_filter($t)); ?>
      <td><?= $t ? e(implode(' · ', $t)) : '<span class="muted">—</span>' ?></td><?php endforeach ?></tr>
  <?php endforeach ?></tbody></table></div>
  <?php $nb = count(array_filter($unidades, fn($x) => !$x['activo'])); if ($nb): ?><p style="margin:8px 0 0"><a class="linklike" href="/config?bajas=<?= $verBajas ? '0' : '1' ?>#unidades"><?= $verBajas ? 'Ocultar' : 'Ver' ?> unidades dadas de baja (<?= $nb ?>)</a></p><?php endif ?>
  <form method="post" action="/config/unidades" data-ajax style="margin-top:16px;border-top:1px solid var(--line);padding-top:14px;display:flex;flex-direction:column;gap:12px"><b>Nueva unidad de negocio</b>
    <?= csrf_field() ?>
    <div class="form-grid" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr))">
      <label class="f">Nombre de la unidad<input name="nombre" id="un-n" required placeholder="Ej.: Kinesiología"></label>
      <label class="f">Servicio que presta<input name="servicio" id="un-p" required placeholder="Ej.: Sesión de kinesiología"></label>
      <label class="f">Carencia (meses)<input name="carencia" type="number" min="0" max="24" value="0"></label></div>
    <div class="tbl-wrap"><table><thead><tr><th>Plan</th><th class="r">Descuento (%)</th><th class="r">Sin cargo (cantidad, opcional)</th></tr></thead><tbody><?php foreach ($planes as $pl): ?><tr><td><?= e($pl['nombre']) ?></td><td class="r"><input type="number" min="0" max="100" step="5" name="pct[<?= e($pl['id']) ?>]" placeholder="0" style="width:100px"></td><td class="r"><input type="number" min="0" max="99" name="gratis[<?= e($pl['id']) ?>]" placeholder="0" style="width:100px"></td></tr><?php endforeach ?></tbody></table></div>
    <div class="form-grid" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr))"><label class="f">Las sin cargo se renuevan cada<select name="per"><?= $sel(['mes' => 'Mes', 'semestre' => 'Semestre', 'anio' => 'Año'], 'anio') ?></select></label><label class="f">Texto del beneficio (lo ve la unidad)<input name="texto" placeholder="Ej.: 20% de descuento en todas las sesiones"></label></div>
    <p class="form-error" style="display:none"></p>
    <div class="row" style="justify-content:space-between;align-items:center"><span class="muted" style="font-size:13px">Se crea el usuario con el nombre de la unidad y la misma clave (se cambia en el primer ingreso).</span><button class="btn primary" type="submit">Crear unidad</button></div>
  </form></div>

<!-- CU-44 Comercios -->
<form class="panel" style="grid-column:1/-1" id="rubros" method="post" action="/config/rubros/previa" data-ajax data-marcar>
  <?= csrf_field() ?>
  <h2>Beneficios en comercios adheridos</h2><p class="sub">Descuento máximo que puede cargar el comercio y, si hace falta, un límite de usos por afiliado.</p>
  <div class="tbl-wrap"><table><thead><tr><th>Rubro</th><th>Comercios</th><th class="r">Descuento máx. %</th><th class="r">Límite de usos (0 = sin límite)</th><th>Cada</th></tr></thead><tbody>
  <?php foreach (Catalogo::rubros() as $r): ?><tr><td><b><?= e($r['nombre']) ?></b></td><td class="muted" style="font-size:13px"><?= e(implode(', ', $comDe[$r['nombre']] ?? []) ?: '—') ?></td>
    <td class="r"><input type="number" min="0" max="100" name="r[<?= e($r['nombre']) ?>][max]" value="<?= e((string)(0 + $r['max_pct'])) ?>" style="width:70px;text-align:right"></td><td class="r"><input type="number" min="0" max="99" name="r[<?= e($r['nombre']) ?>][lim]" value="<?= (int)$r['limite'] ?>" style="width:70px;text-align:right"></td>
    <td><select name="r[<?= e($r['nombre']) ?>][per]"><?= $sel(['mes' => 'Mes', 'semestre' => 'Semestre', 'anio' => 'Año'], $r['per']) ?></select></td></tr><?php endforeach ?>
  </tbody></table></div><p class="form-error" style="display:none"></p>
  <div class="row" style="margin-top:14px;justify-content:flex-end"><button class="btn primary" type="submit">Guardar</button></div>
</form>

<!-- CU-45 Precios -->
<form class="panel" id="precios" method="post" action="/config/precios/previa" data-ajax data-marcar>
  <?= csrf_field() ?>
  <h2>Precios por plan y categoría</h2><p class="sub">Cuota mensual de cada integrante. Con precio 0 el plan no se puede vender.</p>
  <div class="tbl-wrap"><table><thead><tr><th>Categoría</th><?php foreach ($planes as $pl): ?><th class="r"><?= e(str_replace(' del Este', '', $pl['nombre'])) ?></th><?php endforeach ?></tr></thead><tbody>
  <?php foreach (Catalogo::categorias() as $c): ?><tr><td><?= e($c['nombre']) ?></td><?php foreach ($planes as $pl): ?><td class="r"><input class="monto" inputmode="decimal" name="precio[<?= e($pl['id'] . ':' . $c['id']) ?>]" value="<?= e(Fmt::num(Catalogo::precio($c['id'], $pl['id']))) ?>" style="width:110px" aria-label="Precio <?= e($c['nombre']) ?> <?= e($pl['nombre']) ?>"></td><?php endforeach ?></tr><?php endforeach ?>
  <tr><td><b>Alícuota de IVA</b></td><?php foreach ($planes as $pl): ?><td class="r"><select name="iva[<?= e($pl['id']) ?>]" style="width:120px"><?= $sel(['' => 'A confirmar', '21' => '21%', '10.5' => '10,5%', '0' => 'Exento'], $pl['alicuota'] === null ? '' : rtrim(rtrim(number_format($pl['alicuota'], 2, '.', ''), '0'), '.')) ?></select></td><?php endforeach ?></tr>
  </tbody></table></div>
  <label class="chk" style="margin-top:14px"><input type="checkbox" name="aplicar_actuales" value="1"> Aplicar también a la cuota de los afiliados actuales</label>
  <p class="form-error" style="display:none"></p>
  <div class="row" style="margin-top:14px;justify-content:flex-end"><button class="btn primary" type="submit">Guardar precios</button></div>
</form>

<!-- CU-47 Permisos -->
<form class="panel" id="permisos" method="post" action="/config/permisos/previa" data-ajax data-marcar>
  <?= csrf_field() ?>
  <h2>Qué requiere administrador</h2><p class="sub">Si un usuario intenta una de estas acciones, un administrador tiene que autorizarla con su usuario y clave.</p>
  <div style="display:flex;flex-direction:column;gap:10px"><?php foreach (Permisos::ACCIONES as $k => $t): ?><label class="chk" style="font-size:14.5px"><input type="checkbox" name="perm[<?= $k ?>]" value="1"<?= chk($perms[$k] ?? true) ?>> <?= e($t) ?></label><?php endforeach ?></div>
  <p class="form-error" style="display:none"></p>
  <div class="row" style="margin-top:14px;justify-content:flex-end"><button class="btn primary" type="submit">Guardar permisos</button></div>
</form>

<!-- CU-46 Facturación -->
<div class="panel" style="grid-column:1/-1"><h2>Facturación e IVA</h2><p class="sub">Integral del Este factura solo las cuotas de sus afiliados, como <?= e(Config::empresa()['razon'] ?? '') ?> (IVA Responsable Inscripto).</p>
  <div class="tbl-wrap"><table><thead><tr><th>Qué se factura</th><th>Condición</th><th class="r">Alícuota</th><th>Comprobante</th></tr></thead><tbody>
    <tr><td><b>Cuotas de los planes</b> (prepaga, afiliados voluntarios)</td><td>Gravado</td><td class="r"><b><?= e(Fmt::ivaTxt($planes['PLUS']['alicuota'] ?? null)) ?></b></td><td>Factura B a consumidores finales y exentos · Factura A a Responsables Inscriptos</td></tr></tbody></table></div>
  <p class="muted" style="font-size:12.5px;margin:8px 0 0">Las prestaciones las factura cada unidad con su propio sistema y punto de venta. La alícuota de cada plan se cambia en Precios por plan.</p></div>
<form class="panel" style="grid-column:1/-1" id="pv" method="post" action="/config/pv/previa" data-ajax data-marcar>
  <?= csrf_field() ?>
  <h2>Puntos de venta (ARCA)</h2><p class="sub">Punto de venta de <?= e(Config::empresa()['razon'] ?? '') ?> con el que Integral factura las cuotas. Cargá el número y el último comprobante emitido para que el sistema siga desde ahí.</p>
  <div class="tbl-wrap"><table><thead><tr><th>Unidad</th><th class="r">Punto de venta</th><th class="r">Último N° Factura B</th><th class="r">Último N° Factura A</th><th>Próxima factura B</th><th>Próxima factura A</th></tr></thead><tbody>
    <tr><td>Cuotas de Integral del Este</td><td class="r"><input type="number" min="1" max="99999" name="pv" value="<?= Config::pvCuotas() ?>" style="width:90px"></td><td class="r"><input type="number" min="0" name="ult_b" value="<?= (int)$ult['B'] ?>" style="width:120px"></td><td class="r"><input type="number" min="0" name="ult_a" value="<?= (int)$ult['A'] ?>" style="width:120px"></td><td class="mono nw"><?= e($proxB) ?></td><td class="mono nw"><?= e($proxA) ?></td></tr>
  </tbody></table></div>
  <p class="muted" style="font-size:12.5px;margin:8px 0 0">Tienen que ser puntos de venta del tipo “Web Services” en ARCA. Confirmá los números con el contador.</p>
  <p class="form-error" style="display:none"></p>
  <div class="row" style="margin-top:12px;justify-content:flex-end"><button class="btn primary" type="submit">Guardar puntos de venta</button></div>
</form>
<form class="panel" id="cfg" method="post" action="/config/cfg/previa" data-ajax data-marcar>
  <?= csrf_field() ?>
  <h2>Vencimiento y carencias</h2><p class="sub">Día del mes en que vence la cuota y días de carencia desde el alta.</p>
  <div class="form-grid" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr))">
    <label class="f">Día de vencimiento<input name="dia_vto" type="number" min="1" max="31" value="<?= Config::diaVto() ?>"></label>
    <?php foreach (Catalogo::TIPOS as $k => $t): ?><label class="f">Carencia <?= e(mb_strtolower($t)) ?> (días)<input name="car[<?= $k ?>]" type="number" min="0" max="730" value="<?= (int)($car[$k] ?? 0) ?>"></label><?php endforeach ?>
  </div><p class="form-error" style="display:none"></p>
  <div class="row" style="margin-top:14px;justify-content:flex-end"><button class="btn primary" type="submit">Guardar</button></div>
</form>

<!-- CU-48 Mails -->
<form class="panel" style="grid-column:1/-1" id="mails" method="post" action="/config/mail/previa" data-ajax data-marcar>
  <?= csrf_field() ?>
  <h2>Mails a los afiliados</h2><p class="sub">Se envían desde <?= e(\App\Core\Env::get('MAIL_FROM', 'sistema@integraldeleste.com.ar')) ?> con el PDF adjunto. Podés usar: {nombre}, {periodo}, {importe}, {vencimiento}, {plan}, {numero}. Una variable desconocida queda escrita tal cual.</p>
  <div class="grid2"><?php foreach (Mails::NOMBRES as $k => $t): $p = Mails::plantilla($k); ?><div style="display:flex;flex-direction:column;gap:8px"><b><?= e($t) ?><?= $k === 'resumen' ? ' ({detalle} = lo que usó)' : ($k === 'promo' ? ' ({titulo}, {beneficio}, {lugar}, {texto}, {desde}, {hasta})' : '') ?></b><label class="f">Asunto<input name="<?= $k ?>[asunto]" value="<?= e($p['asunto']) ?>"></label><label class="f">Texto<textarea name="<?= $k ?>[cuerpo]" rows="9"><?= e($p['cuerpo']) ?></textarea></label></div><?php endforeach ?></div>
  <label class="chk" style="margin-top:10px"><input type="checkbox" name="recibo_auto" value="1"<?= chk($mail['recibo_auto'] ?? false) ?>> Mandar el recibo por mail automáticamente al cobrar</label>
  <p class="form-error" style="display:none"></p>
  <div class="row" style="justify-content:flex-end;margin-top:10px"><button class="btn primary" type="submit">Guardar textos</button></div>
</form>

<!-- CU-50 Copias de seguridad -->
<div class="panel"><h2>Copias de seguridad</h2>
  <?php $f = $backup ? substr($backup['fecha'], 0, 10) : null; $d = $f ? Fechas::dias($f, $hoy) : null; ?>
  <div class="verdict <?= $f && $d <= 7 ? 'ok' : 'bad' ?>" style="font-size:14px"><?= $f ? 'Última copia correcta: ' . fd($f) . ' (' . e(Fmt::hace($f, $hoy)) . ')' . ($backup['origen'] === 'descarga' ? ', descargada por ' . e($backup['usuario']) : ', automática') . '.' : 'Todavía no hay registro de ninguna copia.' ?><?= !$f || $d > 7 ? ' Revisá el servicio de copias automáticas (docs/OPERACION.md).' : '' ?></div>
  <?php if ($backupFalla && (!$backup || $backupFalla['fecha'] > $backup['fecha'])): ?><div class="verdict bad" style="font-size:13.5px;margin-top:8px">La última copia automática falló el <?= fd(substr($backupFalla['fecha'], 0, 10)) ?>: <?= e($backupFalla['detalle']) ?></div><?php endif ?>
  <p class="sub" style="margin:10px 0">En el servidor se hace una copia cifrada cada 6 horas y se guarda fuera del VPS. Además podés descargar una copia completa de los datos (tiene datos personales: guardala en un lugar seguro). La restauración se hace desde la consola del servidor, con el procedimiento de docs/OPERACION.md.</p>
  <div class="row" style="gap:10px"><button class="btn primary" type="button" data-descargar="/config/copia">Descargar copia ahora</button></div></div>

<!-- CU-49 Últimos cambios -->
<div class="panel"><h2>Últimos cambios de configuración</h2><p class="sub">Cada cambio guardado se puede deshacer, empezando por el más reciente.</p>
  <?php $primero = true; foreach ($cambios as $c): $cs = json_decode((string)$c['cambios'], true); ?>
    <div class="row" style="justify-content:space-between;align-items:flex-start;border-bottom:1px solid var(--line);padding-bottom:8px"><div style="flex:1 1 260px;font-size:13.5px"><b><?= e(\App\Domain\Configuracion::BLOQUES[$c['seccion']] ?? $c['seccion']) ?>:</b> <?= e(implode('; ', array_map(fn($x) => "{$x['t']}: {$x['antes']} → {$x['despues']}", array_slice($cs, 0, 4))) . (count($cs) > 4 ? ' y ' . (count($cs) - 4) . ' más' : '')) ?><div class="muted" style="font-size:12px"><?= e($c['usuario']) ?> · <?= fd(substr($c['fecha'], 0, 10)) ?> <?= e(substr($c['fecha'], 11, 5)) ?><?= $c['deshecho'] ? ' · deshecho' : '' ?></div></div>
      <?php if ($primero && !$c['deshecho']): ?><button class="btn small" type="button" data-post="/config/deshacer/<?= (int)$c['id'] ?>" data-confirmar="¿Deshacer este cambio? Todo vuelve a como estaba antes (no cambia afiliados, facturas ni cobros).">Deshacer</button><?php endif ?></div>
  <?php $primero = false; endforeach;
  if (!$cambios): ?><p class="muted" style="margin:0">Todavía no hay cambios guardados.</p><?php endif ?></div>

<?php if ($demo): ?>
<!-- CU-51 Padrón (solo demo) -->
<form class="panel" method="post" action="/config/padron" data-ajax data-confirmar="Se borran altas, bajas, facturas y cobros, y se vuelve a los 24 afiliados al día al 31/10/2026. Se conservan usuarios, permisos, prestaciones e historial. ¿Seguro?">
  <?= csrf_field() ?><h2>Padrón</h2><p class="sub">Volver al punto de partida: los 24 afiliados del Excel al día al 31/10/2026, sin facturas ni cobros. Solo en el ambiente de demostración.</p>
  <label class="f">Tu clave, para confirmar<input name="clave" type="password" required autocomplete="current-password"></label><p class="form-error" style="display:none"></p>
  <button class="btn danger" type="submit">Volver a cargar el padrón del Excel</button></form>
<?php endif ?>
</div>

<!-- CU-52 Historial y versiones -->
<div class="panel"><h2>Historial de cambios</h2><p class="sub">Altas, bajas, modificaciones de datos y montos, usuarios, sesiones y configuración. No se puede editar ni borrar.</p>
  <div class="tbl-wrap"><table><thead><tr><th>Fecha</th><th>Acción</th><th>Detalle</th><th>Por</th></tr></thead><tbody>
  <?php foreach ($historial as $l): ?><tr><td class="num nw"><?= fd(substr($l['fecha'], 0, 10)) ?> <?= e(substr($l['fecha'], 11, 5)) ?></td><td class="nw"><?= e($l['accion']) ?></td><td><?= e($l['detalle']) ?></td><td class="nw"><?= e($l['usuario']) ?><?= $l['autorizo'] ? ' <span class="muted">(autorizó ' . e($l['autorizo']) . ')</span>' : '' ?></td></tr><?php endforeach ?>
  </tbody></table></div></div>
<div class="panel"><h2>Versiones del sistema</h2><p class="sub">Cada modificación sube el número de versión.</p>
  <ul class="vlist"><?php foreach ($versiones as $x): ?><li><b>Versión <?= (int)$x['v'] ?></b><span><?= fd($x['fecha']) ?></span><span></span><span style="color:var(--ink)"><?= e($x['t']) ?></span></li><?php endforeach ?></ul></div>
<script type="application/json" id="cfg-datos"><?= json_encode(['prest' => array_values(array_map(fn($p) => ['cod' => $p['cod'], 'nombre' => $p['nombre'], 'unidad' => $p['unidad'], 'valor' => $p['valor_ref'], 'activo' => (bool)$prestActivo[$p['cod']]], $prest)), 'unidades' => array_keys(Catalogo::unidades()), 'tipos' => Catalogo::TIPOS], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
<script src="/assets/config.js?v=83" defer></script>
