<?php
/** Resultado de la verificación (CU-20). @var array $v @var array $saldo @var bool $admin @var string $hoy */
$a = $v['a'];
$p = $v['p'];
?>
<h2>Verificación</h2><p class="sub"><?= e($a['nombre']) ?> · <?= e($p['nombre']) ?></p>
<div class="checks"><?php foreach ($v['checks'] as $c): ?><div class="check"><div class="ic <?= $c['ok'] ? 'ok' : 'bad' ?>"><?= $c['ok'] ? '✓' : '✕' ?></div><div><b><?= e($c['t']) ?></b><span><?= e($c['d']) ?></span></div></div><?php endforeach ?></div>
<div style="margin-top:14px"><?= \App\Core\View::parcial('parciales/beneficios_tabla', ['a' => $a, 'saldo' => $saldo, 'compacto' => true, 'hoy' => $hoy]) ?></div>
<div class="verdict <?= $v['ok'] ? 'ok' : 'bad' ?>" style="margin-top:14px"><?= $v['ok'] ? 'Corresponde autorizar.' : 'No corresponde autorizar: ' . e($v['motivo']) . '.' ?></div>
<?php if ($p['requiere_auditoria']): ?><div class="verdict warn" style="margin-top:10px">Esta prestación requiere auditoría de un administrador.</div><?php endif ?>
<div class="row" style="margin-top:14px;justify-content:flex-end">
<?php if ($v['ok'] && (!$p['requiere_auditoria'] || $admin)): ?>
  <button class="btn" type="button" data-aut="auditoria" data-obligatoria="0">Enviar a auditoría</button><button class="btn primary" type="button" data-aut="autorizar">Emitir autorización</button>
<?php else: ?>
  <?php if (!$v['ok']): ?><button class="btn danger" type="button" data-aut="rechazar">Registrar rechazo</button><?php endif ?><button class="btn primary" type="button" data-aut="auditoria" data-obligatoria="1">Enviar a auditoría</button>
<?php endif ?>
</div>
