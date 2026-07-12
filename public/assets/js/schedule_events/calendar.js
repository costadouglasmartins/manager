(function ()
  {
  function initScheduleCalendar()
    {
    const calendarEl = document.getElementById('scheduleCalendar');
    if (!calendarEl || !window.FullCalendar)
      {
      return;
      }

    const calendar = new FullCalendar.Calendar(calendarEl, {
      locale: 'pt-br',
      initialView: 'dayGridMonth',
      height: '100%',
      expandRows: true,
      nowIndicator: true,
      navLinks: true,
      selectable: false,
      dayMaxEvents: true,
      eventDisplay: 'block',
      headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
      },
      buttonText: {
        today: 'Hoje',
        month: 'Mês',
        week: 'Semana',
        day: 'Dia',
        list: 'Lista'
      },
      events: {
        url: calendarEl.dataset.eventsUrl || '',
        method: 'GET',
        failure: function ()
          {
          console.error('Não foi possível carregar os eventos da agenda.');
          }
      },
      eventTimeFormat: {
        hour: '2-digit',
        minute: '2-digit',
        hour12: false
      }
    });

    calendar.render();

    window.addEventListener('resize', () =>
      {
      calendar.updateSize();
      });
  }

  document.addEventListener('DOMContentLoaded', initScheduleCalendar);
  })();
