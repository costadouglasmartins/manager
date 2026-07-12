<link rel="stylesheet" href="<?= url('assets/css/schedule_events/calendar.css?v=2'); ?>">

<div class="app-page schedule-page">
  <div class="app-page-header schedule-calendar-header">
    <div>
      <span class="app-page-kicker"><i class="bi bi-calendar3"></i> Agenda</span>
      <h5>Calendário</h5>
    </div>
    <div class="schedule-calendar-actions">
      <?php if (authCan('schedule.index')): ?>
        <a href="<?= url('schedule'); ?>" class="btn btn-outline-secondary"><i class="bi bi-list-ul"></i> Listar</a>
      <?php endif; ?>
      <?php if (authCan('schedule.create')): ?>
        <a href="<?= url('schedule/create'); ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Novo evento</a>
      <?php endif; ?>
    </div>
  </div>

  <section class="schedule-section schedule-calendar-section">
    <div id="scheduleCalendar" data-events-url="<?= url('schedule/calendar-events'); ?>"></div>
  </section>
</div>

<script src="<?= url('assets/fullcalendar/dist/index.global.min.js'); ?>"></script>
<script src="<?= url('assets/fullcalendar/packages/core/locales-all.global.min.js'); ?>"></script>
<script src="<?= url('assets/js/schedule_events/calendar.js?v=2'); ?>"></script>
