<?php
$formData = $formData ?? [];
$scheduleData = is_array($formData['schedule'] ?? null) ? $formData['schedule'] : [];
$recurrenceData = is_array($formData['recurrence'] ?? null) ? $formData['recurrence'] : [];
$selectedParticipants = is_array($scheduleData['participant_person_ids'] ?? null) ? array_map('strval', $scheduleData['participant_person_ids']) : [];
$today = date('Y-m-d');
?>
<link rel="stylesheet" href="<?= url('assets/css/schedule_events/create.css?v=1'); ?>">

<div class="app-page schedule-page">
  <div class="app-page-header">
    <div>
      <span class="app-page-kicker"><i class="bi bi-calendar-plus"></i> Agenda</span>
      <h5>Criar evento</h5>
    </div>
  </div>

  <?php if (!empty($formError)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars((string) $formError, ENT_QUOTES, 'UTF-8'); ?></div>
  <?php endif; ?>

  <form action="<?= url('schedule/store'); ?>" method="POST" class="needs-validation schedule-form js-schedule-form" novalidate>
    <input name="schedule[status]" type="hidden" value="<?= htmlspecialchars(\App\Models\ScheduleEvent::STATUS_SCHEDULED, ENT_QUOTES, 'UTF-8'); ?>">
    <section class="schedule-section">
      <div class="row g-3">
        <div class="col-12 col-lg-8">
          <label class="form-label" for="scheduleTitle">Título</label>
          <input autocomplete="off" class="form-control" id="scheduleTitle" maxlength="160" name="schedule[title]" required type="text" value="<?= htmlspecialchars((string) ($scheduleData['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
          <div class="invalid-feedback">Informe o título do evento.</div>
        </div>
        <div class="col-12 col-lg-4">
          <label class="form-label" for="scheduleCategory">Categoria</label>
          <select class="form-select" id="scheduleCategory" name="schedule[schedule_category_id]">
            <option value="">Sem categoria</option>
            <?php foreach ($categories as $category): ?>
              <option value="<?= (int) $category['id']; ?>" <?= ((string) ($scheduleData['schedule_category_id'] ?? '') === (string) $category['id']) ? 'selected' : ''; ?>><?= htmlspecialchars((string) $category['name'], ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12 col-lg-4">
          <label class="form-label" for="startDate">Data inicial</label>
          <input class="form-control" id="startDate" name="schedule[start_date]" required type="date" value="<?= htmlspecialchars((string) ($scheduleData['start_date'] ?? $today), ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="col-12 col-lg-4 js-schedule-time-field">
          <label class="form-label" for="startTime">Horário inicial</label>
          <input class="form-control" id="startTime" name="schedule[start_time]" type="time" value="<?= htmlspecialchars((string) ($scheduleData['start_time'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="col-12 col-lg-4">
          <label class="form-label" for="endDate">Data final</label>
          <input class="form-control" id="endDate" name="schedule[end_date]" type="date" value="<?= htmlspecialchars((string) ($scheduleData['end_date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="col-12 col-lg-4 js-schedule-time-field">
          <label class="form-label" for="endTime">Horário final</label>
          <input class="form-control" id="endTime" name="schedule[end_time]" type="time" value="<?= htmlspecialchars((string) ($scheduleData['end_time'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="col-12 col-lg-4">
          <label class="form-label" for="location">Local</label>
          <input autocomplete="off" class="form-control" id="location" maxlength="160" name="schedule[location]" type="text" value="<?= htmlspecialchars((string) ($scheduleData['location'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="col-12 col-lg-4 d-flex align-items-end">
          <div class="form-check form-switch mb-2">
            <input name="schedule[all_day]" type="hidden" value="0">
            <input class="form-check-input js-schedule-all-day" id="allDay" name="schedule[all_day]" type="checkbox" value="1" <?= ((string) ($scheduleData['all_day'] ?? '0') === '1') ? 'checked' : ''; ?>>
            <label class="form-check-label" for="allDay">Dia inteiro</label>
          </div>
        </div>
        <div class="col-12">
          <label class="form-label" for="participants">Participantes</label>
          <select class="form-select" id="participants" name="schedule[participant_person_ids][]" multiple size="6">
            <?php foreach ($people as $person): ?>
              <option value="<?= (int) $person['id']; ?>" <?= in_array((string) $person['id'], $selectedParticipants, true) ? 'selected' : ''; ?>><?= htmlspecialchars((string) $person['name'], ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12">
          <label class="form-label" for="description">Descrição</label>
          <textarea class="form-control" id="description" name="schedule[description]" rows="5"><?= htmlspecialchars((string) ($scheduleData['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>
      </div>
    </section>

    <section class="schedule-section">
      <div class="schedule-section-heading">
        <h6>Recorrência</h6>
        <div class="form-check form-switch">
          <input name="recurrence[enabled]" type="hidden" value="0">
          <input class="form-check-input js-schedule-recurrence-enabled" id="recurrenceEnabled" name="recurrence[enabled]" type="checkbox" value="1" <?= ((string) ($recurrenceData['enabled'] ?? '0') === '1') ? 'checked' : ''; ?>>
          <label class="form-check-label" for="recurrenceEnabled">Criar eventos periódicos</label>
        </div>
      </div>
      <div class="row g-3 js-schedule-recurrence-fields">
        <div class="col-12 col-lg-3">
          <label class="form-label" for="frequency">Frequência</label>
          <select class="form-select" id="frequency" name="recurrence[frequency]">
            <?php foreach ($frequencies as $value => $label): ?>
              <option value="<?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); ?>" <?= ((string) ($recurrenceData['frequency'] ?? \App\Models\ScheduleEvent::FREQUENCY_WEEKLY) === (string) $value) ? 'selected' : ''; ?>><?= htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12 col-lg-3">
          <label class="form-label" for="intervalValue">Intervalo</label>
          <input class="form-control" id="intervalValue" min="1" name="recurrence[interval_value]" type="number" value="<?= htmlspecialchars((string) ($recurrenceData['interval_value'] ?? '1'), ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="col-12 col-lg-3">
          <label class="form-label" for="periodStart">Início do período</label>
          <input class="form-control" id="periodStart" name="recurrence[period_start]" type="date" value="<?= htmlspecialchars((string) ($recurrenceData['period_start'] ?? $scheduleData['start_date'] ?? $today), ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="col-12 col-lg-3">
          <label class="form-label" for="periodEnd">Fim do período</label>
          <input class="form-control" id="periodEnd" name="recurrence[period_end]" type="date" value="<?= htmlspecialchars((string) ($recurrenceData['period_end'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="col-12">
          <div class="form-check form-switch">
            <input name="recurrence[allow_weekends]" type="hidden" value="0">
            <input class="form-check-input" id="allowWeekends" name="recurrence[allow_weekends]" type="checkbox" value="1" <?= ((string) ($recurrenceData['allow_weekends'] ?? '0') === '1') ? 'checked' : ''; ?>>
            <label class="form-check-label" for="allowWeekends">Permitir datas em fins de semana</label>
          </div>
        </div>
      </div>
    </section>

    <div class="form-actions">
      <a href="<?= url('schedule'); ?>" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Cancelar</a>
      <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> Salvar</button>
    </div>
  </form>
</div>

<script src="<?= url('assets/js/schedule_events/create.js?v=1'); ?>"></script>
