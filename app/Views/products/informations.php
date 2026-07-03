<?php
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

$formatMoney = static function ($value): string {
	return 'R$ ' . number_format((float) $value, 2, ',', '.');
};

$formatQuantity = static function ($value): string {
	return number_format((float) $value, 3, ',', '.');
};

$movementTypeLabel = static function (string $type): string {
	return [
		'IN'         => 'Entrada',
		'OUT'        => 'Saída',
		'ADJUSTMENT' => 'Ajuste',
	][$type] ?? $type;
};
?>
<link rel="stylesheet" href="<?= url('assets/css/products/informations.css?v=1'); ?>">

<div class="store-page">
  <div class="store-page-header">
    <div>
      <span class="store-page-kicker"><i class="bi bi-search"></i> Loja</span>
      <h5>Dados do produto</h5>
    </div>
  </div>

  <section class="store-section">
    <div class="table-responsive">
      <table class="table table-hover align-middle">
        <tbody>
          <tr>
            <th class="text-muted" style="width: 220px;">Código</th>
            <td><?= (int) $product['id']; ?></td>
          </tr>
          <tr>
            <th class="text-muted">Nome</th>
            <td><?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?></td>
          </tr>
          <tr>
            <th class="text-muted">Categoria</th>
            <td><?= htmlspecialchars((string) ($product['product_category_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
          </tr>
          <tr>
            <th class="text-muted">SKU</th>
            <td><?= htmlspecialchars((string) ($product['sku'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
          </tr>
          <tr>
            <th class="text-muted">Código de barras</th>
            <td><?= htmlspecialchars((string) ($product['barcode'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
          </tr>
          <tr>
            <th class="text-muted">Preço de venda</th>
            <td><?= htmlspecialchars($formatMoney($product['sale_price'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></td>
          </tr>
          <tr>
            <th class="text-muted">Custo</th>
            <td><?= htmlspecialchars($formatMoney($product['cost_price'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></td>
          </tr>
          <tr>
            <th class="text-muted">Estoque</th>
            <td><?= htmlspecialchars($formatQuantity($product['stock_quantity'] ?? 0), ENT_QUOTES, 'UTF-8'); ?> <?= htmlspecialchars((string) ($product['unit'] ?? 'UN'), ENT_QUOTES, 'UTF-8'); ?></td>
          </tr>
          <tr>
            <th class="text-muted">Estoque mínimo</th>
            <td><?= htmlspecialchars($formatQuantity($product['minimum_stock'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></td>
          </tr>
          <tr>
            <th class="text-muted">Status</th>
            <td><?= ((int) $product['active'] === 1) ? 'Ativo' : 'Inativo'; ?></td>
          </tr>
          <tr>
            <th class="text-muted">Descrição</th>
            <td><?= nl2br(htmlspecialchars((string) ($product['description'] ?? ''), ENT_QUOTES, 'UTF-8')); ?></td>
          </tr>
          <tr>
            <th class="text-muted">Criado em</th>
            <td><?= htmlspecialchars($formatDateTime($product['created'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
          </tr>
          <tr>
            <th class="text-muted">Alterado em</th>
            <td><?= htmlspecialchars($formatDateTime($product['modified'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>

  <section class="store-section">
    <div class="store-subheader">
      <h6>Imagens</h6>
    </div>

    <?php if (!empty($images)): ?>
      <div class="product-image-grid">
        <?php foreach ($images as $image): ?>
          <a class="product-image-item" href="<?= url($image['file_path']); ?>" target="_blank" rel="noopener">
            <img src="<?= url($image['thumb_path'] ?: $image['file_path']); ?>" alt="<?= htmlspecialchars((string) ($image['original_name'] ?? $product['name']), ENT_QUOTES, 'UTF-8'); ?>">
          </a>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="text-muted mb-0">Nenhuma imagem cadastrada.</p>
    <?php endif; ?>
  </section>

  <section class="store-section">
    <div class="store-subheader">
      <h6>Últimas movimentações</h6>
    </div>

    <div class="table-responsive">
      <table class="table table-striped table-hover align-middle">
        <thead>
          <tr>
            <th>#</th>
            <th>Tipo</th>
            <th>Quantidade</th>
            <th>Saldo anterior</th>
            <th>Novo saldo</th>
            <th>Data</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($stockMovements)): ?>
            <?php foreach ($stockMovements as $movement): ?>
              <tr>
                <td style="width:1%;"><?= (int) $movement['id']; ?></td>
                <td><?= htmlspecialchars($movementTypeLabel((string) $movement['movement_type']), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?= htmlspecialchars($formatQuantity($movement['quantity'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?= htmlspecialchars($formatQuantity($movement['previous_quantity'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?= htmlspecialchars($formatQuantity($movement['new_quantity'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?= htmlspecialchars($formatDateTime($movement['movement_date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="6" class="text-center">Nenhuma movimentação encontrada.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <div class="form-actions">
    <a href="<?= url('product'); ?>" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
    <?php if (authCan('product.edit')): ?>
      <a href="<?= url('product/edit/' . $product['id']); ?>" class="btn btn-primary"><i class="bi bi-pencil-square"></i> Editar</a>
    <?php endif; ?>
  </div>
</div>

<script src="<?= url('assets/js/products/informations.js?v=1'); ?>"></script>
