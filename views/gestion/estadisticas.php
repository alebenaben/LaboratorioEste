<?php
/**
 * CU-35 · Estadísticas (con CU-13, CU-36 y CU-37).
 * @var string $hoy @var string $resM @var array $resumen @var array $ret @var array $aho @var array $ind
 * @var array $cons @var array $com @var array $ben @var string $k
 */
use App\Domain\Catalogo;
use App\Domain\Documentos;
use App\Domain\Fmt;
use App\Domain\Retencion;

$PER = ['mes' => 'Este mes', 'trim' => '3 meses', 'anio' => 'Este año'];
$seg = fn($k) => '<div class="seg" role="group" aria-label="Período">' . implode('', array_map(fn($x, $t) => '<a href="/estadisticas?aho=' . $x . '&resm=' . e($resM) . '#ahorro" class="' . ($k === $x ? 'on' : '') . '" style="text-decoration:none;color:inherit;padding:6px 12px;display:inline-block">' . $t . '</a>', array_keys($PER), $PER)) . '</div>';
$env = fn($x) => $x['envio'];
$conMail = array_filter($resumen, fn($x) => $x['g']['email'] && !$x['envio']);
$pendR = array_filter($resumen, fn($x) => !$x['envio']);
?>
<?= head('Estadísticas', 'Indicadores calculados sobre el padrón, las cobranzas y el uso de los beneficios.') ?>

<div class="panel" id="resumen"><form class="row" style="justify-content:space-between;align-items:flex-end" method="get" action="/estadisticas"><div><h2>Resumen mensual para los afiliados</h2><p class="sub" style="margin:0">Al terminar cada mes, a cada afiliado que usó algún beneficio se le manda el detalle de lo que usó y cuánto ahorró.</p></div><input type="hidden" name="aho" value="<?= e($k) ?>"><label class="f" style="margin:0">Mes<input type="month" name="resm" value="<?= e($resM) ?>" max="<?= substr($hoy, 0, 7) ?>" data-autosubmit></label></form>
  <p style="margin:12px 0"><?= count($resumen) ?> <?= count($resumen) === 1 ? 'grupo usó' : 'grupos usaron' ?> beneficios en <?= e(Fmt::perLabel($resM)) ?> · <?= count($resumen) - count($pendR) ?> ya <?= count($resumen) - count($pendR) === 1 ? 'recibió' : 'recibieron' ?> el resumen.</p>
  <div class="row" style="gap:8px;margin-bottom:10px"><button class="btn primary" type="button" data-post="/estadisticas/resumen/mail" data-datos='<?= e(json_encode(['per' => $resM])) ?>' data-confirmar="Se van a enviar <?= count($conMail) ?> mails con el resumen en PDF. ¿Confirmás?"<?= $conMail ? '' : ' disabled' ?>>Enviar por mail a <?= count($conMail) ?> <?= count($conMail) === 1 ? 'afiliado' : 'afiliados' ?></button><span class="muted" style="font-size:13px">A los que no tienen email, mandáselo por WhatsApp desde la lista.</span></div>
  <div class="tbl-wrap"><table><thead><tr><th>Titular</th><th>Qué usaron</th><th class="r">Ahorro</th><th>Envío</th><th></th></tr></thead><tbody>
  <?php foreach ($resumen as $x): ?><tr><td><b><?= e($x['t']['nombre']) ?></b> <span class="muted">N° <?= $x['t']['nro'] ?></span><?= $x['g']['email'] ? '' : '<div class="muted" style="font-size:12px">sin email</div>' ?></td><td style="font-size:13px"><?= e(implode(', ', array_map(fn($z) => $z['nombre'] . ' ×' . $z['veces'], $x['r']['det']))) ?></td><td class="r num"><?= $x['r']['total'] ? money($x['r']['total']) : '—' ?></td>
    <td><?= $x['envio'] ? chip($x['envio']['canal'] . ' ' . fd(substr($x['envio']['fecha'], 0, 10)), 'ok') : chip('Pendiente', 'warn') ?></td><td><button class="btn small btn-wa" type="button" data-wa='<?= e(json_encode(['post' => '/estadisticas/resumen/whatsapp', 'datos' => ['g' => $x['g']['id'], 'per' => $resM]])) ?>'>WhatsApp</button></td></tr><?php endforeach;
  if (!$resumen): ?><tr><td colspan="5" class="muted">Nadie usó beneficios en <?= e(Fmt::perLabel($resM)) ?>.</td></tr><?php endif ?>
  </tbody></table></div></div>

