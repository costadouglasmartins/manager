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
<link rel="stylesheet" href="<?= url('assets/css/financial_operations_subcategories/informations.css?v=1'); ?>">

<div class="financial-page">
  <div class="financial-page-header">
    <div>
      <span class="financial-page-kicker"><i class="bi bi-search"></i> Subcategorias</span>
      <h5>Dados da subcategoria da operação financeira</h5>
    </div>
  </div>

  <div class="table-responsive mb-3">
    <table class="table table-hover align-middle">
      <tbody>
        <tr>
          <th class="text-muted" style="width: 220px;">Código</th>
          <td><?= (int) $subcategory['id']; ?></td>
        </tr>
        <tr>
          <th class="text-muted">Categoria</th>
          <td><?= htmlspecialchars($subcategory['financial_operation_category_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
        </tr>
        <tr>
          <th class="text-muted">Nome</th>
          <td><?= htmlspecialchars($subcategory['name'], ENT_QUOTES, 'UTF-8'); ?></td>
        </tr>
        <tr>
          <th class="text-muted">Criada em</th>
          <td><?= htmlspecialchars($formatDateTime($subcategory['created'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
        </tr>
        <tr>
          <th class="text-muted">Alterada em</th>
          <td><?= htmlspecialchars($formatDateTime($subcategory['modified'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
        </tr>
      </tbody>
    </table>
  </div>

  <div class="form-actions">
    <a href="<?= url('financial_operation_subcategory'); ?>" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
  </div>
</div>

<script src="<?= url('assets/js/financial_operations_subcategories/informations.js?v=1'); ?>"></script>
