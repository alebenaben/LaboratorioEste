<?php /** CU-31 · Modal de factura o recibo. @var string $tipo @var int $id @var string $html @var bool $tieneMail */ ?>
<div class="modal" role="dialog" aria-modal="true" aria-label="<?= $tipo === 'factura' ? 'Factura de cuota' : 'Recibo' ?>" style="width:min(640px,100%)">
  <?= $html ?>
  <div class="row no-print" style="justify-content:flex-end">
    <button class="btn" type="button" data-act="modal-no">Cerrar <kbd>Esc</kbd></button>
    <button class="btn" type="button" data-post="/<?= $tipo ?>s/<?= $id ?>/mail"<?= $tieneMail ? '' : ' disabled title="El grupo no tiene email: cargalo en la ficha"' ?>>Enviar por mail</button>
    <button class="btn" type="button" data-descargar="/pdf/<?= $tipo ?>/<?= $id ?>">Descargar PDF</button>
    <button class="btn btn-wa" type="button" data-wa='<?= e(json_encode(['post' => "/{$tipo}s/$id/whatsapp"])) ?>'>Compartir PDF</button>
    <button class="btn primary" type="button" data-act="imprimir">Imprimir</button>
  </div>
</div>
