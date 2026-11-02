<?php
/**
 * CU-26 a CU-31 · Caja.
 * @var string $per @var array $aFacturar @var array $pend @var array $debito @var array $facturas @var array $recibos
 * @var string $filtro @var array $periodos @var ?array $ultima @var array $mails @var string $hoy @var array $cola @var ?array $ejemplo
 */
use App\Core\Config;
use App\Domain\Catalogo;
use App\Domain\Fmt;
use App\Domain\Padron;
use App\Service\Arca\Arca;

$ini = Config::inicio();
$tot = array_sum(array_column($aFacturar, 'importe'));
$modo = Arca::modo();
$titular = fn($gid) => Padron::titular((int)$gid)['nombre'];
?>
<?= head('Caja', 'Facturación de las cuotas del mes, débito automático y cobro en mostrador.') ?>
<div class="demo">Punto de partida: todos los afiliados arrancaron al día al <?= fd($ini['fecha']) ?>. La primera facturación del sistema es <?= e(Fmt::perLabel($ini['periodo'])) ?>. ARCA: <b><?= $modo === 'simulado' ? 'CAE simulado (modo de prueba)' : ($modo === 'homologacion' ? 'homologación' : 'producción') ?></b><?= ((int)$cola['cae'] || (int)$cola['mail']) ? ' · en proceso: ' . (int)$cola['cae'] . ' pedido(s) de CAE, ' . (int)$cola['mail'] . ' mail(s)' : '' ?>.</div>
<div class="grid2">
  <form class="panel" method="post" action="/caja/facturar" data-ajax data-confirmar="Se van a emitir <?= count($aFacturar) ?> factura(s) de <?= e(Fmt::perLabel($per)) ?> por <?= e(Fmt::money($tot)) ?>. ¿Confirmás?"><h2>Facturar cuotas del mes</h2><p class="sub">Una factura por grupo con la suma de las cuotas de sus integrantes (A para Responsables Inscriptos, B para el resto).</p>
    <?= csrf_field() ?>
    <div class="row"><label class="f">Mes<input type="month" name="per" id="per" min="<?= e($ini['periodo']) ?>" value="<?= e($per) ?>" data-ir-periodo></label></div>
    <div style="margin:14px 0;display:flex;flex-direction:column;gap:4px">
      <div>Grupos a facturar: <b class="num"><?= count($aFacturar) ?></b></div>
      <div>Total a facturar: <b class="num"><?= money($tot) ?></b></div>
      <?php if (!$aFacturar): ?><div class="muted"><?= $per < $ini['periodo'] ? 'El sistema factura desde ' . e(Fmt::perLabel($ini['periodo'])) . '.' : 'Todos los grupos ya tienen la factura de ' . e(Fmt::perLabel($per)) . '.' ?></div><?php endif ?>
    </div>
    <p class="form-error" style="display:none"></p>
    <button class="btn primary" type="submit"<?= $aFacturar ? '' : ' disabled' ?>>Emitir <?= count($aFacturar) ?> factura(s)</button>
    <?php if ($ultima): ?><p class="sub" style="margin:14px 0 0">Última emisión: <?= (int)$ultima['cant'] ?> facturas de <?= e(Fmt::perLabel($ultima['periodo'])) ?> por <?= money($ultima['total']) ?> (<?= fd($ultima['fecha']) ?>).</p><?php endif ?>
  </form>
  <div class="panel"><h2>Enviar facturas por mail</h2>
    <p class="sub">Facturas de <?= e(Fmt::perLabel($per)) ?>: <?= $mails['total'] ?>. Con email: <?= $mails['con'] ?> · sin email: <?= count($mails['sin']) ?> · ya enviadas: <?= $mails['enviadas'] ?><?= $mails['sinCae'] ? ' · esperando CAE: ' . $mails['sinCae'] : '' ?>.</p>
    <?php if ($ejemplo): ?><details style="margin-bottom:10px"><summary style="cursor:pointer;color:var(--accent);font-weight:600">Ver ejemplo del mail</summary><div style="border:1px solid var(--line);border-radius:8px;padding:10px;margin-top:8px;font-size:13.5px"><b>Asunto:</b> <?= e($ejemplo['asunto']) ?><hr style="border:0;border-top:1px solid var(--line)"><?= nl2br(e($ejemplo['cuerpo'])) ?><div class="muted" style="margin-top:6px">📎 Factura en PDF</div></div></details><?php endif ?>
    <div class="row" style="gap:8px"><button class="btn primary" type="button" data-post="/caja/mails" data-datos='<?= e(json_encode(['per' => $per])) ?>' data-confirmar="Se van a enviar <?= count($mails['aEnviar']) ?> mails desde sistema@integraldeleste.com.ar con la factura en PDF. ¿Confirmás?"<?= $mails['aEnviar'] ? '' : ' disabled' ?>>Enviar <?= count($mails['aEnviar']) ?> <?= count($mails['aEnviar']) === 1 ? 'mail' : 'mails' ?></button><a class="btn small" href="/config#mails">Editar el texto del mail</a></div>
    <?php if ($mails['sin']): ?><p class="muted" style="font-size:12.5px;margin:8px 0 0">Sin email: <?= e(implode(', ', array_map(fn($f) => $titular($f['gid']), array_slice($mails['sin'], 0, 8)))) ?><?= count($mails['sin']) > 8 ? ' y ' . (count($mails['sin']) - 8) . ' más' : '' ?>. A ellos mandales por WhatsApp desde cada factura.</p><?php endif ?>
  </div>
  <div class="panel"><h2>Débito automático</h2><p class="sub">Cuotas de los grupos adheridos. Registrá cada una cuando entra el pago (Mercado Pago cobra en fechas distintas).</p>
  <?php if ($debito): ?>
    <div class="tbl-wrap"><table><thead><tr><th>Titular</th><th>Mes</th><th class="r">Importe</th><th>Fecha del pago</th><th></th></tr></thead><tbody>
    <?php foreach ($debito as $f): ?><tr><td><?= e($titular($f['grupo_id'])) ?></td><td><?= e(Fmt::perLabel($f['periodo'])) ?></td><td class="r num"><?= money($f['importe']) ?></td>
      <td colspan="2"><form class="row" style="flex-wrap:nowrap;gap:6px" method="post" action="/caja/debito" data-ajax><?= csrf_field() ?><input type="hidden" name="factura" value="<?= $f['id'] ?>"><input type="date" name="fecha" value="<?= $hoy ?>" max="<?= $hoy ?>" style="padding:5px 8px"><button class="btn small primary" type="submit">Registrar</button></form></td></tr><?php endforeach ?>
    </tbody></table></div>
    <div class="row" style="justify-content:flex-end;margin-top:10px"><button class="btn small" type="button" data-post="/caja/debito" data-datos='{"todas":"1"}' data-confirmar="¿Registrar todas las cuotas de débito automático con fecha de hoy?">Registrar todas con fecha de hoy</button></div>
  <?php else: ?><p class="muted" style="margin:0">No hay cuotas pendientes con débito automático.</p><?php endif ?>
  </div>
