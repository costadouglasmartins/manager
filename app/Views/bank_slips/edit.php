<?php
$bankSlip = $bankSlip ?? [];
$value = static function (string $field, string $default = '') use ($bankSlip): string {
	return (string) ($bankSlip[$field] ?? $default);
};
$selected = static function (string $field, string $expected, string $default = '') use ($value): string {
	return $value($field, $default) === $expected ? 'selected' : '';
};
$dateValue = static function (string $field, string $default = '') use ($value): string {
	$date = $value($field, $default);
	return substr($date, 0, 10);
};
$accountLabel = static function (array $account): string {
	return trim(($account['bank_name'] ?? '') . ' - ' . ($account['name'] ?? ''), ' -');
};
?>
<link rel="stylesheet" href="<?= url('assets/css/bank_slips/edit.css?v=1'); ?>">

<div class="app-page">
  <div class="app-page-header">
    <div>
      <span class="app-page-kicker"><i class="bi bi-upc-scan"></i> Boletos</span>
      <h5>Editar boleto #<?= (int) ($bankSlip['id'] ?? 0); ?></h5>
    </div>
  </div>

<?php if (!empty($formError)): ?>
  <div class="alert alert-danger"><?= htmlspecialchars((string) $formError, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>

<form action="<?= url('bank_slip/update/' . (int) ($bankSlip['id'] ?? 0)); ?>" method="post" class="needs-validation" novalidate>
  <section class="app-section">
    <div class="row g-3">
  <div class="col-md-6">
    <label class="form-label" for="bank_account_id">Conta bancária</label>
    <select id="bank_account_id" name="bank_account_id" class="form-select" required>
      <option value="">Selecione...</option>
      <?php foreach ($bankAccounts as $account): ?>
        <option value="<?= (int) $account['id']; ?>" <?= $selected('bank_account_id', (string) $account['id']); ?>>
          <?= htmlspecialchars($accountLabel($account), ENT_QUOTES, 'UTF-8'); ?>
        </option>
      <?php endforeach; ?>
    </select>
    <div class="invalid-feedback">Selecione a conta bancária.</div>
  </div>

  <div class="col-md-3">
    <label class="form-label" for="portfolio">Carteira</label>
    <input type="text" id="portfolio" name="portfolio" class="form-control" value="<?= htmlspecialchars($value('portfolio', '109'), ENT_QUOTES, 'UTF-8'); ?>" required>
    <div class="invalid-feedback">Informe a carteira.</div>
  </div>

  <div class="col-md-3">
    <label class="form-label" for="status">Status</label>
    <select id="status" name="status" class="form-select">
      <option value="DRAFT" <?= $selected('status', 'DRAFT', 'DRAFT'); ?>>DRAFT</option>
      <option value="READY" <?= $selected('status', 'READY', 'DRAFT'); ?>>READY</option>
    </select>
    <small class="text-muted">Ao salvar como READY, o boleto deixa de ser editável.</small>
  </div>

  <div class="col-md-4">
    <label class="form-label" for="document_number">Número do documento</label>
    <input type="text" id="document_number" name="document_number" class="form-control" value="<?= htmlspecialchars($value('document_number'), ENT_QUOTES, 'UTF-8'); ?>" required>
    <div class="invalid-feedback">Informe o número do documento.</div>
  </div>

  <div class="col-md-4">
    <label class="form-label" for="our_number">Nosso número</label>
    <input type="text" id="our_number" name="our_number" class="form-control" value="<?= htmlspecialchars($value('our_number'), ENT_QUOTES, 'UTF-8'); ?>">
  </div>

  <div class="col-md-4">
    <label class="form-label" for="financial_operation_payment_id">ID pagamento financeiro (opcional)</label>
    <input type="number" id="financial_operation_payment_id" name="financial_operation_payment_id" class="form-control" value="<?= htmlspecialchars($value('financial_operation_payment_id'), ENT_QUOTES, 'UTF-8'); ?>">
  </div>

  <div class="col-md-4">
    <label class="form-label" for="amount">Valor</label>
    <input type="text" id="amount" name="amount" class="form-control" value="<?= htmlspecialchars($value('amount'), ENT_QUOTES, 'UTF-8'); ?>" required>
    <div class="invalid-feedback">Informe o valor.</div>
  </div>

  <div class="col-md-4">
    <label class="form-label" for="issue_date">Emissão</label>
    <input type="date" id="issue_date" name="issue_date" class="form-control" value="<?= htmlspecialchars($dateValue('issue_date', date('Y-m-d')), ENT_QUOTES, 'UTF-8'); ?>" required>
    <div class="invalid-feedback">Informe a data de emissão.</div>
  </div>

  <div class="col-md-4">
    <label class="form-label" for="due_date">Vencimento</label>
    <input type="date" id="due_date" name="due_date" class="form-control" value="<?= htmlspecialchars($dateValue('due_date'), ENT_QUOTES, 'UTF-8'); ?>" required>
    <div class="invalid-feedback">Informe a data de vencimento.</div>
  </div>

  <div class="col-md-6">
    <label class="form-label" for="payer_name">Nome do sacado</label>
    <input type="text" id="payer_name" name="payer_name" class="form-control" value="<?= htmlspecialchars($value('payer_name'), ENT_QUOTES, 'UTF-8'); ?>" required>
    <div class="invalid-feedback">Informe o nome do sacado.</div>
  </div>

  <div class="col-md-2">
    <label class="form-label" for="payer_document_type">Tipo doc.</label>
    <select id="payer_document_type" name="payer_document_type" class="form-select">
      <option value="CPF" <?= $selected('payer_document_type', 'CPF', 'CPF'); ?>>CPF</option>
      <option value="CNPJ" <?= $selected('payer_document_type', 'CNPJ', 'CPF'); ?>>CNPJ</option>
    </select>
  </div>

  <div class="col-md-4">
    <label class="form-label" for="payer_document">Documento</label>
    <input type="text" id="payer_document" name="payer_document" class="form-control" value="<?= htmlspecialchars($value('payer_document'), ENT_QUOTES, 'UTF-8'); ?>" required>
    <div class="invalid-feedback">Informe o documento.</div>
  </div>

  <div class="col-md-6">
    <label class="form-label" for="payer_street">Endereço</label>
    <input type="text" id="payer_street" name="payer_street" class="form-control" value="<?= htmlspecialchars($value('payer_street'), ENT_QUOTES, 'UTF-8'); ?>" required>
    <div class="invalid-feedback">Informe o endereço.</div>
  </div>

  <div class="col-md-3">
    <label class="form-label" for="payer_neighborhood">Bairro</label>
    <input type="text" id="payer_neighborhood" name="payer_neighborhood" class="form-control" value="<?= htmlspecialchars($value('payer_neighborhood'), ENT_QUOTES, 'UTF-8'); ?>">
  </div>

  <div class="col-md-3">
    <label class="form-label" for="payer_city">Cidade</label>
    <input type="text" id="payer_city" name="payer_city" class="form-control" value="<?= htmlspecialchars($value('payer_city'), ENT_QUOTES, 'UTF-8'); ?>" required>
    <div class="invalid-feedback">Informe a cidade.</div>
  </div>

  <div class="col-md-2">
    <label class="form-label" for="payer_state">UF</label>
    <input type="text" id="payer_state" name="payer_state" maxlength="2" class="form-control" value="<?= htmlspecialchars($value('payer_state'), ENT_QUOTES, 'UTF-8'); ?>" required>
    <div class="invalid-feedback">Informe a UF.</div>
  </div>

  <div class="col-md-3">
    <label class="form-label" for="payer_zip_code">CEP</label>
    <input type="text" id="payer_zip_code" name="payer_zip_code" class="form-control" value="<?= htmlspecialchars($value('payer_zip_code'), ENT_QUOTES, 'UTF-8'); ?>" required>
    <div class="invalid-feedback">Informe o CEP.</div>
  </div>

    </div>
  </section>
  <div class="form-actions">
    <a href="<?= url('bank_slip'); ?>" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Cancelar</a>
    <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> Salvar</button>
  </div>
</form>
</div>

<script src="<?= url('assets/js/bank_slips/edit.js?v=1'); ?>"></script>
