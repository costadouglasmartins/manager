<?php

namespace App\Models;

use App\Core\TenantModel;
use PDO;

class ProductCategory extends TenantModel
	{
	/**
	 * Returns the base product category select query.
	 */
	private function baseSelectSql(): string
		{
		return "SELECT ProductCategory.id,
		              ProductCategory.name,
		              ProductCategory.description,
		              ProductCategory.active,
		              ProductCategory.created,
		              ProductCategory.modified
		       FROM product_categories AS ProductCategory";
		}

	/**
	 * Creates a product category and returns the inserted identifier.
	 *
	 * @param array $data Data to save.
	 */
	public function create(array $data): array
		{
		$name = trim((string) ($data['name'] ?? ''));
		if ($name === '') throw new \InvalidArgumentException('Informe o nome da categoria.');
		try
			{
			$this->db->beginTransaction();

			$stmt = $this->db->prepare("
				INSERT INTO product_categories
				    (`name`, `description`, `active`, `created`, `modified`)
				VALUES
				    (:name, :description, :active, :created, :modified)
			");
			$stmt->execute([
				'name'        => mb_substr($name, 0, 120),
				'description' => sanitizeRichText($data['description'] ?? null),
				'active'      => ((string) ($data['active'] ?? '1') === '1') ? 1 : 0,
				'created'     => date('Y-m-d H:i:s'),
				'modified'    => date('Y-m-d H:i:s'),
			]);

			$categoryId = (int) $this->db->lastInsertId();
			$this->syncAttributes($categoryId, $data);
			$categorySnapshot = $this->find($categoryId);
			$this->auditCreate('product_category', 'product_categories', $categoryId, $categorySnapshot);
			$this->db->commit();

			return ['product_category_id' => $categoryId];
			}
		catch (\Exception $e)
			{
			if ($this->db->inTransaction())
				{
				$this->db->rollBack();
				}

			throw $e;
			}
		}

	/**
	 * Returns active categories ordered by name.
	 */
	public function getOptions(bool $activeOnly = true): array
		{
		$sql = $this->baseSelectSql() . "
			WHERE 1 = 1
		";

		if ($activeOnly)
			{
			$sql .= " AND ProductCategory.active = 1";
			}

		$sql .= "
			ORDER BY ProductCategory.name ASC
		";

		$stmt = $this->db->query($sql);
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Finds a product category by id.
	 *
	 * @param mixed $id Category identifier.
	 */
	public function find($id)
		{
		$stmt = $this->db->prepare($this->baseSelectSql() . "
			WHERE ProductCategory.id = :id
		");
		$stmt->execute(['id' => (int) $id]);

		return $stmt->fetch(PDO::FETCH_ASSOC);
		}

	/**
	 * Updates an existing product category.
	 *
	 * @param mixed $id Category identifier.
	 * @param array $data Updated category data.
	 */
	public function update($id, array $data): void
		{
		$categoryId = (int) $id;
		$name = trim((string) ($data['name'] ?? ''));
		if ($categoryId <= 0 || $name === '') throw new \InvalidArgumentException('Informe uma categoria válida e seu nome.');
		try
			{
			$this->db->beginTransaction();
			$beforeData = $this->findAllByProductCategoryId($categoryId);
			$stmt = $this->db->prepare("UPDATE product_categories SET
				name = :name, description = :description, active = :active, modified = NOW()
				WHERE id = :id");
			$stmt->execute([
				'name' => mb_substr($name, 0, 120),
				'description' => sanitizeRichText($data['description'] ?? null),
				'active' => ((string) ($data['active'] ?? '0') === '1') ? 1 : 0,
				'id' => $categoryId,
			]);
			$this->syncAttributes($categoryId, $data);
			$afterData = $this->findAllByProductCategoryId($categoryId);
			$this->auditUpdate('product_category', 'product_categories', $categoryId, $beforeData, $afterData, [
				'affected_tables' => ['product_categories', 'product_attributes', 'product_attribute_options', 'product_category_attributes'],
			]);
			$this->db->commit();
			}
		catch (\Throwable $e)
			{
			if ($this->db->inTransaction()) $this->db->rollBack();
			throw $e;
			}
		}

	/**
	 * Deletes an unused product category.
	 *
	 * @param mixed $id Category identifier.
	 */
	public function delete($id): void
		{
		$categoryId = (int) $id;
		if ($categoryId <= 0)
			{
			throw new \InvalidArgumentException('Categoria de produto inválida.');
			}

		try
			{
			$this->db->beginTransaction();

			$category = $this->find($categoryId);
			if (!$category)
				{
				throw new \InvalidArgumentException('Categoria de produto não encontrada.');
				}

			$linkedProducts = $this->db->prepare('SELECT COUNT(*) FROM product_items WHERE product_category_id = :id');
			$linkedProducts->execute(['id' => $categoryId]);
			if ((int) $linkedProducts->fetchColumn() > 0)
				{
				throw new \RuntimeException('Não é possível excluir a categoria porque existem produtos vinculados.');
				}

			$stmt = $this->db->prepare("DELETE FROM product_categories WHERE id = :id");
			$stmt->execute(['id' => $categoryId]);

			$this->auditDelete('product_category', 'product_categories', $categoryId, $category, [
				'affected_tables' => ['product_categories', 'product_category_attributes'],
			]);
			$this->db->commit();
			}
		catch (\Exception $e)
			{
			if ($this->db->inTransaction())
				{
				$this->db->rollBack();
				}

			throw $e;
			}
		}

	/**
	 * Searches product categories using filters and pagination.
	 */
	public function search(array $filters = [], int $limit = 10, int $offset = 0): array
		{
		$sql = $this->baseSelectSql() . "
			WHERE 1 = 1
		";
		$params = [];

		$this->appendSearchFilters($sql, $params, $filters);

		$sql .= "
			ORDER BY ProductCategory.id DESC
			LIMIT :limit OFFSET :offset
		";

		$stmt = $this->db->prepare($sql);
		$this->bindSearchParams($stmt, $params);
		$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
		$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
		$stmt->execute();

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Counts product categories matching filters.
	 */
	public function count(array $filters = []): int
		{
		$sql = "SELECT COUNT(*) AS total
		        FROM product_categories AS ProductCategory
		        WHERE 1 = 1";
		$params = [];

		$this->appendSearchFilters($sql, $params, $filters);

		$stmt = $this->db->prepare($sql);
		$this->bindSearchParams($stmt, $params);
		$stmt->execute();

		return (int) $stmt->fetchColumn();
		}

	/**
	 * Returns the category details payload used by the information view.
	 *
	 * @param mixed $id Category identifier.
	 */
	public function findAllByProductCategoryId($id): array
		{
		return ['product_category' => $this->find($id), 'attributes' => $this->getAttributes((int) $id)];
		}

	/** Returns attributes configured for a category. */
	public function getAttributes(int $categoryId): array
		{
		$conditionSelect = '';
		$conditionJoin = '';
		$conditionGroup = '';
		if ($this->tableExists('product_category_attribute_conditions'))
			{
			$conditionSelect = ",
			ShowCondition.source_product_attribute_id AS condition_source_attribute_id,
			ShowCondition.operator AS condition_operator,
			ShowCondition.value_text AS condition_value,
			ShowCondition.source_product_attribute_option_id AS condition_option_id,
			CASE WHEN RequireCondition.id IS NULL THEN 0 ELSE 1 END AS required_when_matched";
			$conditionJoin = "
			LEFT JOIN product_category_attribute_conditions AS ShowCondition
			       ON ShowCondition.product_category_id = Link.product_category_id
			      AND ShowCondition.target_product_attribute_id = Link.product_attribute_id
			      AND ShowCondition.action = 'SHOW'
			LEFT JOIN product_category_attribute_conditions AS RequireCondition
			       ON RequireCondition.product_category_id = Link.product_category_id
			      AND RequireCondition.target_product_attribute_id = Link.product_attribute_id
			      AND RequireCondition.action = 'REQUIRE'";
			$conditionGroup = ", ShowCondition.source_product_attribute_id, ShowCondition.operator,
			         ShowCondition.value_text, ShowCondition.source_product_attribute_option_id,
			         RequireCondition.id";
			}
		$stmt = $this->db->prepare("SELECT Link.product_attribute_id, Link.product_unit_id,
			Link.required, Link.filterable, Link.show_on_list, Link.sort_order,
			Attribute.code, Attribute.name, Attribute.data_type, Attribute.measurement_type,
			GROUP_CONCAT(OptionValue.label ORDER BY OptionValue.sort_order, OptionValue.id SEPARATOR '\n') AS options_text
			{$conditionSelect}
			FROM product_category_attributes AS Link
			INNER JOIN product_attributes AS Attribute ON Attribute.id = Link.product_attribute_id
			LEFT JOIN product_attribute_options AS OptionValue ON OptionValue.product_attribute_id = Attribute.id AND OptionValue.active = 1
			{$conditionJoin}
			WHERE Link.product_category_id = :category_id
			GROUP BY Link.product_attribute_id, Link.product_unit_id, Link.required, Link.filterable,
			         Link.show_on_list, Link.sort_order, Attribute.code, Attribute.name,
			         Attribute.data_type, Attribute.measurement_type
			         {$conditionGroup}
			ORDER BY Link.sort_order, Attribute.name");
		$stmt->execute(['category_id' => $categoryId]);
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/** Synchronizes existing links and creates simple category-specific attributes. */
	private function syncAttributes(int $categoryId, array $data): void
		{
		$existing = is_array($data['attributes'] ?? null) ? $data['attributes'] : [];
		$currentStmt = $this->db->prepare('SELECT product_attribute_id FROM product_category_attributes WHERE product_category_id = :id');
		$currentStmt->execute(['id' => $categoryId]);
		$currentIds = array_map('intval', $currentStmt->fetchAll(PDO::FETCH_COLUMN));
		$update = $this->db->prepare("UPDATE product_category_attributes SET required = :required,
			filterable = :filterable, show_on_list = :show_on_list, sort_order = :sort_order
			WHERE product_category_id = :category_id AND product_attribute_id = :attribute_id");
		$delete = $this->db->prepare('DELETE FROM product_category_attributes WHERE product_category_id = :category_id AND product_attribute_id = :attribute_id');
		foreach ($currentIds as $attributeId)
			{
			$input = is_array($existing[$attributeId] ?? null) ? $existing[$attributeId] : [];
			if ((string) ($input['enabled'] ?? '0') !== '1')
				{
				if ($this->tableExists('product_category_attribute_conditions'))
					{
					$this->db->prepare("DELETE FROM product_category_attribute_conditions
						WHERE product_category_id = :category_id
						  AND (target_product_attribute_id = :attribute_id OR source_product_attribute_id = :attribute_id)")
						->execute(['category_id' => $categoryId, 'attribute_id' => $attributeId]);
					}
				$delete->execute(['category_id' => $categoryId, 'attribute_id' => $attributeId]);
				continue;
				}
			$update->execute([
				'required' => (string) ($input['required'] ?? '0') === '1' ? 1 : 0,
				'filterable' => (string) ($input['filterable'] ?? '0') === '1' ? 1 : 0,
				'show_on_list' => (string) ($input['show_on_list'] ?? '0') === '1' ? 1 : 0,
				'sort_order' => max(0, (int) ($input['sort_order'] ?? 0)),
				'category_id' => $categoryId,
				'attribute_id' => $attributeId,
			]);
			$this->syncAttributeCondition($categoryId, $attributeId, $input);
			}

		$newAttributes = is_array($data['new_attributes'] ?? null) ? $data['new_attributes'] : [];
		$sortOrder = count($currentIds);
		foreach ($newAttributes as $index => $input)
			{
			if (!is_array($input)) continue;
			$name = trim((string) ($input['name'] ?? ''));
			if ($name === '') continue;
			$type = strtoupper(trim((string) ($input['data_type'] ?? 'TEXT')));
			if (!in_array($type, ['TEXT', 'INTEGER', 'DECIMAL', 'BOOLEAN', 'DATE', 'OPTION'], true)) throw new \InvalidArgumentException('Tipo inválido para a característica “' . $name . '”.');
			$measurement = strtoupper(trim((string) ($input['measurement_type'] ?? '')));
			if (!in_array($measurement, ['LENGTH', 'WEIGHT'], true)) $measurement = null;
			if (!in_array($type, ['INTEGER', 'DECIMAL'], true)) $measurement = null;

			$code = $this->uniqueAttributeCode($categoryId, $name, (int) $index);
			$attributeStmt = $this->db->prepare("INSERT INTO product_attributes
				(code, name, data_type, measurement_type, active, created, modified)
				VALUES (:code, :name, :data_type, :measurement_type, 1, NOW(), NOW())");
			$attributeStmt->execute(['code' => $code, 'name' => mb_substr($name, 0, 120), 'data_type' => $type, 'measurement_type' => $measurement]);
			$attributeId = (int) $this->db->lastInsertId();
			$unitId = $this->defaultUnitId($measurement);
			$this->db->prepare("INSERT INTO product_category_attributes
				(product_category_id, product_attribute_id, product_unit_id, required, filterable, show_on_list, sort_order)
				VALUES (:category_id, :attribute_id, :unit_id, :required, :filterable, :show_on_list, :sort_order)")
				->execute([
					'category_id' => $categoryId, 'attribute_id' => $attributeId, 'unit_id' => $unitId,
					'required' => (string) ($input['required'] ?? '0') === '1' ? 1 : 0,
					'filterable' => (string) ($input['filterable'] ?? '0') === '1' ? 1 : 0,
					'show_on_list' => (string) ($input['show_on_list'] ?? '0') === '1' ? 1 : 0,
					'sort_order' => $sortOrder++,
				]);

			if ($type === 'OPTION') $this->insertAttributeOptions($attributeId, $name, (string) ($input['options'] ?? ''));
			}
		}

	private function syncAttributeCondition(int $categoryId, int $targetAttributeId, array $input): void
		{
		if (!$this->tableExists('product_category_attribute_conditions'))
			{
			$hasCondition = (int) ($input['condition_source_attribute_id'] ?? 0) > 0;
			if ($hasCondition) throw new \RuntimeException('A tabela product_category_attribute_conditions ainda não existe neste tenant.');
			return;
			}

		$this->db->prepare("DELETE FROM product_category_attribute_conditions
			WHERE product_category_id = :category_id AND target_product_attribute_id = :target_id")
			->execute(['category_id' => $categoryId, 'target_id' => $targetAttributeId]);

		$sourceAttributeId = (int) ($input['condition_source_attribute_id'] ?? 0);
		if ($sourceAttributeId <= 0 || $sourceAttributeId === $targetAttributeId) return;
		if (!$this->categoryHasAttribute($categoryId, $sourceAttributeId)) throw new \InvalidArgumentException('A característica usada na condição não pertence à categoria.');

		$operator = strtoupper(trim((string) ($input['condition_operator'] ?? 'EQUALS')));
		if (!in_array($operator, ['EQUALS', 'NOT_EQUALS', 'FILLED', 'EMPTY'], true)) $operator = 'EQUALS';
		$value = trim((string) ($input['condition_value'] ?? ''));
		if (in_array($operator, ['EQUALS', 'NOT_EQUALS'], true) && $value === '')
			{
			throw new \InvalidArgumentException('Informe o valor da condição.');
			}

		$insert = $this->db->prepare("INSERT INTO product_category_attribute_conditions
			(product_category_id, target_product_attribute_id, source_product_attribute_id,
			 operator, value_text, source_product_attribute_option_id, action, created, modified)
			VALUES (:category_id, :target_id, :source_id, :operator, :value_text, NULL, :action, NOW(), NOW())");
		$base = [
			'category_id' => $categoryId,
			'target_id' => $targetAttributeId,
			'source_id' => $sourceAttributeId,
			'operator' => $operator,
			'value_text' => in_array($operator, ['FILLED', 'EMPTY'], true) ? null : mb_substr($value, 0, 190),
		];
		$insert->execute($base + ['action' => 'SHOW']);
		if ((string) ($input['required_when_matched'] ?? '0') === '1')
			{
			$insert->execute($base + ['action' => 'REQUIRE']);
			}
		}

	private function categoryHasAttribute(int $categoryId, int $attributeId): bool
		{
		$stmt = $this->db->prepare('SELECT 1 FROM product_category_attributes WHERE product_category_id = :category_id AND product_attribute_id = :attribute_id');
		$stmt->execute(['category_id' => $categoryId, 'attribute_id' => $attributeId]);
		return $stmt->fetchColumn() !== false;
		}

	private function insertAttributeOptions(int $attributeId, string $name, string $optionsText): void
		{
		$options = array_values(array_unique(array_filter(array_map('trim', preg_split('/[\r\n,;]+/', $optionsText) ?: []))));
		if ($options === []) throw new \InvalidArgumentException('Informe as opções da característica “' . $name . '”.');
		$stmt = $this->db->prepare("INSERT INTO product_attribute_options
			(product_attribute_id, value, label, sort_order, active)
			VALUES (:attribute_id, :value, :label, :sort_order, 1)");
		foreach ($options as $position => $label)
			{
			$value = mb_strtolower($this->normalizeCode($label));
			$stmt->execute(['attribute_id' => $attributeId, 'value' => mb_substr($value, 0, 120), 'label' => mb_substr($label, 0, 120), 'sort_order' => $position]);
			}
		}

	private function defaultUnitId(?string $measurement): ?int
		{
		if ($measurement === null) return null;
		$stmt = $this->db->prepare('SELECT id FROM product_units WHERE measurement_type = :type AND active = 1 ORDER BY factor_to_base LIMIT 1');
		$stmt->execute(['type' => $measurement]);
		$id = (int) $stmt->fetchColumn();
		return $id > 0 ? $id : null;
		}

	private function uniqueAttributeCode(int $categoryId, string $name, int $index): string
		{
		$base = 'category_' . $categoryId . '_' . $this->normalizeCode($name);
		$base = trim(mb_substr($base, 0, 70), '_');
		$code = $base;
		$attempt = max(1, $index + 1);
		$stmt = $this->db->prepare('SELECT 1 FROM product_attributes WHERE code = :code');
		while (true)
			{
			$stmt->execute(['code' => $code]);
			if (!$stmt->fetchColumn()) return $code;
			$code = mb_substr($base, 0, 70) . '_' . $attempt++;
			}
		}

	private function normalizeCode(string $value): string
		{
		$transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
		$value = strtolower($transliterated !== false ? $transliterated : $value);
		$value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? '';
		return trim($value, '_') ?: 'attribute';
		}

	private function tableExists(string $table): bool
		{
		$stmt = $this->db->prepare('SHOW TABLES LIKE :table');
		$stmt->execute(['table' => $table]);
		return $stmt->fetchColumn() !== false;
		}

	/**
	 * Appends category search filters to a SQL query.
	 */
	private function appendSearchFilters(string &$sql, array &$params, array $filters): void
		{
		if (isset($filters['id']) && $filters['id'] !== '')
			{
			$sql .= " AND ProductCategory.id = :id";
			$params['id'] = (int) $filters['id'];
			}

		if (isset($filters['name']) && $filters['name'] !== '')
			{
			$sql .= " AND ProductCategory.`name` LIKE :name";
			$params['name'] = '%' . $filters['name'] . '%';
			}

		if (isset($filters['active']) && in_array((string) $filters['active'], ['0', '1'], true))
			{
			$sql .= " AND ProductCategory.active = :active";
			$params['active'] = (int) $filters['active'];
			}
		}

	/**
	 * Binds search params to a prepared statement.
	 */
	private function bindSearchParams(\PDOStatement $stmt, array $params): void
		{
		foreach ($params as $key => $value)
			{
			$paramType = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
			$stmt->bindValue(':' . $key, $value, $paramType);
			}
		}
	}
?>
