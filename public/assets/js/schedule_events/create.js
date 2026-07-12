(function ()
  {
  function initScheduleForm()
    {
    const allDay = document.querySelector('.js-schedule-all-day');
    const timeFields = document.querySelectorAll('.js-schedule-time-field');
    const recurrenceEnabled = document.querySelector('.js-schedule-recurrence-enabled');
    const recurrenceFields = document.querySelector('.js-schedule-recurrence-fields');
    const periodStart = document.getElementById('periodStart');
    const startDate = document.getElementById('startDate');

    function syncAllDay()
      {
      const hidden = allDay && allDay.checked;
      timeFields.forEach((field) =>
        {
        field.classList.toggle('schedule-time-hidden', hidden);
        field.querySelectorAll('input').forEach((input) =>
          {
          input.required = !hidden && input.id === 'startTime';
          });
        });
      }

    function syncRecurrence()
      {
      if (!recurrenceEnabled || !recurrenceFields)
        {
        return;
        }

      const enabled = recurrenceEnabled.checked;
      recurrenceFields.classList.toggle('schedule-recurrence-hidden', !enabled);
      recurrenceFields.querySelectorAll('input, select').forEach((input) =>
        {
        if (input.type === 'hidden' || input.type === 'checkbox')
          {
          return;
          }

        input.required = enabled && input.id !== 'periodStart';
        });

      if (enabled && periodStart && startDate && periodStart.value === '')
        {
        periodStart.value = startDate.value;
        }
      }

    if (allDay)
      {
      allDay.addEventListener('change', syncAllDay);
      syncAllDay();
      }

    if (recurrenceEnabled)
      {
      recurrenceEnabled.addEventListener('change', syncRecurrence);
      syncRecurrence();
      }

    if (startDate && periodStart)
      {
      startDate.addEventListener('change', () =>
        {
        if (periodStart.value === '')
          {
          periodStart.value = startDate.value;
          }
        });
      }
    }

  document.addEventListener('DOMContentLoaded', initScheduleForm);
  })();
