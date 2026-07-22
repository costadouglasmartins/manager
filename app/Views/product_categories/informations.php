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
$attributeNames = [];
foreach (($attributes ?? []) as $attribute)
	{
	$attributeNames[(int) $attribute['product_attribute_id']] = (string) $attribute['name'];
	}
$conditionLabel = static function (array $attribute) use ($attributeNames): string {
	$sourceId = (int) ($attribute['condition_source_attribute_id'] ?? 0);
	if ($sourceId <= 0) return 'Sempre';
	$operator = [
		'EQUALS' => 'igual a',
		'NOT_EQUALS' => 'diferente de',
		'FILLED' => 'preenchida',
		'EMPTY' => 'vazia',
	][(string) ($attribute['condition_operator'] ?? 'EQUALS')] ?? 'igual a';
	$source = $attributeNames[$sourceId] ?? ('#' . $sourceId);
	$value = trim((string) ($attribute['condition_value'] ?? ''));
	$text = $source . ' ' . $operator . ($value !== '' ? ' ' . $value : '');
	if ((int) ($attribute['required_when_matched'] ?? 0) === 1) $text .= ' · obrigatória quando atender';
	return $text;
};
?>
<link rel="stylesheet" href="<?= url('assets/css/rich_text_editor.css?v=1'); ?>">
<link rel="stylesheet" href="<?= url('assets/css/product_categories/informations.css?v=1'); ?>">

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
          <td><div class="rich-editor-content"><?= (string) ($category['description'] ?? ''); ?></div></td>
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

  <section class="store-section">
    <div class="store-subheader"><h6>Características da categoria</h6></div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead><tr><th>Característica</th><th>Tipo</th><th>Obrigatória</th><th>Condição</th><th>Opções</th></tr></thead>
        <tbody>
          <?php if (!empty($attributes)): ?>
            <?php foreach ($attributes as $attribute): ?>
              <tr><td><?= htmlspecialchars((string) $attribute['name'], ENT_QUOTES, 'UTF-8'); ?></td><td><?= htmlspecialchars((string) $attribute['data_type'], ENT_QUOTES, 'UTF-8'); ?><?= !empty($attribute['measurement_type']) ? ' · ' . htmlspecialchars((string) $attribute['measurement_type'], ENT_QUOTES, 'UTF-8') : ''; ?></td><td><?= (int) $attribute['required'] === 1 ? 'Sim' : 'Não'; ?></td><td><?= htmlspecialchars($conditionLabel($attribute), ENT_QUOTES, 'UTF-8'); ?></td><td><?= htmlspecialchars(str_replace("\n", ', ', (string) ($attribute['options_text'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td></tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td class="text-center text-muted" colspan="5">Nenhuma característica configurada.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <div class="form-actions">
    <a href="<?= url('product_category'); ?>" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
  </div>
</div>

<script src="<?= url('assets/js/product_categories/informations.js?v=1'); ?>"></script>
