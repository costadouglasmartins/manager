<?php
$status = (isset($status) && is_array($status)) ? $status : [];
?>

<div class="app-page">
  <div class="app-page-header">
    <div>
      <span class="app-page-kicker"><i class="bi bi-search"></i> CRM</span>
      <h5>Status do CRM</h5>
    </div>
    <span class="badge <?= htmlspecialchars((string) ($status['bootstrap_class'] ?? 'text-bg-secondary'), ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars((string) ($status['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
  </div>

  <section class="border rounded p-3 mb-3">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <tbody>
          <tr>
            <th class="text-muted" style="width: 220px;">Código</th>
            <td><?= (int) ($status['id'] ?? 0); ?></td>
          </tr>
          <tr>
            <th class="text-muted">Nome</th>
            <td><?= htmlspecialchars((string) ($status['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
          </tr>
          <tr>
            <th class="text-muted">Chave</th>
            <td><code><?= htmlspecialchars((string) ($status['slug'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code></td>
          </tr>
          <tr>
            <th class="text-muted">Descrição</th>
            <td><?= nl2br(htmlspecialchars((string) ($status['description'] ?? ''), ENT_QUOTES, 'UTF-8')); ?></td>
          </tr>
          <tr>
            <th class="text-muted">Classe Bootstrap</th>
            <td><code><?= htmlspecialchars((string) ($status['bootstrap_class'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code></td>
          </tr>
          <tr>
            <th class="text-muted">Ordem</th>
            <td><?= (int) ($status['sort_order'] ?? 0); ?></td>
          </tr>
          <tr>
            <th class="text-muted">Status</th>
            <td><span class="badge <?= ((int) ($status['active'] ?? 0) === 1) ? 'text-bg-success' : 'text-bg-secondary'; ?>"><?= ((int) ($status['active'] ?? 0) === 1) ? 'Ativo' : 'Inativo'; ?></span></td>
          </tr>
          <tr>
            <th class="text-muted">Criado em</th>
            <td><?= htmlspecialchars(\App\Models\Crm::formatDateTime($status['created'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
          </tr>
          <tr>
            <th class="text-muted">Atualizado em</th>
            <td><?= htmlspecialchars(\App\Models\Crm::formatDateTime($status['modified'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>

  <div class="form-actions">
    <a href="<?= url('crm_status'); ?>" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
    <?php if (authCan('crm_status.edit')): ?>
      <a href="<?= url('crm_status/edit/' . (int) ($status['id'] ?? 0)); ?>" class="btn btn-primary"><i class="bi bi-pencil-square"></i> Editar</a>
    <?php endif; ?>
  </div>
</div>
