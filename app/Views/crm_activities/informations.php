<?php
$crmActivity = (isset($crmActivity) && is_array($crmActivity)) ? $crmActivity : [];
$responsibleName = trim((string) ($crmActivity['responsible_name'] ?? '')) ?: (string) ($crmActivity['responsible_username'] ?? '');
?>

<div class="app-page">
  <div class="app-page-header">
    <div>
      <span class="app-page-kicker"><i class="bi bi-search"></i> CRM</span>
      <h5><?= htmlspecialchars((string) ($crmActivity['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h5>
    </div>
    <span class="badge <?= htmlspecialchars((string) ($crmActivity['activity_status_bootstrap_class'] ?? 'text-bg-secondary'), ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars((string) ($crmActivity['activity_status_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
  </div>

  <section class="border rounded p-3 mb-3">
    <h6>Dados da atividade</h6>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <tbody>
          <tr><th class="text-muted" style="width: 220px;">Código</th><td><?= (int) ($crmActivity['id'] ?? 0); ?></td></tr>
          <tr><th class="text-muted">Contato CRM</th><td><a href="<?= url('crm/informations/' . (int) ($crmActivity['tenant_person_id'] ?? 0)); ?>"><?= htmlspecialchars((string) ($crmActivity['person_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></a></td></tr>
          <tr><th class="text-muted">Tipo</th><td><span class="badge <?= htmlspecialchars((string) ($crmActivity['activity_type_bootstrap_class'] ?? 'text-bg-secondary'), ENT_QUOTES, 'UTF-8'); ?>"><i class="bi <?= htmlspecialchars((string) ($crmActivity['activity_type_icon'] ?? 'bi-sticky'), ENT_QUOTES, 'UTF-8'); ?>"></i> <?= htmlspecialchars((string) ($crmActivity['activity_type_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span></td></tr>
          <tr><th class="text-muted">Responsável</th><td><?= htmlspecialchars($responsibleName, ENT_QUOTES, 'UTF-8'); ?></td></tr>
          <tr><th class="text-muted">Agendada para</th><td><?= htmlspecialchars(\App\Models\Crm::formatDateTime($crmActivity['scheduled_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td></tr>
          <tr><th class="text-muted">Concluída em</th><td><?= htmlspecialchars(\App\Models\Crm::formatDateTime($crmActivity['completed_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td></tr>
          <tr><th class="text-muted">Registro</th><td><span class="badge <?= ((int) ($crmActivity['active'] ?? 0) === 1) ? 'text-bg-success' : 'text-bg-secondary'; ?>"><?= ((int) ($crmActivity['active'] ?? 0) === 1) ? 'Ativo' : 'Inativo'; ?></span></td></tr>
          <tr><th class="text-muted">Criado em</th><td><?= htmlspecialchars(\App\Models\Crm::formatDateTime($crmActivity['created'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td></tr>
          <tr><th class="text-muted">Atualizado em</th><td><?= htmlspecialchars(\App\Models\Crm::formatDateTime($crmActivity['modified'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td></tr>
        </tbody>
      </table>
    </div>
  </section>

  <section class="border rounded p-3 mb-3">
    <h6>Descrição</h6>
    <?php if (trim((string) ($crmActivity['description'] ?? '')) !== ''): ?>
      <p class="mb-0"><?= nl2br(htmlspecialchars((string) ($crmActivity['description'] ?? ''), ENT_QUOTES, 'UTF-8')); ?></p>
    <?php else: ?>
      <p class="text-muted mb-0">Nenhuma descrição cadastrada.</p>
    <?php endif; ?>
  </section>

  <div class="form-actions">
    <a href="<?= url('crm_activity'); ?>" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
    <a href="<?= url('crm/informations/' . (int) ($crmActivity['tenant_person_id'] ?? 0)); ?>" class="btn btn-outline-secondary"><i class="bi bi-person-lines-fill"></i> Ver contato CRM</a>
    <?php if (authCan('crm_activity.edit')): ?>
      <a href="<?= url('crm_activity/edit/' . (int) ($crmActivity['id'] ?? 0)); ?>" class="btn btn-primary"><i class="bi bi-pencil-square"></i> Editar</a>
    <?php endif; ?>
  </div>
</div>
