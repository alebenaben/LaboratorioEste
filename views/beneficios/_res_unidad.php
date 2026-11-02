<?php
/** Resultado de la verificación en la unidad (CU-22). @var array $r */
use App\Domain\Fmt;
use App\Domain\Padron;

$a = $r['a'];
$nom = $a ? trim(Padron::nomPila($a) . ' ' . Fmt::capi($a['apellido'])) : '';
?>
<div class="verdict <?= $r['ok'] ? 'ok' : 'bad' ?>" style="font-size:17px"><?= $r['ok'] ? '✓ Afiliado vigente' : '✕ ' . e($r['txt']) ?></div>
<?php if ($a): ?><p style="margin:12px 0 8px"><b><?= e($nom) ?></b> · afiliado N° <?= $a['nro'] ?><?= !empty($r['plan']) ? ' · ' . e($r['plan']['nombre']) : '' ?></p><?php endif ?>
<?php if ($r['ok']): ?>
  <?php foreach ($r['promos'] as $p): ?><div style="background:#fdecec;border:1.5px solid var(--logo-red);color:var(--logo-red);border-radius:10px;padding:10px 14px;margin:0 0 10px"><b>🎉 Promoción vigente: <?= e($p['beneficio']) ?></b><div style="font-size:13px;color:var(--ink)"><?= e($p['titulo']) ?> · hasta el <?= fd($p['hasta']) ?><?= $p['cond'] ? ' · ' . e(rtrim($p['cond'], '. ')) : '' ?>. Aplicala al cobrar.</div></div><?php endforeach ?>
  <?php if ($r['ahoY']): ?><p style="margin:0 0 10px;color:var(--ok);font-weight:600">💚 Este año ahorró <?= money($r['ahoY']) ?> con Integral del Este. ¡Contáselo!</p><?php endif ?>
  <div class="dispone"><div class="muted" style="font-size:12px;font-weight:700;letter-spacing:.05em">HOY DISPONE DE</div>
  <?php foreach ($r['items'] as $x): ?><div class="dispone-it"><span><?= e($x['p']['nombre']) ?></span><b class="<?= $x['hoy']['c'] === 'ok' ? 'ok' : 'warn' ?>"><?= e($x['hoy']['t']) ?></b></div><div class="muted" style="font-size:12.5px;margin:-2px 0 6px"><?= e(trim($x['hoy']['d'] . ' ' . $x['prox'])) ?></div><?php endforeach ?>
  <?php if ($r['benef']): ?><details style="margin-top:6px"><summary style="cursor:pointer;font-size:13px;color:var(--accent);font-weight:600">Beneficio completo de su plan</summary><?php foreach ($r['benef'] as $b): ?><p style="margin:6px 0;font-size:13.5px"><b><?= e($b['r']) ?>:</b> <?= e($b['t']) ?> <span class="muted">Carencia: <?= e($b['c']) ?>.</span></p><?php endforeach ?></details><?php endif ?></div>
  <p class="sub" style="margin:12px 0 8px">Cobrale con tu sistema aplicando esto y registrá el uso.</p>
  <div style="display:flex;flex-direction:column;gap:10px">
  <?php foreach ($r['items'] as $x): ?>
    <form class="uni-item" method="post" action="/verificar/uso" data-ajax><?= csrf_field() ?><input type="hidden" name="q" value="N° <?= $a['nro'] ?>"><input type="hidden" name="cod" value="<?= e($x['p']['cod']) ?>">
      <div style="flex:1 1 260px"><b><?= e($x['p']['nombre']) ?></b><div class="muted" style="font-size:12.5px;margin-top:2px">Registrar después de atenderlo</div></div>
      <div class="row" style="gap:8px;align-items:flex-end;flex-wrap:nowrap"><label class="f" style="margin:0;width:70px">Cant.<input type="number" name="cant" min="1" value="1"></label><label class="f" style="margin:0;width:130px">Precio (opcional)<input name="precio" class="monto" inputmode="decimal" placeholder="$"></label><button class="btn primary" type="submit">Registrar uso</button></div>
    </form>
  <?php endforeach ?>
  <?php if (!$r['items']): ?><p class="muted">Tu unidad no tiene prestaciones cargadas. Avisale a Integral del Este.</p><?php endif ?>
  </div>
  <p class="muted" style="font-size:12.5px;margin:10px 0 0">El precio es opcional: sirve para que Integral sepa cuánto ahorró el afiliado.</p>
<?php endif;
