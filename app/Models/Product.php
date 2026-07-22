<?php

namespace App\Models;

use App\Core\TenantModel;
use App\Services\FileUploadService;
use PDO;

class Product extends TenantModel
	{
	private function baseSelectSql(): string
		{
		$variationCountSelect = $this->tableExists('product_item_variations')
			? "(SELECT COUNT(*)
		         FROM product_item_variations AS Variation
		        WHERE Variation.product_item_id = Product.id) AS variation_count"
			: "0 AS variation_count";
		$variationStockSelect = $this->tableExists('product_item_variations')
			? ", (SELECT COUNT(*) FROM product_item_variations AS TrackedVariation
			          WHERE TrackedVariation.product_item_id = Product.id
			            AND TrackedVariation.track_stock = 1) AS tracked_variation_count,
			    (SELECT COALESCE(SUM(StockVariation.stock_quantity), 0)
			       FROM product_item_variations AS StockVariation
			      WHERE StockVariation.product_item_id = Product.id
			        AND StockVariation.track_stock = 1) AS variation_stock_quantity"
			: ", 0 AS tracked_variation_count, 0 AS variation_stock_quantity";
		return "SELECT Product.id,
		              Product.product_category_id,
		              Product.sku,
		              Product.barcode,
		              Product.name,
		              Product.description,
		              Product.cost_price,
		              Product.sale_price,
		              Product.sale_unit_id,
		              Product.sale_quantity_step,
		              Product.track_stock,
		              Product.stock_quantity,
		              Product.minimum_stock,
		              Product.length_value,
		              Product.width_value,
		              Product.height_value,
		              Product.dimension_unit_id,
		              Product.weight_value,
		              Product.weight_unit_id,
		              Product.active,
		              Product.created,
		              Product.modified,
			              {$variationCountSelect}
			              {$variationStockSelect},
		              ProductCategory.name AS product_category_name,
		              SaleUnit.code AS unit,
		              SaleUnit.name AS sale_unit_name,
		              SaleUnit.symbol AS sale_unit_symbol,
		              SaleUnit.decimal_places AS sale_unit_decimal_places,
		              DimensionUnit.symbol AS dimension_unit_symbol,
		              WeightUnit.symbol AS weight_unit_symbol,
		              ProductImage.file_path AS main_image_file_path,
		              ProductImage.file_path AS main_image_thumb_path
		       FROM product_items AS Product
		       INNER JOIN product_categories AS ProductCategory ON ProductCategory.id = Product.product_category_id
		       INNER JOIN product_units AS SaleUnit ON SaleUnit.id = Product.sale_unit_id
		       LEFT JOIN product_units AS DimensionUnit ON DimensionUnit.id = Product.dimension_unit_id
		       LEFT JOIN product_units AS WeightUnit ON WeightUnit.id = Product.weight_unit_id
		       LEFT JOIN product_item_images AS ProductImage ON ProductImage.product_item_id = Product.id
		                                                    AND ProductImage.is_main = 1";
		}

	public function create(array $data, array $files = []): array
		{
		$payload = $this->normalizePayload($data, '1');
		$initialStock = $payload['track_stock'] === 1 ? $this->normalizeDecimal($data['initial_stock'] ?? '0', 'Estoque inicial') : '0.000000';
		$fileUploadService = new FileUploadService();
		$storedImages = [];

		try
			{
			$this->db->beginTransaction();
			$stmt = $this->db->prepare("INSERT INTO product_items
				(product_category_id, sku, barcode, name, description, cost_price, sale_price,
				 sale_unit_id, sale_quantity_step, track_stock, stock_quantity, minimum_stock,
				 length_value, width_value, height_value, dimension_unit_id, weight_value,
				 weight_unit_id, active, created, modified)
				VALUES
				(:product_category_id, :sku, :barcode, :name, :description, :cost_price, :sale_price,
				 :sale_unit_id, :sale_quantity_step, :track_stock, 0, :minimum_stock,
				 :length_value, :width_value, :height_value, :dimension_unit_id, :weight_value,
				 :weight_unit_id, :active, NOW(), NOW())");
			$stmt->execute($payload);
			$productId = (int) $this->db->lastInsertId();

			$this->syncAttributeValues($productId, $payload['product_category_id'], $data['attributes'] ?? []);
			$variationMap = $this->syncVariations($productId, $data['variations'] ?? [], $payload, $data['attributes'] ?? []);
			$this->syncPriceRules($productId, $data['price_rules'] ?? [], $variationMap);
			if ((float) $initialStock > 0)
				{
				$this->insertStockMovement($productId, 'IN', $initialStock, '0.000000', $initialStock, 'Estoque inicial', null);
				$this->updateProductStock($productId, $initialStock);
				}

			if ($fileUploadService->hasUploads($files))
				{
				$storedImages = $fileUploadService->storeUploadedFiles('products', $files, $this->productImageUploadOptions($productId));
				$this->insertImageRows($productId, $storedImages);
				$this->ensureMainImage($productId);
				}

			$snapshot = $this->findAllByProductId($productId);
			$this->auditCreate('product', 'product_items', $productId, $snapshot, [
				'affected_tables' => ['product_items', 'product_item_attribute_values', 'product_item_variations',
					'product_item_variation_attribute_values', 'product_item_price_rules', 'product_item_images', 'product_stock_movements'],
			]);
			$this->db->commit();
			return ['product_id' => $productId];
			}
		catch (\Throwable $e)
			{
			if ($this->db->inTransaction()) $this->db->rollBack();
			$fileUploadService->deleteStoredFiles($storedImages);
			throw $e;
			}
		}

	public function find($id)
		{
		$stmt = $this->db->prepare($this->baseSelectSql() . " WHERE Product.id = :id");
		$stmt->execute(['id' => (int) $id]);
		return $stmt->fetch(PDO::FETCH_ASSOC);
		}

	public function update($id, array $data, array $files = []): void
		{
		$productId = (int) $id;
		$payload = $this->normalizePayload($data, '0');
		$fileUploadService = new FileUploadService();
		$storedImages = [];
		$deletedImages = [];

		try
			{
			$this->db->beginTransaction();
			$before = $this->findAllByProductId($productId);
			if (empty($before['product'])) throw new \InvalidArgumentException('Produto não encontrado.');

			$stmt = $this->db->prepare("UPDATE product_items SET
				product_category_id = :product_category_id, sku = :sku, barcode = :barcode,
				name = :name, description = :description, cost_price = :cost_price,
				sale_price = :sale_price, sale_unit_id = :sale_unit_id,
				sale_quantity_step = :sale_quantity_step, track_stock = :track_stock,
				minimum_stock = :minimum_stock, length_value = :length_value,
				width_value = :width_value, height_value = :height_value,
				dimension_unit_id = :dimension_unit_id, weight_value = :weight_value,
				weight_unit_id = :weight_unit_id, active = :active, modified = NOW()
				WHERE id = :id");
			$stmt->execute($payload + ['id' => $productId]);
			$this->syncAttributeValues($productId, $payload['product_category_id'], $data['attributes'] ?? []);
			$variationMap = $this->syncVariations($productId, $data['variations'] ?? [], $payload, $data['attributes'] ?? []);
			$this->syncPriceRules($productId, $data['price_rules'] ?? [], $variationMap);

			$removeIds = $this->normalizeIds($data['remove_images'] ?? []);
			if ($removeIds !== [])
				{
				$deletedImages = $this->findImagesByIds($productId, $removeIds);
				$this->deleteImageRows($productId, $removeIds);
				}
			if ($fileUploadService->hasUploads($files))
				{
				$storedImages = $fileUploadService->storeUploadedFiles('products', $files, $this->productImageUploadOptions($productId));
				$this->insertImageRows($productId, $storedImages);
				}
			$mainImageId = (int) ($data['main_image_id'] ?? 0);
			$this->syncImageVariations($productId, $data['image_variation_ids'] ?? []);
			if ($mainImageId > 0 && $this->imageBelongsToProduct($productId, $mainImageId)) $this->setMainImage($productId, $mainImageId);
			$this->ensureMainImage($productId);

			$after = $this->findAllByProductId($productId);
			$this->auditUpdate('product', 'product_items', $productId, $before, $after, [
				'affected_tables' => ['product_items', 'product_item_attribute_values', 'product_item_variations',
					'product_item_variation_attribute_values', 'product_item_price_rules', 'product_item_images'],
			]);
			$this->db->commit();
			}
		catch (\Throwable $e)
			{
			if ($this->db->inTransaction()) $this->db->rollBack();
			$fileUploadService->deleteStoredFiles($storedImages);
			throw $e;
			}
		$fileUploadService->deleteStoredFiles($deletedImages);
		}

	public function delete($id): void
		{
		$productId = (int) $id;
		if ($productId <= 0) throw new \InvalidArgumentException('Produto inválido.');
		$files = new FileUploadService();
		$images = [];
		try
			{
			$this->db->beginTransaction();
			$before = $this->findAllByProductId($productId);
			if (empty($before['product'])) throw new \InvalidArgumentException('Produto não encontrado.');
			if ($this->hasStockMovements($productId)) throw new \RuntimeException('Não é possível excluir o produto porque existem movimentações de estoque vinculadas.');
			$images = $this->getImages($productId);
			$stmt = $this->db->prepare('DELETE FROM product_items WHERE id = :id');
			$stmt->execute(['id' => $productId]);
			$this->auditDelete('product', 'product_items', $productId, $before, [
				'affected_tables' => ['product_items', 'product_item_attribute_values', 'product_item_variations',
					'product_item_variation_attribute_values', 'product_item_price_rules', 'product_item_images'],
			]);
			$this->db->commit();
			}
		catch (\Throwable $e)
			{
			if ($this->db->inTransaction()) $this->db->rollBack();
			throw $e;
			}
		$files->deleteStoredFiles($images);
		}

	public function search(array $filters = [], int $limit = 10, int $offset = 0): array
		{
		$sql = $this->baseSelectSql() . ' WHERE 1 = 1';
		$params = [];
		$this->appendSearchFilters($sql, $params, $filters);
		$sql .= ' ORDER BY Product.id DESC LIMIT :limit OFFSET :offset';
		$stmt = $this->db->prepare($sql);
		$this->bindSearchParams($stmt, $params);
		$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
		$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
		$stmt->execute();
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	public function count(array $filters = []): int
		{
		$sql = 'SELECT COUNT(*) FROM product_items AS Product WHERE 1 = 1';
		$params = [];
		$this->appendSearchFilters($sql, $params, $filters);
		$stmt = $this->db->prepare($sql);
		$this->bindSearchParams($stmt, $params);
		$stmt->execute();
		return (int) $stmt->fetchColumn();
		}

	public function getOptions(bool $activeOnly = true): array
		{
		$sql = "SELECT Product.id, Product.sku, Product.name, Product.stock_quantity,
		               Product.track_stock, Unit.code AS unit, Unit.symbol AS unit_symbol
		        FROM product_items AS Product
		        INNER JOIN product_units AS Unit ON Unit.id = Product.sale_unit_id
		        WHERE 1 = 1";
		if ($activeOnly) $sql .= ' AND Product.active = 1';
		$sql .= " AND (Product.track_stock = 1 OR EXISTS (
			SELECT 1 FROM product_item_variations AS Variation
			WHERE Variation.product_item_id = Product.id AND Variation.track_stock = 1" . ($activeOnly ? ' AND Variation.active = 1' : '') . '))';
		$sql .= ' ORDER BY Product.name ASC';
		$products = $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
		$variationStmt = $this->db->prepare("SELECT id, product_item_id, sku, name, stock_quantity, minimum_stock, active
			FROM product_item_variations WHERE product_item_id = :product_id AND track_stock = 1" . ($activeOnly ? ' AND active = 1' : '') . ' ORDER BY name');
		foreach ($products as &$product)
			{
			$variationStmt->execute(['product_id' => (int) $product['id']]);
			$product['variations'] = $variationStmt->fetchAll(PDO::FETCH_ASSOC);
			}
		unset($product);
		return $products;
		}

	public function findAllByProductId($id): array
		{
		$productId = (int) $id;
		return [
			'product' => $this->find($productId),
			'images' => $this->getImages($productId),
			'attributes' => $this->getAttributeValues($productId),
			'variations' => $this->getVariations($productId),
			'price_rules' => $this->getPriceRules($productId),
			'stock_movements' => $this->getStockMovements($productId),
		];
		}

	public function getImages(int $productId): array
		{
		$stmt = $this->db->prepare("SELECT ImageRow.id, ImageRow.product_item_id AS product_id,
			ImageRow.product_item_variation_id, ImageRow.original_name, ImageRow.file_path,
			ImageRow.file_path AS thumb_path, ImageRow.mime_type, ImageRow.file_size,
			ImageRow.sort_order AS position, ImageRow.is_main, ImageRow.created,
			Variation.name AS variation_name
			FROM product_item_images AS ImageRow
			LEFT JOIN product_item_variations AS Variation ON Variation.id = ImageRow.product_item_variation_id
			WHERE ImageRow.product_item_id = :id
			ORDER BY ImageRow.is_main DESC, ImageRow.sort_order, ImageRow.id");
		$stmt->execute(['id' => $productId]);
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	public function getVariations(int $productId): array
		{
		if (!$this->tableExists('product_item_variations')) return [];
		$stmt = $this->db->prepare("SELECT id, product_item_id, sku, barcode, name, combination_key,
			cost_price, sale_price, track_stock, stock_quantity, minimum_stock,
			active, created, modified,
			(SELECT COUNT(*) FROM product_stock_movements AS Movement
			 WHERE Movement.product_item_variation_id = Variation.id) AS movement_count,
			(SELECT COUNT(*) FROM product_item_images AS ImageRow
			 WHERE ImageRow.product_item_variation_id = Variation.id) AS image_count
			FROM product_item_variations AS Variation
			WHERE product_item_id = :id ORDER BY id");
		$stmt->execute(['id' => $productId]);
		$variations = $stmt->fetchAll(PDO::FETCH_ASSOC);
		$attributeMaps = (new ProductAttribute())->getVariationValueMaps($productId);
		foreach ($variations as &$variation) $variation['attributes'] = $attributeMaps[(int) $variation['id']] ?? [];
		unset($variation);
		return $variations;
		}

	public function getPriceRules(int $productId): array
		{
		if (!$this->tableExists('product_item_price_rules')) return [];
		$stmt = $this->db->prepare("SELECT PriceRule.id, PriceRule.product_item_id,
			PriceRule.product_item_variation_id, PriceRule.product_unit_id,
			PriceRule.minimum_quantity, PriceRule.maximum_quantity, PriceRule.sale_price,
			PriceRule.available, PriceRule.active, PriceRule.created, PriceRule.modified,
			Variation.name AS variation_name, Unit.name AS unit_name, Unit.symbol AS unit_symbol
			FROM product_item_price_rules AS PriceRule
			LEFT JOIN product_item_variations AS Variation ON Variation.id = PriceRule.product_item_variation_id
			INNER JOIN product_units AS Unit ON Unit.id = PriceRule.product_unit_id
			WHERE PriceRule.product_item_id = :id
			ORDER BY Variation.name, PriceRule.minimum_quantity, PriceRule.id");
		$stmt->execute(['id' => $productId]);
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	public function getAttributeValues(int $productId): array
		{
		$stmt = $this->db->prepare("SELECT AttributeValue.product_attribute_id, Attribute.code, Attribute.name,
			Attribute.data_type, Attribute.measurement_type, AttributeValue.product_attribute_option_id,
			AttributeValue.product_unit_id, AttributeValue.value_text, AttributeValue.value_integer, AttributeValue.value_decimal,
			AttributeValue.value_boolean, AttributeValue.value_date, OptionValue.label AS option_label,
			Unit.symbol AS unit_symbol
			FROM product_item_attribute_values AS AttributeValue
			INNER JOIN product_attributes AS Attribute ON Attribute.id = AttributeValue.product_attribute_id
			LEFT JOIN product_attribute_options AS OptionValue ON OptionValue.id = AttributeValue.product_attribute_option_id
			LEFT JOIN product_units AS Unit ON Unit.id = AttributeValue.product_unit_id
			WHERE AttributeValue.product_item_id = :id ORDER BY Attribute.name");
		$stmt->execute(['id' => $productId]);
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	public function getStockMovements(int $productId, int $limit = 10): array
		{
		$limit = max(1, min(50, $limit));
		$stmt = $this->db->prepare("SELECT Movement.id, Movement.product_item_id AS product_id,
			Movement.product_item_variation_id, Movement.movement_type, Movement.quantity,
			Movement.previous_quantity, Movement.new_quantity, Movement.reference,
			Movement.notes AS observations, Movement.movement_date, Movement.created,
			Variation.name AS variation_name
			FROM product_stock_movements AS Movement
			LEFT JOIN product_item_variations AS Variation ON Variation.id = Movement.product_item_variation_id
			WHERE Movement.product_item_id = :id ORDER BY Movement.id DESC LIMIT {$limit}");
		$stmt->execute(['id' => $productId]);
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	private function normalizePayload(array $data, string $defaultActive): array
		{
		$categoryId = (int) ($data['product_category_id'] ?? 0);
		if ($categoryId <= 0 || !$this->recordExists('product_categories', $categoryId, true)) throw new \InvalidArgumentException('Selecione uma categoria ativa.');
		$saleUnitId = $this->validUnitId($data['sale_unit_id'] ?? 0, null, true, 'Selecione a unidade de venda.');
		$name = trim((string) ($data['name'] ?? ''));
		if ($name === '') throw new \InvalidArgumentException('Informe o nome do produto.');

		$trackStock = isset($data['track_stock']) && (string) $data['track_stock'] === '1' ? 1 : 0;
		$dimensionValues = [
			$this->nullableDecimal($data['length_value'] ?? null, 'Comprimento'),
			$this->nullableDecimal($data['width_value'] ?? null, 'Largura'),
			$this->nullableDecimal($data['height_value'] ?? null, 'Altura'),
		];
		$hasDimension = count(array_filter($dimensionValues, static fn ($value) => $value !== null)) > 0;
		$dimensionUnitId = $this->validUnitId($data['dimension_unit_id'] ?? 0, 'LENGTH', $hasDimension, 'Selecione a unidade das dimensões.');
		$weight = $this->nullableDecimal($data['weight_value'] ?? null, 'Peso');
		$weightUnitId = $this->validUnitId($data['weight_unit_id'] ?? 0, 'WEIGHT', $weight !== null, 'Selecione a unidade de peso.');

		return [
			'product_category_id' => $categoryId,
			'sku' => $this->nullableString($data['sku'] ?? null, 60),
			'barcode' => $this->nullableString($data['barcode'] ?? null, 80),
			'name' => mb_substr($name, 0, 160),
			'description' => sanitizeRichText($data['description'] ?? null),
			'cost_price' => $this->normalizeDecimal($data['cost_price'] ?? '0', 'Preço de custo', 2),
			'sale_price' => $this->normalizeDecimal($data['sale_price'] ?? '0', 'Preço de venda', 2),
			'sale_unit_id' => $saleUnitId,
			'sale_quantity_step' => $this->positiveDecimal($data['sale_quantity_step'] ?? '1', 'Incremento de venda'),
			'track_stock' => $trackStock,
			'minimum_stock' => $trackStock ? $this->normalizeDecimal($data['minimum_stock'] ?? '0', 'Estoque mínimo') : '0.000000',
			'length_value' => $dimensionValues[0],
			'width_value' => $dimensionValues[1],
			'height_value' => $dimensionValues[2],
			'dimension_unit_id' => $dimensionUnitId,
			'weight_value' => $weight,
			'weight_unit_id' => $weightUnitId,
			'active' => in_array((string) ($data['active'] ?? $defaultActive), ['0', '1'], true) ? (int) ($data['active'] ?? $defaultActive) : (int) $defaultActive,
		];
		}

	private function syncAttributeValues(int $productId, int $categoryId, $submitted): void
		{
		$submitted = is_array($submitted) ? $submitted : [];
		$conditions = $this->loadAttributeConditions($categoryId);
		$stmt = $this->db->prepare("SELECT Link.product_attribute_id, Link.product_unit_id AS default_unit_id,
			Link.required, Link.variation_specific, Attribute.name, Attribute.data_type, Attribute.measurement_type
			FROM product_category_attributes AS Link
			INNER JOIN product_attributes AS Attribute ON Attribute.id = Link.product_attribute_id AND Attribute.active = 1
			WHERE Link.product_category_id = :category_id ORDER BY Link.sort_order, Link.id");
		$stmt->execute(['category_id' => $categoryId]);
		$configurations = $stmt->fetchAll(PDO::FETCH_ASSOC);

		$this->db->prepare('DELETE FROM product_item_attribute_values WHERE product_item_id = :id')->execute(['id' => $productId]);
		$insert = $this->db->prepare("INSERT INTO product_item_attribute_values
			(product_item_id, product_attribute_id, product_attribute_option_id, product_unit_id,
			 value_text, value_integer, value_decimal, value_boolean, value_date)
			VALUES (:product_item_id, :product_attribute_id, :option_id, :unit_id,
			 :value_text, :value_integer, :value_decimal, :value_boolean, :value_date)");

		foreach ($configurations as $configuration)
			{
			if ((int) ($configuration['variation_specific'] ?? 0) === 1) continue;
			$attributeId = (int) $configuration['product_attribute_id'];
			[$visible, $requiredByCondition] = $this->attributeConditionState($attributeId, $conditions[$attributeId] ?? [], $submitted, $configurations);
			if (!$visible) continue;
			$input = is_array($submitted[$attributeId] ?? null) ? $submitted[$attributeId] : ['value' => $submitted[$attributeId] ?? ''];
			$rawValue = $input['value'] ?? '';
			$isEmpty = $rawValue === '' || $rawValue === null;
			if ($isEmpty)
				{
				if ((int) $configuration['required'] === 1 || $requiredByCondition) throw new \InvalidArgumentException('Informe o atributo “' . $configuration['name'] . '”.');
				continue;
				}

			$values = ['value_text' => null, 'value_integer' => null, 'value_decimal' => null, 'value_boolean' => null, 'value_date' => null];
			$optionId = null;
			$type = strtoupper((string) $configuration['data_type']);
			if (in_array($type, ['OPTION', 'SELECT'], true))
				{
				$optionId = (int) $rawValue;
				$check = $this->db->prepare('SELECT 1 FROM product_attribute_options WHERE id = :id AND product_attribute_id = :attribute_id AND active = 1');
				$check->execute(['id' => $optionId, 'attribute_id' => $attributeId]);
				if (!$check->fetchColumn()) throw new \InvalidArgumentException('Opção inválida para “' . $configuration['name'] . '”.');
				}
			elseif (in_array($type, ['INTEGER', 'INT'], true)) $values['value_integer'] = (int) $rawValue;
			elseif (in_array($type, ['DECIMAL', 'NUMBER'], true)) $values['value_decimal'] = $this->normalizeDecimal($rawValue, (string) $configuration['name']);
			elseif (in_array($type, ['BOOLEAN', 'BOOL'], true)) $values['value_boolean'] = in_array((string) $rawValue, ['1', 'true', 'on'], true) ? 1 : 0;
			elseif ($type === 'DATE')
				{
				$date = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $rawValue);
				if (!$date || $date->format('Y-m-d') !== (string) $rawValue) throw new \InvalidArgumentException('Data inválida para “' . $configuration['name'] . '”.');
				$values['value_date'] = (string) $rawValue;
				}
			else $values['value_text'] = trim((string) $rawValue);

			$unitId = null;
			if (!empty($configuration['measurement_type']))
				{
				$unitId = $this->validUnitId($input['unit_id'] ?? $configuration['default_unit_id'] ?? 0, (string) $configuration['measurement_type'], true, 'Selecione a unidade de “' . $configuration['name'] . '”.');
				}
			$insert->execute($values + [
				'product_item_id' => $productId,
				'product_attribute_id' => $attributeId,
				'option_id' => $optionId,
				'unit_id' => $unitId,
			]);
			}
		}

	private function loadAttributeConditions(int $categoryId): array
		{
		if (!$this->tableExists('product_category_attribute_conditions')) return [];
		$stmt = $this->db->prepare("SELECT id, target_product_attribute_id, source_product_attribute_id,
			operator, value_text, source_product_attribute_option_id, action
			FROM product_category_attribute_conditions
			WHERE product_category_id = :category_id
			ORDER BY id");
		$stmt->execute(['category_id' => $categoryId]);
		$conditions = [];
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row)
			{
			$conditions[(int) $row['target_product_attribute_id']][] = $row;
			}
		return $conditions;
		}

	private function attributeConditionState(int $targetAttributeId, array $conditions, array $submitted, array $configurations): array
		{
		$visible = true;
		$required = false;
		foreach ($conditions as $condition)
			{
			$matches = $this->conditionMatches($condition, $submitted, $configurations);
			$action = strtoupper((string) ($condition['action'] ?? 'SHOW'));
			if ($action === 'SHOW' && !$matches) $visible = false;
			if ($action === 'REQUIRE' && $matches) $required = true;
			}
		return [$visible, $required];
		}

	private function conditionMatches(array $condition, array $submitted, array $configurations): bool
		{
		$sourceId = (int) ($condition['source_product_attribute_id'] ?? 0);
		if ($sourceId <= 0) return true;
		$input = is_array($submitted[$sourceId] ?? null) ? $submitted[$sourceId] : ['value' => $submitted[$sourceId] ?? ''];
		$rawValue = $input['value'] ?? '';
		$filled = !($rawValue === '' || $rawValue === null);
		$operator = strtoupper((string) ($condition['operator'] ?? 'EQUALS'));
		if ($operator === 'FILLED') return $filled;
		if ($operator === 'EMPTY') return !$filled;

		$expected = trim((string) ($condition['value_text'] ?? ''));
		$expectedOptionId = (int) ($condition['source_product_attribute_option_id'] ?? 0);
		$comparables = $this->submittedAttributeComparables($sourceId, $rawValue, $configurations);
		if ($expectedOptionId > 0) $comparables[] = (string) $expectedOptionId;
		$matches = false;
		foreach ($comparables as $comparable)
			{
			if ($this->sameComparableValue($comparable, $expected))
				{
				$matches = true;
				break;
				}
			}
		return $operator === 'NOT_EQUALS' ? !$matches : $matches;
		}

	private function submittedAttributeComparables(int $sourceId, $rawValue, array $configurations): array
		{
		$values = [(string) $rawValue];
		$type = 'TEXT';
		foreach ($configurations as $configuration)
			{
			if ((int) $configuration['product_attribute_id'] === $sourceId)
				{
				$type = strtoupper((string) $configuration['data_type']);
				break;
				}
			}
		if (in_array($type, ['OPTION', 'SELECT'], true) && (int) $rawValue > 0)
			{
			$stmt = $this->db->prepare('SELECT value, label FROM product_attribute_options WHERE id = :id AND product_attribute_id = :attribute_id');
			$stmt->execute(['id' => (int) $rawValue, 'attribute_id' => $sourceId]);
			$option = $stmt->fetch(PDO::FETCH_ASSOC);
			if ($option)
				{
				$values[] = (string) $option['value'];
				$values[] = (string) $option['label'];
				}
			}
		if (in_array($type, ['BOOLEAN', 'BOOL'], true))
			{
			$values[] = ((string) $rawValue === '1') ? 'sim' : 'não';
			$values[] = ((string) $rawValue === '1') ? 'yes' : 'no';
			}
		return $values;
		}

	private function sameComparableValue(string $left, string $right): bool
		{
		return mb_strtolower(trim($left)) === mb_strtolower(trim($right));
		}

	private function appendSearchFilters(string &$sql, array &$params, array $filters): void
		{
		if (($filters['id'] ?? '') !== '') { $sql .= ' AND Product.id = :id'; $params['id'] = (int) $filters['id']; }
		if (($filters['product_category_id'] ?? '') !== '') { $sql .= ' AND Product.product_category_id = :product_category_id'; $params['product_category_id'] = (int) $filters['product_category_id']; }
		if (($filters['sku'] ?? '') !== '') { $sql .= ' AND Product.sku LIKE :sku'; $params['sku'] = '%' . trim((string) $filters['sku']) . '%'; }
		if (($filters['name'] ?? '') !== '') { $sql .= ' AND Product.name LIKE :name'; $params['name'] = '%' . trim((string) $filters['name']) . '%'; }
		if (in_array((string) ($filters['active'] ?? ''), ['0', '1'], true)) { $sql .= ' AND Product.active = :active'; $params['active'] = (int) $filters['active']; }
		$status = (string) ($filters['stock_status'] ?? '');
		if ($status === 'OUT') $sql .= " AND ((Product.track_stock = 1 AND Product.stock_quantity <= 0)
			OR EXISTS (SELECT 1 FROM product_item_variations AS StockVariation
				WHERE StockVariation.product_item_id = Product.id AND StockVariation.track_stock = 1 AND StockVariation.stock_quantity <= 0))";
		elseif ($status === 'LOW') $sql .= " AND ((Product.track_stock = 1 AND Product.stock_quantity > 0 AND Product.stock_quantity <= Product.minimum_stock)
			OR EXISTS (SELECT 1 FROM product_item_variations AS StockVariation
				WHERE StockVariation.product_item_id = Product.id AND StockVariation.track_stock = 1
				AND StockVariation.stock_quantity > 0 AND StockVariation.stock_quantity <= StockVariation.minimum_stock))";
		elseif ($status === 'AVAILABLE') $sql .= " AND ((Product.track_stock = 1 AND Product.stock_quantity > 0)
			OR EXISTS (SELECT 1 FROM product_item_variations AS StockVariation
				WHERE StockVariation.product_item_id = Product.id AND StockVariation.track_stock = 1 AND StockVariation.stock_quantity > 0))";
		elseif ($status === 'NOT_TRACKED') $sql .= " AND Product.track_stock = 0
			AND NOT EXISTS (SELECT 1 FROM product_item_variations AS StockVariation
				WHERE StockVariation.product_item_id = Product.id AND StockVariation.track_stock = 1)";

		$categoryId = (int) ($filters['product_category_id'] ?? 0);
		$attributeFilters = is_array($filters['attribute_filters'] ?? null) ? $filters['attribute_filters'] : [];
		if ($categoryId > 0 && $attributeFilters !== [])
			{
			$configurations = $this->filterableAttributeConfigurations($categoryId);
			$position = 0;
			foreach ($attributeFilters as $attributeId => $input)
				{
				$attributeId = (int) $attributeId;
				if (!isset($configurations[$attributeId])) continue;
				$input = is_array($input) ? $input : ['value' => $input];
				$value = trim((string) ($input['value'] ?? ''));
				$minimum = trim((string) ($input['min'] ?? ''));
				$maximum = trim((string) ($input['max'] ?? ''));
				if ($value === '' && $minimum === '' && $maximum === '') continue;
				$alias = 'DynamicValue' . $position;
				$idParam = 'dynamic_attribute_id_' . $position;
				$isVariationSpecific = (int) ($configurations[$attributeId]['variation_specific'] ?? 0) === 1;
				if ($isVariationSpecific)
					{
					$variationAlias = 'DynamicVariation' . $position;
					$sql .= " AND EXISTS (SELECT 1 FROM product_item_variations AS {$variationAlias}
						INNER JOIN product_item_variation_attribute_values AS {$alias}
							ON {$alias}.product_item_variation_id = {$variationAlias}.id
						WHERE {$variationAlias}.product_item_id = Product.id
						  AND {$variationAlias}.active = 1
						  AND {$alias}.product_attribute_id = :{$idParam}";
					}
				else
					{
					$sql .= " AND EXISTS (SELECT 1 FROM product_item_attribute_values AS {$alias}
						WHERE {$alias}.product_item_id = Product.id AND {$alias}.product_attribute_id = :{$idParam}";
					}
				$params[$idParam] = $attributeId;
				$type = strtoupper((string) $configurations[$attributeId]['data_type']);
				if (in_array($type, ['OPTION', 'SELECT'], true))
					{
					$sql .= " AND {$alias}.product_attribute_option_id = :dynamic_value_{$position}";
					$params['dynamic_value_' . $position] = (int) $value;
					}
				elseif (in_array($type, ['BOOLEAN', 'BOOL'], true))
					{
					$sql .= " AND {$alias}.value_boolean = :dynamic_value_{$position}";
					$params['dynamic_value_' . $position] = (int) $value;
					}
				elseif (in_array($type, ['INTEGER', 'INT', 'DECIMAL', 'NUMBER'], true))
					{
					$column = in_array($type, ['INTEGER', 'INT'], true) ? 'value_integer' : 'value_decimal';
					if ($minimum !== '') { $sql .= " AND {$alias}.{$column} >= :dynamic_min_{$position}"; $params['dynamic_min_' . $position] = str_replace(',', '.', $minimum); }
					if ($maximum !== '') { $sql .= " AND {$alias}.{$column} <= :dynamic_max_{$position}"; $params['dynamic_max_' . $position] = str_replace(',', '.', $maximum); }
					if ($value !== '') { $sql .= " AND {$alias}.{$column} = :dynamic_value_{$position}"; $params['dynamic_value_' . $position] = str_replace(',', '.', $value); }
					}
				elseif ($type === 'DATE')
					{
					$sql .= " AND {$alias}.value_date = :dynamic_value_{$position}";
					$params['dynamic_value_' . $position] = $value;
					}
				else
					{
					$sql .= " AND {$alias}.value_text LIKE :dynamic_value_{$position}";
					$params['dynamic_value_' . $position] = '%' . $value . '%';
					}
				$sql .= ')';
				$position++;
				}
			}
		}

	private function filterableAttributeConfigurations(int $categoryId): array
		{
		$stmt = $this->db->prepare("SELECT Link.product_attribute_id, Link.variation_specific, Attribute.data_type
			FROM product_category_attributes AS Link
			INNER JOIN product_attributes AS Attribute ON Attribute.id = Link.product_attribute_id
			WHERE Link.product_category_id = :category_id AND Link.filterable = 1
				AND Attribute.active = 1");
		$stmt->execute(['category_id' => $categoryId]);
		$rows = [];
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $rows[(int) $row['product_attribute_id']] = $row;
		return $rows;
		}

	private function bindSearchParams(\PDOStatement $stmt, array $params): void
		{
		foreach ($params as $key => $value) $stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
		}

	private function recordExists(string $table, int $id, bool $active = false): bool
		{
		$sql = "SELECT 1 FROM {$table} WHERE id = :id" . ($active ? ' AND active = 1' : '') . ' LIMIT 1';
		$stmt = $this->db->prepare($sql);
		$stmt->execute(['id' => $id]);
		return $stmt->fetchColumn() !== false;
		}

	private function validUnitId($value, ?string $measurementType, bool $required, string $message): ?int
		{
		$id = (int) $value;
		if ($id <= 0)
			{
			if ($required) throw new \InvalidArgumentException($message);
			return null;
			}
		$sql = 'SELECT 1 FROM product_units WHERE id = :id AND active = 1';
		$params = ['id' => $id];
		if ($measurementType !== null && $measurementType !== '') { $sql .= ' AND measurement_type = :type'; $params['type'] = strtoupper($measurementType); }
		$stmt = $this->db->prepare($sql);
		$stmt->execute($params);
		if (!$stmt->fetchColumn()) throw new \InvalidArgumentException($message);
		return $id;
		}

	private function normalizeDecimal($value, string $label, int $scale = 6): string
		{
		$value = trim((string) $value);
		if ($value === '') $value = '0';
		if (str_contains($value, ',') && str_contains($value, '.')) $value = str_replace('.', '', $value);
		$value = str_replace(',', '.', $value);
		if (!is_numeric($value) || (float) $value < 0) throw new \InvalidArgumentException($label . ' deve ser um número igual ou maior que zero.');
		return number_format((float) $value, $scale, '.', '');
		}

	private function positiveDecimal($value, string $label): string
		{
		$normalized = $this->normalizeDecimal($value, $label);
		if ((float) $normalized <= 0) throw new \InvalidArgumentException($label . ' deve ser maior que zero.');
		return $normalized;
		}

	private function nullableDecimal($value, string $label): ?string
		{
		if ($value === null || trim((string) $value) === '') return null;
		return $this->normalizeDecimal($value, $label);
		}

	private function nullableString($value, int $length): ?string
		{
		$value = trim((string) $value);
		return $value === '' ? null : mb_substr($value, 0, $length);
		}

	private function normalizeIds($ids): array
		{
		if (!is_array($ids)) return [];
		return array_values(array_unique(array_filter(array_map('intval', $ids), static fn ($id) => $id > 0)));
		}

	private function syncVariations(int $productId, $submitted, array $parentPayload, $parentAttributes = []): array
		{
		$submitted = is_array($submitted) ? $submitted : [];
		$normalized = [];
		foreach ($submitted as $input)
			{
			if (!is_array($input)) continue;
			$variation = $this->normalizeVariation($input, $parentPayload);
			if ($variation !== null) $normalized[] = $variation;
			}

		if (!$this->tableExists('product_item_variations'))
			{
			if ($normalized !== []) throw new \RuntimeException('A tabela product_item_variations ainda não existe neste tenant.');
			return [];
			}

		$currentStmt = $this->db->prepare('SELECT id, stock_quantity FROM product_item_variations WHERE product_item_id = :product_id');
		$currentStmt->execute(['product_id' => $productId]);
		$currentRows = $currentStmt->fetchAll(PDO::FETCH_ASSOC);
		$currentIds = array_map(static fn (array $row): int => (int) $row['id'], $currentRows);
		$currentStock = [];
		foreach ($currentRows as $row) $currentStock[(int) $row['id']] = (string) $row['stock_quantity'];
		$keptIds = [];
		$variationMap = [];
		$combinationKeys = [];
		$insert = $this->db->prepare("INSERT INTO product_item_variations
			(product_item_id, sku, barcode, name, combination_key, cost_price, sale_price, track_stock,
			 stock_quantity, minimum_stock, active, created, modified)
			VALUES (:product_item_id, :sku, :barcode, :name, :combination_key, :cost_price, :sale_price, :track_stock,
			 0, :minimum_stock, :active, NOW(), NOW())");
		$update = $this->db->prepare("UPDATE product_item_variations SET
			sku = :sku, barcode = :barcode, name = :name, combination_key = :combination_key,
			cost_price = :cost_price, sale_price = :sale_price, track_stock = :track_stock,
			stock_quantity = :stock_quantity, minimum_stock = :minimum_stock, active = :active, modified = NOW()
			WHERE id = :id AND product_item_id = :product_item_id");

		foreach ($normalized as $variation)
			{
			$id = (int) $variation['id'];
			$attributes = $variation['_attributes'];
			$clientKey = $variation['_client_key'];
			$initialStock = $variation['stock_quantity'];
			unset($variation['id'], $variation['_attributes'], $variation['_client_key']);
			$combinationKey = $variation['combination_key'];
			if ($combinationKey !== null)
				{
				if (isset($combinationKeys[$combinationKey])) throw new \InvalidArgumentException('Existem variações com a mesma combinação de características.');
				$combinationKeys[$combinationKey] = true;
				}

			if ($id > 0 && in_array($id, $currentIds, true))
				{
				if ($this->variationHasStockMovements($id)) $variation['stock_quantity'] = $currentStock[$id] ?? '0.000000';
				$update->execute($variation + ['id' => $id, 'product_item_id' => $productId]);
				}
			else
				{
				unset($variation['stock_quantity']);
				$insert->execute($variation + ['product_item_id' => $productId]);
				$id = (int) $this->db->lastInsertId();
				if ((float) $initialStock > 0 && (int) $variation['track_stock'] === 1)
					{
					$this->insertStockMovement($productId, 'IN', $initialStock, '0.000000', $initialStock, 'Estoque inicial da variação', null, $id);
					$this->updateVariationStock($id, $initialStock);
					}
				}
			$this->syncVariationAttributeValues(
				$id,
				(int) $parentPayload['product_category_id'],
				$attributes,
				is_array($parentAttributes) ? $parentAttributes : []
			);
			$keptIds[] = $id;
			$variationMap[$clientKey] = $id;
			$variationMap[(string) $id] = $id;
			}

		$removeIds = array_values(array_diff($currentIds, $keptIds));
		if ($removeIds !== [])
			{
			$delete = $this->db->prepare('DELETE FROM product_item_variations WHERE id = :id AND product_item_id = :product_id');
			foreach ($removeIds as $id)
				{
				if ($this->variationHasStockMovements($id)) throw new \RuntimeException('Não é possível remover uma variação que possui movimentações de estoque.');
				$delete->execute(['id' => $id, 'product_id' => $productId]);
				}
			}
		return $variationMap;
		}

	private function normalizeVariation(array $input, array $parentPayload): ?array
		{
		$hasAnyValue = false;
		foreach (['name', 'sku', 'barcode', 'cost_price', 'sale_price', 'stock_quantity', 'minimum_stock'] as $field)
			{
			if (trim((string) ($input[$field] ?? '')) !== '') { $hasAnyValue = true; break; }
			}
		if (!$hasAnyValue) return null;

		$name = trim((string) ($input['name'] ?? ''));
		if ($name === '') throw new \InvalidArgumentException('Informe o nome da variação.');
		$trackStock = isset($input['track_stock']) && (string) $input['track_stock'] === '1' ? 1 : 0;
		$costPrice = trim((string) ($input['cost_price'] ?? '')) === '' ? $parentPayload['cost_price'] : $this->normalizeDecimal($input['cost_price'], 'Custo da variação', 2);
		$salePrice = trim((string) ($input['sale_price'] ?? '')) === '' ? $parentPayload['sale_price'] : $this->normalizeDecimal($input['sale_price'], 'Preço da variação', 2);
		$attributes = is_array($input['attributes'] ?? null) ? $input['attributes'] : [];
		return [
			'id' => (int) ($input['id'] ?? 0),
			'_client_key' => mb_substr(trim((string) ($input['client_key'] ?? '')) ?: ('variation_' . bin2hex(random_bytes(6))), 0, 80),
			'_attributes' => $attributes,
			'sku' => $this->nullableString($input['sku'] ?? null, 60),
			'barcode' => $this->nullableString($input['barcode'] ?? null, 80),
			'name' => mb_substr($name, 0, 160),
			'combination_key' => $this->variationCombinationKey($attributes),
			'cost_price' => $costPrice,
			'sale_price' => $salePrice,
			'track_stock' => $trackStock,
			'stock_quantity' => $trackStock ? $this->normalizeDecimal($input['stock_quantity'] ?? '0', 'Estoque da variação') : '0.000000',
			'minimum_stock' => $trackStock ? $this->normalizeDecimal($input['minimum_stock'] ?? '0', 'Estoque mínimo da variação') : '0.000000',
			'active' => in_array((string) ($input['active'] ?? '1'), ['0', '1'], true) ? (int) ($input['active'] ?? '1') : 1,
		];
		}

	private function variationCombinationKey(array $attributes): ?string
		{
		$parts = [];
		foreach ($attributes as $attributeId => $input)
			{
			$input = is_array($input) ? $input : ['value' => $input];
			$value = trim((string) ($input['value'] ?? ''));
			if ((int) $attributeId > 0 && $value !== '') $parts[(int) $attributeId] = $value . '@' . trim((string) ($input['unit_id'] ?? ''));
			}
		if ($parts === []) return null;
		ksort($parts, SORT_NUMERIC);
		return hash('sha256', json_encode($parts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
		}

	private function syncVariationAttributeValues(int $variationId, int $categoryId, array $submitted, array $parentSubmitted = []): void
		{
		if (!$this->tableExists('product_item_variation_attribute_values')) return;
		$stmt = $this->db->prepare("SELECT Link.product_attribute_id, Link.product_unit_id AS default_unit_id,
			Link.required, Link.variation_specific, Attribute.name, Attribute.data_type, Attribute.measurement_type
			FROM product_category_attributes AS Link
			INNER JOIN product_attributes AS Attribute ON Attribute.id = Link.product_attribute_id AND Attribute.active = 1
			WHERE Link.product_category_id = :category_id
			ORDER BY Link.sort_order, Link.id");
		$stmt->execute(['category_id' => $categoryId]);
		$allConfigurations = $stmt->fetchAll(PDO::FETCH_ASSOC);
		$configurations = array_values(array_filter($allConfigurations, static fn (array $configuration): bool => (int) ($configuration['variation_specific'] ?? 0) === 1));
		$conditionSubmitted = array_replace($parentSubmitted, $submitted);
		$conditions = $this->loadAttributeConditions($categoryId);
		$this->db->prepare('DELETE FROM product_item_variation_attribute_values WHERE product_item_variation_id = :id')->execute(['id' => $variationId]);
		$insert = $this->db->prepare("INSERT INTO product_item_variation_attribute_values
			(product_item_variation_id, product_attribute_id, product_attribute_option_id, product_unit_id,
			 value_text, value_integer, value_decimal, value_boolean, value_date)
			VALUES (:variation_id, :attribute_id, :option_id, :unit_id,
			 :value_text, :value_integer, :value_decimal, :value_boolean, :value_date)");

		foreach ($configurations as $configuration)
			{
			$attributeId = (int) $configuration['product_attribute_id'];
			[$visible, $requiredByCondition] = $this->attributeConditionState($attributeId, $conditions[$attributeId] ?? [], $conditionSubmitted, $allConfigurations);
			if (!$visible) continue;
			$input = is_array($submitted[$attributeId] ?? null) ? $submitted[$attributeId] : ['value' => $submitted[$attributeId] ?? ''];
			$rawValue = $input['value'] ?? '';
			if ($rawValue === '' || $rawValue === null)
				{
				if ((int) $configuration['required'] === 1 || $requiredByCondition) throw new \InvalidArgumentException('Informe “' . $configuration['name'] . '” em todas as variações.');
				continue;
				}
			[$values, $optionId] = $this->normalizeAttributeTypedValue($configuration, $rawValue);
			$unitId = null;
			if (!empty($configuration['measurement_type']))
				{
				$unitId = $this->validUnitId($input['unit_id'] ?? $configuration['default_unit_id'] ?? 0, (string) $configuration['measurement_type'], true, 'Selecione a unidade de “' . $configuration['name'] . '”.');
				}
			$insert->execute($values + [
				'variation_id' => $variationId,
				'attribute_id' => $attributeId,
				'option_id' => $optionId,
				'unit_id' => $unitId,
			]);
			}
		}

	private function normalizeAttributeTypedValue(array $configuration, $rawValue): array
		{
		$values = ['value_text' => null, 'value_integer' => null, 'value_decimal' => null, 'value_boolean' => null, 'value_date' => null];
		$optionId = null;
		$attributeId = (int) $configuration['product_attribute_id'];
		$type = strtoupper((string) $configuration['data_type']);
		if (in_array($type, ['OPTION', 'SELECT'], true))
			{
			$optionId = (int) $rawValue;
			$check = $this->db->prepare('SELECT 1 FROM product_attribute_options WHERE id = :id AND product_attribute_id = :attribute_id AND active = 1');
			$check->execute(['id' => $optionId, 'attribute_id' => $attributeId]);
			if (!$check->fetchColumn()) throw new \InvalidArgumentException('Opção inválida para “' . $configuration['name'] . '”.');
			}
		elseif (in_array($type, ['INTEGER', 'INT'], true)) $values['value_integer'] = (int) $rawValue;
		elseif (in_array($type, ['DECIMAL', 'NUMBER'], true)) $values['value_decimal'] = $this->normalizeDecimal($rawValue, (string) $configuration['name']);
		elseif (in_array($type, ['BOOLEAN', 'BOOL'], true)) $values['value_boolean'] = in_array((string) $rawValue, ['1', 'true', 'on'], true) ? 1 : 0;
		elseif ($type === 'DATE')
			{
			$date = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $rawValue);
			if (!$date || $date->format('Y-m-d') !== (string) $rawValue) throw new \InvalidArgumentException('Data inválida para “' . $configuration['name'] . '”.');
			$values['value_date'] = (string) $rawValue;
			}
		else $values['value_text'] = trim((string) $rawValue);
		return [$values, $optionId];
		}

	private function syncPriceRules(int $productId, $submitted, array $variationMap): void
		{
		$submitted = is_array($submitted) ? $submitted : [];
		if (!$this->tableExists('product_item_price_rules'))
			{
			if ($submitted !== []) throw new \RuntimeException('A tabela product_item_price_rules ainda não existe neste tenant.');
			return;
			}
		$this->db->prepare('DELETE FROM product_item_price_rules WHERE product_item_id = :id')->execute(['id' => $productId]);
		$insert = $this->db->prepare("INSERT INTO product_item_price_rules
			(product_item_id, product_item_variation_id, product_unit_id, minimum_quantity,
			 maximum_quantity, sale_price, available, active, created, modified)
			VALUES (:product_id, :variation_id, :unit_id, :minimum_quantity,
			 :maximum_quantity, :sale_price, :available, :active, NOW(), NOW())");
		$ranges = [];
		foreach ($submitted as $input)
			{
			if (!is_array($input)) continue;
			$minimumRaw = trim((string) ($input['minimum_quantity'] ?? ''));
			$priceRaw = trim((string) ($input['sale_price'] ?? ''));
			if ($minimumRaw === '' && $priceRaw === '') continue;
			$minimum = $this->positiveDecimal($minimumRaw === '' ? '1' : $minimumRaw, 'Quantidade mínima da regra de preço');
			$maximum = $this->nullableDecimal($input['maximum_quantity'] ?? null, 'Quantidade máxima da regra de preço');
			if ($maximum !== null && (float) $maximum < (float) $minimum) throw new \InvalidArgumentException('A quantidade máxima da regra de preço deve ser maior ou igual à mínima.');
			$target = trim((string) ($input['variation_key'] ?? ''));
			$variationId = $target === '' ? null : ($variationMap[$target] ?? null);
			if ($target !== '' && !$variationId) throw new \InvalidArgumentException('Selecione uma variação válida na regra de preço.');
			$unitId = $this->validUnitId($input['product_unit_id'] ?? 0, null, true, 'Selecione a unidade da regra de preço.');
			$rangeKey = (string) ($variationId ?? 0) . '|' . (string) $unitId;
			$minimumNumber = (float) $minimum;
			$maximumNumber = $maximum === null ? INF : (float) $maximum;
			foreach ($ranges[$rangeKey] ?? [] as $range)
				{
				if ($minimumNumber <= $range['maximum'] && $range['minimum'] <= $maximumNumber)
					{
					throw new \InvalidArgumentException('Existem faixas de preço sobrepostas para o mesmo produto/variação e unidade.');
					}
				}
			$ranges[$rangeKey][] = ['minimum' => $minimumNumber, 'maximum' => $maximumNumber];
			$insert->execute([
				'product_id' => $productId,
				'variation_id' => $variationId,
				'unit_id' => $unitId,
				'minimum_quantity' => $minimum,
				'maximum_quantity' => $maximum,
				'sale_price' => $this->normalizeDecimal($priceRaw, 'Preço da regra', 2),
				'available' => (string) ($input['available'] ?? '0') === '1' ? 1 : 0,
				'active' => (string) ($input['active'] ?? '1') === '1' ? 1 : 0,
			]);
			}
		}

	private function syncImageVariations(int $productId, $submitted): void
		{
		if (!is_array($submitted)) return;
		$update = $this->db->prepare("UPDATE product_item_images SET product_item_variation_id = :variation_id
			WHERE id = :image_id AND product_item_id = :product_id");
		foreach ($submitted as $imageId => $variationId)
			{
			$imageId = (int) $imageId;
			$variationId = (int) $variationId;
			if ($imageId <= 0) continue;
			if ($variationId > 0 && !$this->variationBelongsToProduct($productId, $variationId))
				{
				if ($this->recordExists('product_item_variations', $variationId)) throw new \InvalidArgumentException('A variação selecionada para uma imagem é inválida.');
				$variationId = 0;
				}
			$update->execute(['variation_id' => $variationId > 0 ? $variationId : null, 'image_id' => $imageId, 'product_id' => $productId]);
			}
		}

	private function variationBelongsToProduct(int $productId, int $variationId): bool
		{
		$stmt = $this->db->prepare('SELECT 1 FROM product_item_variations WHERE id = :id AND product_item_id = :product_id');
		$stmt->execute(['id' => $variationId, 'product_id' => $productId]);
		return $stmt->fetchColumn() !== false;
		}

	private function variationHasStockMovements(int $variationId): bool
		{
		$stmt = $this->db->prepare('SELECT 1 FROM product_stock_movements WHERE product_item_variation_id = :id LIMIT 1');
		$stmt->execute(['id' => $variationId]);
		return $stmt->fetchColumn() !== false;
		}

	private function updateVariationStock(int $variationId, string $quantity): void
		{
		$this->db->prepare('UPDATE product_item_variations SET stock_quantity = :quantity, modified = NOW() WHERE id = :id')
			->execute(['quantity' => $quantity, 'id' => $variationId]);
		}

	private function tableExists(string $table): bool
		{
		$stmt = $this->db->prepare('SHOW TABLES LIKE :table');
		$stmt->execute(['table' => $table]);
		return $stmt->fetchColumn() !== false;
		}

	private function productImageUploadOptions(int $productId): array
		{
		return [
			'directory_parts' => [$productId], 'file_prefix' => 'product_' . $productId,
			'allowed_mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
			'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp'], 'process_image' => true,
			'upload_error_message' => 'Não foi possível enviar uma das imagens.',
			'invalid_file_message' => 'Arquivo de imagem inválido.',
			'max_file_size_message' => 'Cada imagem deve ter no máximo 8 MB.',
			'mime_type_error_message' => 'Envie somente imagens JPG, PNG ou WebP.',
			'image_type_error_message' => 'Envie somente imagens JPG, PNG ou WebP.',
		];
		}

	private function insertImageRows(int $productId, array $images): void
		{
		if ($images === []) return;
		$position = $this->nextImagePosition($productId);
		$stmt = $this->db->prepare("INSERT INTO product_item_images
			(product_item_id, file_path, original_name, mime_type, file_size, sort_order, is_main, created)
			VALUES (:product_item_id, :file_path, :original_name, :mime_type, :file_size, :sort_order, 0, NOW())");
		foreach ($images as $image)
			{
			$stmt->execute([
				'product_item_id' => $productId,
				'file_path' => trim((string) ($image['file_path'] ?? '')),
				'original_name' => $this->nullableString($image['original_name'] ?? null, 180),
				'mime_type' => $this->nullableString($image['mime_type'] ?? null, 80),
				'file_size' => isset($image['file_size']) ? (int) $image['file_size'] : null,
				'sort_order' => $position++,
			]);
			}
		}

	private function insertStockMovement(int $productId, string $type, string $quantity, string $previous, string $new, ?string $reference, ?string $notes, ?int $variationId = null): void
		{
		$stmt = $this->db->prepare("INSERT INTO product_stock_movements
			(product_item_id, product_item_variation_id, movement_type, quantity, previous_quantity, new_quantity, reference, notes, movement_date, created)
			VALUES (:product_item_id, :variation_id, :movement_type, :quantity, :previous_quantity, :new_quantity, :reference, :notes, NOW(), NOW())");
		$stmt->execute(['product_item_id' => $productId, 'movement_type' => $type, 'quantity' => $quantity,
			'variation_id' => $variationId, 'previous_quantity' => $previous, 'new_quantity' => $new, 'reference' => $reference, 'notes' => $notes]);
		}

	private function updateProductStock(int $productId, string $quantity): void
		{
		$this->db->prepare('UPDATE product_items SET stock_quantity = :quantity, modified = NOW() WHERE id = :id')
			->execute(['quantity' => $quantity, 'id' => $productId]);
		}

	private function hasStockMovements(int $productId): bool
		{
		$stmt = $this->db->prepare('SELECT 1 FROM product_stock_movements WHERE product_item_id = :id LIMIT 1');
		$stmt->execute(['id' => $productId]);
		return $stmt->fetchColumn() !== false;
		}

	private function nextImagePosition(int $productId): int
		{
		$stmt = $this->db->prepare('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM product_item_images WHERE product_item_id = :id');
		$stmt->execute(['id' => $productId]);
		return (int) $stmt->fetchColumn();
		}

	private function findImagesByIds(int $productId, array $ids): array
		{
		$rows = [];
		$stmt = $this->db->prepare('SELECT id, file_path FROM product_item_images WHERE id = :id AND product_item_id = :product_id');
		foreach ($ids as $id) { $stmt->execute(['id' => $id, 'product_id' => $productId]); $row = $stmt->fetch(PDO::FETCH_ASSOC); if ($row) $rows[] = $row; }
		return $rows;
		}

	private function deleteImageRows(int $productId, array $ids): void
		{
		$stmt = $this->db->prepare('DELETE FROM product_item_images WHERE id = :id AND product_item_id = :product_id');
		foreach ($ids as $id) $stmt->execute(['id' => $id, 'product_id' => $productId]);
		}

	private function imageBelongsToProduct(int $productId, int $imageId): bool
		{
		$stmt = $this->db->prepare('SELECT 1 FROM product_item_images WHERE id = :id AND product_item_id = :product_id');
		$stmt->execute(['id' => $imageId, 'product_id' => $productId]);
		return $stmt->fetchColumn() !== false;
		}

	private function setMainImage(int $productId, int $imageId): void
		{
		$this->db->prepare('UPDATE product_item_images SET is_main = 0 WHERE product_item_id = :id')->execute(['id' => $productId]);
		$this->db->prepare('UPDATE product_item_images SET is_main = 1 WHERE id = :image_id AND product_item_id = :product_id')->execute(['image_id' => $imageId, 'product_id' => $productId]);
		}

	private function ensureMainImage(int $productId): void
		{
		$stmt = $this->db->prepare('SELECT id FROM product_item_images WHERE product_item_id = :id ORDER BY is_main DESC, sort_order, id LIMIT 1');
		$stmt->execute(['id' => $productId]);
		$imageId = (int) $stmt->fetchColumn();
		if ($imageId > 0) $this->setMainImage($productId, $imageId);
		}
	}
