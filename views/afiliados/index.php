<?php
/** CU-05 · Padrón de afiliados. @var array $filas @var array $cuentas @var array $porPlan @var string $q @var string $estado @var int $abrir */
use App\Domain\Catalogo;

$tot = array_sum($porPlan);
$partes = [];
foreach (Catalogo::planes() as $p) {
    if (!empty($porPlan[$p['id']])) {
        $partes[] = $porPlan[$p['id']] . ' en el ' . e($p['nombre']);
    }
}
?>
<?= head('Afiliados', "$tot afiliados activos" . ($partes ? ': ' . implode(', ', $partes) : '') . '. Tocá una fila para ver o editar los datos.', '<a class="btn primary" href="/altas">Nueva alta</a>') ?>
<div class="row">
  <input id="q" type="search" placeholder="Buscar por nombre, CUIT/DNI o N° de afiliado" value="<?= e($q) ?>" style="flex:1 1 260px" autofocus>
  <select id="festado" aria-label="Estado"><option value="activo"<?= sel($estado, 'activo') ?>>Activos</option><option value="baja"<?= sel($estado, 'baja') ?>>Bajas</option><option value=""<?= sel($estado, '') ?>>Todos</option></select>
</div>
<div class="tbl-wrap"><table><thead><tr><th>N°</th><th>Nombre</th><th>CUIT/DNI</th><th>Categoría</th><th class="r">Cuota</th><th>Paga con</th><th>Alta</th><th>Cuenta</th></tr></thead>
<tbody id="tb-af"><?= \App\Core\View::parcial('afiliados/_filas', ['filas' => $filas, 'cuentas' => $cuentas]) ?></tbody></table></div>
<script src="/assets/afiliados.js?v=83" defer></script>
<?php if ($abrir): ?><span hidden data-abrir-ficha="<?= (int)$abrir ?>"></span><?php endif ?>
