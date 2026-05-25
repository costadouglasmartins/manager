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
<link rel="stylesheet" href="<?= url('assets/css/products_categories/informations.css?v=1'); ?>">

<div class="store-page">
  <div class="store-page-header">
    <div>
      <span class="store-page-kicker"><i class="bi bi-search"></i> Loja</span>
      <h5>Dados da categoria de produto</h5>
    </div>
  </div>

  <div class="table-responsive mb-3">
    <table class="table table-hover align-middle">
      <tbody>
        <tr>
          <th class="text-muted" style="width: 220px;">Código</th>
          <td><?= (int) $category['id']; ?></td>
        </tr>
        <tr>
          <th class="text-muted">Nome</th>
          <td><?= htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8'); ?></td>
        </tr>
        <tr>
          <th class="text-muted">Descrição</th>
          <td><?= htmlspecialchars((string) ($category['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
        </tr>
        <tr>
          <th class="text-muted">Status</th>
          <td><?= ((int) $category['active'] === 1) ? 'Ativo' : 'Inativo'; ?></td>
        </tr>
        <tr>
          <th class="text-muted">Criada em</th>
          <td><?= htmlspecialchars($formatDateTime($category['created'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
        </tr>
        <tr>
          <th class="text-muted">Alterada em</th>
          <td><?= htmlspecialchars($formatDateTime($category['modified'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
        </tr>
      </tbody>
    </table>
  </div>

  <div class="view-actions">
    <a href="<?= url('product_category'); ?>" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
  </div>
</div>

<script src="<?= url('assets/js/products_categories/informations.js?v=1'); ?>"></script>
