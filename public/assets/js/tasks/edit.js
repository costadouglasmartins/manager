(function ()
  {
  function initTaskForm()
    {
    const form = document.querySelector('.js-task-form');
    const status = document.querySelector('.js-task-status');
    const thirdParty = document.querySelector('.js-task-third-party');
    const thirdPartyInput = document.querySelector('.js-third-party-name');
    const editor = document.querySelector('.js-task-editor');
    const description = document.querySelector('.js-task-description');
    const feedback = document.querySelector('.js-task-description-feedback');

    function syncThirdParty()
      {
      if (!status || !thirdParty)
        {
        return;
        }

      const waiting = status.value === 'waiting_third_party';
      thirdParty.classList.toggle('is-hidden', !waiting);
      if (thirdPartyInput)
        {
        thirdPartyInput.required = waiting;
        }
      }

    function syncDescription()
      {
      if (!editor || !description)
        {
        return true;
        }

      description.value = editor.innerHTML.trim();
      const empty = editor.textContent.trim() === '';
      if (feedback)
        {
        feedback.classList.toggle('d-none', !empty);
        }

      return !empty;
      }

    document.querySelectorAll('[data-editor-command]').forEach((button) =>
      {
      button.addEventListener('click', () =>
        {
        const command = button.getAttribute('data-editor-command') || '';
        if (!command)
          {
          return;
          }

        editor.focus();
        document.execCommand(command, false, null);
        syncDescription();
        });
      });

    if (status)
      {
      status.addEventListener('change', syncThirdParty);
      syncThirdParty();
      }

    if (editor)
      {
      editor.addEventListener('input', syncDescription);
      }

    if (form)
      {
      form.addEventListener('submit', (event) =>
        {
        if (!syncDescription())
          {
          event.preventDefault();
          event.stopPropagation();
          }
        });
      }
    }

  document.addEventListener('DOMContentLoaded', initTaskForm);
  })();
