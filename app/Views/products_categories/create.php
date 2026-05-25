<?php
$formData = $formData ?? [];
?>
<link rel="stylesheet" href="<?= url('assets/css/products_categories/create.css?v=1'); ?>">

<div class="store-page">
  <div class="store-page-header">
    <div>
      <span class="store-page-kicker"><i class="bi bi-tag"></i> Loja</span>
      <h5>Criar categoria de produto</h5>
    </div>
  </div>

  <?php if (!empty($formError)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($formError, ENT_QUOTES, 'UTF-8'); ?></div>
  <?php endif; ?>

  <form action="<?= url('product_category/store'); ?>" method="POST" class="needs-validation store-form" novalidate>
    <section class="store-section">
      <div class="row g-3">
        <div class="col-12 col-md-8">
          <label class="form-label" for="name">Nome</label>
          <input autocomplete="off" class="form-control" id="name" maxlength="120" name="name" required type="text" value="<?= htmlspecialchars((string) ($formData['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
          <div class="invalid-feedback">Informe o nome da categoria.</div>
        </div>

        <div class="col-12 col-md-4">
          <label class="form-label" for="active">Status</label>
          <select class="form-select" id="active" name="active">
            <option value="1" <?= ((string) ($formData['active'] ?? '1') === '1') ? 'selected' : ''; ?>>Ativo</option>
            <option value="0" <?= ((string) ($formData['active'] ?? '1') === '0') ? 'selected' : ''; ?>>Inativo</option>
          </select>
        </div>

        <div class="col-12">
          <label class="form-label" for="description">Descrição</label>
          <textarea class="form-control" id="description" maxlength="255" name="description" rows="3"><?= htmlspecialchars((string) ($formData['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>
      </div>
    </section>

    <div class="store-actions view-actions">
      <a href="<?= url('product_category'); ?>" class="btn btn-secondary">Cancelar</a>
      <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> Salvar</button>
    </div>
  </form>
</div>

<script src="<?= url('assets/js/products_categories/create.js?v=1'); ?>"></script>
