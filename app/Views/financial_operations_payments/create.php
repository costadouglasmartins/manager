<?php
$formatMoney = static function ($value): string {
	return 'R$ ' . number_format((float) $value, 2, ',', '.');
};

$operationLabel = static function (array $operation) use ($formatMoney): string {
	return '#' . (int) $operation['id'] . ' - ' . trim((string) ($operation['financial_operation_category_name'] ?? '')) . ' - ' . $formatMoney($operation['total_amount'] ?? 0);
};

$selectedOperationId = (int) ($selectedOperationId ?? 0);
?>
<link rel="stylesheet" href="<?= url('assets/css/financial_operations_payments/create.css?v=1'); ?>">

<div class="financial-page">
  <div class="financial-page-header">
    <div>
      <span class="financial-page-kicker"><i class="bi bi-cash-stack"></i> Pagamentos</span>
      <h5>Criar pagamento de operação financeira</h5>
    </div>
    <a href="<?= url('financial_operation_payment'); ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
  </div>

  <form action="<?= url('financial_operation_payment/store'); ?>" method="POST" class="needs-validation financial-form" novalidate>
    <section class="financial-section">
      <div class="row g-3">

        <div class="col-12 col-lg-8">
          <label class="form-label" for="financial_operation_id">Operação</label>
          <select class="form-select" id="financial_operation_id" name="financial_operation_id" required>
            <option value="" <?= ($selectedOperationId === 0) ? 'selected' : ''; ?> disabled>Selecione...</option>
            <?php foreach ($operations as $operation): ?>
              <option value="<?= (int) $operation['id']; ?>" <?= ($selectedOperationId === (int) $operation['id']) ? 'selected' : ''; ?>>
                <?= htmlspecialchars($operationLabel($operation), ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="invalid-feedback">Selecione a operação.</div>
        </div>

        <div class="col-12 col-lg-4">
          <label class="form-label" for="value">Valor</label>
          <input autocomplete="off" class="form-control js-money" id="value" inputmode="decimal" name="value" required type="text" placeholder="0,00">
          <div class="invalid-feedback">Informe o valor do pagamento.</div>
        </div>

      </div>
    </section>

    <div class="financial-actions">
      <a href="<?= url('financial_operation_payment'); ?>" class="btn btn-secondary">Cancelar</a>
      <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> Salvar</button>
    </div>
  </form>
</div>

<script src="<?= url('assets/js/financial_operations_payments/create.js?v=1'); ?>"></script>
