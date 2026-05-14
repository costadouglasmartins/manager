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

$paidTotal = 0.0;
foreach (($payments ?? []) as $payment)
	{
	$paidTotal += (float) ($payment['value'] ?? 0);
	}
$balance = (float) ($operation['total_amount'] ?? 0) - $paidTotal;
?>
<link rel="stylesheet" href="<?= url('assets/css/financial_operations/informations.css?v=1'); ?>">

<div class="financial-page">
  <div class="financial-page-header">
    <div>
      <span class="financial-page-kicker"><i class="bi bi-search"></i> Operações</span>
      <h5>Dados da operação financeira</h5>
    </div>
    <a href="<?= url('financial_operation'); ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
  </div>

  <div class="table-responsive mb-3">
    <table class="table table-hover align-middle">
      <tbody>
        <tr>
          <th class="text-muted" style="width: 220px;">Código</th>
          <td><?= (int) $operation['id']; ?></td>
        </tr>
        <tr>
          <th class="text-muted">Categoria</th>
          <td><?= htmlspecialchars($operation['financial_operation_category_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
        </tr>
        <tr>
          <th class="text-muted">Valor total</th>
          <td><?= htmlspecialchars($formatMoney($operation['total_amount']), ENT_QUOTES, 'UTF-8'); ?></td>
        </tr>
        <tr>
          <th class="text-muted">Valor pago</th>
          <td><?= htmlspecialchars($formatMoney($paidTotal), ENT_QUOTES, 'UTF-8'); ?></td>
        </tr>
        <tr>
          <th class="text-muted">Saldo</th>
          <td><?= htmlspecialchars($formatMoney($balance), ENT_QUOTES, 'UTF-8'); ?></td>
        </tr>
        <tr>
          <th class="text-muted">Criada em</th>
          <td><?= htmlspecialchars($formatDateTime($operation['created'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
        </tr>
        <tr>
          <th class="text-muted">Alterada em</th>
          <td><?= htmlspecialchars($formatDateTime($operation['modified'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
        </tr>
      </tbody>
    </table>
  </div>

  <div class="financial-subheader">
    <h6>Pagamentos vinculados</h6>
    <?php if (authCan('financial_operation_payment.create')): ?>
      <a href="<?= url('financial_operation_payment/create?financial_operation_id=' . (int) $operation['id']); ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-plus-lg"></i> Novo pagamento</a>
    <?php endif; ?>
  </div>

  <div class="table-responsive">
    <table class="table table-striped table-hover align-middle">
      <thead>
        <tr>
          <th>#</th>
          <th>Valor</th>
          <th>Criado em</th>
          <th class="text-end">Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($payments)): ?>
          <?php foreach ($payments as $payment): ?>
            <tr>
              <td style="width:1%;"><?= (int) $payment['id']; ?></td>
              <td><?= htmlspecialchars($formatMoney($payment['value']), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?= htmlspecialchars($formatDateTime($payment['created'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td style="width:1%;" class="text-nowrap text-end">
                <?php if (authCan('financial_operation_payment.edit')): ?>
                  <a href="<?= url('financial_operation_payment/edit/' . $payment['id']); ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Editar"><i class="bi bi-pencil-square"></i></a>
                <?php endif; ?>
                <?php if (authCan('financial_operation_payment.informations')): ?>
                  <a href="<?= url('financial_operation_payment/informations/' . $payment['id']); ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Saiba mais"><i class="bi bi-search"></i></a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="4" class="text-center">Nenhum pagamento vinculado.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script src="<?= url('assets/js/financial_operations/informations.js?v=1'); ?>"></script>
