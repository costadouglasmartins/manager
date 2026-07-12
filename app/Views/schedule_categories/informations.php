<?php
$category = is_array($category ?? null) ? $category : [];
?>
<link rel="stylesheet" href="<?= url('assets/css/schedule_categories/informations.css?v=1'); ?>">

<div class="app-page schedule-category-page">
  <div class="app-page-header">
    <div>
      <span class="app-page-kicker"><i class="bi bi-search"></i> Agenda</span>
      <h5><?= htmlspecialchars((string) ($category['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h5>
    </div>
    <span class="badge <?= ((int) ($category['active'] ?? 0) === 1) ? 'text-bg-success' : 'text-bg-secondary'; ?>"><?= ((int) ($category['active'] ?? 0) === 1) ? 'Ativo' : 'Inativo'; ?></span>
  </div>

  <section class="schedule-section">
    <div class="table-responsive">
      <table class="table table-hover align-middle">
        <tbody>
          <tr>
            <th class="text-muted" style="width: 220px;">Código</th>
            <td><?= (int) ($category['id'] ?? 0); ?></td>
          </tr>
          <tr>
            <th class="text-muted">Cores</th>
            <td><span class="schedule-category-sample" style="background-color: <?= htmlspecialchars((string) ($category['background_color'] ?? '#0d6efd'), ENT_QUOTES, 'UTF-8'); ?>; color: <?= htmlspecialchars((string) ($category['description_color'] ?? '#ffffff'), ENT_QUOTES, 'UTF-8'); ?>;">Categoria</span></td>
          </tr>
          <tr>
            <th class="text-muted">Descrição</th>
            <td><?= (string) ($category['description'] ?? ''); ?></td>
          </tr>
          <tr>
            <th class="text-muted">Criada em</th>
            <td><?= htmlspecialchars(\App\Models\ScheduleEvent::formatDateTime($category['created'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
          </tr>
          <tr>
            <th class="text-muted">Alterada em</th>
            <td><?= htmlspecialchars(\App\Models\ScheduleEvent::formatDateTime($category['modified'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>

  <div class="form-actions">
    <a href="<?= url('schedule_category'); ?>" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
    <?php if (authCan('schedule_category.edit')): ?>
      <a href="<?= url('schedule_category/edit/' . (int) ($category['id'] ?? 0)); ?>" class="btn btn-primary"><i class="bi bi-pencil-square"></i> Editar</a>
    <?php endif; ?>
  </div>
</div>

<script src="<?= url('assets/js/schedule_categories/informations.js?v=1'); ?>"></script>
