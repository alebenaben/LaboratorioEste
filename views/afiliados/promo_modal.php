<?php
/** CU-09 · Promoción en la cuota del grupo. @var array $g @var array $tit @var float $base @var string $desde @var string $hoy @var int $afiliado */
use App\Domain\Catalogo;
use App\Domain\Fechas;
use App\Domain\Fmt;

$p = $g['promo'];
$meses = [];
$ini = max(\App\Core\Config::inicio()['periodo'], substr($hoy, 0, 7));
for ($i = 0; $i < 13; $i++) {
    $meses[] = Fechas::perMas($ini, $i);
}
?>
<form class="modal" role="dialog" aria-modal="true" aria-label="Promoción del grupo" method="post" action="/grupos/<?= $g['id'] ?>/promo" data-ajax data-promo-grupo data-base="<?= $base ?>">
  <?= csrf_field() ?><input type="hidden" name="a" value="<?= $afiliado ?>">
  <h2>Promoción en la cuota</h2>
  <p>Grupo de <?= e($tit['nombre']) ?> · cuota del grupo <?= money($base) ?>.</p>
  <?php if ($p): ?><div class="verdict <?= $p['hasta'] < substr($hoy, 0, 7) ? 'warn' : 'ok' ?>" style="font-size:14px">Promoción actual: <?= $p['pct'] ?>% de <?= e(Fmt::perLabel($p['desde'])) ?> a <?= e(Fmt::perLabel($p['hasta'])) ?><?= $p['hasta'] < substr($hoy, 0, 7) ? ' (ya terminó)' : '' ?><?= $p['retencion'] ? ' · por retención' : '' ?>.</div><?php endif ?>
  <div class="form-grid" style="grid-template-columns:1fr 1fr 1fr">
    <label class="f">Descuento<select name="pct"><?php foreach (Catalogo::PROMO_PCT as $x): ?><option value="<?= $x ?>"<?= sel($x, $p['pct'] ?? 10) ?>><?= $x ?>%</option><?php endforeach ?></select></label>
    <label class="f">Duración<select name="meses"><?php foreach (Catalogo::PROMO_MESES as $x): ?><option value="<?= $x ?>"<?= sel($x, $p['meses'] ?? 3) ?>><?= $x ?> <?= $x === 1 ? 'mes' : 'meses' ?></option><?php endforeach ?></select></label>
    <label class="f">Desde<select name="desde"><?php foreach ($meses as $m): ?><option value="<?= $m ?>"<?= sel($m, $desde) ?>><?= e(Fmt::perLabel($m)) ?></option><?php endforeach ?></select></label>
  </div>
  <p class="sub" data-promo-txt style="margin:0"></p>
  <p class="form-error" style="display:none"></p>
  <div class="row" style="justify-content:space-between">
    <?php if ($p): ?><button class="btn danger" type="submit" name="accion" value="quitar">Quitar promoción</button><?php else: ?><span></span><?php endif ?>
    <span class="row"><button class="btn" type="button" data-act="modal-no">Cancelar <kbd>Esc</kbd></button><button class="btn primary" type="submit" name="accion" value="aplicar">Aplicar</button></span>
  </div>
</form>
