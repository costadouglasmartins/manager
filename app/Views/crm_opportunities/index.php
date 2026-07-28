<?php
$filters = $filters ?? [
	'id' => '',
	'tenant_person_id' => '',
	'person_name' => '',
	'crm_source_id' => '',
	'opportunity_type_id' => '',
	'pipeline_stage_id' => '',
	'loss_reason_id' => '',
	'responsible_user_id' => '',
	'expected_from' => '',
	'expected_to' => '',
	'result' => '',
	'active' => '1',
];
$page = $page ?? 1;
$totalPages = $totalPages ?? 1;
$total = $total ?? (is_array($opportunities) ? count($opportunities) : 0);
$sort = (string) ($sort ?? 'modified');
$direction = (string) ($direction ?? 'desc');
$queryFilters = http_build_query(array_merge($filters, [
	'sort' => $sort,
	'direction' => $direction,
]));
$sortUrl = static function (string $column) use ($filters, $sort, $direction): string {
	$nextDirection = ($sort === $column && $direction === 'asc') ? 'desc' : 'asc';
	return url('crm_opportunity?' . http_build_query(array_merge($filters, [
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
$money = static fn ($value): string => 'R$ ' . number_format((float) ($value ?? 0), 2, ',', '.');
?>

<div class="app-page">
  <div class="app-page-header">
    <div>
      <span class="app-page-kicker"><i class="bi bi-currency-dollar"></i> CRM</span>
      <h5>Oportunidades CRM</h5>
    </div>
  </div>

  <form action="<?= url('crm_opportunity'); ?>" class="row g-3 mb-3" method="get">
    <input name="sort" type="hidden" value="<?= htmlspecialchars($sort, ENT_QUOTES, 'UTF-8'); ?>">
    <input name="direction" type="hidden" value="<?= htmlspecialchars($direction, ENT_QUOTES, 'UTF-8'); ?>">

    <div class="col-12 col-lg-9">
      <div class="row g-3">
        <div class="col-12 col-md-2">
          <input class="form-control" name="id" placeholder="Código" type="text" value="<?= htmlspecialchars($filters['id'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="col-12 col-md-4">
          <input class="form-control" name="person_name" placeholder="Pessoa" type="text" value="<?= htmlspecialchars($filters['person_name'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="col-12 col-md-3">
          <select class="form-select" name="pipeline_stage_id">
            <option value="" <?= $filters['pipeline_stage_id'] === '' ? 'selected' : ''; ?>>Todas as etapas</option>
            <?php foreach ($pipelineStages as $stage): ?>
              <option value="<?= (int) $stage['id']; ?>" <?= (string) $filters['pipeline_stage_id'] === (string) $stage['id'] ? 'selected' : ''; ?>><?= htmlspecialchars((string) ($stage['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12 col-md-3">
          <select class="form-select" name="result">
            <option value="" <?= $filters['result'] === '' ? 'selected' : ''; ?>>Todos os resultados</option>
            <option value="open" <?= $filters['result'] === 'open' ? 'selected' : ''; ?>>Em aberto</option>
            <option value="won" <?= $filters['result'] === 'won' ? 'selected' : ''; ?>>Ganhas</option>
            <option value="lost" <?= $filters['result'] === 'lost' ? 'selected' : ''; ?>>Perdidas</option>
          </select>
        </div>
        <div class="col-12 col-md-3">
          <select class="form-select" name="crm_source_id">
            <option value="" <?= $filters['crm_source_id'] === '' ? 'selected' : ''; ?>>Todas as origens</option>
            <?php foreach ($sources as $source): ?>
              <option value="<?= (int) $source['id']; ?>" <?= (string) $filters['crm_source_id'] === (string) $source['id'] ? 'selected' : ''; ?>><?= htmlspecialchars((string) ($source['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12 col-md-3">
          <select class="form-select" name="opportunity_type_id">
            <option value="" <?= $filters['opportunity_type_id'] === '' ? 'selected' : ''; ?>>Todos os tipos</option>
            <?php foreach ($opportunityTypes as $type): ?>
              <option value="<?= (int) $type['id']; ?>" <?= (string) $filters['opportunity_type_id'] === (string) $type['id'] ? 'selected' : ''; ?>><?= htmlspecialchars((string) ($type['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12 col-md-3">
          <select class="form-select" name="responsible_user_id">
            <option value="" <?= $filters['responsible_user_id'] === '' ? 'selected' : ''; ?>>Todos os responsáveis</option>
            <?php foreach ($owners as $owner): ?>
              <?php $ownerLabel = trim((string) ($owner['person_name'] ?? '')) ?: (string) ($owner['username'] ?? ''); ?>
              <option value="<?= (int) $owner['id']; ?>" <?= (string) $filters['responsible_user_id'] === (string) $owner['id'] ? 'selected' : ''; ?>><?= htmlspecialchars($ownerLabel, ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12 col-md-3">
          <select class="form-select" name="active">
            <option value="" <?= $filters['active'] === '' ? 'selected' : ''; ?>>Ativas e inativas</option>
            <option value="1" <?= $filters['active'] === '1' ? 'selected' : ''; ?>>Ativas</option>
            <option value="0" <?= $filters['active'] === '0' ? 'selected' : ''; ?>>Inativas</option>
          </select>
        </div>
        <div class="col-12 col-md-6">
          <label class="form-label" for="expectedFrom">Fechamento previsto de</label>
          <input class="form-control" id="expectedFrom" name="expected_from" type="date" value="<?= htmlspecialchars($filters['expected_from'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="col-12 col-md-6">
          <label class="form-label" for="expectedTo">Fechamento previsto até</label>
          <input class="form-control" id="expectedTo" name="expected_to" type="date" value="<?= htmlspecialchars($filters['expected_to'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-3">
      <div class="d-flex flex-column gap-2 align-items-center align-items-lg-end">
        <button type="submit" class="btn btn-primary w-100">Pesquisar</button>
        <a href="<?= url('crm_opportunity'); ?>" class="btn btn-secondary w-100">Limpar</a>
        <?php if (authCan('crm_opportunity.create')): ?>
          <a href="<?= url('crm_opportunity/create'); ?>" class="btn btn-success w-100">Nova oportunidade</a>
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
          <th><a class="sort-link" href="<?= $sortUrl('title'); ?>">Oportunidade <i class="bi <?= htmlspecialchars($sortIcon('title'), ENT_QUOTES, 'UTF-8'); ?>"></i></a></th>
          <th><a class="sort-link" href="<?= $sortUrl('pipeline_stage'); ?>">Etapa <i class="bi <?= htmlspecialchars($sortIcon('pipeline_stage'), ENT_QUOTES, 'UTF-8'); ?>"></i></a></th>
          <th class="text-end"><a class="sort-link" href="<?= $sortUrl('estimated_value'); ?>">Valor <i class="bi <?= htmlspecialchars($sortIcon('estimated_value'), ENT_QUOTES, 'UTF-8'); ?>"></i></a></th>
          <th><a class="sort-link" href="<?= $sortUrl('expected_close_date'); ?>">Previsão <i class="bi <?= htmlspecialchars($sortIcon('expected_close_date'), ENT_QUOTES, 'UTF-8'); ?>"></i></a></th>
          <th>Responsável</th>
          <th class="text-end">Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($opportunities)): ?>
          <?php foreach ($opportunities as $opportunity): ?>
            <?php $responsibleName = trim((string) ($opportunity['responsible_name'] ?? '')) ?: (string) ($opportunity['responsible_username'] ?? ''); ?>
            <tr>
              <td style="width:1%;"><?= (int) $opportunity['id']; ?></td>
              <td>
                <div class="fw-semibold"><?= htmlspecialchars((string) ($opportunity['person_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                <small class="text-muted"><?= htmlspecialchars(maskCpfCnpj((string) ($opportunity['person_document'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></small>
              </td>
              <td>
                <div class="fw-semibold"><?= htmlspecialchars((string) ($opportunity['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                <?php if (trim((string) ($opportunity['opportunity_type_name'] ?? '')) !== ''): ?>
                  <small class="text-muted"><?= htmlspecialchars((string) $opportunity['opportunity_type_name'], ENT_QUOTES, 'UTF-8'); ?></small>
                <?php endif; ?>
              </td>
              <td><span class="badge <?= htmlspecialchars((string) ($opportunity['pipeline_stage_bootstrap_class'] ?? 'text-bg-secondary'), ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars((string) ($opportunity['pipeline_stage_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span></td>
              <td class="text-end"><?= htmlspecialchars($money($opportunity['estimated_value'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?= htmlspecialchars(maskDateBr((string) ($opportunity['expected_close_date'] ?? '')) ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?= htmlspecialchars($responsibleName, ENT_QUOTES, 'UTF-8'); ?></td>
              <td style="width:1%;" class="text-nowrap text-end">
                <?php if (authCan('crm_opportunity.delete') && (int) ($opportunity['active'] ?? 0) === 1): ?>
                  <a href="#"
                     class="js-record-delete-link text-danger"
                     data-bs-toggle="modal"
                     data-bs-target="#recordDeleteModal"
                     data-record-id="<?= (int) $opportunity['id']; ?>"
                     data-record-name="<?= htmlspecialchars((string) ($opportunity['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                     data-record-delete-description="a oportunidade CRM"
                     data-record-delete-url="<?= url('crm_opportunity/delete/' . (int) $opportunity['id']); ?>"
                     data-bs-content="Desativar"
                     data-bs-trigger="hover focus"
                     role="button"
                     aria-label="Desativar oportunidade CRM">
                    <i class="bi bi-trash"></i>
                  </a>
                <?php endif; ?>
                <?php if (authCan('crm_opportunity.edit')): ?>
                  <a href="<?= url('crm_opportunity/edit/' . (int) $opportunity['id']); ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Editar"><i class="bi bi-pencil-square"></i></a>
                <?php endif; ?>
                <?php if (authCan('crm_opportunity.informations')): ?>
                  <a href="<?= url('crm_opportunity/informations/' . (int) $opportunity['id']); ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Saiba mais"><i class="bi bi-search"></i></a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="8" class="text-center">Nenhuma oportunidade encontrada.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <p class="text-muted"><?= (int) $total; ?> registro(s) encontrado(s)</p>

  <?php if ($totalPages > 1): ?>
    <?= paginationLinks('crm_opportunity', $page, $totalPages, $queryFilters, 2, 'Anterior', 'Próxima'); ?>
  <?php endif; ?>
</div>

<?php if (authCan('crm_opportunity.delete')): ?>
  <?php require __DIR__ . '/../shared/delete_modal.php'; ?>
<?php endif; ?>

<script src="<?= url('assets/js/shared/delete_modal.js?v=1'); ?>"></script>
