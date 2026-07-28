<?php
$activityStatus = (isset($activityStatus) && is_array($activityStatus)) ? $activityStatus : [];
?>

<div class="app-page">
  <div class="app-page-header">
    <div>
      <span class="app-page-kicker"><i class="bi bi-search"></i> CRM</span>
      <h5>Status de atividade CRM</h5>
    </div>
    <span class="badge <?= htmlspecialchars((string) ($activityStatus['bootstrap_class'] ?? 'text-bg-secondary'), ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars((string) ($activityStatus['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
  </div>

  <section class="border rounded p-3 mb-3">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <tbody>
          <tr><th class="text-muted" style="width: 220px;">Código</th><td><?= (int) ($activityStatus['id'] ?? 0); ?></td></tr>
          <tr><th class="text-muted">Nome</th><td><?= htmlspecialchars((string) ($activityStatus['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td></tr>
          <tr><th class="text-muted">Chave</th><td><code><?= htmlspecialchars((string) ($activityStatus['slug'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code></td></tr>
          <tr><th class="text-muted">Descrição</th><td><?= nl2br(htmlspecialchars((string) ($activityStatus['description'] ?? ''), ENT_QUOTES, 'UTF-8')); ?></td></tr>
          <tr><th class="text-muted">Classe Bootstrap</th><td><code><?= htmlspecialchars((string) ($activityStatus['bootstrap_class'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code></td></tr>
          <tr><th class="text-muted">Ordem</th><td><?= (int) ($activityStatus['sort_order'] ?? 0); ?></td></tr>
          <tr><th class="text-muted">Status final?</th><td><span class="badge <?= ((int) ($activityStatus['is_final'] ?? 0) === 1) ? 'text-bg-success' : 'text-bg-light text-dark'; ?>"><?= ((int) ($activityStatus['is_final'] ?? 0) === 1) ? 'Sim' : 'Não'; ?></span></td></tr>
          <tr><th class="text-muted">Status</th><td><span class="badge <?= ((int) ($activityStatus['active'] ?? 0) === 1) ? 'text-bg-success' : 'text-bg-secondary'; ?>"><?= ((int) ($activityStatus['active'] ?? 0) === 1) ? 'Ativo' : 'Inativo'; ?></span></td></tr>
          <tr><th class="text-muted">Criado em</th><td><?= htmlspecialchars(\App\Models\Crm::formatDateTime($activityStatus['created'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td></tr>
          <tr><th class="text-muted">Atualizado em</th><td><?= htmlspecialchars(\App\Models\Crm::formatDateTime($activityStatus['modified'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td></tr>
        </tbody>
      </table>
    </div>
  </section>

  <div class="form-actions">
    <a href="<?= url('crm_activity_status'); ?>" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
    <?php if (authCan('crm_activity_status.edit')): ?>
      <a href="<?= url('crm_activity_status/edit/' . (int) ($activityStatus['id'] ?? 0)); ?>" class="btn btn-primary"><i class="bi bi-pencil-square"></i> Editar</a>
    <?php endif; ?>
  </div>
</div>
