<?php
$filters = $filters ?? ['id' => '', 'product_category_id' => '', 'sku' => '', 'name' => '', 'active' => '', 'stock_status' => ''];
$page = $page ?? 1;
$totalPages = $totalPages ?? 1;
$total = $total ?? (is_array($products) ? count($products) : 0);

$queryFilters = http_build_query([
	'id'                  => $filters['id'],
	'product_category_id' => $filters['product_category_id'],
	'sku'                 => $filters['sku'],
	'name'                => $filters['name'],
	'active'              => $filters['active'],
	'stock_status'        => $filters['stock_status'],
]);

$formatMoney = static function ($value): string {
	return 'R$ ' . number_format((float) $value, 2, ',', '.');
};

$formatQuantity = static function ($value): string {
	return number_format((float) $value, 3, ',', '.');
};

$stockClass = static function (array $row): string {
	$quantity = (float) ($row['stock_quantity'] ?? 0);
	$minimum = (float) ($row['minimum_stock'] ?? 0);
	if ($quantity <= 0)
		{
		return 'stock-badge stock-badge-out';
		}

	if ($minimum > 0 && $quantity <= $minimum)
		{
		return 'stock-badge stock-badge-low';
		}

	return 'stock-badge stock-badge-ok';
};
?>
<link rel="stylesheet" href="<?= url('assets/css/products/index.css?v=1'); ?>">

<div class="store-page">
  <div class="store-page-header">
    <div>
      <span class="store-page-kicker"><i class="bi bi-box-seam"></i> Loja</span>
      <h5>Produtos</h5>
    </div>
  </div>

  <form action="<?= url('product'); ?>" class="row g-3 mb-3" method="get">
    <div class="col-12 col-lg-9">
      <div class="row g-3">
        <div class="col-12 col-md-2">
          <input class="form-control" id="id" name="id" type="text" placeholder="Código" value="<?= htmlspecialchars($filters['id'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="col-12 col-md-4">
          <select class="form-select" id="product_category_id" name="product_category_id">
            <option value="" <?= $filters['product_category_id'] === '' ? 'selected' : ''; ?>>Todas as categorias</option>
            <?php foreach ($categories as $category): ?>
              <option value="<?= (int) $category['id']; ?>" <?= ((string) $filters['product_category_id'] === (string) $category['id']) ? 'selected' : ''; ?>>
                <?= htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12 col-md-3">
          <input class="form-control" id="sku" maxlength="60" name="sku" type="text" placeholder="SKU" value="<?= htmlspecialchars($filters['sku'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="col-12 col-md-3">
          <select class="form-select" id="stock_status" name="stock_status">
            <option value="" <?= $filters['stock_status'] === '' ? 'selected' : ''; ?>>Todo estoque</option>
            <option value="AVAILABLE" <?= $filters['stock_status'] === 'AVAILABLE' ? 'selected' : ''; ?>>Com estoque</option>
            <option value="LOW" <?= $filters['stock_status'] === 'LOW' ? 'selected' : ''; ?>>Estoque baixo</option>
            <option value="OUT" <?= $filters['stock_status'] === 'OUT' ? 'selected' : ''; ?>>Sem estoque</option>
          </select>
        </div>

        <div class="col-12 col-md-8">
          <input class="form-control" id="name" maxlength="160" name="name" type="text" placeholder="Nome do produto" value="<?= htmlspecialchars($filters['name'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="col-12 col-md-4">
          <select class="form-select" id="active" name="active">
            <option value="" <?= $filters['active'] === '' ? 'selected' : ''; ?>>Todos</option>
            <option value="1" <?= $filters['active'] === '1' ? 'selected' : ''; ?>>Ativos</option>
            <option value="0" <?= $filters['active'] === '0' ? 'selected' : ''; ?>>Inativos</option>
          </select>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-3">
      <div class="d-flex flex-column gap-2 align-items-center align-items-lg-end">
        <button type="submit" class="btn btn-primary w-100">Pesquisar</button>
        <a href="<?= url('product'); ?>" class="btn btn-secondary w-100">Limpar</a>
        <?php if (authCan('product.create')): ?>
          <a href="<?= url('product/create'); ?>" class="btn btn-success w-100">Novo(a)</a>
        <?php endif; ?>
      </div>
    </div>
  </form>

  <hr>

  <div class="table-responsive">
    <table class="table table-striped table-hover align-middle">
      <thead>
        <tr>
          <th>Imagem</th>
          <th>#</th>
          <th>Produto</th>
          <th>Categoria</th>
          <th>Preço</th>
          <th>Estoque</th>
          <th>Status</th>
          <th class="text-end">Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($products)): ?>
          <?php foreach ($products as $row): ?>
            <tr>
              <td style="width:1%;">
                <div class="store-thumb">
                  <?php if (!empty($row['main_image_thumb_path'])): ?>
                    <img src="<?= url($row['main_image_thumb_path']); ?>" alt="<?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'); ?>">
                  <?php else: ?>
                    <span class="store-thumb-placeholder"><i class="bi bi-image"></i></span>
                  <?php endif; ?>
                </div>
              </td>
              <td style="width:1%;"><?= (int) $row['id']; ?></td>
              <td>
                <div class="fw-semibold"><?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'); ?></div>
                <small class="text-muted"><?= htmlspecialchars((string) ($row['sku'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></small>
              </td>
              <td><?= htmlspecialchars((string) ($row['product_category_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?= htmlspecialchars($formatMoney($row['sale_price'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></td>
              <td>
                <span class="<?= htmlspecialchars($stockClass($row), ENT_QUOTES, 'UTF-8'); ?>">
                  <?= htmlspecialchars($formatQuantity($row['stock_quantity'] ?? 0), ENT_QUOTES, 'UTF-8'); ?>
                </span>
                <small class="text-muted"><?= htmlspecialchars((string) ($row['unit'] ?? 'UN'), ENT_QUOTES, 'UTF-8'); ?></small>
              </td>
              <td><?= ((int) $row['active'] === 1) ? 'Ativo' : 'Inativo'; ?></td>
              <td style="width:1%;" class="text-nowrap text-end">
                <?php if (authCan('product.edit')): ?>
                  <a href="<?= url('product/edit/' . $row['id']); ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Editar"><i class="bi bi-pencil-square"></i></a>
                <?php endif; ?>
                <?php if (authCan('product.informations')): ?>
                  <a href="<?= url('product/informations/' . $row['id']); ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Saiba mais"><i class="bi bi-search"></i></a>
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
    <?= paginationLinks('product', $page, $totalPages, $queryFilters, 2, 'Anterior', 'Próxima'); ?>
  <?php endif; ?>
</div>

<script src="<?= url('assets/js/products/index.js?v=1'); ?>"></script>
