<?php
$formData = $formData ?? [];
$formSchedule = is_array($formData['schedule'] ?? null) ? $formData['schedule'] : [];
$event = is_array($event ?? null) ? $event : [];
$selectedParticipants = [];
if (is_array($formSchedule['participant_person_ids'] ?? null))
	{
	$selectedParticipants = array_map('strval', $formSchedule['participant_person_ids']);
	}
else
	{
	foreach (($participants ?? []) as $participant)
		{
		if ((int) ($participant['active'] ?? 0) === 1)
			{
			$selectedParticipants[] = (string) ($participant['person_id'] ?? '');
			}
		}
	}
$allDay = (int) ($formSchedule['all_day'] ?? $event['all_day'] ?? 0) === 1;
$startsAt = (string) ($event['starts_at'] ?? '');
$endsAt = (string) ($event['ends_at'] ?? '');
$startDate = (string) ($formSchedule['start_date'] ?? substr($startsAt, 0, 10));
$startTime = (string) ($formSchedule['start_time'] ?? ($allDay ? '' : substr($startsAt, 11, 5)));
$endDate = (string) ($formSchedule['end_date'] ?? ($endsAt !== '' ? substr($endsAt, 0, 10) : ''));
$endTime = (string) ($formSchedule['end_time'] ?? ($allDay || $endsAt === '' ? '' : substr($endsAt, 11, 5)));
?>
<link rel="stylesheet" href="<?= url('assets/css/schedule_events/edit.css?v=1'); ?>">

<div class="app-page schedule-page">
  <div class="app-page-header">
    <div>
      <span class="app-page-kicker"><i class="bi bi-pencil-square"></i> Agenda</span>
      <h5>Editar evento</h5>
    </div>
  </div>

  <?php if (!empty($formError)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars((string) $formError, ENT_QUOTES, 'UTF-8'); ?></div>
  <?php endif; ?>

  <?php if (!empty($event['schedule_events_recurrence_id'])): ?>
    <div class="alert alert-info">Este evento foi gerado por recorrência. A edição altera somente esta ocorrência.</div>
  <?php endif; ?>

  <form action="<?= url('schedule/update/' . (int) ($event['id'] ?? 0)); ?>" method="POST" class="needs-validation schedule-form js-schedule-form" novalidate>
    <section class="schedule-section">
      <div class="row g-3">
        <div class="col-12 col-lg-8">
          <label class="form-label" for="scheduleTitle">Título</label>
          <input autocomplete="off" class="form-control" id="scheduleTitle" maxlength="160" name="schedule[title]" required type="text" value="<?= htmlspecialchars((string) ($formSchedule['title'] ?? $event['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
          <div class="invalid-feedback">Informe o título do evento.</div>
        </div>
        <div class="col-12 col-lg-4">
          <label class="form-label" for="scheduleCategory">Categoria</label>
          <select class="form-select" id="scheduleCategory" name="schedule[schedule_category_id]">
            <option value="">Sem categoria</option>
            <?php foreach ($categories as $category): ?>
              <option value="<?= (int) $category['id']; ?>" <?= ((string) ($formSchedule['schedule_category_id'] ?? $event['schedule_category_id'] ?? '') === (string) $category['id']) ? 'selected' : ''; ?>><?= htmlspecialchars((string) $category['name'], ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12 col-lg-4">
          <label class="form-label" for="status">Situação</label>
          <select class="form-select" id="status" name="schedule[status]">
            <?php foreach ($statuses as $value => $config): ?>
              <option value="<?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); ?>" <?= ((string) ($formSchedule['status'] ?? $event['status'] ?? '') === (string) $value) ? 'selected' : ''; ?>><?= htmlspecialchars((string) ($config['label'] ?? $value), ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12 col-lg-4">
          <label class="form-label" for="startDate">Data inicial</label>
          <input class="form-control" id="startDate" name="schedule[start_date]" required type="date" value="<?= htmlspecialchars($startDate, ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="col-12 col-lg-4 js-schedule-time-field">
          <label class="form-label" for="startTime">Horário inicial</label>
          <input class="form-control" id="startTime" name="schedule[start_time]" type="time" value="<?= htmlspecialchars($startTime, ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="col-12 col-lg-4">
          <label class="form-label" for="endDate">Data final</label>
          <input class="form-control" id="endDate" name="schedule[end_date]" type="date" value="<?= htmlspecialchars($endDate, ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="col-12 col-lg-4 js-schedule-time-field">
          <label class="form-label" for="endTime">Horário final</label>
          <input class="form-control" id="endTime" name="schedule[end_time]" type="time" value="<?= htmlspecialchars($endTime, ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="col-12 col-lg-4">
          <label class="form-label" for="location">Local</label>
          <input autocomplete="off" class="form-control" id="location" maxlength="160" name="schedule[location]" type="text" value="<?= htmlspecialchars((string) ($formSchedule['location'] ?? $event['location'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="col-12 col-lg-4 d-flex align-items-end">
          <div class="form-check form-switch mb-2">
            <input name="schedule[all_day]" type="hidden" value="0">
            <input class="form-check-input js-schedule-all-day" id="allDay" name="schedule[all_day]" type="checkbox" value="1" <?= $allDay ? 'checked' : ''; ?>>
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
          <textarea class="form-control" id="description" name="schedule[description]" rows="5"><?= htmlspecialchars((string) ($formSchedule['description'] ?? $event['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>
      </div>
    </section>

    <div class="form-actions">
      <a href="<?= url('schedule'); ?>" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Cancelar</a>
      <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> Salvar</button>
    </div>
  </form>
</div>

<script src="<?= url('assets/js/schedule_events/edit.js?v=1'); ?>"></script>
