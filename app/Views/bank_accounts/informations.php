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

$activeLabel = static function ($value): string {
	return ((int) $value === 1) ? 'Ativa' : 'Inativa';
};
?>
<h5>Dados da conta bancária</h5>
<div class="table-responsive mb-3">
  <table class="table table-hover align-middle">
    <tbody>
      <tr>
        <th class="text-muted" style="width: 220px;">Código</th>
        <td><?= (int) $bankAccount['id']; ?></td>
      </tr>
      <tr>
        <th class="text-muted">Banco</th>
        <td><?= htmlspecialchars(trim(($bankAccount['bank_number'] ?? '') . ' - ' . ($bankAccount['bank_name'] ?? ''), ' -'), ENT_QUOTES, 'UTF-8'); ?></td>
      </tr>
      <tr>
        <th class="text-muted">Nome</th>
        <td><?= htmlspecialchars($bankAccount['name'], ENT_QUOTES, 'UTF-8'); ?></td>
      </tr>
      <tr>
        <th class="text-muted">Agência</th>
        <td><?= htmlspecialchars($bankAccount['agency'], ENT_QUOTES, 'UTF-8'); ?></td>
      </tr>
      <tr>
        <th class="text-muted">Conta</th>
        <td><?= htmlspecialchars($bankAccount['account'], ENT_QUOTES, 'UTF-8'); ?></td>
      </tr>
      <tr>
        <th class="text-muted">Status</th>
        <td><?= $activeLabel($bankAccount['active']); ?></td>
      </tr>
      <tr>
        <th class="text-muted">Criada em</th>
        <td><?= htmlspecialchars($formatDateTime($bankAccount['created'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
      </tr>
      <tr>
        <th class="text-muted">Alterada em</th>
        <td><?= htmlspecialchars($formatDateTime($bankAccount['modified'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
      </tr>
    </tbody>
  </table>
</div>

<div class="form-actions">
  <a href="<?= url('bank_account'); ?>" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
</div>