<div class="panel" id="retencion"><h2>Retención de afiliados</h2><p class="sub">Pedidos de baja y cuántos se quedaron gracias a las propuestas.</p>
  <div class="tiles"><div class="tile"><small>Pedidos de baja</small><b><?= $ret['total'] ?></b><span>registrados</span></div><div class="tile"><small>Se quedaron</small><b style="color:var(--ok)"><?= $ret['retenidos'] ?></b><span><?= $ret['total'] ? round($ret['retenidos'] / $ret['total'] * 100) . '% de los pedidos' : '—' ?></span></div><div class="tile"><small>Se fueron</small><b><?= $ret['bajas'] ?></b><span>bajas confirmadas</span></div><div class="tile"><small>Esperando respuesta</small><b style="color:<?= $ret['pend'] ? 'var(--logo-red)' : 'inherit' ?>"><?= count($ret['pend']) ?></b><span>para administración o para llamar</span></div></div>
  <?php if ($ret['pend']): ?><h3 style="margin:16px 0 8px;font-size:15px">Para resolver</h3><div class="tbl-wrap"><table><tbody>
  <?php foreach ($ret['pend'] as $r): ?><tr><td><b><?= e($r['nombre']) ?></b> <span class="muted">N° <?= (int)$r['nro'] ?></span><div class="muted" style="font-size:12.5px"><?= e(Retencion::motivoTexto($r['motivo'])) ?><?= $r['comentario'] ? ' · “' . e($r['comentario']) . '”' : '' ?> · atendió <?= e($r['usuario']) ?> el <?= fd($r['fecha']) ?></div></td>
    <td><?= $r['resultado'] === 'derivado' ? chip('Pide: ' . ($r['aceptada'] ?: 'descuento'), 'warn') : ($r['resultado'] === 'baja_pedida' ? chip('Insiste en la baja', 'bad') : chip('Lo piensa · llamar el ' . fd($r['llamar']))) ?></td>
    <td class="r" style="white-space:nowrap"><a class="btn small" href="/retencion?a=<?= (int)$r['afiliado_id'] ?>&m=<?= e($r['motivo']) ?>">Atender</a><?php if ($r['resultado'] === 'baja_pedida'): ?> <button class="btn small danger" type="button" data-post="/retencion/<?= (int)$r['id'] ?>/confirmar-baja" data-confirmar="¿Confirmás la baja de <?= e($r['nombre']) ?>?">Confirmar baja</button><?php endif ?></td></tr><?php endforeach ?>
  </tbody></table></div><?php endif ?>
  <?php if ($ret['total']): ?><div class="grid2" style="margin-top:12px"><div><b>Motivos</b><?php foreach ($ret['por'] as $m => $v): ?><div class="row" style="justify-content:space-between;font-size:14px;border-bottom:1px solid var(--line);padding:4px 0"><span><?= e(Retencion::motivoTexto($m)) ?></span><b><?= $v ?></b></div><?php endforeach ?></div>
    <div><b>Qué los convenció</b><?php foreach ($ret['con'] as $m => $v): ?><div class="row" style="justify-content:space-between;font-size:14px;border-bottom:1px solid var(--line);padding:4px 0"><span><?= e($m) ?></span><b><?= $v ?></b></div><?php endforeach ?><?= $ret['con'] ? '' : '<p class="muted">Todavía nadie.</p>' ?></div></div><?php endif ?>
</div>

