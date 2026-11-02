<?php
/** CU-38 · Informes. @var string $tipo @var string $nombre @var string $et @var array $d @var array $q */
use App\Domain\CatalogoMaqueta;
use App\Domain\Fmt;

$p = $q['p'] ?? 'mes';
$qs = http_build_query(array_merge($q, ['tipo' => $tipo]));
$vista = array_slice($d['filas'], 0, 60);
$v = fn($x, $i) => in_array($i, $d['num'], true) && $x !== '' && is_numeric($x) ? Fmt::money2((float)$x) : (string)$x;
?>
<?= head('Informes', 'Elegí el informe y el período. Se descarga en Excel o PDF con el encabezado de Grupo del Este Salud S.A.S.') ?>
<form class="panel" method="get" action="/informes" id="finf">
  <h2>¿Qué informe?</h2>
  <div class="grid2" style="grid-template-columns:repeat(auto-fit,minmax(240px,1fr))"><?php foreach (CatalogoMaqueta::INFORMES as [$k, $n, $t]): ?><label class="inf-op" style="cursor:pointer;display:flex;gap:8px;align-items:flex-start;border:1px solid var(--line);border-radius:10px;padding:10px 12px"><input type="radio" name="tipo" value="<?= e($k) ?>"<?= $k === $tipo ? ' checked' : '' ?> data-autosubmit><span><b><?= e($n) ?></b><br><small class="muted"><?= e($t) ?></small></span></label><?php endforeach ?></div>
  <h2 style="margin-top:14px">¿Qué período?</h2>
  <div class="row" style="gap:10px;align-items:flex-end">
    <label class="f" style="margin:0">Período<select name="p" data-autosubmit><?php foreach (['mes' => 'Este mes', 'mesant' => 'Mes anterior', 'unmes' => 'Un mes puntual', 'anio' => 'Este año', 'anioant' => 'Año anterior', 'entre' => 'Entre dos fechas'] as $k => $t): ?><option value="<?= $k ?>"<?= sel($k, $p) ?>><?= $t ?></option><?php endforeach ?></select></label>
    <?php if ($p === 'unmes'): ?><label class="f" style="margin:0">Mes<input type="month" name="mes" value="<?= e($q['mes'] ?? substr(hoy(), 0, 7)) ?>" data-autosubmit></label><?php endif ?>
    <?php if ($p === 'entre'): ?><label class="f" style="margin:0">Desde<input type="date" name="d" value="<?= e($q['d'] ?? '') ?>" data-autosubmit></label><label class="f" style="margin:0">Hasta<input type="date" name="h" value="<?= e($q['h'] ?? '') ?>" data-autosubmit></label><?php endif ?>
  </div>
</form>
<div class="panel"><div class="row" style="justify-content:space-between;align-items:flex-end"><div><h2><?= e($nombre) ?></h2><p class="sub" style="margin:0"><?= e($et) ?> · <?= count($d['filas']) ?> fila(s)<?= count($d['filas']) > 60 ? ' · se muestran las primeras 60; el archivo trae todas' : '' ?>.</p></div>
  <div class="row" style="gap:8px"><button class="btn" type="button" data-descargar="/informes/excel?<?= e($qs) ?>">Descargar Excel</button><button class="btn primary" type="button" data-descargar="/informes/pdf?<?= e($qs) ?>">Descargar PDF</button></div></div>
  <div class="tbl-wrap" style="margin-top:12px"><table><thead><tr><?php foreach ($d['cols'] as $i => $c): ?><th class="<?= in_array($i, $d['num'], true) ? 'r' : '' ?>"><?= e($c) ?></th><?php endforeach ?></tr></thead><tbody>
  <?php foreach ($vista as $r): ?><tr><?php foreach ($r as $i => $x): ?><td class="<?= in_array($i, $d['num'], true) ? 'r num' : '' ?>"><?= e($v($x, $i)) ?></td><?php endforeach ?></tr><?php endforeach;
  if (!$d['filas']): ?><tr><td colspan="<?= count($d['cols']) ?>" class="muted">No hay datos en este período.</td></tr><?php endif ?>
  <?php if ($d['tot'] && $d['filas']): ?><tr><?php foreach ($d['tot'] as $i => $x): ?><td class="<?= in_array($i, $d['num'], true) ? 'r num' : '' ?>"><b><?= e($v($x, $i)) ?></b></td><?php endforeach ?></tr><?php endif ?>
  </tbody></table></div></div>
