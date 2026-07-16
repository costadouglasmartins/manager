<?php
$movement = $movement ?? [];
$value = static function (string $field, string $default = '') use ($movement): string {
	return (string) ($movement[$field] ?? $default);
};

$productLabel = static function (array $product): string {
	$sku = trim((string) ($product['sku'] ?? ''));
	$name = trim((string) ($product['name'] ?? ''));
	$stock = number_format((float) ($product['stock_quantity'] ?? 0), 3, ',', '.');
	$unit = trim((string) ($product['unit'] ?? 'UN'));
	return ($sku !== '' ? $sku . ' - ' : '') . $name . ' - estoque: ' . $stock . ' ' . $unit;
};

$observations = $value('observations');
$editorHtml = trim(preg_replace('/<([a-z][a-z0-9]*)\b[^>]*>/i', '<$1>', strip_tags($observations, '<b><strong><i><em><u><br><p><div><ul><ol><li>')) ?? '');
?>
<link rel="stylesheet" href="<?= url('assets/css/rich_text_editor.css?v=1'); ?>">
<link rel="stylesheet" href="<?= url('assets/css/product_stocks_movements/edit.css?v=1'); ?>">

<div class="store-page app-page">
  <div class="store-page-header app-page-header">
    <div>
      <span class="store-page-kicker app-page-kicker"><i class="bi bi-pencil-square"></i> Loja</span>
      <h5>Editar movimentação de estoque</h5>
    </div>
  </div>

  <?php if (!empty($formError)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($formError, ENT_QUOTES, 'UTF-8'); ?></div>
  <?php endif; ?>

  <form action="<?= url('product_stock_movement/update/' . (int) ($movement['id'] ?? 0)); ?>" method="POST" class="needs-validation store-form" novalidate>
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
          <?php
          $movementDateValue = $value('movement_date', date('Y-m-d\TH:i'));
          if (preg_match('/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}(:\d{2})?$/', $movementDateValue))
            {
            $movementDateValue = str_replace(' ', 'T', substr($movementDateValue, 0, 16));
            }
          ?>
          <input class="form-control" id="movement_date" name="movement_date" type="datetime-local" value="<?= htmlspecialchars($movementDateValue, ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="col-12 col-md-3">
          <label class="form-label" for="reference">Referência</label>
          <input class="form-control" id="reference" maxlength="120" name="reference" type="text" value="<?= htmlspecialchars($value('reference'), ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="col-12">
          <label class="form-label" for="observationsEditor">Observações</label>
          <div class="rich-editor" data-rich-editor>
            <div class="rich-editor-toolbar" role="toolbar">
              <button class="btn btn-outline-secondary btn-sm" data-editor-command="bold" type="button"><i class="bi bi-type-bold"></i></button>
              <button class="btn btn-outline-secondary btn-sm" data-editor-command="italic" type="button"><i class="bi bi-type-italic"></i></button>
              <button class="btn btn-outline-secondary btn-sm" data-editor-command="underline" type="button"><i class="bi bi-type-underline"></i></button>
            </div>
            <div class="form-control rich-editor-surface" contenteditable="true" data-rich-editor-surface id="observationsEditor"><?= $editorHtml; ?></div>
            <textarea class="d-none" data-rich-editor-source name="observations"><?= htmlspecialchars($observations, ENT_QUOTES, 'UTF-8'); ?></textarea>
          </div>
        </div>
      </div>
    </section>

    <div class="form-actions">
      <a href="<?= url('product_stock_movement'); ?>" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Cancelar</a>
      <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> Salvar</button>
    </div>
  </form>
</div>

<script src="/assets/richtexteditor/rich_text_editor.js?v=2"></script>
<script src="<?= url('assets/js/product_stocks_movements/edit.js?v=1'); ?>"></script>
