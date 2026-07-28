<?php
$crmOpportunity = (isset($crmOpportunity) && is_array($crmOpportunity)) ? $crmOpportunity : [];
$responsibleName = trim((string) ($crmOpportunity['responsible_name'] ?? '')) ?: (string) ($crmOpportunity['responsible_username'] ?? '');
$money = static fn ($value): string => 'R$ ' . number_format((float) ($value ?? 0), 2, ',', '.');
?>

<div class="app-page">
  <div class="app-page-header">
    <div>
      <span class="app-page-kicker"><i class="bi bi-search"></i> CRM</span>
      <h5><?= htmlspecialchars((string) ($crmOpportunity['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h5>
    </div>
    <span class="badge <?= htmlspecialchars((string) ($crmOpportunity['pipeline_stage_bootstrap_class'] ?? 'text-bg-secondary'), ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars((string) ($crmOpportunity['pipeline_stage_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
  </div>

  <section class="border rounded p-3 mb-3">
    <h6>Dados da oportunidade</h6>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <tbody>
          <tr><th class="text-muted" style="width: 220px;">Código</th><td><?= (int) ($crmOpportunity['id'] ?? 0); ?></td></tr>
          <tr><th class="text-muted">Contato CRM</th><td><a href="<?= url('crm/informations/' . (int) ($crmOpportunity['tenant_person_id'] ?? 0)); ?>"><?= htmlspecialchars((string) ($crmOpportunity['person_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></a></td></tr>
          <tr><th class="text-muted">Origem</th><td><?= htmlspecialchars((string) ($crmOpportunity['crm_source_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td></tr>
          <tr><th class="text-muted">Tipo</th><td><?= htmlspecialchars((string) ($crmOpportunity['opportunity_type_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td></tr>
          <tr><th class="text-muted">Responsável</th><td><?= htmlspecialchars($responsibleName, ENT_QUOTES, 'UTF-8'); ?></td></tr>
          <tr><th class="text-muted">Valor estimado</th><td><?= htmlspecialchars($money($crmOpportunity['estimated_value'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></td></tr>
          <tr><th class="text-muted">Probabilidade</th><td><?= (int) ($crmOpportunity['probability'] ?? 0); ?>%</td></tr>
          <tr><th class="text-muted">Fechamento previsto</th><td><?= htmlspecialchars(maskDateBr((string) ($crmOpportunity['expected_close_date'] ?? '')) ?? '', ENT_QUOTES, 'UTF-8'); ?></td></tr>
          <tr><th class="text-muted">Motivo de perda</th><td><?= htmlspecialchars((string) ($crmOpportunity['loss_reason_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td></tr>
          <tr><th class="text-muted">Fechada em</th><td><?= htmlspecialchars(\App\Models\Crm::formatDateTime($crmOpportunity['closed_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td></tr>
          <tr><th class="text-muted">Registro</th><td><span class="badge <?= ((int) ($crmOpportunity['active'] ?? 0) === 1) ? 'text-bg-success' : 'text-bg-secondary'; ?>"><?= ((int) ($crmOpportunity['active'] ?? 0) === 1) ? 'Ativo' : 'Inativo'; ?></span></td></tr>
        </tbody>
      </table>
    </div>
  </section>

  <section class="border rounded p-3 mb-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
      <h6 class="mb-0">Mover no funil</h6>
    </div>

    <?php if (authCan('crm_opportunity.moveStage')): ?>
      <form action="<?= url('crm_opportunity/moveStage/' . (int) ($crmOpportunity['id'] ?? 0)); ?>" method="POST" class="row g-3 align-items-end">
        <input type="hidden" name="redirect_to" value="<?= htmlspecialchars('crm_opportunity/informations/' . (int) ($crmOpportunity['id'] ?? 0), ENT_QUOTES, 'UTF-8'); ?>">
        <div class="col-12 col-lg-6">
          <label class="form-label" for="pipelineStageId">Etapa</label>
          <select class="form-select" id="pipelineStageId" name="pipeline_stage_id">
            <?php foreach ($pipelineStages as $stage): ?>
              <option value="<?= (int) $stage['id']; ?>" <?= (int) ($crmOpportunity['pipeline_stage_id'] ?? 0) === (int) $stage['id'] ? 'selected' : ''; ?>><?= htmlspecialchars((string) ($stage['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12 col-lg-3">
          <button class="btn btn-primary w-100" type="submit"><i class="bi bi-arrow-left-right"></i> Atualizar etapa</button>
        </div>
      </form>
    <?php endif; ?>
  </section>

  <section class="border rounded p-3 mb-3">
    <h6>Descrição</h6>
    <?php if (trim((string) ($crmOpportunity['description'] ?? '')) !== ''): ?>
      <p class="mb-0"><?= nl2br(htmlspecialchars((string) ($crmOpportunity['description'] ?? ''), ENT_QUOTES, 'UTF-8')); ?></p>
    <?php else: ?>
      <p class="text-muted mb-0">Nenhuma descrição cadastrada.</p>
    <?php endif; ?>
  </section>

  <div class="form-actions">
    <a href="<?= url('crm_opportunity'); ?>" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
    <a href="<?= url('crm/informations/' . (int) ($crmOpportunity['tenant_person_id'] ?? 0)); ?>" class="btn btn-outline-secondary"><i class="bi bi-person-lines-fill"></i> Ver contato CRM</a>
    <?php if (authCan('crm_opportunity.edit')): ?>
      <a href="<?= url('crm_opportunity/edit/' . (int) ($crmOpportunity['id'] ?? 0)); ?>" class="btn btn-primary"><i class="bi bi-pencil-square"></i> Editar</a>
    <?php endif; ?>
  </div>
</div>
