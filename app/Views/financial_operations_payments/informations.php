<?php
$formatDateTime = static function ($value): string {
	$value = trim((string) $value);
	if ($value === '' || $value === '0000-00-00 00:00:00')
		{
		return '';
		}

	try
		{
		return (new DateTimeImmutable($value))->format('d/m/Y H:i');
		}
	catch (Throwable $e)
		{
		return $value;
		}
};

$formatMoney = static function ($value): string {
	return 'R$ ' . number_format((float) $value, 2, ',', '.');
};
?>
<link rel="stylesheet" href="<?= url('assets/css/financial_operations_payments/informations.css?v=1'); ?>">

<div class="financial-page">
  <div class="financial-page-header">
    <div>
      <span class="financial-page-kicker"><i class="bi bi-search"></i> Pagamentos</span>
      <h5>Dados do pagamento da operação financeira</h5>
    </div>
  </div>

  <div class="table-responsive mb-3">
    <table class="table table-hover align-middle">
      <tbody>
        <tr>
          <th class="text-muted" style="width: 220px;">Código</th>
          <td><?= (int) $payment['id']; ?></td>
        </tr>
        <tr>
          <th class="text-muted">Operação</th>
          <td>
            #<?= (int) $payment['financial_operation_id']; ?>
            - <?= htmlspecialchars($payment['financial_operation_category_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
            - <?= htmlspecialchars($formatMoney($payment['financial_operation_total_amount'] ?? 0), ENT_QUOTES, 'UTF-8'); ?>
          </td>
        </tr>
        <tr>
          <th class="text-muted">Valor</th>
          <td><?= htmlspecialchars($formatMoney($payment['value']), ENT_QUOTES, 'UTF-8'); ?></td>
        </tr>
        <tr>
          <th class="text-muted">Criado em</th>
          <td><?= htmlspecialchars($formatDateTime($payment['created'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
        </tr>
        <tr>
          <th class="text-muted">Alterado em</th>
          <td><?= htmlspecialchars($formatDateTime($payment['modified'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
        </tr>
      </tbody>
    </table>
  </div>

  <div class="view-actions">
    <a href="<?= url('financial_operation_payment'); ?>" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
  </div>
</div>

<script src="<?= url('assets/js/financial_operations_payments/informations.js?v=1'); ?>"></script>
