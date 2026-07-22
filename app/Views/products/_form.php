<?php
$formData = $formData ?? [];
$product = $product ?? [];
$images = $images ?? [];
$variations = $variations ?? [];
$units = $units ?? [];
$categorySchemas = $categorySchemas ?? [];
$attributeValues = $attributeValues ?? [];
$value = static function (string $field, string $default = '') use ($formData, $product): string {
	return (string) ($formData[$field] ?? $product[$field] ?? $default);
};
$selected = static function (string $field, $candidate, string $default = '') use ($value): string {
	return $value($field, $default) === (string) $candidate ? 'selected' : '';
};
$checked = static function (string $field, string $default = '0') use ($value): string {
	return $value($field, $default) === '1' ? 'checked' : '';
};
$description = $value('description');
$editorHtml = trim(preg_replace('/<([a-z][a-z0-9]*)\b[^>]*>/i', '<$1>', strip_tags($description, '<b><strong><i><em><u><br><p><div><ul><ol><li>')) ?? '');
$formAction = $isEdit ? url('product/update/' . (int) ($product['id'] ?? 0)) : url('product/store');
$dimensionUnits = $units['LENGTH'] ?? [];
$weightUnits = $units['WEIGHT'] ?? [];
$allSaleUnits = [];
foreach ($units as $unitGroup) foreach ($unitGroup as $unit) $allSaleUnits[] = $unit;
$variationValue = static function (array $variation, string $field, string $default = ''): string {
	return (string) ($variation[$field] ?? $default);
};
?>
<link rel="stylesheet" href="<?= url('assets/css/rich_text_editor.css?v=1'); ?>">
<link rel="stylesheet" href="<?= url('assets/css/products/create.css?v=3'); ?>">

