<?php
// Variables expected from HrEmployeeController::edit: $employee (current record, see the field list in the report), $hrDepartments,
// $hrPositions, $formData['employee'] (optional, values to repopulate after a validation error), $formError (optional).
$esc = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$record = (isset($employee) && is_array($employee)) ? $employee : [];
$formData = (isset($formData) && is_array($formData)) ? $formData : [];

// Submitted values win over the stored record when the form is shown again after an error.
$employee = (isset($formData['employee']) && is_array($formData['employee'])) ? array_merge($record, $formData['employee']) : $record;
$hrDepartments = (isset($hrDepartments) && is_array($hrDepartments)) ? $hrDepartments : [];
$hrPositions = (isset($hrPositions) && is_array($hrPositions)) ? $hrPositions : [];
$employeeFieldsDisabled = false;
$employeeId = (int) ($record['id'] ?? 0);
$personId = (int) ($record['person_id'] ?? 0);
$personName = (string) ($record['person_name'] ?? '');
$personDocument = (string) ($record['person_document'] ?? '');
?>
<div class="app-page">
  <div class="app-page-header">
    <div>
      <span class="app-page-kicker"><i class="bi bi-pencil-square" aria-hidden="true"></i> RH</span>
      <h5>Editar funcionário</h5>
    </div>
    <?php if ((int) ($record['active'] ?? 1) !== 1): ?>
      <span class="badge text-bg-secondary">Vínculo inativo</span>
    <?php endif; ?>
  </div>

  <?php if (!empty($formError)): ?>
    <div class="alert alert-danger" role="alert"><?= $esc($formError); ?></div>
  <?php endif; ?>

  <form action="<?= url('hr_employee/update/' . $employeeId); ?>" class="needs-validation js-hr-employee-form" method="POST" novalidate>
    <div class="alert alert-danger d-none js-form-alert" role="alert"></div>

    <section class="border rounded p-3 mb-3">
      <h2 class="h6 mb-3"><i class="bi bi-person" aria-hidden="true"></i> Pessoa</h2>
      <?php // The identity belongs to the person record, so it is shown read-only here. ?>
      <dl class="row mb-0">
        <dt class="col-sm-3 text-muted">Nome</dt>
        <dd class="col-sm-9"><?= $esc($personName); ?></dd>
        <?php if ($personDocument !== ''): ?>
          <dt class="col-sm-3 text-muted">Documento</dt>
          <dd class="col-sm-9"><?= $esc(maskCpfCnpj($personDocument)); ?></dd>
        <?php endif; ?>
      </dl>
      <?php if ($personId > 0 && authCan('person.edit')): ?>
        <a class="btn btn-outline-secondary btn-sm mt-2" href="<?= url('person/edit/' . $personId); ?>"><i class="bi bi-person-lines-fill" aria-hidden="true"></i> Editar dados da pessoa</a>
      <?php endif; ?>
    </section>

    <section class="border rounded p-3 mb-3">
      <h2 class="h6 mb-3"><i class="bi bi-briefcase" aria-hidden="true"></i> Dados do funcionário</h2>
      <?php require __DIR__ . '/_employee_fields.php'; ?>
    </section>

    <div class="form-actions">
      <a class="btn btn-secondary" href="<?= url('hr_employee'); ?>"><i class="bi bi-arrow-left" aria-hidden="true"></i> Cancelar</a>
      <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg" aria-hidden="true"></i> Salvar</button>
    </div>
  </form>
</div>

<script src="/manager_assets/masks/masks.js?v=7"></script>
<script src="/manager_assets/autocomplete/autocomplete.js?v=2"></script>
<script src="<?= url('assets/js/hr_employees/form.js?v=1'); ?>"></script>
