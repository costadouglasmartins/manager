<?php

namespace App\Models;

use App\Core\Database;
use App\Core\TenantModel;
use PDO;

class CrmActivity extends TenantModel
	{
	private const ACTIVITY_SCOPE_PENDING = 'pending';
	private const ACTIVITY_SCOPE_OVERDUE = 'overdue';
	private const ACTIVITY_SCOPE_TODAY = 'today';
	private const ACTIVITY_SCOPE_COMPLETED = 'completed';
	private const ACTIVITY_SCOPE_WITHOUT_ACTIVITY = 'without_activity';

	public static function activityScopes(): array
		{
		return [
			self::ACTIVITY_SCOPE_PENDING => 'Com atividades pendentes',
			self::ACTIVITY_SCOPE_OVERDUE => 'Com atividades atrasadas',
			self::ACTIVITY_SCOPE_TODAY => 'Com atividades hoje',
			self::ACTIVITY_SCOPE_COMPLETED => 'Com atividades concluídas',
			self::ACTIVITY_SCOPE_WITHOUT_ACTIVITY => 'Sem atividade registrada',
		];
		}

	public function search(array $filters = [], int $limit = 10, int $offset = 0, int $tenantId = 0, string $sort = 'scheduled_at', string $direction = 'asc'): array
		{
		$sql = $this->baseSelectSql() . "
			WHERE TenantPerson.tenant_id = :tenant_id
		";
		$params = ['tenant_id' => $tenantId];

		$this->appendFilters($sql, $params, $filters);
		$sql .= $this->orderBySql($sort, $direction);
		$sql .= " LIMIT :limit OFFSET :offset";

		$stmt = $this->db->prepare($sql);
		$this->bindParams($stmt, $params);
		$stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
		$stmt->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
		$stmt->execute();

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	public function count(array $filters = [], int $tenantId = 0): int
		{
		$sql = $this->baseCountSql() . "
			WHERE TenantPerson.tenant_id = :tenant_id
		";
		$params = ['tenant_id' => $tenantId];

		$this->appendFilters($sql, $params, $filters);

		$stmt = $this->db->prepare($sql);
		$this->bindParams($stmt, $params);
		$stmt->execute();

		return (int) $stmt->fetchColumn();
		}

	public function dashboard(int $tenantId): array
		{
		return [
			'cards' => $this->dashboardCards($tenantId),
			'statusSummary' => $this->statusSummary($tenantId),
			'upcomingActivities' => $this->upcomingActivities($tenantId, 8),
			'recentActivities' => $this->recentActivities($tenantId, 8),
		];
		}

	public function timelineByCrmId(int $crmId, int $tenantId, int $limit = 20): array
		{
		$sql = $this->baseSelectSql() . "
			WHERE TenantPerson.id = :crm_id
			  AND TenantPerson.tenant_id = :tenant_id
			  AND Activity.active = 1
			ORDER BY COALESCE(Activity.scheduled_at, Activity.created) DESC, Activity.id DESC
			LIMIT :limit
		";

		$stmt = $this->db->prepare($sql);
		$stmt->bindValue(':crm_id', $crmId, PDO::PARAM_INT);
		$stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
		$stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
		$stmt->execute();

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	public function findAllByCrmActivityId($id, int $tenantId): array
		{
		$stmt = $this->db->prepare($this->baseSelectSql() . "
			WHERE Activity.id = :id
			  AND TenantPerson.tenant_id = :tenant_id
			LIMIT 1
		");
		$stmt->execute([
			'id' => (int) $id,
			'tenant_id' => $tenantId,
		]);

		return ['crm_activity' => $stmt->fetch(PDO::FETCH_ASSOC) ?: null];
		}

	public function create(array $data, int $tenantId, int $userId): array
		{
		$payload = $this->normalizePayload($data, $tenantId, $userId);
		$now = date('Y-m-d H:i:s');

		try
			{
			$this->db->beginTransaction();
			$stmt = $this->db->prepare("
				INSERT INTO crm_activities
					(tenant_person_id, person_id, activity_type_id, activity_status_id, title, description,
					 scheduled_at, completed_at, responsible_user_id, active, created_by_user_id, updated_by_user_id, created, modified)
				VALUES
					(:tenant_person_id, :person_id, :activity_type_id, :activity_status_id, :title, :description,
					 :scheduled_at, :completed_at, :responsible_user_id, :active, :created_by_user_id, :updated_by_user_id, :created, :modified)
			");
			$stmt->execute([
				'tenant_person_id' => $payload['tenant_person_id'],
				'person_id' => $payload['person_id'],
				'activity_type_id' => $payload['activity_type_id'],
				'activity_status_id' => $payload['activity_status_id'],
				'title' => $payload['title'],
				'description' => $payload['description'],
				'scheduled_at' => $payload['scheduled_at'],
				'completed_at' => $payload['completed_at'],
				'responsible_user_id' => $payload['responsible_user_id'],
				'active' => $payload['active'],
				'created_by_user_id' => $userId,
				'updated_by_user_id' => $userId,
				'created' => $now,
				'modified' => $now,
			]);

			$activityId = (int) $this->db->lastInsertId();
			$afterData = $this->findAllByCrmActivityId($activityId, $tenantId);
			$this->auditCreate('crm_activity', 'crm_activities', $activityId, $afterData, [
				'affected_tables' => ['crm_activities'],
			]);
			$this->db->commit();

			return ['crm_activity_id' => $activityId, 'tenant_person_id' => $payload['tenant_person_id']];
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

	public function update($id, array $data, int $tenantId, int $userId): void
		{
		$activityId = (int) $id;
		$beforeData = $this->findAllByCrmActivityId($activityId, $tenantId);
		if (empty($beforeData['crm_activity']))
			{
			throw new \InvalidArgumentException('Atividade CRM não encontrada.');
			}

		$payload = $this->normalizePayload($data, $tenantId, $userId, $beforeData['crm_activity']);

		try
			{
			$this->db->beginTransaction();
			$stmt = $this->db->prepare("
				UPDATE crm_activities
				SET tenant_person_id = :tenant_person_id,
				    person_id = :person_id,
				    activity_type_id = :activity_type_id,
				    activity_status_id = :activity_status_id,
				    title = :title,
				    description = :description,
				    scheduled_at = :scheduled_at,
				    completed_at = :completed_at,
				    responsible_user_id = :responsible_user_id,
				    active = :active,
				    updated_by_user_id = :updated_by_user_id,
				    modified = :modified
				WHERE id = :id
			");
			$stmt->execute([
				'tenant_person_id' => $payload['tenant_person_id'],
				'person_id' => $payload['person_id'],
				'activity_type_id' => $payload['activity_type_id'],
				'activity_status_id' => $payload['activity_status_id'],
				'title' => $payload['title'],
				'description' => $payload['description'],
				'scheduled_at' => $payload['scheduled_at'],
				'completed_at' => $payload['completed_at'],
				'responsible_user_id' => $payload['responsible_user_id'],
				'active' => $payload['active'],
				'updated_by_user_id' => $userId,
				'modified' => date('Y-m-d H:i:s'),
				'id' => $activityId,
			]);

			$afterData = $this->findAllByCrmActivityId($activityId, $tenantId);
			$this->auditUpdate('crm_activity', 'crm_activities', $activityId, $beforeData, $afterData);
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

	public function complete($id, int $tenantId, int $userId): void
		{
		$statusId = $this->finalStatusId('completed');
		$this->setFinalStatus($id, $tenantId, $userId, $statusId, 'complete_activity');
		}

	public function cancel($id, int $tenantId, int $userId): void
		{
		$statusId = $this->finalStatusId('canceled');
		$this->setFinalStatus($id, $tenantId, $userId, $statusId, 'cancel_activity');
		}

	public function delete($id, int $tenantId, int $userId): void
		{
		$activityId = (int) $id;
		$beforeData = $this->findAllByCrmActivityId($activityId, $tenantId);
		if (empty($beforeData['crm_activity']))
			{
			throw new \InvalidArgumentException('Atividade CRM não encontrada.');
			}

		try
			{
			$this->db->beginTransaction();
			$stmt = $this->db->prepare("
				UPDATE crm_activities
				SET active = 0,
				    updated_by_user_id = :updated_by_user_id,
				    modified = :modified
				WHERE id = :id
			");
			$stmt->execute([
				'updated_by_user_id' => $userId,
				'modified' => date('Y-m-d H:i:s'),
				'id' => $activityId,
			]);

			$afterData = $this->findAllByCrmActivityId($activityId, $tenantId);
			$this->auditUpdate('crm_activity', 'crm_activities', $activityId, $beforeData, $afterData, [
				'operation' => 'deactivate_activity',
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

	public function crmOptions(int $tenantId, ?int $includeCrmId = null, int $limit = 200): array
		{
		$tenantPeopleTable = $this->managerTable('tenant_people');
		$peopleTable = $this->managerTable('people');
		$params = ['tenant_id' => $tenantId];

		$sql = "
			SELECT TenantPerson.id,
			       TenantPerson.person_id,
			       TenantPerson.active,
			       Person.name,
			       Person.document
			FROM {$tenantPeopleTable} AS TenantPerson
			INNER JOIN {$peopleTable} AS Person ON Person.id = TenantPerson.person_id
			WHERE TenantPerson.tenant_id = :tenant_id
			  AND (TenantPerson.active = 1
		";

		if ($includeCrmId !== null && $includeCrmId > 0)
			{
			$sql .= " OR TenantPerson.id = :include_crm_id";
			$params['include_crm_id'] = $includeCrmId;
			}

		$sql .= ")
			ORDER BY TenantPerson.active DESC, Person.name ASC, TenantPerson.id ASC
			LIMIT :limit
		";

		$stmt = $this->db->prepare($sql);
		$this->bindParams($stmt, $params);
		$stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
		$stmt->execute();

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	private function setFinalStatus($id, int $tenantId, int $userId, int $statusId, string $operation): void
		{
		$activityId = (int) $id;
		$beforeData = $this->findAllByCrmActivityId($activityId, $tenantId);
		if (empty($beforeData['crm_activity']))
			{
			throw new \InvalidArgumentException('Atividade CRM não encontrada.');
			}

		try
			{
			$this->db->beginTransaction();
			$stmt = $this->db->prepare("
				UPDATE crm_activities
				SET activity_status_id = :activity_status_id,
				    completed_at = COALESCE(completed_at, :completed_at),
				    updated_by_user_id = :updated_by_user_id,
				    modified = :modified
				WHERE id = :id
			");
			$stmt->execute([
				'activity_status_id' => $statusId,
				'completed_at' => date('Y-m-d H:i:s'),
				'updated_by_user_id' => $userId,
				'modified' => date('Y-m-d H:i:s'),
				'id' => $activityId,
			]);

			$afterData = $this->findAllByCrmActivityId($activityId, $tenantId);
			$this->auditUpdate('crm_activity', 'crm_activities', $activityId, $beforeData, $afterData, [
				'operation' => $operation,
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

	private function normalizePayload(array $data, int $tenantId, int $userId, ?array $current = null): array
		{
		$crmId = (int) ($data['tenant_person_id'] ?? $data['crm_id'] ?? $current['tenant_person_id'] ?? 0);
		$link = $this->crmLinkById($crmId, $tenantId);
		if (!$link)
			{
			throw new \InvalidArgumentException('Selecione um contato CRM válido para este tenant.');
			}

		$typeId = (int) ($data['activity_type_id'] ?? $current['activity_type_id'] ?? 0);
		if ($typeId <= 0)
			{
			$typeId = $this->defaultActivityTypeId();
			}
		$this->assertActivityType($typeId, isset($current['activity_type_id']) ? (int) $current['activity_type_id'] : null);

		$statusId = (int) ($data['activity_status_id'] ?? $current['activity_status_id'] ?? 0);
		if ($statusId <= 0)
			{
			$statusId = $this->defaultActivityStatusId();
			}
		$status = $this->assertActivityStatus($statusId, isset($current['activity_status_id']) ? (int) $current['activity_status_id'] : null);

		$title = substr(trim((string) ($data['title'] ?? $current['title'] ?? '')), 0, 180);
		if ($title === '')
			{
			throw new \InvalidArgumentException('Informe o título da atividade.');
			}

		$responsibleUserId = (int) ($data['responsible_user_id'] ?? $current['responsible_user_id'] ?? $link['owner_user_id'] ?? $userId);
		$responsibleUserId = $responsibleUserId > 0 ? $responsibleUserId : null;
		$this->assertAssignableUser($responsibleUserId, $tenantId);

		$completedAt = $this->normalizeDateTime($data['completed_at'] ?? $current['completed_at'] ?? null);
		if ((int) ($status['is_final'] ?? 0) === 1 && $completedAt === null)
			{
			$completedAt = date('Y-m-d H:i:s');
			}

		$active = isset($data['active']) ? (int) $data['active'] : (int) ($current['active'] ?? 1);

		return [
			'tenant_person_id' => (int) $link['id'],
			'person_id' => (int) $link['person_id'],
			'activity_type_id' => $typeId,
			'activity_status_id' => $statusId,
			'title' => $title,
			'description' => substr(trim((string) ($data['description'] ?? $current['description'] ?? '')), 0, 3000),
			'scheduled_at' => $this->normalizeDateTime($data['scheduled_at'] ?? $current['scheduled_at'] ?? null),
			'completed_at' => $completedAt,
			'responsible_user_id' => $responsibleUserId,
			'active' => $active === 0 ? 0 : 1,
		];
		}

	private function baseSelectSql(): string
		{
		$tenantPeopleTable = $this->managerTable('tenant_people');
		$peopleTable = $this->managerTable('people');
		$usersTable = $this->managerTable('users');

		return "
			SELECT Activity.id,
			       Activity.tenant_person_id,
			       Activity.person_id,
			       Activity.activity_type_id,
			       Activity.activity_status_id,
			       Activity.title,
			       Activity.description,
			       Activity.scheduled_at,
			       Activity.completed_at,
			       Activity.responsible_user_id,
			       Activity.active,
			       Activity.created_by_user_id,
			       Activity.updated_by_user_id,
			       Activity.created,
			       Activity.modified,
			       TenantPerson.tenant_id,
			       TenantPerson.owner_user_id AS crm_owner_user_id,
			       Person.name AS person_name,
			       Person.document AS person_document,
			       ActivityType.name AS activity_type_name,
			       ActivityType.bootstrap_class AS activity_type_bootstrap_class,
			       ActivityType.icon AS activity_type_icon,
			       ActivityStatus.name AS activity_status_name,
			       ActivityStatus.bootstrap_class AS activity_status_bootstrap_class,
			       ActivityStatus.is_final AS activity_status_is_final,
			       ResponsibleUser.username AS responsible_username,
			       ResponsiblePerson.name AS responsible_name
			FROM crm_activities AS Activity
			INNER JOIN {$tenantPeopleTable} AS TenantPerson
				ON TenantPerson.id = Activity.tenant_person_id
				AND TenantPerson.person_id = Activity.person_id
			INNER JOIN {$peopleTable} AS Person ON Person.id = Activity.person_id
			LEFT JOIN crm_activity_types AS ActivityType ON ActivityType.id = Activity.activity_type_id
			LEFT JOIN crm_activity_statuses AS ActivityStatus ON ActivityStatus.id = Activity.activity_status_id
			LEFT JOIN {$usersTable} AS ResponsibleUser ON ResponsibleUser.id = Activity.responsible_user_id
			LEFT JOIN {$peopleTable} AS ResponsiblePerson ON ResponsiblePerson.id = ResponsibleUser.person_id
		";
		}

	private function baseCountSql(): string
		{
		$tenantPeopleTable = $this->managerTable('tenant_people');
		$peopleTable = $this->managerTable('people');

		return "
			SELECT COUNT(*) AS total
			FROM crm_activities AS Activity
			INNER JOIN {$tenantPeopleTable} AS TenantPerson
				ON TenantPerson.id = Activity.tenant_person_id
				AND TenantPerson.person_id = Activity.person_id
			INNER JOIN {$peopleTable} AS Person ON Person.id = Activity.person_id
			LEFT JOIN crm_activity_statuses AS ActivityStatus ON ActivityStatus.id = Activity.activity_status_id
		";
		}

	private function appendFilters(string &$sql, array &$params, array $filters): void
		{
		if (isset($filters['id']) && $filters['id'] !== '')
			{
			$sql .= " AND Activity.id = :id";
			$params['id'] = (int) $filters['id'];
			}

		if (isset($filters['tenant_person_id']) && $filters['tenant_person_id'] !== '')
			{
			$sql .= " AND Activity.tenant_person_id = :tenant_person_id";
			$params['tenant_person_id'] = (int) $filters['tenant_person_id'];
			}

		if (isset($filters['person_name']) && $filters['person_name'] !== '')
			{
			$sql .= " AND Person.name LIKE :person_name";
			$params['person_name'] = '%' . trim((string) $filters['person_name']) . '%';
			}

		if (isset($filters['activity_type_id']) && $filters['activity_type_id'] !== '')
			{
			$sql .= " AND Activity.activity_type_id = :activity_type_id";
			$params['activity_type_id'] = (int) $filters['activity_type_id'];
			}

		if (isset($filters['activity_status_id']) && $filters['activity_status_id'] !== '')
			{
			$sql .= " AND Activity.activity_status_id = :activity_status_id";
			$params['activity_status_id'] = (int) $filters['activity_status_id'];
			}

		if (isset($filters['responsible_user_id']) && $filters['responsible_user_id'] !== '')
			{
			$sql .= " AND Activity.responsible_user_id = :responsible_user_id";
			$params['responsible_user_id'] = (int) $filters['responsible_user_id'];
			}

		if (isset($filters['scheduled_from']) && $filters['scheduled_from'] !== '')
			{
			$sql .= " AND Activity.scheduled_at >= :scheduled_from";
			$params['scheduled_from'] = $this->normalizeDateBoundary((string) $filters['scheduled_from'], false);
			}

		if (isset($filters['scheduled_to']) && $filters['scheduled_to'] !== '')
			{
			$sql .= " AND Activity.scheduled_at <= :scheduled_to";
			$params['scheduled_to'] = $this->normalizeDateBoundary((string) $filters['scheduled_to'], true);
			}

		if (isset($filters['activity_scope']) && isset(self::activityScopes()[(string) $filters['activity_scope']]))
			{
			$scope = (string) $filters['activity_scope'];
			if ($scope === self::ACTIVITY_SCOPE_PENDING)
				{
				$sql .= " AND " . $this->pendingActivitySql();
				}
			elseif ($scope === self::ACTIVITY_SCOPE_OVERDUE)
				{
				$sql .= " AND " . $this->pendingActivitySql() . " AND Activity.scheduled_at < NOW()";
				}
			elseif ($scope === self::ACTIVITY_SCOPE_TODAY)
				{
				$sql .= " AND " . $this->pendingActivitySql() . " AND DATE(Activity.scheduled_at) = CURDATE()";
				}
			elseif ($scope === self::ACTIVITY_SCOPE_COMPLETED)
				{
				$sql .= " AND (Activity.completed_at IS NOT NULL OR COALESCE(ActivityStatus.is_final, 0) = 1)";
				}
			}

		if (isset($filters['active']) && $filters['active'] !== '')
			{
			$active = (int) $filters['active'];
			if (in_array($active, [0, 1], true))
				{
				$sql .= " AND Activity.active = :active";
				$params['active'] = $active;
				}
			}
		}

	private function orderBySql(string $sort, string $direction): string
		{
		$columns = [
			'id' => 'Activity.id',
			'person_name' => 'Person.name',
			'activity_type' => 'ActivityType.name',
			'activity_status' => 'ActivityStatus.name',
			'responsible' => 'ResponsiblePerson.name',
			'scheduled_at' => 'Activity.scheduled_at',
			'completed_at' => 'Activity.completed_at',
			'created' => 'Activity.created',
			'modified' => 'Activity.modified',
		];
		$column = $columns[$sort] ?? $columns['scheduled_at'];
		$dir = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';

		return " ORDER BY {$column} {$dir}, Activity.id DESC";
		}

	private function dashboardCards(int $tenantId): array
		{
		$tenantPeopleTable = $this->managerTable('tenant_people');
		$pendingSql = $this->pendingActivitySql();
		$cards = [
			'active_records' => 0,
			'without_owner' => 0,
			'without_activity' => 0,
			'overdue_activities' => 0,
			'today_activities' => 0,
		];

		$stmt = $this->db->prepare("
			SELECT
				SUM(CASE WHEN TenantPerson.active = 1 THEN 1 ELSE 0 END) AS active_records,
				SUM(CASE WHEN TenantPerson.active = 1 AND TenantPerson.owner_user_id IS NULL THEN 1 ELSE 0 END) AS without_owner,
				SUM(CASE WHEN TenantPerson.active = 1 AND NOT EXISTS (
					SELECT 1 FROM crm_activities AS Activity
					WHERE Activity.tenant_person_id = TenantPerson.id
					  AND Activity.active = 1
				) THEN 1 ELSE 0 END) AS without_activity
			FROM {$tenantPeopleTable} AS TenantPerson
			WHERE TenantPerson.tenant_id = :tenant_id
		");
		$stmt->execute(['tenant_id' => $tenantId]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
		$cards['active_records'] = (int) ($row['active_records'] ?? 0);
		$cards['without_owner'] = (int) ($row['without_owner'] ?? 0);
		$cards['without_activity'] = (int) ($row['without_activity'] ?? 0);

		$stmt = $this->db->prepare("
			SELECT
				SUM(CASE WHEN {$pendingSql} AND Activity.scheduled_at < NOW() THEN 1 ELSE 0 END) AS overdue_activities,
				SUM(CASE WHEN {$pendingSql} AND DATE(Activity.scheduled_at) = CURDATE() THEN 1 ELSE 0 END) AS today_activities
			FROM crm_activities AS Activity
			INNER JOIN {$tenantPeopleTable} AS TenantPerson
				ON TenantPerson.id = Activity.tenant_person_id
				AND TenantPerson.person_id = Activity.person_id
			LEFT JOIN crm_activity_statuses AS ActivityStatus ON ActivityStatus.id = Activity.activity_status_id
			WHERE TenantPerson.tenant_id = :tenant_id
		");
		$stmt->execute(['tenant_id' => $tenantId]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
		$cards['overdue_activities'] = (int) ($row['overdue_activities'] ?? 0);
		$cards['today_activities'] = (int) ($row['today_activities'] ?? 0);

		return $cards;
		}

	private function statusSummary(int $tenantId): array
		{
		$tenantPeopleTable = $this->managerTable('tenant_people');

		$stmt = $this->db->prepare("
			SELECT CrmStatus.id,
			       CrmStatus.name,
			       CrmStatus.bootstrap_class,
			       COUNT(TenantPerson.id) AS total
			FROM crm_statuses AS CrmStatus
			LEFT JOIN {$tenantPeopleTable} AS TenantPerson
				ON TenantPerson.crm_status_id = CrmStatus.id
				AND TenantPerson.tenant_id = :tenant_id
				AND TenantPerson.active = 1
			WHERE CrmStatus.active = 1
			GROUP BY CrmStatus.id, CrmStatus.name, CrmStatus.bootstrap_class, CrmStatus.sort_order
			ORDER BY CrmStatus.sort_order ASC, CrmStatus.name ASC
		");
		$stmt->execute(['tenant_id' => $tenantId]);

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	private function upcomingActivities(int $tenantId, int $limit): array
		{
		$sql = $this->baseSelectSql() . "
			WHERE TenantPerson.tenant_id = :tenant_id
			  AND " . $this->pendingActivitySql() . "
			  AND Activity.scheduled_at IS NOT NULL
			ORDER BY Activity.scheduled_at ASC, Activity.id ASC
			LIMIT :limit
		";
		$stmt = $this->db->prepare($sql);
		$stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
		$stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
		$stmt->execute();

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	private function recentActivities(int $tenantId, int $limit): array
		{
		$sql = $this->baseSelectSql() . "
			WHERE TenantPerson.tenant_id = :tenant_id
			  AND Activity.active = 1
			ORDER BY Activity.created DESC, Activity.id DESC
			LIMIT :limit
		";
		$stmt = $this->db->prepare($sql);
		$stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
		$stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
		$stmt->execute();

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	private function pendingActivitySql(): string
		{
		return "(Activity.active = 1 AND Activity.completed_at IS NULL AND COALESCE(ActivityStatus.is_final, 0) = 0)";
		}

	private function crmLinkById(int $crmId, int $tenantId): ?array
		{
		if ($crmId <= 0)
			{
			return null;
			}

		$stmt = $this->db->prepare("
			SELECT id, tenant_id, person_id, owner_user_id, active
			FROM " . $this->managerTable('tenant_people') . "
			WHERE id = :id
			  AND tenant_id = :tenant_id
			LIMIT 1
		");
		$stmt->execute([
			'id' => $crmId,
			'tenant_id' => $tenantId,
		]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);

		return $row ?: null;
		}

	private function assertActivityType(int $typeId, ?int $currentTypeId = null): void
		{
		$stmt = $this->db->prepare("
			SELECT id
			FROM crm_activity_types
			WHERE id = :id
			  AND (active = 1 OR id = :current_id)
			LIMIT 1
		");
		$stmt->execute([
			'id' => $typeId,
			'current_id' => $currentTypeId ?? 0,
		]);

		if (!$stmt->fetchColumn())
			{
			throw new \InvalidArgumentException('Selecione um tipo de atividade válido.');
			}
		}

	private function assertActivityStatus(int $statusId, ?int $currentStatusId = null): array
		{
		$stmt = $this->db->prepare("
			SELECT id, is_final
			FROM crm_activity_statuses
			WHERE id = :id
			  AND (active = 1 OR id = :current_id)
			LIMIT 1
		");
		$stmt->execute([
			'id' => $statusId,
			'current_id' => $currentStatusId ?? 0,
		]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		if (!$row)
			{
			throw new \InvalidArgumentException('Selecione um status de atividade válido.');
			}

		return $row;
		}

	private function defaultActivityTypeId(): int
		{
		$stmt = $this->db->query("
			SELECT id
			FROM crm_activity_types
			WHERE active = 1
			ORDER BY sort_order ASC, id ASC
			LIMIT 1
		");
		$id = $stmt->fetchColumn();
		if (!$id)
			{
			throw new \InvalidArgumentException('Cadastre ao menos um tipo de atividade CRM ativo.');
			}

		return (int) $id;
		}

	private function defaultActivityStatusId(): int
		{
		$stmt = $this->db->query("
			SELECT id
			FROM crm_activity_statuses
			WHERE active = 1
			ORDER BY CASE WHEN slug = 'pending' THEN 0 ELSE 1 END, sort_order ASC, id ASC
			LIMIT 1
		");
		$id = $stmt->fetchColumn();
		if (!$id)
			{
			throw new \InvalidArgumentException('Cadastre ao menos um status de atividade CRM ativo.');
			}

		return (int) $id;
		}

	private function finalStatusId(string $preferredSlug): int
		{
		$stmt = $this->db->prepare("
			SELECT id
			FROM crm_activity_statuses
			WHERE active = 1
			  AND is_final = 1
			ORDER BY CASE WHEN slug = :preferred_slug THEN 0 ELSE 1 END, sort_order ASC, id ASC
			LIMIT 1
		");
		$stmt->execute(['preferred_slug' => $preferredSlug]);
		$id = $stmt->fetchColumn();
		if (!$id)
			{
			throw new \InvalidArgumentException('Cadastre ao menos um status final de atividade CRM.');
			}

		return (int) $id;
		}

	private function assertAssignableUser(?int $userId, int $tenantId): void
		{
		if ($userId === null)
			{
			return;
			}

		$stmt = $this->db->prepare("
			SELECT User.id
			FROM " . $this->managerTable('users') . " AS User
			INNER JOIN " . $this->managerTable('tenant_users') . " AS TenantUser
				ON TenantUser.user_id = User.id
				AND TenantUser.tenant_id = :tenant_id
			WHERE User.id = :user_id
			  AND User.active = 1
			  AND User.type IN ('admin', 'user')
			LIMIT 1
		");
		$stmt->execute([
			'tenant_id' => $tenantId,
			'user_id' => $userId,
		]);

		if (!$stmt->fetchColumn())
			{
			throw new \InvalidArgumentException('Selecione um responsável válido para este tenant.');
			}
		}

	private function normalizeDateTime($value): ?string
		{
		$value = trim((string) ($value ?? ''));
		if ($value === '' || $value === '0000-00-00 00:00:00')
			{
			return null;
			}

		$formats = ['!Y-m-d\TH:i', '!Y-m-d H:i:s', '!Y-m-d H:i', '!d/m/Y H:i'];
		foreach ($formats as $format)
			{
			$date = \DateTimeImmutable::createFromFormat($format, $value);
			$errors = \DateTimeImmutable::getLastErrors();
			$hasErrors = is_array($errors)
				&& (((int) ($errors['warning_count'] ?? 0) > 0) || ((int) ($errors['error_count'] ?? 0) > 0));
			if ($date !== false && !$hasErrors)
				{
				return $date->format('Y-m-d H:i:s');
				}
			}

		throw new \InvalidArgumentException('Informe uma data e hora válidas.');
		}

	private function normalizeDateBoundary(string $value, bool $endOfDay): string
		{
		$value = trim($value);
		$date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
		$errors = \DateTimeImmutable::getLastErrors();
		$hasErrors = is_array($errors)
			&& (((int) ($errors['warning_count'] ?? 0) > 0) || ((int) ($errors['error_count'] ?? 0) > 0));
		if ($date === false || $hasErrors)
			{
			throw new \InvalidArgumentException('Informe um período válido.');
			}

		return $date->format('Y-m-d') . ($endOfDay ? ' 23:59:59' : ' 00:00:00');
		}

	private function managerTable(string $table): string
		{
		return sprintf('`%s`.`%s`', Database::getManagerDatabaseName(), trim($table));
		}

	private function bindParams(\PDOStatement $stmt, array $params): void
		{
		foreach ($params as $key => $value)
			{
			$stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
			}
		}
	}
?>
