<?php

namespace App\Models;

use App\Core\TenantModel;
use PDO;

class CrmStatus extends TenantModel
	{
	/**
	 * Returns the base CRM status select query from the current tenant database.
	 */
	private function baseSelectSql(): string
		{
		return "SELECT CrmStatus.id,
		              CrmStatus.name,
		              CrmStatus.slug,
		              CrmStatus.description,
		              CrmStatus.bootstrap_class,
		              CrmStatus.sort_order,
		              CrmStatus.active,
		              CrmStatus.created_by_user_id,
		              CrmStatus.updated_by_user_id,
		              CrmStatus.created,
		              CrmStatus.modified
		       FROM crm_statuses AS CrmStatus";
		}

	/**
	 * Searches CRM statuses in the current tenant.
	 */
	public function search(array $filters = [], int $limit = 10, int $offset = 0): array
		{
		$sql = $this->baseSelectSql() . "
			WHERE 1 = 1
		";
		$params = [];

		$this->appendFilters($sql, $params, $filters);
		$sql .= "
			ORDER BY CrmStatus.sort_order ASC, CrmStatus.name ASC, CrmStatus.id ASC
			LIMIT :limit OFFSET :offset
		";

		$stmt = $this->db->prepare($sql);
		$this->bindParams($stmt, $params);
		$stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
		$stmt->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
		$stmt->execute();

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Counts CRM statuses in the current tenant.
	 */
	public function count(array $filters = []): int
		{
		$sql = "SELECT COUNT(*) AS total
		        FROM crm_statuses AS CrmStatus
		        WHERE 1 = 1";
		$params = [];

		$this->appendFilters($sql, $params, $filters);

		$stmt = $this->db->prepare($sql);
		$this->bindParams($stmt, $params);
		$stmt->execute();

		return (int) $stmt->fetchColumn();
		}

	/**
	 * Returns CRM status options ordered for selects.
	 */
	public function getOptions(bool $activeOnly = true, ?int $includeId = null): array
		{
		$sql = $this->baseSelectSql() . "
			WHERE 1 = 1
		";
		$params = [];

		if ($activeOnly)
			{
			$sql .= " AND (CrmStatus.active = 1";
			if ($includeId !== null && $includeId > 0)
				{
				$sql .= " OR CrmStatus.id = :include_id";
				$params['include_id'] = $includeId;
				}
			$sql .= ")";
			}

		$sql .= "
			ORDER BY CrmStatus.active DESC, CrmStatus.sort_order ASC, CrmStatus.name ASC, CrmStatus.id ASC
		";

		$stmt = $this->db->prepare($sql);
		$this->bindParams($stmt, $params);
		$stmt->execute();

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Finds a CRM status by id.
	 */
	public function find($id): array|false
		{
		$stmt = $this->db->prepare($this->baseSelectSql() . "
			WHERE CrmStatus.id = :id
			LIMIT 1
		");
		$stmt->execute(['id' => (int) $id]);

		return $stmt->fetch(PDO::FETCH_ASSOC);
		}

	/**
	 * Returns the details payload used by the information view.
	 */
	public function findAllByCrmStatusId($id): array
		{
		return ['crm_status' => $this->find($id)];
		}

	/**
	 * Creates a CRM status in the current tenant.
	 */
	public function create(array $data, int $userId): array
		{
		$payload = $this->normalizePayload($data, '1');
		$this->assertUniqueSlug($payload['slug']);
		$now = date('Y-m-d H:i:s');

		try
			{
			$this->db->beginTransaction();
			$stmt = $this->db->prepare("
				INSERT INTO crm_statuses
					(name, slug, description, bootstrap_class, sort_order, active, created_by_user_id, updated_by_user_id, created, modified)
				VALUES
					(:name, :slug, :description, :bootstrap_class, :sort_order, :active, :created_by_user_id, :updated_by_user_id, :created, :modified)
			");
			$stmt->execute([
				'name' => $payload['name'],
				'slug' => $payload['slug'],
				'description' => $payload['description'],
				'bootstrap_class' => $payload['bootstrap_class'],
				'sort_order' => $payload['sort_order'],
				'active' => $payload['active'],
				'created_by_user_id' => $userId,
				'updated_by_user_id' => $userId,
				'created' => $now,
				'modified' => $now,
			]);

			$statusId = (int) $this->db->lastInsertId();
			$snapshot = $this->find($statusId);
			$this->auditCreate('crm_status', 'crm_statuses', $statusId, $snapshot);
			$this->db->commit();

			return ['crm_status_id' => $statusId];
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
	 * Updates a CRM status in the current tenant.
	 */
	public function update($id, array $data, int $userId): void
		{
		$statusId = (int) $id;
		if ($statusId <= 0)
			{
			throw new \InvalidArgumentException('Status CRM inválido.');
			}

		$beforeData = $this->find($statusId);
		if (!$beforeData)
			{
			throw new \InvalidArgumentException('Status CRM não encontrado.');
			}

		$payload = $this->normalizePayload($data, (string) ($beforeData['active'] ?? '1'));
		$this->assertUniqueSlug($payload['slug'], $statusId);

		try
			{
			$this->db->beginTransaction();
			$stmt = $this->db->prepare("
				UPDATE crm_statuses
				SET name = :name,
				    slug = :slug,
				    description = :description,
				    bootstrap_class = :bootstrap_class,
				    sort_order = :sort_order,
				    active = :active,
				    updated_by_user_id = :updated_by_user_id,
				    modified = :modified
				WHERE id = :id
			");
			$stmt->execute([
				'name' => $payload['name'],
				'slug' => $payload['slug'],
				'description' => $payload['description'],
				'bootstrap_class' => $payload['bootstrap_class'],
				'sort_order' => $payload['sort_order'],
				'active' => $payload['active'],
				'updated_by_user_id' => $userId,
				'modified' => date('Y-m-d H:i:s'),
				'id' => $statusId,
			]);

			$afterData = $this->find($statusId);
			$this->auditUpdate('crm_status', 'crm_statuses', $statusId, $beforeData, $afterData);
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
	 * Deactivates a CRM status while keeping historical links intact.
	 */
	public function delete($id, int $userId): void
		{
		$statusId = (int) $id;
		if ($statusId <= 0)
			{
			throw new \InvalidArgumentException('Status CRM inválido.');
			}

		$beforeData = $this->find($statusId);
		if (!$beforeData)
			{
			throw new \InvalidArgumentException('Status CRM não encontrado.');
			}

		try
			{
			$this->db->beginTransaction();
			$stmt = $this->db->prepare("
				UPDATE crm_statuses
				SET active = 0,
				    updated_by_user_id = :updated_by_user_id,
				    modified = :modified
				WHERE id = :id
			");
			$stmt->execute([
				'updated_by_user_id' => $userId,
				'modified' => date('Y-m-d H:i:s'),
				'id' => $statusId,
			]);

			$afterData = $this->find($statusId);
			$this->auditUpdate('crm_status', 'crm_statuses', $statusId, $beforeData, $afterData, [
				'operation' => 'deactivate_status',
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
	 * Finds the preferred default status id for new CRM records.
	 */
	public function defaultStatusId(): ?int
		{
		$id = $this->statusIdBySlug('lead', true);
		if ($id !== null)
			{
			return $id;
			}

		$stmt = $this->db->query("
			SELECT id
			FROM crm_statuses
			WHERE active = 1
			ORDER BY sort_order ASC, name ASC, id ASC
			LIMIT 1
		");
		$id = $stmt->fetchColumn();

		return $id !== false ? (int) $id : null;
		}

	/**
	 * Finds the inactive status id for the current tenant.
	 */
	public function inactiveStatusId(): ?int
		{
		return $this->statusIdBySlug('inactive', true);
		}

	/**
	 * Checks whether a status exists in the tenant and can be assigned.
	 */
	public function isAssignable(int $statusId, ?int $currentStatusId = null): bool
		{
		if ($statusId <= 0)
			{
			return false;
			}

		$status = $this->find($statusId);
		if (!$status)
			{
			return false;
			}

		if ((int) ($status['active'] ?? 0) === 1)
			{
			return true;
			}

		return $currentStatusId !== null && $statusId === $currentStatusId;
		}

	/**
	 * Appends filters to CRM status queries.
	 */
	private function appendFilters(string &$sql, array &$params, array $filters): void
		{
		if (isset($filters['id']) && $filters['id'] !== '')
			{
			$sql .= " AND CrmStatus.id = :id";
			$params['id'] = (int) $filters['id'];
			}

		if (isset($filters['name']) && $filters['name'] !== '')
			{
			$sql .= " AND CrmStatus.name LIKE :name";
			$params['name'] = '%' . trim((string) $filters['name']) . '%';
			}

		if (isset($filters['bootstrap_class']) && $filters['bootstrap_class'] !== '')
			{
			$sql .= " AND CrmStatus.bootstrap_class LIKE :bootstrap_class";
			$params['bootstrap_class'] = '%' . trim((string) $filters['bootstrap_class']) . '%';
			}

		if (isset($filters['active']) && in_array((string) $filters['active'], ['0', '1'], true))
			{
			$sql .= " AND CrmStatus.active = :active";
			$params['active'] = (int) $filters['active'];
			}
		}

	/**
	 * Binds scalar query params.
	 */
	private function bindParams(\PDOStatement $stmt, array $params): void
		{
		foreach ($params as $key => $value)
			{
			$stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
			}
		}

	/**
	 * Normalizes form data before persistence.
	 */
	private function normalizePayload(array $data, string $defaultActive): array
		{
		$name = substr(trim((string) ($data['name'] ?? '')), 0, 120);
		if ($name === '')
			{
			throw new \InvalidArgumentException('Informe o nome do status CRM.');
			}

		$bootstrapClass = substr(trim((string) ($data['bootstrap_class'] ?? 'text-bg-secondary')), 0, 120);
		if ($bootstrapClass === '')
			{
			$bootstrapClass = 'text-bg-secondary';
			}

		if (!preg_match('/^[A-Za-z0-9:_ -]+$/', $bootstrapClass))
			{
			throw new \InvalidArgumentException('Informe apenas classes CSS Bootstrap válidas para o estilo.');
			}

		$sortOrder = (int) ($data['sort_order'] ?? 0);
		if ($sortOrder < 0)
			{
			$sortOrder = 0;
			}

		return [
			'name' => $name,
			'slug' => $this->slugFromName($name),
			'description' => substr(trim((string) ($data['description'] ?? '')), 0, 1000),
			'bootstrap_class' => $bootstrapClass,
			'sort_order' => $sortOrder,
			'active' => ((string) ($data['active'] ?? $defaultActive) === '1') ? 1 : 0,
		];
		}

	/**
	 * Ensures there is no duplicate slug in the current tenant.
	 */
	private function assertUniqueSlug(string $slug, ?int $ignoreId = null): void
		{
		$sql = "
			SELECT id
			FROM crm_statuses
			WHERE slug = :slug
		";
		$params = ['slug' => $slug];

		if ($ignoreId !== null)
			{
			$sql .= " AND id <> :ignore_id";
			$params['ignore_id'] = $ignoreId;
			}

		$sql .= " LIMIT 1";

		$stmt = $this->db->prepare($sql);
		$this->bindParams($stmt, $params);
		$stmt->execute();

		if ($stmt->fetchColumn())
			{
			throw new \InvalidArgumentException('Já existe um status CRM com esse nome neste tenant.');
			}
		}

	/**
	 * Finds a status id by slug.
	 */
	private function statusIdBySlug(string $slug, bool $activeOnly): ?int
		{
		$sql = "
			SELECT id
			FROM crm_statuses
			WHERE slug = :slug
		";

		if ($activeOnly)
			{
			$sql .= " AND active = 1";
			}

		$sql .= " LIMIT 1";

		$stmt = $this->db->prepare($sql);
		$stmt->execute(['slug' => $slug]);
		$id = $stmt->fetchColumn();

		return $id !== false ? (int) $id : null;
		}

	/**
	 * Builds a stable slug from the visible status name.
	 */
	private function slugFromName(string $name): string
		{
		$slug = trim($name);
		if (function_exists('iconv'))
			{
			$converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $slug);
			if (is_string($converted) && $converted !== '')
				{
				$slug = $converted;
				}
			}

		$slug = strtolower($slug);
		$slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
		$slug = trim($slug, '-');

		return $slug !== '' ? $slug : 'status';
		}
	}
?>