<div class="store-page app-page">
  <div class="store-page-header app-page-header">
    <div>
      <span class="store-page-kicker app-page-kicker"><i class="bi <?= $isEdit ? 'bi-pencil-square' : 'bi-box-seam'; ?>" aria-hidden="true"></i> Produtos</span>
      <h5><?= $isEdit ? 'Editar produto' : 'Criar produto'; ?></h5>
    </div>
  </div>

  <?php if (!empty($formError)): ?>
    <div class="alert alert-danger" role="alert"><?= htmlspecialchars((string) $formError, ENT_QUOTES, 'UTF-8'); ?></div>
  <?php endif; ?>

  <form action="<?= $formAction; ?>" method="POST" enctype="multipart/form-data" class="needs-validation store-form js-product-form" <?= !$isEdit ? 'data-product-chunk-upload-url="' . url('product/upload-chunk') . '"' : ''; ?> novalidate>
    <section class="store-section">
      <div class="store-subheader"><h6>Identificação</h6></div>
      <div class="row g-3">
        <div class="col-12 col-lg-4">
          <label class="form-label" for="product_category_id">Categoria</label>
          <select class="form-select js-product-category" id="product_category_id" name="product_category_id" required>
            <option value="">Selecione</option>
            <?php foreach ($categories as $category): ?>
              <option value="<?= (int) $category['id']; ?>" <?= $selected('product_category_id', $category['id']); ?>><?= htmlspecialchars((string) $category['name'], ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
          <div class="invalid-feedback">Selecione a categoria.</div>
        </div>
        <div class="col-12 col-md-6 col-lg-4">
          <label class="form-label" for="sku">SKU</label>
          <input autocomplete="off" class="form-control" id="sku" maxlength="60" name="sku" type="text" value="<?= htmlspecialchars($value('sku'), ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="col-12 col-md-6 col-lg-4">
          <label class="form-label" for="barcode">Código de barras</label>
          <input autocomplete="off" class="form-control" id="barcode" maxlength="80" name="barcode" type="text" value="<?= htmlspecialchars($value('barcode'), ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="col-12 col-md-8">
          <label class="form-label" for="name">Nome</label>
          <input autocomplete="off" class="form-control" id="name" maxlength="160" name="name" required type="text" value="<?= htmlspecialchars($value('name'), ENT_QUOTES, 'UTF-8'); ?>">
          <div class="invalid-feedback">Informe o nome do produto.</div>
        </div>
        <div class="col-12 col-md-4">
          <label class="form-label" for="active">Status</label>
          <select class="form-select" id="active" name="active">
            <option value="1" <?= $selected('active', '1', '1'); ?>>Ativo</option>
            <option value="0" <?= $selected('active', '0', '1'); ?>>Inativo</option>
          </select>
        </div>
        <div class="col-12">
          <label class="form-label" for="descriptionEditor">Descrição</label>
          <div class="rich-editor" data-rich-editor>
            <div class="rich-editor-toolbar" role="toolbar" aria-label="Formatação da descrição">
              <button class="btn btn-outline-secondary btn-sm" data-editor-command="bold" type="button" aria-label="Negrito"><i class="bi bi-type-bold" aria-hidden="true"></i></button>
              <button class="btn btn-outline-secondary btn-sm" data-editor-command="italic" type="button" aria-label="Itálico"><i class="bi bi-type-italic" aria-hidden="true"></i></button>
              <button class="btn btn-outline-secondary btn-sm" data-editor-command="underline" type="button" aria-label="Sublinhado"><i class="bi bi-type-underline" aria-hidden="true"></i></button>
            </div>
            <div class="form-control rich-editor-surface" contenteditable="true" data-rich-editor-surface id="descriptionEditor"><?= $editorHtml; ?></div>
            <textarea class="d-none" data-rich-editor-source name="description"><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?></textarea>
          </div>
        </div>
      </div>
    </section>

    <section class="store-section">
      <div class="store-subheader"><h6>Venda e estoque</h6></div>
      <div class="row g-3">
        <div class="col-12 col-md-3">
          <label class="form-label" for="cost_price">Custo</label>
          <div class="input-group"><span class="input-group-text">R$</span><input class="form-control" id="cost_price" inputmode="decimal" name="cost_price" type="text" value="<?= htmlspecialchars($value('cost_price', '0,00'), ENT_QUOTES, 'UTF-8'); ?>"></div>
        </div>
        <div class="col-12 col-md-3">
          <label class="form-label" for="sale_price">Preço de venda</label>
          <div class="input-group"><span class="input-group-text">R$</span><input class="form-control" id="sale_price" inputmode="decimal" name="sale_price" type="text" value="<?= htmlspecialchars($value('sale_price', '0,00'), ENT_QUOTES, 'UTF-8'); ?>"></div>
        </div>
        <div class="col-12 col-md-3">
          <label class="form-label" for="sale_unit_id">Unidade de venda</label>
          <select class="form-select" id="sale_unit_id" name="sale_unit_id" required>
            <option value="">Selecione</option>
            <?php foreach ($allSaleUnits as $unit): ?>
              <option value="<?= (int) $unit['id']; ?>" <?= $selected('sale_unit_id', $unit['id']); ?>><?= htmlspecialchars($unit['name'] . ' (' . $unit['symbol'] . ')', ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
          <div class="invalid-feedback">Selecione a unidade de venda.</div>
        </div>
        <div class="col-12 col-md-3">
          <label class="form-label" for="sale_quantity_step">Incremento de venda</label>
          <input class="form-control" id="sale_quantity_step" inputmode="decimal" name="sale_quantity_step" required type="text" value="<?= htmlspecialchars($value('sale_quantity_step', '1'), ENT_QUOTES, 'UTF-8'); ?>">
          <div class="form-text">Ex.: 1 por unidade ou 0,5 por metro.</div>
        </div>
        <div class="col-12">
          <input name="track_stock" type="hidden" value="0">
          <div class="form-check form-switch">
            <input class="form-check-input js-product-track-stock" id="track_stock" name="track_stock" type="checkbox" value="1" <?= $checked('track_stock', '1'); ?>>
            <label class="form-check-label" for="track_stock">Controlar estoque deste produto</label>
          </div>
        </div>
        <div class="col-12 col-md-4 js-product-stock-field">
          <label class="form-label" for="minimum_stock">Estoque mínimo</label>
          <input class="form-control" id="minimum_stock" inputmode="decimal" name="minimum_stock" type="text" value="<?= htmlspecialchars($value('minimum_stock', '0'), ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <?php if (!$isEdit): ?>
          <div class="col-12 col-md-4 js-product-stock-field">
            <label class="form-label" for="initial_stock">Estoque inicial</label>
            <input class="form-control" id="initial_stock" inputmode="decimal" name="initial_stock" type="text" value="<?= htmlspecialchars($value('initial_stock', '0'), ENT_QUOTES, 'UTF-8'); ?>">
          </div>
        <?php else: ?>
          <div class="col-12 col-md-4 js-product-stock-field">
            <label class="form-label" for="stock_quantity">Estoque atual</label>
            <input class="form-control" id="stock_quantity" readonly type="text" value="<?= htmlspecialchars(number_format((float) ($product['stock_quantity'] ?? 0), 6, ',', '.'), ENT_QUOTES, 'UTF-8'); ?>">
            <div class="form-text">Altere o saldo por uma movimentação de estoque.</div>
          </div>
        <?php endif; ?>
      </div>
    </section>

    <section class="store-section">
      <div class="store-subheader">
        <div><h6>Variações</h6><small class="text-muted">Use para tamanho, cor, comprimento ou outra combinação que precise de SKU/preço/estoque próprio.</small></div>
        <button class="btn btn-outline-primary btn-sm js-add-product-variation" type="button"><i class="bi bi-plus-lg" aria-hidden="true"></i> Adicionar</button>
      </div>
      <div class="vstack gap-3 js-product-variations">
        <?php foreach ($variations as $index => $variation): ?>
          <div class="border rounded-3 p-3 js-product-variation-row">
            <input name="variations[<?= (int) $index; ?>][id]" type="hidden" value="<?= (int) ($variation['id'] ?? 0); ?>">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <strong>Variação</strong>
              <button class="btn btn-outline-danger btn-sm js-remove-product-variation" type="button" aria-label="Remover variação"><i class="bi bi-trash" aria-hidden="true"></i></button>
            </div>
            <div class="row g-3">
              <div class="col-12 col-lg-4"><label class="form-label">Nome</label><input class="form-control" maxlength="160" name="variations[<?= (int) $index; ?>][name]" type="text" value="<?= htmlspecialchars($variationValue($variation, 'name'), ENT_QUOTES, 'UTF-8'); ?>"></div>
              <div class="col-12 col-md-6 col-lg-2"><label class="form-label">SKU</label><input class="form-control" maxlength="60" name="variations[<?= (int) $index; ?>][sku]" type="text" value="<?= htmlspecialchars($variationValue($variation, 'sku'), ENT_QUOTES, 'UTF-8'); ?>"></div>
              <div class="col-12 col-md-6 col-lg-2"><label class="form-label">Código de barras</label><input class="form-control" maxlength="80" name="variations[<?= (int) $index; ?>][barcode]" type="text" value="<?= htmlspecialchars($variationValue($variation, 'barcode'), ENT_QUOTES, 'UTF-8'); ?>"></div>
              <div class="col-12 col-md-6 col-lg-2"><label class="form-label">Custo</label><input class="form-control" inputmode="decimal" name="variations[<?= (int) $index; ?>][cost_price]" type="text" value="<?= htmlspecialchars($variationValue($variation, 'cost_price'), ENT_QUOTES, 'UTF-8'); ?>" placeholder="herda"></div>
              <div class="col-12 col-md-6 col-lg-2"><label class="form-label">Preço</label><input class="form-control" inputmode="decimal" name="variations[<?= (int) $index; ?>][sale_price]" type="text" value="<?= htmlspecialchars($variationValue($variation, 'sale_price'), ENT_QUOTES, 'UTF-8'); ?>" placeholder="herda"></div>
              <div class="col-12 col-md-4">
                <input name="variations[<?= (int) $index; ?>][track_stock]" type="hidden" value="0">
                <label class="form-check"><input class="form-check-input js-product-variation-track-stock" name="variations[<?= (int) $index; ?>][track_stock]" type="checkbox" value="1" <?= ((int) ($variation['track_stock'] ?? 0) === 1) ? 'checked' : ''; ?>><span class="form-check-label">Controlar estoque</span></label>
              </div>
              <div class="col-12 col-md-4 js-product-variation-stock-field"><label class="form-label">Estoque</label><input class="form-control" inputmode="decimal" name="variations[<?= (int) $index; ?>][stock_quantity]" type="text" value="<?= htmlspecialchars($variationValue($variation, 'stock_quantity', '0'), ENT_QUOTES, 'UTF-8'); ?>"></div>
              <div class="col-12 col-md-4 js-product-variation-stock-field"><label class="form-label">Mínimo</label><input class="form-control" inputmode="decimal" name="variations[<?= (int) $index; ?>][minimum_stock]" type="text" value="<?= htmlspecialchars($variationValue($variation, 'minimum_stock', '0'), ENT_QUOTES, 'UTF-8'); ?>"></div>
              <div class="col-12 col-md-4"><label class="form-label">Status</label><select class="form-select" name="variations[<?= (int) $index; ?>][active]"><option value="1" <?= $variationValue($variation, 'active', '1') === '1' ? 'selected' : ''; ?>>Ativa</option><option value="0" <?= $variationValue($variation, 'active', '1') === '0' ? 'selected' : ''; ?>>Inativa</option></select></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <p class="text-muted mb-0 js-product-variations-empty <?= empty($variations) ? '' : 'd-none'; ?>">Nenhuma variação adicionada.</p>
    </section>

    <section class="store-section">
      <div class="store-subheader"><h6>Dimensões e peso</h6><small class="text-muted">Preencha apenas o que fizer sentido para o produto.</small></div>
      <div class="row g-3">
        <?php foreach (['length_value' => 'Comprimento', 'width_value' => 'Largura', 'height_value' => 'Altura'] as $field => $label): ?>
          <div class="col-12 col-sm-6 col-lg-2">
            <label class="form-label" for="<?= $field; ?>"><?= $label; ?></label>
            <input class="form-control" id="<?= $field; ?>" inputmode="decimal" name="<?= $field; ?>" type="text" value="<?= htmlspecialchars($value($field), ENT_QUOTES, 'UTF-8'); ?>">
          </div>
        <?php endforeach; ?>
        <div class="col-12 col-sm-6 col-lg-2">
          <label class="form-label" for="dimension_unit_id">Unidade das dimensões</label>
          <select class="form-select" id="dimension_unit_id" name="dimension_unit_id"><option value="">Selecione</option><?php foreach ($dimensionUnits as $unit): ?><option value="<?= (int) $unit['id']; ?>" <?= $selected('dimension_unit_id', $unit['id']); ?>><?= htmlspecialchars($unit['symbol'], ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?></select>
        </div>
        <div class="col-12 col-sm-6 col-lg-2">
          <label class="form-label" for="weight_value">Peso</label>
          <input class="form-control" id="weight_value" inputmode="decimal" name="weight_value" type="text" value="<?= htmlspecialchars($value('weight_value'), ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="col-12 col-sm-6 col-lg-2">
          <label class="form-label" for="weight_unit_id">Unidade de peso</label>
          <select class="form-select" id="weight_unit_id" name="weight_unit_id"><option value="">Selecione</option><?php foreach ($weightUnits as $unit): ?><option value="<?= (int) $unit['id']; ?>" <?= $selected('weight_unit_id', $unit['id']); ?>><?= htmlspecialchars($unit['symbol'], ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?></select>
        </div>
      </div>
    </section>

    <section class="store-section d-none js-product-attributes-section" aria-live="polite">
      <div class="store-subheader"><h6>Características da categoria</h6></div>
      <div class="row g-3 js-product-attributes"></div>
      <p class="text-muted mb-0 d-none js-product-attributes-empty">Esta categoria não possui características adicionais.</p>
    </section>

    <section class="store-section">
      <div class="store-subheader"><h6>Imagens</h6></div>
      <?php if ($isEdit && !empty($images)): ?>
        <div class="product-image-grid mb-3">
          <?php foreach ($images as $image): ?>
            <div class="product-image-item">
              <img src="<?= url((string) $image['file_path']); ?>" alt="<?= htmlspecialchars((string) ($image['original_name'] ?? $product['name']), ENT_QUOTES, 'UTF-8'); ?>">
              <div class="product-image-controls">
                <label class="form-check"><input class="form-check-input" name="main_image_id" type="radio" value="<?= (int) $image['id']; ?>" <?= ((int) $image['is_main'] === 1) ? 'checked' : ''; ?>><span class="form-check-label">Principal</span></label>
                <label class="form-check"><input class="form-check-input" name="remove_images[]" type="checkbox" value="<?= (int) $image['id']; ?>"><span class="form-check-label">Remover</span></label>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php elseif ($isEdit): ?><p class="text-muted">Nenhuma imagem cadastrada.</p><?php endif; ?>
      <label class="form-label" for="images"><?= $isEdit ? 'Adicionar imagens' : 'Imagens do produto'; ?></label>
      <input accept="image/jpeg,image/png,image/webp" class="form-control" data-product-images-input id="images" multiple name="images[]" type="file">
      <?php if (!$isEdit): ?>
        <div class="product-upload-progress d-none" data-product-upload-progress><div class="product-upload-progress-header"><span data-product-upload-status>Preparando upload...</span><span data-product-upload-details>0 de 0 MB</span></div><div class="progress" aria-label="Progresso do upload" aria-valuemax="100" aria-valuemin="0" aria-valuenow="0" role="progressbar"><div class="progress-bar progress-bar-striped progress-bar-animated fw-bold text-white" data-product-upload-progress-bar>0%</div></div></div>
        <div class="alert alert-danger d-none" data-product-upload-error></div>
      <?php endif; ?>
      <div class="product-upload-preview" data-product-upload-preview></div>
    </section>

    <div class="form-actions">
      <a href="<?= url('product'); ?>" class="btn btn-secondary"><i class="bi bi-arrow-left" aria-hidden="true"></i> Cancelar</a>
      <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg" aria-hidden="true"></i> Salvar</button>
    </div>
  </form>
</div>

<template id="productVariationTemplate">
  <div class="border rounded-3 p-3 js-product-variation-row">
    <div class="d-flex justify-content-between align-items-center mb-3"><strong>Variação</strong><button class="btn btn-outline-danger btn-sm js-remove-product-variation" type="button" aria-label="Remover variação"><i class="bi bi-trash" aria-hidden="true"></i></button></div>
    <div class="row g-3">
      <div class="col-12 col-lg-4"><label class="form-label">Nome</label><input class="form-control" data-variation-field="name" maxlength="160" type="text"></div>
      <div class="col-12 col-md-6 col-lg-2"><label class="form-label">SKU</label><input class="form-control" data-variation-field="sku" maxlength="60" type="text"></div>
      <div class="col-12 col-md-6 col-lg-2"><label class="form-label">Código de barras</label><input class="form-control" data-variation-field="barcode" maxlength="80" type="text"></div>
      <div class="col-12 col-md-6 col-lg-2"><label class="form-label">Custo</label><input class="form-control" data-variation-field="cost_price" inputmode="decimal" placeholder="herda" type="text"></div>
      <div class="col-12 col-md-6 col-lg-2"><label class="form-label">Preço</label><input class="form-control" data-variation-field="sale_price" inputmode="decimal" placeholder="herda" type="text"></div>
      <div class="col-12 col-md-4"><input data-variation-field="track_stock_hidden" type="hidden" value="0"><label class="form-check"><input class="form-check-input js-product-variation-track-stock" data-variation-field="track_stock" type="checkbox" value="1"><span class="form-check-label">Controlar estoque</span></label></div>
      <div class="col-12 col-md-4 js-product-variation-stock-field"><label class="form-label">Estoque</label><input class="form-control" data-variation-field="stock_quantity" inputmode="decimal" type="text" value="0"></div>
      <div class="col-12 col-md-4 js-product-variation-stock-field"><label class="form-label">Mínimo</label><input class="form-control" data-variation-field="minimum_stock" inputmode="decimal" type="text" value="0"></div>
      <div class="col-12 col-md-4"><label class="form-label">Status</label><select class="form-select" data-variation-field="active"><option value="1">Ativa</option><option value="0">Inativa</option></select></div>
    </div>
  </div>
</template>
<script type="application/json" id="productCategorySchemas"><?= json_encode($categorySchemas, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
<script type="application/json" id="productAttributeValues"><?= json_encode($attributeValues, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
<script src="/assets/richtexteditor/rich_text_editor.js?v=2"></script>
<script src="<?= url('assets/js/products/form.js?v=1'); ?>"></script>
<script src="<?= url($isEdit ? 'assets/js/products/edit.js?v=2' : 'assets/js/products/create.js?v=3'); ?>"></script>
