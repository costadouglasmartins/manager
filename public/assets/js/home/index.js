(function ()
  {
  function setButtons(row, isRunning)
    {
    const startButton = row.querySelector('[data-action="start"]');
    const pauseButton = row.querySelector('[data-action="pause"]');

    if (startButton)
      {
      startButton.disabled = isRunning;
      }

    if (pauseButton)
      {
      pauseButton.disabled = !isRunning;
      }
    }

  async function sendAction(button)
    {
    const endpoint = button.getAttribute('data-endpoint') || '';
    const taskAssigneeId = button.getAttribute('data-task-assignee-id') || '';
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

  function getTaskRunningCount(task)
    {
    const openCount = Number.parseInt(task.open_execution_count, 10);
    if (Number.isFinite(openCount))
      {
      return openCount;
      }

    return Number.parseInt(task.has_open_execution, 10) > 0 ? 1 : 0;
    }

  function updateTaskRow(data)
    {
    const task = data.task || {};
    const taskId = task.id || '';
    const taskDuration = document.querySelector('[data-dashboard-task-duration="' + taskId + '"]');
    const taskStatus = document.querySelector('[data-dashboard-task-status="' + taskId + '"]');

    if (taskDuration)
      {
      if (window.TaskDurationTimer)
        {
        window.TaskDurationTimer.setDuration(taskDuration, task.total_seconds || 0, getTaskRunningCount(task));
        }
      else
        {
        taskDuration.textContent = data.total_duration || '';
        }
      }

    if (taskStatus)
      {
      taskStatus.className = 'badge ' + (data.status_badge_class || 'text-bg-secondary');
      taskStatus.textContent = data.status_label || '';
      }
    }

  function initDashboardTaskActions()
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

        const wasRunning = row.querySelector('[data-action="pause"]') ? !row.querySelector('[data-action="pause"]').disabled : false;

        try
          {
          const data = await sendAction(button);
          const assigneeId = button.getAttribute('data-task-assignee-id') || '';
          const assigneeDuration = document.querySelector('[data-assignee-duration="' + assigneeId + '"]');

          if (assigneeDuration)
            {
            if (window.TaskDurationTimer && data.assignee)
              {
              window.TaskDurationTimer.setDuration(assigneeDuration, data.assignee.total_seconds || 0, data.is_running ? 1 : 0);
              }
            else
              {
              assigneeDuration.textContent = data.assignee_duration || '';
              }
            }

          setButtons(row, !!data.is_running);
          updateTaskRow(data);
          }
        catch (error)
          {
          alert(error.message);
          setButtons(row, wasRunning);
          }
        });
      });
    }

  document.addEventListener('DOMContentLoaded', initDashboardTaskActions);
  })();
