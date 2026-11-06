<?php
// Variables expected from HrEmployeeController::index: $filters, $page, $perPage, $total, $totalPages, $sort, $direction,
// $employees (rows; $data is accepted as an alias), $hrDepartments, $hrPositions.
$esc = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$filters = array_merge([
	'id' => '',
	'name' => '',
	'hr_department_id' => '',
	'hr_position_id' => '',
	'employment_type' => '',
	'status' => '',
	'active' => '',
], (isset($filters) && is_array($filters)) ? $filters : []);
$rows = (isset($employees) && is_array($employees)) ? $employees : ((isset($data) && is_array($data)) ? $data : []);
$hrDepartments = (isset($hrDepartments) && is_array($hrDepartments)) ? $hrDepartments : [];
$hrPositions = (isset($hrPositions) && is_array($hrPositions)) ? $hrPositions : [];
$page = max(1, (int) ($page ?? 1));
$perPage = (int) ($perPage ?? 0);
$totalPages = max(1, (int) ($totalPages ?? 1));
$total = (int) ($total ?? count($rows));
$sort = (string) ($sort ?? 'name');
$direction = (string) ($direction ?? 'asc');
$employmentTypes = ['clt' => 'CLT', 'pj' => 'PJ', 'estagio' => 'Estágio', 'temporario' => 'Temporário'];
$statusLabels = ['active' => 'Ativo', 'on_leave' => 'Afastado', 'terminated' => 'Desligado'];
$statusClasses = ['active' => 'text-bg-success', 'on_leave' => 'text-bg-warning', 'terminated' => 'text-bg-secondary'];

// Filters shared by the search form, sort links, pagination and exports.
$filterQuery = [];
foreach (['id', 'name', 'hr_department_id', 'hr_position_id', 'employment_type', 'status', 'active'] as $filterKey)
	{
	$filterQuery[$filterKey] = $filters[$filterKey];
	}
$queryFilters = http_build_query($filterQuery + ['sort' => $sort, 'direction' => $direction]);

// Builds a sort link that toggles the direction of the clicked column.
$sortUrl = static function (string $column) use ($filterQuery, $sort, $direction): string {
	$nextDirection = ($sort === $column && $direction === 'asc') ? 'desc' : 'asc';
	return url('hr_employee?' . http_build_query($filterQuery + ['sort' => $column, 'direction' => $nextDirection]));
};
$sortIcon = static function (string $column) use ($sort, $direction): string {
	if ($sort !== $column)
		{
		return 'bi-arrow-down-up';
		}

	return $direction === 'asc' ? 'bi-sort-up' : 'bi-sort-down';
};

// Reads a row field accepting both snake_case and PascalCase aliases.
$field = static function (array $row, string $snake, string $pascal) {
	return $row[$snake] ?? $row[$pascal] ?? '';
};
$dateBr = static function ($value): string {
	$value = trim((string) $value);
	return preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $value, $m) === 1 ? $m[3] . '/' . $m[2] . '/' . $m[1] : $value;
};
?>
<link rel="stylesheet" href="<?= url('assets/css/hr_employees/index.css?v=1'); ?>">

