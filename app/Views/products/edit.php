<?php
$formData = $formData ?? [];
$value = static function (string $field, string $default = '') use ($formData, $product): string {
	return (string) ($formData[$field] ?? $product[$field] ?? $default);
};

$formatQuantity = static function ($value): string {
	return number_format((float) $value, 3, ',', '.');
};
?>
<link rel="stylesheet" href="<?= url('assets/css/products/edit.css?v=1'); ?>">

<div class="store-page">
  <div class="store-page-header">
    <div>
      <span class="store-page-kicker"><i class="bi bi-pencil-square"></i> Loja</span>
      <h5>Editar produto</h5>
    </div>
  </div>

  <?php if (!empty($formError)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($formError, ENT_QUOTES, 'UTF-8'); ?></div>
  <?php endif; ?>

  <form action="<?= url('product/update/' . $product['id']); ?>" method="POST" enctype="multipart/form-data" class="needs-validation store-form" novalidate>
    <section class="store-section">
      <div class="store-subheader">
        <h6>Dados do produto</h6>
      </div>

      <div class="row g-3">
        <div class="col-12 col-md-4">
          <label class="form-label" for="product_category_id">Categoria</label>
          <select class="form-select" id="product_category_id" name="product_category_id">
            <option value="">Sem categoria</option>
            <?php foreach ($categories as $category): ?>
              <option value="<?= (int) $category['id']; ?>" <?= ($value('product_category_id') === (string) $category['id']) ? 'selected' : ''; ?>>
                <?= htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12 col-md-4">
          <label class="form-label" for="sku">SKU</label>
          <input autocomplete="off" class="form-control" id="sku" maxlength="60" name="sku" type="text" value="<?= htmlspecialchars($value('sku'), ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="col-12 col-md-4">
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
            <option value="1" <?= ($value('active') === '1') ? 'selected' : ''; ?>>Ativo</option>
            <option value="0" <?= ($value('active') === '0') ? 'selected' : ''; ?>>Inativo</option>
          </select>
        </div>

        <div class="col-12">
          <label class="form-label" for="description">Descrição</label>
          <textarea class="form-control" id="description" name="description" rows="4"><?= htmlspecialchars($value('description'), ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>
      </div>
    </section>

    <section class="store-section">
      <div class="store-subheader">
        <h6>Preço e estoque</h6>
      </div>

      <div class="row g-3">
        <div class="col-12 col-md-3">
          <label class="form-label" for="cost_price">Custo</label>
          <input class="form-control" id="cost_price" inputmode="decimal" name="cost_price" type="text" value="<?= htmlspecialchars($value('cost_price', '0,00'), ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="col-12 col-md-3">
          <label class="form-label" for="sale_price">Preço de venda</label>
          <input class="form-control" id="sale_price" inputmode="decimal" name="sale_price" type="text" value="<?= htmlspecialchars($value('sale_price', '0,00'), ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="col-12 col-md-2">
          <label class="form-label" for="unit">Unidade</label>
          <input class="form-control" id="unit" maxlength="20" name="unit" required type="text" value="<?= htmlspecialchars($value('unit', 'UN'), ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="col-12 col-md-2">
          <label class="form-label" for="minimum_stock">Estoque mínimo</label>
          <input class="form-control" id="minimum_stock" inputmode="decimal" name="minimum_stock" type="text" value="<?= htmlspecialchars($value('minimum_stock', '0'), ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="col-12 col-md-2">
          <label class="form-label" for="stock_quantity">Estoque atual</label>
          <input class="form-control" id="stock_quantity" readonly type="text" value="<?= htmlspecialchars($formatQuantity($product['stock_quantity'] ?? 0), ENT_QUOTES, 'UTF-8'); ?>">
        </div>
      </div>
    </section>

    <section class="store-section">
      <div class="store-subheader">
        <h6>Imagens atuais</h6>
      </div>

      <?php if (!empty($images)): ?>
        <div class="product-image-grid mb-3">
          <?php foreach ($images as $image): ?>
            <div class="product-image-item">
              <img src="<?= url($image['thumb_path'] ?: $image['file_path']); ?>" alt="<?= htmlspecialchars((string) ($image['original_name'] ?? $product['name']), ENT_QUOTES, 'UTF-8'); ?>">
              <div class="product-image-controls">
                <label class="form-check">
                  <input class="form-check-input" name="main_image_id" type="radio" value="<?= (int) $image['id']; ?>" <?= ((int) $image['is_main'] === 1) ? 'checked' : ''; ?>>
                  <span class="form-check-label">Imagem principal</span>
                </label>
                <label class="form-check">
                  <input class="form-check-input" name="remove_images[]" type="checkbox" value="<?= (int) $image['id']; ?>">
                  <span class="form-check-label">Remover</span>
                </label>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p class="text-muted mb-3">Nenhuma imagem cadastrada.</p>
      <?php endif; ?>

      <label class="form-label" for="images">Adicionar imagens</label>
      <input accept="image/jpeg,image/png,image/webp" class="form-control" data-product-images-input id="images" multiple name="images[]" type="file">
      <div class="product-upload-preview" data-product-upload-preview></div>
    </section>

    <div class="store-actions view-actions">
      <a href="<?= url('product'); ?>" class="btn btn-secondary">Cancelar</a>
      <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> Salvar</button>
    </div>
  </form>
</div>

<script src="<?= url('assets/js/products/edit.js?v=1'); ?>"></script>
