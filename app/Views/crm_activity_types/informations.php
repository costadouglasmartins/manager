<?php
$activityType = (isset($activityType) && is_array($activityType)) ? $activityType : [];
$iconClass = (string) ($activityType['icon'] ?? 'bi-sticky');
?>

<div class="app-page">
  <div class="app-page-header">
    <div>
      <span class="app-page-kicker"><i class="bi bi-search"></i> CRM</span>
      <h5>Tipo de atividade CRM</h5>
    </div>
    <span class="badge <?= htmlspecialchars((string) ($activityType['bootstrap_class'] ?? 'text-bg-secondary'), ENT_QUOTES, 'UTF-8'); ?>"><i class="bi <?= htmlspecialchars($iconClass, ENT_QUOTES, 'UTF-8'); ?>"></i> <?= htmlspecialchars((string) ($activityType['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
  </div>

  <section class="border rounded p-3 mb-3">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <tbody>
          <tr><th class="text-muted" style="width: 220px;">Código</th><td><?= (int) ($activityType['id'] ?? 0); ?></td></tr>
          <tr><th class="text-muted">Nome</th><td><?= htmlspecialchars((string) ($activityType['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td></tr>
          <tr><th class="text-muted">Chave</th><td><code><?= htmlspecialchars((string) ($activityType['slug'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code></td></tr>
          <tr><th class="text-muted">Descrição</th><td><?= nl2br(htmlspecialchars((string) ($activityType['description'] ?? ''), ENT_QUOTES, 'UTF-8')); ?></td></tr>
          <tr><th class="text-muted">Classe Bootstrap</th><td><code><?= htmlspecialchars((string) ($activityType['bootstrap_class'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code></td></tr>
          <tr><th class="text-muted">Ícone</th><td><i class="bi <?= htmlspecialchars($iconClass, ENT_QUOTES, 'UTF-8'); ?>"></i> <code><?= htmlspecialchars($iconClass, ENT_QUOTES, 'UTF-8'); ?></code></td></tr>
          <tr><th class="text-muted">Ordem</th><td><?= (int) ($activityType['sort_order'] ?? 0); ?></td></tr>
          <tr><th class="text-muted">Status</th><td><span class="badge <?= ((int) ($activityType['active'] ?? 0) === 1) ? 'text-bg-success' : 'text-bg-secondary'; ?>"><?= ((int) ($activityType['active'] ?? 0) === 1) ? 'Ativo' : 'Inativo'; ?></span></td></tr>
          <tr><th class="text-muted">Criado em</th><td><?= htmlspecialchars(\App\Models\Crm::formatDateTime($activityType['created'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td></tr>
          <tr><th class="text-muted">Atualizado em</th><td><?= htmlspecialchars(\App\Models\Crm::formatDateTime($activityType['modified'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td></tr>
        </tbody>
      </table>
    </div>
  </section>

  <div class="form-actions">
    <a href="<?= url('crm_activity_type'); ?>" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
    <?php if (authCan('crm_activity_type.edit')): ?>
      <a href="<?= url('crm_activity_type/edit/' . (int) ($activityType['id'] ?? 0)); ?>" class="btn btn-primary"><i class="bi bi-pencil-square"></i> Editar</a>
    <?php endif; ?>
  </div>
</div>