<div class="panel" id="ahorro"><div class="row" style="justify-content:space-between;align-items:flex-end"><div><h2>Ahorro de los afiliados</h2><p class="sub" style="margin:0">Cuánto ahorraron por usar sus beneficios. Sirve para mostrarles lo que ganan por estar asociados.</p></div><?= $seg($k) ?></div>
  <div class="tiles" style="margin-top:12px"><div class="tile"><small>Ahorro total</small><b style="color:var(--ok)"><?= money($aho['tot']) ?></b><span><?= e(Documentos::PER_AHO[$k]) ?></span></div><div class="tile"><small>Grupos que ahorraron</small><b><?= $aho['grupos'] ?></b><span>promedio <?= money($aho['grupos'] ? $aho['tot'] / $aho['grupos'] : 0) ?></span></div><div class="tile"><small>Ahorro por cada $ 1 de cuota</small><b><?= $aho['cuo'] ? '$ ' . number_format($aho['tot'] / $aho['cuo'], 2, ',', '.') : '—' ?></b><span>cobrado <?= money($aho['cuo']) ?></span></div></div>
  <?php if ($aho['sinValor']): ?><p style="color:var(--warn);font-size:13.5px;margin:10px 0 0"><?= $aho['sinValor'] ?> uso(s) sin precio informado ni valor de referencia no suman ahorro: cargá el valor en Configuración → Prestaciones.</p><?php endif ?>
  <?php if ($aho['porU']): $mx = max($aho['porU']) ?: 1; ?><div class="bars" style="margin-top:12px"><?php foreach ($aho['porU'] as $n => $v): ?><div class="bar" style="grid-template-columns:190px minmax(0,1fr) 100px"><span><?= e($n) ?></span><div class="track"><div class="fill" style="width:<?= round($v / $mx * 100) ?>%"></div></div><span class="num r"><?= money($v) ?></span></div><?php endforeach ?></div><?php endif ?>
  <div class="tbl-wrap" style="margin-top:12px"><table><thead><tr><th>Grupo</th><th>Beneficios</th><th class="r">Ahorro</th><th class="r">Cuotas pagadas</th><th></th></tr></thead><tbody>
  <?php foreach ($aho['filas'] as $x): $t = \App\Domain\Padron::titular($x['g']['id']); ?><tr><td><b><?= e($t['nombre']) ?></b> <span class="muted">N° <?= $t['nro'] ?></span></td><td style="font-size:13px"><?= e(implode(', ', array_map(fn($z) => $z['nombre'] . ' ×' . $z['veces'], $x['ah']['det']))) ?></td><td class="r num"><b><?= money($x['ah']['total']) ?></b></td><td class="r num"><?= money($x['cp']) ?></td><td><button class="btn small btn-wa" type="button" data-wa='<?= e(json_encode(['post' => '/estadisticas/ahorro/whatsapp', 'datos' => ['g' => $x['g']['id'], 'k' => $k]])) ?>'>Enviar resumen</button></td></tr><?php endforeach;
  if (!$aho['filas']): ?><tr><td colspan="5" class="muted">Todavía nadie usó beneficios <?= e(Documentos::PER_AHO[$k]) ?>.</td></tr><?php endif ?>
  </tbody></table></div></div>

<div class="tiles">
  <div class="tile"><small>Afiliados activos</small><b><?= $ind['activos'] ?></b><span>en <?= $ind['grupos'] ?> grupos de pago</span></div>
  <div class="tile"><small>Cuotas por mes</small><b><?= money($ind['cuotas']) ?></b><span>con el padrón actual</span></div>
  <div class="tile"><small>Facturado <?= $ind['ult'] ? e(Fmt::perLabel($ind['ult'])) : '' ?></small><b><?= money($ind['facUlt']['t']) ?></b><span><?= (int)$ind['facUlt']['n'] ?> factura(s)</span></div>
  <div class="tile"><small>Deuda vencida</small><b><?= money($ind['deuda']) ?></b><span><?= $ind['morosos'] ?> grupo(s) con atraso</span></div>
