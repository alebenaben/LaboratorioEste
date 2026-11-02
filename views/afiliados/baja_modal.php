<?php
/** CU-10 · Antes de dar de baja. @var array $a @var array $g @var array $ofertas @var ?array $prev @var float $aho @var array $sinUso */
use App\Domain\Catalogo;
use App\Domain\Fechas;
use App\Domain\Padron;
use App\Domain\Retencion;

$meses = intdiv(Fechas::dias($a['fecha_alta'], Fechas::hoy()), 30);
$cfg = ['afiliado' => $a['id'], 'origen' => 'ficha', 'ofertas' => $ofertas, 'admin' => true, 'prev' => $prev ? fd($prev['fecha']) : null];
?>
<div class="modal" role="dialog" aria-modal="true" aria-label="Retención" style="width:min(680px,100%)" data-ret='<?= e(json_encode($cfg, JSON_UNESCAPED_UNICODE)) ?>'>
  <h2>Antes de dar de baja a <?= e(Padron::nomPila($a)) ?></h2>
  <div class="verdict ok" style="font-size:14px">Afiliado hace <?= $meses ?> meses · <?= e(Catalogo::plan($g['plan_id'])['nombre']) ?><?= $aho ? ' · ahorró <b>' . money($aho) . '</b> con Integral' : '' ?><?= $sinUso ? ' · le quedan sin usar: ' . e(implode(', ', array_map(fn($x) => mb_strtolower($x['p']['nombre']) . " ({$x['quedan']})", $sinUso))) : '' ?></div>
  <label class="f">¿Por qué se quiere dar de baja?<select data-ret-motivo><option value="">Elegí el motivo…</option><?php foreach (Retencion::motivos() as $k => $t): ?><option value="<?= $k ?>"><?= e($t) ?></option><?php endforeach ?></select></label>
  <label class="f">Lo que dijo (opcional)<input data-ret-com placeholder="Ej.: perdió el trabajo, no sabía que tenía óptica…"></label>
  <div data-ret-pasos></div>
  <div class="row" style="justify-content:space-between"><button class="btn" type="button" data-act="modal-no">Cancelar <kbd>Esc</kbd></button><span data-ret-fin></span></div>
</div>
