(function () {
  const form = document.getElementById("personEditForm");

  if (!form) {
    return;
  }

  const formAlert = document.getElementById("formAlert");
  const addressesContainer = document.getElementById("addressesContainer");
  const phonesContainer = document.getElementById("phonesContainer");
  const emailsContainer = document.getElementById("emailsContainer");

  const addressTemplate = document.getElementById("addressTemplate");
  const phoneTemplate = document.getElementById("phoneTemplate");
  const emailTemplate = document.getElementById("emailTemplate");

  const btnAddAddress = document.getElementById("btnAddAddress");
  const btnAddPhone = document.getElementById("btnAddPhone");
  const btnAddEmail = document.getElementById("btnAddEmail");
  const btnClearPersonForm = document.getElementById("btnClearPersonForm");
  const personNameInput = document.getElementById("personName");
  const personFantasyInput = document.getElementById("personFantasy");
  const personTypeSelect = document.getElementById("personType");
  const personDocumentInput = document.getElementById("personDocument");
  const personBirthdateInput = document.getElementById("personBirthdate");
  const personActiveSelect = document.getElementById("personActive");
  let addressIndex = 0;
  let phoneIndex = 0;
  let emailIndex = 0;
  const ZIP_CODE_LENGTH = 8;
  const ZIP_CODE_NEIGHBORHOOD_TYPE_DELAY_MS = 80;
  const AUTOCOMPLETE_RESULT_WAIT_MS = 1200;

  function cloneTemplate(template) {
    return template.content.firstElementChild.cloneNode(true);
  }

  function applyIndexedNames(block, token, index) {
    block.querySelectorAll("[name]").forEach(function (field) {
      const currentName = field.getAttribute("name");
      if (!currentName || currentName.indexOf(token) === -1) {
        return;
      }

      field.setAttribute("name", currentName.split(token).join(String(index)));
    });
  }

  function showFormAlert(message) {
    formAlert.textContent = message;
    formAlert.classList.remove("d-none");
  }

  function hideFormAlert() {
    formAlert.textContent = "";
    formAlert.classList.add("d-none");
  }

  function setFieldValue(field, value) {
    if (!field) {
      return;
    }

    field.value = (value === null || value === undefined) ? "" : String(value);
  }

  function clearFieldValidation(field) {
    if (!field) {
      return;
    }

    field.classList.remove("is-invalid");

    if (typeof field.setCustomValidity === "function") {
      field.setCustomValidity("");
    }
  }

  function applyCepMask(value) {
    const digits = String(value || "").replace(/\D/g, "").slice(0, 8);
    return digits.replace(/(\d{5})(\d{1,3})$/, "$1-$2");
  }

  function digitsOnly(value) {
    return String(value || "").replace(/\D/g, "");
  }

  function safeJson(response) {
    return response.json().catch(function () {
      return null;
    });
  }

  function abortError() {
    const error = new Error("Aborted");
    error.name = "AbortError";
    return error;
  }

  function wait(milliseconds, signal) {
    return new Promise(function (resolve, reject) {
      if (signal && signal.aborted) {
        reject(abortError());
        return;
      }

      const timer = window.setTimeout(function () {
        if (signal) {
          signal.removeEventListener("abort", onAbort);
        }

        resolve();
      }, milliseconds);

      function onAbort() {
        window.clearTimeout(timer);
        reject(abortError());
      }

      if (signal) {
        signal.addEventListener("abort", onAbort, { once: true });
      }
    });
  }

  function getPersonZipCodeEndpoint() {
    return (typeof PERSON_ZIP_CODE_SEARCH_URL === "string") ? PERSON_ZIP_CODE_SEARCH_URL : "";
  }

  function normalizeLookupText(value) {
    return String(value || "")
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, " ")
      .trim();
  }

  function buildCityStateLabel(zipCodeData) {
    const city = (typeof zipCodeData.city === "string") ? zipCodeData.city.trim() : "";
    const state = (typeof zipCodeData.state === "string") ? zipCodeData.state.trim() : "";

    if (city === "") {
      return "";
    }

    return state === "" ? city : city + " - " + state;
  }

  function buildNeighborhoodLabel(zipCodeData) {
    const neighborhood = (typeof zipCodeData.neighborhood === "string") ? zipCodeData.neighborhood.trim() : "";
    const city = (typeof zipCodeData.city === "string") ? zipCodeData.city.trim() : "";

    return [neighborhood, city].filter(function (part) {
      return part !== "";
    }).join(" - ");
  }

  function getZipCodeNeighborhoodName(zipCodeData) {
    return (typeof zipCodeData.neighborhood === "string") ? zipCodeData.neighborhood.trim() : "";
  }

  function setZipCodeFeedback(block, message, variant) {
    const feedback = block.querySelector(".js-address-zip-feedback");

    if (!feedback) {
      return;
    }

    feedback.className = "form-text js-address-zip-feedback";

    if (variant === "danger") {
      feedback.classList.add("text-danger");
    }
    else if (variant === "success") {
      feedback.classList.add("text-success");
    }
    else {
      feedback.classList.add("text-secondary");
    }

    feedback.textContent = message || "";
  }

  function setAutoFilledValue(field, value, dataKey) {
    const normalizedValue = String(value || "").trim();

    if (!field || normalizedValue === "") {
      return false;
    }

    const currentValue = field.value.trim();
    const previousAutoFilledValue = field.dataset[dataKey] || "";

    if (currentValue !== "" && currentValue !== previousAutoFilledValue) {
      return false;
    }

    field.value = normalizedValue;
    field.dataset[dataKey] = normalizedValue;
    clearFieldValidation(field);

    return true;
  }

  function clearAddressNeighborhoodCreatePanel(block) {
    const createPanel = block.querySelector(".addr-create-panel");
    const newNeighborhoodNameField = block.querySelector(".addr-new-neighborhood-name");
    const newCityIdField = block.querySelector(".addr-new-city-id");
    const newCityInput = block.querySelector(".addr-new-city-input");

    if (createPanel) {
      createPanel.classList.add("d-none");
    }

    if (newNeighborhoodNameField) {
      newNeighborhoodNameField.value = "";
    }

    if (newCityIdField) {
      newCityIdField.value = "";
    }

    if (newCityInput) {
      newCityInput.value = "";
    }
  }

  function clearAddressNeighborhoodResults(block) {
    const resultsList = block.querySelector(".addr-neighborhood-results");

    if (!resultsList) {
      return;
    }

    resultsList.innerHTML = "";
    resultsList.style.display = "none";
    resultsList.dataset.autocompletePending = "0";
  }

  function fillAddressNeighborhoodCreatePanel(block, zipCodeData, cityItem) {
    const createPanel = block.querySelector(".addr-create-panel");
    const newNeighborhoodNameField = block.querySelector(".addr-new-neighborhood-name");
    const newCityIdField = block.querySelector(".addr-new-city-id");
    const newCityInput = block.querySelector(".addr-new-city-input");
    const neighborhoodName = (typeof zipCodeData.neighborhood === "string") ? zipCodeData.neighborhood.trim() : "";

    if (!createPanel || neighborhoodName === "") {
      return;
    }

    createPanel.classList.remove("d-none");

    if (newNeighborhoodNameField) {
      newNeighborhoodNameField.value = neighborhoodName;
    }

    if (newCityIdField) {
      newCityIdField.value = cityItem && cityItem.id ? String(cityItem.id) : "";
    }

    if (newCityInput) {
      newCityInput.value = cityItem && cityItem.label ? cityItem.label : buildCityStateLabel(zipCodeData);
    }
  }

  async function fetchAutocompleteItems(endpoint, term, signal) {
    if (!endpoint || !term) {
      return [];
    }

    const requestUrl = new URL(endpoint, window.location.origin);
    requestUrl.searchParams.set("term", term);
    requestUrl.searchParams.set("limit", "20");

    const response = await fetch(requestUrl.toString(), {
      method: "GET",
      credentials: "same-origin",
      headers: {
        "Accept": "application/json",
        "X-Requested-With": "XMLHttpRequest"
      },
      signal: signal
    });

    if (!response.ok) {
      return [];
    }

    const data = await safeJson(response);
    return (data && Array.isArray(data.items)) ? data.items : [];
  }

  async function findCityByZipCodeData(zipCodeData, signal) {
    const cityName = (typeof zipCodeData.city === "string") ? zipCodeData.city.trim() : "";
    const stateAcronym = (typeof zipCodeData.state === "string") ? zipCodeData.state.trim() : "";
    const endpoint = (typeof PERSON_CITY_SEARCH_URL === "string") ? PERSON_CITY_SEARCH_URL : "";

    if (cityName === "") {
      return null;
    }

    const targetCity = normalizeLookupText(cityName);
    const targetState = normalizeLookupText(stateAcronym);
    const items = await fetchAutocompleteItems(endpoint, cityName, signal);
    const cityMatches = items.filter(function (item) {
      return normalizeLookupText(item.name || item.label) === targetCity;
    });

    if (!cityMatches.length) {
      return null;
    }

    if (targetState !== "") {
      const cityStateMatch = cityMatches.find(function (item) {
        return normalizeLookupText(item.state_acronym) === targetState;
      });

      if (cityStateMatch) {
        return cityStateMatch;
      }
    }

    return cityMatches.length === 1 ? cityMatches[0] : null;
  }

  function autocompleteResultItems(listEl) {
    if (!listEl) {
      return [];
    }

    return Array.from(listEl.querySelectorAll("[data-autocomplete-result='item']"));
  }

  function autocompleteItemMatchesZipCode(listItem, zipCodeData) {
    if (!listItem || !listItem._autocompleteItem) {
      return false;
    }

    const item = listItem._autocompleteItem;
    const targetNeighborhood = normalizeLookupText(getZipCodeNeighborhoodName(zipCodeData));
    const targetCity = normalizeLookupText(zipCodeData.city);
    const itemNeighborhood = normalizeLookupText(item.name || item.label);
    const itemCity = normalizeLookupText(item.city_name);

    if (targetNeighborhood !== "" && itemNeighborhood !== targetNeighborhood) {
      return false;
    }

    return targetCity === "" || itemCity === "" || itemCity === targetCity;
  }

  async function waitForAutocompleteResults(listEl, term, signal) {
    const startedAt = Date.now();

    while (Date.now() - startedAt < AUTOCOMPLETE_RESULT_WAIT_MS) {
      if (signal && signal.aborted) {
        throw abortError();
      }

      if (
        listEl
        && listEl.dataset.autocompleteTerm === term
        && listEl.dataset.autocompletePending !== "1"
      ) {
        return autocompleteResultItems(listEl);
      }

      await wait(40, signal);
    }

    return autocompleteResultItems(listEl);
  }

  function selectAutocompleteResult(listItem) {
    listItem.dispatchEvent(new MouseEvent("mousedown", {
      bubbles: true,
      cancelable: true,
      view: window
    }));
  }

  async function typeAndSelectNeighborhoodFromAutocomplete(block, zipCodeData, signal) {
    const neighborhoodInput = block.querySelector(".addr-neighborhood-input");
    const resultsList = block.querySelector(".addr-neighborhood-results");
    const neighborhoodName = getZipCodeNeighborhoodName(zipCodeData);

    if (!neighborhoodInput || !resultsList || neighborhoodName === "") {
      return false;
    }

    neighborhoodInput.value = "";
    neighborhoodInput.dataset.zipCodeAutoFilledNeighborhood = "";

    for (let index = 0; index < neighborhoodName.length; index += 1) {
      if (signal && signal.aborted) {
        throw abortError();
      }

      const typedTerm = neighborhoodName.slice(0, index + 1);
      neighborhoodInput.value = typedTerm;
      neighborhoodInput.dispatchEvent(new Event("input", { bubbles: true }));

      await wait(ZIP_CODE_NEIGHBORHOOD_TYPE_DELAY_MS, signal);

      const resultItems = await waitForAutocompleteResults(resultsList, typedTerm.trim(), signal);

      if (resultItems.length === 1 && autocompleteItemMatchesZipCode(resultItems[0], zipCodeData)) {
        selectAutocompleteResult(resultItems[0]);
        neighborhoodInput.dataset.zipCodeAutoFilledNeighborhood = neighborhoodInput.value;
        return true;
      }
    }

    return false;
  }

  async function applyZipCodeDataToAddressBlock(block, zipCodeData, signal) {
    const streetField = block.querySelector(".js-address-street");
    const neighborhoodIdField = block.querySelector(".addr-neighborhood-id");
    const neighborhoodInput = block.querySelector(".addr-neighborhood-input");
    const neighborhoodLabel = buildNeighborhoodLabel(zipCodeData);
    const result = {
      changed: false,
      neighborhoodPending: false
    };

    if (setAutoFilledValue(streetField, zipCodeData.street || "", "zipCodeAutoFilledStreet")) {
      result.changed = true;
    }

    if (!neighborhoodInput || neighborhoodLabel === "") {
      return result;
    }

    const currentNeighborhood = neighborhoodInput.value.trim();
    const previousAutoFilledNeighborhood = neighborhoodInput.dataset.zipCodeAutoFilledNeighborhood || "";

    if (currentNeighborhood !== "" && currentNeighborhood !== previousAutoFilledNeighborhood) {
      return result;
    }

    if (await typeAndSelectNeighborhoodFromAutocomplete(block, zipCodeData, signal)) {
      neighborhoodInput.dataset.zipCodeAutoFilledNeighborhood = neighborhoodInput.value;
      clearFieldValidation(neighborhoodInput);
      result.changed = true;
      return result;
    }

    if (neighborhoodIdField) {
      neighborhoodIdField.value = "";
    }

    clearAddressNeighborhoodResults(block);
    neighborhoodInput.value = neighborhoodLabel;
    neighborhoodInput.dataset.zipCodeAutoFilledNeighborhood = neighborhoodLabel;
    clearFieldValidation(neighborhoodInput);

    const cityItem = await findCityByZipCodeData(zipCodeData, signal);
    fillAddressNeighborhoodCreatePanel(block, zipCodeData, cityItem);

    result.changed = true;
    result.neighborhoodPending = true;
    return result;
  }

  async function lookupAddressZipCode(block) {
    const zipInput = block.querySelector(".js-address-zip");
    const endpoint = getPersonZipCodeEndpoint();
    const zipCode = zipInput ? digitsOnly(zipInput.value) : "";

    if (!zipInput || endpoint === "" || zipCode.length !== ZIP_CODE_LENGTH) {
      setZipCodeFeedback(block, "", "muted");
      return;
    }

    if (zipInput.dataset.zipCodeLastLookup === zipCode) {
      return;
    }

    const previousController = zipInput._zipCodeAbortController || null;
    if (previousController) {
      previousController.abort();
    }

    const controller = new AbortController();
    zipInput._zipCodeAbortController = controller;
    zipInput.dataset.zipCodeLastLookup = "";
    setZipCodeFeedback(block, "Buscando CEP...", "muted");

    try {
      const requestUrl = new URL(endpoint, window.location.origin);
      requestUrl.searchParams.set("zip_code", zipCode);

      const response = await fetch(requestUrl.toString(), {
        method: "GET",
        credentials: "same-origin",
        headers: {
          "Accept": "application/json",
          "X-Requested-With": "XMLHttpRequest"
        },
        signal: controller.signal
      });

      const data = await safeJson(response);

      if (!response.ok) {
        setZipCodeFeedback(block, (data && data.message) ? data.message : "Não foi possível consultar o CEP agora.", "danger");
        return;
      }

      if (!data || data.success !== true || data.found !== true) {
        setZipCodeFeedback(block, (data && data.message) ? data.message : "CEP não encontrado.", "danger");
        return;
      }

      zipInput.dataset.zipCodeLastLookup = zipCode;

      if (typeof data.formatted_zip_code === "string" && data.formatted_zip_code !== "") {
        zipInput.value = data.formatted_zip_code;
      }

      const applyResult = await applyZipCodeDataToAddressBlock(block, data, controller.signal);
      const locationBits = [];

      if (typeof data.city === "string" && data.city.trim() !== "") {
        locationBits.push(data.city.trim());
      }

      if (typeof data.state === "string" && data.state.trim() !== "") {
        locationBits.push(data.state.trim());
      }

      if (applyResult.neighborhoodPending) {
        setZipCodeFeedback(block, "CEP encontrado. Confirme o cadastro do bairro abaixo.", "success");
      }
      else {
        setZipCodeFeedback(
          block,
          locationBits.length ? "CEP encontrado em " + locationBits.join("/") + "." : "CEP encontrado.",
          "success"
        );
      }
    }
    catch (error) {
      if (error && error.name === "AbortError") {
        return;
      }

      setZipCodeFeedback(block, "Não foi possível consultar o CEP agora.", "danger");
    }
    finally {
      if (zipInput._zipCodeAbortController === controller) {
        zipInput._zipCodeAbortController = null;
      }
    }
  }

  function bindAddressZipCodeLookup(block) {
    const zipInput = block.querySelector(".js-address-zip");
    let lookupTimer = null;

    if (!zipInput) {
      return;
    }

    zipInput.addEventListener("input", function () {
      const zipCode = digitsOnly(zipInput.value);

      if (lookupTimer) {
        window.clearTimeout(lookupTimer);
        lookupTimer = null;
      }

      if (zipCode.length < ZIP_CODE_LENGTH) {
        if (zipInput._zipCodeAbortController) {
          zipInput._zipCodeAbortController.abort();
          zipInput._zipCodeAbortController = null;
        }

        zipInput.dataset.zipCodeLastLookup = "";
        setZipCodeFeedback(block, "", "muted");
        return;
      }

      lookupTimer = window.setTimeout(function () {
        lookupTimer = null;
        lookupAddressZipCode(block);
      }, 350);
    });

    zipInput.addEventListener("blur", function () {
      if (lookupTimer) {
        window.clearTimeout(lookupTimer);
        lookupTimer = null;
      }

      zipInput.value = applyCepMask(zipInput.value);

      if (digitsOnly(zipInput.value).length === ZIP_CODE_LENGTH) {
        lookupAddressZipCode(block);
      }
    });
  }

  document.addEventListener("input", function (event) {
    if (event.target && event.target.classList.contains("mask-cep")) {
      const input = event.target;
      const previousValue = input.value;
      const previousPosition = input.selectionStart || previousValue.length;
      input.value = applyCepMask(input.value);
      const diff = input.value.length - previousValue.length;
      const nextPosition = Math.max(0, previousPosition + diff);
      input.setSelectionRange(nextPosition, nextPosition);
    }
  });

  function updateBlockTitles(container, selector, prefix) {
    Array.from(container.querySelectorAll(selector)).forEach(function (block, index) {
      const title = block.querySelector(".js-block-title");
      if (title) {
        title.textContent = prefix + " " + (index + 1);
      }
    });
  }

  function resetBlock(block) {
    block.querySelectorAll("input").forEach(function (input) {
      input.value = "";
      input.classList.remove("is-invalid");
      delete input.dataset.zipCodeLastLookup;
      delete input.dataset.zipCodeAutoFilledStreet;
      delete input.dataset.zipCodeAutoFilledNeighborhood;
    });

    block.querySelectorAll("select").forEach(function (select) {
      select.selectedIndex = 0;
      select.classList.remove("is-invalid");
    });

    block.querySelectorAll("ul").forEach(function (list) {
      list.innerHTML = "";
      list.style.display = "none";
    });

    block.querySelectorAll(".addr-create-panel").forEach(function (panel) {
      panel.classList.add("d-none");
    });

    block.querySelectorAll(".js-address-zip-feedback").forEach(function (feedback) {
      feedback.textContent = "";
      feedback.className = "form-text js-address-zip-feedback";
    });
  }

  function clearBlockTransientState(block) {
    block.querySelectorAll("input, select, textarea").forEach(function (field) {
      clearFieldValidation(field);
      delete field.dataset.zipCodeLastLookup;
      delete field.dataset.zipCodeAutoFilledStreet;
      delete field.dataset.zipCodeAutoFilledNeighborhood;
    });

    block.querySelectorAll("ul").forEach(function (list) {
      list.innerHTML = "";
      list.style.display = "none";
      list.dataset.autocompletePending = "0";
    });

    block.querySelectorAll(".addr-create-panel").forEach(function (panel) {
      panel.classList.add("d-none");
    });

    block.querySelectorAll(".js-address-zip-feedback").forEach(function (feedback) {
      feedback.textContent = "";
      feedback.className = "form-text js-address-zip-feedback";
    });
  }

  function blockHasExistingAssociation(block, idSelector) {
    const idField = block.querySelector(idSelector);
    return !!(idField && idField.value && parseInt(idField.value, 10) > 0);
  }

  function removeBlock(button, container, selector, prefix) {
    const block = button.closest(selector);
    if (!block) {
      return;
    }

    const total = container.querySelectorAll(selector).length;
    if (total <= 1) {
      resetBlock(block);
    }
    else {
      block.remove();
    }

    updateBlockTitles(container, selector, prefix);
  }

  function initMiniAutocomplete(endpoint, inputEl, listEl, onSelect, extraOption) {
    let abortController = null;

    function clearList() {
      listEl.innerHTML = "";
      listEl.style.display = "none";
      listEl.dataset.autocompletePending = "0";
    }

    function renderList(items, term) {
      listEl.innerHTML = "";

      items.forEach(function (item) {
        const listItem = document.createElement("li");
        listItem.className = "list-group-item list-group-item-action py-1 px-2";
        listItem.dataset.autocompleteResult = "item";
        listItem._autocompleteItem = item;
        listItem.textContent = item.label;
        listItem.addEventListener("mousedown", function (event) {
          event.preventDefault();
          onSelect(item);
          clearList();
        });
        listEl.appendChild(listItem);
      });

      if (extraOption) {
        const extraItem = document.createElement("li");
        extraItem.className = "list-group-item list-group-item-action py-1 px-2 text-primary fw-semibold";
        extraItem.dataset.autocompleteResult = "extra";
        extraItem.textContent = extraOption.label(term);
        extraItem.addEventListener("mousedown", function (event) {
          event.preventDefault();
          clearList();
          extraOption.onClick(term);
        });
        listEl.appendChild(extraItem);
      }

      listEl.style.display = listEl.children.length ? "block" : "none";
      listEl.dataset.autocompleteTerm = term;
      listEl.dataset.autocompletePending = "0";
    }

    inputEl.addEventListener("input", async function () {
      const term = inputEl.value.trim();

      if (term.length < 1) {
        clearList();
        return;
      }

      listEl.dataset.autocompleteTerm = term;
      listEl.dataset.autocompletePending = "1";

      if (abortController) {
        abortController.abort();
      }

      abortController = new AbortController();

      try {
        const requestUrl = new URL(endpoint, window.location.origin);
        requestUrl.searchParams.set("term", term);

        const response = await fetch(requestUrl.toString(), {
          method: "GET",
          credentials: "same-origin",
          headers: {
            "Accept": "application/json",
            "X-Requested-With": "XMLHttpRequest"
          },
          signal: abortController.signal
        });

        const data = await response.json();
        renderList(data.items || [], term);
      }
      catch (error) {
        if (error && error.name !== "AbortError") {
          listEl.dataset.autocompletePending = "0";
          console.error("Erro no autocomplete:", error);
        }
      }
    });

    document.addEventListener("click", function (event) {
      if (event.target !== inputEl && !listEl.contains(event.target)) {
        clearList();
      }
    });
  }

  function initAddressBlock(block) {
    const hiddenId = block.querySelector(".addr-neighborhood-id");
    const textInput = block.querySelector(".addr-neighborhood-input");
    const resultsList = block.querySelector(".addr-neighborhood-results");
    const createPanel = block.querySelector(".addr-create-panel");
    const newNameInput = block.querySelector(".addr-new-neighborhood-name");
    const newCityId = block.querySelector(".addr-new-city-id");
    const newCityInput = block.querySelector(".addr-new-city-input");
    const newCityList = block.querySelector(".addr-new-city-results");
    const btnSave = block.querySelector(".addr-btn-save-neighborhood");
    const btnCancel = block.querySelector(".addr-btn-cancel-neighborhood");

    bindAddressZipCodeLookup(block);

    function showCreatePanel(term) {
      createPanel.classList.remove("d-none");
      newNameInput.value = term;
      newCityId.value = "";
      newCityInput.value = "";
      hiddenId.value = "";
      textInput.value = "";
      textInput.classList.remove("is-invalid");
    }

    function hideCreatePanel() {
      createPanel.classList.add("d-none");
      newNameInput.value = "";
      newCityId.value = "";
      newCityInput.value = "";
    }

    textInput.addEventListener("input", function () {
      hiddenId.value = "";
      textInput.classList.remove("is-invalid");
    });

    newCityInput.addEventListener("input", function () {
      newCityId.value = "";
      newCityInput.classList.remove("is-invalid");
    });

    initMiniAutocomplete(
      PERSON_NEIGHBORHOOD_SEARCH_URL,
      textInput,
      resultsList,
      function onSelect(item) {
        hiddenId.value = item.id;
        textInput.value = item.label;
        textInput.classList.remove("is-invalid");
        hideCreatePanel();
      },
      {
        label: function (term) {
          return "+ Criar bairro: \"" + term + "\"";
        },
        onClick: function (term) {
          showCreatePanel(term);
        }
      }
    );

    initMiniAutocomplete(
      PERSON_CITY_SEARCH_URL,
      newCityInput,
      newCityList,
      function onSelect(item) {
        newCityId.value = item.id;
        newCityInput.value = item.label;
        newCityInput.classList.remove("is-invalid");
      }
    );

    btnSave.addEventListener("click", async function () {
      const neighborhoodName = newNameInput.value.trim();
      const cityId = newCityId.value.trim();

      newNameInput.classList.remove("is-invalid");
      newCityInput.classList.remove("is-invalid");

      if (!neighborhoodName) {
        newNameInput.classList.add("is-invalid");
        newNameInput.focus();
        return;
      }

      if (!cityId) {
        newCityInput.classList.add("is-invalid");
        newCityInput.focus();
        return;
      }

      btnSave.disabled = true;
      btnSave.textContent = "Salvando...";

      try {
        const body = new URLSearchParams({
          name: neighborhoodName,
          city_id: cityId
        });

        const response = await fetch(PERSON_NEIGHBORHOOD_CREATE_URL, {
          method: "POST",
          credentials: "same-origin",
          headers: {
            "Accept": "application/json",
            "X-Requested-With": "XMLHttpRequest",
            "Content-Type": "application/x-www-form-urlencoded"
          },
          body: body.toString()
        });

        const data = await response.json().catch(function () {
          return {};
        });

        if (!response.ok || data.error) {
          throw new Error(data.error || "Nao foi possivel cadastrar o bairro.");
        }

        hiddenId.value = data.id;
        textInput.value = data.name;
        textInput.classList.remove("is-invalid");
        hideCreatePanel();
      }
      catch (error) {
        alert(error.message || "Erro de comunicacao ao salvar bairro.");
      }
      finally {
        btnSave.disabled = false;
        btnSave.textContent = "Salvar";
      }
    });

    btnCancel.addEventListener("click", function () {
      hideCreatePanel();
    });
  }

  function populateAddressBlock(block, data) {
    if (!data) {
      return;
    }

    const idField = block.querySelector(".js-address-id");
    const zipField = block.querySelector(".js-address-zip");
    const streetField = block.querySelector(".js-address-street");
    const numberField = block.querySelector(".js-address-number");
    const observationsField = block.querySelector(".js-address-observations");
    const neighborhoodIdField = block.querySelector(".addr-neighborhood-id");
    const neighborhoodInput = block.querySelector(".addr-neighborhood-input");

    if (idField) {
      idField.value = data.id || "";
    }

    if (zipField) {
      zipField.value = applyCepMask(data.zip_code || "");
    }

    if (streetField) {
      streetField.value = data.street || "";
      streetField.dataset.zipCodeAutoFilledStreet = streetField.value.trim();
    }

    if (numberField) {
      numberField.value = data.number || "";
    }

    if (observationsField) {
      observationsField.value = data.observations || "";
    }

    if (neighborhoodIdField) {
      neighborhoodIdField.value = data.neighborhood_id || "";
    }

    if (neighborhoodInput) {
      neighborhoodInput.value = data.neighborhood_label || "";
      neighborhoodInput.dataset.zipCodeAutoFilledNeighborhood = neighborhoodInput.value.trim();
    }
  }

  function populatePhoneBlock(block, data) {
    if (!data) {
      return;
    }

    const idField = block.querySelector(".js-phone-id");
    const numberField = block.querySelector(".js-phone-number");
    const notesField = block.querySelector(".js-phone-notes");

    if (idField) {
      idField.value = data.id || "";
    }

    if (numberField) {
      numberField.value = data.number || "";
    }

    if (notesField) {
      notesField.value = data.notes || "";
    }
  }

  function populateEmailBlock(block, data) {
    if (!data) {
      return;
    }

    const idField = block.querySelector(".js-email-id");
    const addressField = block.querySelector(".js-email-address");
    const descriptionField = block.querySelector(".js-email-description");
    const loginField = block.querySelector(".js-email-login");

    if (idField) {
      idField.value = data.id || "";
    }

    if (addressField) {
      addressField.value = data.address || "";
    }

    if (descriptionField) {
      descriptionField.value = data.description || "";
    }

    if (loginField) {
      loginField.value = String(data.is_login || 0);
    }
  }

  function addAddressBlock(data) {
    const block = cloneTemplate(addressTemplate);
    applyIndexedNames(block, "__INDEX__", addressIndex);
    addressIndex += 1;
    addressesContainer.appendChild(block);
    initAddressBlock(block);
    populateAddressBlock(block, data);
    updateBlockTitles(addressesContainer, ".js-address-item", "Endereço");
  }

  function addPhoneBlock(data) {
    const block = cloneTemplate(phoneTemplate);
    applyIndexedNames(block, "__INDEX__", phoneIndex);
    phoneIndex += 1;
    phonesContainer.appendChild(block);
    populatePhoneBlock(block, data);
    updateBlockTitles(phonesContainer, ".js-phone-item", "Telefone");
  }

  function addEmailBlock(data) {
    const block = cloneTemplate(emailTemplate);
    applyIndexedNames(block, "__INDEX__", emailIndex);
    emailIndex += 1;
    emailsContainer.appendChild(block);
    populateEmailBlock(block, data);
    updateBlockTitles(emailsContainer, ".js-email-item", "E-mail");
  }

  function clearFormValidationState() {
    form.classList.remove("was-validated");
    hideFormAlert();

    form.querySelectorAll("input, select, textarea").forEach(function (field) {
      clearFieldValidation(field);
    });
  }

  function keepOnlyFirstAssociation(container, selector, idSelector, prefix, addFallbackBlock) {
    const blocks = Array.from(container.querySelectorAll(selector));

    if (!blocks.length) {
      addFallbackBlock();
      return;
    }

    blocks.forEach(function (block, index) {
      if (index === 0) {
        if (blockHasExistingAssociation(block, idSelector)) {
          clearBlockTransientState(block);
        }
        else {
          resetBlock(block);
        }

        return;
      }

      block.remove();
    });

    updateBlockTitles(container, selector, prefix);
  }

  function resetRepeatableBlocks() {
    keepOnlyFirstAssociation(addressesContainer, ".js-address-item", ".js-address-id", "Endereço", addAddressBlock);
    keepOnlyFirstAssociation(phonesContainer, ".js-phone-item", ".js-phone-id", "Telefone", addPhoneBlock);
    keepOnlyFirstAssociation(emailsContainer, ".js-email-item", ".js-email-id", "E-mail", addEmailBlock);
  }

  function clearPersonEditForm() {
    clearFormValidationState();

    setFieldValue(personNameInput, "");
    setFieldValue(personFantasyInput, "");
    setFieldValue(personTypeSelect, "");
    setFieldValue(personDocumentInput, "");
    setFieldValue(personBirthdateInput, "");
    setFieldValue(personActiveSelect, "");

    resetRepeatableBlocks();

    if (personNameInput) {
      personNameInput.focus();
    }
  }

  function clearAssociationValidation() {
    form.querySelectorAll(
      ".js-address-street, .addr-neighborhood-input, .js-phone-number, .js-email-address"
    ).forEach(function (field) {
      clearFieldValidation(field);
    });
  }

  function isAddressTouched(block) {
    const zip = block.querySelector(".js-address-zip");
    const street = block.querySelector(".js-address-street");
    const number = block.querySelector(".js-address-number");
    const observations = block.querySelector(".js-address-observations");
    const neighborhood = block.querySelector(".addr-neighborhood-input");

    return [
      zip ? zip.value : "",
      street ? street.value : "",
      number ? number.value : "",
      observations ? observations.value : "",
      neighborhood ? neighborhood.value : ""
    ].some(function (value) {
      return value.trim() !== "";
    });
  }

  function isPhoneTouched(block) {
    const phoneNumber = block.querySelector(".js-phone-number");
    const notes = block.querySelector(".js-phone-notes");

    return [
      phoneNumber ? phoneNumber.value : "",
      notes ? notes.value : ""
    ].some(function (value) {
      return value.trim() !== "";
    });
  }

  function isEmailTouched(block) {
    const addressField = block.querySelector(".js-email-address");
    const descriptionField = block.querySelector(".js-email-description");
    const loginField = block.querySelector(".js-email-login");

    const address = addressField ? addressField.value : "";
    const description = descriptionField ? descriptionField.value : "";
    const isLogin = loginField ? loginField.value : "0";

    return address.trim() !== "" || description.trim() !== "" || isLogin === "1";
  }

  function validateAssociationBlocks() {
    let valid = true;

    addressesContainer.querySelectorAll(".js-address-item").forEach(function (block) {
      if (!isAddressTouched(block)) {
        return;
      }

      const street = block.querySelector(".js-address-street");
      const neighborhoodId = block.querySelector(".addr-neighborhood-id");
      const neighborhoodInput = block.querySelector(".addr-neighborhood-input");

      if (!street.value.trim()) {
        street.classList.add("is-invalid");
        valid = false;
      }

      if (!neighborhoodId.value.trim()) {
        neighborhoodInput.classList.add("is-invalid");
        valid = false;
      }
    });

    phonesContainer.querySelectorAll(".js-phone-item").forEach(function (block) {
      if (!isPhoneTouched(block)) {
        return;
      }

      const phoneNumber = block.querySelector(".js-phone-number");

      if (!phoneNumber.value.trim()) {
        phoneNumber.classList.add("is-invalid");
        valid = false;
      }
    });

    emailsContainer.querySelectorAll(".js-email-item").forEach(function (block) {
      if (!isEmailTouched(block)) {
        return;
      }

      const emailAddress = block.querySelector(".js-email-address");

      if (!emailAddress.value.trim() || !emailAddress.checkValidity()) {
        emailAddress.classList.add("is-invalid");
        valid = false;
      }
    });

    return valid;
  }

  if (btnAddAddress) {
    btnAddAddress.addEventListener("click", function () {
      addAddressBlock();
    });
  }

  if (btnAddPhone) {
    btnAddPhone.addEventListener("click", function () {
      addPhoneBlock();
    });
  }

  if (btnAddEmail) {
    btnAddEmail.addEventListener("click", function () {
      addEmailBlock();
    });
  }

  if (btnClearPersonForm) {
    btnClearPersonForm.addEventListener("click", function () {
      clearPersonEditForm();
    });
  }

  addressesContainer.addEventListener("click", function (event) {
    const button = event.target.closest("[data-remove-group='address']");
    if (button) {
      removeBlock(button, addressesContainer, ".js-address-item", "Endereço");
    }
  });

  phonesContainer.addEventListener("click", function (event) {
    const button = event.target.closest("[data-remove-group='phone']");
    if (button) {
      removeBlock(button, phonesContainer, ".js-phone-item", "Telefone");
    }
  });

  emailsContainer.addEventListener("click", function (event) {
    const button = event.target.closest("[data-remove-group='email']");
    if (button) {
      removeBlock(button, emailsContainer, ".js-email-item", "E-mail");
    }
  });

  form.addEventListener("submit", function (event) {
    hideFormAlert();
    clearAssociationValidation();

    const nativeValid = form.checkValidity();
    const associationsValid = validateAssociationBlocks();

    if (!nativeValid || !associationsValid) {
      event.preventDefault();
      event.stopPropagation();
      form.classList.add("was-validated");
      showFormAlert("Revise os campos destacados antes de salvar.");

      const firstInvalidField = form.querySelector(".is-invalid, :invalid");
      if (firstInvalidField && typeof firstInvalidField.scrollIntoView === "function") {
        firstInvalidField.scrollIntoView({ behavior: "smooth", block: "center" });
      }

      return;
    }

    form.classList.add("was-validated");
  });

  if (Array.isArray(initialAddresses) && initialAddresses.length) {
    initialAddresses.forEach(function (address) {
      addAddressBlock(address);
    });
  }
  else {
    addAddressBlock();
  }

  if (Array.isArray(initialPhones) && initialPhones.length) {
    initialPhones.forEach(function (phone) {
      addPhoneBlock(phone);
    });
  }
  else {
    addPhoneBlock();
  }

  if (Array.isArray(initialEmails) && initialEmails.length) {
    initialEmails.forEach(function (email) {
      addEmailBlock(email);
    });
  }
  else {
    addEmailBlock();
  }
})();
