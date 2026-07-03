<?php
$formatMoney = static function ($value): string {
	return 'R$ ' . number_format((float) $value, 2, ',', '.');
};

$operationLabel = static function (array $operation) use ($formatMoney): string {
	return '#' . (int) $operation['id'] . ' - ' . trim((string) ($operation['financial_operation_category_name'] ?? '')) . ' - ' . $formatMoney($operation['total_amount'] ?? 0);
};

$paymentValue = number_format((float) ($payment['value'] ?? 0), 2, ',', '.');
?>
<link rel="stylesheet" href="<?= url('assets/css/financial_operations_payments/edit.css?v=1'); ?>">

<div class="financial-page app-page">
  <div class="financial-page-header app-page-header">
    <div>
      <span class="financial-page-kicker app-page-kicker"><i class="bi bi-pencil-square"></i> Pagamentos</span>
      <h5>Editar pagamento de operação financeira</h5>
    </div>
  </div>

  <form action="<?= url('financial_operation_payment/update/' . $payment['id']); ?>" method="POST" class="needs-validation financial-form" novalidate>
    <section class="financial-section">
      <div class="row g-3">

        <div class="col-12 col-lg-8">
          <label class="form-label" for="financial_operation_id">Operação</label>
          <select class="form-select" id="financial_operation_id" name="financial_operation_id" required>
            <option value="" disabled>Selecione...</option>
            <?php foreach ($operations as $operation): ?>
              <option value="<?= (int) $operation['id']; ?>" <?= ((int) $payment['financial_operation_id'] === (int) $operation['id']) ? 'selected' : ''; ?>>
                <?= htmlspecialchars($operationLabel($operation), ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="invalid-feedback">Selecione a operação.</div>
        </div>

        <div class="col-12 col-lg-4">
          <label class="form-label" for="value">Valor</label>
          <input autocomplete="off" class="form-control js-money" id="value" inputmode="decimal" name="value" required type="text" value="<?= htmlspecialchars($paymentValue, ENT_QUOTES, 'UTF-8'); ?>">
          <div class="invalid-feedback">Informe o valor do pagamento.</div>
        </div>

      </div>
    </section>

    <div class="form-actions">
      <a href="<?= url('financial_operation_payment'); ?>" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Cancelar</a>
      <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> Salvar</button>
    </div>
  </form>
</div>

<script src="<?= url('assets/js/financial_operations_payments/edit.js?v=1'); ?>"></script>