<div class="app-page">
  <div class="app-page-header">
    <div>
      <span class="app-page-kicker"><i class="bi bi-briefcase" aria-hidden="true"></i> RH</span>
      <h5>Funcionários</h5>
    </div>
  </div>

  <?php if (!empty($formError)): ?>
    <div class="alert alert-danger" role="alert"><?= $esc($formError); ?></div>
  <?php endif; ?>

  <form action="<?= url('hr_employee'); ?>" class="row g-3" method="get">
    <input name="sort" type="hidden" value="<?= $esc($sort); ?>">
    <input name="direction" type="hidden" value="<?= $esc($direction); ?>">

    <div class="col-12 col-lg-9">
      <div class="row g-3">
        <div class="col-12 col-md-2">
          <label class="visually-hidden" for="filterId">Código</label>
          <input class="form-control" id="filterId" name="id" placeholder="Código" type="text" inputmode="numeric" value="<?= $esc($filters['id']); ?>">
        </div>

        <div class="col-12 col-md-4">
          <label class="visually-hidden" for="filterName">Nome</label>
          <input class="form-control" id="filterName" maxlength="160" name="name" placeholder="Nome do funcionário" type="text" value="<?= $esc($filters['name']); ?>">
        </div>

        <div class="col-12 col-md-3">
          <label class="visually-hidden" for="filterDepartment">Departamento</label>
          <select class="form-select" id="filterDepartment" name="hr_department_id">
            <option value="">Todos os departamentos</option>
            <?php foreach ($hrDepartments as $department): ?>
              <option value="<?= (int) $department['id']; ?>" <?= (string) $filters['hr_department_id'] === (string) $department['id'] ? 'selected' : ''; ?>><?= $esc($department['name'] ?? ''); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12 col-md-3">
          <label class="visually-hidden" for="filterPosition">Cargo</label>
          <select class="form-select" id="filterPosition" name="hr_position_id">
            <option value="">Todos os cargos</option>
            <?php foreach ($hrPositions as $position): ?>
              <option value="<?= (int) $position['id']; ?>" <?= (string) $filters['hr_position_id'] === (string) $position['id'] ? 'selected' : ''; ?>><?= $esc($position['name'] ?? ''); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12 col-md-4">
          <label class="visually-hidden" for="filterType">Tipo de contrato</label>
          <select class="form-select" id="filterType" name="employment_type">
            <option value="">Todos os contratos</option>
            <?php foreach ($employmentTypes as $value => $label): ?>
              <option value="<?= $esc($value); ?>" <?= (string) $filters['employment_type'] === $value ? 'selected' : ''; ?>><?= $esc($label); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12 col-md-4">
          <label class="visually-hidden" for="filterStatus">Situação</label>
          <select class="form-select" id="filterStatus" name="status">
            <option value="">Todas as situações</option>
            <?php foreach ($statusLabels as $value => $label): ?>
              <option value="<?= $esc($value); ?>" <?= (string) $filters['status'] === $value ? 'selected' : ''; ?>><?= $esc($label); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12 col-md-4">
          <label class="visually-hidden" for="filterActive">Vínculo</label>
          <select class="form-select" id="filterActive" name="active">
            <option value="" <?= (string) $filters['active'] === '' ? 'selected' : ''; ?>>Ativos e inativos</option>
            <option value="1" <?= (string) $filters['active'] === '1' ? 'selected' : ''; ?>>Somente ativos</option>
            <option value="0" <?= (string) $filters['active'] === '0' ? 'selected' : ''; ?>>Somente inativos</option>
          </select>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-3">
      <div class="d-flex flex-column gap-2 align-items-center align-items-lg-end">
        <button class="btn btn-primary w-100" type="submit">Pesquisar</button>
        <a class="btn btn-secondary w-100" href="<?= url('hr_employee'); ?>">Limpar</a>
        <?php if (authCan('hr_employee.create')): ?>
          <a class="btn btn-success w-100" href="<?= url('hr_employee/create'); ?>">Novo(a)</a>
        <?php endif; ?>
      </div>
    </div>
  </form>

  <div class="d-flex flex-wrap gap-2">
    <?php if (authCan('hr_employee.index')): ?>
      <?= pdfExportButton('hr_employee', $queryFilters); ?>
      <?= excelExportButton('hr_employee', $queryFilters); ?>
    <?php endif; ?>
  </div>

  <div class="table-responsive">
    <table class="table table-striped table-hover align-middle">
      <thead>
        <tr>
          <th><a class="sort-link" href="<?= $esc($sortUrl('id')); ?>"># <i class="bi <?= $esc($sortIcon('id')); ?>" aria-hidden="true"></i></a></th>
          <th><a class="sort-link" href="<?= $esc($sortUrl('name')); ?>">Funcionário <i class="bi <?= $esc($sortIcon('name')); ?>" aria-hidden="true"></i></a></th>
          <th><a class="sort-link" href="<?= $esc($sortUrl('department')); ?>">Departamento <i class="bi <?= $esc($sortIcon('department')); ?>" aria-hidden="true"></i></a></th>
          <th><a class="sort-link" href="<?= $esc($sortUrl('position')); ?>">Cargo <i class="bi <?= $esc($sortIcon('position')); ?>" aria-hidden="true"></i></a></th>
          <th>Matrícula</th>
          <th><a class="sort-link" href="<?= $esc($sortUrl('admission_date')); ?>">Admissão <i class="bi <?= $esc($sortIcon('admission_date')); ?>" aria-hidden="true"></i></a></th>
          <th><a class="sort-link" href="<?= $esc($sortUrl('status')); ?>">Situação <i class="bi <?= $esc($sortIcon('status')); ?>" aria-hidden="true"></i></a></th>
          <th class="text-end">Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($rows !== []): ?>
          <?php foreach ($rows as $row): ?>
            <?php
            // Row fields (see the contract in the controller report): person_name, department_name, position_name, etc.
            $rowId = (int) $field($row, 'id', 'Id');
            $rowName = (string) $field($row, 'person_name', 'Name');
            $rowStatus = (string) $field($row, 'status', 'Status');
            $rowActive = (int) ($row['active'] ?? 1) === 1;
            ?>
            <tr>
              <td class="hr-col-id"><?= $rowId; ?></td>
              <td>
                <div class="fw-semibold"><?= $esc($rowName); ?></div>
                <?php if (!empty($row['employment_type'])): ?>
                  <small class="text-muted"><?= $esc($employmentTypes[(string) $row['employment_type']] ?? $row['employment_type']); ?></small>
                <?php endif; ?>
              </td>
              <td><?= $esc($field($row, 'department_name', 'Department')); ?></td>
              <td><?= $esc($field($row, 'position_name', 'Position')); ?></td>
              <td><?= $esc($field($row, 'registration_number', 'RegistrationNumber')); ?></td>
              <td><?= $esc($dateBr($field($row, 'admission_date', 'AdmissionDate'))); ?></td>
              <td>
                <span class="badge <?= $esc($statusClasses[$rowStatus] ?? 'text-bg-secondary'); ?>"><?= $esc($statusLabels[$rowStatus] ?? $rowStatus); ?></span>
                <?php if (!$rowActive): ?>
                  <span class="badge text-bg-light border">Inativo</span>
                <?php endif; ?>
              </td>
              <td class="text-nowrap text-end">
                <?php if (authCan('hr_employee.delete')): ?>
                  <a aria-label="Excluir funcionário" class="js-record-delete-link text-danger" data-bs-content="Excluir" data-bs-target="#recordDeleteModal" data-bs-toggle="modal" data-bs-trigger="hover focus" data-record-delete-description="o funcionário" data-record-delete-url="<?= url('hr_employee/delete/' . $rowId); ?>" data-record-id="<?= $rowId; ?>" data-record-name="<?= $esc($rowName); ?>" href="#" role="button"><i class="bi bi-trash" aria-hidden="true"></i></a>
                <?php endif; ?>
                <?php if (authCan('hr_employee.edit')): ?>
                  <a aria-label="Editar funcionário" data-bs-content="Editar" data-bs-toggle="popover" data-bs-trigger="hover focus" href="<?= url('hr_employee/edit/' . $rowId); ?>"><i class="bi bi-pencil-square" aria-hidden="true"></i></a>
                <?php endif; ?>
                <?php if (authCan('hr_employee.informations')): ?>
                  <a aria-label="Ver funcionário" data-bs-content="Saiba mais" data-bs-toggle="popover" data-bs-trigger="hover focus" href="<?= url('hr_employee/informations/' . $rowId); ?>"><i class="bi bi-search" aria-hidden="true"></i></a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td class="text-center" colspan="8">Nenhum funcionário encontrado.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <p class="text-muted mb-0"><?= $total; ?> registro(s) encontrado(s)</p>

  <?php if ($totalPages > 1): ?>
    <?= paginationLinks('hr_employee', $page, $totalPages, $queryFilters, 2, 'Anterior', 'Próxima'); ?>
  <?php endif; ?>
</div>

<?php if (authCan('hr_employee.delete')): ?>
  <?php require __DIR__ . '/../shared/delete_modal.php'; ?>
  <script src="<?= url('assets/js/shared/delete_modal.js?v=1'); ?>"></script>
<?php endif; ?>
