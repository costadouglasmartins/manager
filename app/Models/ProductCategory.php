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
		       FROM products_categories AS ProductCategory";
		}

	/**
	 * Creates a product category and returns the inserted identifier.
	 *
	 * @param array $data Data to save.
	 */
	public function create(array $data): array
		{
		try
			{
			$this->db->beginTransaction();

			$stmt = $this->db->prepare("
				INSERT INTO products_categories
				    (`name`, `description`, `active`, `created`, `modified`)
				VALUES
				    (:name, :description, :active, :created, :modified)
			");
			$stmt->execute([
				'name'        => trim((string) ($data['name'] ?? '')),
				'description' => sanitizeRichText($data['description'] ?? null),
				'active'      => ((string) ($data['active'] ?? '1') === '1') ? 1 : 0,
				'created'     => date('Y-m-d H:i:s'),
				'modified'    => date('Y-m-d H:i:s'),
			]);

			$categoryId = (int) $this->db->lastInsertId();
			$categorySnapshot = $this->find($categoryId);
			$this->auditCreate('product_category', 'products_categories', $categoryId, $categorySnapshot);
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
		$beforeData = $this->find($categoryId);
		$stmt = $this->db->prepare("
			UPDATE products_categories
			SET `name` = :name,
			    `description` = :description,
			    `active` = :active,
			    `modified` = :modified
			WHERE id = :id
		");

		$stmt->execute([
			'name'        => trim((string) ($data['name'] ?? '')),
			'description' => sanitizeRichText($data['description'] ?? null),
			'active'      => ((string) ($data['active'] ?? '0') === '1') ? 1 : 0,
			'modified'    => date('Y-m-d H:i:s'),
			'id'          => (int) $id,
		]);

		$afterData = $this->find($categoryId);
		$this->auditUpdate('product_category', 'products_categories', $categoryId, $beforeData, $afterData);
		}

	/**
	 * Deletes an existing product category and keeps linked products without a category.
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

			$unlinkProducts = $this->db->prepare("
				UPDATE products
				SET product_category_id = NULL,
				    modified = :modified
				WHERE product_category_id = :product_category_id
			");
			$unlinkProducts->execute([
				'modified'            => date('Y-m-d H:i:s'),
				'product_category_id' => $categoryId,
			]);

			$stmt = $this->db->prepare("DELETE FROM products_categories WHERE id = :id");
			$stmt->execute(['id' => $categoryId]);

			$this->auditDelete('product_category', 'products_categories', $categoryId, $category, [
				'affected_tables' => ['products_categories', 'products'],
				'unlinked_products_count' => (int) $unlinkProducts->rowCount(),
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
		        FROM products_categories AS ProductCategory
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
		return ['product_category' => $this->find($id)];
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
