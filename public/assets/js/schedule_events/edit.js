(function ()
  {
  function initScheduleForm()
    {
    const allDay = document.querySelector('.js-schedule-all-day');
    const timeFields = document.querySelectorAll('.js-schedule-time-field');

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

    if (allDay)
      {
      allDay.addEventListener('change', syncAllDay);
      syncAllDay();
      }
    }

  document.addEventListener('DOMContentLoaded', initScheduleForm);
  })();
