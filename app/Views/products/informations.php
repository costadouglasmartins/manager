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

$attributeValue = static function (array $attribute) use ($formatQuantity): string {
	$type = strtoupper((string) ($attribute['data_type'] ?? 'TEXT'));
	if (in_array($type, ['OPTION', 'SELECT'], true)) $value = (string) ($attribute['option_label'] ?? '');
	elseif (in_array($type, ['INTEGER', 'INT'], true)) $value = (string) ($attribute['value_integer'] ?? '');
	elseif (in_array($type, ['DECIMAL', 'NUMBER'], true)) $value = $formatQuantity($attribute['value_decimal'] ?? 0);
	elseif (in_array($type, ['BOOLEAN', 'BOOL'], true)) $value = ((int) ($attribute['value_boolean'] ?? 0) === 1) ? 'Sim' : 'Não';
	elseif ($type === 'DATE' && !empty($attribute['value_date']))
		{
		try { $value = (new DateTimeImmutable((string) $attribute['value_date']))->format('d/m/Y'); }
		catch (Throwable $e) { $value = (string) $attribute['value_date']; }
		}
	else $value = (string) ($attribute['value_text'] ?? '');
	$unit = trim((string) ($attribute['unit_symbol'] ?? ''));
	return trim($value . ($unit !== '' ? ' ' . $unit : ''));
};
?>
<link rel="stylesheet" href="<?= url('assets/css/rich_text_editor.css?v=1'); ?>">
<link rel="stylesheet" href="<?= url('assets/css/products/informations.css?v=1'); ?>">

