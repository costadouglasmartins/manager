<?php $money = static fn ($value): string => 'R$ ' . number_format((float) ($value ?? 0), 2, ',', '.'); ?>

<div class="app-page">
  <div class="app-page-header">
    <div>
      <span class="app-page-kicker"><i class="bi bi-kanban"></i> CRM</span>
      <h5>Funil de oportunidades</h5>
    </div>
  </div>

  <div class="row g-3 align-items-stretch">
    <?php foreach ($columns as $column): ?>
      <?php $stage = $column['stage']; ?>
      <div class="col-12 col-md-6 col-xl-4">
        <section class="border rounded p-3 h-100 d-flex flex-column">
          <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
            <div>
              <div class="badge <?= htmlspecialchars((string) ($stage['bootstrap_class'] ?? 'text-bg-secondary'), ENT_QUOTES, 'UTF-8'); ?> mb-2"><?= htmlspecialchars((string) ($stage['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
              <div class="text-muted small"><?= count($column['opportunities']); ?> oportunidade(s)</div>
            </div>
            <div class="fw-semibold text-end"><?= htmlspecialchars($money($column['total_value'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></div>
          </div>

          <?php if (!empty($column['opportunities'])): ?>
            <div class="d-flex flex-column gap-2">
              <?php foreach ($column['opportunities'] as $opportunity): ?>
                <a href="<?= url('crm_opportunity/informations/' . (int) $opportunity['id']); ?>" class="text-decoration-none text-reset border rounded p-2">
                  <div class="fw-semibold"><?= htmlspecialchars((string) ($opportunity['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                  <div class="small text-muted"><?= htmlspecialchars((string) ($opportunity['person_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                  <div class="d-flex justify-content-between gap-2 mt-2 small">
                    <span><?= htmlspecialchars($money($opportunity['estimated_value'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></span>
                    <span><?= (int) ($opportunity['probability'] ?? 0); ?>%</span>
                  </div>
                  <?php if (trim((string) ($opportunity['expected_close_date'] ?? '')) !== ''): ?>
                    <div class="small text-muted mt-1">Prev.: <?= htmlspecialchars(maskDateBr((string) ($opportunity['expected_close_date'] ?? '')) ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                  <?php endif; ?>
                </a>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <p class="text-muted mb-0">Nenhuma oportunidade nesta etapa.</p>
          <?php endif; ?>
        </section>
      </div>
    <?php endforeach; ?>
  </div>
</div>
