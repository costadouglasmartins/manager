<?php
$formData = (isset($formData) && is_array($formData)) ? $formData : [];
$formError = trim((string) ($formError ?? ''));
$personForm = (is_array($formData['person'] ?? null) ? $formData['person'] : []);
$userForm = (is_array($formData['user'] ?? null) ? $formData['user'] : []);
$rawAddresses = (is_array($formData['addresses'] ?? null) ? array_values($formData['addresses']) : []);
$rawPhones = (is_array($formData['phones'] ?? null) ? array_values($formData['phones']) : []);
$rawEmails = (is_array($formData['emails'] ?? null) ? array_values($formData['emails']) : []);

$initialAddresses = array_map(function ($address)
  {
  return [
    'zip_code' => $address['zip_code'] ?? '',
    'street' => $address['street'] ?? '',
    'number' => $address['number'] ?? '',
    'observations' => $address['observations'] ?? '',
    'neighborhood_id' => (int) ($address['neighborhood_id'] ?? 0),
    'neighborhood_label' => $address['neighborhood_label'] ?? '',
  ];
  }, $rawAddresses);

$initialPhones = array_map(function ($phone)
  {
  return [
    'number' => $phone['number'] ?? '',
    'notes' => $phone['notes'] ?? '',
  ];
  }, $rawPhones);

$initialEmails = array_map(function ($email)
  {
  return [
    'address' => $email['address'] ?? '',
    'description' => $email['description'] ?? '',
    'is_login' => (int) ($email['is_login'] ?? 0),
  ];
  }, $rawEmails);

$initialUser = [
  'create_user' => '1',
  'username' => $userForm['username'] ?? '',
  'type' => $userForm['type'] ?? 'user',
  'active' => '1',
];

$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;
?>
<link rel="stylesheet" href="<?= url('assets/css/people/create.css?v=1'); ?>">

