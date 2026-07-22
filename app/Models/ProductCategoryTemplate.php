<?php

namespace App\Models;

use App\Core\TenantModel;
use PDO;

class ProductCategoryTemplate extends TenantModel
	{
	public function getOptions(bool $activeOnly = true): array
		{
		$sql = 'SELECT id, name, description, active, created, modified FROM product_category_templates WHERE 1 = 1';
		if ($activeOnly) $sql .= ' AND active = 1';
		$sql .= ' ORDER BY name';
		return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
		}

	public function saveFromCategory(int $categoryId, string $name, ?string $description, int $userId = 0): int
		{
		$name = trim($name);
		if ($categoryId <= 0 || $name === '') throw new \InvalidArgumentException('Informe um nome para o modelo de categoria.');
		$schema = $this->categorySchema($categoryId);
		if ($schema['attributes'] === []) throw new \RuntimeException('Adicione ao menos uma característica antes de salvar o modelo.');

		$stmt = $this->db->prepare("INSERT INTO product_category_templates
			(name, description, schema_json, active, created_by_user_id, created, modified)
			VALUES (:name, :description, :schema_json, 1, :user_id, NOW(), NOW())
			ON DUPLICATE KEY UPDATE description = VALUES(description), schema_json = VALUES(schema_json),
				active = 1, created_by_user_id = VALUES(created_by_user_id), modified = NOW()") ;
		$stmt->execute([
			'name' => mb_substr($name, 0, 120),
			'description' => $description === null || trim($description) === '' ? null : mb_substr(trim($description), 0, 255),
			'schema_json' => json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
			'user_id' => $userId > 0 ? $userId : null,
		]);
		$id = (int) $this->db->lastInsertId();
		if ($id > 0) return $id;
		$find = $this->db->prepare('SELECT id FROM product_category_templates WHERE name = :name');
		$find->execute(['name' => mb_substr($name, 0, 120)]);
		return (int) $find->fetchColumn();
		}

	public function applyToCategory(int $templateId, int $categoryId): void
		{
		if ($templateId <= 0 || $categoryId <= 0) return;
		$stmt = $this->db->prepare('SELECT schema_json FROM product_category_templates WHERE id = :id AND active = 1');
		$stmt->execute(['id' => $templateId]);
		$json = $stmt->fetchColumn();
		if (!is_string($json) || $json === '') throw new \InvalidArgumentException('Modelo de categoria não encontrado.');
		$schema = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
		$attributes = is_array($schema['attributes'] ?? null) ? $schema['attributes'] : [];
		$idByCode = [];

		$insertAttribute = $this->db->prepare("INSERT INTO product_attributes
			(code, name, data_type, measurement_type, active, created, modified)
			VALUES (:code, :name, :data_type, :measurement_type, 1, NOW(), NOW())");
		$insertLink = $this->db->prepare("INSERT INTO product_category_attributes
			(product_category_id, product_attribute_id, product_unit_id, required, filterable,
			 show_on_list, variation_axis, variation_specific, sort_order)
			VALUES (:category_id, :attribute_id, :unit_id, :required, :filterable,
			 :show_on_list, :variation_axis, :variation_specific, :sort_order)");

		foreach ($attributes as $position => $attribute)
			{
			if (!is_array($attribute)) continue;
			$name = trim((string) ($attribute['name'] ?? ''));
			if ($name === '') continue;
			$sourceCode = (string) ($attribute['code'] ?? ('attribute_' . $position));
			$code = $this->uniqueCode($categoryId, $sourceCode, $name);
			$type = strtoupper((string) ($attribute['data_type'] ?? 'TEXT'));
			$measurement = strtoupper((string) ($attribute['measurement_type'] ?? ''));
			if (!in_array($measurement, ['LENGTH', 'WEIGHT'], true)) $measurement = null;
			$insertAttribute->execute([
				'code' => $code,
				'name' => mb_substr($name, 0, 120),
				'data_type' => $type,
				'measurement_type' => $measurement,
			]);
			$attributeId = (int) $this->db->lastInsertId();
			$idByCode[$sourceCode] = $attributeId;
			$insertLink->execute([
				'category_id' => $categoryId,
				'attribute_id' => $attributeId,
				'unit_id' => $this->unitIdByCode((string) ($attribute['unit_code'] ?? '')),
				'required' => !empty($attribute['required']) ? 1 : 0,
				'filterable' => !empty($attribute['filterable']) ? 1 : 0,
				'show_on_list' => !empty($attribute['show_on_list']) ? 1 : 0,
				'variation_axis' => !empty($attribute['variation_axis']) ? 1 : 0,
				'variation_specific' => (!empty($attribute['variation_specific']) || !empty($attribute['variation_axis'])) ? 1 : 0,
				'sort_order' => max(0, (int) ($attribute['sort_order'] ?? $position)),
			]);
			$this->insertOptions($attributeId, is_array($attribute['options'] ?? null) ? $attribute['options'] : []);
			}

		$insertCondition = $this->db->prepare("INSERT INTO product_category_attribute_conditions
			(product_category_id, target_product_attribute_id, source_product_attribute_id,
			 operator, value_text, source_product_attribute_option_id, action, created, modified)
			VALUES (:category_id, :target_id, :source_id, :operator, :value_text, NULL, :action, NOW(), NOW())");
		foreach (is_array($schema['conditions'] ?? null) ? $schema['conditions'] : [] as $condition)
			{
			if (!is_array($condition)) continue;
			$targetId = $idByCode[(string) ($condition['target_code'] ?? '')] ?? 0;
			$sourceId = $idByCode[(string) ($condition['source_code'] ?? '')] ?? 0;
			if ($targetId <= 0 || $sourceId <= 0) continue;
			$insertCondition->execute([
				'category_id' => $categoryId,
				'target_id' => $targetId,
				'source_id' => $sourceId,
				'operator' => (string) ($condition['operator'] ?? 'EQUALS'),
				'value_text' => $condition['value_text'] ?? null,
				'action' => (string) ($condition['action'] ?? 'SHOW'),
			]);
			}
		}

	private function categorySchema(int $categoryId): array
		{
		$stmt = $this->db->prepare("SELECT Link.product_attribute_id, Link.product_unit_id,
			Link.required, Link.filterable, Link.show_on_list, Link.variation_axis,
			Link.variation_specific, Link.sort_order, Attribute.code, Attribute.name,
			Attribute.data_type, Attribute.measurement_type, Unit.code AS unit_code
			FROM product_category_attributes AS Link
			INNER JOIN product_attributes AS Attribute ON Attribute.id = Link.product_attribute_id
			LEFT JOIN product_units AS Unit ON Unit.id = Link.product_unit_id
			WHERE Link.product_category_id = :category_id
			ORDER BY Link.sort_order, Link.id");
		$stmt->execute(['category_id' => $categoryId]);
		$attributes = $stmt->fetchAll(PDO::FETCH_ASSOC);
		$options = $this->db->prepare("SELECT value, label, sort_order FROM product_attribute_options
			WHERE product_attribute_id = :attribute_id AND active = 1 ORDER BY sort_order, id");
		foreach ($attributes as &$attribute)
			{
			$options->execute(['attribute_id' => (int) $attribute['product_attribute_id']]);
			$attribute['options'] = $options->fetchAll(PDO::FETCH_ASSOC);
			}
		unset($attribute);

		$conditionsStmt = $this->db->prepare("SELECT TargetAttribute.code AS target_code,
			SourceAttribute.code AS source_code, ConditionRow.operator, ConditionRow.value_text,
			ConditionRow.action
			FROM product_category_attribute_conditions AS ConditionRow
			INNER JOIN product_attributes AS TargetAttribute ON TargetAttribute.id = ConditionRow.target_product_attribute_id
			INNER JOIN product_attributes AS SourceAttribute ON SourceAttribute.id = ConditionRow.source_product_attribute_id
			WHERE ConditionRow.product_category_id = :category_id ORDER BY ConditionRow.id");
		$conditionsStmt->execute(['category_id' => $categoryId]);
		return ['attributes' => $attributes, 'conditions' => $conditionsStmt->fetchAll(PDO::FETCH_ASSOC)];
		}

	private function insertOptions(int $attributeId, array $options): void
		{
		$stmt = $this->db->prepare("INSERT INTO product_attribute_options
			(product_attribute_id, value, label, sort_order, active)
			VALUES (:attribute_id, :value, :label, :sort_order, 1)");
		foreach ($options as $position => $option)
			{
			if (!is_array($option)) continue;
			$label = trim((string) ($option['label'] ?? ''));
			if ($label === '') continue;
			$value = trim((string) ($option['value'] ?? '')) ?: $this->normalizeCode($label);
			$stmt->execute(['attribute_id' => $attributeId, 'value' => mb_substr($value, 0, 120), 'label' => mb_substr($label, 0, 120), 'sort_order' => (int) ($option['sort_order'] ?? $position)]);
			}
		}

	private function unitIdByCode(string $code): ?int
		{
		if ($code === '') return null;
		$stmt = $this->db->prepare('SELECT id FROM product_units WHERE code = :code AND active = 1');
		$stmt->execute(['code' => $code]);
		$id = (int) $stmt->fetchColumn();
		return $id > 0 ? $id : null;
		}

	private function uniqueCode(int $categoryId, string $sourceCode, string $name): string
		{
		$base = 'category_' . $categoryId . '_' . $this->normalizeCode($sourceCode !== '' ? $sourceCode : $name);
		$base = trim(mb_substr($base, 0, 72), '_');
		$code = $base;
		$attempt = 2;
		$stmt = $this->db->prepare('SELECT 1 FROM product_attributes WHERE code = :code');
		do
			{
			$stmt->execute(['code' => $code]);
			if (!$stmt->fetchColumn()) return $code;
			$code = mb_substr($base, 0, 70) . '_' . $attempt++;
			}
		while (true);
		}

	private function normalizeCode(string $value): string
		{
		$ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
		$value = strtolower($ascii !== false ? $ascii : $value);
		return trim(preg_replace('/[^a-z0-9]+/', '_', $value) ?? '', '_') ?: 'attribute';
		}
	}
?>
