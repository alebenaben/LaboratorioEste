<?php
/** CU-21 · Auditoría. @var array $pend @var array $hechas */
use App\Domain\Catalogo;

$n = count($pend);
?>
<?= head('Auditoría', $n ? "Hay $n " . ($n === 1 ? 'solicitud' : 'solicitudes') . ' esperando tu decisión.' : 'No hay solicitudes pendientes.') ?>
<div style="display:flex;flex-direction:column;gap:12px">
<?php foreach ($pend as $x): $checks = json_decode((string)$x['controles'], true) ?: []; ?>
  <div class="aud-card">
    <div class="row" style="justify-content:space-between;align-items:flex-start"><div><b style="font-size:17px"><?= e($x['prest']) ?></b><div class="muted"><?= e($x['unidad']) ?> · solicitud N° <?= (int)$x['nro'] ?> · <?= fd($x['fecha']) ?> · la pidió <?= e($x['usuario']) ?></div></div><?= chip('En auditoría', 'warn') ?></div>
    <div><b><?= e($x['nombre']) ?></b> · N° <?= (int)$x['anro'] ?> · <?= e(Catalogo::plan($x['plan_id'])['nombre']) ?> · alta <?= fd($x['fecha_alta']) ?></div>
    <?php if ($x['observacion'] !== ''): ?><div style="background:var(--sunken);border-radius:8px;padding:10px 12px"><span class="muted">Observación:</span> <?= e($x['observacion']) ?></div><?php endif ?>
    <?php if ($checks): ?><div class="checks"><?php foreach ($checks as $c): ?><div class="check"><div class="ic <?= $c['ok'] ? 'ok' : 'bad' ?>"><?= $c['ok'] ? '✓' : '✕' ?></div><div><b><?= e($c['t']) ?></b><span><?= e($c['d']) ?></span></div></div><?php endforeach ?></div><?php endif ?>
    <div class="row" style="justify-content:flex-end"><button class="btn small" type="button" data-modal="/afiliados/<?= (int)$x['afiliado_id'] ?>/historial">Ver historial del afiliado</button><button class="btn danger" type="button" data-modal="/auditoria/<?= (int)$x['id'] ?>/decidir?ok=0">Rechazar</button><button class="btn primary" type="button" data-modal="/auditoria/<?= (int)$x['id'] ?>/decidir?ok=1">Aprobar</button></div>
  </div>
<?php endforeach;
if (!$pend): ?><div class="panel"><p class="muted" style="margin:0">Todo al día: no hay prestaciones para auditar.</p></div><?php endif ?>
</div>
<div class="panel"><h2>Últimas resueltas</h2><div class="tbl-wrap"><table><thead><tr><th>N°</th><th>Fecha</th><th>Afiliado</th><th>Prestación</th><th>Resultado</th><th>Respuesta</th><th>Pidió / auditó</th></tr></thead><tbody>
<?php foreach ($hechas as $x): ?>
  <tr><td class="mono"><?= (int)$x['nro'] ?></td><td class="num"><?= fd($x['fecha']) ?></td><td><?= e($x['nombre']) ?></td><td><?= e($x['prest']) ?></td><td><?= $x['estado'] === 'aprobada' ? chip('Autorizada', 'ok') : chip('Rechazada', 'bad') ?></td><td class="muted"><?= e($x['respuesta'] ?: '—') ?></td><td class="nw"><?= e($x['usuario']) ?><div class="muted" style="font-size:12px">auditó <?= e($x['auditor']) ?></div></td></tr>
<?php endforeach;
if (!$hechas): ?><tr><td colspan="7" class="muted">Todavía no hay solicitudes resueltas.</td></tr><?php endif ?>
</tbody></table></div></div>