<div class="person-create-page app-page">
  <div class="person-create-header app-page-header">
    <div>
      <span class="person-create-kicker app-page-kicker"><i class="bi bi-person-fill-add"></i> Pessoas</span>
      <h5>Cadastrar pessoa</h5>
    </div>
  </div>

  <form autocomplete="off" action="<?= url('person/store'); ?>" method="POST" id="personCreateForm" class="needs-validation person-create-form" novalidate>
    <input autocomplete="off" type="hidden" id="personActive" name="person[active]" value="1">
    <input autocomplete="off" type="hidden" id="createSystemUser" name="create_user" value="1">
    <input autocomplete="off" type="hidden" class="js-user-required" id="userActive" name="user[active]" value="1">

    <div class="alert alert-danger <?= ($formError !== '' ? '' : 'd-none'); ?>" id="formAlert" role="alert"><?= htmlspecialchars($formError, ENT_QUOTES, 'UTF-8'); ?></div>

    <section class="person-create-section">
      <div class="person-create-section-header">
        <div class="person-create-section-title">
          <i class="bi bi-person-vcard"></i>
          <h2>Dados da pessoa</h2>
        </div>
      </div>

      <div class="row g-3">
        <div class="col-12 col-xl-6">
          <label class="form-label" for="personName">Nome *</label>
          <input autocomplete="off" class="form-control" id="personName" name="person[name]" placeholder="Nome completo ou razão social" type="text" required value="<?= htmlspecialchars($personForm['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
          <div class="invalid-feedback">Informe o nome.</div>
        </div>

        <div class="col-12 col-xl-6">
          <label class="form-label" for="personFantasy">Nome fantasia</label>
          <input autocomplete="off" class="form-control" id="personFantasy" name="person[fantasy]" placeholder="Nome fantasia" type="text" value="<?= htmlspecialchars($personForm['fantasy'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
        </div>

        <div class="col-12 col-md-4">
          <label class="form-label" for="personType">Tipo *</label>
          <select autocomplete="off" class="form-select" id="personType" name="person[type]" required>
            <option value="" disabled <?= empty($personForm['type']) ? 'selected' : ''; ?>>Selecione...</option>
            <option value="individual" <?= (($personForm['type'] ?? '') === 'individual' ? 'selected' : ''); ?>>Pessoa física</option>
            <option value="company" <?= (($personForm['type'] ?? '') === 'company' ? 'selected' : ''); ?>>Pessoa jurídica</option>
          </select>
          <div class="invalid-feedback">Selecione o tipo.</div>
        </div>

        <div class="col-12 col-md-4">
          <label class="form-label" for="personDocument">Documento *</label>
          <input autocomplete="off" class="form-control mask-cpf-cnpj" id="personDocument" name="person[document]" maxlength="18" placeholder="CPF ou CNPJ" type="text" required value="<?= htmlspecialchars($personForm['document'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
          <div class="invalid-feedback">Informe o documento.</div>
        </div>

        <div class="col-12 col-md-4">
          <label class="form-label" for="personBirthdate">Data de nascimento</label>
          <input autocomplete="off" class="form-control mask-date" id="personBirthdate" name="person[birthdate]" placeholder="DD/MM/AAAA" type="text" value="<?= htmlspecialchars($personForm['birthdate'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
          <div class="invalid-feedback">Informe uma data válida.</div>
        </div>
      </div>
    </section>

    <section class="person-create-section" id="userFieldsSection">
      <div class="person-create-section-header">
        <div class="person-create-section-title">
          <i class="bi bi-person-badge"></i>
          <h2>Usuário do sistema</h2>
        </div>
      </div>

      <div class="row g-3">
        <div class="col-12 col-lg-4">
          <label class="form-label" for="userUsername">Usuário *</label>
          <input autocomplete="off" class="form-control js-user-required" id="userUsername" maxlength="45" name="user[username]" placeholder="nome.sobrenome" type="text" required value="<?= htmlspecialchars($initialUser['username'], ENT_QUOTES, 'UTF-8'); ?>" />
          <div class="invalid-feedback">Informe o nome de usuário.</div>
        </div>

        <div class="col-12 col-lg-4">
          <label class="form-label" for="userPassword">Senha *</label>
          <input autocomplete="off" class="form-control js-user-required" id="userPassword" name="user[password]" placeholder="Defina uma senha" type="password" required />
          <div class="invalid-feedback">Informe a senha do usuário.</div>
        </div>

        <div class="col-12 col-lg-4">
          <label class="form-label" for="userType">Tipo *</label>
          <select autocomplete="off" class="form-select js-user-required" id="userType" name="user[type]" required>
            <option value="" disabled <?= empty($initialUser['type']) ? 'selected' : ''; ?>>Selecione...</option>
            <option value="admin" <?= ($initialUser['type'] === 'admin' ? 'selected' : ''); ?>>Administrador</option>
            <option value="user" <?= ($initialUser['type'] === 'user' ? 'selected' : ''); ?>>Usuário</option>
          </select>
          <div class="invalid-feedback">Selecione o tipo do usuário.</div>
        </div>
      </div>
    </section>

    <section class="person-create-section">
      <div class="person-create-section-header">
        <div class="person-create-section-title">
          <i class="bi bi-signpost-split"></i>
          <h2>Endereços</h2>
        </div>
        <button type="button" class="btn btn-outline-primary btn-sm" id="btnAddAddress"><i class="bi bi-plus-lg"></i> Adicionar</button>
      </div>

      <div id="addressesContainer" class="person-create-block-list"></div>

      <template id="addressTemplate">
        <div class="person-create-block js-address-item">
          <div class="person-create-block-header">
            <div class="fw-semibold js-block-title">Endereço</div>
            <button type="button" class="btn btn-outline-danger btn-sm" data-remove-group="address"><i class="bi bi-trash"></i> Remover</button>
          </div>

          <div class="row g-3">
            <div class="col-12 col-lg-2">
              <label class="form-label">CEP</label>
              <input autocomplete="off" class="form-control mask-cep js-address-zip" name="addresses[__INDEX__][zip_code]" type="text" maxlength="9" placeholder="00000-000" />
              <div class="form-text js-address-zip-feedback" aria-live="polite"></div>
            </div>

            <div class="col-12 col-lg-5">
              <label class="form-label">Logradouro</label>
              <input autocomplete="off" class="form-control js-address-street" name="addresses[__INDEX__][street]" type="text" maxlength="255" placeholder="Rua, Avenida, etc." />
              <div class="invalid-feedback">Informe o logradouro.</div>
            </div>

            <div class="col-12 col-md-4 col-lg-2">
              <label class="form-label">Número</label>
              <input autocomplete="off" class="form-control js-address-number" name="addresses[__INDEX__][number]" type="text" maxlength="10" placeholder="Nº" />
            </div>

            <div class="col-12 col-md-8 col-lg-3">
              <label class="form-label">Observações</label>
              <input autocomplete="off" class="form-control js-address-observations" name="addresses[__INDEX__][observations]" type="text" maxlength="255" placeholder="Complemento, referência..." />
            </div>

            <div class="col-12 position-relative">
              <label class="form-label">Bairro</label>
              <input autocomplete="off" type="hidden" class="addr-neighborhood-id" name="addresses[__INDEX__][neighborhood_id]" value="" />
              <input type="text" class="form-control addr-neighborhood-input" name="addresses[__INDEX__][neighborhood_label]" autocomplete="off" placeholder="Digite o nome do bairro..." />
              <div class="invalid-feedback">Selecione ou cadastre um bairro.</div>
              <ul class="list-group addr-neighborhood-results person-create-results mt-1"></ul>
            </div>

            <div class="col-12 addr-create-panel d-none">
              <div class="person-create-inline-panel">
                <p class="fw-semibold small mb-2">Cadastrar novo bairro</p>
                <div class="row g-2 align-items-end">
                  <div class="col-12 col-md-5">
                    <label class="form-label small">Nome do bairro</label>
                    <input autocomplete="off" type="text" class="form-control form-control-sm addr-new-neighborhood-name" placeholder="Ex.: Centro" />
                  </div>

                  <div class="col-12 col-md-5 position-relative">
                    <label class="form-label small">Cidade</label>
                    <input autocomplete="off" type="hidden" class="addr-new-city-id" value="" />
                    <input type="text" class="form-control form-control-sm addr-new-city-input" autocomplete="off" placeholder="Digite o nome da cidade..." />
                    <ul class="list-group addr-new-city-results person-create-results person-create-results-compact mt-1"></ul>
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

    <section class="person-create-section">
      <div class="person-create-section-header">
        <div class="person-create-section-title">
          <i class="bi bi-telephone"></i>
          <h2>Telefones</h2>
        </div>
        <button type="button" class="btn btn-outline-primary btn-sm" id="btnAddPhone"><i class="bi bi-plus-lg"></i> Adicionar</button>
      </div>

      <div id="phonesContainer" class="person-create-block-list"></div>

      <template id="phoneTemplate">
        <div class="person-create-block js-phone-item">
          <div class="person-create-block-header">
            <div class="fw-semibold js-block-title">Telefone</div>
            <button type="button" class="btn btn-outline-danger btn-sm" data-remove-group="phone"><i class="bi bi-trash"></i> Remover</button>
          </div>

          <div class="row g-3">
            <div class="col-12 col-md-4">
              <label class="form-label">Número</label>
              <input autocomplete="off" class="form-control js-phone-number" name="phones[__INDEX__][number]" type="text" inputmode="numeric" placeholder="(XX) XXXXX-XXXX" />
              <div class="invalid-feedback">Informe o número do telefone.</div>
            </div>

            <div class="col-12 col-md-8">
              <label class="form-label">Observações</label>
              <input autocomplete="off" class="form-control js-phone-notes" name="phones[__INDEX__][notes]" type="text" placeholder="Ex.: celular, comercial, WhatsApp" />
            </div>
          </div>
        </div>
      </template>
    </section>

    <section class="person-create-section">
      <div class="person-create-section-header">
        <div class="person-create-section-title">
          <i class="bi bi-envelope-paper"></i>
          <h2>E-mails</h2>
        </div>
        <button type="button" class="btn btn-outline-primary btn-sm" id="btnAddEmail"><i class="bi bi-plus-lg"></i> Adicionar</button>
      </div>

      <div id="emailsContainer" class="person-create-block-list"></div>

      <template id="emailTemplate">
        <div class="person-create-block js-email-item">
          <div class="person-create-block-header">
            <div class="fw-semibold js-block-title">E-mail</div>
            <button type="button" class="btn btn-outline-danger btn-sm" data-remove-group="email"><i class="bi bi-trash"></i> Remover</button>
          </div>

          <div class="row g-3">
            <div class="col-12 col-lg-5">
              <label class="form-label">Endereço de e-mail</label>
              <input autocomplete="off" class="form-control js-email-address" name="emails[__INDEX__][address]" type="email" placeholder="exemplo@email.com" />
              <div class="invalid-feedback">Informe um e-mail válido.</div>
            </div>

            <div class="col-12 col-lg-4">
              <label class="form-label">Descrição</label>
              <input autocomplete="off" class="form-control js-email-description" name="emails[__INDEX__][description]" type="text" placeholder="Ex.: financeiro, pessoal, comercial" />
            </div>

            <div class="col-12 col-lg-3">
              <label class="form-label">E-mail de login?</label>
              <select autocomplete="off" class="form-select js-email-login" name="emails[__INDEX__][is_login]">
                <option value="0" selected>Não</option>
                <option value="1">Sim</option>
              </select>
              <div class="invalid-feedback">Marque ao menos um e-mail de login.</div>
            </div>
          </div>
        </div>
      </template>
    </section>

    <div class="form-actions">
      <button type="button" class="btn btn-outline-secondary" id="btnClearPersonForm"><i class="bi bi-eraser"></i> Limpar</button>
      <a href="<?= url('person'); ?>" class="btn btn-secondary">Cancelar</a>
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
  const initialUser = <?= json_encode($initialUser, $jsonFlags); ?>;
</script>
<script src="<?= url('assets/js/people/create.js?v=5'); ?>"></script>
