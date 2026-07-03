<?php
$filters = $filters ?? ['id' => '', 'product_id' => '', 'movement_type' => '', 'reference' => ''];
$page = $page ?? 1;
$totalPages = $totalPages ?? 1;
$total = $total ?? (is_array($movements) ? count($movements) : 0);

$queryFilters = http_build_query([
	'id'            => $filters['id'],
	'product_id'    => $filters['product_id'],
	'movement_type' => $filters['movement_type'],
	'reference'     => $filters['reference'],
]);

$formatQuantity = static function ($value): string {
	return number_format((float) $value, 3, ',', '.');
};

$formatDateTime = static function ($value): string {
	$value = trim((string) $value);
	if ($value === '' || $value === '0000-00-00 00:00:00')
		{
		return '';
		}

	try
		{
		return (new DateTimeImmutable($value))->format('d/m/Y H:i');
		}
	catch (Throwable $e)
		{
		return $value;
		}
};

$movementTypeLabel = static function (string $type): string {
	return [
		'IN'         => 'Entrada',
		'OUT'        => 'Saída',
		'ADJUSTMENT' => 'Ajuste',
	][$type] ?? $type;
};

$productLabel = static function (array $product): string {
	$sku = trim((string) ($product['sku'] ?? ''));
	$name = trim((string) ($product['name'] ?? ''));
	return ($sku !== '' ? $sku . ' - ' : '') . $name;
};
?>
<link rel="stylesheet" href="<?= url('assets/css/products_stocks_movements/index.css?v=1'); ?>">

