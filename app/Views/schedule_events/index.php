<?php
$filters = $filters ?? [
	'id' => '',
	'schedule_category_id' => '',
	'status' => '',
	'participant_person_id' => '',
	'title' => '',
	'location' => '',
	'starts_from' => '',
	'starts_to' => '',
];
$page = $page ?? 1;
$totalPages = $totalPages ?? 1;
$total = $total ?? (is_array($events) ? count($events) : 0);
$sort = (string) ($sort ?? 'starts_at');
$direction = (string) ($direction ?? 'asc');
$baseQuery = [
	'id' => $filters['id'],
	'schedule_category_id' => $filters['schedule_category_id'],
	'status' => $filters['status'],
	'participant_person_id' => $filters['participant_person_id'],
	'title' => $filters['title'],
	'location' => $filters['location'],
	'starts_from' => $filters['starts_from'],
	'starts_to' => $filters['starts_to'],
	'sort' => $sort,
	'direction' => $direction,
];
$queryFilters = http_build_query($baseQuery);
$sortUrl = static function (string $column) use ($filters, $sort, $direction): string {
	$nextDirection = ($sort === $column && $direction === 'asc') ? 'desc' : 'asc';
	return url('schedule?' . http_build_query([
		'id' => $filters['id'],
		'schedule_category_id' => $filters['schedule_category_id'],
		'status' => $filters['status'],
		'participant_person_id' => $filters['participant_person_id'],
		'title' => $filters['title'],
		'location' => $filters['location'],
		'starts_from' => $filters['starts_from'],
		'starts_to' => $filters['starts_to'],
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
<link rel="stylesheet" href="<?= url('assets/css/schedule_events/index.css?v=1'); ?>">

<h5>Lista de Eventos da Agenda</h5>

<?php if (!empty($formError)): ?>
  <div class="alert alert-danger"><?= htmlspecialchars((string) $formError, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>

<form action="<?= url('schedule'); ?>" class="row g-3 mb-3" method="get">
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
        <select class="form-select" id="schedule_category_id" name="schedule_category_id">
          <option value="" <?= $filters['schedule_category_id'] === '' ? 'selected' : ''; ?>>Todas as categorias</option>
          <?php foreach ($categories as $category): ?>
            <option value="<?= (int) $category['id']; ?>" <?= ((string) $filters['schedule_category_id'] === (string) $category['id']) ? 'selected' : ''; ?>><?= htmlspecialchars((string) $category['name'], ENT_QUOTES, 'UTF-8'); ?></option>
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
        <select class="form-select" id="participant_person_id" name="participant_person_id">
          <option value="" <?= $filters['participant_person_id'] === '' ? 'selected' : ''; ?>>Todos os participantes</option>
          <?php foreach ($people as $person): ?>
            <option value="<?= (int) $person['id']; ?>" <?= ((string) $filters['participant_person_id'] === (string) $person['id']) ? 'selected' : ''; ?>><?= htmlspecialchars((string) $person['name'], ENT_QUOTES, 'UTF-8'); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 col-md-4">
        <input class="form-control" id="location" maxlength="160" name="location" type="text" placeholder="Local" value="<?= htmlspecialchars($filters['location'], ENT_QUOTES, 'UTF-8'); ?>">
      </div>
      <div class="col-12 col-md-2">
        <input class="form-control" id="starts_from" name="starts_from" type="date" value="<?= htmlspecialchars($filters['starts_from'], ENT_QUOTES, 'UTF-8'); ?>">
      </div>
      <div class="col-12 col-md-2">
        <input class="form-control" id="starts_to" name="starts_to" type="date" value="<?= htmlspecialchars($filters['starts_to'], ENT_QUOTES, 'UTF-8'); ?>">
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-3">
    <div class="d-flex flex-column gap-2 align-items-center align-items-lg-end">
      <button type="submit" class="btn btn-primary w-100">Pesquisar</button>
      <a href="<?= url('schedule'); ?>" class="btn btn-secondary w-100">Limpar</a>
      <?php if (authCan('schedule.calendar')): ?>
        <a href="<?= url('schedule/calendar'); ?>" class="btn btn-outline-primary w-100"><i class="bi bi-calendar3"></i> Calendário</a>
      <?php endif; ?>
      <?php if (authCan('schedule.create')): ?>
        <a href="<?= url('schedule/create'); ?>" class="btn btn-success w-100">Novo(a)</a>
      <?php endif; ?>
    </div>
  </div>
</form>

<div class="d-flex flex-wrap gap-2 mb-3">
  <?php if (authCan('schedule.index')): ?>
    <?= pdfExportButton('schedule', $queryFilters); ?>
    <?= excelExportButton('schedule', $queryFilters); ?>
  <?php endif; ?>
</div>

<hr>

<div class="table-responsive">
  <table class="table table-striped table-hover align-middle">
    <thead>
      <tr>
        <th><a class="sort-link" href="<?= $sortUrl('id'); ?>"># <i class="bi <?= htmlspecialchars($sortIcon('id'), ENT_QUOTES, 'UTF-8'); ?>"></i></a></th>
        <th><a class="sort-link" href="<?= $sortUrl('title'); ?>">Evento <i class="bi <?= htmlspecialchars($sortIcon('title'), ENT_QUOTES, 'UTF-8'); ?>"></i></a></th>
        <th><a class="sort-link" href="<?= $sortUrl('category'); ?>">Categoria <i class="bi <?= htmlspecialchars($sortIcon('category'), ENT_QUOTES, 'UTF-8'); ?>"></i></a></th>
        <th><a class="sort-link" href="<?= $sortUrl('status'); ?>">Situação <i class="bi <?= htmlspecialchars($sortIcon('status'), ENT_QUOTES, 'UTF-8'); ?>"></i></a></th>
        <th>Participantes</th>
        <th><a class="sort-link" href="<?= $sortUrl('starts_at'); ?>">Início <i class="bi <?= htmlspecialchars($sortIcon('starts_at'), ENT_QUOTES, 'UTF-8'); ?>"></i></a></th>
        <th class="text-end">Ações</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!empty($events)): ?>
        <?php foreach ($events as $row): ?>
          <tr>
            <td style="width:1%;"><?= (int) $row['id']; ?></td>
            <td>
              <div class="fw-semibold"><?= htmlspecialchars((string) ($row['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
              <?php if (!empty($row['location'])): ?>
                <small class="text-muted"><?= htmlspecialchars((string) $row['location'], ENT_QUOTES, 'UTF-8'); ?></small>
              <?php endif; ?>
            </td>
            <td>
              <?php if (!empty($row['schedule_category_name'])): ?>
                <span class="schedule-event-category" style="background-color: <?= htmlspecialchars((string) ($row['schedule_category_background_color'] ?? '#0d6efd'), ENT_QUOTES, 'UTF-8'); ?>; color: <?= htmlspecialchars((string) ($row['schedule_category_description_color'] ?? '#ffffff'), ENT_QUOTES, 'UTF-8'); ?>;"><?= htmlspecialchars((string) $row['schedule_category_name'], ENT_QUOTES, 'UTF-8'); ?></span>
              <?php endif; ?>
            </td>
            <td><span class="badge <?= htmlspecialchars(\App\Models\ScheduleEvent::statusBadgeClass((string) ($row['status'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars(\App\Models\ScheduleEvent::statusLabel((string) ($row['status'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></span></td>
            <td><?= htmlspecialchars((string) ($row['participant_names'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?= htmlspecialchars(\App\Models\ScheduleEvent::formatDateTime($row['starts_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
            <td style="width:1%;" class="text-nowrap text-end">
              <?php if (authCan('schedule.delete')): ?>
                <a href="#" class="js-record-delete-link text-danger" data-bs-toggle="modal" data-bs-target="#recordDeleteModal" data-record-id="<?= (int) $row['id']; ?>" data-record-name="<?= htmlspecialchars((string) ($row['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" data-record-delete-description="o evento" data-record-delete-url="<?= url('schedule/delete/' . (int) $row['id']); ?>" data-bs-content="Excluir" data-bs-trigger="hover focus" role="button" aria-label="Excluir evento"><i class="bi bi-trash"></i></a>
              <?php endif; ?>
              <?php if (authCan('schedule.edit')): ?>
                <a href="<?= url('schedule/edit/' . (int) $row['id']); ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Editar"><i class="bi bi-pencil-square"></i></a>
              <?php endif; ?>
              <?php if (authCan('schedule.informations')): ?>
                <a href="<?= url('schedule/informations/' . (int) $row['id']); ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Saiba mais"><i class="bi bi-search"></i></a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr>
          <td colspan="7" class="text-center">Nenhum registro encontrado.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<p class="text-muted"><?= (int) $total; ?> registro(s) encontrado(s)</p>

<?php if ($totalPages > 1): ?>
  <?= paginationLinks('schedule', $page, $totalPages, $queryFilters, 2, 'Anterior', 'Próxima'); ?>
<?php endif; ?>

<?php if (authCan('schedule.delete')): ?>
  <?php require __DIR__ . '/../shared/delete_modal.php'; ?>
<?php endif; ?>

<script src="<?= url('assets/js/shared/delete_modal.js?v=1'); ?>"></script>
<script src="<?= url('assets/js/schedule_events/index.js?v=1'); ?>"></script>