</div>
<div class="panel"><h2>Cobranza en mostrador</h2><p class="sub">Cuotas pendientes de cobro, ordenadas por vencimiento.</p>
<div class="tbl-wrap"><table><thead><tr><th>Titular</th><th>Mes</th><th>Vence</th><th class="r">Importe</th><th>Estado</th><th>Medio de pago</th><th></th></tr></thead><tbody>
<?php foreach ($pend as $f): $g = Padron::grupo($f['grupo_id']); ?>
  <tr><td><b><?= e($titular($f['grupo_id'])) ?></b></td><td><?= e(Fmt::perLabel($f['periodo'])) ?></td><td class="num"><?= fd($f['vto']) ?></td><td class="r num"><?= money($f['importe']) ?></td>
    <td><?= $f['vto'] < $hoy ? chip('Vencida', 'bad') : chip('A vencer', 'warn') ?></td>
    <td colspan="2"><form class="row" style="flex-wrap:nowrap;gap:6px" method="post" action="/caja/cobrar" data-ajax><?= csrf_field() ?><input type="hidden" name="factura" value="<?= $f['id'] ?>"><select name="medio" aria-label="Medio de pago"><?php foreach (Catalogo::MEDIOS as $m): ?><option<?= sel($m, $g['forma_pago']) ?>><?= e($m) ?></option><?php endforeach ?></select><button class="btn small primary" type="submit">Cobrar</button></form></td></tr>
