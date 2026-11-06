(function ()
    {
      const form = document.getElementById("personCreateForm");

      if (!form)
        {
          return;
        }

      const formAlert = document.getElementById("formAlert");
      const addressesContainer = document.getElementById("addressesContainer");
      const phonesContainer = document.getElementById("phonesContainer");
      const emailsContainer = document.getElementById("emailsContainer");
      const personNameInput = document.getElementById("personName");
      const personFantasyInput = document.getElementById("personFantasy");
      const personTypeSelect = document.getElementById("personType");
      // Role checkboxes (person[role][]); several roles can be selected at once.
      const personRoleInputs = Array.from(form.querySelectorAll(".js-person-role"));
      const personDocumentInput = document.getElementById("personDocument");
      const personBirthdateInput = document.getElementById("personBirthdate");
      const personActiveSelect = document.getElementById("personActive");
      const createUserSelect = document.getElementById("createSystemUser");
      const userFieldsSection = document.getElementById("userFieldsSection");
      const userUsernameInput = document.getElementById("userUsername");
      const userPasswordInput = document.getElementById("userPassword");
      const userTypeSelect = document.getElementById("userType");
      const userActiveSelect = document.getElementById("userActive");

      const addressTemplate = document.getElementById("addressTemplate");
      const phoneTemplate = document.getElementById("phoneTemplate");
      const emailTemplate = document.getElementById("emailTemplate");

      const btnAddAddress = document.getElementById("btnAddAddress");
      const btnAddPhone = document.getElementById("btnAddPhone");
      const btnAddEmail = document.getElementById("btnAddEmail");
      const btnClearPersonForm = document.getElementById("btnClearPersonForm");
      let addressIndex = 0;
      let phoneIndex = 0;
      let emailIndex = 0;
      let validationMessage = "Revise os campos destacados antes de salvar.";
      let usernameWasSuggested = false;
      let userActiveWasSuggested = false;
      const FORM_DRAFT_STORAGE_KEY = "manager.people.create.formDraft.v1";
      const formDraftStorage = resolveSessionStorage();
      let draftSaveTimer = null;
      let draftPersistenceEnabled = false;
      let draftHasChanges = false;
      const ZIP_CODE_LENGTH = 8;
      const ZIP_CODE_NEIGHBORHOOD_TYPE_DELAY_MS = 80;
      const AUTOCOMPLETE_RESULT_WAIT_MS = 1200;

      function resolveSessionStorage()
        {
          try
            {
              const storage = window.sessionStorage;
              const testKey = FORM_DRAFT_STORAGE_KEY + ".test";
              storage.setItem(testKey, "1");
              storage.removeItem(testKey);
              return storage;
            }
          catch (error)
            {
              return null;
            }
        }

      function getFieldValue(field)
        {
          return field ? field.value : "";
        }

      function setFieldValue(field, value)
        {
          if (!field)
            {
              return;
            }

          field.value = (value === null || value === undefined) ? "" : String(value);
        }

      function getRoleValues()
        {
          return personRoleInputs.filter(function (input)
            {
              return input.checked;
            }).map(function (input)
            {
              return input.value;
            });
        }

      // Accepts an array or a legacy CSV string and syncs the dependent employee card.
      function setRoleValues(roles)
        {
          const list = Array.isArray(roles) ? roles : String(roles || "").split(",");
          const normalized = list.map(function (role)
            {
              return String(role).trim();
            });

          personRoleInputs.forEach(function (input)
            {
              const wasChecked = input.checked;
              input.checked = normalized.indexOf(input.value) !== -1;

              if (wasChecked !== input.checked)
                {
                  input.dispatchEvent(new Event("change", {bubbles: true}));
                }
            });
        }

      // Collects the "Dados do funcionário" fields tagged with data-employee-draft.
      function collectEmployeeDraft()
        {
          const data = {};

          form.querySelectorAll("[data-employee-draft]").forEach(function (field)
            {
              data[field.getAttribute("data-employee-draft")] = field.value;
            });

          return data;
        }

      function restoreEmployeeDraft(data)
        {
          if (!data || typeof data !== "object")
            {
              return;
            }

          form.querySelectorAll("[data-employee-draft]").forEach(function (field)
            {
              const key = field.getAttribute("data-employee-draft");

              if (Object.prototype.hasOwnProperty.call(data, key))
                {
                  setFieldValue(field, data[key]);
                }
            });
        }

      // Defaults used when the form is cleared (selects without a default are emptied).
      function resetEmployeeFields()
        {
          const defaults = {employment_type: "clt", status: "active"};

          form.querySelectorAll("[data-employee-draft]").forEach(function (field)
            {
              const key = field.getAttribute("data-employee-draft");
              setFieldValue(field, Object.prototype.hasOwnProperty.call(defaults, key) ? defaults[key] : "");
            });
        }

      function assignDraftField(field, data, key)
        {
          if (!data || !Object.prototype.hasOwnProperty.call(data, key))
            {
              return;
            }

          setFieldValue(field, data[key]);
        }

      function collectAddressDraft(block)
        {
          const createPanel = block.querySelector(".addr-create-panel");

          return {
            zip_code: getFieldValue(block.querySelector(".js-address-zip")),
            street: getFieldValue(block.querySelector(".js-address-street")),
            number: getFieldValue(block.querySelector(".js-address-number")),
            observations: getFieldValue(block.querySelector(".js-address-observations")),
            neighborhood_id: getFieldValue(block.querySelector(".addr-neighborhood-id")),
            neighborhood_label: getFieldValue(block.querySelector(".addr-neighborhood-input")),
            create_panel_open: createPanel ? !createPanel.classList.contains("d-none") : false,
            new_neighborhood_name: getFieldValue(block.querySelector(".addr-new-neighborhood-name")),
            new_city_id: getFieldValue(block.querySelector(".addr-new-city-id")),
            new_city_label: getFieldValue(block.querySelector(".addr-new-city-input"))
          };
        }

      function collectPhoneDraft(block)
        {
          return {
            number: getFieldValue(block.querySelector(".js-phone-number")),
            notes: getFieldValue(block.querySelector(".js-phone-notes"))
          };
        }

      function collectEmailDraft(block)
        {
          return {
            address: getFieldValue(block.querySelector(".js-email-address")),
            description: getFieldValue(block.querySelector(".js-email-description")),
            is_login: getFieldValue(block.querySelector(".js-email-login"))
          };
        }

      function collectFormDraft()
        {
          return {
            version: 1,
            saved_at: (new Date()).toISOString(),
            person: {
              active: getFieldValue(personActiveSelect),
              name: getFieldValue(personNameInput),
              fantasy: getFieldValue(personFantasyInput),
              type: getFieldValue(personTypeSelect),
              role: getRoleValues(),
              document: getFieldValue(personDocumentInput),
              birthdate: getFieldValue(personBirthdateInput)
            },
            employee: collectEmployeeDraft(),
            create_user: getFieldValue(createUserSelect),
            user: {
              active: getFieldValue(userActiveSelect),
              username: getFieldValue(userUsernameInput),
              password: getFieldValue(userPasswordInput),
              type: getFieldValue(userTypeSelect)
            },
            addresses: Array.from(addressesContainer.querySelectorAll(".js-address-item")).map(collectAddressDraft),
            phones: Array.from(phonesContainer.querySelectorAll(".js-phone-item")).map(collectPhoneDraft),
            emails: Array.from(emailsContainer.querySelectorAll(".js-email-item")).map(collectEmailDraft)
          };
        }

      function readFormDraft()
        {
          if (!formDraftStorage)
            {
              return null;
            }

          const rawDraft = formDraftStorage.getItem(FORM_DRAFT_STORAGE_KEY);

          if (!rawDraft)
            {
              return null;
            }

          try
            {
              const draft = JSON.parse(rawDraft);
              return (draft && draft.version === 1) ? draft : null;
            }
          catch (error)
            {
              formDraftStorage.removeItem(FORM_DRAFT_STORAGE_KEY);
              return null;
            }
        }

      function persistFormDraft()
        {
          if (!draftPersistenceEnabled || !draftHasChanges || !formDraftStorage)
            {
              return;
            }

          try
            {
              formDraftStorage.setItem(FORM_DRAFT_STORAGE_KEY, JSON.stringify(collectFormDraft()));
              draftHasChanges = false;
            }
          catch (error)
            {
              console.warn("Não foi possível salvar o rascunho do formulário:", error);
            }
        }

      function schedulePersistFormDraft()
        {
          if (!draftPersistenceEnabled)
            {
              return;
            }

          draftHasChanges = true;

          if (draftSaveTimer)
            {
              window.clearTimeout(draftSaveTimer);
            }

          draftSaveTimer = window.setTimeout(function ()
            {
              draftSaveTimer = null;
              persistFormDraft();
            }, 100);
        }

      function clearFormDraft()
        {
          if (draftSaveTimer)
            {
              window.clearTimeout(draftSaveTimer);
              draftSaveTimer = null;
            }

          draftHasChanges = false;

          if (!formDraftStorage)
            {
              return;
            }

          formDraftStorage.removeItem(FORM_DRAFT_STORAGE_KEY);
        }

      function restoreStaticDraft(draft)
        {
          if (!draft)
            {
              return;
            }

          const person = draft.person || {};
          const user = draft.user || {};

          assignDraftField(personActiveSelect, person, "active");
          assignDraftField(personNameInput, person, "name");
          assignDraftField(personFantasyInput, person, "fantasy");
          assignDraftField(personTypeSelect, person, "type");
          setRoleValues(person.role);
          restoreEmployeeDraft(draft.employee);
          assignDraftField(personDocumentInput, person, "document");
          assignDraftField(personBirthdateInput, person, "birthdate");
          assignDraftField(createUserSelect, draft, "create_user");
          assignDraftField(userActiveSelect, user, "active");
          assignDraftField(userUsernameInput, user, "username");
          assignDraftField(userPasswordInput, user, "password");
          assignDraftField(userTypeSelect, user, "type");
        }

      function getRepeatableInitialData(draft, key, fallbackData)
        {
          if (draft && Array.isArray(draft[key]) && draft[key].length)
            {
              return draft[key];
            }

          return Array.isArray(fallbackData) ? fallbackData : [];
        }

      function cloneTemplate(template)
        {
          return template.content.firstElementChild.cloneNode(true);
        }

      function applyIndexedNames(block, token, index)
        {
          block.querySelectorAll("[name]").forEach(function (field)
            {
              const currentName = field.getAttribute("name");
              if (!currentName || currentName.indexOf(token) === -1)
                {
                  return;
                }

              field.setAttribute("name", currentName.split(token).join(String(index)));
            });
        }

      function showFormAlert(message)
        {
          formAlert.textContent = message;
          formAlert.classList.remove("d-none");
        }

      function hideFormAlert()
        {
          formAlert.textContent = "";
          formAlert.classList.add("d-none");
        }

      function clearFieldValidation(field)
        {
          if (!field)
            {
              return;
            }

          field.classList.remove("is-invalid");

          if (typeof field.setCustomValidity === "function")
            {
              field.setCustomValidity("");
            }
        }

      function resetValidationMessage()
        {
          validationMessage = "Revise os campos destacados antes de salvar.";
        }

      function applyCepMask(value)
        {
          const digits = String(value || "").replace(/\D/g, "").slice(0, 8);
          return digits.replace(/(\d{5})(\d{1,3})$/, "$1-$2");
        }

      function digitsOnly(value)
        {
          return String(value || "").replace(/\D/g, "");
        }

      function safeJson(response)
        {
          return response.json().catch(function ()
            {
              return null;
            });
        }

      function abortError()
        {
          const error = new Error("Aborted");
          error.name = "AbortError";
          return error;
        }

      function wait(milliseconds, signal)
        {
          return new Promise(function (resolve, reject)
            {
              if (signal && signal.aborted)
                {
                  reject(abortError());
                  return;
                }

              const timer = window.setTimeout(function ()
                {
                  if (signal)
                    {
                      signal.removeEventListener("abort", onAbort);
                    }

                  resolve();
                }, milliseconds);

              function onAbort()
                {
                  window.clearTimeout(timer);
                  reject(abortError());
                }

              if (signal)
                {
                  signal.addEventListener("abort", onAbort, {once: true});
                }
            });
        }

      function getPersonZipCodeEndpoint()
        {
          return (typeof PERSON_ZIP_CODE_SEARCH_URL === "string") ? PERSON_ZIP_CODE_SEARCH_URL : "";
        }

      function normalizeLookupText(value)
        {
          return String(value || "")
            .normalize("NFD")
            .replace(/[\u0300-\u036f]/g, "")
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, " ")
            .trim();
        }

      function buildCityStateLabel(zipCodeData)
        {
          const city = (typeof zipCodeData.city === "string") ? zipCodeData.city.trim() : "";
          const state = (typeof zipCodeData.state === "string") ? zipCodeData.state.trim() : "";

          if (city === "")
            {
              return "";
            }

          return state === "" ? city : city + " - " + state;
        }

      function buildNeighborhoodLabel(zipCodeData)
        {
          const neighborhood = (typeof zipCodeData.neighborhood === "string") ? zipCodeData.neighborhood.trim() : "";
          const city = (typeof zipCodeData.city === "string") ? zipCodeData.city.trim() : "";

          return [neighborhood, city].filter(function (part)
            {
              return part !== "";
            }).join(" - ");
        }

      function getZipCodeNeighborhoodName(zipCodeData)
        {
          return (typeof zipCodeData.neighborhood === "string") ? zipCodeData.neighborhood.trim() : "";
        }

      function setZipCodeFeedback(block, message, variant)
        {
          const feedback = block.querySelector(".js-address-zip-feedback");

          if (!feedback)
            {
              return;
            }

          feedback.className = "form-text js-address-zip-feedback";

          if (variant === "danger")
            {
              feedback.classList.add("text-danger");
            }
          else if (variant === "success")
            {
              feedback.classList.add("text-success");
            }
          else
            {
              feedback.classList.add("text-secondary");
            }

          feedback.textContent = message || "";
        }

      function setAutoFilledValue(field, value, dataKey)
        {
          const normalizedValue = String(value || "").trim();

          if (!field || normalizedValue === "")
            {
              return false;
            }

          const currentValue = field.value.trim();
          const previousAutoFilledValue = field.dataset[dataKey] || "";

          if (currentValue !== "" && currentValue !== previousAutoFilledValue)
            {
              return false;
            }

          field.value = normalizedValue;
          field.dataset[dataKey] = normalizedValue;
          clearFieldValidation(field);

          return true;
        }

      function clearAddressNeighborhoodCreatePanel(block)
        {
          const createPanel = block.querySelector(".addr-create-panel");
          const newNeighborhoodNameField = block.querySelector(".addr-new-neighborhood-name");
          const newCityIdField = block.querySelector(".addr-new-city-id");
          const newCityInput = block.querySelector(".addr-new-city-input");

          if (createPanel)
            {
              createPanel.classList.add("d-none");
            }

          if (newNeighborhoodNameField)
            {
              newNeighborhoodNameField.value = "";
            }

          if (newCityIdField)
            {
              newCityIdField.value = "";
            }

          if (newCityInput)
            {
              newCityInput.value = "";
            }
        }

      function clearAddressNeighborhoodResults(block)
        {
          const resultsList = block.querySelector(".addr-neighborhood-results");

          if (!resultsList)
            {
              return;
            }

          resultsList.innerHTML = "";
          resultsList.style.display = "none";
          resultsList.dataset.autocompletePending = "0";
        }

      function fillAddressNeighborhoodCreatePanel(block, zipCodeData, cityItem)
        {
          const createPanel = block.querySelector(".addr-create-panel");
          const newNeighborhoodNameField = block.querySelector(".addr-new-neighborhood-name");
          const newCityIdField = block.querySelector(".addr-new-city-id");
          const newCityInput = block.querySelector(".addr-new-city-input");
          const neighborhoodName = (typeof zipCodeData.neighborhood === "string") ? zipCodeData.neighborhood.trim() : "";

          if (!createPanel || neighborhoodName === "")
            {
              return;
            }

          createPanel.classList.remove("d-none");

          if (newNeighborhoodNameField)
            {
              newNeighborhoodNameField.value = neighborhoodName;
            }

          if (newCityIdField)
            {
              newCityIdField.value = cityItem && cityItem.id ? String(cityItem.id) : "";
            }

          if (newCityInput)
            {
              newCityInput.value = cityItem && cityItem.label ? cityItem.label : buildCityStateLabel(zipCodeData);
            }
        }

      async function fetchAutocompleteItems(endpoint, term, signal)
        {
          if (!endpoint || !term)
            {
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

          if (!response.ok)
            {
              return [];
            }

          const data = await safeJson(response);
          return (data && Array.isArray(data.items)) ? data.items : [];
        }

      async function findCityByZipCodeData(zipCodeData, signal)
        {
          const cityName = (typeof zipCodeData.city === "string") ? zipCodeData.city.trim() : "";
          const stateAcronym = (typeof zipCodeData.state === "string") ? zipCodeData.state.trim() : "";
          const endpoint = (typeof PERSON_CITY_SEARCH_URL === "string") ? PERSON_CITY_SEARCH_URL : "";

          if (cityName === "")
            {
              return null;
            }

          const targetCity = normalizeLookupText(cityName);
          const targetState = normalizeLookupText(stateAcronym);
          const items = await fetchAutocompleteItems(endpoint, cityName, signal);
          const cityMatches = items.filter(function (item)
            {
              return normalizeLookupText(item.name || item.label) === targetCity;
            });

          if (!cityMatches.length)
            {
              return null;
            }

          if (targetState !== "")
            {
              const cityStateMatch = cityMatches.find(function (item)
                {
                  return normalizeLookupText(item.state_acronym) === targetState;
                });

              if (cityStateMatch)
                {
                  return cityStateMatch;
                }
            }

          return cityMatches.length === 1 ? cityMatches[0] : null;
        }

      function autocompleteResultItems(listEl)
        {
          if (!listEl)
            {
              return [];
            }

          return Array.from(listEl.querySelectorAll("[data-autocomplete-result='item']"));
        }

      function autocompleteItemMatchesZipCode(listItem, zipCodeData)
        {
          if (!listItem || !listItem._autocompleteItem)
            {
              return false;
            }

          const item = listItem._autocompleteItem;
          const targetNeighborhood = normalizeLookupText(getZipCodeNeighborhoodName(zipCodeData));
          const targetCity = normalizeLookupText(zipCodeData.city);
          const itemNeighborhood = normalizeLookupText(item.name || item.label);
          const itemCity = normalizeLookupText(item.city_name);

          if (targetNeighborhood !== "" && itemNeighborhood !== targetNeighborhood)
            {
              return false;
            }

          return targetCity === "" || itemCity === "" || itemCity === targetCity;
        }

      async function waitForAutocompleteResults(listEl, term, signal)
        {
          const startedAt = Date.now();

          while (Date.now() - startedAt < AUTOCOMPLETE_RESULT_WAIT_MS)
            {
              if (signal && signal.aborted)
                {
                  throw abortError();
                }

              if (
                listEl
                && listEl.dataset.autocompleteTerm === term
                && listEl.dataset.autocompletePending !== "1"
              )
                {
                  return autocompleteResultItems(listEl);
                }

              await wait(40, signal);
            }

          return autocompleteResultItems(listEl);
        }

      function selectAutocompleteResult(listItem)
        {
          listItem.dispatchEvent(new MouseEvent("mousedown", {
            bubbles: true,
            cancelable: true,
            view: window
          }));
        }

      async function typeAndSelectNeighborhoodFromAutocomplete(block, zipCodeData, signal)
        {
          const neighborhoodInput = block.querySelector(".addr-neighborhood-input");
          const resultsList = block.querySelector(".addr-neighborhood-results");
          const neighborhoodName = getZipCodeNeighborhoodName(zipCodeData);

          if (!neighborhoodInput || !resultsList || neighborhoodName === "")
            {
              return false;
            }

          neighborhoodInput.value = "";
          neighborhoodInput.dataset.zipCodeAutoFilledNeighborhood = "";

          for (let index = 0; index < neighborhoodName.length; index += 1)
            {
              if (signal && signal.aborted)
                {
                  throw abortError();
                }

              const typedTerm = neighborhoodName.slice(0, index + 1);
              neighborhoodInput.value = typedTerm;
              neighborhoodInput.dispatchEvent(new Event("input", {bubbles: true}));

              await wait(ZIP_CODE_NEIGHBORHOOD_TYPE_DELAY_MS, signal);

              const resultItems = await waitForAutocompleteResults(resultsList, typedTerm.trim(), signal);

              if (resultItems.length === 1 && autocompleteItemMatchesZipCode(resultItems[0], zipCodeData))
                {
                  selectAutocompleteResult(resultItems[0]);
                  neighborhoodInput.dataset.zipCodeAutoFilledNeighborhood = neighborhoodInput.value;
                  return true;
                }
            }

          return false;
        }

      async function applyZipCodeDataToAddressBlock(block, zipCodeData, signal)
        {
          const streetField = block.querySelector(".js-address-street");
          const neighborhoodIdField = block.querySelector(".addr-neighborhood-id");
          const neighborhoodInput = block.querySelector(".addr-neighborhood-input");
          const neighborhoodLabel = buildNeighborhoodLabel(zipCodeData);
          const result = {
            changed: false,
            neighborhoodPending: false
          };

          if (setAutoFilledValue(streetField, zipCodeData.street || "", "zipCodeAutoFilledStreet"))
            {
              result.changed = true;
            }

          if (!neighborhoodInput || neighborhoodLabel === "")
            {
              return result;
            }

          const currentNeighborhood = neighborhoodInput.value.trim();
          const previousAutoFilledNeighborhood = neighborhoodInput.dataset.zipCodeAutoFilledNeighborhood || "";

          if (currentNeighborhood !== "" && currentNeighborhood !== previousAutoFilledNeighborhood)
            {
              return result;
            }

          if (await typeAndSelectNeighborhoodFromAutocomplete(block, zipCodeData, signal))
            {
              neighborhoodInput.dataset.zipCodeAutoFilledNeighborhood = neighborhoodInput.value;
              clearFieldValidation(neighborhoodInput);
              result.changed = true;
              return result;
            }

          if (neighborhoodIdField)
            {
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

      async function lookupAddressZipCode(block)
        {
          const zipInput = block.querySelector(".js-address-zip");
          const endpoint = getPersonZipCodeEndpoint();
          const zipCode = zipInput ? digitsOnly(zipInput.value) : "";

          if (!zipInput || endpoint === "" || zipCode.length !== ZIP_CODE_LENGTH)
            {
              setZipCodeFeedback(block, "", "muted");
              return;
            }

          if (zipInput.dataset.zipCodeLastLookup === zipCode)
            {
              return;
            }

          const previousController = zipInput._zipCodeAbortController || null;
          if (previousController)
            {
              previousController.abort();
            }

          const controller = new AbortController();
          zipInput._zipCodeAbortController = controller;
          zipInput.dataset.zipCodeLastLookup = "";
          setZipCodeFeedback(block, "Buscando CEP...", "muted");

          try
            {
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

              if (!response.ok)
                {
                  setZipCodeFeedback(block, (data && data.message) ? data.message : "Não foi possível consultar o CEP agora.", "danger");
                  return;
                }

              if (!data || data.success !== true || data.found !== true)
                {
                  setZipCodeFeedback(block, (data && data.message) ? data.message : "CEP não encontrado.", "danger");
                  return;
                }

              zipInput.dataset.zipCodeLastLookup = zipCode;

              if (typeof data.formatted_zip_code === "string" && data.formatted_zip_code !== "")
                {
                  zipInput.value = data.formatted_zip_code;
                }

              const applyResult = await applyZipCodeDataToAddressBlock(block, data, controller.signal);
              const locationBits = [];

              if (typeof data.city === "string" && data.city.trim() !== "")
                {
                  locationBits.push(data.city.trim());
                }

              if (typeof data.state === "string" && data.state.trim() !== "")
                {
                  locationBits.push(data.state.trim());
                }

              if (applyResult.neighborhoodPending)
                {
                  setZipCodeFeedback(block, "CEP encontrado. Confirme o cadastro do bairro abaixo.", "success");
                }
              else
                {
                  setZipCodeFeedback(
                    block,
                    locationBits.length ? "CEP encontrado em " + locationBits.join("/") + "." : "CEP encontrado.",
                    "success"
                  );
                }

              if (applyResult.changed)
                {
                  schedulePersistFormDraft();
                }
            }
          catch (error)
            {
              if (error && error.name === "AbortError")
                {
                  return;
                }

              setZipCodeFeedback(block, "Não foi possível consultar o CEP agora.", "danger");
            }
          finally
            {
              if (zipInput._zipCodeAbortController === controller)
                {
                  zipInput._zipCodeAbortController = null;
                }
            }
        }

      function bindAddressZipCodeLookup(block)
        {
          const zipInput = block.querySelector(".js-address-zip");
          let lookupTimer = null;

          if (!zipInput)
            {
              return;
            }

          zipInput.addEventListener("input", function ()
            {
              const zipCode = digitsOnly(zipInput.value);

              if (lookupTimer)
                {
                  window.clearTimeout(lookupTimer);
                  lookupTimer = null;
                }

              if (zipCode.length < ZIP_CODE_LENGTH)
                {
                  if (zipInput._zipCodeAbortController)
                    {
                      zipInput._zipCodeAbortController.abort();
                      zipInput._zipCodeAbortController = null;
                    }

                  zipInput.dataset.zipCodeLastLookup = "";
                  setZipCodeFeedback(block, "", "muted");
                  return;
                }

              lookupTimer = window.setTimeout(function ()
                {
                  lookupTimer = null;
                  lookupAddressZipCode(block);
                }, 350);
            });

          zipInput.addEventListener("blur", function ()
            {
              if (lookupTimer)
                {
                  window.clearTimeout(lookupTimer);
                  lookupTimer = null;
                }

              zipInput.value = applyCepMask(zipInput.value);

              if (digitsOnly(zipInput.value).length === ZIP_CODE_LENGTH)
                {
                  lookupAddressZipCode(block);
                }
            });
        }

      function normalizeUsernameText(value)
        {
          return String(value || "")
            .normalize("NFD")
            .replace(/[\u0300-\u036f]/g, "")
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, " ")
            .trim();
        }

      function buildUsernameSuggestion(name)
        {
          const normalized = normalizeUsernameText(name);

          if (!normalized)
            {
              return "";
            }

          const parts = normalized.split(/\s+/).filter(Boolean);

          if (!parts.length)
            {
              return "";
            }

          const suggestion = (parts.length === 1)
            ? parts[0]
            : (parts[0] + "." + parts[parts.length - 1]);

          return suggestion.slice(0, 45);
        }

      function syncUsernameSuggestionState()
        {
          const suggestion = buildUsernameSuggestion(personNameInput ? personNameInput.value : "");
          usernameWasSuggested = (suggestion !== "" && userUsernameInput && userUsernameInput.value.trim() === suggestion);
        }

      function applyUsernameSuggestion(force)
        {
          if (!userUsernameInput)
            {
              return;
            }

          const suggestion = buildUsernameSuggestion(personNameInput ? personNameInput.value : "");

          if (!suggestion)
            {
              return;
            }

          if (force || userUsernameInput.value.trim() === "" || usernameWasSuggested)
            {
              userUsernameInput.value = suggestion;
              usernameWasSuggested = true;
            }
        }

      function syncUserActiveSuggestionState()
        {
          if (!userActiveSelect || !personActiveSelect)
            {
              userActiveWasSuggested = false;
              return;
            }

          userActiveWasSuggested = (
            personActiveSelect.value !== ""
            && userActiveSelect.value !== ""
            && userActiveSelect.value === personActiveSelect.value
          );
        }

      function applyUserActiveSuggestion(force)
        {
          if (!userActiveSelect || !personActiveSelect || personActiveSelect.value === "")
            {
              return;
            }

          if (force || userActiveSelect.value === "" || userActiveWasSuggested)
            {
              userActiveSelect.value = personActiveSelect.value;
              userActiveWasSuggested = true;
            }
        }

      function isUserCreationEnabled()
        {
          return createUserSelect && createUserSelect.value === "1";
        }

      function updateUserSectionState()
        {
          const enabled = isUserCreationEnabled();

          if (userFieldsSection)
            {
              userFieldsSection.classList.toggle("d-none", !enabled);
            }

          [userUsernameInput, userPasswordInput, userTypeSelect, userActiveSelect].forEach(function (field)
            {
              if (!field)
                {
                  return;
                }

              clearFieldValidation(field);

              if (enabled)
                {
                  field.setAttribute("required", "required");
                }
              else
                {
                  field.removeAttribute("required");
                }
            });

          if (enabled)
            {
              applyUsernameSuggestion(false);
              applyUserActiveSuggestion(false);
            }
        }

      document.addEventListener("input", function (event)
        {
          if (event.target && event.target.classList.contains("mask-cep"))
            {
              const input = event.target;
              const previousValue = input.value;
              const previousPosition = input.selectionStart || previousValue.length;
              input.value = applyCepMask(input.value);
              const diff = input.value.length - previousValue.length;
              const nextPosition = Math.max(0, previousPosition + diff);
              input.setSelectionRange(nextPosition, nextPosition);
            }
        });

      function updateBlockTitles(container, selector, prefix)
        {
          Array.from(container.querySelectorAll(selector)).forEach(function (block, index)
            {
              const title = block.querySelector(".js-block-title");
              if (title)
                {
                  title.textContent = prefix + " " + (index + 1);
                }
            });
        }

      function resetBlock(block)
        {
          block.querySelectorAll("input").forEach(function (input)
            {
              input.value = "";
              input.classList.remove("is-invalid");
              delete input.dataset.zipCodeLastLookup;
              delete input.dataset.zipCodeAutoFilledStreet;
              delete input.dataset.zipCodeAutoFilledNeighborhood;
            });

          block.querySelectorAll("select").forEach(function (select)
            {
              select.selectedIndex = 0;
              select.classList.remove("is-invalid");
            });

          block.querySelectorAll("ul").forEach(function (list)
            {
              list.innerHTML = "";
              list.style.display = "none";
            });

          block.querySelectorAll(".addr-create-panel").forEach(function (panel)
            {
              panel.classList.add("d-none");
            });

          block.querySelectorAll(".js-address-zip-feedback").forEach(function (feedback)
            {
              feedback.textContent = "";
              feedback.className = "form-text js-address-zip-feedback";
            });
        }

      function removeBlock(button, container, selector, prefix)
        {
          const block = button.closest(selector);
          if (!block)
            {
              return;
            }

          const total = container.querySelectorAll(selector).length;
          if (total <= 1)
            {
              resetBlock(block);
            }
          else
            {
              block.remove();
            }

          updateBlockTitles(container, selector, prefix);
          schedulePersistFormDraft();
        }

      function initMiniAutocomplete(endpoint, inputEl, listEl, onSelect, extraOption)
        {
          let abortController = null;

          function clearList()
            {
              listEl.innerHTML = "";
              listEl.style.display = "none";
              listEl.dataset.autocompletePending = "0";
            }

          function renderList(items, term)
            {
              listEl.innerHTML = "";

              items.forEach(function (item)
                {
                  const listItem = document.createElement("li");
                  listItem.className = "list-group-item list-group-item-action py-1 px-2";
                  listItem.dataset.autocompleteResult = "item";
                  listItem._autocompleteItem = item;
                  listItem.textContent = item.label;
                  listItem.addEventListener("mousedown", function (event)
                    {
                      event.preventDefault();
                      onSelect(item);
                      clearList();
                      schedulePersistFormDraft();
                    });
                  listEl.appendChild(listItem);
                });

              if (extraOption)
                {
                  const extraItem = document.createElement("li");
                  extraItem.className = "list-group-item list-group-item-action py-1 px-2 text-primary fw-semibold";
                  extraItem.dataset.autocompleteResult = "extra";
                  extraItem.textContent = extraOption.label(term);
                  extraItem.addEventListener("mousedown", function (event)
                    {
                      event.preventDefault();
                      clearList();
                      extraOption.onClick(term);
                      schedulePersistFormDraft();
                    });
                  listEl.appendChild(extraItem);
                }

              listEl.style.display = listEl.children.length ? "block" : "none";
              listEl.dataset.autocompleteTerm = term;
              listEl.dataset.autocompletePending = "0";
            }

          inputEl.addEventListener("input", async function ()
            {
              const term = inputEl.value.trim();

              if (term.length < 1)
                {
                  clearList();
                  return;
                }

              listEl.dataset.autocompleteTerm = term;
              listEl.dataset.autocompletePending = "1";

              if (abortController)
                {
                  abortController.abort();
                }

              abortController = new AbortController();

              try
                {
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
              catch (error)
                {
                  if (error && error.name !== "AbortError")
                    {
                      listEl.dataset.autocompletePending = "0";
                      console.error("Erro no autocomplete:", error);
                    }
                }
            });

          document.addEventListener("click", function (event)
            {
              if (event.target !== inputEl && !listEl.contains(event.target))
                {
                  clearList();
                }
            });
        }

      function initAddressBlock(block)
        {
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

          function showCreatePanel(term)
            {
              createPanel.classList.remove("d-none");
              newNameInput.value = term;
              newCityId.value = "";
              newCityInput.value = "";
              hiddenId.value = "";
              textInput.value = "";
              textInput.classList.remove("is-invalid");
              schedulePersistFormDraft();
            }

          function hideCreatePanel()
            {
              createPanel.classList.add("d-none");
              newNameInput.value = "";
              newCityId.value = "";
              newCityInput.value = "";
              schedulePersistFormDraft();
            }

          textInput.addEventListener("input", function ()
            {
              hiddenId.value = "";
              textInput.classList.remove("is-invalid");
            });

          newCityInput.addEventListener("input", function ()
            {
              newCityId.value = "";
              newCityInput.classList.remove("is-invalid");
            });

          initMiniAutocomplete(
            PERSON_NEIGHBORHOOD_SEARCH_URL,
            textInput,
            resultsList,
            function onSelect(item)
              {
                hiddenId.value = item.id;
                textInput.value = item.label;
                textInput.classList.remove("is-invalid");
                hideCreatePanel();
              },
            {
              label: function (term)
                {
                  return "+ Criar bairro: \"" + term + "\"";
                },
              onClick: function (term)
                {
                  showCreatePanel(term);
                }
            }
          );

          initMiniAutocomplete(
            PERSON_CITY_SEARCH_URL,
            newCityInput,
            newCityList,
            function onSelect(item)
              {
                newCityId.value = item.id;
                newCityInput.value = item.label;
                newCityInput.classList.remove("is-invalid");
              }
          );

          btnSave.addEventListener("click", async function ()
            {
              const neighborhoodName = newNameInput.value.trim();
              const cityId = newCityId.value.trim();

              newNameInput.classList.remove("is-invalid");
              newCityInput.classList.remove("is-invalid");

              if (!neighborhoodName)
                {
                  newNameInput.classList.add("is-invalid");
                  newNameInput.focus();
                  return;
                }

              if (!cityId)
                {
                  newCityInput.classList.add("is-invalid");
                  newCityInput.focus();
                  return;
                }

              btnSave.disabled = true;
              btnSave.textContent = "Salvando...";

              try
                {
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

                  const data = await response.json().catch(function ()
                    {
                      return {};
                    });

                  if (!response.ok || data.error)
                    {
                      throw new Error(data.error || "Não foi possível cadastrar o bairro.");
                    }

                  hiddenId.value = data.id;
                  textInput.value = data.name;
                  textInput.classList.remove("is-invalid");
                  hideCreatePanel();
                }
              catch (error)
                {
                  alert(error.message || "Erro de comunicação ao salvar bairro.");
                }
              finally
                {
                  btnSave.disabled = false;
                  btnSave.textContent = "Salvar";
                }
            });

          btnCancel.addEventListener("click", function ()
            {
              hideCreatePanel();
            });
        }

      function populateAddressBlock(block, data)
        {
          if (!data)
            {
              return;
            }

          const zipField = block.querySelector(".js-address-zip");
          const streetField = block.querySelector(".js-address-street");
          const numberField = block.querySelector(".js-address-number");
          const observationsField = block.querySelector(".js-address-observations");
          const neighborhoodIdField = block.querySelector(".addr-neighborhood-id");
          const neighborhoodInput = block.querySelector(".addr-neighborhood-input");
          const createPanel = block.querySelector(".addr-create-panel");
          const newNeighborhoodNameField = block.querySelector(".addr-new-neighborhood-name");
          const newCityIdField = block.querySelector(".addr-new-city-id");
          const newCityInput = block.querySelector(".addr-new-city-input");

          if (zipField)
            {
              zipField.value = applyCepMask(data.zip_code || "");
            }

          if (streetField)
            {
              streetField.value = data.street || "";
            }

          if (numberField)
            {
              numberField.value = data.number || "";
            }

          if (observationsField)
            {
              observationsField.value = data.observations || "";
            }

          if (neighborhoodIdField)
            {
              neighborhoodIdField.value = data.neighborhood_id || "";
            }

          if (neighborhoodInput)
            {
              neighborhoodInput.value = data.neighborhood_label || "";
            }

          if (newNeighborhoodNameField)
            {
              newNeighborhoodNameField.value = data.new_neighborhood_name || "";
            }

          if (newCityIdField)
            {
              newCityIdField.value = data.new_city_id || "";
            }

          if (newCityInput)
            {
              newCityInput.value = data.new_city_label || "";
            }

          if (createPanel)
            {
              createPanel.classList.toggle("d-none", !data.create_panel_open);
            }
        }

      function populatePhoneBlock(block, data)
        {
          if (!data)
            {
              return;
            }

          const numberField = block.querySelector(".js-phone-number");
          const notesField = block.querySelector(".js-phone-notes");

          if (numberField)
            {
              numberField.value = data.number || "";
            }

          if (notesField)
            {
              notesField.value = data.notes || "";
            }
        }

      function populateEmailBlock(block, data)
        {
          if (!data)
            {
              return;
            }

          const addressField = block.querySelector(".js-email-address");
          const descriptionField = block.querySelector(".js-email-description");
          const loginField = block.querySelector(".js-email-login");

          if (addressField)
            {
              addressField.value = data.address || "";
            }

          if (descriptionField)
            {
              descriptionField.value = data.description || "";
            }

          if (loginField)
            {
              loginField.value = String(data.is_login || 0);
            }
        }

      function addAddressBlock(data)
        {
          const block = cloneTemplate(addressTemplate);
          applyIndexedNames(block, "__INDEX__", addressIndex);
          addressIndex += 1;
          addressesContainer.appendChild(block);
          initAddressBlock(block);
          populateAddressBlock(block, data);
          updateBlockTitles(addressesContainer, ".js-address-item", "Endereço");
          schedulePersistFormDraft();
        }

      function addPhoneBlock(data)
        {
          const block = cloneTemplate(phoneTemplate);
          applyIndexedNames(block, "__INDEX__", phoneIndex);
          phoneIndex += 1;
          phonesContainer.appendChild(block);
          populatePhoneBlock(block, data);
          updateBlockTitles(phonesContainer, ".js-phone-item", "Telefone");
          schedulePersistFormDraft();
        }

      function addEmailBlock(data)
        {
          const shouldUseLoginDefault = !data && emailIndex === 0;
          const block = cloneTemplate(emailTemplate);
          applyIndexedNames(block, "__INDEX__", emailIndex);
          emailIndex += 1;
          emailsContainer.appendChild(block);
          populateEmailBlock(block, data || (shouldUseLoginDefault ? {is_login: 1} : null));
          updateBlockTitles(emailsContainer, ".js-email-item", "E-mail");
          schedulePersistFormDraft();
        }

      function clearFormValidationState()
        {
          form.classList.remove("was-validated");
          hideFormAlert();
          resetValidationMessage();

          form.querySelectorAll("input, select, textarea").forEach(function (field)
            {
              clearFieldValidation(field);
            });
        }

      function resetRepeatableBlocks()
        {
          addressesContainer.innerHTML = "";
          phonesContainer.innerHTML = "";
          emailsContainer.innerHTML = "";

          addressIndex = 0;
          phoneIndex = 0;
          emailIndex = 0;

          addAddressBlock();
          addPhoneBlock();
          addEmailBlock();
        }

      function clearPersonCreateForm()
        {
          const previousDraftPersistenceState = draftPersistenceEnabled;
          draftPersistenceEnabled = false;
          clearFormDraft();
          clearFormValidationState();

          setFieldValue(personActiveSelect, "1");
          setFieldValue(createUserSelect, "1");
          setFieldValue(userActiveSelect, "1");
          setFieldValue(personNameInput, "");
          setFieldValue(personFantasyInput, "");
          setFieldValue(personTypeSelect, "");
          setRoleValues([]);
          resetEmployeeFields();
          setFieldValue(personDocumentInput, "");
          setFieldValue(personBirthdateInput, "");
          setFieldValue(userUsernameInput, "");
          setFieldValue(userPasswordInput, "");
          setFieldValue(userTypeSelect, "user");

          resetRepeatableBlocks();
          syncUsernameSuggestionState();
          syncUserActiveSuggestionState();
          updateUserSectionState();
          clearFormDraft();
          draftPersistenceEnabled = previousDraftPersistenceState;

          if (personNameInput)
            {
              personNameInput.focus();
            }
        }

      function clearAssociationValidation()
        {
          form.querySelectorAll(
            ".js-address-street, .addr-neighborhood-input, .js-phone-number, .js-email-address, .js-email-login, .js-user-required"
          ).forEach(function (field)
            {
              clearFieldValidation(field);
            });
        }

      function isAddressTouched(block)
        {
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
          ].some(function (value)
            {
              return value.trim() !== "";
            });
        }

      function isPhoneTouched(block)
        {
          const phoneNumber = block.querySelector(".js-phone-number");
          const notes = block.querySelector(".js-phone-notes");

          return [
            phoneNumber ? phoneNumber.value : "",
            notes ? notes.value : ""
          ].some(function (value)
            {
              return value.trim() !== "";
            });
        }

      function isEmailTouched(block)
        {
          const addressField = block.querySelector(".js-email-address");
          const descriptionField = block.querySelector(".js-email-description");
          const loginField = block.querySelector(".js-email-login");

          const address = addressField ? addressField.value : "";
          const description = descriptionField ? descriptionField.value : "";
          const isLogin = loginField ? loginField.value : "0";

          return address.trim() !== "" || description.trim() !== "" || isLogin === "1";
        }

      function validateUserFields()
        {
          let valid = true;

          if (!isUserCreationEnabled())
            {
              return true;
            }

          [userUsernameInput, userPasswordInput, userTypeSelect, userActiveSelect].forEach(function (field)
            {
              if (!field)
                {
                  return;
                }

              const value = ("value" in field) ? String(field.value || "").trim() : "";

              if (!value)
                {
                  field.classList.add("is-invalid");
                  valid = false;
                }
            });

          if (userUsernameInput && userUsernameInput.value.trim().length > 45)
            {
              userUsernameInput.classList.add("is-invalid");
              valid = false;
            }

          const hasLoginEmail = Array.from(emailsContainer.querySelectorAll(".js-email-item")).some(function (block)
            {
              const addressField = block.querySelector(".js-email-address");
              const loginField = block.querySelector(".js-email-login");
              const address = addressField ? addressField.value.trim() : "";
              const isLogin = loginField ? loginField.value === "1" : false;

              return isLogin && address !== "" && addressField.checkValidity();
            });

          if (!hasLoginEmail)
            {
              const firstLoginSelect = emailsContainer.querySelector(".js-email-login");
              if (firstLoginSelect)
                {
                  firstLoginSelect.classList.add("is-invalid");
                }

              validationMessage = "Para gerar o usuário, marque ao menos um e-mail válido como e-mail de login.";
              valid = false;
            }

          return valid;
        }

      function validateAssociationBlocks()
        {
          let valid = true;

          addressesContainer.querySelectorAll(".js-address-item").forEach(function (block)
            {
              if (!isAddressTouched(block))
                {
                  return;
                }

              const street = block.querySelector(".js-address-street");
              const neighborhoodId = block.querySelector(".addr-neighborhood-id");
              const neighborhoodInput = block.querySelector(".addr-neighborhood-input");

              if (!street.value.trim())
                {
                  street.classList.add("is-invalid");
                  valid = false;
                }

              if (!neighborhoodId.value.trim())
                {
                  neighborhoodInput.classList.add("is-invalid");
                  valid = false;
                }
            });

          phonesContainer.querySelectorAll(".js-phone-item").forEach(function (block)
            {
              if (!isPhoneTouched(block))
                {
                  return;
                }

              const phoneNumber = block.querySelector(".js-phone-number");

              if (!phoneNumber.value.trim())
                {
                  phoneNumber.classList.add("is-invalid");
                  valid = false;
                }
            });

          emailsContainer.querySelectorAll(".js-email-item").forEach(function (block)
            {
              if (!isEmailTouched(block))
                {
                  return;
                }

              const emailAddress = block.querySelector(".js-email-address");

              if (!emailAddress.value.trim() || !emailAddress.checkValidity())
                {
                  emailAddress.classList.add("is-invalid");
                  valid = false;
                }
            });

          return validateUserFields() && valid;
        }

      if (btnAddAddress)
        {
          btnAddAddress.addEventListener("click", function ()
            {
              addAddressBlock();
            });
        }

      if (btnAddPhone)
        {
          btnAddPhone.addEventListener("click", function ()
            {
              addPhoneBlock();
            });
        }

      if (btnAddEmail)
        {
          btnAddEmail.addEventListener("click", function ()
            {
              addEmailBlock();
            });
        }

      if (btnClearPersonForm)
        {
          btnClearPersonForm.addEventListener("click", function ()
            {
              clearPersonCreateForm();
            });
        }

      addressesContainer.addEventListener("click", function (event)
        {
          const button = event.target.closest("[data-remove-group='address']");
          if (button)
            {
              removeBlock(button, addressesContainer, ".js-address-item", "Endereço");
            }
        });

      phonesContainer.addEventListener("click", function (event)
        {
          const button = event.target.closest("[data-remove-group='phone']");
          if (button)
            {
              removeBlock(button, phonesContainer, ".js-phone-item", "Telefone");
            }
        });

      emailsContainer.addEventListener("click", function (event)
        {
          const button = event.target.closest("[data-remove-group='email']");
          if (button)
            {
              removeBlock(button, emailsContainer, ".js-email-item", "E-mail");
            }
        });

      if (personNameInput)
        {
          personNameInput.addEventListener("input", function ()
            {
              if (!userUsernameInput)
                {
                  return;
                }

              if (userUsernameInput.value.trim() === "" || usernameWasSuggested)
                {
                  applyUsernameSuggestion(true);
                }
              else
                {
                  syncUsernameSuggestionState();
                }
            });
        }

      if (userUsernameInput)
        {
          userUsernameInput.addEventListener("input", function ()
            {
              syncUsernameSuggestionState();
              clearFieldValidation(userUsernameInput);
            });
        }

      if (personActiveSelect)
        {
          personActiveSelect.addEventListener("change", function ()
            {
              if (userActiveSelect && (userActiveSelect.value === "" || userActiveWasSuggested))
                {
                  applyUserActiveSuggestion(true);
                }
              else
                {
                  syncUserActiveSuggestionState();
                }
            });
        }

      if (userActiveSelect)
        {
          userActiveSelect.addEventListener("change", function ()
            {
              syncUserActiveSuggestionState();
              clearFieldValidation(userActiveSelect);
            });
        }

      if (userTypeSelect)
        {
          userTypeSelect.addEventListener("change", function ()
            {
              clearFieldValidation(userTypeSelect);
            });
        }

      if (userPasswordInput)
        {
          userPasswordInput.addEventListener("input", function ()
            {
              clearFieldValidation(userPasswordInput);
            });
        }

      if (createUserSelect)
        {
          createUserSelect.addEventListener("change", function ()
            {
              updateUserSectionState();
              clearFieldValidation(createUserSelect);
            });
        }

      form.addEventListener("input", schedulePersistFormDraft);
      form.addEventListener("change", schedulePersistFormDraft);
      window.addEventListener("beforeunload", persistFormDraft);

      form.addEventListener("submit", function (event)
        {
          hideFormAlert();
          resetValidationMessage();
          clearAssociationValidation();

          const nativeValid = form.checkValidity();
          const associationsValid = validateAssociationBlocks();

          if (!nativeValid || !associationsValid)
            {
              event.preventDefault();
              event.stopPropagation();
              form.classList.add("was-validated");
              showFormAlert(validationMessage);

              const firstInvalidField = form.querySelector(".is-invalid, :invalid");
              if (firstInvalidField && typeof firstInvalidField.scrollIntoView === "function")
                {
                  firstInvalidField.scrollIntoView({behavior: "smooth", block: "center"});
                }

              return;
            }

          form.classList.add("was-validated");
          draftPersistenceEnabled = false;
          clearFormDraft();
        });

      const storedDraft = readFormDraft();
      restoreStaticDraft(storedDraft);

      const addressInitialData = getRepeatableInitialData(storedDraft, "addresses", initialAddresses);
      const phoneInitialData = getRepeatableInitialData(storedDraft, "phones", initialPhones);
      const emailInitialData = getRepeatableInitialData(storedDraft, "emails", initialEmails);

      if (addressInitialData.length)
        {
          addressInitialData.forEach(function (address)
            {
              addAddressBlock(address);
            });
        }
      else
        {
          addAddressBlock();
        }

      if (phoneInitialData.length)
        {
          phoneInitialData.forEach(function (phone)
            {
              addPhoneBlock(phone);
            });
        }
      else
        {
          addPhoneBlock();
        }

      if (emailInitialData.length)
        {
          emailInitialData.forEach(function (email)
            {
              addEmailBlock(email);
            });
        }
      else
        {
          addEmailBlock();
        }

      syncUsernameSuggestionState();
      syncUserActiveSuggestionState();
      updateUserSectionState();

      if (initialUser && initialUser.create_user === "1")
        {
          applyUsernameSuggestion(false);
          applyUserActiveSuggestion(false);
        }

      draftPersistenceEnabled = true;
    })();