<div class="store-page">
  <div class="store-page-header">
    <div>
      <span class="store-page-kicker"><i class="bi bi-search"></i> Loja</span>
      <h5>Dados do produto</h5>
    </div>
  </div>

  <section class="store-section">
    <div class="table-responsive">
      <table class="table table-hover align-middle">
        <tbody>
          <tr>
            <th class="text-muted" style="width: 220px;">Código</th>
            <td><?= (int) $product['id']; ?></td>
          </tr>
          <tr>
            <th class="text-muted">Nome</th>
            <td><?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?></td>
          </tr>
          <tr>
            <th class="text-muted">Categoria</th>
            <td><?= htmlspecialchars((string) ($product['product_category_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
          </tr>
          <tr>
            <th class="text-muted">SKU</th>
            <td><?= htmlspecialchars((string) ($product['sku'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
          </tr>
          <tr>
            <th class="text-muted">Código de barras</th>
            <td><?= htmlspecialchars((string) ($product['barcode'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
          </tr>
          <tr>
            <th class="text-muted">Preço de venda</th>
            <td><?= htmlspecialchars($formatMoney($product['sale_price'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></td>
          </tr>
          <tr>
            <th class="text-muted">Unidade de venda</th>
            <td><?= htmlspecialchars((string) ($product['sale_unit_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> (<?= htmlspecialchars((string) ($product['sale_unit_symbol'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>), incremento <?= htmlspecialchars($formatQuantity($product['sale_quantity_step'] ?? 1), ENT_QUOTES, 'UTF-8'); ?></td>
          </tr>
          <tr>
            <th class="text-muted">Custo</th>
            <td><?= htmlspecialchars($formatMoney($product['cost_price'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></td>
          </tr>
          <tr>
            <th class="text-muted">Estoque</th>
            <td><?= (int) ($product['track_stock'] ?? 0) === 1 ? htmlspecialchars($formatQuantity($product['stock_quantity'] ?? 0) . ' ' . (string) ($product['sale_unit_symbol'] ?? ''), ENT_QUOTES, 'UTF-8') : 'Não controlado'; ?></td>
          </tr>
          <tr>
            <th class="text-muted">Estoque mínimo</th>
            <td><?= htmlspecialchars($formatQuantity($product['minimum_stock'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></td>
          </tr>
          <tr>
            <th class="text-muted">Status</th>
            <td><?= ((int) $product['active'] === 1) ? 'Ativo' : 'Inativo'; ?></td>
          </tr>
          <?php if ($product['length_value'] !== null || $product['width_value'] !== null || $product['height_value'] !== null): ?>
            <tr><th class="text-muted">Dimensões (C × L × A)</th><td><?= htmlspecialchars(implode(' × ', array_map(static fn ($value) => $value === null ? '—' : $formatQuantity($value), [$product['length_value'], $product['width_value'], $product['height_value']])) . ' ' . (string) ($product['dimension_unit_symbol'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td></tr>
          <?php endif; ?>
          <?php if ($product['weight_value'] !== null): ?>
            <tr><th class="text-muted">Peso</th><td><?= htmlspecialchars($formatQuantity($product['weight_value']) . ' ' . (string) ($product['weight_unit_symbol'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td></tr>
          <?php endif; ?>
          <tr>
            <th class="text-muted">Descrição</th>
            <td><div class="rich-editor-content"><?= (string) ($product['description'] ?? ''); ?></div></td>
          </tr>
          <tr>
            <th class="text-muted">Criado em</th>
            <td><?= htmlspecialchars($formatDateTime($product['created'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
          </tr>
          <tr>
            <th class="text-muted">Alterado em</th>
            <td><?= htmlspecialchars($formatDateTime($product['modified'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>

  <?php if (!empty($attributes)): ?>
    <section class="store-section">
      <div class="store-subheader"><h6>Características</h6></div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0"><tbody>
          <?php foreach ($attributes as $attribute): ?>
            <tr><th class="text-muted" style="width: 220px;"><?= htmlspecialchars((string) $attribute['name'], ENT_QUOTES, 'UTF-8'); ?></th><td><?= htmlspecialchars($attributeValue($attribute), ENT_QUOTES, 'UTF-8'); ?></td></tr>
          <?php endforeach; ?>
        </tbody></table>
      </div>
    </section>
  <?php endif; ?>

  <?php if (!empty($variations)): ?>
    <section class="store-section">
      <div class="store-subheader"><h6>Variações</h6></div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead>
            <tr>
              <th>Nome</th>
              <th>SKU</th>
              <th>Código de barras</th>
              <th>Preço</th>
              <th>Estoque</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($variations as $variation): ?>
              <tr>
                <td><?= htmlspecialchars((string) $variation['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?= htmlspecialchars((string) ($variation['sku'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?= htmlspecialchars((string) ($variation['barcode'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?= htmlspecialchars($formatMoney($variation['sale_price'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?= ((int) ($variation['track_stock'] ?? 0) === 1) ? htmlspecialchars($formatQuantity($variation['stock_quantity'] ?? 0), ENT_QUOTES, 'UTF-8') : 'Não controlado'; ?></td>
                <td><?= ((int) ($variation['active'] ?? 0) === 1) ? 'Ativa' : 'Inativa'; ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
  <?php endif; ?>

  <section class="store-section">
    <div class="store-subheader">
      <h6>Imagens</h6>
    </div>

    <?php if (!empty($images)): ?>
      <div class="product-image-grid">
        <?php foreach ($images as $image): ?>
          <a class="product-image-item" href="<?= url($image['file_path']); ?>" target="_blank" rel="noopener">
            <img src="<?= url($image['thumb_path'] ?: $image['file_path']); ?>" alt="<?= htmlspecialchars((string) ($image['original_name'] ?? $product['name']), ENT_QUOTES, 'UTF-8'); ?>">
          </a>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="text-muted mb-0">Nenhuma imagem cadastrada.</p>
    <?php endif; ?>
  </section>

  <?php if ((int) ($product['track_stock'] ?? 0) === 1): ?>
  <section class="store-section">
    <div class="store-subheader">
      <h6>Últimas movimentações</h6>
    </div>

    <div class="table-responsive">
      <table class="table table-striped table-hover align-middle">
        <thead>
          <tr>
            <th>#</th>
            <th>Tipo</th>
            <th>Quantidade</th>
            <th>Saldo anterior</th>
            <th>Novo saldo</th>
            <th>Data</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($stockMovements)): ?>
            <?php foreach ($stockMovements as $movement): ?>
              <tr>
                <td style="width:1%;"><?= (int) $movement['id']; ?></td>
                <td><?= htmlspecialchars($movementTypeLabel((string) $movement['movement_type']), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?= htmlspecialchars($formatQuantity($movement['quantity'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?= htmlspecialchars($formatQuantity($movement['previous_quantity'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?= htmlspecialchars($formatQuantity($movement['new_quantity'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?= htmlspecialchars($formatDateTime($movement['movement_date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="6" class="text-center">Nenhuma movimentação encontrada.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
  <?php endif; ?>

  <div class="form-actions">
    <a href="<?= url('product'); ?>" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
    <?php if (authCan('product.edit')): ?>
      <a href="<?= url('product/edit/' . $product['id']); ?>" class="btn btn-primary"><i class="bi bi-pencil-square"></i> Editar</a>
    <?php endif; ?>
  </div>
</div>

<script src="<?= url('assets/js/products/informations.js?v=1'); ?>"></script>