</div>
<div class="grid2">
  <div class="panel"><h2>Afiliados por categoría</h2><p class="sub">Cantidad de personas activas.</p><?php $mx = max([1, ...array_map(fn($x) => (int)$x['v'], $ind['porCat'])]); ?><div class="bars"><?php foreach ($ind['porCat'] as $x): ?><div class="bar"><span><?= e($x['n']) ?></span><div class="track"><div class="fill" style="width:<?= round($x['v'] / $mx * 100) ?>%"></div></div><span class="num r"><?= (int)$x['v'] ?></span></div><?php endforeach ?></div></div>
  <div class="panel"><h2>Cobrado por medio de pago</h2><p class="sub">Total de recibos registrados.</p><?php $mx = max([1, ...array_column($ind['porMedio'], 'v')]); ?><div class="bars"><?php foreach ($ind['porMedio'] as $x): ?><div class="bar" style="grid-template-columns:150px minmax(0,1fr) 100px"><span><?= e($x['n']) ?></span><div class="track"><div class="fill" style="width:<?= round($x['v'] / $mx * 100) ?>%"></div></div><span class="num r"><?= money($x['v']) ?></span></div><?php endforeach ?></div></div>
</div>

<div class="panel"><h2>Consultas atendidas</h2><p class="sub">Cotizaciones e información enviadas desde el Cotizador y Beneficios.</p>
  <div class="tiles"><div class="tile"><small>Este mes</small><b><?= $cons['mes'] ?></b><span>consultas</span></div><div class="tile"><small>Total</small><b><?= $cons['total'] ?></b><span><?= $cons['noAfiliados'] ?> de no afiliados</span></div><div class="tile"><small>Por canal</small><b style="font-size:16px"><?= e(implode(' · ', array_map(fn($x) => $x['n'] . ' ' . $x['v'], $cons['canal'])) ?: '—') ?></b><span></span></div><div class="tile"><small>Planes más consultados</small><b style="font-size:16px"><?= e(implode(' · ', array_map(fn($p, $v) => Catalogo::planCorto($p) . " $v", array_keys($cons['planes']), $cons['planes'])) ?: '—') ?></b><span></span></div></div>
  <div class="tbl-wrap" style="margin-top:12px"><table><thead><tr><th>Fecha</th><th>Atendió</th><th>Canal</th><th>Quién</th><th>Celular</th><th>Planes</th><th>Qué se mandó</th></tr></thead><tbody>
  <?php foreach ($cons['ultimas'] as $c): ?><tr><td class="num nw"><?= fd($c['fecha']) ?> <span class="muted"><?= e(substr((string)$c['hora'], 0, 5)) ?></span></td><td><?= e($c['usuario']) ?></td><td><?= e($c['canal']) ?></td><td><?= e($c['nombre']) ?><?= $c['afiliado_id'] ? ' ' . chip('afiliado', 'ok') : '' ?></td><td class="num"><?= e($c['celular']) ?></td><td><?= e(implode(', ', array_map([Catalogo::class, 'planCorto'], \App\Core\Db::leerArray($c['planes'])))) ?></td><td><?= e($c['tipo']) ?></td></tr><?php endforeach;
  if (!$cons['ultimas']): ?><tr><td colspan="7" class="muted">Todavía no hay consultas registradas.</td></tr><?php endif ?>
  </tbody></table></div></div>

<div class="panel"><h2>Consumo en comercios adheridos</h2>
  <div class="grid2"><div class="tbl-wrap"><table><thead><tr><th>Comercio</th><th class="r">Este mes</th><th class="r">Total</th><th class="r">Afiliados</th><th class="r">Vendido</th><th class="r">Ahorro</th></tr></thead><tbody>
  <?php foreach ($com['porComercio'] as $x): ?><tr><td><b><?= e($x['comercio']) ?></b></td><td class="r num"><?= (int)$x['mes'] ?></td><td class="r num"><?= (int)$x['total'] ?></td><td class="r num"><?= (int)$x['af'] ?></td><td class="r num"><?= money($x['vendido']) ?></td><td class="r num"><?= money($x['ahorro']) ?></td></tr><?php endforeach;
  if (!$com['porComercio']): ?><tr><td colspan="6" class="muted">Todavía no hay consumos.</td></tr><?php endif ?></tbody></table></div>
  <div><b>Los que más los usan</b><?php foreach ($com['top'] as $x): ?><div class="row" style="justify-content:space-between;font-size:14px;border-bottom:1px solid var(--line);padding:4px 0"><span><?= e($x['nombre']) ?> <span class="muted">N° <?= (int)$x['nro'] ?></span></span><span><?= (int)$x['n'] ?> · ahorró <?= money($x['ahorro']) ?></span></div><?php endforeach ?><?= $com['top'] ? '' : '<p class="muted">—</p>' ?></div></div></div>