<?php endforeach;
if (!$pend): ?><tr><td colspan="7" class="muted">No hay cuotas pendientes.</td></tr><?php endif ?>
</tbody></table></div></div>
<div class="panel"><h2>Recibos emitidos</h2><p class="sub">Los últimos cobros. Tocá Ver para imprimirlo o mandarlo.</p><div class="tbl-wrap"><table><thead><tr><th>Fecha</th><th>Recibo</th><th>Titular</th><th>Cuota</th><th>Medio</th><th>Cobró</th><th class="r">Importe</th><th></th></tr></thead><tbody>
<?php foreach ($recibos as $r): ?><tr><td class="num nw"><?= fd($r['fecha']) ?></td><td class="mono"><?= e(\App\Domain\Caja::nroRecibo($r)) ?></td><td><?= e($r['titular']) ?></td><td><?= e(Fmt::perLabel($r['periodo'])) ?></td><td><?= e($r['medio']) ?></td><td class="muted"><?= e($r['usuario']) ?></td><td class="r num"><?= money($r['importe']) ?></td><td><button class="btn small" type="button" data-modal="/recibos/<?= (int)$r['id'] ?>">Ver</button></td></tr><?php endforeach;
if (!$recibos): ?><tr><td colspan="8" class="muted">Todavía no hay cobros.</td></tr><?php endif ?>
</tbody></table></div></div>
<div class="panel"><form class="row" style="justify-content:space-between;margin-bottom:12px" method="get" action="/caja"><div><h2>Facturas</h2><p class="sub" style="margin:0">Emitidas por el sistema desde <?= e(Fmt::perLabel($ini['periodo'])) ?>.</p></div>
  <input type="hidden" name="per" value="<?= e($per) ?>"><select name="fper" aria-label="Filtrar mes" data-autosubmit><option value="">Todos los meses</option><?php foreach ($periodos as $p): ?><option value="<?= e($p) ?>"<?= sel($p, $filtro) ?>><?= e(Fmt::perLabel($p)) ?></option><?php endforeach ?></select></form>
<div class="tbl-wrap"><table><thead><tr><th>Factura</th><th>Titular</th><th>Mes</th><th class="r">Bonificación</th><th class="r">Importe</th><th>CAE</th><th>Estado</th><th>Mail</th><th></th></tr></thead><tbody>
<?php foreach ($facturas as $f): ?>
  <tr><td class="mono"><?= $f['letra'] ?> <?= e($f['nro']) ?></td><td><?= e($f['titular']) ?></td><td><?= e(Fmt::perLabel($f['periodo'])) ?></td><td class="r num"><?= $f['promo_pct'] ? $f['promo_pct'] . '% · − ' . money($f['base'] - $f['importe']) : '—' ?></td><td class="r num"><?= money($f['importe']) ?></td>
    <td class="mono estado-cae"><?= $f['cae'] ? e($f['cae']) : ($f['cae_estado'] === 'rechazada' ? '<span style="color:var(--bad)" title="' . e($f['cae_error']) . '">Rechazado</span>' : '<span class="muted">Pendiente</span>') ?></td>
    <td><?= $f['estado'] === 'pagada' ? chip('Pagada', 'ok') : chip('Pendiente', 'warn') ?></td>
    <td class="muted" style="font-size:12.5px"><?= ['encolado' => 'en camino', 'enviado' => 'enviado', 'rebotado' => '<span style="color:var(--bad)">rebotó</span>'][$f['mail_estado'] ?? ''] ?? '—' ?></td>
    <td><button class="btn small" type="button" data-modal="/facturas/<?= $f['id'] ?>">Ver</button></td></tr>
<?php endforeach;
if (!$facturas): ?><tr><td colspan="9" class="muted">Sin facturas.</td></tr><?php endif ?>
</tbody></table></div></div>
<script src="/assets/caja.js?v=83"></script>
