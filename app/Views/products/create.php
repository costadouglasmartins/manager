<?php
$formData = $formData ?? [];
$value = static function (string $field, string $default = '') use ($formData): string {
	return (string) ($formData[$field] ?? $default);
};

$description = $value('description');
$editorHtml = trim(preg_replace('/<([a-z][a-z0-9]*)\b[^>]*>/i', '<$1>', strip_tags($description, '<b><strong><i><em><u><br><p><div><ul><ol><li>')) ?? '');
?>
<link rel="stylesheet" href="<?= url('assets/css/rich_text_editor.css?v=1'); ?>">
<link rel="stylesheet" href="<?= url('assets/css/products/create.css?v=2'); ?>">

<div class="store-page app-page">
  <div class="store-page-header app-page-header">
    <div>
      <span class="store-page-kicker app-page-kicker"><i class="bi bi-box-seam"></i> Loja</span>
      <h5>Criar produto</h5>
    </div>
  </div>

  <?php if (!empty($formError)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($formError, ENT_QUOTES, 'UTF-8'); ?></div>
  <?php endif; ?>

  <form action="<?= url('product/store'); ?>" method="POST" enctype="multipart/form-data" class="needs-validation store-form" data-product-chunk-upload-url="<?= url('product/upload-chunk'); ?>" novalidate>
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
            <option value="1" <?= ($value('active', '1') === '1') ? 'selected' : ''; ?>>Ativo</option>
            <option value="0" <?= ($value('active', '1') === '0') ? 'selected' : ''; ?>>Inativo</option>
          </select>
        </div>

        <div class="col-12">
          <label class="form-label" for="descriptionEditor">Descrição</label>
          <div class="rich-editor" data-rich-editor>
            <div class="rich-editor-toolbar" role="toolbar">
              <button class="btn btn-outline-secondary btn-sm" data-editor-command="bold" type="button"><i class="bi bi-type-bold"></i></button>
              <button class="btn btn-outline-secondary btn-sm" data-editor-command="italic" type="button"><i class="bi bi-type-italic"></i></button>
              <button class="btn btn-outline-secondary btn-sm" data-editor-command="underline" type="button"><i class="bi bi-type-underline"></i></button>
            </div>
            <div class="form-control rich-editor-surface" contenteditable="true" data-rich-editor-surface id="descriptionEditor"><?= $editorHtml; ?></div>
            <textarea class="d-none" data-rich-editor-source name="description"><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?></textarea>
          </div>
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
          <label class="form-label" for="initial_stock">Estoque inicial</label>
          <input class="form-control" id="initial_stock" inputmode="decimal" name="initial_stock" type="text" value="<?= htmlspecialchars($value('initial_stock', '0'), ENT_QUOTES, 'UTF-8'); ?>">
        </div>
      </div>
    </section>

    <section class="store-section">
      <div class="store-subheader">
        <h6>Imagens</h6>
      </div>

      <label class="form-label" for="images">Imagens do produto</label>
      <input accept="image/jpeg,image/png,image/webp" class="form-control" data-product-images-input id="images" multiple name="images[]" type="file">
      <div class="product-upload-progress d-none" data-product-upload-progress>
        <div class="product-upload-progress-header">
          <span data-product-upload-status>Preparando upload...</span>
          <span data-product-upload-details>0 de 0 MB</span>
        </div>
        <div class="progress" aria-label="Progresso do upload" aria-valuemax="100" aria-valuemin="0" aria-valuenow="0" role="progressbar">
          <div class="progress-bar progress-bar-striped progress-bar-animated fw-bold text-white" data-product-upload-progress-bar style="width: 0%;">0%</div>
        </div>
      </div>
      <div class="alert alert-danger d-none" data-product-upload-error></div>
      <div class="product-upload-preview" data-product-upload-preview></div>
    </section>

    <div class="form-actions">
      <a href="<?= url('product'); ?>" class="btn btn-secondary">Cancelar</a>
      <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> Salvar</button>
    </div>
  </form>
</div>

<script src="<?= url('assets/js/rich_text_editor.js?v=1'); ?>"></script>
<script src="<?= url('assets/js/products/create.js?v=2'); ?>"></script>
