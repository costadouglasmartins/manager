(function (window, document)
  {
  const durationSelector = '[data-task-duration-seconds]';
  const tickMilliseconds = 1000;
  let intervalId = null;

  function parseInteger(value)
    {
    const parsed = Number.parseInt(value, 10);
    return Number.isFinite(parsed) && parsed > 0 ? parsed : 0;
    }

  function currentTimestamp()
    {
    return Math.floor(Date.now() / 1000);
    }

  function formatDuration(seconds)
    {
    const total = Math.max(0, parseInteger(seconds));
    const hours = Math.floor(total / 3600);
    const minutes = Math.floor((total % 3600) / 60);

    if (hours > 0)
      {
      return hours + 'h ' + String(minutes).padStart(2, '0') + 'min';
      }

    return minutes + 'min';
    }

  function getRunningCount(element)
    {
    return parseInteger(element.getAttribute('data-task-duration-running-count'));
    }

  function getStartedAt(element)
    {
    let startedAt = parseInteger(element.getAttribute('data-task-duration-started-at'));
    if (startedAt === 0)
      {
      startedAt = currentTimestamp();
      element.setAttribute('data-task-duration-started-at', String(startedAt));
      }

    return startedAt;
    }

  function calculateSeconds(element)
    {
    const baseSeconds = parseInteger(element.getAttribute('data-task-duration-seconds'));
    const runningCount = getRunningCount(element);
    if (runningCount === 0)
      {
      return baseSeconds;
      }

    return baseSeconds + ((currentTimestamp() - getStartedAt(element)) * runningCount);
    }

  function updateElement(element)
    {
    if (!element)
      {
      return;
      }

    element.textContent = formatDuration(calculateSeconds(element));
    }

  function eachDurationElement(root, callback)
    {
    if (!root)
      {
      return;
      }

    if (root.matches && root.matches(durationSelector))
      {
      callback(root);
      }

    root.querySelectorAll(durationSelector).forEach(callback);
    }

  function hasRunningDuration()
    {
    return Array.from(document.querySelectorAll(durationSelector)).some((element) => getRunningCount(element) > 0);
    }

  function syncTimer()
    {
    const shouldRun = hasRunningDuration();
    if (shouldRun && intervalId === null)
      {
      intervalId = window.setInterval(updateAll, tickMilliseconds);
      }

    if (!shouldRun && intervalId !== null)
      {
      window.clearInterval(intervalId);
      intervalId = null;
      }
    }

  function updateAll(root)
    {
    eachDurationElement(root || document, updateElement);
    syncTimer();
    }

  function setDuration(element, seconds, runningCount)
    {
    if (!element)
      {
      return;
      }

    element.setAttribute('data-task-duration-seconds', String(parseInteger(seconds)));
    element.setAttribute('data-task-duration-running-count', String(parseInteger(runningCount)));
    element.setAttribute('data-task-duration-started-at', String(currentTimestamp()));
    updateElement(element);
    syncTimer();
    }

  function setDurations(elements, seconds, runningCount)
    {
    Array.prototype.forEach.call(elements || [], (element) => setDuration(element, seconds, runningCount));
    }

  document.addEventListener('DOMContentLoaded', () => updateAll(document));
  document.addEventListener('visibilitychange', () => updateAll(document));

  window.TaskDurationTimer = {
    formatDuration,
    setDuration,
    setDurations,
    updateAll,
    updateElement
  };
  })(window, document);
