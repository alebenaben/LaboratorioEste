<?php
/** CU-19 · Enviar promoción. @var array $p @var array $dest @var ?array $ejemplo @var string $hoy */
use App\Domain\Catalogo;
use App\Domain\Fmt;

$pend = array_values(array_filter($dest, fn($x) => !$x['envio']));
$conMail = array_values(array_filter($pend, fn($x) => $x['g']['email']));
$sig = null;
foreach ($pend as $x) {
    if (Fmt::telValido($x['tel'])) {
        $sig = $x;
        break;
    }
}
$paraQuien = $p['planes'] ? 'Solo ' . implode(', ', array_map(fn($x) => Catalogo::plan($x)['nombre'], $p['planes'])) : 'Todos los afiliados';
?>
<?= head('Enviar promoción', e($p['titulo']) . ' · ' . e($p['beneficio']), '<a class="btn" href="/promociones" data-esc="/promociones">‹ Volver a promociones</a>') ?>
<div class="tiles"><div class="tile"><small>Afiliados a los que va</small><b><?= count($dest) ?></b><span><?= e($paraQuien) ?></span></div><div class="tile"><small>Ya la recibieron</small><b><?= count($dest) - count($pend) ?></b><span>por mail o WhatsApp</span></div><div class="tile"><small>Faltan</small><b><?= count($pend) ?></b><span><?= count($conMail) ?> con email</span></div></div>
<div class="grid2" style="margin-top:14px">
  <div class="panel" style="margin:0"><h2>1 · Por mail, a todos juntos</h2><p class="sub">A cada afiliado le llega un mail con su nombre y el PDF de la promoción adjunto.</p>
    <?php if ($ejemplo && $conMail): ?><details style="margin-bottom:10px"><summary style="cursor:pointer;color:var(--accent);font-weight:600">Ver ejemplo del mail</summary><div style="border:1px solid var(--line);border-radius:8px;padding:10px;margin-top:8px;font-size:13.5px"><b>Asunto:</b> <?= e($ejemplo['asunto']) ?><hr style="border:0;border-top:1px solid var(--line)"><?= nl2br(e($ejemplo['cuerpo'])) ?><div class="muted" style="margin-top:6px">📎 PDF de la promoción con su nombre</div></div></details><?php endif ?>
    <button class="btn primary" type="button" data-post="/promociones/<?= $p['id'] ?>/mail" data-confirmar="Se van a enviar <?= count($conMail) ?> mails con el PDF adjunto. ¿Confirmás?" <?= $conMail ? '' : 'disabled' ?> style="padding:12px 16px;font-size:15px">Enviar por mail a <?= count($conMail) ?> <?= count($conMail) === 1 ? 'afiliado' : 'afiliados' ?></button></div>
  <div class="panel" style="margin:0"><h2>2 · Por WhatsApp, de a uno</h2><p class="sub">Se arma el PDF con su nombre y se abre WhatsApp con su número. Lo mandás y tocás de nuevo para el siguiente.</p>
    <?php if ($sig): ?><button class="btn btn-wa" type="button" data-wa='<?= e(json_encode(['post' => "/promociones/{$p['id']}/whatsapp"])) ?>' style="padding:12px 16px;font-size:15px;width:100%">Enviar al siguiente: <?= e($sig['t']['nombre']) ?></button>
    <?php else: ?><p class="muted" style="margin:0"><?= $pend ? 'A los que faltan no les tenemos un celular cargado.' : '¡Listo! Ya se la mandaste a todos.' ?></p><?php endif ?></div>
</div>
<div class="panel"><h2>Afiliados</h2><div class="tbl-wrap"><table><thead><tr><th>Titular</th><th>Plan</th><th>Contacto</th><th>Envío</th><th></th></tr></thead><tbody>
<?php foreach ($dest as $x): ?>
  <tr><td><b><?= e($x['t']['nombre']) ?></b> <span class="muted">N° <?= $x['t']['nro'] ?></span></td><td><?= e(Catalogo::plan($x['g']['plan_id'])['nombre']) ?></td>
    <td style="font-size:13px"><?= e($x['tel']) ?><?= $x['tel'] && $x['g']['email'] ? '<br>' : '' ?><?= e($x['g']['email']) ?><?= !$x['tel'] && !$x['g']['email'] ? '<span class="muted">sin datos</span>' : '' ?></td>
    <td><?= $x['envio'] ? chip($x['envio']['canal'] . ' ' . fd(substr($x['envio']['fecha'], 0, 10)), 'ok') : chip('Pendiente', 'warn') ?></td>
    <td><button class="btn small btn-wa" type="button" data-wa='<?= e(json_encode(['post' => "/promociones/{$p['id']}/whatsapp", 'datos' => ['g' => $x['g']['id']]])) ?>'>WhatsApp</button></td></tr>
<?php endforeach;
if (!$dest): ?><tr><td colspan="5" class="muted">No hay afiliados activos para esta promoción.</td></tr><?php endif ?>
</tbody></table></div><p class="muted" style="font-size:12.5px;margin:10px 0 0">El envío automático por WhatsApp Business queda fuera de este alcance: se manda de a uno desde acá.</p></div>
