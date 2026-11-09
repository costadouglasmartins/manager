<?php

namespace App\Models;

use App\Core\Database;
use App\Core\TenantModel;
use PDO;

/**
 * HR employee records of the active tenant (table hr_employees).
 * Identity data (name, document, contacts) stays in manager_main.people; this model only keeps HR data.
 * An employee is a person whose people.role contains "employee".
 **/
class HrEmployee extends TenantModel
	{
	/**
	 * Allowed employee statuses and their labels.
	 * @var array<string, string>
	 **/
	public const STATUSES = [
		'active'     => 'Ativo',
		'on_leave'   => 'Afastado',
		'terminated' => 'Desligado',
	];

	/**
	 * Allowed employment (contract) types and their labels.
	 * @var array<string, string>
	 **/
	public const EMPLOYMENT_TYPES = [
		'clt'        => 'CLT',
		'pj'         => 'PJ',
		'estagio'    => 'Estágio',
		'temporario' => 'Temporário',
	];

	/**
	 * Columns copied by snapshots and compensation restores (everything except id/person_id/created).
	 * @var array<int, string>
	 **/
	private const EDITABLE_COLUMNS = [
		'user_id',
		'hr_department_id',
		'hr_position_id',
		'manager_hr_employee_id',
		'registration_number',
		'admission_date',
		'termination_date',
		'employment_type',
		'status',
		'salary',
		'notes',
		'active',
	];

	/**
	 * Sort keys accepted by search().
	 * @var array<int, string>
	 **/
	public const SORT_KEYS = ['id', 'name', 'department', 'position', 'admission_date', 'status'];

	/**
	 * Returns the label of a status key.
	 * @param string $status Status key.
	 * @return string Portuguese label, or the key itself when unknown.
	 **/
	public static function statusLabel(string $status): string
		{
		return self::STATUSES[$status] ?? $status;
		}

	/**
	 * Returns the label of an employment type key.
	 * @param string $type Employment type key.
	 * @return string Portuguese label, or the key itself when unknown.
	 **/
	public static function employmentTypeLabel(string $type): string
		{
		return self::EMPLOYMENT_TYPES[$type] ?? $type;
		}

	/**
	 * Formats a Y-m-d date as dd/mm/yyyy.
	 * @param mixed $value Stored date.
	 * @return string Formatted date, or an empty string when empty or invalid.
	 **/
	public static function formatDate($value): string
		{
		return (string) (\maskDateBr(is_scalar($value) ? (string) $value : '') ?? '');
		}

	/**
	 * Formats a salary as Brazilian currency.
	 * @param mixed $value Stored decimal value.
	 * @return string Formatted amount, or an empty string when empty.
	 **/
	public static function formatSalary($value): string
		{
		if ($value === null || trim((string) $value) === '')
			{
			return '';
			}

		return 'R$ ' . number_format((float) $value, 2, ',', '.');
		}

	/**
	 * Returns a table qualified with the central manager database name.
	 * @param string $table Central table name.
	 * @return string Qualified and quoted table.
	 **/
	private function managerTable(string $table): string
		{
		return sprintf('`%s`.`%s`', Database::getManagerDatabaseName(), trim($table));
		}

	/**
	 * Returns the base select query with department, position, person, manager and user data.
	 * @return string SQL without filters or ordering.
	 **/
	private function baseSelectSql(): string
		{
		$peopleTable = $this->managerTable('people');
		$usersTable = $this->managerTable('users');

		return "SELECT HrEmployee.id,
		              HrEmployee.person_id,
		              HrEmployee.user_id,
		              HrEmployee.hr_department_id,
		              HrEmployee.hr_position_id,
		              HrEmployee.manager_hr_employee_id,
		              HrEmployee.registration_number,
		              HrEmployee.admission_date,
		              HrEmployee.termination_date,
		              HrEmployee.employment_type,
		              HrEmployee.status,
		              HrEmployee.salary,
		              HrEmployee.notes,
		              HrEmployee.active,
		              HrEmployee.created,
		              HrEmployee.modified,
		              Person.name AS person_name,
		              Person.document AS person_document,
		              Person.type AS person_type,
		              Person.role AS person_role,
		              Person.active AS person_active,
		              HrDepartment.name AS department_name,
		              HrPosition.name AS position_name,
		              ManagerPerson.name AS manager_name,
		              User.username AS user_username
		       FROM hr_employees AS HrEmployee
		       INNER JOIN {$peopleTable} AS Person ON Person.id = HrEmployee.person_id
		       LEFT JOIN hr_departments AS HrDepartment ON HrDepartment.id = HrEmployee.hr_department_id
		       LEFT JOIN hr_positions AS HrPosition ON HrPosition.id = HrEmployee.hr_position_id
		       LEFT JOIN hr_employees AS ManagerEmployee ON ManagerEmployee.id = HrEmployee.manager_hr_employee_id
		       LEFT JOIN {$peopleTable} AS ManagerPerson ON ManagerPerson.id = ManagerEmployee.person_id
		       LEFT JOIN {$usersTable} AS User ON User.id = HrEmployee.user_id";
		}

	/**
	 * Finds an employee by id.
	 * @param mixed $id Employee identifier.
	 * @return array|false Employee row or false when missing.
	 **/
	public function findById($id)
		{
		$stmt = $this->db->prepare($this->baseSelectSql() . "
			WHERE HrEmployee.id = :id
		");
		$stmt->execute(['id' => (int) $id]);

		return $stmt->fetch(PDO::FETCH_ASSOC);
		}

	/**
	 * Alias of findById() used by the generic CRUD conventions.
	 * @param mixed $id Employee identifier.
	 * @return array|false Employee row or false when missing.
	 **/
	public function find($id)
		{
		return $this->findById($id);
		}

	/**
	 * Finds the HR record linked to a person.
	 * @param int $personId Central person identifier.
	 * @return array|false Employee row or false when the person has no HR record.
	 **/
	public function findByPersonId(int $personId)
		{
		$stmt = $this->db->prepare($this->baseSelectSql() . "
			WHERE HrEmployee.person_id = :person_id
		");
		$stmt->execute(['person_id' => $personId]);

		return $stmt->fetch(PDO::FETCH_ASSOC);
		}

	/**
	 * Searches employees using filters and pagination.
	 * @param array<string, mixed> $filters Normalized filters.
	 * @param int|null $limit Page size, or null for every row (exports).
	 * @param int $offset Rows to skip.
	 * @param string $sort Sort key: id, name, department, position, admission_date or status (allowlisted).
	 * @param string $direction Sort direction: asc or desc.
	 * @return array<int, array<string, mixed>> Matching rows.
	 **/
	public function search(array $filters = [], ?int $limit = 10, int $offset = 0, string $sort = 'name', string $direction = 'asc'): array
		{
		$sql = $this->baseSelectSql() . "
			WHERE 1 = 1
		";
		$params = [];
		$this->appendSearchFilters($sql, $params, $filters);

		// Column and direction only enter the SQL through these allowlists.
		$sortColumns = [
			'id'             => 'HrEmployee.id',
			'name'           => 'Person.name',
			'department'     => 'HrDepartment.name',
			'position'       => 'HrPosition.name',
			'admission_date' => 'HrEmployee.admission_date',
			'status'         => 'HrEmployee.status',
		];
		$sortColumn = $sortColumns[$sort] ?? $sortColumns['name'];
		$sortDirection = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';

		// The unique id as last key keeps pagination stable.
		$sql .= "
			ORDER BY {$sortColumn} {$sortDirection}, Person.name ASC, HrEmployee.id ASC
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
	 * Counts employees matching the same filters used by search().
	 * @param array<string, mixed> $filters Normalized filters.
	 * @return int Total rows.
	 **/
	public function count(array $filters = []): int
		{
		$sql = "SELECT COUNT(*) AS total
		        FROM hr_employees AS HrEmployee
		        INNER JOIN " . $this->managerTable('people') . " AS Person ON Person.id = HrEmployee.person_id
		        WHERE 1 = 1";
		$params = [];
		$this->appendSearchFilters($sql, $params, $filters);

		$stmt = $this->db->prepare($sql);
		$this->bindSearchParams($stmt, $params);
		$stmt->execute();

		return (int) $stmt->fetchColumn();
		}

	/**
	 * Creates an HR record for an existing person (hr_employee/create flow) and audits it.
	 * @param array $data Submitted form data: person_id plus the HR fields.
	 * @return array{id: int} Inserted identifier.
	 **/
	public function create(array $data): array
		{
		$personId = (int) ($data['person_id'] ?? 0);
		$this->assertPersonCanBeEmployee($personId);
		$payload = $this->normalizeEmployeeData($data);

		try
			{
			$this->db->beginTransaction();
			$saved = $this->saveForPerson($personId, $payload, [
				'source' => 'hr_employee_form',
			], true);
			$this->db->commit();

			return ['id' => $saved['id']];
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
	 * Updates the HR data of an employee and audits both snapshots. The person never changes here.
	 * @param mixed $id Employee identifier.
	 * @param array $data Submitted form data.
	 * @return void
	 **/
	public function update($id, array $data): void
		{
		$employeeId = (int) $id;
		$current = $this->findById($employeeId);
		if (!$current)
			{
			throw new \InvalidArgumentException('Funcionário não encontrado.');
			}

		$payload = $this->normalizeEmployeeData($data, $current);

		try
			{
			$this->db->beginTransaction();
			$this->saveForPerson((int) $current['person_id'], $payload, [
				'source' => 'hr_employee_form',
			], true);
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
	 * Inactivates an employee without deleting history: active = 0, status terminated and today as termination date when empty.
	 * @param mixed $id Employee identifier.
	 * @return void
	 **/
	public function inactivate($id): void
		{
		$employeeId = (int) $id;
		$current = $this->findById($employeeId);
		if (!$current)
			{
			throw new \InvalidArgumentException('Funcionário não encontrado.');
			}

		$this->inactivateByPersonId((int) $current['person_id'], ['source' => 'hr_employee_inactivation']);
		}

	/**
	 * Inactivates the HR record of a person (used when the "employee" role is removed). Never deletes data.
	 * @param int $personId Central person identifier.
	 * @param array $context Extra audit context.
	 * @return array{id: int, before: array, after: array}|null Change summary, or null when there is nothing to inactivate.
	 **/
	public function inactivateByPersonId(int $personId, array $context = []): ?array
		{
		$before = $this->findByPersonId($personId);
		if (!$before)
			{
			return null;
			}

		// Already inactive and terminated: nothing to change.
		if ((int) $before['active'] === 0 && (string) $before['status'] === 'terminated')
			{
			return null;
			}

		$terminationDate = trim((string) ($before['termination_date'] ?? ''));
		if ($terminationDate === '')
			{
			$terminationDate = date('Y-m-d');
			}

		try
			{
			$this->db->beginTransaction();

			$stmt = $this->db->prepare("
				UPDATE hr_employees
				SET `active` = 0,
				    `status` = 'terminated',
				    `termination_date` = :termination_date,
				    `modified` = :modified
				WHERE id = :id
			");
			$stmt->execute([
				'termination_date' => $terminationDate,
				'modified'         => date('Y-m-d H:i:s'),
				'id'               => (int) $before['id'],
			]);

			$after = $this->findById((int) $before['id']);
			$this->auditUpdate('hr_employee', 'hr_employees', (int) $before['id'], $before, $after, $context + [
				'source' => 'person_role_removed',
			]);
			$this->db->commit();

			return ['id' => (int) $before['id'], 'before' => $before, 'after' => $after];
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
	 * Deletes an employee record. The person and the "employee" role are kept, so the person can be linked again.
	 * When this record is the ONLY link between the person and the tenant (no user and no CRM link), deleting it would
	 * leave the person unreachable in the tenant (the person scope relies on this row), so the deletion is blocked and
	 * the HR link must be inactivated instead (status "Desligado").
	 * @param mixed $id Employee identifier.
	 * @return void
	 * @throws \InvalidArgumentException When the employee does not exist, manages others or is the only tenant link of the person.
	 **/
	public function delete($id): void
		{
		$employeeId = (int) $id;
		if ($employeeId <= 0)
			{
			throw new \InvalidArgumentException('Funcionário inválido.');
			}

		try
			{
			$this->db->beginTransaction();

			$before = $this->findById($employeeId);
			if (!$before)
				{
				throw new \InvalidArgumentException('Funcionário não encontrado.');
				}

			// Block when hr_employees is the only tenant link of the person: the person would vanish and could not be edited anymore.
			$tenantId = (int) ((\App\Core\TenantContext::tenant()['id'] ?? 0));
			$stmt = $this->db->prepare("
				SELECT " . $this->tenantMembershipSql('Person') . " AS has_other_link
				FROM " . $this->managerTable('people') . " AS Person
				WHERE Person.id = :person_id
			");
			$stmt->execute([
				'person_id'               => (int) $before['person_id'],
				'tenant_user_tenant_id'   => $tenantId,
				'tenant_person_tenant_id' => $tenantId,
			]);
			if ((int) $stmt->fetchColumn() !== 1)
				{
				throw new \InvalidArgumentException(
					'Este funcionário não possui usuário nem vínculo de cliente neste tenant; excluir o registro tornaria a pessoa inacessível. Inative o vínculo alterando a situação para "Desligado".'
				);
				}

			// The foreign key would silently clear the manager of the subordinates, so block instead.
			$stmt = $this->db->prepare("
				SELECT COUNT(*)
				FROM hr_employees AS Subordinate
				WHERE Subordinate.manager_hr_employee_id = :id
			");
			$stmt->execute(['id' => $employeeId]);
			$subordinates = (int) $stmt->fetchColumn();
			if ($subordinates > 0)
				{
				throw new \InvalidArgumentException(sprintf(
					'Este funcionário é gestor de %d funcionário(s). Altere o gestor deles antes de excluir.',
					$subordinates
				));
				}

			$stmt = $this->db->prepare("DELETE FROM hr_employees WHERE id = :id");
			$stmt->execute(['id' => $employeeId]);

			$this->auditDelete('hr_employee', 'hr_employees', $employeeId, $before);
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
	 * Validates and normalizes the HR fields. Used by the HR forms and by the person form (employee[...] group).
	 * Department, position and manager must exist in the active tenant database; inactive ones are only accepted when unchanged.
	 * @param array $raw Submitted HR fields.
	 * @param array|null $current Current HR row when editing, or null when creating.
	 * @param bool $strict When true, department, position and admission date are required (forms always send them).
	 * @return array<string, mixed> Values ready to bind (without person_id and user_id).
	 **/
	public function normalizeEmployeeData(array $raw, ?array $current = null, bool $strict = true): array
		{
		$currentId = $current ? (int) $current['id'] : null;

		$departmentId = $this->normalizeReferenceId($raw['hr_department_id'] ?? null);
		if ($strict && $departmentId === null)
			{
			throw new \InvalidArgumentException('Selecione o departamento.');
			}

		if ($departmentId !== null)
			{
			$this->assertCatalogReference('hr_departments', $departmentId, $current['hr_department_id'] ?? null, 'Departamento');
			}

		$positionId = $this->normalizeReferenceId($raw['hr_position_id'] ?? null);
		if ($strict && $positionId === null)
			{
			throw new \InvalidArgumentException('Selecione o cargo.');
			}

		if ($positionId !== null)
			{
			$this->assertCatalogReference('hr_positions', $positionId, $current['hr_position_id'] ?? null, 'Cargo');
			}

		$managerId = $this->normalizeReferenceId($raw['manager_hr_employee_id'] ?? null);
		if ($managerId !== null)
			{
			$this->assertManager($managerId, $currentId, $current['manager_hr_employee_id'] ?? null);
			}

		$registration = $this->scalarText($raw['registration_number'] ?? null);
		if (mb_strlen($registration) > 40)
			{
			throw new \InvalidArgumentException('A matrícula deve ter no máximo 40 caracteres.');
			}

		$employmentType = $this->scalarText($raw['employment_type'] ?? null);
		$employmentType = $employmentType === '' ? 'clt' : $employmentType;
		if (!isset(self::EMPLOYMENT_TYPES[$employmentType]))
			{
			throw new \InvalidArgumentException('Selecione um tipo de contrato válido.');
			}

		$status = $this->scalarText($raw['status'] ?? null);
		$status = $status === '' ? 'active' : $status;
		if (!isset(self::STATUSES[$status]))
			{
			throw new \InvalidArgumentException('Selecione uma situação válida para o funcionário.');
			}

		$admissionDate = $this->normalizeDate($raw['admission_date'] ?? null, 'admissão');
		$terminationDate = $this->normalizeDate($raw['termination_date'] ?? null, 'desligamento');
		if ($strict && $admissionDate === null)
			{
			throw new \InvalidArgumentException('Informe a data de admissão.');
			}

		// A terminated employee always carries a termination date; default to today like the role-removal flow.
		if ($status === 'terminated' && $terminationDate === null)
			{
			$terminationDate = date('Y-m-d');
			}

		if ($admissionDate !== null && $terminationDate !== null && $terminationDate < $admissionDate)
			{
			throw new \InvalidArgumentException('A data de desligamento não pode ser anterior à data de admissão.');
			}

		$notes = $this->scalarText($raw['notes'] ?? null);
		if (mb_strlen($notes) > 5000)
			{
			throw new \InvalidArgumentException('As observações devem ter no máximo 5000 caracteres.');
			}

		return [
			'hr_department_id'       => $departmentId,
			'hr_position_id'         => $positionId,
			'manager_hr_employee_id' => $managerId,
			'registration_number'    => $registration !== '' ? $registration : null,
			'admission_date'         => $admissionDate,
			'termination_date'       => $terminationDate,
			'employment_type'        => $employmentType,
			'status'                 => $status,
			'salary'                 => $this->normalizeSalary($raw['salary'] ?? null),
			'notes'                  => $notes !== '' ? $notes : null,
			// Terminated employees are inactive; any other status keeps the record active.
			'active'                 => $status === 'terminated' ? 0 : 1,
		];
		}

	/**
	 * Inserts or updates the HR record of a person with already normalized data and audits the change.
	 * Runs inside the caller transaction when one is open, otherwise opens its own.
	 * The "employee" role is NOT checked here: the person form guarantees it before calling this method.
	 * @param int $personId Central person identifier.
	 * @param array $payload Output of normalizeEmployeeData().
	 * @param array $context Extra audit context.
	 * @param bool $insideTransaction True when the caller already opened the transaction.
	 * @return array{id: int, created: bool, before: array|null, after: array} Change summary used for compensation.
	 **/
	public function saveForPerson(int $personId, array $payload, array $context = [], bool $insideTransaction = false): array
		{
		if ($personId <= 0)
			{
			throw new \InvalidArgumentException('Pessoa inválida.');
			}

		$ownsTransaction = !$insideTransaction && !$this->db->inTransaction();

		try
			{
			if ($ownsTransaction)
				{
				$this->db->beginTransaction();
				}

			$before = $this->findByPersonId($personId);
			$now = date('Y-m-d H:i:s');
			$bindings = [
				'user_id'                => $this->resolveUserId($personId),
				'hr_department_id'       => $payload['hr_department_id'],
				'hr_position_id'         => $payload['hr_position_id'],
				'manager_hr_employee_id' => $payload['manager_hr_employee_id'],
				'registration_number'    => $payload['registration_number'],
				'admission_date'         => $payload['admission_date'],
				'termination_date'       => $payload['termination_date'],
				'employment_type'        => $payload['employment_type'],
				'status'                 => $payload['status'],
				'salary'                 => $payload['salary'],
				'notes'                  => $payload['notes'],
				'active'                 => $payload['active'],
			];

			if ($before)
				{
				$employeeId = (int) $before['id'];
				// A manager chain that loops back to this employee is rejected before writing.
				if ($payload['manager_hr_employee_id'] !== null)
					{
					$this->assertNoManagerCycle($employeeId, (int) $payload['manager_hr_employee_id']);
					}

				$stmt = $this->db->prepare("
					UPDATE hr_employees
					SET `user_id` = :user_id,
					    `hr_department_id` = :hr_department_id,
					    `hr_position_id` = :hr_position_id,
					    `manager_hr_employee_id` = :manager_hr_employee_id,
					    `registration_number` = :registration_number,
					    `admission_date` = :admission_date,
					    `termination_date` = :termination_date,
					    `employment_type` = :employment_type,
					    `status` = :status,
					    `salary` = :salary,
					    `notes` = :notes,
					    `active` = :active,
					    `modified` = :modified
					WHERE id = :id
					  AND person_id = :person_id
				");
				$stmt->execute($bindings + [
					'modified'  => $now,
					'id'        => $employeeId,
					'person_id' => $personId,
				]);

				$after = $this->findById($employeeId);
				$this->auditUpdate('hr_employee', 'hr_employees', $employeeId, $before, $after, $context);
				$created = false;
				}
			else
				{
				$stmt = $this->db->prepare("
					INSERT INTO hr_employees
					    (`person_id`, `user_id`, `hr_department_id`, `hr_position_id`, `manager_hr_employee_id`,
					     `registration_number`, `admission_date`, `termination_date`, `employment_type`, `status`,
					     `salary`, `notes`, `active`, `created`, `modified`)
					VALUES
					    (:person_id, :user_id, :hr_department_id, :hr_position_id, :manager_hr_employee_id,
					     :registration_number, :admission_date, :termination_date, :employment_type, :status,
					     :salary, :notes, :active, :created, :modified)
				");
				$stmt->execute($bindings + [
					'person_id' => $personId,
					'created'   => $now,
					'modified'  => $now,
				]);

				$employeeId = (int) $this->db->lastInsertId();
				$after = $this->findById($employeeId);
				$this->auditCreate('hr_employee', 'hr_employees', $employeeId, $after, $context);
				$created = true;
				}

			if ($ownsTransaction)
				{
				$this->db->commit();
				}

			return [
				'id'      => $employeeId,
				'created' => $created,
				'before'  => $before ?: null,
				'after'   => $after,
			];
			}
		catch (\PDOException $e)
			{
			if ($ownsTransaction && $this->db->inTransaction())
				{
				$this->db->rollBack();
				}

			// 23000 is the SQLSTATE of the UNIQUE KEY on person_id (a concurrent request created the record first).
			if ($e->getCode() === '23000' && stripos($e->getMessage(), 'uq_hr_employees_person') !== false)
				{
				throw new \InvalidArgumentException('Esta pessoa já possui cadastro de funcionário.');
				}

			throw $e;
			}
		catch (\Exception $e)
			{
			if ($ownsTransaction && $this->db->inTransaction())
				{
				$this->db->rollBack();
				}

			throw $e;
			}
		}

	/**
	 * Restores the HR fields of a record from a previous snapshot (compensation when the person write fails).
	 * @param array $snapshot Row returned by findById()/findByPersonId() before the change.
	 * @param array $context Extra audit context.
	 * @return void
	 **/
	public function restoreSnapshot(array $snapshot, array $context = []): void
		{
		$employeeId = (int) ($snapshot['id'] ?? 0);
		if ($employeeId <= 0)
			{
			throw new \InvalidArgumentException('Funcionário inválido.');
			}

		try
			{
			$this->db->beginTransaction();

			$current = $this->findById($employeeId);
			$sets = [];
			$params = ['id' => $employeeId, 'modified' => date('Y-m-d H:i:s')];
			foreach (self::EDITABLE_COLUMNS as $column)
				{
				$sets[] = "`{$column}` = :{$column}";
				$params[$column] = $snapshot[$column] ?? null;
				}

			$stmt = $this->db->prepare("
				UPDATE hr_employees
				SET " . implode(', ', $sets) . ",
				    `modified` = :modified
				WHERE id = :id
			");
			$stmt->execute($params);

			$this->auditUpdate('hr_employee', 'hr_employees', $employeeId, $current, $this->findById($employeeId), $context + [
				'source' => 'person_form_compensation',
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
	 * Removes a record created moments ago by the same request (compensation when the person write fails).
	 * @param int $employeeId Employee identifier.
	 * @param array $context Extra audit context.
	 * @return void
	 **/
	public function discardCreated(int $employeeId, array $context = []): void
		{
		try
			{
			$this->db->beginTransaction();

			$before = $this->findById($employeeId);
			if ($before)
				{
				$stmt = $this->db->prepare("DELETE FROM hr_employees WHERE id = :id");
				$stmt->execute(['id' => $employeeId]);
				$this->auditDelete('hr_employee', 'hr_employees', $employeeId, $before, $context + [
					'source' => 'person_form_compensation',
				]);
				}

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
	 * Returns person suggestions for the "new employee" form: active people with the employee role,
	 * linked to the tenant (user or CRM link) and without an HR record yet.
	 * @param string $term Search term.
	 * @param int $tenantId Active tenant identifier.
	 * @param int $limit Maximum suggestions.
	 * @return array<int, array{id: int, name: string}> Suggestions.
	 **/
	public function autocompletePeople(string $term, int $tenantId, int $limit = 20): array
		{
		$term = trim($term);
		if ($term === '' || $tenantId <= 0)
			{
			return [];
			}

		$limit = max(1, min(50, $limit));
		$peopleTable = $this->managerTable('people');
		$sql = "SELECT Person.id,
		               Person.name
		        FROM {$peopleTable} AS Person
		        WHERE Person.active = 1
		          AND FIND_IN_SET('employee', Person.role) > 0
		          AND Person.name LIKE :term
		          AND " . $this->tenantMembershipSql('Person') . "
		          AND NOT EXISTS (
		            SELECT 1
		            FROM hr_employees AS ExistingEmployee
		            WHERE ExistingEmployee.person_id = Person.id
		          )
		        ORDER BY Person.name ASC, Person.id ASC
		        LIMIT {$limit}";

		$stmt = $this->db->prepare($sql);
		$stmt->execute([
			'term'                  => '%' . $term . '%',
			'tenant_user_tenant_id' => $tenantId,
			'tenant_person_tenant_id' => $tenantId,
		]);

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Returns employee suggestions for the manager field: not terminated, optionally excluding one employee.
	 * @param string $term Search term.
	 * @param int $limit Maximum suggestions.
	 * @param int|null $excludeId Employee that cannot be its own manager.
	 * @return array<int, array{id: int, name: string}> Suggestions (id is the hr_employees id).
	 **/
	public function autocompleteEmployees(string $term, int $limit = 20, ?int $excludeId = null): array
		{
		$term = trim($term);
		if ($term === '')
			{
			return [];
			}

		$limit = max(1, min(50, $limit));
		$sql = "SELECT HrEmployee.id,
		               Person.name
		        FROM hr_employees AS HrEmployee
		        INNER JOIN " . $this->managerTable('people') . " AS Person ON Person.id = HrEmployee.person_id
		        WHERE HrEmployee.active = 1
		          AND HrEmployee.status <> 'terminated'
		          AND Person.name LIKE :term";
		$params = ['term' => '%' . $term . '%'];

		if ($excludeId !== null && $excludeId > 0)
			{
			$sql .= " AND HrEmployee.id <> :exclude_id";
			$params['exclude_id'] = $excludeId;
			}

		$sql .= "
		        ORDER BY Person.name ASC, HrEmployee.id ASC
		        LIMIT {$limit}";

		$stmt = $this->db->prepare($sql);
		$this->bindSearchParams($stmt, $params);
		$stmt->execute();

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Returns the name of a person of the active tenant, used to repopulate the person field after a rejected submission.
	 * @param int $personId Central person identifier.
	 * @param int $tenantId Active tenant identifier.
	 * @return string Person name, or an empty string when the person is not in the tenant.
	 **/
	public function personLabel(int $personId, int $tenantId): string
		{
		if ($personId <= 0 || $tenantId <= 0)
			{
			return '';
			}

		$stmt = $this->db->prepare("
			SELECT Person.name
			FROM " . $this->managerTable('people') . " AS Person
			WHERE Person.id = :person_id
			  AND " . $this->tenantMembershipSql('Person') . "
		");
		$stmt->execute([
			'person_id'               => $personId,
			'tenant_user_tenant_id'   => $tenantId,
			'tenant_person_tenant_id' => $tenantId,
		]);
		$name = $stmt->fetchColumn();

		return $name !== false ? (string) $name : '';
		}

	/**
	 * Returns the person name of an employee, used to repopulate the manager field after a rejected submission.
	 * @param int $employeeId Employee identifier.
	 * @return string Person name, or an empty string when the employee does not exist.
	 **/
	public function employeeLabel(int $employeeId): string
		{
		if ($employeeId <= 0)
			{
			return '';
			}

		$stmt = $this->db->prepare("
			SELECT Person.name
			FROM hr_employees AS HrEmployee
			INNER JOIN " . $this->managerTable('people') . " AS Person ON Person.id = HrEmployee.person_id
			WHERE HrEmployee.id = :id
		");
		$stmt->execute(['id' => $employeeId]);
		$name = $stmt->fetchColumn();

		return $name !== false ? (string) $name : '';
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
			$sql .= " AND HrEmployee.id = :id";
			$params['id'] = (int) $filters['id'];
			}

		if (isset($filters['name']) && $filters['name'] !== '')
			{
			$sql .= " AND Person.name LIKE :name";
			$params['name'] = '%' . trim((string) $filters['name']) . '%';
			}

		if (isset($filters['document']) && $filters['document'] !== '')
			{
			$digits = \onlyDigits((string) $filters['document']);
			if ($digits !== '')
				{
				$sql .= " AND Person.document LIKE :document";
				$params['document'] = '%' . $digits . '%';
				}
			}

		if (isset($filters['registration_number']) && $filters['registration_number'] !== '')
			{
			$sql .= " AND HrEmployee.registration_number LIKE :registration_number";
			$params['registration_number'] = '%' . trim((string) $filters['registration_number']) . '%';
			}

		if (isset($filters['hr_department_id']) && $filters['hr_department_id'] !== '')
			{
			$sql .= " AND HrEmployee.hr_department_id = :hr_department_id";
			$params['hr_department_id'] = (int) $filters['hr_department_id'];
			}

		if (isset($filters['hr_position_id']) && $filters['hr_position_id'] !== '')
			{
			$sql .= " AND HrEmployee.hr_position_id = :hr_position_id";
			$params['hr_position_id'] = (int) $filters['hr_position_id'];
			}

		if (isset($filters['status']) && isset(self::STATUSES[(string) $filters['status']]))
			{
			$sql .= " AND HrEmployee.status = :status";
			$params['status'] = (string) $filters['status'];
			}

		if (isset($filters['employment_type']) && isset(self::EMPLOYMENT_TYPES[(string) $filters['employment_type']]))
			{
			$sql .= " AND HrEmployee.employment_type = :employment_type";
			$params['employment_type'] = (string) $filters['employment_type'];
			}

		if (isset($filters['active']) && in_array((string) $filters['active'], ['0', '1'], true))
			{
			$sql .= " AND HrEmployee.active = :active";
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
	 * Returns the SQL condition that restricts a central person to the active tenant (user link or CRM link).
	 * Expects the named parameters :tenant_user_tenant_id and :tenant_person_tenant_id.
	 * @param string $personAlias Alias of the people table in the outer query.
	 * @return string SQL condition without a leading AND.
	 **/
	private function tenantMembershipSql(string $personAlias): string
		{
		$usersTable = $this->managerTable('users');
		$tenantUsersTable = $this->managerTable('tenant_users');
		$tenantPeopleTable = $this->managerTable('tenant_people');

		return "(
		            EXISTS (
		              SELECT 1
		              FROM {$usersTable} AS MemberUser
		              INNER JOIN {$tenantUsersTable} AS MemberTenantUser
		                ON MemberTenantUser.user_id = MemberUser.id
		               AND MemberTenantUser.tenant_id = :tenant_user_tenant_id
		              WHERE MemberUser.person_id = {$personAlias}.id
		            )
		            OR EXISTS (
		              SELECT 1
		              FROM {$tenantPeopleTable} AS MemberTenantPerson
		              WHERE MemberTenantPerson.person_id = {$personAlias}.id
		                AND MemberTenantPerson.tenant_id = :tenant_person_tenant_id
		            )
		          )";
		}

	/**
	 * Ensures a person can become an employee through the HR screen:
	 * exists, is active, has the "employee" role, belongs to the active tenant and has no HR record yet.
	 * @param int $personId Central person identifier.
	 * @return void
	 **/
	private function assertPersonCanBeEmployee(int $personId): void
		{
		if ($personId <= 0)
			{
			throw new \InvalidArgumentException('Selecione a pessoa do funcionário.');
			}

		$tenantId = (int) ((\App\Core\TenantContext::tenant()['id'] ?? 0));
		$stmt = $this->db->prepare("
			SELECT Person.id,
			       Person.active,
			       FIND_IN_SET('employee', Person.role) > 0 AS is_employee,
			       " . $this->tenantMembershipSql('Person') . " AS in_tenant
			FROM " . $this->managerTable('people') . " AS Person
			WHERE Person.id = :person_id
		");
		$stmt->execute([
			'person_id'               => $personId,
			'tenant_user_tenant_id'   => $tenantId,
			'tenant_person_tenant_id' => $tenantId,
		]);
		$person = $stmt->fetch(PDO::FETCH_ASSOC);

		// A person reached only through an existing HR record (no user or CRM link) belongs to this tenant too.
		$existingEmployee = $person ? $this->findByPersonId($personId) : false;

		// A missing person and a person of another tenant look the same to the caller.
		if (!$person || ((int) $person['in_tenant'] !== 1 && !$existingEmployee))
			{
			throw new \InvalidArgumentException('Pessoa não encontrada neste tenant.');
			}

		if ($existingEmployee)
			{
			// An inactive record is reactivated by editing it (status other than "Desligado"), not by creating another one.
			throw new \InvalidArgumentException(
				(int) $existingEmployee['active'] === 0
					? 'Esta pessoa já possui cadastro de funcionário inativo. Edite o cadastro existente e altere a situação para reativá-lo.'
					: 'Esta pessoa já possui cadastro de funcionário.'
			);
			}

		if ((int) $person['active'] !== 1)
			{
			throw new \InvalidArgumentException('A pessoa selecionada está inativa.');
			}

		if ((int) $person['is_employee'] !== 1)
			{
			throw new \InvalidArgumentException('A pessoa selecionada não possui o perfil de funcionário. Marque o perfil "Funcionário" no cadastro da pessoa.');
			}

		}

	/**
	 * Finds the central user of a person that is linked to the active tenant.
	 * @param int $personId Central person identifier.
	 * @return int|null User identifier, or null when the person has no user in this tenant.
	 **/
	private function resolveUserId(int $personId): ?int
		{
		$tenantId = (int) ((\App\Core\TenantContext::tenant()['id'] ?? 0));
		if ($personId <= 0 || $tenantId <= 0)
			{
			return null;
			}

		$stmt = $this->db->prepare("
			SELECT User.id
			FROM " . $this->managerTable('users') . " AS User
			INNER JOIN " . $this->managerTable('tenant_users') . " AS TenantUser
			        ON TenantUser.user_id = User.id
			       AND TenantUser.tenant_id = :tenant_id
			WHERE User.person_id = :person_id
			ORDER BY User.id ASC
			LIMIT 1
		");
		$stmt->execute([
			'tenant_id' => $tenantId,
			'person_id' => $personId,
		]);
		$userId = $stmt->fetchColumn();

		return $userId !== false ? (int) $userId : null;
		}

	/**
	 * Converts a submitted foreign key to a positive integer or null.
	 * @param mixed $value Submitted value.
	 * @return int|null Identifier, or null when empty.
	 **/
	private function normalizeReferenceId($value): ?int
		{
		if (!is_scalar($value) || trim((string) $value) === '')
			{
			return null;
			}

		$id = (int) $value;
		if ($id <= 0 || (string) $id !== trim((string) $value))
			{
			throw new \InvalidArgumentException('Seleção inválida.');
			}

		return $id;
		}

	/**
	 * Ensures a department or position exists in the tenant database and is active (or unchanged).
	 * @param string $table Catalog table, allowlisted by the callers.
	 * @param int $id Submitted identifier.
	 * @param mixed $currentId Identifier currently stored in the record being edited.
	 * @param string $label Label used in error messages.
	 * @return void
	 **/
	private function assertCatalogReference(string $table, int $id, $currentId, string $label): void
		{
		// Identifier comes from the two literal calls above, never from user input.
		if (!in_array($table, ['hr_departments', 'hr_positions'], true))
			{
			throw new \InvalidArgumentException('Seleção inválida.');
			}

		$stmt = $this->db->prepare("SELECT Catalog.active FROM {$table} AS Catalog WHERE Catalog.id = :id");
		$stmt->execute(['id' => $id]);
		$active = $stmt->fetchColumn();

		if ($active === false)
			{
			throw new \InvalidArgumentException($label . ' não encontrado neste tenant.');
			}

		if ((int) $active !== 1 && (int) $currentId !== $id)
			{
			throw new \InvalidArgumentException($label . ' selecionado está inativo.');
			}
		}

	/**
	 * Ensures the manager exists in the tenant database, is not the employee itself and is active (or unchanged).
	 * @param int $managerId Submitted hr_employees identifier.
	 * @param int|null $employeeId Employee being edited, or null when creating.
	 * @param mixed $currentManagerId Manager currently stored in the record being edited.
	 * @return void
	 **/
	private function assertManager(int $managerId, ?int $employeeId, $currentManagerId): void
		{
		if ($employeeId !== null && $managerId === $employeeId)
			{
			throw new \InvalidArgumentException('O funcionário não pode ser gestor de si mesmo.');
			}

		$stmt = $this->db->prepare("
			SELECT ManagerEmployee.active,
			       ManagerEmployee.status
			FROM hr_employees AS ManagerEmployee
			WHERE ManagerEmployee.id = :id
		");
		$stmt->execute(['id' => $managerId]);
		$manager = $stmt->fetch(PDO::FETCH_ASSOC);

		if (!$manager)
			{
			throw new \InvalidArgumentException('Gestor não encontrado neste tenant.');
			}

		$isUnchanged = ((int) $currentManagerId === $managerId);
		if (!$isUnchanged && ((int) $manager['active'] !== 1 || (string) $manager['status'] === 'terminated'))
			{
			throw new \InvalidArgumentException('O gestor selecionado está inativo ou desligado.');
			}
		}

	/**
	 * Walks up the manager chain and rejects loops that would pass through the employee.
	 * @param int $employeeId Employee being saved.
	 * @param int $managerId Proposed manager.
	 * @return void
	 **/
	private function assertNoManagerCycle(int $employeeId, int $managerId): void
		{
		$stmt = $this->db->prepare("SELECT manager_hr_employee_id FROM hr_employees WHERE id = :id");
		$visited = [];
		$cursor = $managerId;

		// A depth cap protects against pre-existing corrupted loops that do not include this employee.
		for ($depth = 0; $cursor > 0 && $depth < 100; $depth++)
			{
			if ($cursor === $employeeId)
				{
				throw new \InvalidArgumentException('O gestor selecionado já é subordinado deste funcionário.');
				}

			if (isset($visited[$cursor]))
				{
				return;
				}

			$visited[$cursor] = true;
			$stmt->execute(['id' => $cursor]);
			$next = $stmt->fetchColumn();
			$cursor = $next !== false ? (int) $next : 0;
			}
		}

	/**
	 * Returns a trimmed string for scalar input and an empty string for arrays or objects.
	 * @param mixed $value Submitted value.
	 * @return string Trimmed text.
	 **/
	private function scalarText($value): string
		{
		return is_scalar($value) ? trim((string) $value) : '';
		}

	/**
	 * Parses a date submitted as dd/mm/yyyy or yyyy-mm-dd into Y-m-d.
	 * @param mixed $value Submitted date.
	 * @param string $label Field name used in error messages.
	 * @return string|null Normalized date, or null when empty.
	 **/
	private function normalizeDate($value, string $label): ?string
		{
		$text = $this->scalarText($value);
		if ($text === '')
			{
			return null;
			}

		$format = null;
		if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $text) === 1)
			{
			$format = 'd/m/Y';
			}
		elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $text) === 1)
			{
			$format = 'Y-m-d';
			}

		$date = $format !== null ? \DateTimeImmutable::createFromFormat('!' . $format, $text) : false;
		$errors = \DateTimeImmutable::getLastErrors();
		$hasErrors = is_array($errors) && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0);

		if ($date === false || $hasErrors)
			{
			throw new \InvalidArgumentException('Informe uma data de ' . $label . ' válida.');
			}

		return $date->format('Y-m-d');
		}

	/**
	 * Parses a salary typed in Brazilian format (1.234,56) or plain decimal (1234.56).
	 * @param mixed $value Submitted salary.
	 * @return string|null Decimal string with two places, or null when empty.
	 **/
	private function normalizeSalary($value): ?string
		{
		$text = $this->scalarText($value);
		$text = trim(str_ireplace(['R$', ' '], '', $text));
		if ($text === '')
			{
			return null;
			}

		if (strpos($text, ',') !== false)
			{
			// Brazilian format: dots are thousand separators and the comma is the decimal separator.
			$text = str_replace('.', '', $text);
			$text = str_replace(',', '.', $text);
			}
		elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $text) === 1)
			{
			// Only thousand separators, such as 1.234.567.
			$text = str_replace('.', '', $text);
			}

		if (preg_match('/^\d+(\.\d{1,2})?$/', $text) !== 1)
			{
			throw new \InvalidArgumentException('Informe um salário válido.');
			}

		// DECIMAL(12,2) holds up to 9,999,999,999.99.
		if ((float) $text > 9999999999.99)
			{
			throw new \InvalidArgumentException('O salário informado é maior que o permitido.');
			}

		return number_format((float) $text, 2, '.', '');
		}
	}
?>
