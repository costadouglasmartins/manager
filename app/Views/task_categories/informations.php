<?php
$formatDateTime = static function ($value): string {
	return \App\Models\Task::formatDateTime($value);
};
?>
<link rel="stylesheet" href="<?= url('assets/css/rich_text_editor.css?v=1'); ?>">
<link rel="stylesheet" href="<?= url('assets/css/task_categories/informations.css?v=1'); ?>">

<div class="task-page">
  <div class="task-page-header">
    <div>
      <span class="task-page-kicker"><i class="bi bi-search"></i> Tarefas</span>
      <h5>Dados da categoria de tarefa</h5>
    </div>
  </div>

  <div class="table-responsive mb-3">
    <table class="table table-hover align-middle">
      <tbody>
        <tr>
          <th class="text-muted" style="width: 220px;">Código</th>
          <td><?= (int) $category['id']; ?></td>
        </tr>
        <tr>
          <th class="text-muted">Nome</th>
          <td><?= htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8'); ?></td>
        </tr>
        <tr>
          <th class="text-muted">Descrição</th>
          <td><div class="rich-editor-content"><?= (string) ($category['description'] ?? ''); ?></div></td>
        </tr>
        <tr>
          <th class="text-muted">Status</th>
          <td><span class="badge <?= ((int) $category['active'] === 1) ? 'text-bg-success' : 'text-bg-secondary'; ?>"><?= ((int) $category['active'] === 1) ? 'Ativo' : 'Inativo'; ?></span></td>
        </tr>
        <tr>
          <th class="text-muted">Criada em</th>
          <td><?= htmlspecialchars($formatDateTime($category['created'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
        </tr>
        <tr>
          <th class="text-muted">Alterada em</th>
          <td><?= htmlspecialchars($formatDateTime($category['modified'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
        </tr>
      </tbody>
    </table>
  </div>

  <div class="form-actions">
    <a href="<?= url('task_category'); ?>" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
  </div>
</div>

<script src="<?= url('assets/js/task_categories/informations.js?v=1'); ?>"></script>
