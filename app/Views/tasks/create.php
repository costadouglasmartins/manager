<?php
$formData = $formData ?? [];
$taskData = is_array($formData['task'] ?? null) ? $formData['task'] : [];
$selectedAssignees = is_array($taskData['assignee_person_ids'] ?? null) ? array_map('strval', $taskData['assignee_person_ids']) : [];
$description = (string) ($taskData['description'] ?? '');
$editorHtml = trim(preg_replace('/<([a-z][a-z0-9]*)\b[^>]*>/i', '<$1>', strip_tags($description, '<b><strong><i><em><u><br><p><div><ul><ol><li>')) ?? '');
$statusValue = (string) ($taskData['status'] ?? \App\Models\Task::STATUS_OPEN);
?>
<link rel="stylesheet" href="<?= url('assets/css/tasks/create.css?v=2'); ?>">

<div class="task-page app-page">
  <div class="task-page-header app-page-header">
    <div>
      <span class="task-page-kicker app-page-kicker"><i class="bi bi-list-check"></i> Tarefas</span>
      <h5>Criar tarefa</h5>
    </div>
  </div>

  <?php if (!empty($formError)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars((string) $formError, ENT_QUOTES, 'UTF-8'); ?></div>
  <?php endif; ?>

  <form action="<?= url('task/store'); ?>" method="POST" class="needs-validation task-form js-task-form" novalidate>
    <section class="task-section">
      <div class="row g-3">
        <div class="col-12 col-lg-8">
          <label class="form-label" for="taskTitle">Título</label>
          <input autocomplete="off" class="form-control" id="taskTitle" maxlength="160" name="task[title]" required type="text" value="<?= htmlspecialchars((string) ($taskData['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
          <div class="invalid-feedback">Informe o título da tarefa.</div>
        </div>

        <div class="col-12 col-lg-4">
          <label class="form-label" for="taskCategory">Categoria</label>
          <select class="form-select" id="taskCategory" name="task[task_category_id]">
            <option value="">Sem categoria</option>
            <?php foreach ($categories as $category): ?>
              <option value="<?= (int) $category['id']; ?>" <?= ((string) ($taskData['task_category_id'] ?? '') === (string) $category['id']) ? 'selected' : ''; ?>><?= htmlspecialchars((string) $category['name'], ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12 col-lg-6">
          <label class="form-label" for="taskAssignees">Responsáveis</label>
          <select class="form-select" id="taskAssignees" name="task[assignee_person_ids][]" multiple required size="6">
            <?php foreach ($people as $person): ?>
              <option value="<?= (int) $person['id']; ?>" <?= in_array((string) $person['id'], $selectedAssignees, true) ? 'selected' : ''; ?>><?= htmlspecialchars((string) $person['name'], ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
          <div class="invalid-feedback">Selecione ao menos um responsável.</div>
        </div>

        <div class="col-12 col-lg-6">
          <label class="form-label" for="taskStatus">Situação</label>
          <select class="form-select js-task-status" id="taskStatus" name="task[status]" required>
            <?php foreach ($statuses as $value => $config): ?>
              <option value="<?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); ?>" <?= $statusValue === (string) $value ? 'selected' : ''; ?>><?= htmlspecialchars((string) ($config['label'] ?? $value), ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>

          <div class="task-third-party js-task-third-party mt-3">
            <label class="form-label" for="thirdPartyName">Terceiro</label>
            <input autocomplete="off" class="form-control js-autocomplete js-third-party-name" data-endpoint="<?= url('task/thirdPartyAutocomplete'); ?>" data-hidden="#thirdPartyPersonId" data-minlen="1" data-param="term" data-results="#thirdPartyResults" id="thirdPartyName" maxlength="160" name="task[third_party_name]" type="text" value="<?= htmlspecialchars((string) ($taskData['third_party_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
            <input id="thirdPartyPersonId" name="task[third_party_person_id]" type="hidden" value="<?= htmlspecialchars((string) ($taskData['third_party_person_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
            <ul class="list-group autocomplete-results" id="thirdPartyResults"></ul>
          </div>
        </div>

        <div class="col-12">
          <label class="form-label" for="taskDescriptionEditor">Descrição</label>
          <div class="task-editor">
            <div class="task-editor-toolbar" role="toolbar">
              <button class="btn btn-outline-secondary btn-sm" data-editor-command="bold" type="button"><i class="bi bi-type-bold"></i></button>
              <button class="btn btn-outline-secondary btn-sm" data-editor-command="italic" type="button"><i class="bi bi-type-italic"></i></button>
              <button class="btn btn-outline-secondary btn-sm" data-editor-command="underline" type="button"><i class="bi bi-type-underline"></i></button>
            </div>
            <div class="form-control task-editor-surface js-task-editor" contenteditable="true" id="taskDescriptionEditor"><?= $editorHtml; ?></div>
            <textarea class="d-none js-task-description" name="task[description]" required><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?></textarea>
          </div>
          <div class="invalid-feedback d-block js-task-description-feedback d-none">Informe a descrição da tarefa.</div>
        </div>
      </div>
    </section>

    <div class="form-actions">
      <a href="<?= url('task'); ?>" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Cancelar</a>
      <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> Salvar</button>
    </div>
  </form>
</div>

<script src="/assets/autocomplete/autocomplete.js?v=2"></script>
<script src="<?= url('assets/js/tasks/create.js?v=1'); ?>"></script>
