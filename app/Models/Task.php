<?php

namespace App\Models;

use App\Core\Database;
use App\Core\TenantModel;
use PDO;

class Task extends TenantModel
	{
	public const STATUS_OPEN = 'open';
	public const STATUS_IN_PROGRESS = 'in_progress';
	public const STATUS_WAITING_THIRD_PARTY = 'waiting_third_party';
	public const STATUS_COMPLETED = 'completed';
	public const STATUS_CANCELED = 'canceled';

	private const PENDING_STATUSES = [
		self::STATUS_OPEN,
		self::STATUS_IN_PROGRESS,
		self::STATUS_WAITING_THIRD_PARTY,
	];

	/**
	 * Returns the status catalog used by forms, lists and reports.
	 **/
	public static function statuses(): array
		{
		return [
			self::STATUS_OPEN => [
				'label' => 'Aberta',
				'badge' => 'text-bg-primary',
			],
			self::STATUS_IN_PROGRESS => [
				'label' => 'Em andamento',
				'badge' => 'text-bg-warning',
			],
			self::STATUS_WAITING_THIRD_PARTY => [
				'label' => 'Aguardando terceiro',
				'badge' => 'text-bg-info',
			],
			self::STATUS_COMPLETED => [
				'label' => 'Concluída',
				'badge' => 'text-bg-success',
			],
			self::STATUS_CANCELED => [
				'label' => 'Cancelada',
				'badge' => 'text-bg-secondary',
			],
		];
		}

	/**
	 * Returns statuses considered pending for dashboard visibility.
	 **/
	public static function pendingStatuses(): array
		{
		return self::PENDING_STATUSES;
		}

	/**
	 * Returns a display label for a status value.
	 **/
	public static function statusLabel(?string $status): string
		{
		$statuses = self::statuses();
		return (string) ($statuses[(string) $status]['label'] ?? (string) $status);
		}

	/**
	 * Returns the Bootstrap badge class for a status value.
	 **/
	public static function statusBadgeClass(?string $status): string
		{
		$statuses = self::statuses();
		return (string) ($statuses[(string) $status]['badge'] ?? 'text-bg-secondary');
		}

	/**
	 * Returns the base task select query with category and aggregate task data.
	 **/
	private function baseSelectSql(): string
		{
		$peopleTable = $this->managerTable('people');

		return "SELECT Task.id,
		              Task.task_category_id,
		              Task.title,
		              Task.status,
		              Task.third_party_person_id,
		              Task.third_party_name,
		              Task.description,
		              Task.created_by_user_id,
		              Task.created,
		              Task.modified,
		              TaskCategory.name AS task_category_name,
		              ThirdPartyPerson.name AS third_party_person_name,
		              COALESCE(
		              	(
		              		SELECT GROUP_CONCAT(AssigneePerson.name ORDER BY AssigneePerson.name ASC SEPARATOR ', ')
		              		FROM task_assignees AS ActiveTaskAssignee
		              		INNER JOIN {$peopleTable} AS AssigneePerson ON AssigneePerson.id = ActiveTaskAssignee.person_id
		              		WHERE ActiveTaskAssignee.task_id = Task.id
		              		  AND ActiveTaskAssignee.active = 1
		              	),
		              	''
		              ) AS assignee_names,
		              COALESCE(
		              	(
		              		SELECT SUM(TIMESTAMPDIFF(SECOND, TaskExecution.started_at, COALESCE(TaskExecution.paused_at, NOW())))
		              		FROM task_assignees AS ExecutionTaskAssignee
		              		INNER JOIN task_executions AS TaskExecution ON TaskExecution.task_assignee_id = ExecutionTaskAssignee.id
		              		WHERE ExecutionTaskAssignee.task_id = Task.id
		              	),
		              	0
		              ) AS total_seconds,
		              EXISTS(
		              	SELECT 1
		              	FROM task_assignees AS OpenTaskAssignee
		              	INNER JOIN task_executions AS OpenTaskExecution ON OpenTaskExecution.task_assignee_id = OpenTaskAssignee.id
		              	WHERE OpenTaskAssignee.task_id = Task.id
		              	  AND OpenTaskExecution.paused_at IS NULL
		              ) AS has_open_execution
		       FROM tasks AS Task
		       LEFT JOIN task_categories AS TaskCategory ON TaskCategory.id = Task.task_category_id
		       LEFT JOIN {$peopleTable} AS ThirdPartyPerson ON ThirdPartyPerson.id = Task.third_party_person_id";
		}

	/**
	 * Creates a task with its assignees.
	 * @param array $data Request payload.
	 * @param int $userId User creating the task.
	 * @param int $tenantId Current tenant id for person validation.
	 * @return array
	 **/
	public function create(array $data, int $userId, int $tenantId): array
		{
		$payload = $this->normalizePayload($data, $tenantId);
		$now = date('Y-m-d H:i:s');

		try
			{
			$this->db->beginTransaction();

			$stmt = $this->db->prepare("
				INSERT INTO tasks
				    (`task_category_id`, `title`, `status`, `third_party_person_id`, `third_party_name`,
				     `description`, `created_by_user_id`, `created`, `modified`)
				VALUES
				    (:task_category_id, :title, :status, :third_party_person_id, :third_party_name,
				     :description, :created_by_user_id, :created, :modified)
			");
			$stmt->execute([
				'task_category_id'      => $payload['task_category_id'],
				'title'                 => $payload['title'],
				'status'                => $payload['status'],
				'third_party_person_id' => $payload['third_party_person_id'],
				'third_party_name'      => $payload['third_party_name'],
				'description'           => $payload['description'],
				'created_by_user_id'    => $userId,
				'created'               => $now,
				'modified'              => $now,
			]);

			$taskId = (int) $this->db->lastInsertId();
			$this->syncAssignees($taskId, $payload['assignee_person_ids'], $now);

			$taskSnapshot = $this->findAllByTaskId($taskId);
			$this->auditCreate('task', 'tasks', $taskId, $taskSnapshot, [
				'affected_tables' => ['tasks', 'task_assignees'],
			]);
			$this->db->commit();

			return ['task_id' => $taskId];
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
	 * Updates an existing task and synchronizes active assignees.
	 * @param mixed $id Task identifier.
	 * @param array $data Request payload.
	 * @param int $tenantId Current tenant id for person validation.
	 **/
	public function update($id, array $data, int $tenantId): void
		{
		$taskId = (int) $id;
		$payload = $this->normalizePayload($data, $tenantId);
		$now = date('Y-m-d H:i:s');

		try
			{
			$this->db->beginTransaction();
			$beforeData = $this->findAllByTaskId($taskId);

			if (empty($beforeData['task']))
				{
				throw new \InvalidArgumentException('Tarefa não encontrada.');
				}

			$stmt = $this->db->prepare("
				UPDATE tasks
				SET `task_category_id` = :task_category_id,
				    `title` = :title,
				    `status` = :status,
				    `third_party_person_id` = :third_party_person_id,
				    `third_party_name` = :third_party_name,
				    `description` = :description,
				    `modified` = :modified
				WHERE id = :id
			");
			$stmt->execute([
				'task_category_id'      => $payload['task_category_id'],
				'title'                 => $payload['title'],
				'status'                => $payload['status'],
				'third_party_person_id' => $payload['third_party_person_id'],
				'third_party_name'      => $payload['third_party_name'],
				'description'           => $payload['description'],
				'modified'              => $now,
				'id'                    => $taskId,
			]);

			$this->syncAssignees($taskId, $payload['assignee_person_ids'], $now);
			$afterData = $this->findAllByTaskId($taskId);
			$this->auditUpdate('task', 'tasks', $taskId, $beforeData, $afterData, [
				'affected_tables' => ['tasks', 'task_assignees'],
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
	 * Finds a task by id.
	 * @param mixed $id Task identifier.
	 * @return array|false
	 **/
	public function find($id)
		{
		$stmt = $this->db->prepare($this->baseSelectSql() . "
			WHERE Task.id = :id
		");
		$stmt->execute(['id' => (int) $id]);

		return $stmt->fetch(PDO::FETCH_ASSOC);
		}

	/**
	 * Searches tasks using filters, pagination and safe ordering.
	 **/
	public function search(array $filters = [], ?int $limit = 10, int $offset = 0, string $sort = 'created', string $direction = 'desc'): array
		{
		$sql = $this->baseSelectSql() . "
			WHERE 1 = 1
		";
		$params = [];

		$this->appendSearchFilters($sql, $params, $filters);
		$sql .= $this->orderBySql($sort, $direction);

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
	 * Counts tasks matching filters.
	 **/
	public function count(array $filters = []): int
		{
		$peopleTable = $this->managerTable('people');
		$sql = "SELECT COUNT(*) AS total
		        FROM tasks AS Task
		        LEFT JOIN task_categories AS TaskCategory ON TaskCategory.id = Task.task_category_id
		        LEFT JOIN {$peopleTable} AS ThirdPartyPerson ON ThirdPartyPerson.id = Task.third_party_person_id
		        WHERE 1 = 1";
		$params = [];

		$this->appendSearchFilters($sql, $params, $filters);

		$stmt = $this->db->prepare($sql);
		$this->bindSearchParams($stmt, $params);
		$stmt->execute();

		return (int) $stmt->fetchColumn();
		}

	/**
	 * Returns dashboard tasks that are still pending.
	 **/
	public function dashboardTasks(int $limit = 8): array
		{
		return $this->search([
			'statuses' => self::pendingStatuses(),
		], $limit, 0, 'created', 'desc');
		}

	/**
	 * Returns a task details payload for information screens and audit snapshots.
	 * @param mixed $id Task identifier.
	 * @return array
	 **/
	public function findAllByTaskId($id): array
		{
		$taskId = (int) $id;

		return [
			'task' => $this->find($taskId),
			'assignees' => $this->getAssignees($taskId, false),
			'executions' => $this->getExecutions($taskId),
			'comments' => $this->getComments($taskId),
		];
		}

	/**
	 * Adds a comment to a task.
	 **/
	public function addComment(int $taskId, int $userId, string $comment): array
		{
		$text = trim($comment);
		if ($text === '')
			{
			throw new \InvalidArgumentException('Informe o comentário.');
			}

		try
			{
			$this->db->beginTransaction();

			if (!$this->find($taskId))
				{
				throw new \InvalidArgumentException('Tarefa não encontrada.');
				}

			$stmt = $this->db->prepare("
				INSERT INTO task_comments
				    (`task_id`, `user_id`, `comment`, `created`, `modified`)
				VALUES
				    (:task_id, :user_id, :comment, :created, :modified)
			");
			$stmt->execute([
				'task_id'  => $taskId,
				'user_id'  => $userId,
				'comment'  => $text,
				'created'  => date('Y-m-d H:i:s'),
				'modified' => date('Y-m-d H:i:s'),
			]);

			$commentId = (int) $this->db->lastInsertId();
			$commentSnapshot = $this->findComment($commentId);
			$this->auditCreate('task', 'task_comments', $commentId, $commentSnapshot, [
				'task_id' => $taskId,
			]);
			$this->db->commit();

			return ['task_comment_id' => $commentId];
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
	 * Starts an execution interval for an assignee.
	 **/
	public function startExecution(int $taskAssigneeId, int $userId): array
		{
		try
			{
			$this->db->beginTransaction();
			$assignee = $this->findAssignee($taskAssigneeId);

			if (!$assignee || (int) ($assignee['active'] ?? 0) !== 1)
				{
				throw new \InvalidArgumentException('Responsável da tarefa não encontrado.');
				}

			if (in_array((string) ($assignee['task_status'] ?? ''), [self::STATUS_COMPLETED, self::STATUS_CANCELED], true))
				{
				throw new \InvalidArgumentException('Não é possível iniciar uma tarefa concluída ou cancelada.');
				}

			if ($this->hasOpenExecution($taskAssigneeId))
				{
				throw new \InvalidArgumentException('Esta execução já está em andamento.');
				}

			$beforeTask = $this->find((int) $assignee['task_id']);
			$now = date('Y-m-d H:i:s');
			$stmt = $this->db->prepare("
				INSERT INTO task_executions
				    (`task_assignee_id`, `started_by_user_id`, `started_at`, `created`, `modified`)
				VALUES
				    (:task_assignee_id, :started_by_user_id, :started_at, :created, :modified)
			");
			$stmt->execute([
				'task_assignee_id'  => $taskAssigneeId,
				'started_by_user_id' => $userId,
				'started_at'         => $now,
				'created'            => $now,
				'modified'           => $now,
			]);

			$executionId = (int) $this->db->lastInsertId();
			if ((string) ($assignee['task_status'] ?? '') === self::STATUS_OPEN)
				{
				$this->setTaskStatus((int) $assignee['task_id'], self::STATUS_IN_PROGRESS, $now);
				}

			$executionSnapshot = $this->findExecution($executionId);
			$this->auditCreate('task', 'task_executions', $executionId, $executionSnapshot, [
				'task_id' => (int) $assignee['task_id'],
				'task_assignee_id' => $taskAssigneeId,
				'action' => 'start_execution',
			]);

			$afterTask = $this->find((int) $assignee['task_id']);
			if ($beforeTask !== $afterTask)
				{
				$this->auditUpdate('task', 'tasks', (int) $assignee['task_id'], $beforeTask, $afterTask, [
					'source' => 'start_execution',
				]);
				}

			$this->db->commit();

			return $this->executionActionPayload((int) $assignee['task_id'], $taskAssigneeId);
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
	 * Pauses the currently open execution interval for an assignee.
	 **/
	public function pauseExecution(int $taskAssigneeId, int $userId): array
		{
		try
			{
			$this->db->beginTransaction();
			$assignee = $this->findAssignee($taskAssigneeId);

			if (!$assignee)
				{
				throw new \InvalidArgumentException('Responsável da tarefa não encontrado.');
				}

			$execution = $this->findOpenExecution($taskAssigneeId);
			if (!$execution)
				{
				throw new \InvalidArgumentException('Não existe execução em andamento para pausar.');
				}

			$executionId = (int) $execution['id'];
			$beforeData = $this->findExecution($executionId);
			$stmt = $this->db->prepare("
				UPDATE task_executions
				SET `paused_by_user_id` = :paused_by_user_id,
				    `paused_at` = :paused_at,
				    `modified` = :modified
				WHERE id = :id
				  AND paused_at IS NULL
			");
			$stmt->execute([
				'paused_by_user_id' => $userId,
				'paused_at'         => date('Y-m-d H:i:s'),
				'modified'          => date('Y-m-d H:i:s'),
				'id'                => $executionId,
			]);

			$afterData = $this->findExecution($executionId);
			$this->auditUpdate('task', 'task_executions', $executionId, $beforeData, $afterData, [
				'task_id' => (int) $assignee['task_id'],
				'task_assignee_id' => $taskAssigneeId,
				'action' => 'pause_execution',
			]);
			$this->db->commit();

			return $this->executionActionPayload((int) $assignee['task_id'], $taskAssigneeId);
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
	 * Returns all active assignees with their accumulated execution seconds.
	 **/
	public function getAssignees(int $taskId, bool $activeOnly = true): array
		{
		$peopleTable = $this->managerTable('people');
		$sql = "SELECT TaskAssignee.id,
		               TaskAssignee.task_id,
		               TaskAssignee.person_id,
		               TaskAssignee.active,
		               TaskAssignee.created,
		               TaskAssignee.modified,
		               Person.name AS person_name,
		               COALESCE(SUM(TIMESTAMPDIFF(SECOND, TaskExecution.started_at, COALESCE(TaskExecution.paused_at, NOW()))), 0) AS total_seconds,
		               MAX(CASE WHEN TaskExecution.paused_at IS NULL THEN TaskExecution.id ELSE NULL END) AS open_execution_id
		        FROM task_assignees AS TaskAssignee
		        INNER JOIN {$peopleTable} AS Person ON Person.id = TaskAssignee.person_id
		        LEFT JOIN task_executions AS TaskExecution ON TaskExecution.task_assignee_id = TaskAssignee.id
		        WHERE TaskAssignee.task_id = :task_id";

		if ($activeOnly)
			{
			$sql .= "
		          AND TaskAssignee.active = 1";
			}

		$sql .= "
		        GROUP BY TaskAssignee.id,
		                 TaskAssignee.task_id,
		                 TaskAssignee.person_id,
		                 TaskAssignee.active,
		                 TaskAssignee.created,
		                 TaskAssignee.modified,
		                 Person.name
		        ORDER BY TaskAssignee.active DESC,
		                 Person.name ASC";

		$stmt = $this->db->prepare($sql);
		$stmt->execute(['task_id' => $taskId]);

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Returns execution intervals for a task ordered by start date.
	 **/
	public function getExecutions(int $taskId): array
		{
		$peopleTable = $this->managerTable('people');
		$usersTable = $this->managerTable('users');
		$sql = "SELECT TaskExecution.id,
		               TaskExecution.task_assignee_id,
		               TaskExecution.started_by_user_id,
		               TaskExecution.paused_by_user_id,
		               TaskExecution.started_at,
		               TaskExecution.paused_at,
		               TaskExecution.created,
		               TaskExecution.modified,
		               TaskAssignee.person_id,
		               AssigneePerson.name AS person_name,
		               StartedUser.username AS started_by_username,
		               StartedPerson.name AS started_by_name,
		               PausedUser.username AS paused_by_username,
		               PausedPerson.name AS paused_by_name,
		               TIMESTAMPDIFF(SECOND, TaskExecution.started_at, COALESCE(TaskExecution.paused_at, NOW())) AS elapsed_seconds
		        FROM task_executions AS TaskExecution
		        INNER JOIN task_assignees AS TaskAssignee ON TaskAssignee.id = TaskExecution.task_assignee_id
		        INNER JOIN {$peopleTable} AS AssigneePerson ON AssigneePerson.id = TaskAssignee.person_id
		        INNER JOIN {$usersTable} AS StartedUser ON StartedUser.id = TaskExecution.started_by_user_id
		        INNER JOIN {$peopleTable} AS StartedPerson ON StartedPerson.id = StartedUser.person_id
		        LEFT JOIN {$usersTable} AS PausedUser ON PausedUser.id = TaskExecution.paused_by_user_id
		        LEFT JOIN {$peopleTable} AS PausedPerson ON PausedPerson.id = PausedUser.person_id
		        WHERE TaskAssignee.task_id = :task_id
		        ORDER BY TaskExecution.started_at ASC,
		                 TaskExecution.id ASC";

		$stmt = $this->db->prepare($sql);
		$stmt->execute(['task_id' => $taskId]);

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Returns comments for a task ordered by creation date.
	 **/
	public function getComments(int $taskId): array
		{
		$peopleTable = $this->managerTable('people');
		$usersTable = $this->managerTable('users');
		$sql = "SELECT TaskComment.id,
		               TaskComment.task_id,
		               TaskComment.user_id,
		               TaskComment.comment,
		               TaskComment.created,
		               TaskComment.modified,
		               User.username,
		               Person.name AS user_name
		        FROM task_comments AS TaskComment
		        INNER JOIN {$usersTable} AS User ON User.id = TaskComment.user_id
		        INNER JOIN {$peopleTable} AS Person ON Person.id = User.person_id
		        WHERE TaskComment.task_id = :task_id
		        ORDER BY TaskComment.created ASC,
		                 TaskComment.id ASC";

		$stmt = $this->db->prepare($sql);
		$stmt->execute(['task_id' => $taskId]);

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Returns people available to be assigned to tasks for the selected tenant.
	 **/
	public function getPersonOptions(int $tenantId): array
		{
		$peopleTable = $this->managerTable('people');
		$usersTable = $this->managerTable('users');
		$tenantUsersTable = $this->managerTable('tenant_users');
		$sql = "SELECT DISTINCT Person.id,
		               Person.name
		        FROM {$peopleTable} AS Person
		        INNER JOIN {$usersTable} AS User ON User.person_id = Person.id
		        INNER JOIN {$tenantUsersTable} AS TenantUser ON TenantUser.user_id = User.id
		                                                   AND TenantUser.tenant_id = :tenant_id
		        WHERE Person.active = 1
		        ORDER BY Person.name ASC";

		$stmt = $this->db->prepare($sql);
		$stmt->execute(['tenant_id' => $tenantId]);

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Returns autocomplete suggestions for tenant-scoped people.
	 **/
	public function autocompletePeople(string $term, int $tenantId, int $limit = 20): array
		{
		$term = trim($term);
		if ($term === '')
			{
			return [];
			}

		$limit = max(1, min(50, $limit));
		$peopleTable = $this->managerTable('people');
		$usersTable = $this->managerTable('users');
		$tenantUsersTable = $this->managerTable('tenant_users');
		$sql = "SELECT DISTINCT Person.id,
		               Person.name
		        FROM {$peopleTable} AS Person
		        INNER JOIN {$usersTable} AS User ON User.person_id = Person.id
		        INNER JOIN {$tenantUsersTable} AS TenantUser ON TenantUser.user_id = User.id
		                                                   AND TenantUser.tenant_id = :tenant_id
		        WHERE Person.active = 1
		          AND Person.name LIKE :term
		        ORDER BY Person.name ASC
		        LIMIT {$limit}";

		$stmt = $this->db->prepare($sql);
		$stmt->execute([
			'tenant_id' => $tenantId,
			'term' => '%' . $term . '%',
		]);

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Formats seconds as a compact duration string.
	 **/
	public static function formatDuration($seconds): string
		{
		$total = max(0, (int) $seconds);
		$hours = intdiv($total, 3600);
		$minutes = intdiv($total % 3600, 60);

		if ($hours > 0)
			{
			return sprintf('%dh %02dmin', $hours, $minutes);
			}

		return sprintf('%dmin', $minutes);
		}

	/**
	 * Formats a nullable date time value for screens and reports.
	 **/
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
	 * Appends task search filters to a SQL query.
	 **/
	private function appendSearchFilters(string &$sql, array &$params, array $filters): void
		{
		if (isset($filters['id']) && $filters['id'] !== '')
			{
			$sql .= " AND Task.id = :id";
			$params['id'] = (int) $filters['id'];
			}

		if (isset($filters['task_category_id']) && $filters['task_category_id'] !== '')
			{
			$sql .= " AND Task.task_category_id = :task_category_id";
			$params['task_category_id'] = (int) $filters['task_category_id'];
			}

		if (isset($filters['status']) && $filters['status'] !== '')
			{
			$sql .= " AND Task.status = :status";
			$params['status'] = (string) $filters['status'];
			}

		if (isset($filters['statuses']) && is_array($filters['statuses']) && $filters['statuses'] !== [])
			{
			$placeholders = [];
			foreach (array_values($filters['statuses']) as $index => $status)
				{
				if (!$this->isValidStatus((string) $status))
					{
					continue;
					}

				$key = 'status_' . $index;
				$placeholders[] = ':' . $key;
				$params[$key] = (string) $status;
				}

			if ($placeholders !== [])
				{
				$sql .= " AND Task.status IN (" . implode(', ', $placeholders) . ")";
				}
			}

		if (isset($filters['title']) && $filters['title'] !== '')
			{
			$sql .= " AND Task.title LIKE :title";
			$params['title'] = '%' . trim((string) $filters['title']) . '%';
			}

		if (isset($filters['description']) && $filters['description'] !== '')
			{
			$sql .= " AND Task.description LIKE :description";
			$params['description'] = '%' . trim((string) $filters['description']) . '%';
			}

		if (isset($filters['assignee_person_id']) && $filters['assignee_person_id'] !== '')
			{
			$sql .= " AND EXISTS (
				SELECT 1
				FROM task_assignees AS FilterTaskAssignee
				WHERE FilterTaskAssignee.task_id = Task.id
				  AND FilterTaskAssignee.active = 1
				  AND FilterTaskAssignee.person_id = :assignee_person_id
			)";
			$params['assignee_person_id'] = (int) $filters['assignee_person_id'];
			}

		if (isset($filters['third_party_name']) && $filters['third_party_name'] !== '')
			{
			$sql .= " AND (
				Task.third_party_name LIKE :third_party_name
				OR ThirdPartyPerson.name LIKE :third_party_name
			)";
			$params['third_party_name'] = '%' . trim((string) $filters['third_party_name']) . '%';
			}

		if (isset($filters['created_from']) && $filters['created_from'] !== '')
			{
			$sql .= " AND Task.created >= :created_from";
			$params['created_from'] = $this->normalizeDateFilter((string) $filters['created_from'], '00:00:00');
			}

		if (isset($filters['created_to']) && $filters['created_to'] !== '')
			{
			$sql .= " AND Task.created <= :created_to";
			$params['created_to'] = $this->normalizeDateFilter((string) $filters['created_to'], '23:59:59');
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
	 * Builds safe ORDER BY SQL for a task list.
	 **/
	private function orderBySql(string $sort, string $direction): string
		{
		$allowed = [
			'id' => 'Task.id',
			'title' => 'Task.title',
			'category' => 'TaskCategory.name',
			'status' => 'Task.status',
			'created' => 'Task.created',
			'modified' => 'Task.modified',
		];

		$column = $allowed[$sort] ?? $allowed['created'];
		$dir = strtolower($direction) === 'asc' ? 'ASC' : 'DESC';

		return "
			ORDER BY {$column} {$dir},
			         Task.id DESC
		";
		}

	/**
	 * Normalizes and validates task form payload.
	 **/
	private function normalizePayload(array $data, int $tenantId): array
		{
		$taskData = is_array($data['task'] ?? null) ? $data['task'] : $data;

		$title = trim((string) ($taskData['title'] ?? ''));
		if ($title === '')
			{
			throw new \InvalidArgumentException('Informe o título da tarefa.');
			}

		$status = (string) ($taskData['status'] ?? self::STATUS_OPEN);
		if (!$this->isValidStatus($status))
			{
			$status = self::STATUS_OPEN;
			}

		$categoryId = (int) ($taskData['task_category_id'] ?? 0);
		$thirdPartyPersonId = null;
		$thirdPartyName = null;
		if ($status === self::STATUS_WAITING_THIRD_PARTY)
			{
			$thirdPartyName = $this->nullableTrim($taskData['third_party_name'] ?? null);
			$candidateThirdPartyId = (int) ($taskData['third_party_person_id'] ?? 0);

			if ($candidateThirdPartyId > 0 && $this->personBelongsToTenant($candidateThirdPartyId, $tenantId))
				{
				$thirdPartyPersonId = $candidateThirdPartyId;
				}

			if ($thirdPartyName === null && $thirdPartyPersonId === null)
				{
				throw new \InvalidArgumentException('Informe o terceiro aguardado.');
				}
			}

		$assigneePersonIds = $this->normalizeAssigneeIds($taskData['assignee_person_ids'] ?? [], $tenantId);
		if ($assigneePersonIds === [])
			{
			throw new \InvalidArgumentException('Selecione ao menos um responsável.');
			}

		return [
			'task_category_id'      => $categoryId > 0 ? $categoryId : null,
			'title'                 => $title,
			'status'                => $status,
			'third_party_person_id' => $thirdPartyPersonId,
			'third_party_name'      => $thirdPartyName,
			'description'           => $this->sanitizeDescription((string) ($taskData['description'] ?? '')),
			'assignee_person_ids'   => $assigneePersonIds,
		];
		}

	/**
	 * Synchronizes task assignees preserving historical execution rows.
	 **/
	private function syncAssignees(int $taskId, array $personIds, string $now): void
		{
		$stmtDeactivate = $this->db->prepare("
			UPDATE task_assignees
			SET active = 0,
			    modified = :modified
			WHERE task_id = :task_id
		");
		$stmtDeactivate->execute([
			'modified' => $now,
			'task_id' => $taskId,
		]);

		$stmtExisting = $this->db->prepare("
			SELECT TaskAssignee.id
			FROM task_assignees AS TaskAssignee
			WHERE TaskAssignee.task_id = :task_id
			  AND TaskAssignee.person_id = :person_id
			LIMIT 1
		");
		$stmtUpdate = $this->db->prepare("
			UPDATE task_assignees
			SET active = 1,
			    modified = :modified
			WHERE id = :id
		");
		$stmtInsert = $this->db->prepare("
			INSERT INTO task_assignees
			    (`task_id`, `person_id`, `active`, `created`, `modified`)
			VALUES
			    (:task_id, :person_id, :active, :created, :modified)
		");

		foreach ($personIds as $personId)
			{
			$stmtExisting->execute([
				'task_id' => $taskId,
				'person_id' => $personId,
			]);
			$existingId = $stmtExisting->fetchColumn();

			if ($existingId)
				{
				$stmtUpdate->execute([
					'modified' => $now,
					'id' => (int) $existingId,
				]);
				continue;
				}

			$stmtInsert->execute([
				'task_id' => $taskId,
				'person_id' => $personId,
				'active' => 1,
				'created' => $now,
				'modified' => $now,
			]);
			}
		}

	/**
	 * Normalizes assignee ids and keeps only tenant-scoped people.
	 **/
	private function normalizeAssigneeIds($rawIds, int $tenantId): array
		{
		if (!is_array($rawIds))
			{
			$rawIds = [$rawIds];
			}

		$ids = [];
		foreach ($rawIds as $rawId)
			{
			$id = (int) $rawId;
			if ($id > 0 && $this->personBelongsToTenant($id, $tenantId))
				{
				$ids[$id] = $id;
				}
			}

		return array_values($ids);
		}

	/**
	 * Checks whether a manager person belongs to the current tenant through tenant_users.
	 **/
	private function personBelongsToTenant(int $personId, int $tenantId): bool
		{
		if ($personId <= 0 || $tenantId <= 0)
			{
			return false;
			}

		$usersTable = $this->managerTable('users');
		$tenantUsersTable = $this->managerTable('tenant_users');
		$sql = "SELECT 1
		        FROM {$usersTable} AS User
		        INNER JOIN {$tenantUsersTable} AS TenantUser ON TenantUser.user_id = User.id
		                                                   AND TenantUser.tenant_id = :tenant_id
		        WHERE User.person_id = :person_id
		        LIMIT 1";

		$stmt = $this->db->prepare($sql);
		$stmt->execute([
			'tenant_id' => $tenantId,
			'person_id' => $personId,
		]);

		return (bool) $stmt->fetchColumn();
		}

	/**
	 * Sanitizes the rich text description and removes tag attributes.
	 **/
	private function sanitizeDescription(string $description): string
		{
		$description = trim($description);
		if ($description === '')
			{
			throw new \InvalidArgumentException('Informe a descrição da tarefa.');
			}

		$allowedTags = '<b><strong><i><em><u><br><p><div><ul><ol><li>';
		$description = strip_tags($description, $allowedTags);
		$description = preg_replace('/<([a-z][a-z0-9]*)\b[^>]*>/i', '<$1>', $description) ?? '';
		$description = preg_replace('/javascript\s*:/i', '', $description) ?? '';

		return trim($description);
		}

	/**
	 * Checks whether the given status exists in the status catalog.
	 **/
	private function isValidStatus(string $status): bool
		{
		return array_key_exists($status, self::statuses());
		}

	/**
	 * Finds an assignee row with task status data.
	 **/
	private function findAssignee(int $taskAssigneeId)
		{
		$stmt = $this->db->prepare("SELECT TaskAssignee.id,
		                                  TaskAssignee.task_id,
		                                  TaskAssignee.person_id,
		                                  TaskAssignee.active,
		                                  Task.status AS task_status
		                           FROM task_assignees AS TaskAssignee
		                           INNER JOIN tasks AS Task ON Task.id = TaskAssignee.task_id
		                           WHERE TaskAssignee.id = :id
		                           LIMIT 1");
		$stmt->execute(['id' => $taskAssigneeId]);

		return $stmt->fetch(PDO::FETCH_ASSOC);
		}

	/**
	 * Returns true when an assignee has an open execution interval.
	 **/
	private function hasOpenExecution(int $taskAssigneeId): bool
		{
		return (bool) $this->findOpenExecution($taskAssigneeId);
		}

	/**
	 * Finds the open execution interval for an assignee.
	 **/
	private function findOpenExecution(int $taskAssigneeId)
		{
		$stmt = $this->db->prepare("SELECT TaskExecution.id,
		                                  TaskExecution.task_assignee_id,
		                                  TaskExecution.started_by_user_id,
		                                  TaskExecution.paused_by_user_id,
		                                  TaskExecution.started_at,
		                                  TaskExecution.paused_at,
		                                  TaskExecution.created,
		                                  TaskExecution.modified
		                           FROM task_executions AS TaskExecution
		                           WHERE TaskExecution.task_assignee_id = :task_assignee_id
		                             AND TaskExecution.paused_at IS NULL
		                           ORDER BY TaskExecution.started_at DESC,
		                                    TaskExecution.id DESC
		                           LIMIT 1");
		$stmt->execute(['task_assignee_id' => $taskAssigneeId]);

		return $stmt->fetch(PDO::FETCH_ASSOC);
		}

	/**
	 * Finds one execution row.
	 **/
	private function findExecution(int $executionId)
		{
		$stmt = $this->db->prepare("SELECT TaskExecution.id,
		                                  TaskExecution.task_assignee_id,
		                                  TaskExecution.started_by_user_id,
		                                  TaskExecution.paused_by_user_id,
		                                  TaskExecution.started_at,
		                                  TaskExecution.paused_at,
		                                  TaskExecution.created,
		                                  TaskExecution.modified
		                           FROM task_executions AS TaskExecution
		                           WHERE TaskExecution.id = :id
		                           LIMIT 1");
		$stmt->execute(['id' => $executionId]);

		return $stmt->fetch(PDO::FETCH_ASSOC);
		}

	/**
	 * Finds one comment row.
	 **/
	private function findComment(int $commentId)
		{
		$stmt = $this->db->prepare("SELECT TaskComment.id,
		                                  TaskComment.task_id,
		                                  TaskComment.user_id,
		                                  TaskComment.comment,
		                                  TaskComment.created,
		                                  TaskComment.modified
		                           FROM task_comments AS TaskComment
		                           WHERE TaskComment.id = :id
		                           LIMIT 1");
		$stmt->execute(['id' => $commentId]);

		return $stmt->fetch(PDO::FETCH_ASSOC);
		}

	/**
	 * Updates only the task status.
	 **/
	private function setTaskStatus(int $taskId, string $status, string $now): void
		{
		$stmt = $this->db->prepare("
			UPDATE tasks
			SET status = :status,
			    modified = :modified
			WHERE id = :id
		");
		$stmt->execute([
			'status' => $status,
			'modified' => $now,
			'id' => $taskId,
		]);
		}

	/**
	 * Builds the JSON payload returned after start or pause actions.
	 **/
	private function executionActionPayload(int $taskId, int $taskAssigneeId): array
		{
		$task = $this->find($taskId);
		$assignees = $this->getAssignees($taskId);
		$currentAssignee = null;
		foreach ($assignees as $assignee)
			{
			if ((int) $assignee['id'] === $taskAssigneeId)
				{
				$currentAssignee = $assignee;
				break;
				}
			}

		return [
			'task' => $task,
			'assignees' => $assignees,
			'assignee' => $currentAssignee,
			'status_label' => self::statusLabel((string) ($task['status'] ?? '')),
			'status_badge_class' => self::statusBadgeClass((string) ($task['status'] ?? '')),
			'total_duration' => self::formatDuration((int) ($task['total_seconds'] ?? 0)),
			'assignee_duration' => self::formatDuration((int) ($currentAssignee['total_seconds'] ?? 0)),
			'is_running' => !empty($currentAssignee['open_execution_id']),
		];
		}

	/**
	 * Normalizes a date filter to a SQL datetime value.
	 **/
	private function normalizeDateFilter(string $date, string $time): string
		{
		$date = trim($date);
		if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $date) === 1)
			{
			$date = dateBrToUs($date);
			}

		return $date . ' ' . $time;
		}

	/**
	 * Returns a trimmed string or null when the value is empty.
	 * @param mixed $value Raw value.
	 **/
	private function nullableTrim($value): ?string
		{
		$text = trim((string) $value);
		return ($text !== '' ? $text : null);
		}

	/**
	 * Returns a table qualified with the manager database name.
	 **/
	private function managerTable(string $table): string
		{
		return sprintf('`%s`.`%s`', Database::getManagerDatabaseName(), trim($table));
		}
	}
?>
