<?php
$filters = $filters ?? [
	'id' => '',
	'task_category_id' => '',
	'status' => '',
	'assignee_person_id' => '',
	'title' => '',
	'description' => '',
	'third_party_name' => '',
	'created_from' => '',
	'created_to' => '',
];
$page = $page ?? 1;
$totalPages = $totalPages ?? 1;
$total = $total ?? (is_array($tasks) ? count($tasks) : 0);
$sort = (string) ($sort ?? 'created');
$direction = (string) ($direction ?? 'desc');
$baseQuery = [
	'id' => $filters['id'],
	'task_category_id' => $filters['task_category_id'],
	'status' => $filters['status'],
	'assignee_person_id' => $filters['assignee_person_id'],
	'title' => $filters['title'],
	'description' => $filters['description'],
	'third_party_name' => $filters['third_party_name'],
	'created_from' => $filters['created_from'],
	'created_to' => $filters['created_to'],
	'sort' => $sort,
	'direction' => $direction,
];
$queryFilters = http_build_query($baseQuery);
$sortUrl = static function (string $column) use ($filters, $sort, $direction): string {
	$nextDirection = ($sort === $column && $direction === 'asc') ? 'desc' : 'asc';
	return url('task?' . http_build_query([
		'id' => $filters['id'],
		'task_category_id' => $filters['task_category_id'],
		'status' => $filters['status'],
		'assignee_person_id' => $filters['assignee_person_id'],
		'title' => $filters['title'],
		'description' => $filters['description'],
		'third_party_name' => $filters['third_party_name'],
		'created_from' => $filters['created_from'],
		'created_to' => $filters['created_to'],
		'sort' => $column,
		'direction' => $nextDirection,
	]));
};
$sortIcon = static function (string $column) use ($sort, $direction): string {
	if ($sort !== $column)
		{
		return 'bi-arrow-down-up';
		}

	return $direction === 'asc' ? 'bi-sort-up' : 'bi-sort-down';
};
?>
<h5>Lista de Tarefas</h5>

  <form action="<?= url('task'); ?>" class="row g-3 mb-3" method="get">
    <input name="sort" type="hidden" value="<?= htmlspecialchars($sort, ENT_QUOTES, 'UTF-8'); ?>">
    <input name="direction" type="hidden" value="<?= htmlspecialchars($direction, ENT_QUOTES, 'UTF-8'); ?>">

    <div class="col-12 col-lg-9">
      <div class="row g-3">
        <div class="col-12 col-md-2">
          <input class="form-control" id="id" name="id" type="text" placeholder="Código" value="<?= htmlspecialchars($filters['id'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="col-12 col-md-4">
          <input class="form-control" id="title" maxlength="160" name="title" type="text" placeholder="Título" value="<?= htmlspecialchars($filters['title'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="col-12 col-md-3">
          <select class="form-select" id="task_category_id" name="task_category_id">
            <option value="" <?= $filters['task_category_id'] === '' ? 'selected' : ''; ?>>Todas as categorias</option>
            <?php foreach ($categories as $category): ?>
              <option value="<?= (int) $category['id']; ?>" <?= ((string) $filters['task_category_id'] === (string) $category['id']) ? 'selected' : ''; ?>><?= htmlspecialchars((string) $category['name'], ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12 col-md-3">
          <select class="form-select" id="status" name="status">
            <option value="" <?= $filters['status'] === '' ? 'selected' : ''; ?>>Todas as situações</option>
            <?php foreach ($statuses as $value => $config): ?>
              <option value="<?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); ?>" <?= $filters['status'] === (string) $value ? 'selected' : ''; ?>><?= htmlspecialchars((string) ($config['label'] ?? $value), ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12 col-md-4">
          <select class="form-select" id="assignee_person_id" name="assignee_person_id">
            <option value="" <?= $filters['assignee_person_id'] === '' ? 'selected' : ''; ?>>Todos os responsáveis</option>
            <?php foreach ($people as $person): ?>
              <option value="<?= (int) $person['id']; ?>" <?= ((string) $filters['assignee_person_id'] === (string) $person['id']) ? 'selected' : ''; ?>><?= htmlspecialchars((string) $person['name'], ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12 col-md-4">
          <input class="form-control" id="third_party_name" maxlength="160" name="third_party_name" type="text" placeholder="Terceiro" value="<?= htmlspecialchars($filters['third_party_name'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="col-12 col-md-4">
          <input class="form-control" id="description" name="description" type="text" placeholder="Descrição" value="<?= htmlspecialchars($filters['description'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="col-12 col-md-6">
          <input class="form-control" id="created_from" name="created_from" type="date" value="<?= htmlspecialchars($filters['created_from'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="col-12 col-md-6">
          <input class="form-control" id="created_to" name="created_to" type="date" value="<?= htmlspecialchars($filters['created_to'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-3">
      <div class="d-flex flex-column gap-2 align-items-center align-items-lg-end">
        <button type="submit" class="btn btn-primary w-100">Pesquisar</button>
        <a href="<?= url('task'); ?>" class="btn btn-secondary w-100">Limpar</a>
        <?php if (authCan('task.create')): ?>
          <a href="<?= url('task/create'); ?>" class="btn btn-success w-100">Novo(a)</a>
        <?php endif; ?>
      </div>
    </div>
  </form>

  <div class="d-flex flex-wrap gap-2 mb-3">
    <?php if (authCan('task.index')): ?>
      <?= pdfExportButton('task', $queryFilters); ?>
      <?= excelExportButton('task', $queryFilters); ?>
    <?php endif; ?>
  </div>

  <hr>

  <div class="table-responsive">
    <table class="table table-striped table-hover">
      <thead>
        <tr>
          <th><a class="sort-link" href="<?= $sortUrl('id'); ?>"># <i class="bi <?= htmlspecialchars($sortIcon('id'), ENT_QUOTES, 'UTF-8'); ?>"></i></a></th>
          <th><a class="sort-link" href="<?= $sortUrl('title'); ?>">Tarefa <i class="bi <?= htmlspecialchars($sortIcon('title'), ENT_QUOTES, 'UTF-8'); ?>"></i></a></th>
          <th><a class="sort-link" href="<?= $sortUrl('category'); ?>">Categoria <i class="bi <?= htmlspecialchars($sortIcon('category'), ENT_QUOTES, 'UTF-8'); ?>"></i></a></th>
          <th><a class="sort-link" href="<?= $sortUrl('status'); ?>">Situação <i class="bi <?= htmlspecialchars($sortIcon('status'), ENT_QUOTES, 'UTF-8'); ?>"></i></a></th>
          <th>Responsáveis</th>
          <th>Tempo</th>
          <th><a class="sort-link" href="<?= $sortUrl('created'); ?>">Criada em <i class="bi <?= htmlspecialchars($sortIcon('created'), ENT_QUOTES, 'UTF-8'); ?>"></i></a></th>
          <th class="text-end">Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($tasks)): ?>
          <?php foreach ($tasks as $row): ?>
            <tr>
              <td style="width:1%;"><?= (int) $row['id']; ?></td>
              <td>
                <div class="fw-semibold"><?= htmlspecialchars((string) ($row['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                <?php if (!empty($row['third_party_name']) || !empty($row['third_party_person_name'])): ?>
                  <small class="text-muted"><?= htmlspecialchars((string) ($row['third_party_person_name'] ?: $row['third_party_name']), ENT_QUOTES, 'UTF-8'); ?></small>
                <?php endif; ?>
              </td>
              <td><?= htmlspecialchars((string) ($row['task_category_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><span class="badge <?= htmlspecialchars(\App\Models\Task::statusBadgeClass((string) ($row['status'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars(\App\Models\Task::statusLabel((string) ($row['status'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></span></td>
              <td><?= htmlspecialchars((string) ($row['assignee_names'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?= htmlspecialchars(\App\Models\Task::formatDuration((int) ($row['total_seconds'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?= htmlspecialchars(\App\Models\Task::formatDateTime($row['created'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td style="width:1%;" class="text-nowrap text-end">
                <?php if (authCan('task.edit')): ?>
                  <a href="<?= url('task/edit/' . $row['id']); ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Editar"><i class="bi bi-pencil-square"></i></a>
                <?php endif; ?>
                <?php if (authCan('task.informations')): ?>
                  <a href="<?= url('task/informations/' . $row['id']); ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Saiba mais"><i class="bi bi-search"></i></a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="8" class="text-center">Nenhum registro encontrado.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <p class="text-muted"><?= (int) $total; ?> registro(s) encontrado(s)</p>

  <?php if ($totalPages > 1): ?>
    <?= paginationLinks('task', $page, $totalPages, $queryFilters, 2, 'Anterior', 'Próxima'); ?>
  <?php endif; ?>

<script src="<?= url('assets/js/tasks/index.js?v=1'); ?>"></script>
