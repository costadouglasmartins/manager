<?php
$tenants = is_array($tenants ?? null) ? $tenants : [];
$currentTenantId = (int) ($currentTenant['id'] ?? 0);
$e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<div class="app-page" aria-labelledby="tenantSelectionTitle">
  <div class="app-page-header">
    <div>
      <span class="app-page-kicker"><i class="bi bi-database" aria-hidden="true"></i> Acesso ao sistema</span>
      <h5 id="tenantSelectionTitle">Selecionar tenant</h5>
    </div>
  </div>

  <?php if (!empty($error)): ?>
    <div class="alert alert-danger" role="alert"><?= $e($error); ?></div>
  <?php endif; ?>

  <?php if ($tenants !== []): ?>
    <form action="<?= $e(url('tenant/select')); ?>" id="tenantSelectionForm" method="post">
      <section class="app-section">
        <p class="text-body-secondary">Escolha o tenant que deseja acessar.</p>
        <div class="row g-3">
          <div class="col-12 col-md-8">
            <label class="form-label" for="tenant_id">Tenant</label>
            <select class="form-select" id="tenant_id" name="tenant_id" required>
              <option value="" <?= $currentTenantId <= 0 ? 'selected' : ''; ?>>Selecione um tenant</option>
              <?php foreach ($tenants as $tenant): ?>
                <?php
                $tenantId = (int) ($tenant['id'] ?? 0);
                $tenantName = trim((string) ($tenant['name'] ?? ''));
                $tenantFantasy = trim((string) ($tenant['fantasy'] ?? ''));
                $tenantLabel = $tenantName !== '' ? $tenantName : 'Tenant #' . $tenantId;
                if ($tenantFantasy !== '' && $tenantFantasy !== $tenantName)
                  {
                  $tenantLabel .= ' · ' . $tenantFantasy;
                  }
                ?>
                <option value="<?= $tenantId; ?>" <?= $currentTenantId === $tenantId ? 'selected' : ''; ?>><?= $e($tenantLabel); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </section>
    </form>
  <?php else: ?>
    <div class="alert alert-info" role="status">Nenhum tenant disponível para o seu usuário. Entre em contato com o administrador.</div>
  <?php endif; ?>

  <div class="view-actions">
    <a class="btn btn-outline-danger" href="<?= $e(url('user/logout')); ?>"><i class="bi bi-box-arrow-right" aria-hidden="true"></i> Encerrar sessão</a>
    <?php if ($tenants !== []): ?>
      <button class="btn btn-primary" form="tenantSelectionForm" type="submit"><i class="bi bi-box-arrow-in-right" aria-hidden="true"></i> Entrar</button>
    <?php endif; ?>
  </div>
</div>
