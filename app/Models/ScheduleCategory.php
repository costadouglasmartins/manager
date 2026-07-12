<?php

namespace App\Models;

use App\Core\TenantModel;
use PDO;

class ScheduleCategory extends TenantModel
	{
	/**
	 * Returns the base schedule category select query.
	 **/
	private function baseSelectSql(): string
		{
		return "SELECT ScheduleCategory.id,
		              ScheduleCategory.name,
		              ScheduleCategory.description,
		              ScheduleCategory.background_color,
		              ScheduleCategory.description_color,
		              ScheduleCategory.active,
		              ScheduleCategory.created,
		              ScheduleCategory.modified
		       FROM schedule_categories AS ScheduleCategory";
		}

	/**
	 * Creates a schedule category and returns the inserted identifier.
	 *
	 * @param array<string, mixed> $data Data to save.
	 * @return array<string, int>
	 **/
	public function create(array $data): array
		{
		$payload = $this->normalizePayload($data, '1');

		try
			{
			$this->db->beginTransaction();

			$stmt = $this->db->prepare("
				INSERT INTO schedule_categories
				    (`name`, `description`, `background_color`, `description_color`, `active`, `created`, `modified`)
				VALUES
				    (:name, :description, :background_color, :description_color, :active, :created, :modified)
			");
			$stmt->execute([
				'name'              => $payload['name'],
				'description'       => $payload['description'],
				'background_color'  => $payload['background_color'],
				'description_color' => $payload['description_color'],
				'active'            => $payload['active'],
				'created'           => date('Y-m-d H:i:s'),
				'modified'          => date('Y-m-d H:i:s'),
			]);

			$categoryId = (int) $this->db->lastInsertId();
			$categorySnapshot = $this->find($categoryId);
			$this->auditCreate('schedule_category', 'schedule_categories', $categoryId, $categorySnapshot);
			$this->db->commit();

			return ['schedule_category_id' => $categoryId];
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
	 * Returns category options ordered by name.
	 **/
	public function getOptions(bool $activeOnly = true): array
		{
		$sql = $this->baseSelectSql() . "
			WHERE 1 = 1
		";

		if ($activeOnly)
			{
			$sql .= " AND ScheduleCategory.active = 1";
			}

		$sql .= "
			ORDER BY ScheduleCategory.name ASC
		";

		$stmt = $this->db->query($sql);
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Finds a schedule category by id.
	 *
	 * @param mixed $id Category identifier.
	 * @return array<string, mixed>|false
	 **/
	public function find($id)
		{
		$stmt = $this->db->prepare($this->baseSelectSql() . "
			WHERE ScheduleCategory.id = :id
		");
		$stmt->execute(['id' => (int) $id]);

		return $stmt->fetch(PDO::FETCH_ASSOC);
		}

	/**
	 * Updates an existing schedule category.
	 *
	 * @param mixed $id Category identifier.
	 * @param array<string, mixed> $data Updated category data.
	 **/
	public function update($id, array $data): void
		{
		$categoryId = (int) $id;
		$payload = $this->normalizePayload($data, '0');
		$beforeData = $this->find($categoryId);

		if (!$beforeData)
			{
			throw new \InvalidArgumentException('Categoria de agenda não encontrada.');
			}

		$stmt = $this->db->prepare("
			UPDATE schedule_categories
			SET `name` = :name,
			    `description` = :description,
			    `background_color` = :background_color,
			    `description_color` = :description_color,
			    `active` = :active,
			    `modified` = :modified
			WHERE id = :id
		");

		$stmt->execute([
			'name'              => $payload['name'],
			'description'       => $payload['description'],
			'background_color'  => $payload['background_color'],
			'description_color' => $payload['description_color'],
			'active'            => $payload['active'],
			'modified'          => date('Y-m-d H:i:s'),
			'id'                => $categoryId,
		]);

		$afterData = $this->find($categoryId);
		$this->auditUpdate('schedule_category', 'schedule_categories', $categoryId, $beforeData, $afterData);
		}

	/**
	 * Deletes a schedule category and keeps linked schedule records without a category.
	 *
	 * @param mixed $id Category identifier.
	 **/
	public function delete($id): void
		{
		$categoryId = (int) $id;
		if ($categoryId <= 0)
			{
			throw new \InvalidArgumentException('Categoria de agenda inválida.');
			}

		try
			{
			$this->db->beginTransaction();

			$category = $this->find($categoryId);
			if (!$category)
				{
				throw new \InvalidArgumentException('Categoria de agenda não encontrada.');
				}

			$now = date('Y-m-d H:i:s');
			$unlinkEvents = $this->db->prepare("
				UPDATE schedule_events
				SET schedule_category_id = NULL,
				    modified = :modified
				WHERE schedule_category_id = :schedule_category_id
			");
			$unlinkEvents->execute([
				'modified' => $now,
				'schedule_category_id' => $categoryId,
			]);

			$unlinkRecurrences = $this->db->prepare("
				UPDATE schedule_events_recurrences
				SET schedule_category_id = NULL,
				    modified = :modified
				WHERE schedule_category_id = :schedule_category_id
			");
			$unlinkRecurrences->execute([
				'modified' => $now,
				'schedule_category_id' => $categoryId,
			]);

			$stmt = $this->db->prepare("DELETE FROM schedule_categories WHERE id = :id");
			$stmt->execute(['id' => $categoryId]);

			$this->auditDelete('schedule_category', 'schedule_categories', $categoryId, $category, [
				'affected_tables' => ['schedule_categories', 'schedule_events', 'schedule_events_recurrences'],
				'unlinked_events_count' => (int) $unlinkEvents->rowCount(),
				'unlinked_recurrences_count' => (int) $unlinkRecurrences->rowCount(),
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
	 * Searches schedule categories using filters and pagination.
	 **/
	public function search(array $filters = [], ?int $limit = 10, int $offset = 0): array
		{
		$sql = $this->baseSelectSql() . "
			WHERE 1 = 1
		";
		$params = [];

		$this->appendSearchFilters($sql, $params, $filters);

		$sql .= "
			ORDER BY ScheduleCategory.id DESC
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
	 * Counts schedule categories matching filters.
	 **/
	public function count(array $filters = []): int
		{
		$sql = "SELECT COUNT(*) AS total
		        FROM schedule_categories AS ScheduleCategory
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
	 * @return array<string, mixed>
	 **/
	public function findAllByScheduleCategoryId($id): array
		{
		return ['schedule_category' => $this->find($id)];
		}

	/**
	 * Appends category search filters to a SQL query.
	 **/
	private function appendSearchFilters(string &$sql, array &$params, array $filters): void
		{
		if (isset($filters['id']) && $filters['id'] !== '')
			{
			$sql .= " AND ScheduleCategory.id = :id";
			$params['id'] = (int) $filters['id'];
			}

		if (isset($filters['name']) && $filters['name'] !== '')
			{
			$sql .= " AND ScheduleCategory.`name` LIKE :name";
			$params['name'] = '%' . trim((string) $filters['name']) . '%';
			}

		if (isset($filters['active']) && in_array((string) $filters['active'], ['0', '1'], true))
			{
			$sql .= " AND ScheduleCategory.active = :active";
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
			'name'              => $name,
			'description'       => sanitizeRichText($data['description'] ?? null),
			'background_color'  => $this->normalizeHexColor((string) ($data['background_color'] ?? '#0d6efd'), '#0d6efd'),
			'description_color' => $this->normalizeHexColor((string) ($data['description_color'] ?? '#ffffff'), '#ffffff'),
			'active'            => ((string) ($data['active'] ?? $defaultActive) === '1') ? 1 : 0,
		];
		}

	/**
	 * Normalizes a hexadecimal color.
	 **/
	private function normalizeHexColor(string $value, string $fallback): string
		{
		$value = trim($value);
		if (preg_match('/^#[0-9A-Fa-f]{6}$/', $value) !== 1)
			{
			$value = $fallback;
			}

		return strtolower($value);
		}
	}
?>
