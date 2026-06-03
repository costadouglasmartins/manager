  (function ()
  {
  const LOOKUP_SELECTOR = '[data-zip-code-lookup]';
  const ZIP_CODE_LENGTH = 8;

  function formatZipCode(value)
    {
    const digits = digitsOnly(value);

    if (digits.length !== ZIP_CODE_LENGTH)
      {
      return digits;
      }

    return digits.slice(0, 5) + '-' + digits.slice(5);
    }

  function getFeedbackElement(input)
    {
    const selector = input.dataset.zipCodeFeedbackTarget || '';

    if (selector !== '')
      {
      try
        {
        const element = document.querySelector(selector);
        if (element)
          {
          return element;
          }
        }
      catch (error)
        {
        console.warn('ZIP code lookup: feedback selector inválido:', selector, error);
        }
      }

    return null;
    }

  function getStreetInput(input)
    {
    const selector = input.dataset.zipCodeStreetTarget || '#street';

    try
      {
      return document.querySelector(selector);
      }
    catch (error)
      {
      console.warn('ZIP code lookup: street selector inválido:', selector, error);
      return null;
      }
    }

  function setFeedbackState(element, message, variant)
    {
    if (!element)
      {
      return;
      }

    element.className = 'form-text';

    if (variant === 'danger')
      {
      element.classList.add('text-danger');
      }
    else if (variant === 'success')
      {
      element.classList.add('text-success');
      }
    else
      {
      element.classList.add('text-secondary');
      }

    element.textContent = message || '';
    }

  function safeJson(response)
    {
    return response.json().catch(function ()
      {
      return null;
      });
    }

  async function lookupZipCode(input)
    {
    const endpoint = input.dataset.zipCodeEndpoint || '';
    const zipCode = digitsOnly(input.value);
    const feedback = getFeedbackElement(input);
    const streetInput = getStreetInput(input);
    const neighborhoodInput = document.getElementById('neighborhoodName');

    if (endpoint === '' || zipCode.length !== ZIP_CODE_LENGTH)
      {
      setFeedbackState(feedback, '', 'muted');
      return;
      }

    if (input.dataset.zipCodeLastLookup === zipCode)
      {
      return;
      }

    const previousController = input._zipCodeAbortController || null;
    if (previousController)
      {
      previousController.abort();
      }

    const controller = new AbortController();
    input._zipCodeAbortController = controller;

    input.dataset.zipCodeLastLookup = '';
    setFeedbackState(feedback, 'Buscando CEP...', 'muted');

    try
      {
      const url = new URL(endpoint, window.location.origin);
      url.searchParams.set('zip_code', zipCode);

      const response = await fetch(url.toString(), {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        signal: controller.signal
      });

      const data = await safeJson(response);

      if (!response.ok)
        {
        const message = (data && data.message) ? data.message : 'Não foi possível consultar o CEP agora.';
        setFeedbackState(feedback, message, 'danger');
        return;
        }

      if (!data || data.success !== true || data.found !== true)
        {
        setFeedbackState(feedback, (data && data.message) ? data.message : 'CEP não encontrado.', 'danger');
        return;
        }

      input.dataset.zipCodeLastLookup = zipCode;

      if (streetInput && typeof data.street === 'string' && data.street.trim() !== '')
        {
        const currentStreet = streetInput.value.trim();
        const previousAutoFill = streetInput.dataset.zipCodeAutoFilledStreet || '';

        if (currentStreet === '' || currentStreet === previousAutoFill)
          {
          streetInput.value = data.street;
          streetInput.dataset.zipCodeAutoFilledStreet = data.street;
          }
        }

      if (neighborhoodInput)
        {
        const neighborhoodLabel = [
          typeof data.neighborhood === 'string' ? data.neighborhood.trim() : '',
          typeof data.city === 'string' ? data.city.trim() : ''
        ].filter(function (part)
          {
          return part !== '';
          }).join(' - ');

        if (neighborhoodLabel !== '')
          {
          const currentNeighborhood = neighborhoodInput.value.trim();
          const previousAutoFillNeighborhood = neighborhoodInput.dataset.zipCodeAutoFilledNeighborhood || '';

          if (currentNeighborhood === '' || currentNeighborhood === previousAutoFillNeighborhood)
            {
            neighborhoodInput.value = neighborhoodLabel;
            neighborhoodInput.dataset.zipCodeAutoFilledNeighborhood = neighborhoodLabel;
            }
          }
        }

      const locationBits = [];
      if (typeof data.city === 'string' && data.city.trim() !== '')
        {
        locationBits.push(data.city.trim());
        }
      if (typeof data.state === 'string' && data.state.trim() !== '')
        {
        locationBits.push(data.state.trim());
        }

      setFeedbackState(
        feedback,
        locationBits.length ? 'CEP encontrado em ' + locationBits.join('/') + '.' : 'CEP encontrado.',
        'success'
      );
      }
    catch (error)
      {
      if (error && error.name === 'AbortError')
        {
        return;
        }

      setFeedbackState(feedback, 'Não foi possível consultar o CEP agora.', 'danger');
      }
    finally
      {
      if (input._zipCodeAbortController === controller)
        {
        input._zipCodeAbortController = null;
        }
      }
    }

  function bindZipCodeLookup(input)
    {
    let lookupTimer = null;

    input.addEventListener('input', function ()
      {
      const digits = digitsOnly(input.value);

      if (input.value !== digits)
        {
        input.value = digits;
        }

      if (lookupTimer)
        {
        window.clearTimeout(lookupTimer);
        lookupTimer = null;
        }

      if (digits.length < ZIP_CODE_LENGTH)
        {
        if (input._zipCodeAbortController)
          {
          input._zipCodeAbortController.abort();
          input._zipCodeAbortController = null;
          }

        input.dataset.zipCodeLastLookup = '';
        setFeedbackState(getFeedbackElement(input), '', 'muted');
        return;
        }

      lookupTimer = window.setTimeout(function ()
        {
        lookupTimer = null;
        lookupZipCode(input);
        }, 350);
      });

    input.addEventListener('blur', function ()
      {
      input.value = formatZipCode(input.value);
      });
    }

  document.querySelectorAll(LOOKUP_SELECTOR).forEach(bindZipCodeLookup);
  })();
