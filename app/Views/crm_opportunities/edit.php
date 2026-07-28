<?php
$formData = (isset($formData) && is_array($formData)) ? $formData : [];
$crmOpportunity = (isset($crmOpportunity) && is_array($crmOpportunity)) ? $crmOpportunity : [];
$selectedCrmId = (int) ($formData['tenant_person_id'] ?? $crmOpportunity['tenant_person_id'] ?? 0);
$activeValue = (string) ($formData['active'] ?? $crmOpportunity['active'] ?? '1');
$toDateTimeLocal = static function ($value): string {
	$value = trim((string) ($value ?? ''));
	if ($value === '' || $value === '0000-00-00 00:00:00')
		{
		return '';
		}

	try
		{
		return (new DateTimeImmutable($value))->format('Y-m-d\TH:i');
		}
	catch (Throwable $e)
		{
		return $value;
		}
};
?>

<div class="app-page">
  <div class="app-page-header">
    <div>
      <span class="app-page-kicker"><i class="bi bi-pencil-square"></i> CRM</span>
      <h5>Editar oportunidade CRM</h5>
    </div>
    <span class="badge <?= htmlspecialchars((string) ($crmOpportunity['pipeline_stage_bootstrap_class'] ?? 'text-bg-secondary'), ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars((string) ($crmOpportunity['pipeline_stage_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
  </div>

  <?php if (!empty($formError)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars((string) $formError, ENT_QUOTES, 'UTF-8'); ?></div>
  <?php endif; ?>

  <form action="<?= url('crm_opportunity/update/' . (int) ($crmOpportunity['id'] ?? 0)); ?>" method="POST" class="needs-validation" novalidate>
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
        </div>

        <div class="col-12 col-lg-6">
          <label class="form-label" for="title">Título *</label>
          <input class="form-control" id="title" maxlength="180" name="title" required type="text" value="<?= htmlspecialchars((string) ($formData['title'] ?? $crmOpportunity['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="col-12 col-md-4">
          <label class="form-label" for="crmSourceId">Origem</label>
          <select class="form-select" id="crmSourceId" name="crm_source_id">
            <option value="">Selecione</option>
            <?php foreach ($sources as $source): ?>
              <option value="<?= (int) $source['id']; ?>" <?= (string) ($formData['crm_source_id'] ?? $crmOpportunity['crm_source_id'] ?? '') === (string) $source['id'] ? 'selected' : ''; ?>><?= htmlspecialchars((string) ($source['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12 col-md-4">
          <label class="form-label" for="opportunityTypeId">Tipo</label>
          <select class="form-select" id="opportunityTypeId" name="opportunity_type_id">
            <option value="">Selecione</option>
            <?php foreach ($opportunityTypes as $type): ?>
              <option value="<?= (int) $type['id']; ?>" <?= (string) ($formData['opportunity_type_id'] ?? $crmOpportunity['opportunity_type_id'] ?? '') === (string) $type['id'] ? 'selected' : ''; ?>><?= htmlspecialchars((string) ($type['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12 col-md-4">
          <label class="form-label" for="pipelineStageId">Etapa do funil *</label>
          <select class="form-select" id="pipelineStageId" name="pipeline_stage_id" required>
            <?php foreach ($pipelineStages as $stage): ?>
              <option value="<?= (int) $stage['id']; ?>" <?= (string) ($formData['pipeline_stage_id'] ?? $crmOpportunity['pipeline_stage_id'] ?? '') === (string) $stage['id'] ? 'selected' : ''; ?>><?= htmlspecialchars((string) ($stage['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12 col-md-4">
          <label class="form-label" for="estimatedValue">Valor estimado</label>
          <input class="form-control" id="estimatedValue" name="estimated_value" type="text" value="<?= htmlspecialchars((string) ($formData['estimated_value'] ?? number_format((float) ($crmOpportunity['estimated_value'] ?? 0), 2, ',', '.')), ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="col-12 col-md-4">
          <label class="form-label" for="probability">Probabilidade (%)</label>
          <input class="form-control" id="probability" max="100" min="0" name="probability" type="number" value="<?= htmlspecialchars((string) ($formData['probability'] ?? $crmOpportunity['probability'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="col-12 col-md-4">
          <label class="form-label" for="expectedCloseDate">Fechamento previsto</label>
          <input class="form-control" id="expectedCloseDate" name="expected_close_date" type="date" value="<?= htmlspecialchars((string) ($formData['expected_close_date'] ?? $crmOpportunity['expected_close_date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="col-12 col-md-4">
          <label class="form-label" for="lossReasonId">Motivo de perda</label>
          <select class="form-select" id="lossReasonId" name="loss_reason_id">
            <option value="">Selecione</option>
            <?php foreach ($lossReasons as $reason): ?>
              <option value="<?= (int) $reason['id']; ?>" <?= (string) ($formData['loss_reason_id'] ?? $crmOpportunity['loss_reason_id'] ?? '') === (string) $reason['id'] ? 'selected' : ''; ?>><?= htmlspecialchars((string) ($reason['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12 col-md-4">
          <label class="form-label" for="closedAt">Fechada em</label>
          <input class="form-control" id="closedAt" name="closed_at" type="datetime-local" value="<?= htmlspecialchars((string) ($formData['closed_at'] ?? $toDateTimeLocal($crmOpportunity['closed_at'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="col-12 col-md-4">
          <label class="form-label" for="responsibleUserId">Responsável</label>
          <select class="form-select" id="responsibleUserId" name="responsible_user_id">
            <option value="">Sem responsável</option>
            <?php foreach ($owners as $owner): ?>
              <?php $ownerLabel = trim((string) ($owner['person_name'] ?? '')) ?: (string) ($owner['username'] ?? ''); ?>
              <option value="<?= (int) $owner['id']; ?>" <?= (string) ($formData['responsible_user_id'] ?? $crmOpportunity['responsible_user_id'] ?? '') === (string) $owner['id'] ? 'selected' : ''; ?>><?= htmlspecialchars($ownerLabel, ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
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
          <textarea class="form-control" id="description" maxlength="3000" name="description" rows="6"><?= htmlspecialchars((string) ($formData['description'] ?? $crmOpportunity['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>
      </div>
    </section>

    <div class="form-actions">
      <a href="<?= url('crm_opportunity/informations/' . (int) ($crmOpportunity['id'] ?? 0)); ?>" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Cancelar</a>
      <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> Salvar</button>
    </div>
  </form>
</div>
