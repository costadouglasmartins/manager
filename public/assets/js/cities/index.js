(function ()
  {
  const countrySelect = document.getElementById('country_id');
  const stateSelect = document.getElementById('state_id');
  const feedback = document.getElementById('stateFilterFeedback');

  if (!countrySelect || !stateSelect)
    {
    return;
    }

  let requestController = null;

  function setFeedback(message, isError)
    {
    if (!feedback)
      {
      return;
      }

    feedback.textContent = message || '';
    feedback.className = isError ? 'form-text text-danger' : 'form-text text-secondary';
    }

  function resetStates(label, disabled)
    {
    stateSelect.replaceChildren(new Option(label, ''));
    stateSelect.disabled = disabled;
    }

  async function loadStates(countryId)
    {
    if (requestController)
      {
      requestController.abort();
      }

    if (countryId === '')
      {
      resetStates('Selecione um país primeiro', true);
      setFeedback('', false);
      return;
      }

    const endpoint = countrySelect.dataset.statesEndpoint || '';
    if (endpoint === '')
      {
      resetStates('Não foi possível carregar os estados', true);
      setFeedback('Endpoint de estados não configurado.', true);
      return;
      }

    const controller = new AbortController();
    requestController = controller;
    resetStates('Carregando estados...', true);
    setFeedback('Carregando estados...', false);

    try
      {
      const requestUrl = new URL(endpoint, window.location.origin);
      requestUrl.searchParams.set('country_id', countryId);

      const response = await fetch(requestUrl.toString(), {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        signal: controller.signal
      });

      const data = await response.json().catch(function ()
        {
        return null;
        });

      if (!response.ok || !data || !Array.isArray(data.items))
        {
        throw new Error('Invalid states response');
        }

      if (countrySelect.value !== countryId)
        {
        return;
        }

      resetStates('Todos os estados', false);
      data.items.forEach(function (item)
        {
        if (item && item.id !== null && item.id !== undefined)
          {
          stateSelect.add(new Option(item.label || item.name || String(item.id), String(item.id)));
          }
        });

      setFeedback(
        data.items.length === 0 ? 'Nenhum estado cadastrado para este país.' : '',
        false
      );
      }
    catch (error)
      {
      if (error && error.name === 'AbortError')
        {
        return;
        }

      resetStates('Não foi possível carregar os estados', true);
      setFeedback('Não foi possível carregar os estados deste país.', true);
      }
    finally
      {
      if (requestController === controller)
        {
        requestController = null;
        }
      }
    }

  countrySelect.addEventListener('change', function ()
    {
    loadStates(countrySelect.value);
    });
  })();
