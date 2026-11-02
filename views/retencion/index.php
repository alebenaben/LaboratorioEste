<?php
/**
 * CU-12 · Quiere darse de baja.
 * @var string $q @var ?array $a @var string $fin @var string $motivo @var array $llamar
 * @var array $d @var array $ofertas @var array $guiones @var ?array $prev @var array $promos @var array $cuenta @var string $hoy
 */
use App\Domain\Catalogo;
use App\Domain\Documentos;
use App\Domain\Fechas;
use App\Domain\Fmt;
use App\Domain\Padron;
use App\Domain\Retencion;

$admin = es_admin();
if ($fin):
    $F = [
        'retenido' => ['ok', '¡Se queda!', 'Quedó registrado. Ya se aplicó lo que aceptó.'],
        'derivado' => ['warn', 'Pasado a administración', 'Un administrador lo va a llamar para cerrar la propuesta.'],
        'piensa' => ['warn', 'Lo va a pensar', 'Va a aparecer en “Para llamar hoy” dentro de 3 días.'],
        'baja_pedida' => ['bad', 'Insiste en darse de baja', 'La baja la confirma administración. La puerta siempre queda abierta para que vuelva.'],
        'baja' => ['bad', 'Baja registrada', 'El afiliado quedó dado de baja con fecha de hoy.'],
    ][$fin] ?? ['ok', 'Listo', ''];
?>
<?= head('Quiere darse de baja', '') ?>
<div class="panel" style="max-width:720px"><div class="verdict <?= $F[0] ?>" style="font-size:18px"><?= e($F[1]) ?></div><p style="margin:12px 0"><?= e($F[2]) ?></p><a class="btn primary" href="/retencion">Atender a otro afiliado</a></div>
<?php return; endif ?>

<?= head('Quiere darse de baja', 'El sistema te muestra todo sobre el afiliado y te guía, paso a paso, para que se quede.') ?>
<?php if ($llamar && !$a): ?>
<div class="panel"><h2>📞 Para llamar hoy</h2><p class="sub">Dijeron que lo iban a pensar.</p>
<?php foreach ($llamar as $r): ?>
  <div class="row" style="justify-content:space-between;border-bottom:1px solid var(--line);padding:6px 0"><span><b><?= e($r['nombre']) ?></b> <span class="muted">· <?= e($r['telefono'] ?: 'sin teléfono') ?> · <?= e(Retencion::motivoTexto($r['motivo'])) ?></span></span><a class="btn small" href="/retencion?a=<?= (int)$r['afiliado_id'] ?>&m=<?= e($r['motivo']) ?>">Atender</a></div>
<?php endforeach ?></div>
<?php endif ?>

<form class="panel" method="get" action="/retencion"><p class="at-paso">1 · ¿Quién es?</p>
  <label class="f" style="margin:0;max-width:520px">Apellido, DNI o N° de afiliado<input name="q" value="<?= e($q) ?>" placeholder="Ej.: Gómez, 30.123.456 o 75" autocomplete="off" data-sugerir="afiliados" data-auto-submit <?= $a ? '' : 'autofocus' ?>></label>
  <div style="margin-top:6px;font-size:13.5px"><?php if ($q !== '' && !$a): ?><span style="color:var(--bad)">No se encontró ese afiliado.</span><?php endif ?></div>
