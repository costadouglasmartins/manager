<?php
// "Dados do funcionário" card, hidden until the employee role is checked.
// Expected: $employeeCardClass ("person-create" or "person-edit"), $employeeCardVisible (bool), plus the variables of hr_employees/_employee_fields.php.
$employeeCardClass = (string) ($employeeCardClass ?? 'person-create');
$employeeCardVisible = !empty($employeeCardVisible);
$employeeFieldsDisabled = !$employeeCardVisible;
?>
<section class="<?= htmlspecialchars($employeeCardClass, ENT_QUOTES, 'UTF-8'); ?>-section <?= $employeeCardVisible ? '' : 'd-none'; ?>" id="employeeSection" aria-labelledby="employeeSectionTitle">
  <div class="<?= htmlspecialchars($employeeCardClass, ENT_QUOTES, 'UTF-8'); ?>-section-header">
    <div class="<?= htmlspecialchars($employeeCardClass, ENT_QUOTES, 'UTF-8'); ?>-section-title">
      <i class="bi bi-briefcase" aria-hidden="true"></i>
      <h2 id="employeeSectionTitle">Dados do funcionário</h2>
    </div>
  </div>

  <?php require __DIR__ . '/../hr_employees/_employee_fields.php'; ?>
</section>
