(function ()
  {
  function setButtons(row, isRunning)
    {
    const startButton = row.querySelector('[data-action="start"]');
    const pauseButton = row.querySelector('[data-action="pause"]');
    const label = row.querySelector('[data-assignee-running-label]');

    if (startButton)
      {
      startButton.disabled = isRunning;
      }

    if (pauseButton)
      {
      pauseButton.disabled = !isRunning;
      }

    if (label)
      {
      label.textContent = isRunning ? 'Em execução' : 'Pausada';
      label.classList.toggle('text-bg-warning', isRunning);
      label.classList.toggle('text-bg-secondary', !isRunning);
      }
    }

  async function sendAction(button)
    {
    const taskAssigneeId = button.getAttribute('data-task-assignee-id') || '';
    const action = button.getAttribute('data-action') || '';
    const endpoint = button.getAttribute('data-endpoint') || '';
    const body = new URLSearchParams();
    body.set('task_assignee_id', taskAssigneeId);

    button.disabled = true;

    const response = await fetch(endpoint, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: body.toString()
    });

    const payload = await response.json();
    if (!response.ok || !payload.success)
      {
      throw new Error(payload.error || 'Não foi possível atualizar a execução.');
      }

    return payload.data || {};
    }

  function initExecutionActions()
    {
    document.querySelectorAll('.js-task-execution-action').forEach((button) =>
      {
      button.addEventListener('click', async () =>
        {
        const row = button.closest('[data-task-assignee-row]');
        if (!row)
          {
          return;
          }

        const pauseButton = row.querySelector('[data-action="pause"]');
        const wasRunning = pauseButton ? !pauseButton.disabled : false;

        try
          {
          const data = await sendAction(button);
          const assigneeId = button.getAttribute('data-task-assignee-id') || '';
          const assigneeDuration = document.querySelector('[data-assignee-duration="' + assigneeId + '"]');
          const totalDuration = document.querySelector('[data-task-total-duration]');

          if (assigneeDuration)
            {
            assigneeDuration.textContent = data.assignee_duration || '';
            }

          if (totalDuration)
            {
            totalDuration.textContent = data.total_duration || '';
            }

          setButtons(row, !!data.is_running);
          }
        catch (error)
          {
          alert(error.message);
          setButtons(row, wasRunning);
          }
        });
      });
    }

  document.addEventListener('DOMContentLoaded', initExecutionActions);
  })();
