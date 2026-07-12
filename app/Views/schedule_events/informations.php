<?php
$event = is_array($event ?? null) ? $event : [];
$participants = is_array($participants ?? null) ? $participants : [];
$recurrence = is_array($recurrence ?? null) ? $recurrence : null;
$status = (string) ($event['status'] ?? '');
?>
<link rel="stylesheet" href="<?= url('assets/css/schedule_events/informations.css?v=1'); ?>">

<div class="app-page schedule-page">
  <div class="app-page-header">
    <div>
      <span class="app-page-kicker"><i class="bi bi-search"></i> Agenda</span>
      <h5><?= htmlspecialchars((string) ($event['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h5>
    </div>
    <span class="badge <?= htmlspecialchars(\App\Models\ScheduleEvent::statusBadgeClass($status), ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars(\App\Models\ScheduleEvent::statusLabel($status), ENT_QUOTES, 'UTF-8'); ?></span>
  </div>

  <section class="schedule-section">
    <div class="table-responsive">
      <table class="table table-hover align-middle">
        <tbody>
          <tr>
            <th class="text-muted" style="width: 220px;">Código</th>
            <td><?= (int) ($event['id'] ?? 0); ?></td>
          </tr>
          <tr>
            <th class="text-muted">Categoria</th>
            <td>
              <?php if (!empty($event['schedule_category_name'])): ?>
                <span class="schedule-event-category" style="background-color: <?= htmlspecialchars((string) ($event['schedule_category_background_color'] ?? '#0d6efd'), ENT_QUOTES, 'UTF-8'); ?>; color: <?= htmlspecialchars((string) ($event['schedule_category_description_color'] ?? '#ffffff'), ENT_QUOTES, 'UTF-8'); ?>;"><?= htmlspecialchars((string) $event['schedule_category_name'], ENT_QUOTES, 'UTF-8'); ?></span>
              <?php endif; ?>
            </td>
          </tr>
          <tr>
            <th class="text-muted">Local</th>
            <td><?= htmlspecialchars((string) ($event['location'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
          </tr>
          <tr>
            <th class="text-muted">Início</th>
            <td><?= htmlspecialchars(\App\Models\ScheduleEvent::formatDateTime($event['starts_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
          </tr>
          <tr>
            <th class="text-muted">Fim</th>
            <td><?= htmlspecialchars(\App\Models\ScheduleEvent::formatDateTime($event['ends_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
          </tr>
          <tr>
            <th class="text-muted">Dia inteiro</th>
            <td><?= ((int) ($event['all_day'] ?? 0) === 1) ? 'Sim' : 'Não'; ?></td>
          </tr>
          <tr>
            <th class="text-muted">Criado por</th>
            <td><?= htmlspecialchars((string) ($event['created_by_name'] ?? $event['created_by_username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
          </tr>
          <tr>
            <th class="text-muted">Criado em</th>
            <td><?= htmlspecialchars(\App\Models\ScheduleEvent::formatDateTime($event['created'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>

  <section class="schedule-section">
    <h6>Descrição</h6>
    <div class="schedule-description"><?= (string) ($event['description'] ?? ''); ?></div>
  </section>

  <section class="schedule-section">
    <h6>Participantes</h6>
    <?php if (!empty($participants)): ?>
      <div class="schedule-participant-list">
        <?php foreach ($participants as $participant): ?>
          <span class="schedule-participant <?= ((int) ($participant['active'] ?? 0) === 1) ? '' : 'is-inactive'; ?>"><?= htmlspecialchars((string) ($participant['person_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="text-muted mb-0">Nenhum participante cadastrado.</p>
    <?php endif; ?>
  </section>

  <?php if ($recurrence): ?>
    <section class="schedule-section">
      <h6>Recorrência</h6>
      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
          <tbody>
            <tr>
              <th class="text-muted" style="width: 220px;">Frequência</th>
              <td><?= htmlspecialchars(\App\Models\ScheduleEvent::frequencyLabel((string) ($recurrence['frequency'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
            </tr>
            <tr>
              <th class="text-muted">Intervalo</th>
              <td><?= (int) ($recurrence['interval_value'] ?? 1); ?></td>
            </tr>
            <tr>
              <th class="text-muted">Período</th>
              <td><?= htmlspecialchars(\App\Models\ScheduleEvent::formatDate($recurrence['period_start'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> até <?= htmlspecialchars(\App\Models\ScheduleEvent::formatDate($recurrence['period_end'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
            </tr>
            <tr>
              <th class="text-muted">Fins de semana</th>
              <td><?= ((int) ($recurrence['allow_weekends'] ?? 0) === 1) ? 'Permitidos' : 'Não permitidos'; ?></td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  <?php endif; ?>

  <div class="form-actions">
    <a href="<?= url('schedule'); ?>" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
    <?php if (authCan('schedule.edit')): ?>
      <a href="<?= url('schedule/edit/' . (int) ($event['id'] ?? 0)); ?>" class="btn btn-primary"><i class="bi bi-pencil-square"></i> Editar</a>
    <?php endif; ?>
  </div>
</div>

<script src="<?= url('assets/js/schedule_events/informations.js?v=1'); ?>"></script>
