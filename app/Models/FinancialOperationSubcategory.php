<?php

namespace App\Models;

use App\Core\TenantModel;
use PDO;

class FinancialOperationSubcategory extends TenantModel
	{
	/**
	 * Returns the base financial operation subcategory select query with category data.
	 */
	private function baseSelectSql(): string
		{
		return "SELECT FinancialOperationSubcategory.id,
		              FinancialOperationSubcategory.financial_operation_category_id,
		              FinancialOperationSubcategory.name,
		              FinancialOperationSubcategory.created,
		              FinancialOperationSubcategory.modified,
		              FinancialOperationCategory.name AS financial_operation_category_name
		       FROM financial_operations_subcategories AS FinancialOperationSubcategory
		       INNER JOIN financial_operations_categories AS FinancialOperationCategory ON FinancialOperationCategory.id = FinancialOperationSubcategory.financial_operation_category_id";
		}

	/**
	 * Creates a financial operation subcategory and returns the inserted identifier.
	 *
	 * @param array $data Data to save.
	 * @return array
	 * @throws \Exception
	 */
	public function create($data): array
		{
		try
			{
			$this->db->beginTransaction();

			$stmt = $this->db->prepare("
				INSERT INTO financial_operations_subcategories
				    (`financial_operation_category_id`, `name`, `created`, `modified`)
				VALUES
				    (:financial_operation_category_id, :name, :created, :modified)
			");
			$stmt->execute([
				'financial_operation_category_id' => (int) ($data['financial_operation_category_id'] ?? 0),
				'name'                            => trim((string) ($data['name'] ?? '')),
				'created'                         => date('Y-m-d H:i:s'),
				'modified'                        => date('Y-m-d H:i:s'),
			]);

			$subcategoryId = (int) $this->db->lastInsertId();
			$subcategorySnapshot = $this->find($subcategoryId);
			$this->auditCreate('financial_operation_subcategory', 'financial_operations_subcategories', $subcategoryId, $subcategorySnapshot);
			$this->db->commit();

			return ['financial_operation_subcategory_id' => $subcategoryId];
			}
		catch (\Exception $e)
			{
			$this->db->rollBack();
			throw $e;
			}
		}

	/**
	 * Returns all subcategories ordered by category and name.
	 */
	public function getOptions(): array
		{
		$stmt = $this->db->query($this->baseSelectSql() . "
			ORDER BY FinancialOperationCategory.name ASC,
			         FinancialOperationSubcategory.name ASC
		");

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Finds a financial operation subcategory by id.
	 *
	 * @param mixed $id Subcategory identifier.
	 */
	public function find($id)
		{
		$stmt = $this->db->prepare($this->baseSelectSql() . "
			WHERE FinancialOperationSubcategory.id = :id
		");
		$stmt->execute(['id' => (int) $id]);

		return $stmt->fetch(PDO::FETCH_ASSOC);
		}

	/**
	 * Updates an existing financial operation subcategory.
	 *
	 * @param mixed $id Subcategory identifier.
	 * @param array $data Updated subcategory data.
	 */
	public function update($id, array $data): void
		{
		$subcategoryId = (int) $id;
		$beforeData = $this->find($subcategoryId);
		$stmt = $this->db->prepare("
			UPDATE financial_operations_subcategories
			SET `financial_operation_category_id` = :financial_operation_category_id,
			    `name` = :name,
			    `modified` = :modified
			WHERE id = :id
		");

		$stmt->execute([
			'financial_operation_category_id' => (int) ($data['financial_operation_category_id'] ?? 0),
			'name'                            => trim((string) ($data['name'] ?? '')),
			'modified'                        => date('Y-m-d H:i:s'),
			'id'                              => (int) $id,
		]);

		$afterData = $this->find($subcategoryId);
		$this->auditUpdate('financial_operation_subcategory', 'financial_operations_subcategories', $subcategoryId, $beforeData, $afterData);
		}

	/**
	 * Searches subcategories using the provided filters and pagination.
	 */
	public function search(array $filters = [], int $limit = 10, int $offset = 0): array
		{
		$sql = $this->baseSelectSql() . "
			WHERE 1 = 1
		";
		$params = [];

		if (isset($filters['id']) && $filters['id'] !== '')
			{
			$sql .= " AND FinancialOperationSubcategory.id = :id";
			$params['id'] = (int) $filters['id'];
			}

		if (isset($filters['financial_operation_category_id']) && $filters['financial_operation_category_id'] !== '')
			{
			$sql .= " AND FinancialOperationSubcategory.financial_operation_category_id = :financial_operation_category_id";
			$params['financial_operation_category_id'] = (int) $filters['financial_operation_category_id'];
			}

		if (isset($filters['name']) && $filters['name'] !== '')
			{
			$sql .= " AND FinancialOperationSubcategory.`name` LIKE :name";
			$params['name'] = '%' . $filters['name'] . '%';
			}

		$sql .= "
			ORDER BY FinancialOperationSubcategory.id DESC
			LIMIT :limit OFFSET :offset
		";

		$stmt = $this->db->prepare($sql);

		foreach ($params as $key => $value)
			{
			$paramType = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
			$stmt->bindValue(':' . $key, $value, $paramType);
			}

		$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
		$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
		$stmt->execute();

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Counts subcategories that match the provided filters.
	 */
	public function count(array $filters = []): int
		{
		$sql = "SELECT COUNT(*) AS total
		        FROM financial_operations_subcategories AS FinancialOperationSubcategory
		        INNER JOIN financial_operations_categories AS FinancialOperationCategory ON FinancialOperationCategory.id = FinancialOperationSubcategory.financial_operation_category_id
		        WHERE 1 = 1";
		$params = [];

		if (isset($filters['id']) && $filters['id'] !== '')
			{
			$sql .= " AND FinancialOperationSubcategory.id = :id";
			$params['id'] = (int) $filters['id'];
			}

		if (isset($filters['financial_operation_category_id']) && $filters['financial_operation_category_id'] !== '')
			{
			$sql .= " AND FinancialOperationSubcategory.financial_operation_category_id = :financial_operation_category_id";
			$params['financial_operation_category_id'] = (int) $filters['financial_operation_category_id'];
			}

		if (isset($filters['name']) && $filters['name'] !== '')
			{
			$sql .= " AND FinancialOperationSubcategory.`name` LIKE :name";
			$params['name'] = '%' . $filters['name'] . '%';
			}

		$stmt = $this->db->prepare($sql);

		foreach ($params as $key => $value)
			{
			$paramType = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
			$stmt->bindValue(':' . $key, $value, $paramType);
			}

		$stmt->execute();

		return (int) $stmt->fetchColumn();
		}

	/**
	 * Returns the subcategory details payload used by the information view.
	 *
	 * @param mixed $id Subcategory identifier.
	 */
	public function findAllByFinancialOperationSubcategoryId($id): array
		{
		return ['financial_operation_subcategory' => $this->find($id)];
		}
	}
?>
