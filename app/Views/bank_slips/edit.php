<?php
$bankSlip = (isset($bankSlip) && is_array($bankSlip)) ? $bankSlip : [];
$formError = trim((string) ($formError ?? ''));
$value = static function (string $field, string $default = '') use ($bankSlip): string {
	return (string) ($bankSlip[$field] ?? $default);
};
$escape = static function ($content): string {
	return htmlspecialchars((string) $content, ENT_QUOTES, 'UTF-8');
};
$selected = static function (string $field, string $expected, string $default = '') use ($value): string {
	return $value($field, $default) === $expected ? 'selected' : '';
};
$dateValue = static function (string $field, string $default = '') use ($value): string {
	return substr($value($field, $default), 0, 10);
};
$accountLabel = static function (array $account): string {
	$label = trim(($account['bank_name'] ?? '') . ' - ' . ($account['name'] ?? ''), ' -');
	$accountDetails = array_filter([
		trim((string) ($account['agency'] ?? '')) !== '' ? 'Ag. ' . $account['agency'] : '',
		trim((string) ($account['account'] ?? '')) !== '' ? 'Conta ' . $account['account'] : '',
	]);

	return $accountDetails === [] ? $label : $label . ' (' . implode(' / ', $accountDetails) . ')';
};
?>
<link rel="stylesheet" href="<?= url('assets/css/bank_slips/edit.css?v=2'); ?>">

