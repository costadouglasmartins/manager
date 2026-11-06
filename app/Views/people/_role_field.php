<?php
// Multiple-choice person role field. Expected: $roleValue (CSV string or array), $roleHasEmployeeRecord (bool, optional),
// $roleEmployeeLocked (bool, optional): true when the user lacks the HR ability, so the employee role is shown but cannot be changed.
$roleOptions = ['employee' => 'Funcionário', 'customer' => 'Cliente', 'user' => 'Usuário comum'];
$roleValue = $roleValue ?? '';
$roleEmployeeLocked = !empty($roleEmployeeLocked);
$selectedRoles = is_array($roleValue) ? $roleValue : explode(',', (string) $roleValue);
$selectedRoles = array_map(static fn($role): string => trim((string) $role), $selectedRoles);
?>
<fieldset class="person-role-field">
  <legend class="form-label fs-6">Papel</legend>
  <div class="d-flex flex-wrap gap-3">
    <?php foreach ($roleOptions as $roleKey => $roleLabel): ?>
      <?php $roleLocked = ($roleKey === 'employee' && $roleEmployeeLocked); $roleChecked = in_array($roleKey, $selectedRoles, true); ?>
      <div class="form-check">
        <input class="form-check-input js-person-role" id="personRole<?= ucfirst($roleKey); ?>" <?= $roleLocked ? '' : 'name="person[role][]"'; ?> type="checkbox" value="<?= htmlspecialchars($roleKey, ENT_QUOTES, 'UTF-8'); ?>" <?= $roleChecked ? 'checked' : ''; ?> <?= $roleLocked ? 'disabled' : ''; ?>>
        <?php if ($roleLocked && $roleChecked): ?>
          <?php // A disabled checkbox is not submitted: keep the current role through a hidden field. ?>
          <input type="hidden" name="person[role][]" value="<?= htmlspecialchars($roleKey, ENT_QUOTES, 'UTF-8'); ?>">
        <?php endif; ?>
        <label class="form-check-label" for="personRole<?= ucfirst($roleKey); ?>"><?= htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8'); ?></label>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="form-text">Nenhum marcado significa que não se aplica.<?= $roleEmployeeLocked ? ' O perfil Funcionário só pode ser alterado por quem gerencia o RH.' : (!empty($roleHasEmployeeRecord) ? ' Desmarcar Funcionário inativa o vínculo de RH, sem excluir o histórico.' : ''); ?></div>
</fieldset>
