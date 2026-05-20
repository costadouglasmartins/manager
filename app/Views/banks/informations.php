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

?>
<h5>Dados do banco</h5>
<div class="table-responsive mb-3">
  <table class="table table-hover align-middle">
    <tbody>
      <tr>
        <th class="text-muted" style="width: 220px;">Código</th>
        <td><?= (int) $bank['id']; ?></td>
      </tr>
      <tr>
        <th class="text-muted">Número</th>
        <td><?= htmlspecialchars($bank['number'], ENT_QUOTES, 'UTF-8'); ?></td>
      </tr>
      <tr>
        <th class="text-muted">Nome</th>
        <td><?= htmlspecialchars($bank['name'], ENT_QUOTES, 'UTF-8'); ?></td>
      </tr>
      <tr>
        <th class="text-muted">Criado em</th>
        <td><?= htmlspecialchars($formatDateTime($bank['created'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
      </tr>
      <tr>
        <th class="text-muted">Alterado em</th>
        <td><?= htmlspecialchars($formatDateTime($bank['modified'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
      </tr>
    </tbody>
  </table>
</div>

<div class="view-actions">
  <a href="<?= url('bank'); ?>" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
</div>
