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

$formatQuantity = static function ($value): string {
	return number_format((float) $value, 3, ',', '.');
};

$movementTypeLabel = static function (string $type): string {
	return [
		'IN'         => 'Entrada',
		'OUT'        => 'Saída',
		'ADJUSTMENT' => 'Ajuste',
	][$type] ?? $type;
};
?>
<link rel="stylesheet" href="<?= url('assets/css/rich_text_editor.css?v=1'); ?>">
<link rel="stylesheet" href="<?= url('assets/css/product_stocks_movements/informations.css?v=1'); ?>">

<div class="store-page">
  <div class="store-page-header">
    <div>
      <span class="store-page-kicker"><i class="bi bi-search"></i> Loja</span>
      <h5>Dados da movimentação de estoque</h5>
    </div>
  </div>

  <div class="table-responsive mb-3">
    <table class="table table-hover align-middle">
      <tbody>
        <tr>
          <th class="text-muted" style="width: 220px;">Código</th>
          <td><?= (int) $movement['id']; ?></td>
        </tr>
        <tr>
          <th class="text-muted">Produto</th>
          <td><?= htmlspecialchars($movement['product_name'], ENT_QUOTES, 'UTF-8'); ?></td>
        </tr>
        <tr>
          <th class="text-muted">SKU</th>
          <td><?= htmlspecialchars((string) ($movement['product_sku'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
        </tr>
        <tr>
          <th class="text-muted">Tipo</th>
          <td><?= htmlspecialchars($movementTypeLabel((string) $movement['movement_type']), ENT_QUOTES, 'UTF-8'); ?></td>
        </tr>
        <tr>
          <th class="text-muted">Quantidade</th>
          <td><?= htmlspecialchars($formatQuantity($movement['quantity'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></td>
        </tr>
        <tr>
          <th class="text-muted">Saldo anterior</th>
          <td><?= htmlspecialchars($formatQuantity($movement['previous_quantity'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></td>
        </tr>
        <tr>
          <th class="text-muted">Novo saldo</th>
          <td><?= htmlspecialchars($formatQuantity($movement['new_quantity'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></td>
        </tr>
        <tr>
          <th class="text-muted">Referência</th>
          <td><?= htmlspecialchars((string) ($movement['reference'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
        </tr>
        <tr>
          <th class="text-muted">Notas</th>
          <td><div class="rich-editor-content"><?= (string) ($movement['observations'] ?? ''); ?></div></td>
        </tr>
        <tr>
          <th class="text-muted">Data</th>
          <td><?= htmlspecialchars($formatDateTime($movement['movement_date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
        </tr>
        <tr>
          <th class="text-muted">Criada em</th>
          <td><?= htmlspecialchars($formatDateTime($movement['created'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
        </tr>
      </tbody>
    </table>
  </div>

  <div class="form-actions">
    <a href="<?= url('product_stock_movement'); ?>" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
  </div>
</div>

<script src="<?= url('assets/js/product_stocks_movements/informations.js?v=1'); ?>"></script>