<div class="app-page bank-slip-edit-page">
  <div class="app-page-header">
    <div>
      <span class="app-page-kicker"><i class="bi bi-upc-scan"></i> Boletos</span>
      <h5>Editar boleto #<?= (int) ($bankSlip['id'] ?? 0); ?></h5>
    </div>
  </div>

  <form action="<?= url('bank_slip/update/' . (int) ($bankSlip['id'] ?? 0)); ?>" method="post" id="bankSlipEditForm" class="needs-validation bank-slip-edit-form" autocomplete="off" novalidate>
    <?php if ($formError !== ''): ?>
      <div class="alert alert-danger mb-0" role="alert"><?= $escape($formError); ?></div>
    <?php endif; ?>

    <section class="bank-slip-edit-section" aria-labelledby="bankSlipMainTitle">
      <div class="bank-slip-edit-section-header">
        <div class="bank-slip-edit-section-title">
          <i class="bi bi-receipt"></i>
          <div>
            <h2 id="bankSlipMainTitle">Identificação do boleto</h2>
            <p>Conta, numeração e situação do título.</p>
          </div>
        </div>
      </div>

      <div class="row g-3">
        <div class="col-12 col-lg-6">
          <label class="form-label" for="bank_account_id">Conta bancária *</label>
          <select id="bank_account_id" name="bank_account_id" class="form-select" required>
            <option value="">Selecione...</option>
            <?php foreach ($bankAccounts as $account): ?>
              <option value="<?= (int) $account['id']; ?>" <?= $selected('bank_account_id', (string) $account['id']); ?>><?= $escape($accountLabel($account)); ?></option>
            <?php endforeach; ?>
          </select>
          <div class="invalid-feedback">Selecione a conta bancária.</div>
        </div>

        <div class="col-12 col-md-6 col-lg-3">
          <label class="form-label" for="status">Status *</label>
          <select id="status" name="status" class="form-select" required>
            <option value="DRAFT" <?= $selected('status', 'DRAFT', 'DRAFT'); ?>>Rascunho</option>
            <option value="READY" <?= $selected('status', 'READY', 'DRAFT'); ?>>Pronto</option>
          </select>
          <div class="form-text">Ao salvar como Pronto, o boleto deixa de ser editável.</div>
        </div>

        <div class="col-12 col-md-6 col-lg-3">
          <label class="form-label" for="portfolio">Carteira *</label>
          <input class="form-control" id="portfolio" maxlength="10" name="portfolio" type="text" required value="<?= $escape($value('portfolio', '109')); ?>">
          <div class="invalid-feedback">Informe a carteira.</div>
        </div>

        <div class="col-12 col-md-6 col-lg-4">
          <label class="form-label" for="document_number">Número do documento *</label>
          <input class="form-control" id="document_number" maxlength="20" name="document_number" type="text" required value="<?= $escape($value('document_number')); ?>">
          <div class="invalid-feedback">Informe o número do documento.</div>
        </div>

        <div class="col-12 col-md-6 col-lg-4">
          <label class="form-label" for="our_number">Nosso número</label>
          <input class="form-control" id="our_number" maxlength="20" name="our_number" type="text" value="<?= $escape($value('our_number')); ?>">
        </div>

        <div class="col-12 col-md-4 col-lg-2">
          <label class="form-label" for="our_number_digit">Dígito</label>
          <input class="form-control text-uppercase js-uppercase" id="our_number_digit" maxlength="2" name="our_number_digit" type="text" value="<?= $escape($value('our_number_digit')); ?>">
        </div>
      </div>
    </section>

    <section class="bank-slip-edit-section" aria-labelledby="bankSlipPayerTitle">
      <div class="bank-slip-edit-section-header">
        <div class="bank-slip-edit-section-title">
          <i class="bi bi-person-vcard"></i>
          <div>
            <h2 id="bankSlipPayerTitle">Dados do pagador</h2>
            <p>Identificação e endereço que serão impressos no boleto.</p>
          </div>
        </div>
      </div>

      <div class="row g-3">
        <div class="col-12 col-lg-6">
          <label class="form-label" for="payer_name">Nome ou razão social *</label>
          <input class="form-control" id="payer_name" maxlength="100" name="payer_name" type="text" required value="<?= $escape($value('payer_name')); ?>">
          <div class="invalid-feedback">Informe o nome do pagador.</div>
        </div>

        <div class="col-12 col-sm-4 col-lg-2">
          <label class="form-label" for="payer_document_type">Tipo *</label>
          <select class="form-select" id="payer_document_type" name="payer_document_type" required>
            <option value="CPF" <?= $selected('payer_document_type', 'CPF', 'CPF'); ?>>CPF</option>
            <option value="CNPJ" <?= $selected('payer_document_type', 'CNPJ', 'CPF'); ?>>CNPJ</option>
          </select>
        </div>

        <div class="col-12 col-sm-8 col-lg-4">
          <label class="form-label" for="payer_document">Documento *</label>
          <input class="form-control mask-cpf-cnpj" id="payer_document" maxlength="18" name="payer_document" type="text" required value="<?= $escape($value('payer_document')); ?>">
          <div class="invalid-feedback">Informe um CPF ou CNPJ válido.</div>
        </div>

        <div class="col-12 col-lg-6">
          <label class="form-label" for="payer_street">Logradouro *</label>
          <input class="form-control" id="payer_street" maxlength="120" name="payer_street" type="text" required value="<?= $escape($value('payer_street')); ?>">
          <div class="invalid-feedback">Informe o logradouro.</div>
        </div>

        <div class="col-12 col-md-6 col-lg-3">
          <label class="form-label" for="payer_neighborhood">Bairro</label>
          <input class="form-control" id="payer_neighborhood" maxlength="60" name="payer_neighborhood" type="text" value="<?= $escape($value('payer_neighborhood')); ?>">
        </div>

        <div class="col-12 col-md-6 col-lg-3">
          <label class="form-label" for="payer_zip_code">CEP *</label>
          <input class="form-control" id="payer_zip_code" inputmode="numeric" maxlength="9" name="payer_zip_code" placeholder="00000-000" type="text" required value="<?= $escape($value('payer_zip_code')); ?>">
          <div class="invalid-feedback">Informe o CEP.</div>
        </div>

        <div class="col-12 col-md-8 col-lg-6">
          <label class="form-label" for="payer_city">Cidade *</label>
          <input class="form-control" id="payer_city" maxlength="60" name="payer_city" type="text" required value="<?= $escape($value('payer_city')); ?>">
          <div class="invalid-feedback">Informe a cidade.</div>
        </div>

        <div class="col-12 col-md-4 col-lg-2">
          <label class="form-label" for="payer_state">UF *</label>
          <input class="form-control text-uppercase js-uppercase" id="payer_state" maxlength="2" name="payer_state" type="text" required value="<?= $escape($value('payer_state')); ?>">
          <div class="invalid-feedback">Informe a UF.</div>
        </div>
      </div>
    </section>

    <section class="bank-slip-edit-section" aria-labelledby="bankSlipValuesTitle">
      <div class="bank-slip-edit-section-header">
        <div class="bank-slip-edit-section-title">
          <i class="bi bi-cash-coin"></i>
          <div>
            <h2 id="bankSlipValuesTitle">Valores e vencimento</h2>
            <p>Valor nominal, datas e condições financeiras do título.</p>
          </div>
        </div>
      </div>

      <div class="row g-3">
        <div class="col-12 col-md-4">
          <label class="form-label" for="amount">Valor do boleto *</label>
          <div class="input-group">
            <span class="input-group-text">R$</span>
            <input class="form-control js-money" id="amount" inputmode="decimal" name="amount" placeholder="0,00" type="text" required value="<?= $escape($value('amount')); ?>">
          </div>
          <div class="invalid-feedback">Informe o valor do boleto.</div>
        </div>

        <div class="col-12 col-md-4">
          <label class="form-label" for="issue_date">Data de emissão *</label>
          <input class="form-control" id="issue_date" name="issue_date" type="date" required value="<?= $escape($dateValue('issue_date', date('Y-m-d'))); ?>">
          <div class="invalid-feedback">Informe a data de emissão.</div>
        </div>

        <div class="col-12 col-md-4">
          <label class="form-label" for="due_date">Data de vencimento *</label>
          <input class="form-control" id="due_date" name="due_date" type="date" required value="<?= $escape($dateValue('due_date')); ?>">
          <div class="invalid-feedback">Informe a data de vencimento.</div>
        </div>

        <div class="col-12 col-md-6 col-xl-3">
          <label class="form-label" for="interest_date">Início dos juros</label>
          <input class="form-control" id="interest_date" name="interest_date" type="date" value="<?= $escape($dateValue('interest_date')); ?>">
        </div>

        <div class="col-12 col-md-6 col-xl-3">
          <label class="form-label" for="interest_amount_per_day">Juros por dia *</label>
          <div class="input-group">
            <span class="input-group-text">R$</span>
            <input class="form-control js-money" id="interest_amount_per_day" inputmode="decimal" name="interest_amount_per_day" placeholder="0,00" type="text" required value="<?= $escape($value('interest_amount_per_day', '0,00')); ?>">
          </div>
        </div>

        <div class="col-12 col-md-6 col-xl-3">
          <label class="form-label" for="discount_date">Limite do desconto</label>
          <input class="form-control" id="discount_date" name="discount_date" type="date" value="<?= $escape($dateValue('discount_date')); ?>">
        </div>

        <div class="col-12 col-md-6 col-xl-3">
          <label class="form-label" for="discount_amount">Desconto *</label>
          <div class="input-group">
            <span class="input-group-text">R$</span>
            <input class="form-control js-money" id="discount_amount" inputmode="decimal" name="discount_amount" placeholder="0,00" type="text" required value="<?= $escape($value('discount_amount', '0,00')); ?>">
          </div>
        </div>

        <div class="col-12 col-md-6 col-xl-3">
          <label class="form-label" for="fine_date">Início da multa</label>
          <input class="form-control" id="fine_date" name="fine_date" type="date" value="<?= $escape($dateValue('fine_date')); ?>">
        </div>

        <div class="col-12 col-md-6 col-xl-3">
          <label class="form-label" for="fine_amount">Multa *</label>
          <div class="input-group">
            <span class="input-group-text">R$</span>
            <input class="form-control js-money" id="fine_amount" inputmode="decimal" name="fine_amount" placeholder="0,00" type="text" required value="<?= $escape($value('fine_amount', '0,00')); ?>">
          </div>
        </div>
      </div>
    </section>

    <section class="bank-slip-edit-section" aria-labelledby="bankSlipSettlementTitle">
      <div class="bank-slip-edit-section-header">
        <div class="bank-slip-edit-section-title">
          <i class="bi bi-check2-circle"></i>
          <div>
            <h2 id="bankSlipSettlementTitle">Liquidação e crédito</h2>
            <p>Informações de pagamento e crédito do boleto.</p>
          </div>
        </div>
      </div>

      <div class="row g-3">
        <div class="col-12 col-md-6 col-xl-3">
          <label class="form-label" for="payment_date">Data do pagamento</label>
          <input class="form-control" id="payment_date" name="payment_date" type="date" value="<?= $escape($dateValue('payment_date')); ?>">
        </div>

        <div class="col-12 col-md-6 col-xl-3">
          <label class="form-label" for="paid_amount">Valor pago</label>
          <div class="input-group">
            <span class="input-group-text">R$</span>
            <input class="form-control js-money" id="paid_amount" inputmode="decimal" name="paid_amount" placeholder="0,00" type="text" value="<?= $escape($value('paid_amount')); ?>">
          </div>
        </div>

        <div class="col-12 col-md-6 col-xl-3">
          <label class="form-label" for="credit_date">Data do crédito</label>
          <input class="form-control" id="credit_date" name="credit_date" type="date" value="<?= $escape($dateValue('credit_date')); ?>">
        </div>

        <div class="col-12 col-md-6 col-xl-3">
          <label class="form-label" for="credited_amount">Valor creditado</label>
          <div class="input-group">
            <span class="input-group-text">R$</span>
            <input class="form-control js-money" id="credited_amount" inputmode="decimal" name="credited_amount" placeholder="0,00" type="text" value="<?= $escape($value('credited_amount')); ?>">
          </div>
        </div>
      </div>
    </section>

    <section class="bank-slip-edit-section" aria-labelledby="bankSlipCollectionTitle">
      <div class="bank-slip-edit-section-header">
        <div class="bank-slip-edit-section-title">
          <i class="bi bi-calendar2-exclamation"></i>
          <div>
            <h2 id="bankSlipCollectionTitle">Protesto e baixa</h2>
            <p>Códigos e prazos usados no processamento bancário.</p>
          </div>
        </div>
      </div>

      <div class="row g-3">
        <div class="col-12 col-md-6 col-xl-3">
          <label class="form-label" for="protest_code">Código de protesto</label>
          <input class="form-control text-uppercase js-uppercase" id="protest_code" maxlength="2" name="protest_code" type="text" value="<?= $escape($value('protest_code')); ?>">
        </div>

        <div class="col-12 col-md-6 col-xl-3">
          <label class="form-label" for="protest_days">Dias para protesto</label>
          <input class="form-control" id="protest_days" inputmode="numeric" max="255" min="0" name="protest_days" type="number" value="<?= $escape($value('protest_days')); ?>">
        </div>

        <div class="col-12 col-md-6 col-xl-3">
          <label class="form-label" for="write_off_code">Código de baixa</label>
          <input class="form-control text-uppercase js-uppercase" id="write_off_code" maxlength="2" name="write_off_code" type="text" value="<?= $escape($value('write_off_code')); ?>">
        </div>

        <div class="col-12 col-md-6 col-xl-3">
          <label class="form-label" for="write_off_days">Dias para baixa</label>
          <input class="form-control" id="write_off_days" inputmode="numeric" max="255" min="0" name="write_off_days" type="number" value="<?= $escape($value('write_off_days')); ?>">
        </div>
      </div>
    </section>

    <section class="bank-slip-edit-section" aria-labelledby="bankSlipReturnTitle">
      <div class="bank-slip-edit-section-header">
        <div class="bank-slip-edit-section-title">
          <i class="bi bi-arrow-left-right"></i>
          <div>
            <h2 id="bankSlipReturnTitle">Retorno bancário</h2>
            <p>Última ocorrência conhecida para o boleto.</p>
          </div>
        </div>
      </div>

      <div class="row g-3">
        <div class="col-12 col-md-3">
          <label class="form-label" for="last_occurrence_code">Código da ocorrência</label>
          <input class="form-control text-uppercase js-uppercase" id="last_occurrence_code" maxlength="2" name="last_occurrence_code" type="text" value="<?= $escape($value('last_occurrence_code')); ?>">
        </div>

        <div class="col-12 col-md-9">
          <label class="form-label" for="last_occurrence_message">Mensagem da ocorrência</label>
          <textarea class="form-control" id="last_occurrence_message" maxlength="255" name="last_occurrence_message" rows="2"><?= $escape($value('last_occurrence_message')); ?></textarea>
        </div>
      </div>
    </section>

    <section class="bank-slip-edit-section" aria-labelledby="bankSlipIntegrationTitle">
      <div class="bank-slip-edit-section-header">
        <div class="bank-slip-edit-section-title">
          <i class="bi bi-diagram-3"></i>
          <div>
            <h2 id="bankSlipIntegrationTitle">Vínculos e controle</h2>
            <p>Relacionamentos opcionais e campos administrados automaticamente pelo sistema.</p>
          </div>
        </div>
      </div>

      <div class="row g-3">
        <div class="col-12 col-md-6 col-xl-4">
          <label class="form-label" for="financial_operation_payment_id">ID do pagamento financeiro</label>
          <input class="form-control" id="financial_operation_payment_id" inputmode="numeric" min="1" name="financial_operation_payment_id" type="number" value="<?= $escape($value('financial_operation_payment_id')); ?>">
          <div class="form-text">O pagamento só pode estar vinculado a um boleto.</div>
        </div>

        <div class="col-12 col-md-6 col-xl-4">
          <label class="form-label" for="bank_file_id">ID do arquivo bancário</label>
          <input class="form-control" id="bank_file_id" inputmode="numeric" min="1" name="bank_file_id" type="number" value="<?= $escape($value('bank_file_id')); ?>">
        </div>

        <div class="col-12 col-md-6 col-xl-4">
          <label class="form-label" for="automatic_id">ID do boleto</label>
          <input class="form-control bank-slip-edit-system-field" id="automatic_id" name="id" type="text" value="<?= (int) ($bankSlip['id'] ?? 0); ?>" disabled>
        </div>

        <div class="col-12 col-md-6">
          <label class="form-label" for="automatic_created">Criado em</label>
          <input class="form-control bank-slip-edit-system-field" id="automatic_created" name="created" type="text" value="<?= $escape($value('created')); ?>" disabled>
        </div>

        <div class="col-12 col-md-6">
          <label class="form-label" for="automatic_modified">Modificado em</label>
          <input class="form-control bank-slip-edit-system-field" id="automatic_modified" name="modified" type="text" value="<?= $escape($value('modified')); ?>" disabled>
        </div>
      </div>
    </section>

    <div class="form-actions">
      <a href="<?= url('bank_slip'); ?>" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Cancelar</a>
      <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> Salvar</button>
    </div>
  </form>
</div>

<script src="<?= url('assets/js/bank_slips/edit.js?v=2'); ?>"></script>
