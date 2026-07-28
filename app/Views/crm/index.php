<?php
$filters = $filters ?? [
	'id' => '',
	'name' => '',
	'document' => '',
	'type' => '',
	'crm_status' => '',
	'owner_user_id' => '',
	'active' => '1',
];
$page = $page ?? 1;
$totalPages = $totalPages ?? 1;
$total = $total ?? (is_array($crmPeople) ? count($crmPeople) : 0);
$sort = (string) ($sort ?? 'modified');
$direction = (string) ($direction ?? 'desc');
$queryFilters = http_build_query([
	'id' => $filters['id'],
	'name' => $filters['name'],
	'document' => $filters['document'],
	'type' => $filters['type'],
	'crm_status' => $filters['crm_status'],
	'owner_user_id' => $filters['owner_user_id'],
	'active' => $filters['active'],
	'sort' => $sort,
	'direction' => $direction,
]);
$sortUrl = static function (string $column) use ($filters, $sort, $direction): string {
	$nextDirection = ($sort === $column && $direction === 'asc') ? 'desc' : 'asc';
	return url('crm?' . http_build_query([
		'id' => $filters['id'],
		'name' => $filters['name'],
		'document' => $filters['document'],
		'type' => $filters['type'],
		'crm_status' => $filters['crm_status'],
		'owner_user_id' => $filters['owner_user_id'],
		'active' => $filters['active'],
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

<div class="app-page">
  <div class="app-page-header">
    <div>
      <span class="app-page-kicker"><i class="bi bi-briefcase"></i> CRM</span>
      <h5>Carteira comercial</h5>
    </div>
  </div>

  <?php if (!empty($formError)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars((string) $formError, ENT_QUOTES, 'UTF-8'); ?></div>
  <?php endif; ?>

  <form action="<?= url('crm'); ?>" class="row g-3 mb-3" method="get">
    <input name="sort" type="hidden" value="<?= htmlspecialchars($sort, ENT_QUOTES, 'UTF-8'); ?>">
    <input name="direction" type="hidden" value="<?= htmlspecialchars($direction, ENT_QUOTES, 'UTF-8'); ?>">

    <div class="col-12 col-lg-9">
      <div class="row g-3">
        <div class="col-12 col-md-2">
          <input class="form-control" id="id" name="id" placeholder="Código" type="text" value="<?= htmlspecialchars($filters['id'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="col-12 col-md-4">
          <input class="form-control" id="name" maxlength="255" name="name" placeholder="Nome ou razão social" type="text" value="<?= htmlspecialchars($filters['name'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="col-12 col-md-3">
          <input autocomplete="off" class="form-control mask-cpf-cnpj" id="document" maxlength="18" name="document" placeholder="CPF ou CNPJ" type="text" value="<?= htmlspecialchars(maskCpfCnpj($filters['document']), ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="col-12 col-md-3">
          <select class="form-select" id="type" name="type">
            <option value="" <?= $filters['type'] === '' ? 'selected' : ''; ?>>Todos os tipos</option>
            <?php foreach ($personTypes as $value => $label): ?>
              <option value="<?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); ?>" <?= $filters['type'] === (string) $value ? 'selected' : ''; ?>><?= htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12 col-md-4">
          <select class="form-select" id="crm_status" name="crm_status">
            <option value="" <?= $filters['crm_status'] === '' ? 'selected' : ''; ?>>Todas as situações</option>
            <?php foreach ($statuses as $value => $config): ?>
              <option value="<?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); ?>" <?= $filters['crm_status'] === (string) $value ? 'selected' : ''; ?>><?= htmlspecialchars((string) ($config['label'] ?? $value), ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12 col-md-4">
          <select class="form-select" id="owner_user_id" name="owner_user_id">
            <option value="" <?= $filters['owner_user_id'] === '' ? 'selected' : ''; ?>>Todos os responsáveis</option>
            <?php foreach ($owners as $owner): ?>
              <?php $ownerLabel = trim((string) ($owner['person_name'] ?? '')) ?: (string) ($owner['username'] ?? ''); ?>
              <option value="<?= (int) $owner['id']; ?>" <?= ((string) $filters['owner_user_id'] === (string) $owner['id']) ? 'selected' : ''; ?>><?= htmlspecialchars($ownerLabel, ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12 col-md-4">
          <select class="form-select" id="active" name="active">
            <option value="" <?= $filters['active'] === '' ? 'selected' : ''; ?>>Ativos e inativos</option>
            <option value="1" <?= $filters['active'] === '1' ? 'selected' : ''; ?>>Ativos</option>
            <option value="0" <?= $filters['active'] === '0' ? 'selected' : ''; ?>>Inativos</option>
          </select>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-3">
      <div class="d-flex flex-column gap-2 align-items-center align-items-lg-end">
        <button type="submit" class="btn btn-primary w-100">Pesquisar</button>
        <a href="<?= url('crm'); ?>" class="btn btn-secondary w-100">Limpar</a>
        <?php if (authCan('crm.create')): ?>
          <a href="<?= url('crm/create'); ?>" class="btn btn-success w-100">Novo(a)</a>
        <?php endif; ?>
      </div>
    </div>
  </form>

  <div class="d-flex flex-wrap gap-2 mb-3">
    <?php if (authCan('crm.index')): ?>
      <?= pdfExportButton('crm', $queryFilters); ?>
      <?= excelExportButton('crm', $queryFilters); ?>
    <?php endif; ?>
  </div>

  <hr>

  <div class="table-responsive">
    <table class="table table-striped table-hover align-middle">
      <thead>
        <tr>
          <th><a class="sort-link" href="<?= $sortUrl('id'); ?>"># <i class="bi <?= htmlspecialchars($sortIcon('id'), ENT_QUOTES, 'UTF-8'); ?>"></i></a></th>
          <th><a class="sort-link" href="<?= $sortUrl('name'); ?>">Pessoa <i class="bi <?= htmlspecialchars($sortIcon('name'), ENT_QUOTES, 'UTF-8'); ?>"></i></a></th>
          <th><a class="sort-link" href="<?= $sortUrl('document'); ?>">Documento <i class="bi <?= htmlspecialchars($sortIcon('document'), ENT_QUOTES, 'UTF-8'); ?>"></i></a></th>
          <th><a class="sort-link" href="<?= $sortUrl('crm_status'); ?>">Situação <i class="bi <?= htmlspecialchars($sortIcon('crm_status'), ENT_QUOTES, 'UTF-8'); ?>"></i></a></th>
          <th><a class="sort-link" href="<?= $sortUrl('owner'); ?>">Responsável <i class="bi <?= htmlspecialchars($sortIcon('owner'), ENT_QUOTES, 'UTF-8'); ?>"></i></a></th>
          <th>Contato</th>
          <th><a class="sort-link" href="<?= $sortUrl('modified'); ?>">Atualizado em <i class="bi <?= htmlspecialchars($sortIcon('modified'), ENT_QUOTES, 'UTF-8'); ?>"></i></a></th>
          <th class="text-end">Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($crmPeople)): ?>
          <?php foreach ($crmPeople as $row): ?>
            <?php $ownerName = trim((string) ($row['owner_name'] ?? '')) ?: (string) ($row['owner_username'] ?? ''); ?>
            <tr>
              <td style="width:1%;"><?= (int) $row['id']; ?></td>
              <td>
                <div class="fw-semibold"><?= htmlspecialchars((string) ($row['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                <small class="text-muted"><?= htmlspecialchars(\App\Models\Crm::personTypeLabel((string) ($row['type'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></small>
              </td>
              <td><?= htmlspecialchars(maskCpfCnpj((string) ($row['document'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><span class="badge <?= htmlspecialchars(\App\Models\Crm::statusBadgeClass((string) ($row['crm_status'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars(\App\Models\Crm::statusLabel((string) ($row['crm_status'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></span></td>
              <td><?= htmlspecialchars($ownerName, ENT_QUOTES, 'UTF-8'); ?></td>
              <td>
                <div><?= htmlspecialchars((string) ($row['primary_email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                <small class="text-muted"><?= htmlspecialchars(maskPhoneBr((string) ($row['primary_phone'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></small>
              </td>
              <td><?= htmlspecialchars(\App\Models\Crm::formatDateTime($row['modified'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td style="width:1%;" class="text-nowrap text-end">
                <?php if (authCan('crm.delete') && (int) ($row['active'] ?? 0) === 1): ?>
                  <a href="#"
                     class="js-record-delete-link text-danger"
                     data-bs-toggle="modal"
                     data-bs-target="#recordDeleteModal"
                     data-record-id="<?= (int) $row['id']; ?>"
                     data-record-name="<?= htmlspecialchars((string) ($row['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                     data-record-delete-description="o vínculo CRM de"
                     data-record-delete-url="<?= url('crm/delete/' . (int) $row['id']); ?>"
                     data-bs-content="Desativar"
                     data-bs-trigger="hover focus"
                     role="button"
                     aria-label="Desativar vínculo CRM">
                    <i class="bi bi-trash"></i>
                  </a>
                <?php endif; ?>
                <?php if (authCan('crm.edit')): ?>
                  <a href="<?= url('crm/edit/' . (int) $row['id']); ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Editar"><i class="bi bi-pencil-square"></i></a>
                <?php endif; ?>
                <?php if (authCan('crm.informations')): ?>
                  <a href="<?= url('crm/informations/' . (int) $row['id']); ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Saiba mais"><i class="bi bi-search"></i></a>
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
    <?= paginationLinks('crm', $page, $totalPages, $queryFilters, 2, 'Anterior', 'Próxima'); ?>
  <?php endif; ?>
</div>

<?php if (authCan('crm.delete')): ?>
  <?php require __DIR__ . '/../shared/delete_modal.php'; ?>
<?php endif; ?>

<script src="<?= url('assets/js/shared/delete_modal.js?v=1'); ?>"></script>
