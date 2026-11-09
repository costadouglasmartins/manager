<?php
// Variables expected from HrEmployeeController::create: $hrDepartments, $hrPositions, $employee (array|null, repopulation after a
// validation error; $formData['employee'] is accepted too), $formError (string, optional).
$esc = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$formData = (isset($formData) && is_array($formData)) ? $formData : [];
$employee = (isset($employee) && is_array($employee)) ? $employee : ((isset($formData['employee']) && is_array($formData['employee'])) ? $formData['employee'] : []);
$hrDepartments = (isset($hrDepartments) && is_array($hrDepartments)) ? $hrDepartments : [];
$hrPositions = (isset($hrPositions) && is_array($hrPositions)) ? $hrPositions : [];
$employeeFieldsDisabled = false;
$personId = (string) ($employee['person_id'] ?? '');
$personName = (string) ($employee['person_name'] ?? '');
?>
<div class="app-page">
  <div class="app-page-header">
    <div>
      <span class="app-page-kicker"><i class="bi bi-briefcase" aria-hidden="true"></i> RH</span>
      <h5>Cadastrar funcionário</h5>
    </div>
  </div>

  <?php if (!empty($formError)): ?>
    <div class="alert alert-danger" role="alert"><?= $esc($formError); ?></div>
  <?php endif; ?>

  <div class="alert alert-info mb-0" role="note">
    <i class="bi bi-info-circle" aria-hidden="true"></i>
    Somente pessoas com o papel <strong>Funcionário</strong> e sem registro de RH aparecem na busca. Para cadastrar uma nova pessoa, use o cadastro de pessoas.
  </div>

  <form action="<?= url('hr_employee/store'); ?>" class="needs-validation js-hr-employee-form" method="POST" novalidate>
    <div class="alert alert-danger d-none js-form-alert" role="alert"></div>

    <section class="border rounded p-3 mb-3">
      <h2 class="h6 mb-3"><i class="bi bi-person" aria-hidden="true"></i> Pessoa</h2>
      <div class="row g-3">
        <div class="col-12 col-lg-6 position-relative">
          <label class="form-label" for="employeePersonName">Pessoa *</label>
          <input autocomplete="off" type="hidden" id="employeePersonId" name="employee[person_id]" value="<?= $esc($personId); ?>">
          <input autocomplete="off" class="form-control js-autocomplete js-person-name" id="employeePersonName" type="text" maxlength="160" placeholder="Digite o nome da pessoa" required data-endpoint="<?= url('hr_employee/personAutocomplete'); ?>" data-hidden="#employeePersonId" data-results="#employeePersonResults" data-minlen="2" data-param="term" data-autocomplete-portal="body" value="<?= $esc($personName); ?>">
          <ul class="list-group mt-1" id="employeePersonResults" role="listbox" aria-label="Pessoas encontradas"></ul>
          <div class="invalid-feedback">Selecione uma pessoa da lista.</div>
        </div>
      </div>
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
