<?php
/** CU-20 · Autorizaciones. @var array $hist @var array $lista @var array $prest */
$admin = es_admin();
$chipAut = function (array $x): string {
    return match ($x['estado']) {
        'autorizada' => chip('Autorizada', 'ok'),
        'rechazada' => chip('Rechazada', 'bad'),
        'pendiente' => chip('En auditoría', 'warn'),
        'aprobada' => chip('Autorizada en auditoría', 'ok'),
        'desaprobada' => chip('Rechazada en auditoría', 'bad'),
        default => chip($x['estado']),
    };
};
?>
<?= head('Autorizaciones', 'Antes de atender a un afiliado en el CMDE, la óptica o la biobárica, verificá que esté activo y con los pagos al día.') ?>
<div class="grid2">
  <form class="panel" id="faut"><h2>Nueva autorización</h2><p class="sub">Elegí el afiliado y qué va a usar.</p>
    <div class="form-grid" style="grid-template-columns:1fr">
    <?php if ($admin): ?>
      <label class="f">Afiliado<select id="aut-af" name="afiliado_id"><option value="">Seleccionar…</option><?php foreach ($lista as $a): ?><option value="<?= (int)$a['id'] ?>"><?= e($a['nombre']) ?> · N° <?= (int)$a['nro'] ?><?= $a['estado'] !== 'activo' ? ' (baja)' : '' ?></option><?php endforeach ?></select></label>
    <?php else: ?>
      <label class="f">Afiliado: nombre, DNI, CUIT o N° de afiliado<input id="aut-busca" name="afiliado" autocomplete="off" placeholder="Ej.: Ramallo, 28913458 o 75" data-sugerir="afiliados" autofocus></label>
    <?php endif ?>
      <label class="f">Prestación o beneficio<select id="aut-pr" name="prest"><?php foreach ($prest as $p): ?><option value="<?= e($p['cod']) ?>"><?= e($p['nombre']) ?></option><?php endforeach ?></select></label>
    </div></form>
  <div class="panel" id="aut-res"><h2>Verificación</h2><p class="sub">Elegí un afiliado para ver si corresponde autorizar.</p></div>
</div>
<div class="panel"><h2><?= $admin ? 'Historial' : 'Tus autorizaciones' ?></h2><p class="sub"><?= $admin ? 'Autorizaciones emitidas, rechazos y auditorías.' : 'Las de hoy y todas las que enviaste a auditoría, con su resultado.' ?></p>
<div class="tbl-wrap"><table><thead><tr><th>N°</th><th>Fecha</th><th>Afiliado</th><th>Prestación</th><th>Resultado</th><th>Motivo</th><th>Por</th></tr></thead><tbody>
<?php foreach ($hist as $x): ?>
  <tr><td class="mono"><?= (int)$x['nro'] ?></td><td class="num"><?= fd($x['fecha']) ?></td><td><?= e($x['nombre']) ?></td><td><?= e($x['prest']) ?><?= $x['consumo'] ? '<div class="muted" style="font-size:12px">' . e($x['consumo']) . '</div>' : '' ?></td><td><?= $chipAut($x) ?></td>
    <td class="muted"><?= e(in_array($x['estado'], ['pendiente', 'aprobada', 'desaprobada'], true) ? ($x['respuesta'] ?: $x['observacion']) : $x['motivo']) ?: '—' ?></td>
    <td class="nw"><?= e($x['usuario'] ?: '—') ?><?= $x['auditor'] ? '<div class="muted" style="font-size:12px">auditó ' . e($x['auditor']) . '</div>' : '' ?></td></tr>
<?php endforeach;
if (!$hist): ?><tr><td colspan="7" class="muted">Todavía no hay autorizaciones.</td></tr><?php endif ?>
</tbody></table></div></div>
<script src="/assets/autorizaciones.js?v=83"></script>
