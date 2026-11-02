<?php
/** Resultado de la verificación en el comercio (CU-23). @var array $r @var string $q */
use App\Domain\Beneficios;
use App\Domain\Fmt;

$a = $r['a'] ?? null;
?>
<div class="verdict <?= $r['ok'] ? 'ok' : 'bad' ?>" style="font-size:17px"><?= $r['ok'] ? '✓ Tiene el beneficio' : '✕ ' . e($r['txt']) ?></div>
<?php if ($a): ?><p style="margin:12px 0 4px"><b><?= e($r['nom']) ?></b> · afiliado N° <?= $a['nro'] ?><?= !empty($r['plan']) ? ' · ' . e($r['plan']['nombre']) : '' ?></p><?php endif ?>
<?php if ($r['ok']): $rr = $r['rubro']; $max = (float)$rr['max_pct']; ?>
  <p style="margin:4px 0"><?= e($r['txt']) ?></p><?php if ($r['car']): ?><p class="muted" style="font-size:13px">Carencia: <?= e($r['car']) ?></p><?php endif ?>
  <p class="muted" style="font-size:13px;margin:6px 0 0">En tu comercio este año: <?= (int)$r['anio']['n'] ?> <?= (int)$r['anio']['n'] === 1 ? 'consumo' : 'consumos' ?><?= (int)$r['anio']['n'] ? ' · ahorró ' . money($r['anio']['ahorro']) : '' ?><?= (int)$rr['limite'] ? ' · usos ' . Beneficios::VENT_TXT[$rr['per']] . ': ' . $r['enPer'] . ' de ' . (int)$rr['limite'] : '' ?></p>
  <form id="fcons" method="post" action="/verificar/consumo" data-ajax data-max="<?= $max ?>" style="margin-top:14px;border-top:1px solid var(--line);padding-top:14px;display:flex;flex-direction:column;gap:10px"><b>Cargar consumo</b>
    <?= csrf_field() ?><input type="hidden" name="q" value="<?= e($q) ?>">
    <label class="f">Qué compró o qué servicio usó<input name="detalle" id="detalle" required autocomplete="off" placeholder="Ej.: cuota mensual, corte de pelo, análisis de sangre"></label>
    <div class="form-grid" style="grid-template-columns:1fr 1fr">
      <label class="f">Importe sin descuento<input name="importe" id="importe" class="monto" inputmode="decimal" required placeholder="$"></label>
      <label class="f">Descuento aplicado %<?= $max ? ' (hasta ' . Fmt::num($max) . '%)' : '' ?><input name="pct" id="cs-pct" type="number" min="0" <?= $max ? 'max="' . $max . '"' : '' ?> step="1" value="<?= Fmt::num($max) ?>"></label></div>
    <p class="form-error" style="display:none"></p>
    <div class="row" style="justify-content:space-between"><span id="cs-tot" class="muted"></span><button class="btn primary" type="submit">Registrar consumo</button></div>
  </form>
<?php endif;
