<?php

namespace App\Models;

use App\Core\TenantModel;
use PDO;

class FinancialOperationCategory extends TenantModel
	{
	/**
	 * Returns the base financial operation category select query.
	 */
	private function baseSelectSql(): string
		{
		return "SELECT FinancialOperationCategory.id,
		              FinancialOperationCategory.name,
		              FinancialOperationCategory.created,
		              FinancialOperationCategory.modified
		       FROM financial_operations_categories AS FinancialOperationCategory";
		}

	/**
	 * Creates a financial operation category and returns the inserted identifier.
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
				INSERT INTO financial_operations_categories
				    (`name`, `created`, `modified`)
				VALUES
				    (:name, :created, :modified)
			");
			$stmt->execute([
				'name'     => trim((string) ($data['name'] ?? '')),
				'created'  => date('Y-m-d H:i:s'),
				'modified' => date('Y-m-d H:i:s'),
			]);

			$categoryId = (int) $this->db->lastInsertId();
			$categorySnapshot = $this->find($categoryId);
			$this->auditCreate('financial_operation_category', 'financial_operations_categories', $categoryId, $categorySnapshot);
			$this->db->commit();

			return ['financial_operation_category_id' => $categoryId];
			}
		catch (\Exception $e)
			{
			$this->db->rollBack();
			throw $e;
			}
		}

	/**
	 * Returns all categories ordered by name.
	 */
	public function getOptions(): array
		{
		$stmt = $this->db->query($this->baseSelectSql() . "
			ORDER BY FinancialOperationCategory.name ASC
		");

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Finds a financial operation category by id.
	 *
	 * @param mixed $id Category identifier.
	 */
	public function find($id)
		{
		$stmt = $this->db->prepare($this->baseSelectSql() . "
			WHERE FinancialOperationCategory.id = :id
		");
		$stmt->execute(['id' => (int) $id]);

		return $stmt->fetch(PDO::FETCH_ASSOC);
		}

	/**
	 * Updates an existing financial operation category.
	 *
	 * @param mixed $id Category identifier.
	 * @param array $data Updated category data.
	 */
	public function update($id, array $data): void
		{
		$categoryId = (int) $id;
		$beforeData = $this->find($categoryId);
		$stmt = $this->db->prepare("
			UPDATE financial_operations_categories
			SET `name` = :name,
			    `modified` = :modified
			WHERE id = :id
		");

		$stmt->execute([
			'name'     => trim((string) ($data['name'] ?? '')),
			'modified' => date('Y-m-d H:i:s'),
			'id'       => (int) $id,
		]);

		$afterData = $this->find($categoryId);
		$this->auditUpdate('financial_operation_category', 'financial_operations_categories', $categoryId, $beforeData, $afterData);
		}

	/**
	 * Deletes an existing financial operation category when it has no dependent records.
	 *
	 * @param mixed $id Category identifier.
	 */
	public function delete($id): void
		{
		$categoryId = (int) $id;
		if ($categoryId <= 0)
			{
			throw new \InvalidArgumentException('Categoria financeira inválida.');
			}

		try
			{
			$this->db->beginTransaction();

			$category = $this->find($categoryId);
			if (!$category)
				{
				throw new \InvalidArgumentException('Categoria financeira não encontrada.');
				}

			if ($this->hasOperations($categoryId))
				{
				throw new \RuntimeException('Não é possível excluir a categoria financeira porque existem operações vinculadas.');
				}

			if ($this->hasSubcategories($categoryId))
				{
				throw new \RuntimeException('Não é possível excluir a categoria financeira porque existem subcategorias vinculadas.');
				}

			$stmt = $this->db->prepare("DELETE FROM financial_operations_categories WHERE id = :id");
			$stmt->execute(['id' => $categoryId]);

			$this->auditDelete('financial_operation_category', 'financial_operations_categories', $categoryId, $category);
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
	 * Searches categories using the provided filters and pagination.
	 */
	public function search(array $filters = [], int $limit = 10, int $offset = 0): array
		{
		$sql = $this->baseSelectSql() . "
			WHERE 1 = 1
		";
		$params = [];

		if (isset($filters['id']) && $filters['id'] !== '')
			{
			$sql .= " AND FinancialOperationCategory.id = :id";
			$params['id'] = (int) $filters['id'];
			}

		if (isset($filters['name']) && $filters['name'] !== '')
			{
			$sql .= " AND FinancialOperationCategory.`name` LIKE :name";
			$params['name'] = '%' . $filters['name'] . '%';
			}

		$sql .= "
			ORDER BY FinancialOperationCategory.id DESC
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
	 * Counts categories that match the provided filters.
	 */
	public function count(array $filters = []): int
		{
		$sql = "SELECT COUNT(*) AS total
		        FROM financial_operations_categories AS FinancialOperationCategory
		        WHERE 1 = 1";
		$params = [];

		if (isset($filters['id']) && $filters['id'] !== '')
			{
			$sql .= " AND FinancialOperationCategory.id = :id";
			$params['id'] = (int) $filters['id'];
			}

		if (isset($filters['name']) && $filters['name'] !== '')
			{
			$sql .= " AND FinancialOperationCategory.`name` LIKE :name";
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
	 * Returns the category details payload used by the information view.
	 *
	 * @param mixed $id Category identifier.
	 */
	public function findAllByFinancialOperationCategoryId($id): array
		{
		return ['financial_operation_category' => $this->find($id)];
		}

	/**
	 * Checks whether the category has linked financial operations.
	 */
	private function hasOperations(int $categoryId): bool
		{
		$stmt = $this->db->prepare("
			SELECT COUNT(*)
			FROM financial_operations
			WHERE financial_operation_category_id = :financial_operation_category_id
		");
		$stmt->execute(['financial_operation_category_id' => $categoryId]);

		return (int) $stmt->fetchColumn() > 0;
		}

	/**
	 * Checks whether the category has linked financial subcategories.
	 */
	private function hasSubcategories(int $categoryId): bool
		{
		$stmt = $this->db->prepare("
			SELECT COUNT(*)
			FROM financial_operations_subcategories
			WHERE financial_operation_category_id = :financial_operation_category_id
		");
		$stmt->execute(['financial_operation_category_id' => $categoryId]);

		return (int) $stmt->fetchColumn() > 0;
		}
	}
?>
