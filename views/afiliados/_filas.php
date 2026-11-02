<?php
/** Filas del padrón. @var array $filas @var array $cuentas */
use App\Domain\Catalogo;

foreach ($filas as $a): ?>
<tr data-panel="/afiliados/<?= $a['id'] ?>/ficha" tabindex="0">
  <td class="mono"><?= $a['nro'] ?></td>
  <td><b><?= e($a['nombre']) ?></b><?php if ((int)$a['paga_id'] !== $a['id']): ?><div class="muted" style="font-size:13px">Paga: <?= e($a['paga']) ?></div><?php endif ?></td>
  <td class="num"><?= e($a['doc']) ?></td>
  <td class="nw"><?= e(Catalogo::categoria($a['categoria_id'])['nombre']) ?><div class="muted" style="font-size:13px"><?= e(Catalogo::planCorto($a['plan_id'])) ?></div></td>
  <td class="r num"><?= money($a['cuota']) ?></td>
  <td class="nw"><?= e(str_replace(' bancaria', '', $a['forma_pago'])) ?></td>
  <td class="num"><?= fd($a['fecha_alta']) ?></td>
  <td><?= $a['activo'] ? chip_cuenta($cuentas[$a['grupo_id']] ?? 'al_dia') : chip('Baja') ?></td>
</tr>
<?php endforeach;
if (!$filas): ?><tr><td colspan="8" class="muted">No hay afiliados que coincidan.</td></tr><?php endif;