<div class="store-page">
  <div class="store-page-header">
    <div>
      <span class="store-page-kicker"><i class="bi bi-arrow-left-right"></i> Loja</span>
      <h5>Movimentações de estoque</h5>
    </div>
  </div>

  <?php if (!empty($formError)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars((string) $formError, ENT_QUOTES, 'UTF-8'); ?></div>
  <?php endif; ?>

  <form action="<?= url('product_stock_movement'); ?>" class="row g-3 mb-3" method="get">
    <div class="col-12 col-lg-9">
      <div class="row g-3">
        <div class="col-12 col-md-2">
          <input class="form-control" id="id" name="id" type="text" placeholder="Código" value="<?= htmlspecialchars($filters['id'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="col-12 col-md-5">
          <select class="form-select" id="product_id" name="product_id">
            <option value="" <?= $filters['product_id'] === '' ? 'selected' : ''; ?>>Todos os produtos</option>
            <?php foreach ($products as $product): ?>
              <option value="<?= (int) $product['id']; ?>" <?= ((string) $filters['product_id'] === (string) $product['id']) ? 'selected' : ''; ?>>
                <?= htmlspecialchars($productLabel($product), ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12 col-md-2">
          <select class="form-select" id="movement_type" name="movement_type">
            <option value="" <?= $filters['movement_type'] === '' ? 'selected' : ''; ?>>Todos</option>
            <option value="IN" <?= $filters['movement_type'] === 'IN' ? 'selected' : ''; ?>>Entrada</option>
            <option value="OUT" <?= $filters['movement_type'] === 'OUT' ? 'selected' : ''; ?>>Saída</option>
            <option value="ADJUSTMENT" <?= $filters['movement_type'] === 'ADJUSTMENT' ? 'selected' : ''; ?>>Ajuste</option>
          </select>
        </div>

        <div class="col-12 col-md-3">
          <input class="form-control" id="reference" maxlength="120" name="reference" type="text" placeholder="Referência" value="<?= htmlspecialchars($filters['reference'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-3">
      <div class="d-flex flex-column gap-2 align-items-center align-items-lg-end">
        <button type="submit" class="btn btn-primary w-100">Pesquisar</button>
        <a href="<?= url('product_stock_movement'); ?>" class="btn btn-secondary w-100">Limpar</a>
        <?php if (authCan('product_stock_movement.create')): ?>
          <a href="<?= url('product_stock_movement/create'); ?>" class="btn btn-success w-100">Novo(a)</a>
        <?php endif; ?>
      </div>
    </div>
  </form>

  <div class="d-flex flex-wrap gap-2 mb-3">
    <?= pdfExportButton('product_stock_movement', $queryFilters); ?>
    <?= excelExportButton('product_stock_movement', $queryFilters); ?>
  </div>

  <hr>

  <div class="table-responsive">
    <table class="table table-striped table-hover align-middle">
      <thead>
        <tr>
          <th>#</th>
          <th>Produto</th>
          <th>Tipo</th>
          <th>Quantidade</th>
          <th>Saldo anterior</th>
          <th>Novo saldo</th>
          <th>Data</th>
          <th class="text-end">Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($movements)): ?>
          <?php foreach ($movements as $row): ?>
            <tr>
              <td style="width:1%;"><?= (int) $row['id']; ?></td>
              <td>
                <div class="fw-semibold"><?= htmlspecialchars($row['product_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                <small class="text-muted"><?= htmlspecialchars((string) ($row['product_sku'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></small>
              </td>
              <td><?= htmlspecialchars($movementTypeLabel((string) $row['movement_type']), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?= htmlspecialchars($formatQuantity($row['quantity'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?= htmlspecialchars($formatQuantity($row['previous_quantity'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?= htmlspecialchars($formatQuantity($row['new_quantity'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?= htmlspecialchars($formatDateTime($row['movement_date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td style="width:1%;" class="text-nowrap text-end">
                <?php if (authCan('product_stock_movement.delete')): ?>
                  <a href="#"
                     class="js-stock-movement-delete-link text-danger"
                     data-bs-toggle="modal"
                     data-bs-target="#productStockMovementDeleteModal"
                     data-movement-id="<?= (int) $row['id']; ?>"
                     data-movement-delete-url="<?= url('product_stock_movement/delete/' . $row['id']); ?>"
                     data-bs-content="Excluir"
                     data-bs-trigger="hover focus"
                     role="button"
                     aria-label="Excluir movimentação">
                    <i class="bi bi-trash"></i>
                  </a>
                <?php endif; ?>
                <?php if (authCan('product_stock_movement.edit')): ?>
                  <a href="<?= url('product_stock_movement/edit/' . $row['id']); ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Editar"><i class="bi bi-pencil-square"></i></a>
                <?php endif; ?>
                <?php if (authCan('product_stock_movement.informations')): ?>
                  <a href="<?= url('product_stock_movement/informations/' . $row['id']); ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Saiba mais"><i class="bi bi-search"></i></a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="8" class="text-center">Nenhum registro encontrado.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <p class="text-muted"><?= (int) $total; ?> registro(s) encontrado(s)</p>

  <?php if ($totalPages > 1): ?>
    <?= paginationLinks('product_stock_movement', $page, $totalPages, $queryFilters, 2, 'Anterior', 'Próxima'); ?>
  <?php endif; ?>
</div>

<?php if (authCan('product_stock_movement.delete')): ?>
  <div class="modal fade" id="productStockMovementDeleteModal" tabindex="-1" aria-labelledby="productStockMovementDeleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <form id="productStockMovementDeleteForm" method="POST" action="">
          <div class="modal-header">
            <h5 class="modal-title" id="productStockMovementDeleteModalLabel">Confirmar exclusão</h5>
            <a href="#" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></a>
          </div>
          <div class="modal-body">
            <p class="mb-0">Deseja realmente excluir a movimentação <strong id="productStockMovementDeleteName">#</strong>?</p>
          </div>
          <div class="modal-footer">
            <a href="#" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</a>
            <button type="submit" class="btn btn-danger">Excluir</button>
          </div>
        </form>
      </div>
    </div>
  </div>
<?php endif; ?>

<script src="<?= url('assets/js/products_stocks_movements/index.js?v=2'); ?>"></script>
