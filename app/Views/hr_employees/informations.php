<?php
// Variables expected from HrEmployeeController::informations: $employee (record with person/department/position/manager names).
$esc = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$employee = (isset($employee) && is_array($employee)) ? $employee : [];
$employeeId = (int) ($employee['id'] ?? 0);
$employmentTypes = ['clt' => 'CLT', 'pj' => 'PJ', 'estagio' => 'Estágio', 'temporario' => 'Temporário'];
$statusLabels = ['active' => 'Ativo', 'on_leave' => 'Afastado', 'terminated' => 'Desligado'];
$statusClasses = ['active' => 'text-bg-success', 'on_leave' => 'text-bg-warning', 'terminated' => 'text-bg-secondary'];
$status = (string) ($employee['status'] ?? '');
$dash = static fn($value): string => trim((string) $value) !== '' ? (string) $value : '—';
$dateBr = static function ($value): string {
	$value = trim((string) $value);
	return preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $value, $m) === 1 ? $m[3] . '/' . $m[2] . '/' . $m[1] : '';
};
$dateTimeBr = static function ($value): string {
	$value = trim((string) $value);
	return preg_match('/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}:\d{2})/', $value, $m) === 1 ? $m[3] . '/' . $m[2] . '/' . $m[1] . ' ' . $m[4] : '';
};
$salary = trim((string) ($employee['salary'] ?? ''));
?>
<div class="app-page">
  <div class="app-page-header">
    <div>
      <span class="app-page-kicker"><i class="bi bi-search" aria-hidden="true"></i> RH</span>
      <h5><?= $esc($employee['person_name'] ?? 'Funcionário'); ?></h5>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <span class="badge <?= $esc($statusClasses[$status] ?? 'text-bg-secondary'); ?>"><?= $esc($statusLabels[$status] ?? $status); ?></span>
      <?php if ((int) ($employee['active'] ?? 1) !== 1): ?>
        <span class="badge text-bg-secondary">Vínculo inativo</span>
      <?php endif; ?>
    </div>
  </div>

  <section class="border rounded p-3 mb-3">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <tbody>
          <tr>
            <th class="text-muted" scope="row" style="width: 220px;">Código</th>
            <td><?= $employeeId; ?></td>
          </tr>
          <tr>
            <th class="text-muted" scope="row">Nome</th>
            <td><?= $esc($dash($employee['person_name'] ?? '')); ?></td>
          </tr>
          <tr>
            <th class="text-muted" scope="row">Documento</th>
            <td><?= $esc($dash(maskCpfCnpj((string) ($employee['person_document'] ?? '')))); ?></td>
          </tr>
          <tr>
            <th class="text-muted" scope="row">Departamento</th>
            <td><?= $esc($dash($employee['department_name'] ?? '')); ?></td>
          </tr>
          <tr>
            <th class="text-muted" scope="row">Cargo</th>
            <td><?= $esc($dash($employee['position_name'] ?? '')); ?></td>
          </tr>
          <tr>
            <th class="text-muted" scope="row">Gestor</th>
            <td><?= $esc($dash($employee['manager_name'] ?? '')); ?></td>
          </tr>
          <tr>
            <th class="text-muted" scope="row">Matrícula</th>
            <td><?= $esc($dash($employee['registration_number'] ?? '')); ?></td>
          </tr>
          <tr>
            <th class="text-muted" scope="row">Tipo de contrato</th>
            <td><?= $esc($dash($employmentTypes[(string) ($employee['employment_type'] ?? '')] ?? ($employee['employment_type'] ?? ''))); ?></td>
          </tr>
          <tr>
            <th class="text-muted" scope="row">Data de admissão</th>
            <td><?= $esc($dash($dateBr($employee['admission_date'] ?? ''))); ?></td>
          </tr>
          <tr>
            <th class="text-muted" scope="row">Data de desligamento</th>
            <td><?= $esc($dash($dateBr($employee['termination_date'] ?? ''))); ?></td>
          </tr>
          <tr>
            <th class="text-muted" scope="row">Salário</th>
            <td><?= $salary !== '' && is_numeric($salary) ? 'R$ ' . $esc(number_format((float) $salary, 2, ',', '.')) : '—'; ?></td>
          </tr>
          <tr>
            <th class="text-muted" scope="row">Observações</th>
            <td><?= nl2br($esc($dash($employee['notes'] ?? ''))); ?></td>
          </tr>
          <tr>
            <th class="text-muted" scope="row">Criado em</th>
            <td><?= $esc($dash($dateTimeBr($employee['created'] ?? ''))); ?></td>
          </tr>
          <tr>
            <th class="text-muted" scope="row">Atualizado em</th>
            <td><?= $esc($dash($dateTimeBr($employee['modified'] ?? ''))); ?></td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>

  <div class="form-actions">
    <a class="btn btn-secondary" href="<?= url('hr_employee'); ?>"><i class="bi bi-arrow-left" aria-hidden="true"></i> Voltar</a>
    <?php if (authCan('hr_employee.edit')): ?>
      <a class="btn btn-primary" href="<?= url('hr_employee/edit/' . $employeeId); ?>"><i class="bi bi-pencil-square" aria-hidden="true"></i> Editar</a>
    <?php endif; ?>
  </div>
</div>
