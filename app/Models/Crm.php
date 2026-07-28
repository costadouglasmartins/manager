<?php

namespace App\Models;

use App\Core\Model;
use App\Core\TenantContext;
use PDO;

class Crm extends Model
	{
	public const STATUS_LEAD = 'lead';
	public const STATUS_PROSPECT = 'prospect';
	public const STATUS_CUSTOMER = 'customer';
	public const STATUS_INACTIVE = 'inactive';

	/**
	 * Returns CRM status metadata used by screens and reports.
	 */
	public static function statuses(): array
		{
		return [
			self::STATUS_LEAD => ['label' => 'Lead', 'badge' => 'text-bg-primary'],
			self::STATUS_PROSPECT => ['label' => 'Prospect', 'badge' => 'text-bg-info'],
			self::STATUS_CUSTOMER => ['label' => 'Cliente', 'badge' => 'text-bg-success'],
			self::STATUS_INACTIVE => ['label' => 'Inativo', 'badge' => 'text-bg-secondary'],
		];
		}

	/**
	 * Returns a CRM status label.
	 */
	public static function statusLabel(?string $status): string
		{
		return (string) (self::statuses()[(string) $status]['label'] ?? (string) $status);
		}

	/**
	 * Returns a CRM status badge class.
	 */
	public static function statusBadgeClass(?string $status): string
		{
		return (string) (self::statuses()[(string) $status]['badge'] ?? 'text-bg-secondary');
		}

	/**
	 * Returns person type labels.
	 */
	public static function personTypes(): array
		{
		return [
			'individual' => 'Pessoa física',
			'company' => 'Pessoa jurídica',
		];
		}

	/**
	 * Returns a person type label.
	 */
	public static function personTypeLabel(?string $type): string
		{
		return (string) (self::personTypes()[(string) $type] ?? (string) $type);
		}

	/**
	 * Formats a nullable date time value.
	 */
	public static function formatDateTime($value): string
		{
		$value = trim((string) ($value ?? ''));
		if ($value === '' || $value === '0000-00-00 00:00:00')
			{
			return '';
			}

		try
			{
			return (new \DateTimeImmutable($value))->format('d/m/Y H:i');
			}
		catch (\Throwable $e)
			{
			return $value;
			}
		}

	/**
	 * Searches CRM people scoped to the selected tenant.
	 */
	public function search(array $filters = [], int $limit = 10, int $offset = 0, ?int $tenantId = null, string $sort = 'modified', string $direction = 'desc'): array
		{
		$sql = $this->baseSelectSql() . "
			WHERE TenantPerson.tenant_id = :tenant_id
		";
		$params = ['tenant_id' => (int) $tenantId];

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

	/**
	 * Counts CRM people scoped to the selected tenant.
	 */
	public function count(array $filters = [], ?int $tenantId = null): int
		{
		$tenantPeopleTable = $this->managerTable('tenant_people');
		$crmStatusesTable = $this->tenantTable('crm_statuses');
		$peopleTable = $this->managerTable('people');
		$sql = "
			SELECT COUNT(*) AS total
			FROM {$tenantPeopleTable} AS TenantPerson
			INNER JOIN {$peopleTable} AS Person ON Person.id = TenantPerson.person_id
			LEFT JOIN {$crmStatusesTable} AS CrmStatus
				ON CrmStatus.id = TenantPerson.crm_status_id
			WHERE TenantPerson.tenant_id = :tenant_id
		";
		$params = ['tenant_id' => (int) $tenantId];

		$this->appendFilters($sql, $params, $filters);

		$stmt = $this->db->prepare($sql);
		$this->bindParams($stmt, $params);
		$stmt->execute();

		return (int) $stmt->fetchColumn();
		}

	/**
	 * Creates a CRM tenant-person link, creating the person when needed.
	 */
	public function create(array $data, int $tenantId, int $userId): array
		{
		$person = $this->normalizePersonPayload((array) ($data['person'] ?? []));
		$crm = $this->normalizeCrmPayload((array) ($data['crm'] ?? []), $tenantId);
		$contact = (array) ($data['contact'] ?? []);
		$now = date('Y-m-d H:i:s');

		$this->assertAssignableOwner($crm['owner_user_id'], $tenantId);

		try
			{
			$this->db->beginTransaction();

			$personId = $this->findPersonIdByDocument($person['document']);
			$createdPerson = false;
			if ($personId === null)
				{
				$personId = $this->insertPerson($person, $now);
				$this->insertInitialContact($personId, $contact, $now);
				$createdPerson = true;
				}

			$existing = $this->findLinkByTenantAndPerson($tenantId, $personId);
			if ($existing)
				{
				$crmId = (int) $existing['id'];
				$beforeData = $this->findAllByCrmId($crmId, $tenantId);
				$this->updateLink($crmId, $tenantId, $crm, $userId, $now);
				$afterData = $this->findAllByCrmId($crmId, $tenantId);
				$this->auditUpdate('crm', 'tenant_people', $crmId, $beforeData, $afterData, [
					'affected_tables' => ['tenant_people'],
					'reused_person' => true,
				]);
				$this->db->commit();

				return ['crm_id' => $crmId, 'person_id' => $personId, 'created_person' => $createdPerson];
				}

			$crmId = $this->insertLink($tenantId, $personId, $crm, $userId, $now);
			$afterData = $this->findAllByCrmId($crmId, $tenantId);
			$this->auditCreate('crm', 'tenant_people', $crmId, $afterData, [
				'affected_tables' => $createdPerson ? ['people', 'emails', 'phone_numbers', 'tenant_people'] : ['tenant_people'],
				'created_person' => $createdPerson,
			]);
			$this->db->commit();

			return ['crm_id' => $crmId, 'person_id' => $personId, 'created_person' => $createdPerson];
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
	 * Updates CRM metadata scoped to the selected tenant.
	 */
	public function update($id, array $data, int $tenantId, int $userId): void
		{
		$crmId = (int) $id;
		if ($crmId <= 0)
			{
			throw new \InvalidArgumentException('Registro CRM inválido.');
			}

		$beforeData = $this->findAllByCrmId($crmId, $tenantId);
		if (empty($beforeData['crm']))
			{
			throw new \InvalidArgumentException('Registro CRM não encontrado.');
			}

		$crm = $this->normalizeCrmPayload((array) ($data['crm'] ?? []), $tenantId, (int) ($beforeData['crm']['crm_status_id'] ?? 0));
		$this->assertAssignableOwner($crm['owner_user_id'], $tenantId, (int) ($beforeData['crm']['owner_user_id'] ?? 0));

		try
			{
			$this->db->beginTransaction();
			$this->updateLink($crmId, $tenantId, $crm, $userId, date('Y-m-d H:i:s'));
			$afterData = $this->findAllByCrmId($crmId, $tenantId);
			$this->auditUpdate('crm', 'tenant_people', $crmId, $beforeData, $afterData, [
				'affected_tables' => ['tenant_people'],
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
	 * Deactivates the CRM link without deleting the global person.
	 */
	public function delete($id, int $tenantId, int $userId): void
		{
		$crmId = (int) $id;
		if ($crmId <= 0)
			{
			throw new \InvalidArgumentException('Registro CRM inválido.');
			}

		$beforeData = $this->findAllByCrmId($crmId, $tenantId);
		if (empty($beforeData['crm']))
			{
			throw new \InvalidArgumentException('Registro CRM não encontrado.');
			}

		try
			{
			$this->db->beginTransaction();
			$inactiveStatusId = (new CrmStatus())->inactiveStatusId();
			$stmt = $this->db->prepare("
				UPDATE " . $this->managerTable('tenant_people') . "
				SET active = 0,
				    crm_status_id = COALESCE(:crm_status_id, crm_status_id),
				    updated_by_user_id = :updated_by_user_id,
				    modified = :modified
				WHERE id = :id
				  AND tenant_id = :tenant_id
			");
			$stmt->execute([
				'crm_status_id' => $inactiveStatusId,
				'updated_by_user_id' => $userId,
				'modified' => date('Y-m-d H:i:s'),
				'id' => $crmId,
				'tenant_id' => $tenantId,
			]);

			$afterData = $this->findAllByCrmId($crmId, $tenantId);
			$this->auditUpdate('crm', 'tenant_people', $crmId, $beforeData, $afterData, [
				'affected_tables' => ['tenant_people'],
				'operation' => 'deactivate_link',
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
	 * Finds a CRM record and related person data scoped to the selected tenant.
	 */
	public function findAllByCrmId($id, int $tenantId): array
		{
		$stmt = $this->db->prepare($this->baseSelectSql() . "
			WHERE TenantPerson.id = :id
			  AND TenantPerson.tenant_id = :tenant_id
			LIMIT 1
		");
		$stmt->execute([
			'id' => (int) $id,
			'tenant_id' => $tenantId,
		]);
		$crm = $stmt->fetch(PDO::FETCH_ASSOC);

		if (!$crm)
			{
			return ['crm' => null, 'emails' => [], 'phones' => [], 'addresses' => []];
			}

		$personId = (int) ($crm['person_id'] ?? 0);
		return [
			'crm' => $crm,
			'emails' => $this->emailsByPersonId($personId),
			'phones' => $this->phonesByPersonId($personId),
			'addresses' => $this->addressesByPersonId($personId),
		];
		}

	/**
	 * Returns tenant users eligible to own CRM records.
	 */
	public function ownerOptions(int $tenantId, ?int $includeUserId = null): array
		{
		$usersTable = $this->managerTable('users');
		$peopleTable = $this->managerTable('people');
		$tenantUsersTable = $this->managerTable('tenant_users');
		$params = ['tenant_id' => $tenantId];

		$sql = "
			SELECT User.id,
			       User.username,
			       User.type,
			       User.active,
			       Person.name AS person_name,
			       CASE WHEN User.active = 1 AND User.type IN ('admin', 'user') THEN 1 ELSE 0 END AS assignable
			FROM {$usersTable} AS User
			INNER JOIN {$tenantUsersTable} AS TenantUser
				ON TenantUser.user_id = User.id
				AND TenantUser.tenant_id = :tenant_id
			INNER JOIN {$peopleTable} AS Person ON Person.id = User.person_id
			WHERE (User.type IN ('admin', 'user')
		";

		if ($includeUserId !== null && $includeUserId > 0)
			{
			$sql .= " OR User.id = :include_user_id";
			$params['include_user_id'] = $includeUserId;
			}

		$sql .= ")
			ORDER BY assignable DESC, Person.name ASC, User.username ASC
		";

		$stmt = $this->db->prepare($sql);
		$stmt->execute($params);

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Returns CRM status options available for the current tenant.
	 */
	public function statusOptions(int $tenantId, ?int $includeStatusId = null): array
		{
		return (new CrmStatus())->getOptions(true, $includeStatusId);
		}

	/**
	 * Returns a table qualified with the current tenant database name.
	 */
	private function tenantTable(string $table): string
		{
		$databaseName = TenantContext::databaseName();
		if (!$databaseName || !preg_match('/^[A-Za-z0-9_]+$/', $databaseName))
			{
			throw new \InvalidArgumentException('Tenant inválido para consulta do CRM.');
			}

		return sprintf('`%s`.`%s`', $databaseName, trim($table));
		}

	/**
	 * Builds the base CRM select query.
	 */
	private function baseSelectSql(): string
		{
		$tenantPeopleTable = $this->managerTable('tenant_people');
		$crmStatusesTable = $this->tenantTable('crm_statuses');
		$peopleTable = $this->managerTable('people');
		$usersTable = $this->managerTable('users');
		$emailsTable = $this->managerTable('emails');
		$phoneNumbersTable = $this->managerTable('phone_numbers');
		$crmActivitiesTable = $this->tenantTable('crm_activities');
		$activityStatusesTable = $this->tenantTable('crm_activity_statuses');

		return "
			SELECT TenantPerson.id,
			       TenantPerson.tenant_id,
			       TenantPerson.person_id,
			       TenantPerson.crm_status_id,
			       TenantPerson.owner_user_id,
			       TenantPerson.source,
			       TenantPerson.notes,
			       TenantPerson.active,
			       TenantPerson.created_by_user_id,
			       TenantPerson.updated_by_user_id,
			       TenantPerson.created,
			       TenantPerson.modified,
			       Person.name,
			       Person.fantasy,
			       Person.type,
			       Person.document,
			       Person.birthdate,
			       Person.active AS person_active,
			       CrmStatus.name AS crm_status_name,
			       CrmStatus.slug AS crm_status_slug,
			       CrmStatus.bootstrap_class AS crm_status_bootstrap_class,
			       CrmStatus.active AS crm_status_active,
			       OwnerUser.username AS owner_username,
			       OwnerPerson.name AS owner_name,
			       (
				SELECT Activity.scheduled_at
				FROM {$crmActivitiesTable} AS Activity
				LEFT JOIN {$activityStatusesTable} AS ActivityStatus ON ActivityStatus.id = Activity.activity_status_id
				WHERE Activity.tenant_person_id = TenantPerson.id
				  AND Activity.active = 1
				  AND Activity.completed_at IS NULL
				  AND COALESCE(ActivityStatus.is_final, 0) = 0
				ORDER BY Activity.scheduled_at IS NULL ASC, Activity.scheduled_at ASC, Activity.id ASC
				LIMIT 1
			       ) AS next_activity_at,
			       (
				SELECT Activity.title
				FROM {$crmActivitiesTable} AS Activity
				LEFT JOIN {$activityStatusesTable} AS ActivityStatus ON ActivityStatus.id = Activity.activity_status_id
				WHERE Activity.tenant_person_id = TenantPerson.id
				  AND Activity.active = 1
				  AND Activity.completed_at IS NULL
				  AND COALESCE(ActivityStatus.is_final, 0) = 0
				ORDER BY Activity.scheduled_at IS NULL ASC, Activity.scheduled_at ASC, Activity.id ASC
				LIMIT 1
			       ) AS next_activity_title,
			       (
			       	SELECT Email.email
			       	FROM {$emailsTable} AS Email
			       	WHERE Email.person_id = Person.id
			       	ORDER BY Email.is_login DESC, Email.id ASC
			       	LIMIT 1
			       ) AS primary_email,
			       (
			       	SELECT PhoneNumber.phone_number
			       	FROM {$phoneNumbersTable} AS PhoneNumber
			       	WHERE PhoneNumber.person_id = Person.id
			       	ORDER BY PhoneNumber.id ASC
			       	LIMIT 1
			       ) AS primary_phone
			FROM {$tenantPeopleTable} AS TenantPerson
			INNER JOIN {$peopleTable} AS Person ON Person.id = TenantPerson.person_id
			LEFT JOIN {$crmStatusesTable} AS CrmStatus
				ON CrmStatus.id = TenantPerson.crm_status_id
			LEFT JOIN {$usersTable} AS OwnerUser ON OwnerUser.id = TenantPerson.owner_user_id
			LEFT JOIN {$peopleTable} AS OwnerPerson ON OwnerPerson.id = OwnerUser.person_id
		";
		}

	/**
	 * Appends filters to CRM queries.
	 */
	private function appendFilters(string &$sql, array &$params, array $filters): void
		{
		if (isset($filters['id']) && $filters['id'] !== '')
			{
			$sql .= " AND TenantPerson.id = :id";
			$params['id'] = (int) $filters['id'];
			}

		if (isset($filters['name']) && $filters['name'] !== '')
			{
			$sql .= " AND Person.name LIKE :name";
			$params['name'] = '%' . trim((string) $filters['name']) . '%';
			}

		if (isset($filters['document']) && $filters['document'] !== '')
			{
			$sql .= " AND Person.document LIKE :document";
			$params['document'] = '%' . cleanCpfCnpj((string) $filters['document']) . '%';
			}

		if (isset($filters['type']) && $filters['type'] !== '' && isset(self::personTypes()[$filters['type']]))
			{
			$sql .= " AND Person.type = :type";
			$params['type'] = (string) $filters['type'];
			}

		if (isset($filters['crm_status_id']) && $filters['crm_status_id'] !== '')
			{
			$sql .= " AND TenantPerson.crm_status_id = :crm_status_id";
			$params['crm_status_id'] = (int) $filters['crm_status_id'];
			}

		if (isset($filters['owner_user_id']) && $filters['owner_user_id'] !== '')
			{
			$sql .= " AND TenantPerson.owner_user_id = :owner_user_id";
			$params['owner_user_id'] = (int) $filters['owner_user_id'];
			}

		if (isset($filters['source']) && $filters['source'] !== '')
			{
			$sql .= " AND TenantPerson.source LIKE :source";
			$params['source'] = '%' . trim((string) $filters['source']) . '%';
			}

		if (isset($filters['next_activity_from']) && $filters['next_activity_from'] !== '')
			{
			$sql .= " AND EXISTS (
				SELECT 1
				FROM " . $this->tenantTable('crm_activities') . " AS FilterActivity
				LEFT JOIN " . $this->tenantTable('crm_activity_statuses') . " AS FilterActivityStatus
					ON FilterActivityStatus.id = FilterActivity.activity_status_id
				WHERE FilterActivity.tenant_person_id = TenantPerson.id
				  AND FilterActivity.active = 1
				  AND FilterActivity.completed_at IS NULL
				  AND COALESCE(FilterActivityStatus.is_final, 0) = 0
				  AND FilterActivity.scheduled_at >= :next_activity_from
			)";
			$params['next_activity_from'] = $this->normalizeDateBoundary((string) $filters['next_activity_from'], false);
			}

		if (isset($filters['next_activity_to']) && $filters['next_activity_to'] !== '')
			{
			$sql .= " AND EXISTS (
				SELECT 1
				FROM " . $this->tenantTable('crm_activities') . " AS FilterActivity
				LEFT JOIN " . $this->tenantTable('crm_activity_statuses') . " AS FilterActivityStatus
					ON FilterActivityStatus.id = FilterActivity.activity_status_id
				WHERE FilterActivity.tenant_person_id = TenantPerson.id
				  AND FilterActivity.active = 1
				  AND FilterActivity.completed_at IS NULL
				  AND COALESCE(FilterActivityStatus.is_final, 0) = 0
				  AND FilterActivity.scheduled_at <= :next_activity_to
			)";
			$params['next_activity_to'] = $this->normalizeDateBoundary((string) $filters['next_activity_to'], true);
			}

		if (isset($filters['activity_scope']) && isset(CrmActivity::activityScopes()[(string) $filters['activity_scope']]))
			{
			$activityTable = $this->tenantTable('crm_activities');
			$statusTable = $this->tenantTable('crm_activity_statuses');
			$scope = (string) $filters['activity_scope'];
			if ($scope === 'without_activity')
				{
				$sql .= " AND NOT EXISTS (
					SELECT 1
					FROM {$activityTable} AS ScopeActivity
					WHERE ScopeActivity.tenant_person_id = TenantPerson.id
					  AND ScopeActivity.active = 1
				)";
				}
			else
				{
				$scopeCondition = "ScopeActivity.active = 1";
				if ($scope === 'pending')
					{
					$scopeCondition .= " AND ScopeActivity.completed_at IS NULL AND COALESCE(ScopeActivityStatus.is_final, 0) = 0";
					}
				elseif ($scope === 'overdue')
					{
					$scopeCondition .= " AND ScopeActivity.completed_at IS NULL AND COALESCE(ScopeActivityStatus.is_final, 0) = 0 AND ScopeActivity.scheduled_at < NOW()";
					}
				elseif ($scope === 'today')
					{
					$scopeCondition .= " AND ScopeActivity.completed_at IS NULL AND COALESCE(ScopeActivityStatus.is_final, 0) = 0 AND DATE(ScopeActivity.scheduled_at) = CURDATE()";
					}
				elseif ($scope === 'completed')
					{
					$scopeCondition .= " AND (ScopeActivity.completed_at IS NOT NULL OR COALESCE(ScopeActivityStatus.is_final, 0) = 1)";
					}

				$sql .= " AND EXISTS (
					SELECT 1
					FROM {$activityTable} AS ScopeActivity
					LEFT JOIN {$statusTable} AS ScopeActivityStatus
						ON ScopeActivityStatus.id = ScopeActivity.activity_status_id
					WHERE ScopeActivity.tenant_person_id = TenantPerson.id
					  AND {$scopeCondition}
				)";
				}
			}

		if (isset($filters['active']) && $filters['active'] !== '')
			{
			$active = (int) $filters['active'];
			if (in_array($active, [0, 1], true))
				{
				$sql .= " AND TenantPerson.active = :active";
				$params['active'] = $active;
				}
			}
		}

	/**
	 * Builds safe ordering SQL.
	 */
	private function orderBySql(string $sort, string $direction): string
		{
		$columns = [
			'id' => 'TenantPerson.id',
			'name' => 'Person.name',
			'document' => 'Person.document',
			'crm_status' => 'CrmStatus.name',
			'owner' => 'OwnerPerson.name',
			'next_activity' => 'next_activity_at',
			'created' => 'TenantPerson.created',
			'modified' => 'TenantPerson.modified',
		];
		$column = $columns[$sort] ?? $columns['modified'];
		$dir = strtolower($direction) === 'asc' ? 'ASC' : 'DESC';

		return " ORDER BY {$column} {$dir}, TenantPerson.id DESC";
		}

	private function normalizeDateBoundary(string $value, bool $endOfDay): string
		{
		$date = \DateTimeImmutable::createFromFormat('!Y-m-d', trim($value));
		$errors = \DateTimeImmutable::getLastErrors();
		$hasErrors = is_array($errors)
			&& (((int) ($errors['warning_count'] ?? 0) > 0) || ((int) ($errors['error_count'] ?? 0) > 0));
		if ($date === false || $hasErrors)
			{
			throw new \InvalidArgumentException('Informe um período de próxima atividade válido.');
			}

		return $date->format('Y-m-d') . ($endOfDay ? ' 23:59:59' : ' 00:00:00');
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
	 * Normalizes the person payload.
	 */
	private function normalizePersonPayload(array $data): array
		{
		$name = trim((string) ($data['name'] ?? ''));
		$type = trim((string) ($data['type'] ?? ''));
		$document = cleanCpfCnpj((string) ($data['document'] ?? ''));

		if ($name === '')
			{
			throw new \InvalidArgumentException('Informe o nome da pessoa.');
			}

		if (!isset(self::personTypes()[$type]))
			{
			throw new \InvalidArgumentException('Selecione um tipo de pessoa válido.');
			}

		if ($document === '')
			{
			throw new \InvalidArgumentException('Informe o CPF ou CNPJ.');
			}

		return [
			'name' => $name,
			'fantasy' => trim((string) ($data['fantasy'] ?? '')),
			'type' => $type,
			'document' => $document,
			'birthdate' => $this->normalizeDate($data['birthdate'] ?? null),
		];
		}

	/**
	 * Normalizes CRM metadata.
	 */
	private function normalizeCrmPayload(array $data, int $tenantId, ?int $currentStatusId = null): array
		{
		$statusModel = new CrmStatus();
		$statusId = (int) ($data['crm_status_id'] ?? 0);
		if ($statusId <= 0)
			{
			$statusId = (int) ($statusModel->defaultStatusId() ?? 0);
			}

		if (!$statusModel->isAssignable($statusId, $currentStatusId))
			{
			throw new \InvalidArgumentException('Selecione uma situação de CRM válida.');
			}

		$ownerUserId = (int) ($data['owner_user_id'] ?? 0);
		$active = isset($data['active']) ? (int) $data['active'] : 1;

		return [
			'crm_status_id' => $statusId,
			'owner_user_id' => $ownerUserId > 0 ? $ownerUserId : null,
			'source' => substr(trim((string) ($data['source'] ?? '')), 0, 120),
			'notes' => substr(trim((string) ($data['notes'] ?? '')), 0, 2000),
			'active' => $active === 0 ? 0 : 1,
		];
		}

	/**
	 * Normalizes date input from YYYY-MM-DD or DD/MM/YYYY.
	 */
	private function normalizeDate($value): ?string
		{
		$value = trim((string) ($value ?? ''));
		if ($value === '')
			{
			return null;
			}

		$formats = ['!Y-m-d', '!d/m/Y'];
		foreach ($formats as $format)
			{
			$date = \DateTimeImmutable::createFromFormat($format, $value);
			$errors = \DateTimeImmutable::getLastErrors();
			$hasErrors = is_array($errors)
				&& (((int) ($errors['warning_count'] ?? 0) > 0) || ((int) ($errors['error_count'] ?? 0) > 0));
			if ($date !== false && !$hasErrors)
				{
				return $date->format('Y-m-d');
				}
			}

		throw new \InvalidArgumentException('Informe uma data de nascimento válida.');
		}

	/**
	 * Finds a person by global document.
	 */
	private function findPersonIdByDocument(string $document): ?int
		{
		$stmt = $this->db->prepare("
			SELECT id
			FROM " . $this->managerTable('people') . "
			WHERE document = :document
			LIMIT 1
		");
		$stmt->execute(['document' => $document]);
		$id = $stmt->fetchColumn();

		return $id !== false ? (int) $id : null;
		}

	/**
	 * Inserts a global person.
	 */
	private function insertPerson(array $person, string $now): int
		{
		$stmt = $this->db->prepare("
			INSERT INTO " . $this->managerTable('people') . "
				(name, fantasy, type, document, active, birthdate, created, modified)
			VALUES
				(:name, :fantasy, :type, :document, 1, :birthdate, :created, :modified)
		");
		$stmt->execute([
			'name' => $person['name'],
			'fantasy' => $person['fantasy'],
			'type' => $person['type'],
			'document' => $person['document'],
			'birthdate' => $person['birthdate'],
			'created' => $now,
			'modified' => $now,
		]);

		return (int) $this->db->lastInsertId();
		}

	/**
	 * Inserts optional initial email and phone.
	 */
	private function insertInitialContact(int $personId, array $contact, string $now): void
		{
		$email = trim((string) ($contact['email'] ?? ''));
		if ($email !== '')
			{
			if (!filter_var($email, FILTER_VALIDATE_EMAIL))
				{
				throw new \InvalidArgumentException('Informe um e-mail válido.');
				}

			$stmt = $this->db->prepare("
				INSERT INTO " . $this->managerTable('emails') . "
					(email, observations, is_login, person_id, created, modified)
				VALUES
					(:email, NULL, 0, :person_id, :created, :modified)
			");
			$stmt->execute([
				'email' => $email,
				'person_id' => $personId,
				'created' => $now,
				'modified' => $now,
			]);
			}

		$phone = onlyDigits((string) ($contact['phone'] ?? ''));
		if ($phone !== '')
			{
			$stmt = $this->db->prepare("
				INSERT INTO " . $this->managerTable('phone_numbers') . "
					(phone_number, observations, person_id, created, modified)
				VALUES
					(:phone_number, NULL, :person_id, :created, :modified)
			");
			$stmt->execute([
				'phone_number' => $phone,
				'person_id' => $personId,
				'created' => $now,
				'modified' => $now,
			]);
			}
		}

	/**
	 * Inserts a tenant-person CRM link.
	 */
	private function insertLink(int $tenantId, int $personId, array $crm, int $userId, string $now): int
		{
		$stmt = $this->db->prepare("
			INSERT INTO " . $this->managerTable('tenant_people') . "
				(tenant_id, person_id, crm_status_id, owner_user_id, source, notes, active, created_by_user_id, updated_by_user_id, created, modified)
			VALUES
				(:tenant_id, :person_id, :crm_status_id, :owner_user_id, :source, :notes, :active, :created_by_user_id, :updated_by_user_id, :created, :modified)
		");
		$stmt->execute([
			'tenant_id' => $tenantId,
			'person_id' => $personId,
			'crm_status_id' => $crm['crm_status_id'],
			'owner_user_id' => $crm['owner_user_id'],
			'source' => $crm['source'],
			'notes' => $crm['notes'],
			'active' => $crm['active'],
			'created_by_user_id' => $userId,
			'updated_by_user_id' => $userId,
			'created' => $now,
			'modified' => $now,
		]);

		return (int) $this->db->lastInsertId();
		}

	/**
	 * Updates a tenant-person CRM link.
	 */
	private function updateLink(int $crmId, int $tenantId, array $crm, int $userId, string $now): void
		{
		$stmt = $this->db->prepare("
			UPDATE " . $this->managerTable('tenant_people') . "
			SET crm_status_id = :crm_status_id,
			    owner_user_id = :owner_user_id,
			    source = :source,
			    notes = :notes,
			    active = :active,
			    updated_by_user_id = :updated_by_user_id,
			    modified = :modified
			WHERE id = :id
			  AND tenant_id = :tenant_id
		");
		$stmt->execute([
			'crm_status_id' => $crm['crm_status_id'],
			'owner_user_id' => $crm['owner_user_id'],
			'source' => $crm['source'],
			'notes' => $crm['notes'],
			'active' => $crm['active'],
			'updated_by_user_id' => $userId,
			'modified' => $now,
			'id' => $crmId,
			'tenant_id' => $tenantId,
		]);
		}

	/**
	 * Finds a tenant-person link.
	 */
	private function findLinkByTenantAndPerson(int $tenantId, int $personId): ?array
		{
		$stmt = $this->db->prepare("
			SELECT *
			FROM " . $this->managerTable('tenant_people') . "
			WHERE tenant_id = :tenant_id
			  AND person_id = :person_id
			LIMIT 1
		");
		$stmt->execute([
			'tenant_id' => $tenantId,
			'person_id' => $personId,
		]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);

		return $row ?: null;
		}

	/**
	 * Ensures the owner belongs to the tenant and is admin or user.
	 */
	private function assertAssignableOwner(?int $ownerUserId, int $tenantId, ?int $currentOwnerUserId = null): void
		{
		if ($ownerUserId === null)
			{
			return;
			}

		if ($currentOwnerUserId !== null && $ownerUserId === $currentOwnerUserId)
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
			'user_id' => $ownerUserId,
		]);

		if (!$stmt->fetchColumn())
			{
			throw new \InvalidArgumentException('Selecione um responsável válido para este tenant.');
			}
		}

	private function emailsByPersonId(int $personId): array
		{
		$stmt = $this->db->prepare("
			SELECT id, email, observations, is_login, created, modified
			FROM " . $this->managerTable('emails') . "
			WHERE person_id = :person_id
			ORDER BY is_login DESC, id ASC
		");
		$stmt->execute(['person_id' => $personId]);

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	private function phonesByPersonId(int $personId): array
		{
		$stmt = $this->db->prepare("
			SELECT id, phone_number, observations, created, modified
			FROM " . $this->managerTable('phone_numbers') . "
			WHERE person_id = :person_id
			ORDER BY id ASC
		");
		$stmt->execute(['person_id' => $personId]);

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	private function addressesByPersonId(int $personId): array
		{
		$stmt = $this->db->prepare("
			SELECT Address.id,
			       Address.zip_code,
			       Address.street,
			       Address.number,
			       Address.observations,
			       Neighborhood.name AS neighborhood_name,
			       City.name AS city_name
			FROM " . $this->managerTable('addresses') . " AS Address
			LEFT JOIN " . $this->managerTable('neighborhoods') . " AS Neighborhood ON Neighborhood.id = Address.neighborhood_id
			LEFT JOIN " . $this->managerTable('cities') . " AS City ON City.id = Neighborhood.city_id
			WHERE Address.person_id = :person_id
			ORDER BY Address.id ASC
		");
		$stmt->execute(['person_id' => $personId]);

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}
	}
?>
