<?php

namespace App\Models;

use App\Core\TenantModel;
use PDO;

class CrmActivityStatus extends TenantModel
	{
	private function baseSelectSql(): string
		{
		return "SELECT CrmActivityStatus.id,
		              CrmActivityStatus.name,
		              CrmActivityStatus.slug,
		              CrmActivityStatus.description,
		              CrmActivityStatus.bootstrap_class,
		              CrmActivityStatus.sort_order,
		              CrmActivityStatus.active,
		              CrmActivityStatus.is_final,
		              CrmActivityStatus.created_by_user_id,
		              CrmActivityStatus.updated_by_user_id,
		              CrmActivityStatus.created,
		              CrmActivityStatus.modified
		       FROM crm_activity_statuses AS CrmActivityStatus";
		}

	public function search(array $filters = [], int $limit = 10, int $offset = 0): array
		{
		$sql = $this->baseSelectSql() . "
			WHERE 1 = 1
		";
		$params = [];

		$this->appendFilters($sql, $params, $filters);
		$sql .= "
			ORDER BY CrmActivityStatus.sort_order ASC, CrmActivityStatus.name ASC, CrmActivityStatus.id ASC
			LIMIT :limit OFFSET :offset
		";

		$stmt = $this->db->prepare($sql);
		$this->bindParams($stmt, $params);
		$stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
		$stmt->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
		$stmt->execute();

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	public function count(array $filters = []): int
		{
		$sql = "SELECT COUNT(*) AS total
		        FROM crm_activity_statuses AS CrmActivityStatus
		        WHERE 1 = 1";
		$params = [];

		$this->appendFilters($sql, $params, $filters);

		$stmt = $this->db->prepare($sql);
		$this->bindParams($stmt, $params);
		$stmt->execute();

		return (int) $stmt->fetchColumn();
		}

	public function getOptions(bool $activeOnly = true, ?int $includeId = null): array
		{
		$sql = $this->baseSelectSql() . "
			WHERE 1 = 1
		";
		$params = [];

		if ($activeOnly)
			{
			$sql .= " AND (CrmActivityStatus.active = 1";
			if ($includeId !== null && $includeId > 0)
				{
				$sql .= " OR CrmActivityStatus.id = :include_id";
				$params['include_id'] = $includeId;
				}
			$sql .= ")";
			}

		$sql .= "
			ORDER BY CrmActivityStatus.active DESC, CrmActivityStatus.sort_order ASC, CrmActivityStatus.name ASC, CrmActivityStatus.id ASC
		";

		$stmt = $this->db->prepare($sql);
		$this->bindParams($stmt, $params);
		$stmt->execute();

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	public function find($id): array|false
		{
		$stmt = $this->db->prepare($this->baseSelectSql() . "
			WHERE CrmActivityStatus.id = :id
			LIMIT 1
		");
		$stmt->execute(['id' => (int) $id]);

		return $stmt->fetch(PDO::FETCH_ASSOC);
		}

	public function findAllByCrmActivityStatusId($id): array
		{
		return ['crm_activity_status' => $this->find($id)];
		}

	public function create(array $data, int $userId): array
		{
		$payload = $this->normalizePayload($data, '1');
		$this->assertUniqueSlug($payload['slug']);
		$now = date('Y-m-d H:i:s');

		try
			{
			$this->db->beginTransaction();
			$stmt = $this->db->prepare("
				INSERT INTO crm_activity_statuses
					(name, slug, description, bootstrap_class, sort_order, active, is_final, created_by_user_id, updated_by_user_id, created, modified)
				VALUES
					(:name, :slug, :description, :bootstrap_class, :sort_order, :active, :is_final, :created_by_user_id, :updated_by_user_id, :created, :modified)
			");
			$stmt->execute([
				'name' => $payload['name'],
				'slug' => $payload['slug'],
				'description' => $payload['description'],
				'bootstrap_class' => $payload['bootstrap_class'],
				'sort_order' => $payload['sort_order'],
				'active' => $payload['active'],
				'is_final' => $payload['is_final'],
				'created_by_user_id' => $userId,
				'updated_by_user_id' => $userId,
				'created' => $now,
				'modified' => $now,
			]);

			$statusId = (int) $this->db->lastInsertId();
			$snapshot = $this->find($statusId);
			$this->auditCreate('crm_activity_status', 'crm_activity_statuses', $statusId, $snapshot);
			$this->db->commit();

			return ['crm_activity_status_id' => $statusId];
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

	public function update($id, array $data, int $userId): void
		{
		$statusId = (int) $id;
		if ($statusId <= 0)
			{
			throw new \InvalidArgumentException('Status de atividade CRM inválido.');
			}

		$beforeData = $this->find($statusId);
		if (!$beforeData)
			{
			throw new \InvalidArgumentException('Status de atividade CRM não encontrado.');
			}

		$payload = $this->normalizePayload($data, (string) ($beforeData['active'] ?? '1'));
		$this->assertUniqueSlug($payload['slug'], $statusId);

		try
			{
			$this->db->beginTransaction();
			$stmt = $this->db->prepare("
				UPDATE crm_activity_statuses
				SET name = :name,
				    slug = :slug,
				    description = :description,
				    bootstrap_class = :bootstrap_class,
				    sort_order = :sort_order,
				    active = :active,
				    is_final = :is_final,
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
				'is_final' => $payload['is_final'],
				'updated_by_user_id' => $userId,
				'modified' => date('Y-m-d H:i:s'),
				'id' => $statusId,
			]);

			$afterData = $this->find($statusId);
			$this->auditUpdate('crm_activity_status', 'crm_activity_statuses', $statusId, $beforeData, $afterData);
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

	public function delete($id, int $userId): void
		{
		$statusId = (int) $id;
		if ($statusId <= 0)
			{
			throw new \InvalidArgumentException('Status de atividade CRM inválido.');
			}

		$beforeData = $this->find($statusId);
		if (!$beforeData)
			{
			throw new \InvalidArgumentException('Status de atividade CRM não encontrado.');
			}

		try
			{
			$this->db->beginTransaction();
			$stmt = $this->db->prepare("
				UPDATE crm_activity_statuses
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
			$this->auditUpdate('crm_activity_status', 'crm_activity_statuses', $statusId, $beforeData, $afterData, [
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

	private function appendFilters(string &$sql, array &$params, array $filters): void
		{
		if (isset($filters['id']) && $filters['id'] !== '')
			{
			$sql .= " AND CrmActivityStatus.id = :id";
			$params['id'] = (int) $filters['id'];
			}

		if (isset($filters['name']) && $filters['name'] !== '')
			{
			$sql .= " AND CrmActivityStatus.name LIKE :name";
			$params['name'] = '%' . trim((string) $filters['name']) . '%';
			}

		if (isset($filters['is_final']) && in_array((string) $filters['is_final'], ['0', '1'], true))
			{
			$sql .= " AND CrmActivityStatus.is_final = :is_final";
			$params['is_final'] = (int) $filters['is_final'];
			}

		if (isset($filters['active']) && in_array((string) $filters['active'], ['0', '1'], true))
			{
			$sql .= " AND CrmActivityStatus.active = :active";
			$params['active'] = (int) $filters['active'];
			}
		}

	private function bindParams(\PDOStatement $stmt, array $params): void
		{
		foreach ($params as $key => $value)
			{
			$stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
			}
		}

	private function normalizePayload(array $data, string $defaultActive): array
		{
		$name = substr(trim((string) ($data['name'] ?? '')), 0, 120);
		if ($name === '')
			{
			throw new \InvalidArgumentException('Informe o nome do status de atividade.');
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
			'is_final' => ((string) ($data['is_final'] ?? '0') === '1') ? 1 : 0,
		];
		}

	private function assertUniqueSlug(string $slug, ?int $ignoreId = null): void
		{
		$sql = "SELECT id FROM crm_activity_statuses WHERE slug = :slug";
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
			throw new \InvalidArgumentException('Já existe um status de atividade com esse nome neste tenant.');
			}
		}

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

		return $slug !== '' ? $slug : 'status-atividade';
		}
	}
?>
