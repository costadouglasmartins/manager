<?php
$dashboard = (isset($dashboard) && is_array($dashboard)) ? $dashboard : [];
$cards = (isset($dashboard['cards']) && is_array($dashboard['cards'])) ? $dashboard['cards'] : [];
$statusSummary = (isset($dashboard['statusSummary']) && is_array($dashboard['statusSummary'])) ? $dashboard['statusSummary'] : [];
$upcomingActivities = (isset($dashboard['upcomingActivities']) && is_array($dashboard['upcomingActivities'])) ? $dashboard['upcomingActivities'] : [];
$recentActivities = (isset($dashboard['recentActivities']) && is_array($dashboard['recentActivities'])) ? $dashboard['recentActivities'] : [];
?>

<div class="app-page">
  <div class="app-page-header">
    <div>
      <span class="app-page-kicker"><i class="bi bi-speedometer2"></i> CRM</span>
      <h5>Painel CRM</h5>
    </div>
  </div>

  <div class="row g-3 mb-3">
    <div class="col-12 col-md-6 col-xl">
      <div class="border rounded p-3 h-100">
        <span class="text-muted">Contatos ativos</span>
        <h4 class="mb-0"><?= (int) ($cards['active_records'] ?? 0); ?></h4>
      </div>
    </div>
    <div class="col-12 col-md-6 col-xl">
      <div class="border rounded p-3 h-100">
        <span class="text-muted">Sem responsável</span>
        <h4 class="mb-0"><?= (int) ($cards['without_owner'] ?? 0); ?></h4>
      </div>
    </div>
    <div class="col-12 col-md-6 col-xl">
      <div class="border rounded p-3 h-100">
        <span class="text-muted">Sem atividade</span>
        <h4 class="mb-0"><?= (int) ($cards['without_activity'] ?? 0); ?></h4>
      </div>
    </div>
    <div class="col-12 col-md-6 col-xl">
      <div class="border rounded p-3 h-100">
        <span class="text-muted">Atividades hoje</span>
        <h4 class="mb-0"><?= (int) ($cards['today_activities'] ?? 0); ?></h4>
      </div>
    </div>
    <div class="col-12 col-md-6 col-xl">
      <div class="border rounded p-3 h-100">
        <span class="text-muted">Atrasadas</span>
        <h4 class="mb-0"><?= (int) ($cards['overdue_activities'] ?? 0); ?></h4>
      </div>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-12 col-xl-4">
      <section class="border rounded p-3 h-100">
        <h6>Contatos por status</h6>
        <div class="d-flex flex-column gap-2">
          <?php if (!empty($statusSummary)): ?>
            <?php foreach ($statusSummary as $status): ?>
              <div class="d-flex align-items-center justify-content-between gap-3">
                <span class="badge <?= htmlspecialchars((string) ($status['bootstrap_class'] ?? 'text-bg-secondary'), ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars((string) ($status['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="fw-semibold"><?= (int) ($status['total'] ?? 0); ?></span>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <p class="text-muted mb-0">Nenhum status cadastrado.</p>
          <?php endif; ?>
        </div>
      </section>
    </div>

    <div class="col-12 col-xl-4">
      <section class="border rounded p-3 h-100">
        <h6>Próximas atividades</h6>
        <?php if (!empty($upcomingActivities)): ?>
          <div class="list-group list-group-flush">
            <?php foreach ($upcomingActivities as $activity): ?>
              <a class="list-group-item list-group-item-action px-0" href="<?= url('crm_activity/informations/' . (int) ($activity['id'] ?? 0)); ?>">
                <div class="d-flex justify-content-between gap-2">
                  <span class="fw-semibold"><?= htmlspecialchars((string) ($activity['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                  <small class="text-muted text-nowrap"><?= htmlspecialchars(\App\Models\Crm::formatDateTime($activity['scheduled_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></small>
                </div>
                <small class="text-muted"><?= htmlspecialchars((string) ($activity['person_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></small>
              </a>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <p class="text-muted mb-0">Nenhuma atividade pendente agendada.</p>
        <?php endif; ?>
      </section>
    </div>

    <div class="col-12 col-xl-4">
      <section class="border rounded p-3 h-100">
        <h6>Últimas atividades registradas</h6>
        <?php if (!empty($recentActivities)): ?>
          <div class="list-group list-group-flush">
            <?php foreach ($recentActivities as $activity): ?>
              <a class="list-group-item list-group-item-action px-0" href="<?= url('crm_activity/informations/' . (int) ($activity['id'] ?? 0)); ?>">
                <div class="d-flex justify-content-between gap-2">
                  <span class="fw-semibold"><?= htmlspecialchars((string) ($activity['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                  <small class="text-muted text-nowrap"><?= htmlspecialchars(\App\Models\Crm::formatDateTime($activity['created'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></small>
                </div>
                <small class="text-muted"><?= htmlspecialchars((string) ($activity['person_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></small>
              </a>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <p class="text-muted mb-0">Nenhuma atividade registrada.</p>
        <?php endif; ?>
      </section>
    </div>
  </div>
</div>
