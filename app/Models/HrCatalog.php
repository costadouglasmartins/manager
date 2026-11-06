<?php

namespace App\Models;

use App\Core\TenantModel;
use PDO;

/**
 * Shared behavior of the tenant HR catalogs (departments and positions).
 * Both catalogs have the same columns; concrete classes only describe the table, the audit names and the labels.
 **/
abstract class HrCatalog extends TenantModel
	{
	/**
	 * Returns the catalog table name (plural snake_case).
	 * @return string Table name, for example hr_departments.
	 **/
	abstract protected function tableName(): string;

	/**
	 * Returns the foreign key column of hr_employees that references this catalog.
	 * @return string Column name, for example hr_department_id.
	 **/
	abstract protected function employeeColumn(): string;

	/**
	 * Returns the audit module name (also the route resource).
	 * @return string Module name, for example hr_department.
	 **/
	abstract protected function auditModule(): string;

	/**
	 * Returns the labels and routes shared with the views.
	 * @return array<string, string> Catalog metadata.
	 **/
	abstract public function meta(): array;

	/**
	 * Returns the base catalog select query, including how many employees use each row.
	 * @return string SQL without filters or ordering.
	 **/
	private function baseSelectSql(): string
		{
		return "SELECT Catalog.id,
		              Catalog.name,
		              Catalog.description,
		              Catalog.active,
		              Catalog.created,
		              Catalog.modified,
		              (
		                SELECT COUNT(*)
		                FROM hr_employees AS CatalogEmployee
		                WHERE CatalogEmployee.{$this->employeeColumn()} = Catalog.id
		              ) AS employees_count
		       FROM {$this->tableName()} AS Catalog";
		}

	/**
	 * Finds a catalog row by id.
	 * @param mixed $id Record identifier.
	 * @return array|false Row data or false when missing.
	 **/
	public function find($id)
		{
		$stmt = $this->db->prepare($this->baseSelectSql() . "
			WHERE Catalog.id = :id
		");
		$stmt->execute(['id' => (int) $id]);

		return $stmt->fetch(PDO::FETCH_ASSOC);
		}

	/**
	 * Searches catalog rows using filters and pagination.
	 * @param array<string, mixed> $filters Normalized filters (id, name, active).
	 * @param int|null $limit Page size, or null for every row (exports).
	 * @param int $offset Rows to skip.
	 * @return array<int, array<string, mixed>> Matching rows.
	 **/
	public function search(array $filters = [], ?int $limit = 10, int $offset = 0): array
		{
		$sql = $this->baseSelectSql() . "
			WHERE 1 = 1
		";
		$params = [];
		$this->appendSearchFilters($sql, $params, $filters);

		// Name first keeps the listing readable; id keeps pagination stable.
		$sql .= "
			ORDER BY Catalog.name ASC, Catalog.id ASC
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
	 * Counts catalog rows matching the same filters used by search().
	 * @param array<string, mixed> $filters Normalized filters.
	 * @return int Total rows.
	 **/
	public function count(array $filters = []): int
		{
		$sql = "SELECT COUNT(*) AS total
		        FROM {$this->tableName()} AS Catalog
		        WHERE 1 = 1";
		$params = [];
		$this->appendSearchFilters($sql, $params, $filters);

		$stmt = $this->db->prepare($sql);
		$this->bindSearchParams($stmt, $params);
		$stmt->execute();

		return (int) $stmt->fetchColumn();
		}

	/**
	 * Returns rows ordered for form selects.
	 * @param bool $activeOnly Return only active rows.
	 * @param int|null $includeId Row kept in the list even when inactive (current value of an edited record).
	 * @return array<int, array{id: int, name: string, active: int}> Select options.
	 **/
	public function getOptions(bool $activeOnly = true, ?int $includeId = null): array
		{
		$sql = "SELECT Catalog.id,
		               Catalog.name,
		               Catalog.active
		        FROM {$this->tableName()} AS Catalog
		        WHERE 1 = 1";
		$params = [];

		if ($activeOnly)
			{
			$sql .= " AND (Catalog.active = 1";
			if ($includeId !== null && $includeId > 0)
				{
				$sql .= " OR Catalog.id = :include_id";
				$params['include_id'] = $includeId;
				}
			$sql .= ")";
			}

		$sql .= "
		        ORDER BY Catalog.name ASC, Catalog.id ASC";

		$stmt = $this->db->prepare($sql);
		$this->bindSearchParams($stmt, $params);
		$stmt->execute();

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Creates a catalog row and audits the inserted snapshot.
	 * @param array $data Submitted form data.
	 * @return array{id: int} Inserted identifier.
	 **/
	public function create(array $data): array
		{
		$payload = $this->normalizePayload($data, '1');

		try
			{
			$this->db->beginTransaction();
			$this->assertNameAvailable($payload['name']);

			$stmt = $this->db->prepare("
				INSERT INTO {$this->tableName()}
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

			$id = (int) $this->db->lastInsertId();
			$this->auditCreate($this->auditModule(), $this->tableName(), $id, $this->find($id));
			$this->db->commit();

			return ['id' => $id];
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
	 * Updates a catalog row and audits both snapshots.
	 * @param mixed $id Record identifier.
	 * @param array $data Submitted form data.
	 * @return void
	 **/
	public function update($id, array $data): void
		{
		$recordId = (int) $id;
		$payload = $this->normalizePayload($data, '0');

		try
			{
			$this->db->beginTransaction();

			$before = $this->find($recordId);
			if (!$before)
				{
				throw new \InvalidArgumentException($this->meta()['not_found_message']);
				}

			$this->assertNameAvailable($payload['name'], $recordId);

			$stmt = $this->db->prepare("
				UPDATE {$this->tableName()}
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
				'id'          => $recordId,
			]);

			$this->auditUpdate($this->auditModule(), $this->tableName(), $recordId, $before, $this->find($recordId));
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
	 * Deletes a catalog row, blocking the operation while employees still use it.
	 * @param mixed $id Record identifier.
	 * @return void
	 **/
	public function delete($id): void
		{
		$recordId = (int) $id;
		$meta = $this->meta();
		if ($recordId <= 0)
			{
			throw new \InvalidArgumentException($meta['not_found_message']);
			}

		try
			{
			$this->db->beginTransaction();

			$before = $this->find($recordId);
			if (!$before)
				{
				throw new \InvalidArgumentException($meta['not_found_message']);
				}

			// The foreign key would silently clear the link, so in-use rows must be inactivated instead.
			$inUse = (int) ($before['employees_count'] ?? 0);
			if ($inUse > 0)
				{
				throw new \InvalidArgumentException(sprintf($meta['in_use_message'], $inUse));
				}

			$stmt = $this->db->prepare("DELETE FROM {$this->tableName()} WHERE id = :id");
			$stmt->execute(['id' => $recordId]);

			$this->auditDelete($this->auditModule(), $this->tableName(), $recordId, $before);
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
	 * Appends filter clauses shared by search() and count().
	 * @param string $sql Query being built.
	 * @param array $params Bound parameters being collected.
	 * @param array $filters Normalized filters.
	 * @return void
	 **/
	private function appendSearchFilters(string &$sql, array &$params, array $filters): void
		{
		if (isset($filters['id']) && $filters['id'] !== '')
			{
			$sql .= " AND Catalog.id = :id";
			$params['id'] = (int) $filters['id'];
			}

		if (isset($filters['name']) && $filters['name'] !== '')
			{
			$sql .= " AND Catalog.`name` LIKE :name";
			$params['name'] = '%' . trim((string) $filters['name']) . '%';
			}

		if (isset($filters['active']) && in_array((string) $filters['active'], ['0', '1'], true))
			{
			$sql .= " AND Catalog.active = :active";
			$params['active'] = (int) $filters['active'];
			}
		}

	/**
	 * Binds collected parameters with their PDO types.
	 * @param \PDOStatement $stmt Prepared statement.
	 * @param array $params Parameters keyed without the colon.
	 * @return void
	 **/
	private function bindSearchParams(\PDOStatement $stmt, array $params): void
		{
		foreach ($params as $key => $value)
			{
			$stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
			}
		}

	/**
	 * Validates and normalizes submitted data before persistence.
	 * @param array $data Submitted form data.
	 * @param string $defaultActive Active flag used when the field is absent.
	 * @return array<string, mixed> Values ready to bind.
	 **/
	private function normalizePayload(array $data, string $defaultActive): array
		{
		$meta = $this->meta();
		$name = trim(is_scalar($data['name'] ?? null) ? (string) $data['name'] : '');
		if ($name === '')
			{
			throw new \InvalidArgumentException($meta['name_required_message']);
			}

		if (mb_strlen($name) > 120)
			{
			throw new \InvalidArgumentException('O nome deve ter no máximo 120 caracteres.');
			}

		// The description column is VARCHAR(255): plain text only.
		$description = trim(is_scalar($data['description'] ?? null) ? (string) $data['description'] : '');
		if (mb_strlen($description) > 255)
			{
			throw new \InvalidArgumentException('A descrição deve ter no máximo 255 caracteres.');
			}

		return [
			'name'        => $name,
			'description' => $description !== '' ? $description : null,
			'active'      => ((string) ($data['active'] ?? $defaultActive) === '1') ? 1 : 0,
		];
		}

	/**
	 * Ensures the name is unique, mirroring the UNIQUE KEY of the table with a readable message.
	 * @param string $name Normalized name.
	 * @param int|null $ignoreId Row being edited.
	 * @return void
	 **/
	private function assertNameAvailable(string $name, ?int $ignoreId = null): void
		{
		$sql = "SELECT Catalog.id
		        FROM {$this->tableName()} AS Catalog
		        WHERE Catalog.`name` = :name";
		$params = ['name' => $name];

		if ($ignoreId !== null)
			{
			$sql .= " AND Catalog.id <> :ignore_id";
			$params['ignore_id'] = $ignoreId;
			}

		$sql .= " LIMIT 1";
		$stmt = $this->db->prepare($sql);
		$stmt->execute($params);

		if ($stmt->fetchColumn() !== false)
			{
			throw new \InvalidArgumentException($this->meta()['duplicate_message']);
			}
		}
	}
?>
