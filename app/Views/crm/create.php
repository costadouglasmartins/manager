<?php
$formData = (isset($formData) && is_array($formData)) ? $formData : [];
$personForm = is_array($formData['person'] ?? null) ? $formData['person'] : [];
$crmForm = is_array($formData['crm'] ?? null) ? $formData['crm'] : [];
$contactForm = is_array($formData['contact'] ?? null) ? $formData['contact'] : [];
$statusValue = (string) ($crmForm['crm_status_id'] ?? ($statuses[0]['id'] ?? ''));
$activeValue = (string) ($crmForm['active'] ?? '1');
?>

<div class="app-page">
  <div class="app-page-header">
    <div>
      <span class="app-page-kicker"><i class="bi bi-briefcase"></i> CRM</span>
      <h5>Novo registro CRM</h5>
    </div>
  </div>

  <?php if (!empty($formError)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars((string) $formError, ENT_QUOTES, 'UTF-8'); ?></div>
  <?php endif; ?>

  <form action="<?= url('crm/store'); ?>" method="POST" class="needs-validation" novalidate>
    <section class="border rounded p-3 mb-3">
      <h6>Pessoa</h6>
      <div class="row g-3">
        <div class="col-12 col-xl-6">
          <label class="form-label" for="personName">Nome *</label>
          <input autocomplete="off" class="form-control" id="personName" maxlength="255" name="person[name]" required type="text" value="<?= htmlspecialchars((string) ($personForm['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
          <div class="invalid-feedback">Informe o nome.</div>
        </div>

        <div class="col-12 col-xl-6">
          <label class="form-label" for="personFantasy">Nome fantasia</label>
          <input autocomplete="off" class="form-control" id="personFantasy" maxlength="255" name="person[fantasy]" type="text" value="<?= htmlspecialchars((string) ($personForm['fantasy'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="col-12 col-md-4">
          <label class="form-label" for="personType">Tipo *</label>
          <select class="form-select" id="personType" name="person[type]" required>
            <option value="" disabled <?= empty($personForm['type']) ? 'selected' : ''; ?>>Selecione...</option>
            <?php foreach ($personTypes as $value => $label): ?>
              <option value="<?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); ?>" <?= ((string) ($personForm['type'] ?? '') === (string) $value) ? 'selected' : ''; ?>><?= htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
          <div class="invalid-feedback">Selecione o tipo.</div>
        </div>

        <div class="col-12 col-md-4">
          <label class="form-label" for="personDocument">Documento *</label>
          <input autocomplete="off" class="form-control mask-cpf-cnpj" id="personDocument" maxlength="18" name="person[document]" required type="text" value="<?= htmlspecialchars((string) ($personForm['document'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
          <div class="form-text">Se já existir em pessoas, será apenas vinculado ao CRM deste tenant.</div>
          <div class="invalid-feedback">Informe o documento.</div>
        </div>

        <div class="col-12 col-md-4">
          <label class="form-label" for="personBirthdate">Data de nascimento</label>
          <input autocomplete="off" class="form-control" id="personBirthdate" name="person[birthdate]" type="date" value="<?= htmlspecialchars((string) ($personForm['birthdate'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>
      </div>
    </section>

    <section class="border rounded p-3 mb-3">
      <h6>Contato inicial</h6>
      <div class="row g-3">
        <div class="col-12 col-md-6">
          <label class="form-label" for="contactEmail">E-mail</label>
          <input autocomplete="off" class="form-control" id="contactEmail" maxlength="255" name="contact[email]" type="email" value="<?= htmlspecialchars((string) ($contactForm['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
          <div class="invalid-feedback">Informe um e-mail válido.</div>
        </div>

        <div class="col-12 col-md-6">
          <label class="form-label" for="contactPhone">Telefone</label>
          <input autocomplete="off" class="form-control mask-phone" id="contactPhone" name="contact[phone]" type="text" value="<?= htmlspecialchars((string) ($contactForm['phone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>
      </div>
    </section>

    <section class="border rounded p-3 mb-3">
      <h6>Dados do CRM</h6>
      <div class="row g-3">
        <div class="col-12 col-md-4">
          <label class="form-label" for="crmStatus">Situação *</label>
          <select class="form-select" id="crmStatus" name="crm[crm_status_id]" required>
            <option value="" disabled <?= $statusValue === '' ? 'selected' : ''; ?>>Cadastre um status CRM ativo...</option>
            <?php foreach ($statuses as $status): ?>
              <option value="<?= (int) $status['id']; ?>" <?= $statusValue === (string) $status['id'] ? 'selected' : ''; ?>><?= htmlspecialchars((string) ($status['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
          <div class="invalid-feedback">Selecione uma situação de CRM.</div>
        </div>

        <div class="col-12 col-md-4">
          <label class="form-label" for="ownerUserId">Responsável</label>
          <select class="form-select" id="ownerUserId" name="crm[owner_user_id]">
            <option value="">Sem responsável</option>
            <?php foreach ($owners as $owner): ?>
              <?php $ownerLabel = trim((string) ($owner['person_name'] ?? '')) ?: (string) ($owner['username'] ?? ''); ?>
              <option value="<?= (int) $owner['id']; ?>" <?= ((string) ($crmForm['owner_user_id'] ?? '') === (string) $owner['id']) ? 'selected' : ''; ?>><?= htmlspecialchars($ownerLabel, ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12 col-md-4">
          <label class="form-label" for="crmSource">Origem</label>
          <input autocomplete="off" class="form-control" id="crmSource" maxlength="120" name="crm[source]" placeholder="Indicação, site, evento..." type="text" value="<?= htmlspecialchars((string) ($crmForm['source'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="col-12 col-md-4">
          <label class="form-label" for="crmActive">Status do vínculo</label>
          <select class="form-select" id="crmActive" name="crm[active]">
            <option value="1" <?= $activeValue === '1' ? 'selected' : ''; ?>>Ativo</option>
            <option value="0" <?= $activeValue === '0' ? 'selected' : ''; ?>>Inativo</option>
          </select>
        </div>

        <div class="col-12">
          <label class="form-label" for="crmNotes">Observações</label>
          <textarea class="form-control" id="crmNotes" maxlength="2000" name="crm[notes]" rows="5"><?= htmlspecialchars((string) ($crmForm['notes'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>
      </div>
    </section>

    <div class="form-actions">
      <a href="<?= url('crm'); ?>" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Cancelar</a>
      <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> Salvar</button>
    </div>
  </form>
</div>
