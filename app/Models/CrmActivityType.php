<?php

namespace App\Models;

use App\Core\TenantModel;
use PDO;

class CrmActivityType extends TenantModel
	{
	/**
	 * Returns the base CRM activity type select query.
	 */
	private function baseSelectSql(): string
		{
		return "SELECT CrmActivityType.id,
		              CrmActivityType.name,
		              CrmActivityType.slug,
		              CrmActivityType.description,
		              CrmActivityType.bootstrap_class,
		              CrmActivityType.icon,
		              CrmActivityType.sort_order,
		              CrmActivityType.active,
		              CrmActivityType.created_by_user_id,
		              CrmActivityType.updated_by_user_id,
		              CrmActivityType.created,
		              CrmActivityType.modified
		       FROM crm_activity_types AS CrmActivityType";
		}

	/**
	 * Searches CRM activity types in the current tenant.
	 */
	public function search(array $filters = [], int $limit = 10, int $offset = 0): array
		{
		$sql = $this->baseSelectSql() . "
			WHERE 1 = 1
		";
		$params = [];

		$this->appendFilters($sql, $params, $filters);
		$sql .= "
			ORDER BY CrmActivityType.sort_order ASC, CrmActivityType.name ASC, CrmActivityType.id ASC
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
	 * Counts CRM activity types in the current tenant.
	 */
	public function count(array $filters = []): int
		{
		$sql = "SELECT COUNT(*) AS total
		        FROM crm_activity_types AS CrmActivityType
		        WHERE 1 = 1";
		$params = [];

		$this->appendFilters($sql, $params, $filters);

		$stmt = $this->db->prepare($sql);
		$this->bindParams($stmt, $params);
		$stmt->execute();

		return (int) $stmt->fetchColumn();
		}

	/**
	 * Returns active options ordered for selects.
	 */
	public function getOptions(bool $activeOnly = true, ?int $includeId = null): array
		{
		$sql = $this->baseSelectSql() . "
			WHERE 1 = 1
		";
		$params = [];

		if ($activeOnly)
			{
			$sql .= " AND (CrmActivityType.active = 1";
			if ($includeId !== null && $includeId > 0)
				{
				$sql .= " OR CrmActivityType.id = :include_id";
				$params['include_id'] = $includeId;
				}
			$sql .= ")";
			}

		$sql .= "
			ORDER BY CrmActivityType.active DESC, CrmActivityType.sort_order ASC, CrmActivityType.name ASC, CrmActivityType.id ASC
		";

		$stmt = $this->db->prepare($sql);
		$this->bindParams($stmt, $params);
		$stmt->execute();

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Finds a CRM activity type by id.
	 */
	public function find($id): array|false
		{
		$stmt = $this->db->prepare($this->baseSelectSql() . "
			WHERE CrmActivityType.id = :id
			LIMIT 1
		");
		$stmt->execute(['id' => (int) $id]);

		return $stmt->fetch(PDO::FETCH_ASSOC);
		}

	/**
	 * Returns the details payload used by the information view.
	 */
	public function findAllByCrmActivityTypeId($id): array
		{
		return ['crm_activity_type' => $this->find($id)];
		}

	/**
	 * Creates a CRM activity type.
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
				INSERT INTO crm_activity_types
					(name, slug, description, bootstrap_class, icon, sort_order, active, created_by_user_id, updated_by_user_id, created, modified)
				VALUES
					(:name, :slug, :description, :bootstrap_class, :icon, :sort_order, :active, :created_by_user_id, :updated_by_user_id, :created, :modified)
			");
			$stmt->execute([
				'name' => $payload['name'],
				'slug' => $payload['slug'],
				'description' => $payload['description'],
				'bootstrap_class' => $payload['bootstrap_class'],
				'icon' => $payload['icon'],
				'sort_order' => $payload['sort_order'],
				'active' => $payload['active'],
				'created_by_user_id' => $userId,
				'updated_by_user_id' => $userId,
				'created' => $now,
				'modified' => $now,
			]);

			$typeId = (int) $this->db->lastInsertId();
			$snapshot = $this->find($typeId);
			$this->auditCreate('crm_activity_type', 'crm_activity_types', $typeId, $snapshot);
			$this->db->commit();

			return ['crm_activity_type_id' => $typeId];
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
	 * Updates a CRM activity type.
	 */
	public function update($id, array $data, int $userId): void
		{
		$typeId = (int) $id;
		if ($typeId <= 0)
			{
			throw new \InvalidArgumentException('Tipo de atividade CRM inválido.');
			}

		$beforeData = $this->find($typeId);
		if (!$beforeData)
			{
			throw new \InvalidArgumentException('Tipo de atividade CRM não encontrado.');
			}

		$payload = $this->normalizePayload($data, (string) ($beforeData['active'] ?? '1'));
		$this->assertUniqueSlug($payload['slug'], $typeId);

		try
			{
			$this->db->beginTransaction();
			$stmt = $this->db->prepare("
				UPDATE crm_activity_types
				SET name = :name,
				    slug = :slug,
				    description = :description,
				    bootstrap_class = :bootstrap_class,
				    icon = :icon,
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
				'icon' => $payload['icon'],
				'sort_order' => $payload['sort_order'],
				'active' => $payload['active'],
				'updated_by_user_id' => $userId,
				'modified' => date('Y-m-d H:i:s'),
				'id' => $typeId,
			]);

			$afterData = $this->find($typeId);
			$this->auditUpdate('crm_activity_type', 'crm_activity_types', $typeId, $beforeData, $afterData);
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
	 * Deactivates a CRM activity type.
	 */
	public function delete($id, int $userId): void
		{
		$typeId = (int) $id;
		if ($typeId <= 0)
			{
			throw new \InvalidArgumentException('Tipo de atividade CRM inválido.');
			}

		$beforeData = $this->find($typeId);
		if (!$beforeData)
			{
			throw new \InvalidArgumentException('Tipo de atividade CRM não encontrado.');
			}

		try
			{
			$this->db->beginTransaction();
			$stmt = $this->db->prepare("
				UPDATE crm_activity_types
				SET active = 0,
				    updated_by_user_id = :updated_by_user_id,
				    modified = :modified
				WHERE id = :id
			");
			$stmt->execute([
				'updated_by_user_id' => $userId,
				'modified' => date('Y-m-d H:i:s'),
				'id' => $typeId,
			]);

			$afterData = $this->find($typeId);
			$this->auditUpdate('crm_activity_type', 'crm_activity_types', $typeId, $beforeData, $afterData, [
				'operation' => 'deactivate_type',
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
			$sql .= " AND CrmActivityType.id = :id";
			$params['id'] = (int) $filters['id'];
			}

		if (isset($filters['name']) && $filters['name'] !== '')
			{
			$sql .= " AND CrmActivityType.name LIKE :name";
			$params['name'] = '%' . trim((string) $filters['name']) . '%';
			}

		if (isset($filters['icon']) && $filters['icon'] !== '')
			{
			$sql .= " AND CrmActivityType.icon LIKE :icon";
			$params['icon'] = '%' . trim((string) $filters['icon']) . '%';
			}

		if (isset($filters['active']) && in_array((string) $filters['active'], ['0', '1'], true))
			{
			$sql .= " AND CrmActivityType.active = :active";
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
			throw new \InvalidArgumentException('Informe o nome do tipo de atividade.');
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

		$icon = substr(trim((string) ($data['icon'] ?? 'bi-sticky')), 0, 80);
		if ($icon === '')
			{
			$icon = 'bi-sticky';
			}

		if (!preg_match('/^[A-Za-z0-9:_ -]+$/', $icon))
			{
			throw new \InvalidArgumentException('Informe apenas classes Bootstrap Icons válidas para o ícone.');
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
			'icon' => $icon,
			'sort_order' => $sortOrder,
			'active' => ((string) ($data['active'] ?? $defaultActive) === '1') ? 1 : 0,
		];
		}

	private function assertUniqueSlug(string $slug, ?int $ignoreId = null): void
		{
		$sql = "SELECT id FROM crm_activity_types WHERE slug = :slug";
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
			throw new \InvalidArgumentException('Já existe um tipo de atividade com esse nome neste tenant.');
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

		return $slug !== '' ? $slug : 'tipo-atividade';
		}
	}
?>
