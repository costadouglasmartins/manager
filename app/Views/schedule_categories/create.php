<?php
$formData = $formData ?? [];
?>
<link rel="stylesheet" href="<?= url('assets/css/schedule_categories/create.css?v=1'); ?>">

<div class="app-page schedule-category-page">
  <div class="app-page-header">
    <div>
      <span class="app-page-kicker"><i class="bi bi-tag"></i> Agenda</span>
      <h5>Criar categoria da agenda</h5>
    </div>
  </div>

  <?php if (!empty($formError)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars((string) $formError, ENT_QUOTES, 'UTF-8'); ?></div>
  <?php endif; ?>

  <form action="<?= url('schedule_category/store'); ?>" method="POST" class="needs-validation schedule-category-form" novalidate>
    <section class="schedule-section">
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
        <div class="col-12 col-md-6">
          <label class="form-label" for="backgroundColor">Cor de fundo</label>
          <input class="form-control form-control-color schedule-color-input" id="backgroundColor" name="background_color" type="color" value="<?= htmlspecialchars((string) ($formData['background_color'] ?? '#0d6efd'), ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="col-12 col-md-6">
          <label class="form-label" for="descriptionColor">Cor da descrição</label>
          <input class="form-control form-control-color schedule-color-input" id="descriptionColor" name="description_color" type="color" value="<?= htmlspecialchars((string) ($formData['description_color'] ?? '#ffffff'), ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="col-12">
          <label class="form-label" for="description">Descrição</label>
          <textarea class="form-control" id="description" maxlength="255" name="description" rows="3"><?= htmlspecialchars((string) ($formData['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>
      </div>
    </section>
    <div class="form-actions">
      <a href="<?= url('schedule_category'); ?>" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Cancelar</a>
      <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> Salvar</button>
    </div>
  </form>
</div>

<script src="<?= url('assets/js/schedule_categories/create.js?v=1'); ?>"></script>
