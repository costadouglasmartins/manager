<?php
$filters = $filters ?? [
	'id' => '',
	'tenant_person_id' => '',
	'person_name' => '',
	'activity_type_id' => '',
	'activity_status_id' => '',
	'responsible_user_id' => '',
	'scheduled_from' => '',
	'scheduled_to' => '',
	'activity_scope' => '',
	'active' => '1',
];
$page = $page ?? 1;
$totalPages = $totalPages ?? 1;
$total = $total ?? (is_array($activities) ? count($activities) : 0);
$sort = (string) ($sort ?? 'scheduled_at');
$direction = (string) ($direction ?? 'asc');
$queryFilters = http_build_query(array_merge($filters, [
	'sort' => $sort,
	'direction' => $direction,
]));
$sortUrl = static function (string $column) use ($filters, $sort, $direction): string {
	$nextDirection = ($sort === $column && $direction === 'asc') ? 'desc' : 'asc';
	return url('crm_activity?' . http_build_query(array_merge($filters, [
		'sort' => $column,
		'direction' => $nextDirection,
	])));
};
$sortIcon = static function (string $column) use ($sort, $direction): string {
	if ($sort !== $column)
		{
		return 'bi-arrow-down-up';
		}

	return $direction === 'asc' ? 'bi-sort-up' : 'bi-sort-down';
};
?>

