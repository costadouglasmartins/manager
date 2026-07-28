<?php
$report = (isset($report) && is_array($report)) ? $report : ['summary' => [], 'stages' => []];
$summary = (isset($report['summary']) && is_array($report['summary'])) ? $report['summary'] : [];
$stages = (isset($report['stages']) && is_array($report['stages'])) ? $report['stages'] : [];
$money = static fn ($value): string => 'R$ ' . number_format((float) ($value ?? 0), 2, ',', '.');
?>

<div class="app-page">
  <div class="app-page-header">
    <div>
      <span class="app-page-kicker"><i class="bi bi-graph-up-arrow"></i> CRM</span>
      <h5>Relatório comercial</h5>
    </div>
  </div>

  <div class="row g-3 mb-3">
    <div class="col-12 col-md-3">
      <section class="border rounded p-3 h-100">
        <div class="text-muted small">Oportunidades ativas</div>
        <div class="fs-4 fw-semibold"><?= (int) ($summary['total'] ?? 0); ?></div>
      </section>
    </div>
    <div class="col-12 col-md-3">
      <section class="border rounded p-3 h-100">
        <div class="text-muted small">Valor total</div>
        <div class="fs-4 fw-semibold"><?= htmlspecialchars($money($summary['total_value'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></div>
      </section>
    </div>
    <div class="col-12 col-md-3">
      <section class="border rounded p-3 h-100">
        <div class="text-muted small">Valor ponderado</div>
        <div class="fs-4 fw-semibold"><?= htmlspecialchars($money($summary['weighted_value'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></div>
      </section>
    </div>
    <div class="col-12 col-md-3">
      <section class="border rounded p-3 h-100">
        <div class="text-muted small">Ganhos / Perdidos</div>
        <div class="fs-4 fw-semibold"><?= (int) ($summary['won_total'] ?? 0); ?> / <?= (int) ($summary['lost_total'] ?? 0); ?></div>
      </section>
    </div>
  </div>

  <section class="border rounded p-3">
    <h6>Resumo por etapa</h6>
    <div class="table-responsive">
      <table class="table table-striped table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>Etapa</th>
            <th class="text-end">Quantidade</th>
            <th class="text-end">Valor</th>
            <th class="text-end">Valor ponderado</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($stages as $stage): ?>
            <tr>
              <td><span class="badge <?= htmlspecialchars((string) ($stage['bootstrap_class'] ?? 'text-bg-secondary'), ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars((string) ($stage['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span></td>
              <td class="text-end"><?= (int) ($stage['total'] ?? 0); ?></td>
              <td class="text-end"><?= htmlspecialchars($money($stage['total_value'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></td>
              <td class="text-end"><?= htmlspecialchars($money($stage['weighted_value'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>
