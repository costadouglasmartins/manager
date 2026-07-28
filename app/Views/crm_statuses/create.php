<?php
$formData = (isset($formData) && is_array($formData)) ? $formData : [];
$bootstrapClass = (string) ($formData['bootstrap_class'] ?? 'text-bg-secondary');
$activeValue = (string) ($formData['active'] ?? '1');
?>

<div class="app-page">
  <div class="app-page-header">
    <div>
      <span class="app-page-kicker"><i class="bi bi-tag"></i> CRM</span>
      <h5>Novo status do CRM</h5>
    </div>
    <span class="badge <?= htmlspecialchars($bootstrapClass, ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars((string) ($formData['name'] ?? 'Preview'), ENT_QUOTES, 'UTF-8'); ?></span>
  </div>

  <?php if (!empty($formError)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars((string) $formError, ENT_QUOTES, 'UTF-8'); ?></div>
  <?php endif; ?>

  <form action="<?= url('crm_status/store'); ?>" method="POST" class="needs-validation" novalidate>
    <section class="border rounded p-3 mb-3">
      <div class="row g-3">
        <div class="col-12 col-md-6">
          <label class="form-label" for="name">Nome *</label>
          <input autocomplete="off" class="form-control" id="name" maxlength="120" name="name" required type="text" value="<?= htmlspecialchars((string) ($formData['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
          <div class="invalid-feedback">Informe o nome do status CRM.</div>
        </div>

        <div class="col-12 col-md-3">
          <label class="form-label" for="bootstrapClass">Classe Bootstrap *</label>
          <input autocomplete="off" class="form-control" id="bootstrapClass" maxlength="120" name="bootstrap_class" required type="text" value="<?= htmlspecialchars($bootstrapClass, ENT_QUOTES, 'UTF-8'); ?>">
          <div class="form-text">Ex.: text-bg-primary, text-bg-success, text-bg-warning.</div>
          <div class="invalid-feedback">Informe a classe Bootstrap.</div>
        </div>

        <div class="col-12 col-md-3">
          <label class="form-label" for="sortOrder">Ordem</label>
          <input class="form-control" id="sortOrder" min="0" name="sort_order" type="number" value="<?= htmlspecialchars((string) ($formData['sort_order'] ?? '0'), ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="col-12 col-md-3">
          <label class="form-label" for="active">Status</label>
          <select class="form-select" id="active" name="active">
            <option value="1" <?= $activeValue === '1' ? 'selected' : ''; ?>>Ativo</option>
            <option value="0" <?= $activeValue === '0' ? 'selected' : ''; ?>>Inativo</option>
          </select>
        </div>

        <div class="col-12">
          <label class="form-label" for="description">Descrição</label>
          <textarea class="form-control" id="description" maxlength="1000" name="description" rows="5"><?= htmlspecialchars((string) ($formData['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>
      </div>
    </section>

    <div class="form-actions">
      <a href="<?= url('crm_status'); ?>" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Cancelar</a>
      <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> Salvar</button>
    </div>
  </form>
</div>
