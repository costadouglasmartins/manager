<?php
$formData = $formData ?? [];
$category = $category ?? [];
$activeValue = (string) ($formData['active'] ?? ($category['active'] ?? '1'));
?>
<link rel="stylesheet" href="<?= url('assets/css/task_categories/edit.css?v=1'); ?>">

<div class="task-page">
  <div class="task-page-header">
    <div>
      <span class="task-page-kicker"><i class="bi bi-pencil-square"></i> Tarefas</span>
      <h5>Editar categoria de tarefa</h5>
    </div>
  </div>

  <?php if (!empty($formError)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars((string) $formError, ENT_QUOTES, 'UTF-8'); ?></div>
  <?php endif; ?>

  <form action="<?= url('task_category/update/' . (int) ($category['id'] ?? 0)); ?>" method="POST" class="needs-validation task-form" novalidate>
    <section class="task-section">
      <div class="row g-3">
        <div class="col-12 col-md-8">
          <label class="form-label" for="name">Nome</label>
          <input autocomplete="off" class="form-control" id="name" maxlength="120" name="name" required type="text" value="<?= htmlspecialchars((string) ($formData['name'] ?? $category['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
          <div class="invalid-feedback">Informe o nome da categoria.</div>
        </div>

        <div class="col-12 col-md-4">
          <label class="form-label" for="active">Status</label>
          <select class="form-select" id="active" name="active">
            <option value="1" <?= $activeValue === '1' ? 'selected' : ''; ?>>Ativo</option>
            <option value="0" <?= $activeValue === '0' ? 'selected' : ''; ?>>Inativo</option>
          </select>
        </div>

        <div class="col-12">
          <label class="form-label" for="description">Descrição</label>
          <textarea class="form-control" id="description" maxlength="255" name="description" rows="3"><?= htmlspecialchars((string) ($formData['description'] ?? $category['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>
      </div>
    </section>

    <div class="view-actions">
      <a href="<?= url('task_category'); ?>" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Cancelar</a>
      <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> Salvar</button>
    </div>
  </form>
</div>

<script src="<?= url('assets/js/task_categories/edit.js?v=1'); ?>"></script>
