<?php
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$selected = static function (array $data, string $field, string $value): string {
	return ((string) ($data[$field] ?? '') === $value) ? 'selected' : '';
};
$selectedNullable = static function (array $data, string $field, string $value): string {
	if ($value === '')
		{
		return (($data[$field] ?? null) === null || (string) ($data[$field] ?? '') === '') ? 'selected' : '';
		}

	return ((string) ($data[$field] ?? '') === $value) ? 'selected' : '';
};
$decimalValue = static function (array $data, string $field): string {
	if (($data[$field] ?? null) === null || (string) $data[$field] === '')
		{
		return '';
		}

	return number_format((float) $data[$field], 2, ',', '.');
};
?>
<div class="app-page">
  <div class="app-page-header">
    <div>
      <span class="app-page-kicker"><i class="bi bi-wallet2"></i> Contas Bancárias</span>
      <h5>Editar conta bancária</h5>
    </div>
  </div>

<form action="<?= url('bank_account/update/' . $bankAccount['id']); ?>" method="POST" class="needs-validation" novalidate>
  <div class="row g-3">

    <div class="col-12">
      <h6 class="mb-0">Dados da conta</h6>
    </div>

    <div class="col-12">
      <label class="form-label" for="name">Nome</label>
      <input autocomplete="off" class="form-control" id="name" maxlength="50" name="name" required type="text" value="<?= $escape($bankAccount['name'] ?? ''); ?>">
      <div class="invalid-feedback">Informe o nome.</div>
    </div>

    <div class="col-12">
      <label class="form-label" for="bank_id">Banco</label>
      <select class="form-select" id="bank_id" name="bank_id" required>
        <option value="" disabled>Selecione...</option>
        <?php foreach ($banks as $bank): ?>
          <?php
          $bankNumber = trim((string) ($bank['number'] ?? ''));
          $bankLabelParts = [$bankNumber, trim((string) ($bank['name'] ?? ''))];
          $bankCnab = trim((string) ($bank['cnab'] ?? ''));
          if ($bankNumber !== '000' && $bankCnab !== '')
            {
            $bankLabelParts[] = 'CNAB ' . $bankCnab;
            }
          else
            {
            $bankCnab = '000'; 
            }
          ?>
          <option value="<?= (int) $bank['id']; ?>" <?= ((int) $bankAccount['bank_id'] === (int) $bank['id']) ? 'selected' : ''; ?> data-bank-cnab="<?= htmlspecialchars($bankCnab, ENT_QUOTES, 'UTF-8'); ?>">
            <?= htmlspecialchars(implode(' - ', array_filter($bankLabelParts, static fn ($part): bool => $part !== '')), ENT_QUOTES, 'UTF-8'); ?>
          </option>
        <?php endforeach; ?>
      </select>
      <div class="invalid-feedback">Selecione o banco.</div>
    </div>

    <div class="col-12 col-lg-6">
      <label class="form-label" for="agency">Agência</label>
      <input autocomplete="off" class="form-control" id="agency" maxlength="6" name="agency" required type="text" value="<?= $escape($bankAccount['agency'] ?? ''); ?>">
      <div class="invalid-feedback">Informe a agência.</div>
    </div>

    <div class="col-12 col-lg-6">
      <label class="form-label" for="account">Conta</label>
      <input autocomplete="off" class="form-control" id="account" maxlength="50" name="account" required type="text" value="<?= $escape($bankAccount['account'] ?? ''); ?>">
      <div class="invalid-feedback">Informe a conta.</div>
    </div>

    <div class="col-12">
      <h6 class="mb-0 mt-2">Pix</h6>
    </div>

    <div class="col-12 col-lg-4">
      <label class="form-label" for="pix_key_type">Tipo da chave Pix</label>
      <select class="form-select" id="pix_key_type" name="pix_key_type">
        <option value="" <?= $selectedNullable($bankAccount, 'pix_key_type', ''); ?>>Não informado</option>
        <option value="CPF" <?= $selected($bankAccount, 'pix_key_type', 'CPF'); ?>>CPF</option>
        <option value="CNPJ" <?= $selected($bankAccount, 'pix_key_type', 'CNPJ'); ?>>CNPJ</option>
        <option value="EMAIL" <?= $selected($bankAccount, 'pix_key_type', 'EMAIL'); ?>>E-mail</option>
        <option value="PHONE" <?= $selected($bankAccount, 'pix_key_type', 'PHONE'); ?>>Telefone</option>
        <option value="RANDOM" <?= $selected($bankAccount, 'pix_key_type', 'RANDOM'); ?>>Aleatória</option>
      </select>
    </div>

    <div class="col-12 col-lg-8">
      <label class="form-label" for="pix_key">Chave Pix</label>
      <input autocomplete="off" class="form-control" id="pix_key" maxlength="255" name="pix_key" type="text" value="<?= $escape($bankAccount['pix_key'] ?? ''); ?>">
    </div>

    <div class="col-12">
      <h6 class="mb-0 mt-2">Boleto</h6>
    </div>

    <div class="col-12 col-lg-6">
      <label class="form-label" for="bank_slip_wallet">Carteira</label>
      <input autocomplete="off" class="form-control" id="bank_slip_wallet" maxlength="50" name="bank_slip_wallet" type="text" value="<?= $escape($bankAccount['bank_slip_wallet'] ?? ''); ?>">
    </div>

    <div class="col-12 col-lg-6">
      <label class="form-label" for="bank_slip_species_title">Espécie do título</label>
      <input autocomplete="off" class="form-control" id="bank_slip_species_title" maxlength="50" name="bank_slip_species_title" type="text" value="<?= $escape($bankAccount['bank_slip_species_title'] ?? ''); ?>">
    </div>

    <div class="col-12 col-lg-4">
      <label class="form-label" for="bank_slip_protest">Protestar</label>
      <select class="form-select" id="bank_slip_protest" name="bank_slip_protest">
        <option value="" <?= $selectedNullable($bankAccount, 'bank_slip_protest', ''); ?>>Não informado</option>
        <option value="1" <?= $selected($bankAccount, 'bank_slip_protest', '1'); ?>>Sim</option>
        <option value="0" <?= $selected($bankAccount, 'bank_slip_protest', '0'); ?>>Não</option>
      </select>
    </div>

    <div class="col-12 col-lg-4">
      <label class="form-label" for="bank_slip_protest_number_of_days">Dias para protesto</label>
      <input autocomplete="off" class="form-control" id="bank_slip_protest_number_of_days" max="127" min="0" name="bank_slip_protest_number_of_days" step="1" type="number" value="<?= $escape($bankAccount['bank_slip_protest_number_of_days'] ?? ''); ?>">
    </div>

    <div class="col-12 col-lg-4">
      <label class="form-label" for="bank_slip_interest_type">Tipo de juros</label>
      <select class="form-select" id="bank_slip_interest_type" name="bank_slip_interest_type">
        <option value="" <?= $selectedNullable($bankAccount, 'bank_slip_interest_type', ''); ?>>Não informado</option>
        <option value="DAILY" <?= $selected($bankAccount, 'bank_slip_interest_type', 'DAILY'); ?>>Diário</option>
        <option value="MONTHLY" <?= $selected($bankAccount, 'bank_slip_interest_type', 'MONTHLY'); ?>>Mensal</option>
      </select>
    </div>

    <div class="col-12 col-lg-4">
      <label class="form-label" for="bank_slip_interest_amount_type">Tipo do valor dos juros</label>
      <select class="form-select" id="bank_slip_interest_amount_type" name="bank_slip_interest_amount_type">
        <option value="" <?= $selectedNullable($bankAccount, 'bank_slip_interest_amount_type', ''); ?>>Não informado</option>
        <option value="PERCENTAGE" <?= $selected($bankAccount, 'bank_slip_interest_amount_type', 'PERCENTAGE'); ?>>Percentual</option>
        <option value="FIXED_AMOUNT" <?= $selected($bankAccount, 'bank_slip_interest_amount_type', 'FIXED_AMOUNT'); ?>>Valor fixo</option>
      </select>
    </div>

    <div class="col-12 col-lg-4">
      <label class="form-label" for="bank_slip_interest">Juros</label>
      <input autocomplete="off" class="form-control js-money" id="bank_slip_interest" inputmode="decimal" name="bank_slip_interest" placeholder="0,00" type="text" value="<?= $escape($decimalValue($bankAccount, 'bank_slip_interest')); ?>">
    </div>

    <div class="col-12 col-lg-4">
      <label class="form-label" for="bank_slip_late_fee_amount">Multa</label>
      <input autocomplete="off" class="form-control js-money" id="bank_slip_late_fee_amount" inputmode="decimal" name="bank_slip_late_fee_amount" placeholder="0,00" type="text" value="<?= $escape($decimalValue($bankAccount, 'bank_slip_late_fee_amount')); ?>">
    </div>

    <div class="col-12 col-lg-6">
      <label class="form-label" for="bank_slip_instructions_line_1">Instrução 1</label>
      <input autocomplete="off" class="form-control" id="bank_slip_instructions_line_1" maxlength="255" name="bank_slip_instructions_line_1" type="text" value="<?= $escape($bankAccount['bank_slip_instructions_line_1'] ?? ''); ?>">
    </div>

    <div class="col-12 col-lg-6">
      <label class="form-label" for="bank_slip_instructions_line_2">Instrução 2</label>
      <input autocomplete="off" class="form-control" id="bank_slip_instructions_line_2" maxlength="255" name="bank_slip_instructions_line_2" type="text" value="<?= $escape($bankAccount['bank_slip_instructions_line_2'] ?? ''); ?>">
    </div>

    <div class="col-12 col-lg-6">
      <label class="form-label" for="bank_slip_instructions_line_3">Instrução 3</label>
      <input autocomplete="off" class="form-control" id="bank_slip_instructions_line_3" maxlength="255" name="bank_slip_instructions_line_3" type="text" value="<?= $escape($bankAccount['bank_slip_instructions_line_3'] ?? ''); ?>">
    </div>

    <div class="col-12 col-lg-6">
      <label class="form-label" for="bank_slip_instructions_line_4">Instrução 4</label>
      <input autocomplete="off" class="form-control" id="bank_slip_instructions_line_4" maxlength="255" name="bank_slip_instructions_line_4" type="text" value="<?= $escape($bankAccount['bank_slip_instructions_line_4'] ?? ''); ?>">
    </div>

    <div class="col-12">
      <label class="form-label" for="active">Status</label>
      <select class="form-select" id="active" name="active">
        <option value="1" <?= ((int) $bankAccount['active'] === 1) ? 'selected' : ''; ?>>Ativa</option>
        <option value="0" <?= ((int) $bankAccount['active'] !== 1) ? 'selected' : ''; ?>>Inativa</option>
      </select>
    </div>

  </div>
  <div class="form-actions">
    <button class="btn btn-primary" type="submit">Salvar</button>
  </div>
</form>
</div>

<script src="<?= url('assets/js/bank_accounts/edit.js?v=1'); ?>"></script>