<div class="panel"><div class="row" style="justify-content:space-between;align-items:flex-end"><div><h2>Consumo de beneficios</h2><p class="sub" style="margin:0">Qué usan los afiliados en cada unidad y comercio, <?= e(Documentos::PER_AHO[$k]) ?>. Sirve para saber qué beneficios funcionan y cuáles no.</p></div><div class="row" style="gap:8px"><?= $seg($k) ?><a class="btn small" href="/prestaciones">Ver cada uso</a></div></div>
  <?php $pls = array_filter(Catalogo::planes(), fn($p) => (bool)array_filter($ben['L'], fn($x) => !empty($x['pl'][$p['id']]))); $tot = array_sum(array_column($ben['L'], 'usos')); ?>
  <div class="tbl-wrap" style="margin-top:12px"><table><thead><tr><th>Unidad o comercio</th><th>Beneficio</th><th class="r">Usos</th><th class="r">Sin cargo</th><th class="r">Con descuento</th><th class="r">Afiliados</th><?php foreach ($pls as $p): ?><th class="r"><?= e(Catalogo::planCorto($p['id'])) ?></th><?php endforeach ?><th class="r">Ahorro</th></tr></thead><tbody>
  <?php foreach ($ben['L'] as $x): ?><tr><td><b><?= e($x['lugar']) ?></b></td><td><?= e($x['serv']) ?></td><td class="r num"><b><?= $x['usos'] ?></b></td><td class="r num"><?= $x['sc'] ?: '—' ?></td><td class="r num"><?= $x['cd'] ?: '—' ?></td><td class="r num"><?= count($x['af']) ?></td><?php foreach ($pls as $p): ?><td class="r num"><?= $x['pl'][$p['id']] ?? '—' ?></td><?php endforeach ?><td class="r num"><?= $x['aho'] ? money($x['aho']) : '—' ?></td></tr><?php endforeach;
  if (!$ben['L']): ?><tr><td colspan="8" class="muted">Todavía no hay consumos registrados <?= e(Documentos::PER_AHO[$k]) ?>.</td></tr><?php endif ?>
  </tbody></table></div>
  <?php if ($ben['L']): ?><p style="margin:10px 0 0;font-size:14px"><b>Lo más usado:</b> <?= e($ben['L'][0]['serv']) ?> en <?= e($ben['L'][0]['lugar']) ?> (<?= round($ben['L'][0]['usos'] / max(1, $tot) * 100) ?>% de los usos).</p><?php endif ?>
  <?php if ($ben['nadie']): ?><p style="margin:6px 0 0;font-size:14px;color:var(--warn)"><b>Nadie usó <?= e(Documentos::PER_AHO[$k]) ?>:</b> <?= e(implode(', ', array_map(fn($p) => $p['nombre'] . ' (' . $p['unidad'] . ')', $ben['nadie']))) ?>.</p><?php endif ?>
</div>

<div class="panel"><h2>Facturado y cobrado por mes</h2><?php $mx = max([1, ...array_map(fn($x) => (float)$x['fac'], $ind['porMes'])]); ?>
  <div class="bars"><?php foreach ($ind['porMes'] as $x): ?><div class="bar" style="grid-template-columns:150px minmax(0,1fr) 220px"><span><?= e(Fmt::perLabel($x['p'])) ?></span><div class="track"><div class="fill" style="width:<?= round($x['fac'] / $mx * 100) ?>%"></div><div class="fill b2" style="width:<?= round((float)$x['cob'] / $mx * 100) ?>%"></div></div><span class="num r"><?= money($x['fac']) ?> · cobrado <?= money($x['cob']) ?></span></div><?php endforeach ?></div>
  <?= $ind['porMes'] ? '' : '<p class="muted">Todavía no hay facturas.</p>' ?></div>
