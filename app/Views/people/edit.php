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
<link rel="stylesheet" href="<?= url('assets/css/people/edit.css?v=1'); ?>">

<div class="person-edit-page app-page">
  <div class="person-edit-header app-page-header">
    <div>
      <span class="person-edit-kicker app-page-kicker"><i class="bi bi-pencil-square"></i> Pessoas</span>
      <h5>Editar pessoa</h5>
    </div>
  </div>

  <form action="<?= url('person/update/' . (int) ($person['id'] ?? 0)); ?>" method="POST" id="personEditForm" class="needs-validation person-edit-form" novalidate>
    <div class="alert alert-danger d-none" id="formAlert" role="alert"></div>

    <section class="person-edit-section">
      <div class="person-edit-section-header">
        <div class="person-edit-section-title">
          <i class="bi bi-person-vcard"></i>
          <h2>Dados da pessoa</h2>
        </div>
      </div>

      <div class="row g-3">
        <div class="col-12 col-xl-6">
          <label class="form-label" for="personName">Nome *</label>
          <input autocomplete="off" class="form-control" id="personName" name="person[name]" placeholder="Nome completo ou razão social" type="text" required value="<?= htmlspecialchars($person['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
          <div class="invalid-feedback">Informe o nome.</div>
        </div>

        <div class="col-12 col-xl-6">
          <label class="form-label" for="personFantasy">Nome fantasia</label>
          <input autocomplete="off" class="form-control" id="personFantasy" name="person[fantasy]" placeholder="Nome fantasia" type="text" value="<?= htmlspecialchars($person['fantasy'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
        </div>

        <div class="col-12 col-md-3">
          <label class="form-label" for="personType">Tipo *</label>
          <select autocomplete="off" class="form-select" id="personType" name="person[type]" required>
            <option value="" disabled <?= empty($person['type']) ? 'selected' : ''; ?>>Selecione...</option>
            <option value="individual" <?= (($person['type'] ?? '') === 'individual' ? 'selected' : ''); ?>>Pessoa física</option>
            <option value="company" <?= (($person['type'] ?? '') === 'company' ? 'selected' : ''); ?>>Pessoa jurídica</option>
          </select>
          <div class="invalid-feedback">Selecione o tipo.</div>
        </div>

        <div class="col-12 col-md-3">
          <label class="form-label" for="personDocument">Documento *</label>
          <input autocomplete="off" class="form-control mask-cpf-cnpj" id="personDocument" name="person[document]" maxlength="18" placeholder="CPF ou CNPJ" type="text" required value="<?= htmlspecialchars(maskCpfCnpj($person['document'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
          <div class="invalid-feedback">Informe o documento.</div>
        </div>

        <div class="col-12 col-md-3">
          <label class="form-label" for="personBirthdate">Data de nascimento</label>
          <input autocomplete="off" class="form-control mask-date" id="personBirthdate" name="person[birthdate]" placeholder="DD/MM/AAAA" type="text" value="<?= htmlspecialchars(maskDateBr($person['birthdate'] ?? null) ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
          <div class="invalid-feedback">Informe uma data válida.</div>
        </div>

        <div class="col-12 col-md-3">
          <label class="form-label" for="personActive">Ativo *</label>
          <select autocomplete="off" class="form-select" id="personActive" name="person[active]" required>
            <option value="" disabled <?= (!isset($person['active']) || $person['active'] === '') ? 'selected' : ''; ?>>Selecione...</option>
            <option value="1" <?= ((int) ($person['active'] ?? 0) === 1 ? 'selected' : ''); ?>>Sim</option>
            <option value="0" <?= (isset($person['active']) && (int) $person['active'] === 0 ? 'selected' : ''); ?>>Não</option>
          </select>
          <div class="invalid-feedback">Selecione a situação.</div>
        </div>
      </div>
    </section>

    <section class="person-edit-section">
      <div class="person-edit-section-header">
        <div class="person-edit-section-title">
          <i class="bi bi-signpost-split"></i>
          <h2>Endereços</h2>
        </div>
        <button type="button" class="btn btn-outline-primary btn-sm" id="btnAddAddress"><i class="bi bi-plus-lg"></i> Adicionar</button>
      </div>

      <div id="addressesContainer" class="person-edit-block-list"></div>

      <template id="addressTemplate">
        <div class="person-edit-block js-address-item">
          <input type="hidden" class="js-address-id" name="addresses[__INDEX__][id]" value="" />

          <div class="person-edit-block-header">
            <div class="fw-semibold js-block-title">Endereço</div>
            <button type="button" class="btn btn-outline-danger btn-sm" data-remove-group="address"><i class="bi bi-trash"></i> Remover</button>
          </div>

          <div class="row g-3">
            <div class="col-12 col-lg-2">
              <label class="form-label">CEP</label>
              <input class="form-control mask-cep js-address-zip" name="addresses[__INDEX__][zip_code]" type="text" maxlength="9" placeholder="00000-000" />
              <div class="form-text js-address-zip-feedback" aria-live="polite"></div>
            </div>

            <div class="col-12 col-lg-5">
              <label class="form-label">Logradouro</label>
              <input class="form-control js-address-street" name="addresses[__INDEX__][street]" type="text" maxlength="255" placeholder="Rua, Avenida, etc." />
              <div class="invalid-feedback">Informe o logradouro.</div>
            </div>

            <div class="col-12 col-md-4 col-lg-2">
              <label class="form-label">Número</label>
              <input class="form-control js-address-number" name="addresses[__INDEX__][number]" type="text" maxlength="10" placeholder="Nº" />
            </div>

            <div class="col-12 col-md-8 col-lg-3">
              <label class="form-label">Observações</label>
              <input class="form-control js-address-observations" name="addresses[__INDEX__][observations]" type="text" maxlength="255" placeholder="Complemento, referência..." />
            </div>

            <div class="col-12 position-relative">
              <label class="form-label">Bairro</label>
              <input type="hidden" class="addr-neighborhood-id" name="addresses[__INDEX__][neighborhood_id]" value="" />
              <input type="text" class="form-control addr-neighborhood-input" autocomplete="off" placeholder="Digite o nome do bairro..." />
              <div class="invalid-feedback">Selecione ou cadastre um bairro.</div>
              <ul class="list-group addr-neighborhood-results person-edit-results mt-1"></ul>
            </div>

            <div class="col-12 addr-create-panel d-none">
              <div class="person-edit-inline-panel">
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
                    <ul class="list-group addr-new-city-results person-edit-results person-edit-results-compact mt-1"></ul>
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

    <section class="person-edit-section">
      <div class="person-edit-section-header">
        <div class="person-edit-section-title">
          <i class="bi bi-telephone"></i>
          <h2>Telefones</h2>
        </div>
        <button type="button" class="btn btn-outline-primary btn-sm" id="btnAddPhone"><i class="bi bi-plus-lg"></i> Adicionar</button>
      </div>

      <div id="phonesContainer" class="person-edit-block-list"></div>

      <template id="phoneTemplate">
        <div class="person-edit-block js-phone-item">
          <input type="hidden" class="js-phone-id" name="phones[__INDEX__][id]" value="" />

          <div class="person-edit-block-header">
            <div class="fw-semibold js-block-title">Telefone</div>
            <button type="button" class="btn btn-outline-danger btn-sm" data-remove-group="phone"><i class="bi bi-trash"></i> Remover</button>
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

    <section class="person-edit-section">
      <div class="person-edit-section-header">
        <div class="person-edit-section-title">
          <i class="bi bi-envelope-paper"></i>
          <h2>E-mails</h2>
        </div>
        <button type="button" class="btn btn-outline-primary btn-sm" id="btnAddEmail"><i class="bi bi-plus-lg"></i> Adicionar</button>
      </div>

      <div id="emailsContainer" class="person-edit-block-list"></div>

      <template id="emailTemplate">
        <div class="person-edit-block js-email-item">
          <input type="hidden" class="js-email-id" name="emails[__INDEX__][id]" value="" />

          <div class="person-edit-block-header">
            <div class="fw-semibold js-block-title">E-mail</div>
            <button type="button" class="btn btn-outline-danger btn-sm" data-remove-group="email"><i class="bi bi-trash"></i> Remover</button>
          </div>

          <div class="row g-3">
            <div class="col-12 col-lg-5">
              <label class="form-label">Endereço de e-mail</label>
              <input class="form-control js-email-address" name="emails[__INDEX__][address]" type="email" placeholder="exemplo@email.com" />
              <div class="invalid-feedback">Informe um e-mail válido.</div>
            </div>

            <div class="col-12 col-lg-4">
              <label class="form-label">Descrição</label>
              <input class="form-control js-email-description" name="emails[__INDEX__][description]" type="text" placeholder="Ex.: financeiro, pessoal, comercial" />
            </div>

            <div class="col-12 col-lg-3">
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

    <div class="form-actions">
      <button type="button" class="btn btn-outline-secondary" id="btnClearPersonForm"><i class="bi bi-eraser"></i> Limpar</button>
      <a href="<?= url('person'); ?>" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Cancelar</a>
      <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Salvar</button>
    </div>
  </form>
</div>

<script>
  const PERSON_NEIGHBORHOOD_SEARCH_URL = "<?= url('person/neighborhoodSearch'); ?>";
  const PERSON_NEIGHBORHOOD_CREATE_URL = "<?= url('person/neighborhoodCreate'); ?>";
  const PERSON_CITY_SEARCH_URL = "<?= url('person/citySearch'); ?>";
  const PERSON_ZIP_CODE_SEARCH_URL = "<?= url('person/zipCodeSearch'); ?>";
  const initialAddresses = <?= json_encode($initialAddresses, $jsonFlags); ?>;
  const initialPhones = <?= json_encode($initialPhones, $jsonFlags); ?>;
  const initialEmails = <?= json_encode($initialEmails, $jsonFlags); ?>;
</script>
<script src="<?= url('assets/js/people/edit.js?v=4'); ?>"></script>
