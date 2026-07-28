<?php
$formData = (isset($formData) && is_array($formData)) ? $formData : [];
$selectedCrmId = (int) ($formData['tenant_person_id'] ?? $formData['crm_id'] ?? 0);
$activeValue = (string) ($formData['active'] ?? '1');
$backToCrm = (string) ($formData['back_to_crm'] ?? ($selectedCrmId > 0 ? '1' : '0'));
?>

<div class="app-page">
  <div class="app-page-header">
    <div>
      <span class="app-page-kicker"><i class="bi bi-clock-history"></i> CRM</span>
      <h5>Nova atividade CRM</h5>
    </div>
  </div>

  <?php if (!empty($formError)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars((string) $formError, ENT_QUOTES, 'UTF-8'); ?></div>
  <?php endif; ?>

  <form action="<?= url('crm_activity/store'); ?>" method="POST" class="needs-validation" novalidate>
    <input type="hidden" name="back_to_crm" value="<?= htmlspecialchars($backToCrm, ENT_QUOTES, 'UTF-8'); ?>">

    <section class="border rounded p-3 mb-3">
      <div class="row g-3">
        <div class="col-12 col-lg-6">
          <label class="form-label" for="tenantPersonId">Contato CRM *</label>
          <select class="form-select" id="tenantPersonId" name="tenant_person_id" required>
            <option value="">Selecione</option>
            <?php foreach ($crmOptions as $crmOption): ?>
              <?php $crmLabel = trim((string) ($crmOption['name'] ?? '')) . ' #' . (int) ($crmOption['id'] ?? 0); ?>
              <option value="<?= (int) $crmOption['id']; ?>" <?= $selectedCrmId === (int) $crmOption['id'] ? 'selected' : ''; ?>><?= htmlspecialchars($crmLabel, ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
          <div class="invalid-feedback">Selecione o contato CRM.</div>
        </div>

        <div class="col-12 col-lg-6">
          <label class="form-label" for="title">Título *</label>
          <input autocomplete="off" class="form-control" id="title" maxlength="180" name="title" required type="text" value="<?= htmlspecialchars((string) ($formData['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
          <div class="invalid-feedback">Informe o título da atividade.</div>
        </div>

        <div class="col-12 col-md-4">
          <label class="form-label" for="activityTypeId">Tipo *</label>
          <select class="form-select" id="activityTypeId" name="activity_type_id" required>
            <?php foreach ($activityTypes as $type): ?>
              <option value="<?= (int) $type['id']; ?>" <?= (string) ($formData['activity_type_id'] ?? '') === (string) $type['id'] ? 'selected' : ''; ?>><?= htmlspecialchars((string) ($type['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12 col-md-4">
          <label class="form-label" for="activityStatusId">Status *</label>
          <select class="form-select" id="activityStatusId" name="activity_status_id" required>
            <?php foreach ($activityStatuses as $status): ?>
              <option value="<?= (int) $status['id']; ?>" <?= (string) ($formData['activity_status_id'] ?? '') === (string) $status['id'] ? 'selected' : ''; ?>><?= htmlspecialchars((string) ($status['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12 col-md-4">
          <label class="form-label" for="responsibleUserId">Responsável</label>
          <select class="form-select" id="responsibleUserId" name="responsible_user_id">
            <option value="">Usar responsável do CRM</option>
            <?php foreach ($owners as $owner): ?>
              <?php $ownerLabel = trim((string) ($owner['person_name'] ?? '')) ?: (string) ($owner['username'] ?? ''); ?>
              <option value="<?= (int) $owner['id']; ?>" <?= (string) ($formData['responsible_user_id'] ?? '') === (string) $owner['id'] ? 'selected' : ''; ?>><?= htmlspecialchars($ownerLabel, ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12 col-md-4">
          <label class="form-label" for="scheduledAt">Agendada para</label>
          <input class="form-control" id="scheduledAt" name="scheduled_at" type="datetime-local" value="<?= htmlspecialchars((string) ($formData['scheduled_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="col-12 col-md-4">
          <label class="form-label" for="completedAt">Concluída em</label>
          <input class="form-control" id="completedAt" name="completed_at" type="datetime-local" value="<?= htmlspecialchars((string) ($formData['completed_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="col-12 col-md-4">
          <label class="form-label" for="active">Status do registro</label>
          <select class="form-select" id="active" name="active">
            <option value="1" <?= $activeValue === '1' ? 'selected' : ''; ?>>Ativo</option>
            <option value="0" <?= $activeValue === '0' ? 'selected' : ''; ?>>Inativo</option>
          </select>
        </div>

        <div class="col-12">
          <label class="form-label" for="description">Descrição</label>
          <textarea class="form-control" id="description" maxlength="3000" name="description" rows="6"><?= htmlspecialchars((string) ($formData['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>
      </div>
    </section>

    <div class="form-actions">
      <a href="<?= $selectedCrmId > 0 ? url('crm/informations/' . $selectedCrmId) : url('crm_activity'); ?>" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Cancelar</a>
      <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> Salvar</button>
    </div>
  </form>
</div>
