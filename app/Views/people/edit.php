<?php
$addresses = $addresses ?? [];
$phones = $phones ?? [];
$emails = $emails ?? [];

$initialAddresses = array_map(function ($address)
  {
  $zipDigits = onlyDigits((string) ($address['zip_code'] ?? ''));
  $zipCode = $zipDigits;

  if (strlen($zipDigits) === 8)
    {
    $zipCode = substr($zipDigits, 0, 5) . '-' . substr($zipDigits, 5);
    }

  $neighborhoodLabel = trim((string) ($address['neighborhood_name'] ?? ''));
  $cityName = trim((string) ($address['neighborhood_city_name'] ?? ''));

  if ($neighborhoodLabel !== '' && $cityName !== '')
    {
    $neighborhoodLabel .= ' - ' . $cityName;
    }

  return [
    'id' => (int) ($address['id'] ?? 0),
    'zip_code' => $zipCode,
    'street' => $address['street'] ?? '',
    'number' => $address['number'] ?? '',
    'observations' => $address['observations'] ?? '',
    'neighborhood_id' => (int) ($address['neighborhood_id'] ?? 0),
    'neighborhood_label' => $neighborhoodLabel,
  ];
  }, $addresses);

$initialPhones = array_map(function ($phone)
  {
  return [
    'id' => (int) ($phone['id'] ?? 0),
    'number' => maskPhoneBr($phone['phone_number'] ?? ''),
    'notes' => $phone['observations'] ?? '',
  ];
  }, $phones);

$initialEmails = array_map(function ($email)
  {
  return [
    'id' => (int) ($email['id'] ?? 0),
    'address' => $email['email'] ?? '',
    'description' => $email['observations'] ?? '',
    'is_login' => (int) ($email['is_login'] ?? 0),
  ];
  }, $emails);

$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h5 class="mb-0">Editar pessoa</h5>
    <div class="text-body-secondary small">Atualize os dados da pessoa e gerencie os endereços, telefones e e-mails cadastrados.</div>
  </div>
  <a href="<?= url('person'); ?>" class="btn btn-outline-secondary">Voltar</a>
</div>

