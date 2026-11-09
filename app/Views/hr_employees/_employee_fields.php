<?php
// Shared HR field set rendered by people/create, people/edit and hr_employees/create|edit.
// Expected variables: $employee (array|null), $hrDepartments, $hrPositions, $employeeFieldsDisabled (bool).
$employeeData = (isset($employee) && is_array($employee)) ? $employee : [];
$hrDepartments = (isset($hrDepartments) && is_array($hrDepartments)) ? $hrDepartments : [];
$hrPositions = (isset($hrPositions) && is_array($hrPositions)) ? $hrPositions : [];
$employeeFieldsDisabled = !empty($employeeFieldsDisabled);
$disabledAttr = $employeeFieldsDisabled ? 'disabled' : '';
$esc = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

// Returns the first non-null value among the given keys (accepts both "department_id" and "hr_department_id" shapes).
$pick = static function (array $keys, $default = '') use ($employeeData) {
	foreach ($keys as $key)
		{
		if (array_key_exists($key, $employeeData) && $employeeData[$key] !== null)
			{
			return $employeeData[$key];
			}
		}

	return $default;
};

// Dates arrive as Y-m-d from the database or as dd/mm/aaaa when the form is repopulated after an error.
$toDateBr = static function ($value): string {
	$value = trim((string) $value);
	if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $value, $m) === 1)
		{
		return $m[3] . '/' . $m[2] . '/' . $m[1];
		}

	return $value;
};

// Salary arrives as a decimal string from the database or already formatted (1.234,56) after an error.
$toMoneyBr = static function ($value): string {
	$value = trim((string) $value);
	if ($value !== '' && preg_match('/^\d+(\.\d{1,4})?$/', $value) === 1)
		{
		return number_format((float) $value, 2, ',', '.');
		}

	return $value;
};

$departmentId = (string) $pick(['hr_department_id', 'department_id']);
$positionId = (string) $pick(['hr_position_id', 'position_id']);
$managerId = (string) $pick(['manager_hr_employee_id', 'manager_employee_id']);
$managerName = (string) $pick(['manager_name', 'manager_label', 'manager_person_name']);
$employmentType = (string) $pick(['employment_type'], 'clt');
$employeeStatus = (string) $pick(['status'], 'active');
$employmentTypes = ['clt' => 'CLT', 'pj' => 'PJ', 'estagio' => 'Estágio', 'temporario' => 'Temporário'];
$employeeStatuses = ['active' => 'Ativo', 'on_leave' => 'Afastado', 'terminated' => 'Desligado'];

