<?php
$formData = (isset($formData) && is_array($formData)) ? $formData : [];
$crmForm = is_array($formData['crm'] ?? null) ? $formData['crm'] : [];
$statusValue = (string) ($crmForm['crm_status'] ?? $crmRecord['crm_status'] ?? \App\Models\Crm::STATUS_LEAD);
$activeValue = (string) ($crmForm['active'] ?? $crmRecord['active'] ?? '1');
$ownerValue = (string) ($crmForm['owner_user_id'] ?? $crmRecord['owner_user_id'] ?? '');
?>

<div class="app-page">
  <div class="app-page-header">
    <div>
      <span class="app-page-kicker"><i class="bi bi-pencil-square"></i> CRM</span>
      <h5>Editar registro CRM</h5>
    </div>
  </div>

  <?php if (!empty($formError)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars((string) $formError, ENT_QUOTES, 'UTF-8'); ?></div>
  <?php endif; ?>

  <section class="border rounded p-3 mb-3">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <tbody>
          <tr>
            <th class="text-muted" style="width: 220px;">Pessoa</th>
            <td><?= htmlspecialchars((string) ($crmRecord['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
          </tr>
          <tr>
            <th class="text-muted">Documento</th>
            <td><?= htmlspecialchars(maskCpfCnpj((string) ($crmRecord['document'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
          </tr>
          <tr>
            <th class="text-muted">Tipo</th>
            <td><?= htmlspecialchars(\App\Models\Crm::personTypeLabel((string) ($crmRecord['type'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>

  <form action="<?= url('crm/update/' . (int) ($crmRecord['id'] ?? 0)); ?>" method="POST" class="needs-validation" novalidate>
    <section class="border rounded p-3 mb-3">
      <div class="row g-3">
        <div class="col-12 col-md-4">
          <label class="form-label" for="crmStatus">Situação *</label>
          <select class="form-select" id="crmStatus" name="crm[crm_status]" required>
            <?php foreach ($statuses as $value => $config): ?>
              <option value="<?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); ?>" <?= $statusValue === (string) $value ? 'selected' : ''; ?>><?= htmlspecialchars((string) ($config['label'] ?? $value), ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12 col-md-4">
          <label class="form-label" for="ownerUserId">Responsável</label>
          <select class="form-select" id="ownerUserId" name="crm[owner_user_id]">
            <option value="">Sem responsável</option>
            <?php foreach ($owners as $owner): ?>
              <?php
              $ownerLabel = trim((string) ($owner['person_name'] ?? '')) ?: (string) ($owner['username'] ?? '');
              if ((int) ($owner['assignable'] ?? 0) !== 1)
                {
                $ownerLabel .= ' (inativo)';
                }
              ?>
              <option value="<?= (int) $owner['id']; ?>" <?= $ownerValue === (string) $owner['id'] ? 'selected' : ''; ?>><?= htmlspecialchars($ownerLabel, ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12 col-md-4">
          <label class="form-label" for="crmSource">Origem</label>
          <input autocomplete="off" class="form-control" id="crmSource" maxlength="120" name="crm[source]" type="text" value="<?= htmlspecialchars((string) ($crmForm['source'] ?? $crmRecord['source'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="col-12 col-md-4">
          <label class="form-label" for="crmActive">Status do vínculo</label>
          <select class="form-select" id="crmActive" name="crm[active]">
            <option value="1" <?= $activeValue === '1' ? 'selected' : ''; ?>>Ativo</option>
            <option value="0" <?= $activeValue === '0' ? 'selected' : ''; ?>>Inativo</option>
          </select>
        </div>

        <div class="col-12">
          <label class="form-label" for="crmNotes">Observações</label>
          <textarea class="form-control" id="crmNotes" maxlength="2000" name="crm[notes]" rows="6"><?= htmlspecialchars((string) ($crmForm['notes'] ?? $crmRecord['notes'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>
      </div>
    </section>

    <div class="form-actions">
      <a href="<?= url('crm'); ?>" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Cancelar</a>
      <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> Salvar</button>
    </div>
  </form>
</div>
