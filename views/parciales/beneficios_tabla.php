<?php
/**
 * Tabla de beneficios del año (htmlBeneficios de la maqueta).
 * @var array $a afiliado @var array $saldo Beneficios::saldo() @var bool $compacto @var string $hoy
 */
use App\Domain\Beneficios;

$compacto = $compacto ?? false;
$y = substr($hoy ?? \App\Domain\Fechas::hoy(), 0, 4);
$filas = $compacto ? array_filter($saldo, fn($b) => $b['r']['gratis'] || $b['veces']) : $saldo;
if (!$saldo): ?>
<p class="muted" style="margin:0">Su plan no tiene beneficios cargados para estas prestaciones.</p>
<?php else: ?>
<div class="tbl-wrap"><table><thead><tr><th>Beneficio <?= $y ?></th><th class="r">Sin cargo usados</th><th class="r">Le quedan</th><th class="r">Usos</th><?php if (!$compacto): ?><th class="r">Ahorro</th><?php endif ?></tr></thead><tbody>
<?php foreach ($filas as $b): ?>
<tr><td><?= e($b['p']['nombre']) ?><div class="muted" style="font-size:12.5px"><?= e(Beneficios::textoRegla($b['r'])) ?></div><?php if ($b['est']): ?><div style="font-size:12.5px;color:var(--warn)"><?= e($b['est']) ?></div><?php endif ?></td>
  <td class="r num"><?php if ($b['r']['gratis']): ?><?= $b['usados'] ?> de <?= $b['r']['gratis'] ?><div class="muted" style="font-size:12px"><?= Beneficios::VENT_TXT[$b['r']['per']] ?><?= $b['r']['por_grupo'] ? ', grupo' : '' ?></div><?php else: ?>—<?php endif ?></td>
  <td class="r"><?= $b['r']['gratis'] ? ($b['quedan'] ? chip((string)$b['quedan'], 'ok') : chip('0', 'warn')) : '—' ?></td>
  <td class="r num"><?= $b['veces'] ?></td><?php if (!$compacto): ?><td class="r num"><?= money($b['ahorro']) ?></td><?php endif ?></tr>
<?php endforeach;
if (!$filas): ?><tr><td colspan="5" class="muted">Sin usos este año.</td></tr><?php endif ?>
</tbody></table></div>
<?php if (empty($sinBotones)): ?>
<div class="row" style="margin-top:8px"><button class="btn small" type="button" data-modal="/afiliados/<?= (int)$a['id'] ?>/historial">Ver historial de usos</button><button class="btn small btn-wa" type="button" data-wa='<?= e(json_encode(['post' => "/afiliados/{$a['id']}/whatsapp"])) ?>'>Enviar por WhatsApp</button></div>
<?php endif; endif;