<form action="<?= url('person/update/' . (int) ($person['id'] ?? 0)); ?>" method="POST" id="personEditForm" class="needs-validation" novalidate>
  <div class="card shadow-sm mb-3">
    <div class="card-body">
      <div class="alert alert-danger d-none mb-4" id="formAlert" role="alert"></div>

      <section class="mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
          <div>
            <h2 class="h6 mb-0">Dados da pessoa</h2>
            <div class="text-body-secondary small">Os campos marcados com * são obrigatórios.</div>
          </div>
        </div>

        <div class="row g-3">
          <div class="col-12 col-md-6">
            <label class="form-label" for="personName">Nome *</label>
            <input autocomplete="off" class="form-control" id="personName" name="person[name]" placeholder="Nome completo ou razão social" type="text" required value="<?= htmlspecialchars($person['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
            <div class="invalid-feedback">Informe o nome.</div>
          </div>

          <div class="col-12 col-md-6">
            <label class="form-label" for="personFantasy">Nome fantasia</label>
            <input autocomplete="off" class="form-control" id="personFantasy" name="person[fantasy]" placeholder="Nome fantasia" type="text" value="<?= htmlspecialchars($person['fantasy'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
          </div>

          <div class="col-12 col-md-4">
            <label class="form-label" for="personType">Tipo *</label>
            <select autocomplete="off" class="form-select" id="personType" name="person[type]" required>
              <option value="" disabled <?= empty($person['type']) ? 'selected' : ''; ?>>Selecione...</option>
              <option value="individual" <?= (($person['type'] ?? '') === 'individual' ? 'selected' : ''); ?>>Pessoa física</option>
              <option value="company" <?= (($person['type'] ?? '') === 'company' ? 'selected' : ''); ?>>Pessoa jurídica</option>
            </select>
            <div class="invalid-feedback">Selecione o tipo.</div>
          </div>

          <div class="col-12 col-md-4">
            <label class="form-label" for="personDocument">Documento *</label>
            <input autocomplete="off" class="form-control mask-cpf-cnpj" id="personDocument" name="person[document]" maxlength="18" placeholder="CPF ou CNPJ" type="text" required value="<?= htmlspecialchars(maskCpfCnpj($person['document'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
            <div class="invalid-feedback">Informe o documento.</div>
          </div>

          <div class="col-12 col-md-2">
            <label class="form-label" for="personActive">Ativo *</label>
            <select autocomplete="off" class="form-select" id="personActive" name="person[active]" required>
              <option value="" disabled <?= (!isset($person['active']) || $person['active'] === '') ? 'selected' : ''; ?>>Selecione...</option>
              <option value="1" <?= ((int) ($person['active'] ?? 0) === 1 ? 'selected' : ''); ?>>Sim</option>
              <option value="0" <?= (isset($person['active']) && (int) $person['active'] === 0 ? 'selected' : ''); ?>>Não</option>
            </select>
            <div class="invalid-feedback">Selecione a situação.</div>
          </div>

          <div class="col-12 col-md-2">
            <label class="form-label" for="personBirthdate">Data de nascimento</label>
            <input autocomplete="off" class="form-control mask-date" id="personBirthdate" name="person[birthdate]" placeholder="DD/MM/AAAA" type="text" value="<?= htmlspecialchars(maskDateBr($person['birthdate'] ?? null) ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
            <div class="invalid-feedback">Informe uma data válida.</div>
          </div>
        </div>
      </section>

      <section class="mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
          <div>
            <h2 class="h6 mb-0">Endereços</h2>
            <div class="text-body-secondary small">Você pode adicionar quantos endereços quiser. Se um bloco ficar vazio, ele será ignorado.</div>
          </div>
          <button type="button" class="btn btn-outline-primary btn-sm" id="btnAddAddress">Adicionar endereço</button>
        </div>

        <div id="addressesContainer" class="d-grid gap-3"></div>

        <template id="addressTemplate">
          <div class="border rounded p-3 js-address-item">
            <input type="hidden" class="js-address-id" name="addresses[__INDEX__][id]" value="" />

            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
              <div class="fw-semibold js-block-title">Endereço</div>
              <button type="button" class="btn btn-outline-danger btn-sm" data-remove-group="address">Remover</button>
            </div>

            <div class="row g-3">
              <div class="col-12 col-md-3">
                <label class="form-label">CEP</label>
                <input class="form-control mask-cep js-address-zip" name="addresses[__INDEX__][zip_code]" type="text" maxlength="9" placeholder="00000-000" />
              </div>

              <div class="col-12 col-md-5">
                <label class="form-label">Logradouro</label>
                <input class="form-control js-address-street" name="addresses[__INDEX__][street]" type="text" maxlength="255" placeholder="Rua, Avenida, etc." />
                <div class="invalid-feedback">Informe o logradouro.</div>
              </div>

              <div class="col-12 col-md-2">
                <label class="form-label">Número</label>
                <input class="form-control js-address-number" name="addresses[__INDEX__][number]" type="text" maxlength="10" placeholder="Nº" />
              </div>

              <div class="col-12 col-md-2">
                <label class="form-label">Observações</label>
                <input class="form-control js-address-observations" name="addresses[__INDEX__][observations]" type="text" maxlength="255" placeholder="Complemento, referência..." />
              </div>

              <div class="col-12 position-relative">
                <label class="form-label">Bairro</label>
                <input type="hidden" class="addr-neighborhood-id" name="addresses[__INDEX__][neighborhood_id]" value="" />
                <input type="text" class="form-control addr-neighborhood-input" autocomplete="off" placeholder="Digite o nome do bairro..." />
                <div class="invalid-feedback">Selecione ou cadastre um bairro.</div>
                <ul class="list-group addr-neighborhood-results mt-1" style="display:none; position:absolute; width:100%; z-index:1050; max-height:220px; overflow-y:auto;"></ul>
              </div>

              <div class="col-12 addr-create-panel d-none">
                <div class="border border-warning rounded p-3 bg-warning-subtle">
                  <p class="fw-semibold small mb-2">Cadastrar novo bairro</p>
                  <div class="row g-2 align-items-end">
                    <div class="col-12 col-md-5">
                      <label class="form-label small">Nome do bairro</label>
                      <input type="text" class="form-control form-control-sm addr-new-neighborhood-name" placeholder="Ex.: Centro" />
                    </div>

                    <div class="col-12 col-md-5 position-relative">
                      <label class="form-label small">Cidade</label>
                      <input type="hidden" class="addr-new-city-id" value="" />
                      <input type="text" class="form-control form-control-sm addr-new-city-input" autocomplete="off" placeholder="Digite o nome da cidade..." />
                      <ul class="list-group addr-new-city-results mt-1" style="display:none; position:absolute; width:100%; z-index:1060; max-height:180px; overflow-y:auto;"></ul>
                    </div>

                    <div class="col-12 col-md-2 d-flex gap-2 align-items-end flex-wrap">
                      <button type="button" class="btn btn-warning btn-sm addr-btn-save-neighborhood">Salvar</button>
                      <button type="button" class="btn btn-link btn-sm p-0 text-secondary addr-btn-cancel-neighborhood">Cancelar</button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </template>
      </section>

      <section class="mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
          <div>
            <h2 class="h6 mb-0">Telefones</h2>
            <div class="text-body-secondary small">Você pode adicionar quantos telefones quiser. Se um bloco ficar vazio, ele será ignorado.</div>
          </div>
          <button type="button" class="btn btn-outline-primary btn-sm" id="btnAddPhone">Adicionar telefone</button>
        </div>

        <div id="phonesContainer" class="d-grid gap-3"></div>

        <template id="phoneTemplate">
          <div class="border rounded p-3 js-phone-item">
            <input type="hidden" class="js-phone-id" name="phones[__INDEX__][id]" value="" />

            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
              <div class="fw-semibold js-block-title">Telefone</div>
              <button type="button" class="btn btn-outline-danger btn-sm" data-remove-group="phone">Remover</button>
            </div>

            <div class="row g-3">
              <div class="col-12 col-md-4">
                <label class="form-label">Número</label>
                <input class="form-control js-phone-number" name="phones[__INDEX__][number]" type="text" inputmode="numeric" placeholder="(XX) XXXXX-XXXX" />
                <div class="invalid-feedback">Informe o número do telefone.</div>
              </div>

              <div class="col-12 col-md-8">
                <label class="form-label">Observações</label>
                <input class="form-control js-phone-notes" name="phones[__INDEX__][notes]" type="text" placeholder="Ex.: celular, comercial, WhatsApp" />
              </div>
            </div>
          </div>
        </template>
      </section>

      <section>
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
          <div>
            <h2 class="h6 mb-0">E-mails</h2>
            <div class="text-body-secondary small">Você pode adicionar quantos e-mails quiser. Se um bloco ficar vazio, ele será ignorado.</div>
          </div>
          <button type="button" class="btn btn-outline-primary btn-sm" id="btnAddEmail">Adicionar e-mail</button>
        </div>

        <div id="emailsContainer" class="d-grid gap-3"></div>

        <template id="emailTemplate">
          <div class="border rounded p-3 js-email-item">
            <input type="hidden" class="js-email-id" name="emails[__INDEX__][id]" value="" />

            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
              <div class="fw-semibold js-block-title">E-mail</div>
              <button type="button" class="btn btn-outline-danger btn-sm" data-remove-group="email">Remover</button>
            </div>

            <div class="row g-3">
              <div class="col-12 col-md-5">
                <label class="form-label">Endereço de e-mail</label>
                <input class="form-control js-email-address" name="emails[__INDEX__][address]" type="email" placeholder="exemplo@email.com" />
                <div class="invalid-feedback">Informe um e-mail válido.</div>
              </div>

              <div class="col-12 col-md-4">
                <label class="form-label">Descrição</label>
                <input class="form-control js-email-description" name="emails[__INDEX__][description]" type="text" placeholder="Ex.: financeiro, pessoal, comercial" />
              </div>

              <div class="col-12 col-md-3">
                <label class="form-label">E-mail de login?</label>
                <select class="form-select js-email-login" name="emails[__INDEX__][is_login]">
                  <option value="0" selected>Não</option>
                  <option value="1">Sim</option>
                </select>
              </div>
            </div>
          </div>
        </template>
      </section>
    </div>

    <div class="card-footer bg-body d-flex justify-content-end gap-2">
      <a href="<?= url('person'); ?>" class="btn btn-secondary">Cancelar</a>
      <button type="submit" class="btn btn-primary">Salvar</button>
    </div>
  </div>
