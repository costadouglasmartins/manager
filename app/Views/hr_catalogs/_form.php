<?php
// Shared form of hr_catalogs/create and hr_catalogs/edit.
// Expected: $catalogMeta, $formAction (string), $formValues (name, description, active), $catalogHeader (page title).
$esc = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$resource = (string) ($catalogMeta['resource'] ?? '');
$activeValue = (string) ($formValues['active'] ?? '1');
?>
<div class="app-page">
  <div class="app-page-header">
    <div>
      <span class="app-page-kicker"><i class="bi <?= $esc($catalogMeta['icon'] ?? 'bi-diagram-3'); ?>" aria-hidden="true"></i> RH</span>
      <h5><?= $esc($catalogHeader); ?></h5>
    </div>
  </div>

  <?php if (!empty($formError)): ?>
    <div class="alert alert-danger" role="alert"><?= $esc($formError); ?></div>
  <?php endif; ?>

  <form action="<?= $esc($formAction); ?>" class="needs-validation" method="POST" novalidate>
    <section class="border rounded p-3 mb-3">
      <div class="row g-3">
        <div class="col-12 col-lg-8">
          <label class="form-label" for="catalogName">Nome *</label>
          <input autocomplete="off" class="form-control" id="catalogName" maxlength="120" name="name" required type="text" value="<?= $esc($formValues['name'] ?? ''); ?>">
          <div class="invalid-feedback">Informe o nome.</div>
        </div>

        <div class="col-12 col-lg-4">
          <label class="form-label" for="catalogActive">Status</label>
          <select class="form-select" id="catalogActive" name="active">
            <option value="1" <?= $activeValue === '1' ? 'selected' : ''; ?>>Ativo</option>
            <option value="0" <?= $activeValue === '0' ? 'selected' : ''; ?>>Inativo</option>
          </select>
        </div>

        <div class="col-12">
          <label class="form-label" for="catalogDescription">Descrição</label>
          <textarea class="form-control" id="catalogDescription" maxlength="255" name="description" rows="4"><?= $esc($formValues['description'] ?? ''); ?></textarea>
        </div>
      </div>
    </section>

    <div class="form-actions">
      <a class="btn btn-secondary" href="<?= url($resource); ?>"><i class="bi bi-arrow-left" aria-hidden="true"></i> Cancelar</a>
      <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg" aria-hidden="true"></i> Salvar</button>
    </div>
  </form>
</div>
<script src="<?= url('assets/js/hr_catalogs/form.js?v=1'); ?>"></script>
