<?php

namespace App\Models;

use App\Core\TenantModel;
use PDO;

class ProductAttribute extends TenantModel
	{
	public function getCategorySchema(int $categoryId): array
		{
		$stmt = $this->db->prepare("SELECT Link.id AS category_attribute_id,
			Link.product_category_id, Link.product_attribute_id, Link.product_unit_id,
			Link.required, Link.filterable, Link.show_on_list, Link.sort_order,
			Attribute.code, Attribute.name, UPPER(Attribute.data_type) AS data_type,
			UPPER(Attribute.measurement_type) AS measurement_type
			FROM product_category_attributes AS Link
			INNER JOIN product_attributes AS Attribute ON Attribute.id = Link.product_attribute_id
			WHERE Link.product_category_id = :category_id AND Attribute.active = 1
			ORDER BY Link.sort_order, Link.id");
		$stmt->execute(['category_id' => $categoryId]);
		$attributes = $stmt->fetchAll(PDO::FETCH_ASSOC);

		$optionStmt = $this->db->prepare("SELECT id, value, label, sort_order
			FROM product_attribute_options WHERE product_attribute_id = :attribute_id AND active = 1
			ORDER BY sort_order, label");
		$unitStmt = $this->db->prepare("SELECT id, code, name, symbol, measurement_type, decimal_places
			FROM product_units WHERE measurement_type = :measurement_type AND active = 1
			ORDER BY factor_to_base, name");
		foreach ($attributes as &$attribute)
			{
			$attribute['options'] = [];
			$attribute['units'] = [];
			$attribute['conditions'] = [];
			if (in_array((string) $attribute['data_type'], ['OPTION', 'SELECT'], true))
				{
				$optionStmt->execute(['attribute_id' => (int) $attribute['product_attribute_id']]);
				$attribute['options'] = $optionStmt->fetchAll(PDO::FETCH_ASSOC);
				}
			if (!empty($attribute['measurement_type']))
				{
				$unitStmt->execute(['measurement_type' => $attribute['measurement_type']]);
				$attribute['units'] = $unitStmt->fetchAll(PDO::FETCH_ASSOC);
				}
			}
		unset($attribute);

		$conditions = $this->getCategoryConditions($categoryId);
		foreach ($attributes as &$attribute)
			{
			$attributeId = (int) $attribute['product_attribute_id'];
			$attribute['conditions'] = $conditions[$attributeId] ?? [];
			}
		unset($attribute);
		return $attributes;
		}

	public function getItemValueMap(int $productItemId): array
		{
		$stmt = $this->db->prepare("SELECT product_attribute_id, product_attribute_option_id,
			product_unit_id, value_text, value_integer, value_decimal, value_boolean, value_date
			FROM product_item_attribute_values WHERE product_item_id = :id");
		$stmt->execute(['id' => $productItemId]);
		$values = [];
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $values[(int) $row['product_attribute_id']] = $row;
		return $values;
		}

	private function getCategoryConditions(int $categoryId): array
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
			$targetId = (int) $row['target_product_attribute_id'];
			$conditions[$targetId][] = $row;
			}
		return $conditions;
		}

	private function tableExists(string $table): bool
		{
		$stmt = $this->db->prepare('SHOW TABLES LIKE :table');
		$stmt->execute(['table' => $table]);
		return $stmt->fetchColumn() !== false;
		}
	}