<div class="app-page">
  <div class="app-page-header">
    <div>
      <span class="app-page-kicker"><i class="bi bi-clock-history"></i> CRM</span>
      <h5>Atividades CRM</h5>
    </div>
  </div>

  <?php if (!empty($formError)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars((string) $formError, ENT_QUOTES, 'UTF-8'); ?></div>
  <?php endif; ?>

  <form action="<?= url('crm_activity'); ?>" class="row g-3 mb-3" method="get">
    <input name="sort" type="hidden" value="<?= htmlspecialchars($sort, ENT_QUOTES, 'UTF-8'); ?>">
    <input name="direction" type="hidden" value="<?= htmlspecialchars($direction, ENT_QUOTES, 'UTF-8'); ?>">

    <div class="col-12 col-lg-9">
      <div class="row g-3">
        <div class="col-12 col-md-2">
          <input class="form-control" id="id" name="id" placeholder="Código" type="text" value="<?= htmlspecialchars($filters['id'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="col-12 col-md-4">
          <input class="form-control" id="personName" maxlength="255" name="person_name" placeholder="Pessoa" type="text" value="<?= htmlspecialchars($filters['person_name'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="col-12 col-md-3">
          <select class="form-select" id="activityTypeId" name="activity_type_id">
            <option value="" <?= $filters['activity_type_id'] === '' ? 'selected' : ''; ?>>Todos os tipos</option>
            <?php foreach ($activityTypes as $type): ?>
              <option value="<?= (int) $type['id']; ?>" <?= (string) $filters['activity_type_id'] === (string) $type['id'] ? 'selected' : ''; ?>><?= htmlspecialchars((string) ($type['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12 col-md-3">
          <select class="form-select" id="activityStatusId" name="activity_status_id">
            <option value="" <?= $filters['activity_status_id'] === '' ? 'selected' : ''; ?>>Todos os status</option>
            <?php foreach ($activityStatuses as $status): ?>
              <option value="<?= (int) $status['id']; ?>" <?= (string) $filters['activity_status_id'] === (string) $status['id'] ? 'selected' : ''; ?>><?= htmlspecialchars((string) ($status['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12 col-md-4">
          <select class="form-select" id="responsibleUserId" name="responsible_user_id">
            <option value="" <?= $filters['responsible_user_id'] === '' ? 'selected' : ''; ?>>Todos os responsáveis</option>
            <?php foreach ($owners as $owner): ?>
              <?php $ownerLabel = trim((string) ($owner['person_name'] ?? '')) ?: (string) ($owner['username'] ?? ''); ?>
              <option value="<?= (int) $owner['id']; ?>" <?= (string) $filters['responsible_user_id'] === (string) $owner['id'] ? 'selected' : ''; ?>><?= htmlspecialchars($ownerLabel, ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12 col-md-4">
          <select class="form-select" id="activityScope" name="activity_scope">
            <option value="" <?= $filters['activity_scope'] === '' ? 'selected' : ''; ?>>Todos os acompanhamentos</option>
            <?php foreach ($activityScopes as $value => $label): ?>
              <?php if ((string) $value !== 'without_activity'): ?>
                <option value="<?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); ?>" <?= (string) $filters['activity_scope'] === (string) $value ? 'selected' : ''; ?>><?= htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8'); ?></option>
              <?php endif; ?>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12 col-md-4">
          <select class="form-select" id="active" name="active">
            <option value="" <?= $filters['active'] === '' ? 'selected' : ''; ?>>Ativas e inativas</option>
            <option value="1" <?= $filters['active'] === '1' ? 'selected' : ''; ?>>Ativas</option>
            <option value="0" <?= $filters['active'] === '0' ? 'selected' : ''; ?>>Inativas</option>
          </select>
        </div>

        <div class="col-12 col-md-6">
          <label class="form-label" for="scheduledFrom">Agendada de</label>
          <input class="form-control" id="scheduledFrom" name="scheduled_from" type="date" value="<?= htmlspecialchars($filters['scheduled_from'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="col-12 col-md-6">
          <label class="form-label" for="scheduledTo">Agendada até</label>
          <input class="form-control" id="scheduledTo" name="scheduled_to" type="date" value="<?= htmlspecialchars($filters['scheduled_to'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-3">
      <div class="d-flex flex-column gap-2 align-items-center align-items-lg-end">
        <button type="submit" class="btn btn-primary w-100">Pesquisar</button>
        <a href="<?= url('crm_activity'); ?>" class="btn btn-secondary w-100">Limpar</a>
        <?php if (authCan('crm_activity.create')): ?>
          <a href="<?= url('crm_activity/create'); ?>" class="btn btn-success w-100">Novo(a)</a>
        <?php endif; ?>
      </div>
    </div>
  </form>

  <hr>

  <div class="table-responsive">
    <table class="table table-striped table-hover align-middle">
      <thead>
        <tr>
          <th><a class="sort-link" href="<?= $sortUrl('id'); ?>"># <i class="bi <?= htmlspecialchars($sortIcon('id'), ENT_QUOTES, 'UTF-8'); ?>"></i></a></th>
          <th><a class="sort-link" href="<?= $sortUrl('person_name'); ?>">Pessoa <i class="bi <?= htmlspecialchars($sortIcon('person_name'), ENT_QUOTES, 'UTF-8'); ?>"></i></a></th>
          <th>Título</th>
          <th><a class="sort-link" href="<?= $sortUrl('activity_type'); ?>">Tipo <i class="bi <?= htmlspecialchars($sortIcon('activity_type'), ENT_QUOTES, 'UTF-8'); ?>"></i></a></th>
          <th><a class="sort-link" href="<?= $sortUrl('activity_status'); ?>">Status <i class="bi <?= htmlspecialchars($sortIcon('activity_status'), ENT_QUOTES, 'UTF-8'); ?>"></i></a></th>
          <th><a class="sort-link" href="<?= $sortUrl('scheduled_at'); ?>">Agendada <i class="bi <?= htmlspecialchars($sortIcon('scheduled_at'), ENT_QUOTES, 'UTF-8'); ?>"></i></a></th>
          <th>Responsável</th>
          <th class="text-end">Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($activities)): ?>
          <?php foreach ($activities as $activity): ?>
            <?php $responsibleName = trim((string) ($activity['responsible_name'] ?? '')) ?: (string) ($activity['responsible_username'] ?? ''); ?>
            <tr>
              <td style="width:1%;"><?= (int) $activity['id']; ?></td>
              <td>
                <div class="fw-semibold"><?= htmlspecialchars((string) ($activity['person_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                <small class="text-muted"><?= htmlspecialchars(maskCpfCnpj((string) ($activity['person_document'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></small>
              </td>
              <td><?= htmlspecialchars((string) ($activity['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><span class="badge <?= htmlspecialchars((string) ($activity['activity_type_bootstrap_class'] ?? 'text-bg-secondary'), ENT_QUOTES, 'UTF-8'); ?>"><i class="bi <?= htmlspecialchars((string) ($activity['activity_type_icon'] ?? 'bi-sticky'), ENT_QUOTES, 'UTF-8'); ?>"></i> <?= htmlspecialchars((string) ($activity['activity_type_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span></td>
              <td><span class="badge <?= htmlspecialchars((string) ($activity['activity_status_bootstrap_class'] ?? 'text-bg-secondary'), ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars((string) ($activity['activity_status_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span></td>
              <td><?= htmlspecialchars(\App\Models\Crm::formatDateTime($activity['scheduled_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?= htmlspecialchars($responsibleName, ENT_QUOTES, 'UTF-8'); ?></td>
              <td style="width:1%;" class="text-nowrap text-end">
                <?php if (authCan('crm_activity.complete') && (int) ($activity['activity_status_is_final'] ?? 0) !== 1 && (int) ($activity['active'] ?? 0) === 1): ?>
                  <form action="<?= url('crm_activity/complete/' . (int) $activity['id']); ?>" method="POST" class="d-inline">
                    <button type="submit" class="btn btn-link btn-sm p-0 text-success" title="Concluir" aria-label="Concluir atividade"><i class="bi bi-check2-circle"></i></button>
                  </form>
                <?php endif; ?>
                <?php if (authCan('crm_activity.cancel') && (int) ($activity['activity_status_is_final'] ?? 0) !== 1 && (int) ($activity['active'] ?? 0) === 1): ?>
                  <form action="<?= url('crm_activity/cancel/' . (int) $activity['id']); ?>" method="POST" class="d-inline">
                    <button type="submit" class="btn btn-link btn-sm p-0 text-secondary" title="Cancelar" aria-label="Cancelar atividade"><i class="bi bi-x-circle"></i></button>
                  </form>
                <?php endif; ?>
                <?php if (authCan('crm_activity.delete') && (int) ($activity['active'] ?? 0) === 1): ?>
                  <a href="#"
                     class="js-record-delete-link text-danger"
                     data-bs-toggle="modal"
                     data-bs-target="#recordDeleteModal"
                     data-record-id="<?= (int) $activity['id']; ?>"
                     data-record-name="<?= htmlspecialchars((string) ($activity['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                     data-record-delete-description="a atividade CRM"
                     data-record-delete-url="<?= url('crm_activity/delete/' . (int) $activity['id']); ?>"
                     data-bs-content="Desativar"
                     data-bs-trigger="hover focus"
                     role="button"
                     aria-label="Desativar atividade CRM">
                    <i class="bi bi-trash"></i>
                  </a>
                <?php endif; ?>
                <?php if (authCan('crm_activity.edit')): ?>
                  <a href="<?= url('crm_activity/edit/' . (int) $activity['id']); ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Editar"><i class="bi bi-pencil-square"></i></a>
                <?php endif; ?>
                <?php if (authCan('crm_activity.informations')): ?>
                  <a href="<?= url('crm_activity/informations/' . (int) $activity['id']); ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Saiba mais"><i class="bi bi-search"></i></a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="8" class="text-center">Nenhuma atividade encontrada.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <p class="text-muted"><?= (int) $total; ?> registro(s) encontrado(s)</p>

  <?php if ($totalPages > 1): ?>
    <?= paginationLinks('crm_activity', $page, $totalPages, $queryFilters, 2, 'Anterior', 'Próxima'); ?>
  <?php endif; ?>
</div>

<?php if (authCan('crm_activity.delete')): ?>
  <?php require __DIR__ . '/../shared/delete_modal.php'; ?>
<?php endif; ?>

<script src="<?= url('assets/js/shared/delete_modal.js?v=1'); ?>"></script>