</form>
<?php if (!$a) return; ?>
<?php if (!$a['activo']): ?><div class="panel"><div class="verdict bad">Este afiliado ya está dado de baja.</div></div><?php return; endif;
$g = $d['g'];
$cum = $a['nac'] ? substr(fd($a['nac']), 0, 5) . ' (' . Fechas::edad($a['nac'], $hoy) . ' años)' : '';
$cfg = ['afiliado' => $a['id'], 'origen' => 'guia', 'ofertas' => $ofertas, 'admin' => $admin, 'prev' => $prev ? fd($prev['fecha']) : null, 'motivo' => $motivo];
?>
<div class="panel"><p class="at-paso">2 · Todo lo que tenés que saber</p>
  <div class="row" style="justify-content:space-between;align-items:flex-start;gap:10px"><div><h2 style="margin:0;font-size:20px"><?= e($a['nombre']) ?></h2><div class="muted">N° <?= $a['nro'] ?> · <?= e(Catalogo::plan($g['plan_id'])['nombre']) ?> · afiliado desde el <?= fd($a['fecha_alta']) ?></div></div><?= chip_cuenta($cuenta['estado']) ?></div>
  <div class="tiles" style="margin-top:12px">
    <div class="tile"><small>Antigüedad</small><b style="font-size:20px"><?= e($d['ant']) ?></b><span>con Integral del Este</span></div>
    <div class="tile"><small>Paga por mes</small><b style="font-size:20px"><?= e($d['cuota']) ?></b><span><?= count($d['fam']) ?> <?= count($d['fam']) === 1 ? 'integrante' : 'integrantes' ?></span></div>
    <div class="tile"><small>Ahorró con Integral</small><b style="font-size:20px;color:var(--ok)"><?= e($d['aho'] ?: '$ 0') ?></b><span><?= $d['ahoY'] ? 'este año ' . e($d['ahoY']) : 'todavía no usó beneficios' ?></span></div>
  </div>
  <div class="grid2" style="margin-top:12px">
    <div><b>Lo que usó este año</b><?php foreach ($d['usados'] as $x): ?><div style="font-size:14px;padding:3px 0">✓ <?= e($x['p']['nombre']) ?> <span class="muted">· <?= $x['veces'] ?> <?= $x['veces'] === 1 ? 'vez' : 'veces' ?></span></div><?php endforeach ?><?= $d['usados'] ? '' : '<div class="muted" style="font-size:14px">No usó nada este año.</div>' ?></div>
    <div><b>Lo que tiene sin usar</b><?php foreach ($d['sinUso'] as $x): ?><div style="font-size:14px;padding:3px 0;color:var(--logo-red)">★ <?= e($x['p']['nombre']) ?> <span class="muted">· le <?= $x['quedan'] === 1 ? 'queda' : 'quedan' ?> <?= $x['quedan'] ?> sin cargo</span></div><?php endforeach ?><?= $d['sinUso'] ? '' : '<div class="muted" style="font-size:14px">—</div>' ?></div>
  </div>
  <div style="margin-top:10px;font-size:13.5px;display:flex;flex-direction:column;gap:3px">
    <div><b>Grupo:</b> <?= e(implode(', ', array_map(fn($m) => Padron::nomPila($m) . ($m['nac'] ? ' (' . Fechas::edad($m['nac'], $hoy) . ')' : ''), $d['fam']))) ?></div>
    <?php if ($cum): ?><div><b>Cumpleaños:</b> <?= $cum ?><?= Fechas::esCumple($a['nac'], $hoy) ? ' 🎂 <b style="color:var(--logo-red)">¡Es hoy! Saludalo.</b>' : '' ?></div><?php endif ?>
    <?php if ($promos): ?><div><b>Promociones vigentes para mencionarle:</b> <?= e(implode(', ', array_map(fn($p) => $p['beneficio'] . ' (' . Documentos::lugarPromo($p) . ')', $promos))) ?></div><?php endif ?>
    <?php if ($d['previas']): ?><div style="color:var(--logo-red)"><b>Ya quiso irse antes:</b> <?= e(implode(' / ', array_map(fn($r) => fd($r['fecha']) . ' · ' . Retencion::motivoTexto($r['motivo']) . ' · ' . ($r['resultado'] === 'retenido' ? 'se quedó con: ' . $r['aceptada'] : str_replace('_', ' ', $r['resultado'])), $d['previas']))) ?></div><?php endif ?>
  </div>
  <div class="row" style="margin-top:12px"><button class="btn btn-wa" type="button" data-wa='<?= e(json_encode(['post' => "/afiliados/{$a['id']}/whatsapp"])) ?>'>Mandarle por WhatsApp todo lo que tiene (PDF)</button></div>
</div>

<div data-ret='<?= e(json_encode($cfg, JSON_UNESCAPED_UNICODE)) ?>' data-guiones='<?= e(json_encode($guiones, JSON_UNESCAPED_UNICODE)) ?>'>
  <div class="panel"><p class="at-paso">3 · ¿Por qué se quiere ir?</p>
    <div class="row" style="gap:8px" data-motivos><?php foreach (Retencion::motivos() as $k => $t): ?><button class="btn<?= $motivo === $k ? ' primary' : '' ?>" type="button" data-motivo="<?= $k ?>"><?= e($t) ?></button><?php endforeach ?></div>
    <label class="f" style="margin-top:10px">Lo que dijo (con sus palabras)<input data-ret-com placeholder="Ej.: perdió el trabajo, no sabía que tenía óptica…"></label>
  </div>
  <div class="panel" style="border:2px solid var(--accent)" data-guion hidden></div>
  <div class="panel" data-ofrecer hidden><p class="at-paso">5 · Qué ofrecerle (de a una, en este orden)</p><div data-ret-pasos></div></div>
  <div class="panel" data-como hidden><p class="at-paso">6 · ¿Cómo terminó?</p><div class="row" style="gap:8px" data-ret-fin></div>
    <p class="muted" style="font-size:12.5px;margin:8px 0 0"><?= $admin ? '' : 'Si insiste, respetá su decisión y despedilo con buena onda: la baja la confirma administración. ' ?>La puerta siempre queda abierta para que vuelva.</p></div>
</div>
<script src="/assets/retencion.js?v=83"></script>
