<?php

namespace App\Models;

use App\Core\TenantModel;
use PDO;

class TaskCategory extends TenantModel
	{
	/**
	 * Returns the base task category select query.
	 **/
	private function baseSelectSql(): string
		{
		return "SELECT TaskCategory.id,
		              TaskCategory.name,
		              TaskCategory.description,
		              TaskCategory.active,
		              TaskCategory.created,
		              TaskCategory.modified
		       FROM task_categories AS TaskCategory";
		}

	/**
	 * Creates a task category and returns the inserted identifier.
	 * @param array $data Data to save.
	 * @return array
	 **/
	public function create(array $data): array
		{
		$payload = $this->normalizePayload($data, '1');

		try
			{
			$this->db->beginTransaction();

			$stmt = $this->db->prepare("
				INSERT INTO task_categories
				    (`name`, `description`, `active`, `created`, `modified`)
				VALUES
				    (:name, :description, :active, :created, :modified)
			");
			$stmt->execute([
				'name'        => $payload['name'],
				'description' => $payload['description'],
				'active'      => $payload['active'],
				'created'     => date('Y-m-d H:i:s'),
				'modified'    => date('Y-m-d H:i:s'),
			]);

			$categoryId = (int) $this->db->lastInsertId();
			$categorySnapshot = $this->find($categoryId);
			$this->auditCreate('task_category', 'task_categories', $categoryId, $categorySnapshot);
			$this->db->commit();

			return ['task_category_id' => $categoryId];
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
	 * Returns task category options ordered by name.
	 **/
	public function getOptions(bool $activeOnly = true): array
		{
		$sql = $this->baseSelectSql() . "
			WHERE 1 = 1
		";

		if ($activeOnly)
			{
			$sql .= " AND TaskCategory.active = 1";
			}

		$sql .= "
			ORDER BY TaskCategory.name ASC
		";

		$stmt = $this->db->query($sql);
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Finds a task category by id.
	 * @param mixed $id Category identifier.
	 * @return array|false
	 **/
	public function find($id)
		{
		$stmt = $this->db->prepare($this->baseSelectSql() . "
			WHERE TaskCategory.id = :id
		");
		$stmt->execute(['id' => (int) $id]);

		return $stmt->fetch(PDO::FETCH_ASSOC);
		}

	/**
	 * Updates an existing task category.
	 * @param mixed $id Category identifier.
	 * @param array $data Updated category data.
	 **/
	public function update($id, array $data): void
		{
		$categoryId = (int) $id;
		$payload = $this->normalizePayload($data, '0');
		$beforeData = $this->find($categoryId);

		$stmt = $this->db->prepare("
			UPDATE task_categories
			SET `name` = :name,
			    `description` = :description,
			    `active` = :active,
			    `modified` = :modified
			WHERE id = :id
		");

		$stmt->execute([
			'name'        => $payload['name'],
			'description' => $payload['description'],
			'active'      => $payload['active'],
			'modified'    => date('Y-m-d H:i:s'),
			'id'          => $categoryId,
		]);

		$afterData = $this->find($categoryId);
		$this->auditUpdate('task_category', 'task_categories', $categoryId, $beforeData, $afterData);
		}

	/**
	 * Deletes an existing task category and keeps linked tasks without a category.
	 * @param mixed $id Category identifier.
	 **/
	public function delete($id): void
		{
		$categoryId = (int) $id;
		if ($categoryId <= 0)
			{
			throw new \InvalidArgumentException('Categoria de tarefa inválida.');
			}

		try
			{
			$this->db->beginTransaction();

			$category = $this->find($categoryId);
			if (!$category)
				{
				throw new \InvalidArgumentException('Categoria de tarefa não encontrada.');
				}

			$unlinkTasks = $this->db->prepare("
				UPDATE tasks
				SET task_category_id = NULL,
				    modified = :modified
				WHERE task_category_id = :task_category_id
			");
			$unlinkTasks->execute([
				'modified'         => date('Y-m-d H:i:s'),
				'task_category_id' => $categoryId,
			]);

			$stmt = $this->db->prepare("DELETE FROM task_categories WHERE id = :id");
			$stmt->execute(['id' => $categoryId]);

			$this->auditDelete('task_category', 'task_categories', $categoryId, $category, [
				'affected_tables' => ['task_categories', 'tasks'],
				'unlinked_tasks_count' => (int) $unlinkTasks->rowCount(),
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
	 * Searches task categories using filters and pagination.
	 **/
	public function search(array $filters = [], ?int $limit = 10, int $offset = 0): array
		{
		$sql = $this->baseSelectSql() . "
			WHERE 1 = 1
		";
		$params = [];

		$this->appendSearchFilters($sql, $params, $filters);

		$sql .= "
			ORDER BY TaskCategory.id DESC
		";

		if ($limit !== null)
			{
			$sql .= "
			LIMIT :limit OFFSET :offset
			";
			}

		$stmt = $this->db->prepare($sql);
		$this->bindSearchParams($stmt, $params);

		if ($limit !== null)
			{
			$stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
			$stmt->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
			}

		$stmt->execute();

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Counts task categories matching filters.
	 **/
	public function count(array $filters = []): int
		{
		$sql = "SELECT COUNT(*) AS total
		        FROM task_categories AS TaskCategory
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
	 * @param mixed $id Category identifier.
	 * @return array
	 **/
	public function findAllByTaskCategoryId($id): array
		{
		return ['task_category' => $this->find($id)];
		}

	/**
	 * Appends category search filters to a SQL query.
	 **/
	private function appendSearchFilters(string &$sql, array &$params, array $filters): void
		{
		if (isset($filters['id']) && $filters['id'] !== '')
			{
			$sql .= " AND TaskCategory.id = :id";
			$params['id'] = (int) $filters['id'];
			}

		if (isset($filters['name']) && $filters['name'] !== '')
			{
			$sql .= " AND TaskCategory.`name` LIKE :name";
			$params['name'] = '%' . trim((string) $filters['name']) . '%';
			}

		if (isset($filters['active']) && in_array((string) $filters['active'], ['0', '1'], true))
			{
			$sql .= " AND TaskCategory.active = :active";
			$params['active'] = (int) $filters['active'];
			}
		}

	/**
	 * Binds search params to a prepared statement.
	 **/
	private function bindSearchParams(\PDOStatement $stmt, array $params): void
		{
		foreach ($params as $key => $value)
			{
			$paramType = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
			$stmt->bindValue(':' . $key, $value, $paramType);
			}
		}

	/**
	 * Normalizes form data before persistence.
	 **/
	private function normalizePayload(array $data, string $defaultActive): array
		{
		$name = trim((string) ($data['name'] ?? ''));
		if ($name === '')
			{
			throw new \InvalidArgumentException('Informe o nome da categoria.');
			}

		return [
			'name'        => $name,
			'description' => sanitizeRichText($data['description'] ?? null),
			'active'      => ((string) ($data['active'] ?? $defaultActive) === '1') ? 1 : 0,
		];
		}
	}
?>