</form>

<script>
  const PERSON_NEIGHBORHOOD_SEARCH_URL = "<?= url('person/neighborhoodSearch'); ?>";
  const PERSON_NEIGHBORHOOD_CREATE_URL = "<?= url('person/neighborhoodCreate'); ?>";
  const PERSON_CITY_SEARCH_URL = "<?= url('person/citySearch'); ?>";
  const initialAddresses = <?= json_encode($initialAddresses, $jsonFlags); ?>;
  const initialPhones = <?= json_encode($initialPhones, $jsonFlags); ?>;
  const initialEmails = <?= json_encode($initialEmails, $jsonFlags); ?>;

  (function ()
    {
      const form = document.getElementById("personEditForm");

      if (!form)
        {
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
      let addressIndex = 0;
      let phoneIndex = 0;
      let emailIndex = 0;

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

      function applyCepMask(value)
        {
          const digits = String(value || "").replace(/\D/g, "").slice(0, 8);
          return digits.replace(/(\d{5})(\d{1,3})$/, "$1-$2");
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
        }

      function initMiniAutocomplete(endpoint, inputEl, listEl, onSelect, extraOption)
        {
          let abortController = null;

          function clearList()
            {
              listEl.innerHTML = "";
              listEl.style.display = "none";
            }

          function renderList(items, term)
            {
              listEl.innerHTML = "";

              items.forEach(function (item)
                {
                  const listItem = document.createElement("li");
                  listItem.className = "list-group-item list-group-item-action py-1 px-2";
                  listItem.textContent = item.label;
                  listItem.addEventListener("mousedown", function (event)
                    {
                      event.preventDefault();
                      onSelect(item);
                      clearList();
                    });
                  listEl.appendChild(listItem);
                });

              if (extraOption)
                {
                  const extraItem = document.createElement("li");
                  extraItem.className = "list-group-item list-group-item-action py-1 px-2 text-primary fw-semibold";
                  extraItem.textContent = extraOption.label(term);
                  extraItem.addEventListener("mousedown", function (event)
                    {
                      event.preventDefault();
                      clearList();
                      extraOption.onClick(term);
                    });
                  listEl.appendChild(extraItem);
                }

              listEl.style.display = listEl.children.length ? "block" : "none";
            }

          inputEl.addEventListener("input", async function ()
            {
              const term = inputEl.value.trim();

              if (term.length < 1)
                {
                  clearList();
                  return;
                }

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

          function showCreatePanel(term)
            {
              createPanel.classList.remove("d-none");
              newNameInput.value = term;
              newCityId.value = "";
              newCityInput.value = "";
              hiddenId.value = "";
              textInput.value = "";
              textInput.classList.remove("is-invalid");
            }

          function hideCreatePanel()
            {
              createPanel.classList.add("d-none");
              newNameInput.value = "";
              newCityId.value = "";
              newCityInput.value = "";
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
                      throw new Error(data.error || "Nao foi possivel cadastrar o bairro.");
                    }

                  hiddenId.value = data.id;
                  textInput.value = data.name;
                  textInput.classList.remove("is-invalid");
                  hideCreatePanel();
                }
              catch (error)
                {
                  alert(error.message || "Erro de comunicacao ao salvar bairro.");
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

          const idField = block.querySelector(".js-address-id");
          const zipField = block.querySelector(".js-address-zip");
          const streetField = block.querySelector(".js-address-street");
          const numberField = block.querySelector(".js-address-number");
          const observationsField = block.querySelector(".js-address-observations");
          const neighborhoodIdField = block.querySelector(".addr-neighborhood-id");
          const neighborhoodInput = block.querySelector(".addr-neighborhood-input");

          if (idField)
            {
              idField.value = data.id || "";
            }

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
        }

      function populatePhoneBlock(block, data)
        {
          if (!data)
            {
              return;
            }

          const idField = block.querySelector(".js-phone-id");
          const numberField = block.querySelector(".js-phone-number");
          const notesField = block.querySelector(".js-phone-notes");

          if (idField)
            {
              idField.value = data.id || "";
            }

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

          const idField = block.querySelector(".js-email-id");
          const addressField = block.querySelector(".js-email-address");
          const descriptionField = block.querySelector(".js-email-description");
          const loginField = block.querySelector(".js-email-login");

          if (idField)
            {
              idField.value = data.id || "";
            }

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
          updateBlockTitles(addressesContainer, ".js-address-item", "Endereco");
        }

      function addPhoneBlock(data)
        {
          const block = cloneTemplate(phoneTemplate);
          applyIndexedNames(block, "__INDEX__", phoneIndex);
          phoneIndex += 1;
          phonesContainer.appendChild(block);
          populatePhoneBlock(block, data);
          updateBlockTitles(phonesContainer, ".js-phone-item", "Telefone");
        }

      function addEmailBlock(data)
        {
          const block = cloneTemplate(emailTemplate);
          applyIndexedNames(block, "__INDEX__", emailIndex);
          emailIndex += 1;
          emailsContainer.appendChild(block);
          populateEmailBlock(block, data);
          updateBlockTitles(emailsContainer, ".js-email-item", "E-mail");
        }

      function clearAssociationValidation()
        {
          form.querySelectorAll(
            ".js-address-street, .addr-neighborhood-input, .js-phone-number, .js-email-address"
          ).forEach(function (field)
            {
              field.classList.remove("is-invalid");
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

          return valid;
        }

      btnAddAddress.addEventListener("click", function ()
        {
          addAddressBlock();
        });
      btnAddPhone.addEventListener("click", function ()
        {
          addPhoneBlock();
        });
      btnAddEmail.addEventListener("click", function ()
        {
          addEmailBlock();
        });

      addressesContainer.addEventListener("click", function (event)
        {
          const button = event.target.closest("[data-remove-group='address']");
          if (button)
            {
              removeBlock(button, addressesContainer, ".js-address-item", "Endereco");
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

      form.addEventListener("submit", function (event)
        {
          hideFormAlert();
          clearAssociationValidation();

          const nativeValid = form.checkValidity();
          const associationsValid = validateAssociationBlocks();

          if (!nativeValid || !associationsValid)
            {
              event.preventDefault();
              event.stopPropagation();
              form.classList.add("was-validated");
              showFormAlert("Revise os campos destacados antes de salvar.");

              const firstInvalidField = form.querySelector(".is-invalid, :invalid");
              if (firstInvalidField && typeof firstInvalidField.scrollIntoView === "function")
                {
                  firstInvalidField.scrollIntoView({behavior: "smooth", block: "center"});
                }

              return;
            }

          form.classList.add("was-validated");
        });

      if (Array.isArray(initialAddresses) && initialAddresses.length)
        {
          initialAddresses.forEach(function (address)
            {
              addAddressBlock(address);
            });
        }
      else
        {
          addAddressBlock();
        }

      if (Array.isArray(initialPhones) && initialPhones.length)
        {
          initialPhones.forEach(function (phone)
            {
              addPhoneBlock(phone);
            });
        }
      else
        {
          addPhoneBlock();
        }

      if (Array.isArray(initialEmails) && initialEmails.length)
        {
          initialEmails.forEach(function (email)
            {
              addEmailBlock(email);
            });
        }
      else
        {
          addEmailBlock();
        }
    })();
</script>
