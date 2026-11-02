<?php
/** CU-18 · Promociones. @var array $lista @var ?array $edit @var array $cant @var string $hoy */
use App\Domain\Catalogo;
use App\Domain\Documentos;
use App\Domain\Fechas;
use App\Domain\Promociones;

$f = $edit ?? ['id' => 0, 'titulo' => '', 'beneficio' => '', 'unidad' => '', 'desde' => $hoy, 'hasta' => Fechas::finPeriodo(substr($hoy, 0, 7)), 'texto' => '', 'cond' => '', 'planes' => []];
$paraQuien = fn($p) => $p['planes'] ? 'Solo ' . implode(', ', array_map(fn($x) => Catalogo::plan($x)['nombre'], $p['planes'])) : 'Todos los afiliados';
?>
<?= head('Promociones', 'Armá una promoción y mandásela a los afiliados en un PDF prolijo, por WhatsApp o por mail.') ?>
<form class="panel" id="pr-form" method="post" action="/promociones" data-ajax>
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
  <h2><?= $edit ? 'Editar promoción' : 'Nueva promoción' ?></h2>
  <div class="grid2">
    <label class="f">Título<input name="titulo" id="titulo" value="<?= e($f['titulo']) ?>" placeholder="Ej.: Mes de la visión"></label>
    <label class="f">Beneficio (es lo que se ve grande en el PDF)<input name="beneficio" id="beneficio" value="<?= e($f['beneficio']) ?>" placeholder="Ej.: 30% OFF en anteojos recetados"></label>
    <label class="f">Dónde se usa<select name="unidad"><option value="">Integral del Este (en general)</option><?php foreach (Catalogo::unidades() as $u): ?><option value="<?= e($u['nombre']) ?>"<?= sel($u['nombre'], $f['unidad']) ?>><?= e($u['nombre_largo']) ?></option><?php endforeach ?></select></label>
    <div class="row" style="gap:10px;flex-wrap:nowrap"><label class="f" style="flex:1;margin:0">Desde<input type="date" name="desde" id="desde" value="<?= e($f['desde']) ?>"></label><label class="f" style="flex:1;margin:0">Hasta<input type="date" name="hasta" id="hasta" value="<?= e($f['hasta']) ?>"></label></div>
    <label class="f" style="grid-column:1/-1">Descripción<textarea name="texto" rows="3" placeholder="Contale al afiliado en qué consiste la promoción."><?= e($f['texto']) ?></textarea></label>
    <label class="f" style="grid-column:1/-1">Condiciones (opcional)<input name="cond" value="<?= e($f['cond']) ?>" placeholder="Ej.: No acumulable con otras promociones."></label>
  </div>
  <div style="margin-top:12px"><div class="muted" style="font-size:13px;font-weight:600;margin-bottom:6px">¿A quién se la mandás?</div><div class="row" style="gap:16px" data-planes-promo>
    <label class="chk"><input type="checkbox" data-todos<?= $f['planes'] ? '' : ' checked' ?>> Todos los afiliados</label>
    <?php foreach (Catalogo::planes() as $p): ?><label class="chk"><input type="checkbox" name="planes[]" value="<?= e($p['id']) ?>"<?= in_array($p['id'], $f['planes'], true) ? ' checked' : '' ?>> Solo <?= e($p['nombre']) ?></label><?php endforeach ?>
  </div></div>
  <p class="form-error" style="display:none"></p>
  <div class="row" style="justify-content:flex-end;gap:8px;margin-top:14px"><?php if ($edit): ?><a class="btn" href="/promociones">Cancelar</a><?php endif ?><button class="btn" type="button" data-ver-borrador>Ver cómo queda el PDF</button><button class="btn primary" type="submit"><?= $edit ? 'Guardar cambios' : 'Guardar promoción' ?></button></div>
</form>
<div class="panel"><h2>Promociones armadas</h2>
<?php if (!$lista): ?><p class="muted" style="margin:0">Todavía no armaste ninguna promoción.</p><?php endif ?>
<div style="display:flex;flex-direction:column;gap:10px">
<?php foreach ($lista as $p): [$et, $cl] = Promociones::estado($p, $hoy); [$en, $tot] = $cant[$p['id']]; ?>
  <div style="border:1px solid var(--line);border-radius:12px;padding:14px 16px;display:flex;gap:14px;align-items:center;flex-wrap:wrap"><div style="flex:1 1 320px;min-width:0"><div class="row" style="gap:8px;align-items:center"><b style="font-size:16px"><?= e($p['titulo']) ?></b><?= chip($et, $cl) ?></div>
    <div style="color:var(--logo-red);font-weight:700;margin:4px 0"><?= e($p['beneficio']) ?> <span style="color:var(--muted);font-weight:500">· <?= e(Documentos::lugarPromo($p)) ?></span></div>
    <div class="muted" style="font-size:13px">Del <?= fd($p['desde']) ?> al <?= fd($p['hasta']) ?> · Para: <?= e($paraQuien($p)) ?> · Enviada a <b><?= $en ?></b> de <?= $tot ?></div></div>
    <div class="row" style="gap:6px"><button class="btn small" type="button" data-descargar="/pdf/promo/<?= $p['id'] ?>">Ver PDF</button><a class="btn small" href="/promociones?editar=<?= $p['id'] ?>">Editar</a><button class="btn small" type="button" data-post="/promociones/<?= $p['id'] ?>/eliminar" data-confirmar="¿Eliminar la promoción “<?= e($p['titulo']) ?>”? Los envíos ya hechos no se deshacen.">Eliminar</button><?php if ($et === 'Terminada'): ?><button class="btn small primary" type="button" disabled title="La promoción ya terminó">Enviar a los afiliados</button><?php else: ?><a class="btn small primary" href="/promociones?enviar=<?= $p['id'] ?>">Enviar a los afiliados</a><?php endif ?></div></div>
<?php endforeach ?>
</div></div>
<script src="/assets/promociones.js?v=83"></script>