// Renders the options of a catalog select, keeping a current inactive record selectable.
$catalogOptions = static function (array $items, string $selected) use ($esc): string {
	$html = '<option value="">Selecione...</option>';
	$found = false;
	foreach ($items as $item)
		{
		$itemId = (string) ($item['id'] ?? '');
		$isSelected = ($selected !== '' && $selected === $itemId);
		$found = $found || $isSelected;
		$html .= '<option value="' . $esc($itemId) . '"' . ($isSelected ? ' selected' : '') . '>' . $esc($item['name'] ?? '') . '</option>';
		}
	if ($selected !== '' && !$found)
		{
		$html .= '<option value="' . $esc($selected) . '" selected>Registro #' . $esc($selected) . ' (inativo)</option>';
		}

	return $html;
};
?>
<?php if ($hrDepartments === [] || $hrPositions === []): ?>
  <div class="alert alert-warning mb-3" role="alert">
    <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
    Cadastre
    <?php if ($hrDepartments === []): ?>
      <?php if (authCan('hr_department.index')): ?><a class="alert-link" href="<?= url('hr_department'); ?>">departamentos</a><?php else: ?>departamentos<?php endif; ?>
    <?php endif; ?>
    <?php if ($hrDepartments === [] && $hrPositions === []): ?> e <?php endif; ?>
    <?php if ($hrPositions === []): ?>
      <?php if (authCan('hr_position.index')): ?><a class="alert-link" href="<?= url('hr_position'); ?>">cargos</a><?php else: ?>cargos<?php endif; ?>
    <?php endif; ?>
    antes de informar os dados do funcionário.
  </div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-12 col-md-6 col-xl-4">
    <label class="form-label" for="employeeDepartment">Departamento *</label>
    <select class="form-select" id="employeeDepartment" name="employee[hr_department_id]" data-employee-draft="hr_department_id" required <?= $disabledAttr; ?>><?= $catalogOptions($hrDepartments, $departmentId); ?></select>
    <div class="invalid-feedback">Selecione o departamento.</div>
  </div>

  <div class="col-12 col-md-6 col-xl-4">
    <label class="form-label" for="employeePosition">Cargo *</label>
    <select class="form-select" id="employeePosition" name="employee[hr_position_id]" data-employee-draft="hr_position_id" required <?= $disabledAttr; ?>><?= $catalogOptions($hrPositions, $positionId); ?></select>
    <div class="invalid-feedback">Selecione o cargo.</div>
  </div>

  <div class="col-12 col-xl-4 position-relative">
    <label class="form-label" for="employeeManagerName">Gestor</label>
    <input autocomplete="off" type="hidden" id="employeeManagerId" name="employee[manager_hr_employee_id]" data-employee-draft="manager_hr_employee_id" value="<?= $esc($managerId); ?>" <?= $disabledAttr; ?>>
    <input autocomplete="off" class="form-control js-autocomplete" id="employeeManagerName" type="text" maxlength="160" placeholder="Digite o nome do gestor" data-employee-draft="manager_name" data-endpoint="<?= url('hr_employee/employeeAutocomplete'); ?>" data-hidden="#employeeManagerId" data-results="#employeeManagerResults" data-minlen="2" data-param="term" data-autocomplete-portal="body" aria-describedby="employeeManagerHelp" value="<?= $esc($managerName); ?>" <?= $disabledAttr; ?>>
    <ul class="list-group mt-1" id="employeeManagerResults" role="listbox" aria-label="Gestores encontrados"></ul>
    <div class="form-text" id="employeeManagerHelp">Opcional. Busque pelo nome do funcionário.</div>
  </div>

  <div class="col-12 col-md-4 col-xl-3">
    <label class="form-label" for="employeeRegistration">Matrícula</label>
    <input autocomplete="off" class="form-control" id="employeeRegistration" name="employee[registration_number]" data-employee-draft="registration_number" type="text" maxlength="40" value="<?= $esc($pick(['registration_number'])); ?>" <?= $disabledAttr; ?>>
  </div>

  <div class="col-12 col-md-4 col-xl-3">
    <label class="form-label" for="employeeAdmissionDate">Data de admissão *</label>
    <input autocomplete="off" class="form-control mask-date" id="employeeAdmissionDate" name="employee[admission_date]" data-employee-draft="admission_date" type="text" inputmode="numeric" maxlength="10" placeholder="DD/MM/AAAA" required value="<?= $esc($toDateBr($pick(['admission_date']))); ?>" <?= $disabledAttr; ?>>
    <div class="invalid-feedback">Informe uma data de admissão válida.</div>
  </div>

  <div class="col-12 col-md-4 col-xl-3">
    <label class="form-label" for="employeeTerminationDate">Data de desligamento</label>
    <input autocomplete="off" class="form-control mask-date" id="employeeTerminationDate" name="employee[termination_date]" data-employee-draft="termination_date" type="text" inputmode="numeric" maxlength="10" placeholder="DD/MM/AAAA" value="<?= $esc($toDateBr($pick(['termination_date']))); ?>" <?= $disabledAttr; ?>>
    <div class="invalid-feedback">Informe uma data de desligamento válida.</div>
  </div>

  <div class="col-12 col-md-6 col-xl-3">
    <label class="form-label" for="employeeSalary">Salário (R$)</label>
    <div class="input-group">
      <span class="input-group-text" aria-hidden="true">R$</span>
      <input autocomplete="off" class="form-control mask-money-br" id="employeeSalary" name="employee[salary]" data-employee-draft="salary" type="text" inputmode="numeric" maxlength="16" placeholder="0,00" value="<?= $esc($toMoneyBr($pick(['salary']))); ?>" <?= $disabledAttr; ?>>
    </div>
  </div>

  <div class="col-12 col-md-6">
    <label class="form-label" for="employeeType">Tipo de contrato *</label>
    <select class="form-select" id="employeeType" name="employee[employment_type]" data-employee-draft="employment_type" required <?= $disabledAttr; ?>>
      <?php foreach ($employmentTypes as $typeValue => $typeLabel): ?>
        <option value="<?= $esc($typeValue); ?>" <?= $employmentType === $typeValue ? 'selected' : ''; ?>><?= $esc($typeLabel); ?></option>
      <?php endforeach; ?>
    </select>
    <div class="invalid-feedback">Selecione o tipo de contrato.</div>
  </div>

  <div class="col-12 col-md-6">
    <label class="form-label" for="employeeStatus">Situação *</label>
    <select class="form-select" id="employeeStatus" name="employee[status]" data-employee-draft="status" required <?= $disabledAttr; ?>>
      <?php foreach ($employeeStatuses as $statusValue => $statusLabel): ?>
        <option value="<?= $esc($statusValue); ?>" <?= $employeeStatus === $statusValue ? 'selected' : ''; ?>><?= $esc($statusLabel); ?></option>
      <?php endforeach; ?>
    </select>
    <div class="invalid-feedback">Selecione a situação.</div>
  </div>

  <div class="col-12">
    <label class="form-label" for="employeeNotes">Observações</label>
    <textarea class="form-control" id="employeeNotes" name="employee[notes]" data-employee-draft="notes" rows="3" maxlength="2000" <?= $disabledAttr; ?>><?= $esc($pick(['notes'])); ?></textarea>
  </div>
</div>
