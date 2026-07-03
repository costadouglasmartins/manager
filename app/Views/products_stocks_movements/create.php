<?php
$formData = $formData ?? [];
$value = static function (string $field, string $default = '') use ($formData): string {
	return (string) ($formData[$field] ?? $default);
};

$productLabel = static function (array $product): string {
	$sku = trim((string) ($product['sku'] ?? ''));
	$name = trim((string) ($product['name'] ?? ''));
	$stock = number_format((float) ($product['stock_quantity'] ?? 0), 3, ',', '.');
	$unit = trim((string) ($product['unit'] ?? 'UN'));
	return ($sku !== '' ? $sku . ' - ' : '') . $name . ' - estoque: ' . $stock . ' ' . $unit;
};
?>
<link rel="stylesheet" href="<?= url('assets/css/products_stocks_movements/create.css?v=1'); ?>">

<div class="store-page">
  <div class="store-page-header">
    <div>
      <span class="store-page-kicker"><i class="bi bi-arrow-left-right"></i> Loja</span>
      <h5>Criar movimentação de estoque</h5>
    </div>
  </div>

  <?php if (!empty($formError)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($formError, ENT_QUOTES, 'UTF-8'); ?></div>
  <?php endif; ?>

  <form action="<?= url('product_stock_movement/store'); ?>" method="POST" class="needs-validation store-form" novalidate>
    <section class="store-section">
      <div class="row g-3">
        <div class="col-12 col-lg-6">
          <label class="form-label" for="product_id">Produto</label>
          <select class="form-select" id="product_id" name="product_id" required>
            <option value="">Selecione</option>
            <?php foreach ($products as $product): ?>
              <option value="<?= (int) $product['id']; ?>" <?= ($value('product_id') === (string) $product['id']) ? 'selected' : ''; ?>>
                <?= htmlspecialchars($productLabel($product), ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="invalid-feedback">Selecione o produto.</div>
        </div>

        <div class="col-12 col-md-3">
          <label class="form-label" for="movement_type">Tipo</label>
          <select class="form-select" id="movement_type" name="movement_type" required>
            <option value="IN" <?= ($value('movement_type', 'IN') === 'IN') ? 'selected' : ''; ?>>Entrada</option>
            <option value="OUT" <?= ($value('movement_type', 'IN') === 'OUT') ? 'selected' : ''; ?>>Saída</option>
            <option value="ADJUSTMENT" <?= ($value('movement_type', 'IN') === 'ADJUSTMENT') ? 'selected' : ''; ?>>Ajuste</option>
          </select>
        </div>

        <div class="col-12 col-md-3">
          <label class="form-label" data-stock-quantity-label for="quantity">Quantidade</label>
          <input class="form-control" id="quantity" inputmode="decimal" name="quantity" required type="text" value="<?= htmlspecialchars($value('quantity'), ENT_QUOTES, 'UTF-8'); ?>">
          <div class="invalid-feedback">Informe a quantidade.</div>
        </div>

        <div class="col-12 col-md-3">
          <label class="form-label" for="unit_cost">Custo unitário</label>
          <input class="form-control" id="unit_cost" inputmode="decimal" name="unit_cost" type="text" value="<?= htmlspecialchars($value('unit_cost'), ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="col-12 col-md-3">
          <label class="form-label" for="unit_price">Preço unitário</label>
          <input class="form-control" id="unit_price" inputmode="decimal" name="unit_price" type="text" value="<?= htmlspecialchars($value('unit_price'), ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="col-12 col-md-3">
          <label class="form-label" for="movement_date">Data</label>
          <input class="form-control" id="movement_date" name="movement_date" type="datetime-local" value="<?= htmlspecialchars($value('movement_date', date('Y-m-d\TH:i')), ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="col-12 col-md-3">
          <label class="form-label" for="reference">Referência</label>
          <input class="form-control" id="reference" maxlength="120" name="reference" type="text" value="<?= htmlspecialchars($value('reference'), ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="col-12">
          <label class="form-label" for="observations">Observações</label>
          <textarea class="form-control" id="observations" maxlength="255" name="observations" rows="3"><?= htmlspecialchars($value('observations'), ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>
      </div>
    </section>

    <div class="form-actions">
      <a href="<?= url('product_stock_movement'); ?>" class="btn btn-secondary">Cancelar</a>
      <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> Salvar</button>
    </div>
  </form>
</div>

<script src="<?= url('assets/js/products_stocks_movements/create.js?v=1'); ?>"></script>
