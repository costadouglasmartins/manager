<?php

namespace App\Models;

use App\Core\Database;
use App\Core\TenantModel;
use App\Services\ScheduleRecurrenceService;
use PDO;

class ScheduleEvent extends TenantModel
	{
	public const STATUS_SCHEDULED = 'scheduled';
	public const STATUS_COMPLETED = 'completed';
	public const STATUS_CANCELED = 'canceled';

	public const FREQUENCY_DAILY = 'daily';
	public const FREQUENCY_WEEKLY = 'weekly';
	public const FREQUENCY_MONTHLY = 'monthly';

	/**
	 * Returns the status catalog used by forms, lists and reports.
	 **/
	public static function statuses(): array
		{
		return [
			self::STATUS_SCHEDULED => [
				'label' => 'Agendado',
				'badge' => 'text-bg-primary',
			],
			self::STATUS_COMPLETED => [
				'label' => 'Concluído',
				'badge' => 'text-bg-success',
			],
			self::STATUS_CANCELED => [
				'label' => 'Cancelado',
				'badge' => 'text-bg-secondary',
			],
		];
		}

	/**
	 * Returns the recurrence frequency catalog.
	 **/
	public static function frequencies(): array
		{
		return [
			self::FREQUENCY_DAILY => 'Diária',
			self::FREQUENCY_WEEKLY => 'Semanal',
			self::FREQUENCY_MONTHLY => 'Mensal',
		];
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
	 * Returns a display label for a recurrence frequency.
	 **/
	public static function frequencyLabel(?string $frequency): string
		{
		$frequencies = self::frequencies();
		return (string) ($frequencies[(string) $frequency] ?? (string) $frequency);
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
	 * Formats a nullable date value for screens and reports.
	 **/
	public static function formatDate($value): string
		{
		$value = trim((string) ($value ?? ''));
		if ($value === '' || $value === '0000-00-00')
			{
			return '';
			}

		try
			{
			return (new \DateTimeImmutable($value))->format('d/m/Y');
			}
		catch (\Throwable $e)
			{
			return $value;
			}
		}

	/**
	 * Returns the base schedule event select query.
	 **/
	private function baseSelectSql(): string
		{
		$peopleTable = $this->managerTable('people');
		$usersTable = $this->managerTable('users');

		return "SELECT ScheduleEvent.id,
		              ScheduleEvent.schedule_category_id,
		              ScheduleEvent.schedule_events_recurrence_id,
		              ScheduleEvent.recurrence_sequence,
		              ScheduleEvent.title,
		              ScheduleEvent.description,
		              ScheduleEvent.location,
		              ScheduleEvent.all_day,
		              ScheduleEvent.starts_at,
		              ScheduleEvent.ends_at,
		              ScheduleEvent.status,
		              ScheduleEvent.created_by_user_id,
		              ScheduleEvent.created,
		              ScheduleEvent.modified,
		              ScheduleCategory.name AS schedule_category_name,
		              ScheduleCategory.background_color AS schedule_category_background_color,
		              ScheduleCategory.description_color AS schedule_category_description_color,
		              CreatedUser.username AS created_by_username,
		              CreatedPerson.name AS created_by_name,
		              COALESCE(
		              	(
		              		SELECT GROUP_CONCAT(ParticipantPerson.name ORDER BY ParticipantPerson.name ASC SEPARATOR ', ')
		              		FROM schedule_events_participants AS ActiveParticipant
		              		INNER JOIN {$peopleTable} AS ParticipantPerson ON ParticipantPerson.id = ActiveParticipant.person_id
		              		WHERE ActiveParticipant.schedule_event_id = ScheduleEvent.id
		              		  AND ActiveParticipant.active = 1
		              	),
		              	''
		              ) AS participant_names,
		              COALESCE(
		              	(
		              		SELECT COUNT(*)
		              		FROM schedule_events_participants AS CountParticipant
		              		WHERE CountParticipant.schedule_event_id = ScheduleEvent.id
		              		  AND CountParticipant.active = 1
		              	),
		              	0
		              ) AS participant_count
		       FROM schedule_events AS ScheduleEvent
		       LEFT JOIN schedule_categories AS ScheduleCategory ON ScheduleCategory.id = ScheduleEvent.schedule_category_id
		       INNER JOIN {$usersTable} AS CreatedUser ON CreatedUser.id = ScheduleEvent.created_by_user_id
		       INNER JOIN {$peopleTable} AS CreatedPerson ON CreatedPerson.id = CreatedUser.person_id";
		}

	/**
	 * Creates one schedule event or a set of recurring events.
	 *
	 * @param array<string, mixed> $data Request payload.
	 * @return array<string, mixed>
	 **/
	public function create(array $data, int $userId, int $tenantId): array
		{
		$payload = $this->normalizePayload($data, $tenantId, true);
		$now = date('Y-m-d H:i:s');

		try
			{
			$this->db->beginTransaction();

			$eventIds = [];
			$recurrenceId = null;

			if ($payload['recurrence_enabled'])
				{
				$recurrenceId = $this->insertRecurrence($payload, $userId, $now);
				$service = new ScheduleRecurrenceService();
				$occurrences = $service->buildOccurrences($payload['recurrence']);

				foreach ($occurrences as $occurrence)
					{
					$eventId = $this->insertEvent(
						$payload,
						$userId,
						$now,
						$recurrenceId,
						(int) $occurrence['sequence'],
						(string) $occurrence['starts_at'],
						$occurrence['ends_at']
					);
					$this->syncParticipants($eventId, $payload['participant_person_ids'], $now);
					$eventIds[] = $eventId;
					}
				}
			else
				{
				$eventId = $this->insertEvent($payload, $userId, $now, null, null, $payload['starts_at'], $payload['ends_at']);
				$this->syncParticipants($eventId, $payload['participant_person_ids'], $now);
				$eventIds[] = $eventId;
				}

			$firstEventId = (int) ($eventIds[0] ?? 0);
			$eventSnapshot = $firstEventId > 0 ? $this->findAllByScheduleEventId($firstEventId) : [];
			$this->auditCreate('schedule', 'schedule_events', $firstEventId, $eventSnapshot, [
				'affected_tables' => ['schedule_events', 'schedule_events_participants', 'schedule_events_recurrences'],
				'schedule_events_recurrence_id' => $recurrenceId,
				'generated_event_ids' => $eventIds,
				'generated_events_count' => count($eventIds),
			]);
			$this->db->commit();

			return [
				'schedule_event_id' => $firstEventId,
				'schedule_events_recurrence_id' => $recurrenceId,
				'generated_events_count' => count($eventIds),
			];
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
	 * Updates one schedule event and synchronizes its participants.
	 *
	 * @param mixed $id Event identifier.
	 * @param array<string, mixed> $data Request payload.
	 **/
	public function update($id, array $data, int $tenantId): void
		{
		$eventId = (int) $id;
		$payload = $this->normalizePayload($data, $tenantId, false);
		$now = date('Y-m-d H:i:s');

		try
			{
			$this->db->beginTransaction();
			$beforeData = $this->findAllByScheduleEventId($eventId);

			if (empty($beforeData['schedule_event']))
				{
				throw new \InvalidArgumentException('Evento não encontrado.');
				}

			$stmt = $this->db->prepare("
				UPDATE schedule_events
				SET `schedule_category_id` = :schedule_category_id,
				    `title` = :title,
				    `description` = :description,
				    `location` = :location,
				    `all_day` = :all_day,
				    `starts_at` = :starts_at,
				    `ends_at` = :ends_at,
				    `status` = :status,
				    `modified` = :modified
				WHERE id = :id
			");
			$stmt->execute([
				'schedule_category_id' => $payload['schedule_category_id'],
				'title' => $payload['title'],
				'description' => $payload['description'],
				'location' => $payload['location'],
				'all_day' => $payload['all_day'],
				'starts_at' => $payload['starts_at'],
				'ends_at' => $payload['ends_at'],
				'status' => $payload['status'],
				'modified' => $now,
				'id' => $eventId,
			]);

			$this->syncParticipants($eventId, $payload['participant_person_ids'], $now);
			$afterData = $this->findAllByScheduleEventId($eventId);
			$this->auditUpdate('schedule', 'schedule_events', $eventId, $beforeData, $afterData, [
				'affected_tables' => ['schedule_events', 'schedule_events_participants'],
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
	 * Deletes an existing schedule event and its participants.
	 *
	 * @param mixed $id Event identifier.
	 **/
	public function delete($id): void
		{
		$eventId = (int) $id;
		if ($eventId <= 0)
			{
			throw new \InvalidArgumentException('Evento inválido.');
			}

		try
			{
			$this->db->beginTransaction();

			$event = $this->findAllByScheduleEventId($eventId);
			if (empty($event['schedule_event']))
				{
				throw new \InvalidArgumentException('Evento não encontrado.');
				}

			$stmt = $this->db->prepare("DELETE FROM schedule_events WHERE id = :id");
			$stmt->execute(['id' => $eventId]);

			$this->auditDelete('schedule', 'schedule_events', $eventId, $event, [
				'affected_tables' => ['schedule_events', 'schedule_events_participants'],
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
	 * Finds a schedule event by id.
	 *
	 * @param mixed $id Event identifier.
	 * @return array<string, mixed>|false
	 **/
	public function find($id)
		{
		$stmt = $this->db->prepare($this->baseSelectSql() . "
			WHERE ScheduleEvent.id = :id
		");
		$stmt->execute(['id' => (int) $id]);

		return $stmt->fetch(PDO::FETCH_ASSOC);
		}

	/**
	 * Searches schedule events using filters, pagination and safe ordering.
	 **/
	public function search(array $filters = [], ?int $limit = 10, int $offset = 0, string $sort = 'starts_at', string $direction = 'asc'): array
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
	 * Counts schedule events matching filters.
	 **/
	public function count(array $filters = []): int
		{
		$peopleTable = $this->managerTable('people');
		$usersTable = $this->managerTable('users');
		$sql = "SELECT COUNT(*) AS total
		        FROM schedule_events AS ScheduleEvent
		        LEFT JOIN schedule_categories AS ScheduleCategory ON ScheduleCategory.id = ScheduleEvent.schedule_category_id
		        INNER JOIN {$usersTable} AS CreatedUser ON CreatedUser.id = ScheduleEvent.created_by_user_id
		        INNER JOIN {$peopleTable} AS CreatedPerson ON CreatedPerson.id = CreatedUser.person_id
		        WHERE 1 = 1";
		$params = [];

		$this->appendSearchFilters($sql, $params, $filters);

		$stmt = $this->db->prepare($sql);
		$this->bindSearchParams($stmt, $params);
		$stmt->execute();

		return (int) $stmt->fetchColumn();
		}

	/**
	 * Returns events in the range requested by FullCalendar.
	 **/
	public function calendarEvents(string $start, string $end): array
		{
		return $this->search([
			'status_not' => self::STATUS_CANCELED,
			'range_start' => $start,
			'range_end' => $end,
		], null, 0, 'starts_at', 'asc');
		}

	/**
	 * Returns upcoming events used by the dashboard.
	 **/
	public function dashboardEvents(int $limit = 10): array
		{
		return $this->search([
			'status' => self::STATUS_SCHEDULED,
			'starts_from' => date('Y-m-d'),
		], $limit, 0, 'starts_at', 'asc');
		}

	/**
	 * Returns the full details payload for information screens and audit snapshots.
	 *
	 * @param mixed $id Event identifier.
	 * @return array<string, mixed>
	 **/
	public function findAllByScheduleEventId($id): array
		{
		$eventId = (int) $id;
		$event = $this->find($eventId);

		return [
			'schedule_event' => $event,
			'participants' => $this->getParticipants($eventId, false),
			'recurrence' => $event ? $this->findRecurrence((int) ($event['schedule_events_recurrence_id'] ?? 0)) : null,
		];
		}

	/**
	 * Returns all active participants for a schedule event.
	 **/
	public function getParticipants(int $eventId, bool $activeOnly = true): array
		{
		$peopleTable = $this->managerTable('people');
		$sql = "SELECT Participant.id,
		               Participant.schedule_event_id,
		               Participant.person_id,
		               Participant.active,
		               Participant.created,
		               Participant.modified,
		               Person.name AS person_name
		        FROM schedule_events_participants AS Participant
		        INNER JOIN {$peopleTable} AS Person ON Person.id = Participant.person_id
		        WHERE Participant.schedule_event_id = :schedule_event_id";

		if ($activeOnly)
			{
			$sql .= "
		          AND Participant.active = 1";
			}

		$sql .= "
		        ORDER BY Participant.active DESC,
		                 Person.name ASC";

		$stmt = $this->db->prepare($sql);
		$stmt->execute(['schedule_event_id' => $eventId]);

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Returns people available to participate in schedule events for the selected tenant.
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
	 * Inserts one recurrence row.
	 **/
	private function insertRecurrence(array $payload, int $userId, string $now): int
		{
		$stmt = $this->db->prepare("
			INSERT INTO schedule_events_recurrences
			    (`schedule_category_id`, `title`, `description`, `location`, `all_day`, `start_time`, `end_time`,
			     `frequency`, `interval_value`, `period_start`, `period_end`, `allow_weekends`,
			     `created_by_user_id`, `active`, `created`, `modified`)
			VALUES
			    (:schedule_category_id, :title, :description, :location, :all_day, :start_time, :end_time,
			     :frequency, :interval_value, :period_start, :period_end, :allow_weekends,
			     :created_by_user_id, :active, :created, :modified)
		");
		$stmt->execute([
			'schedule_category_id' => $payload['schedule_category_id'],
			'title' => $payload['title'],
			'description' => $payload['description'],
			'location' => $payload['location'],
			'all_day' => $payload['all_day'],
			'start_time' => $payload['recurrence']['start_time'],
			'end_time' => $payload['recurrence']['end_time'],
			'frequency' => $payload['recurrence']['frequency'],
			'interval_value' => $payload['recurrence']['interval_value'],
			'period_start' => $payload['recurrence']['period_start'],
			'period_end' => $payload['recurrence']['period_end'],
			'allow_weekends' => $payload['recurrence']['allow_weekends'],
			'created_by_user_id' => $userId,
			'active' => 1,
			'created' => $now,
			'modified' => $now,
		]);

		return (int) $this->db->lastInsertId();
		}

	/**
	 * Inserts one event row and returns its identifier.
	 **/
	private function insertEvent(array $payload, int $userId, string $now, ?int $recurrenceId, ?int $sequence, string $startsAt, ?string $endsAt): int
		{
		$stmt = $this->db->prepare("
			INSERT INTO schedule_events
			    (`schedule_category_id`, `schedule_events_recurrence_id`, `recurrence_sequence`, `title`, `description`,
			     `location`, `all_day`, `starts_at`, `ends_at`, `status`, `created_by_user_id`, `created`, `modified`)
			VALUES
			    (:schedule_category_id, :schedule_events_recurrence_id, :recurrence_sequence, :title, :description,
			     :location, :all_day, :starts_at, :ends_at, :status, :created_by_user_id, :created, :modified)
		");
		$stmt->execute([
			'schedule_category_id' => $payload['schedule_category_id'],
			'schedule_events_recurrence_id' => $recurrenceId,
			'recurrence_sequence' => $sequence,
			'title' => $payload['title'],
			'description' => $payload['description'],
			'location' => $payload['location'],
			'all_day' => $payload['all_day'],
			'starts_at' => $startsAt,
			'ends_at' => $endsAt,
			'status' => $payload['status'],
			'created_by_user_id' => $userId,
			'created' => $now,
			'modified' => $now,
		]);

		return (int) $this->db->lastInsertId();
		}

	/**
	 * Synchronizes event participants preserving inactive historical rows.
	 **/
	private function syncParticipants(int $eventId, array $personIds, string $now): void
		{
		$stmtDeactivate = $this->db->prepare("
			UPDATE schedule_events_participants
			SET active = 0,
			    modified = :modified
			WHERE schedule_event_id = :schedule_event_id
		");
		$stmtDeactivate->execute([
			'modified' => $now,
			'schedule_event_id' => $eventId,
		]);

		$stmtExisting = $this->db->prepare("
			SELECT Participant.id
			FROM schedule_events_participants AS Participant
			WHERE Participant.schedule_event_id = :schedule_event_id
			  AND Participant.person_id = :person_id
			LIMIT 1
		");
		$stmtUpdate = $this->db->prepare("
			UPDATE schedule_events_participants
			SET active = 1,
			    modified = :modified
			WHERE id = :id
		");
		$stmtInsert = $this->db->prepare("
			INSERT INTO schedule_events_participants
			    (`schedule_event_id`, `person_id`, `active`, `created`, `modified`)
			VALUES
			    (:schedule_event_id, :person_id, :active, :created, :modified)
		");

		foreach ($personIds as $personId)
			{
			$stmtExisting->execute([
				'schedule_event_id' => $eventId,
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
				'schedule_event_id' => $eventId,
				'person_id' => $personId,
				'active' => 1,
				'created' => $now,
				'modified' => $now,
			]);
			}
		}

	/**
	 * Finds a recurrence row by id.
	 **/
	private function findRecurrence(int $recurrenceId)
		{
		if ($recurrenceId <= 0)
			{
			return null;
			}

		$stmt = $this->db->prepare("SELECT Recurrence.id,
		                                  Recurrence.schedule_category_id,
		                                  Recurrence.title,
		                                  Recurrence.description,
		                                  Recurrence.location,
		                                  Recurrence.all_day,
		                                  Recurrence.start_time,
		                                  Recurrence.end_time,
		                                  Recurrence.frequency,
		                                  Recurrence.interval_value,
		                                  Recurrence.period_start,
		                                  Recurrence.period_end,
		                                  Recurrence.allow_weekends,
		                                  Recurrence.created_by_user_id,
		                                  Recurrence.active,
		                                  Recurrence.created,
		                                  Recurrence.modified
		                           FROM schedule_events_recurrences AS Recurrence
		                           WHERE Recurrence.id = :id
		                           LIMIT 1");
		$stmt->execute(['id' => $recurrenceId]);

		return $stmt->fetch(PDO::FETCH_ASSOC);
		}

	/**
	 * Appends schedule event search filters to a SQL query.
	 **/
	private function appendSearchFilters(string &$sql, array &$params, array $filters): void
		{
		if (isset($filters['id']) && $filters['id'] !== '')
			{
			$sql .= " AND ScheduleEvent.id = :id";
			$params['id'] = (int) $filters['id'];
			}

		if (isset($filters['schedule_category_id']) && $filters['schedule_category_id'] !== '')
			{
			$sql .= " AND ScheduleEvent.schedule_category_id = :schedule_category_id";
			$params['schedule_category_id'] = (int) $filters['schedule_category_id'];
			}

		if (isset($filters['status']) && $filters['status'] !== '')
			{
			$sql .= " AND ScheduleEvent.status = :status";
			$params['status'] = (string) $filters['status'];
			}

		if (isset($filters['status_not']) && $filters['status_not'] !== '')
			{
			$sql .= " AND ScheduleEvent.status <> :status_not";
			$params['status_not'] = (string) $filters['status_not'];
			}

		if (isset($filters['title']) && $filters['title'] !== '')
			{
			$sql .= " AND ScheduleEvent.title LIKE :title";
			$params['title'] = '%' . trim((string) $filters['title']) . '%';
			}

		if (isset($filters['location']) && $filters['location'] !== '')
			{
			$sql .= " AND ScheduleEvent.location LIKE :location";
			$params['location'] = '%' . trim((string) $filters['location']) . '%';
			}

		if (isset($filters['participant_person_id']) && $filters['participant_person_id'] !== '')
			{
			$sql .= " AND EXISTS (
				SELECT 1
				FROM schedule_events_participants AS FilterParticipant
				WHERE FilterParticipant.schedule_event_id = ScheduleEvent.id
				  AND FilterParticipant.active = 1
				  AND FilterParticipant.person_id = :participant_person_id
			)";
			$params['participant_person_id'] = (int) $filters['participant_person_id'];
			}

		if (isset($filters['starts_from']) && $filters['starts_from'] !== '')
			{
			$sql .= " AND ScheduleEvent.starts_at >= :starts_from";
			$params['starts_from'] = $this->normalizeDateFilter((string) $filters['starts_from'], '00:00:00');
			}

		if (isset($filters['starts_to']) && $filters['starts_to'] !== '')
			{
			$sql .= " AND ScheduleEvent.starts_at <= :starts_to";
			$params['starts_to'] = $this->normalizeDateFilter((string) $filters['starts_to'], '23:59:59');
			}

		if (isset($filters['range_start']) && $filters['range_start'] !== '')
			{
			$sql .= " AND COALESCE(ScheduleEvent.ends_at, ScheduleEvent.starts_at) >= :range_start";
			$params['range_start'] = $this->normalizeCalendarRangeDate((string) $filters['range_start'], '00:00:00');
			}

		if (isset($filters['range_end']) && $filters['range_end'] !== '')
			{
			$sql .= " AND ScheduleEvent.starts_at < :range_end";
			$params['range_end'] = $this->normalizeCalendarRangeDate((string) $filters['range_end'], '23:59:59');
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
	 * Builds safe ORDER BY SQL for event lists.
	 **/
	private function orderBySql(string $sort, string $direction): string
		{
		$allowed = [
			'id' => 'ScheduleEvent.id',
			'title' => 'ScheduleEvent.title',
			'category' => 'ScheduleCategory.name',
			'status' => 'ScheduleEvent.status',
			'starts_at' => 'ScheduleEvent.starts_at',
			'created' => 'ScheduleEvent.created',
			'modified' => 'ScheduleEvent.modified',
		];

		$column = $allowed[$sort] ?? $allowed['starts_at'];
		$dir = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';

		return "
			ORDER BY {$column} {$dir},
			         ScheduleEvent.id DESC
		";
		}

	/**
	 * Normalizes and validates event form payload.
	 **/
	private function normalizePayload(array $data, int $tenantId, bool $allowRecurrence): array
		{
		$eventData = is_array($data['schedule'] ?? null) ? $data['schedule'] : $data;
		$recurrenceData = is_array($data['recurrence'] ?? null) ? $data['recurrence'] : [];
		if ($recurrenceData === [] && is_array($eventData['recurrence'] ?? null))
			{
			$recurrenceData = $eventData['recurrence'];
			}

		$title = trim((string) ($eventData['title'] ?? ''));
		if ($title === '')
			{
			throw new \InvalidArgumentException('Informe o título do evento.');
			}

		$status = (string) ($eventData['status'] ?? self::STATUS_SCHEDULED);
		if (!$this->isValidStatus($status))
			{
			$status = self::STATUS_SCHEDULED;
			}

		$categoryId = (int) ($eventData['schedule_category_id'] ?? 0);
		$allDay = ((string) ($eventData['all_day'] ?? '0') === '1') ? 1 : 0;
		$dateTimes = $this->normalizeEventDateTimes($eventData, $allDay === 1);
		$participantPersonIds = $this->normalizeParticipantIds($eventData['participant_person_ids'] ?? [], $tenantId);
		$recurrenceEnabled = $allowRecurrence && ((string) ($recurrenceData['enabled'] ?? '0') === '1');

		$payload = [
			'schedule_category_id' => $categoryId > 0 ? $categoryId : null,
			'title' => $title,
			'description' => sanitizeRichText($eventData['description'] ?? null),
			'location' => $this->nullableTrim($eventData['location'] ?? null),
			'all_day' => $allDay,
			'starts_at' => $dateTimes['starts_at'],
			'ends_at' => $dateTimes['ends_at'],
			'status' => $status,
			'participant_person_ids' => $participantPersonIds,
			'recurrence_enabled' => $recurrenceEnabled,
			'recurrence' => [],
		];

		if ($recurrenceEnabled)
			{
			$payload['recurrence'] = $this->normalizeRecurrencePayload($eventData, $recurrenceData, $payload);
			}

		return $payload;
		}

	/**
	 * Normalizes one event start and end date/time.
	 **/
	private function normalizeEventDateTimes(array $eventData, bool $allDay): array
		{
		$startDate = $this->date((string) ($eventData['start_date'] ?? ''));
		$endDateValue = trim((string) ($eventData['end_date'] ?? ''));
		$endDate = $endDateValue !== '' ? $this->date($endDateValue) : null;

		if ($allDay)
			{
			$startsAt = $startDate->format('Y-m-d') . ' 00:00:00';
			$endsAt = $endDate !== null ? $endDate->format('Y-m-d') . ' 23:59:59' : null;

			if ($endsAt !== null && $endsAt < $startsAt)
				{
				throw new \InvalidArgumentException('A data final deve ser maior ou igual à data inicial.');
				}

			return [
				'starts_at' => $startsAt,
				'ends_at' => $endsAt,
			];
			}

		$startTime = $this->time((string) ($eventData['start_time'] ?? ''), true);
		$endTime = $this->time((string) ($eventData['end_time'] ?? ''), false);
		$startsAt = $startDate->format('Y-m-d') . ' ' . $startTime;
		$endsAt = null;

		if ($endTime !== null || $endDate !== null)
			{
			$endDate = $endDate ?? $startDate;
			$endsAt = $endDate->format('Y-m-d') . ' ' . ($endTime ?? '23:59:59');

			if ($endsAt <= $startsAt)
				{
				throw new \InvalidArgumentException('A data/hora final deve ser maior que a inicial.');
				}
			}

		return [
			'starts_at' => $startsAt,
			'ends_at' => $endsAt,
		];
		}

	/**
	 * Normalizes recurrence fields.
	 **/
	private function normalizeRecurrencePayload(array $eventData, array $recurrenceData, array $payload): array
		{
		$frequency = (string) ($recurrenceData['frequency'] ?? self::FREQUENCY_WEEKLY);
		if (!array_key_exists($frequency, self::frequencies()))
			{
			$frequency = self::FREQUENCY_WEEKLY;
			}

		$interval = max(1, min(365, (int) ($recurrenceData['interval_value'] ?? 1)));
		$periodStartValue = trim((string) ($recurrenceData['period_start'] ?? ''));
		if ($periodStartValue === '')
			{
			$periodStartValue = (string) ($eventData['start_date'] ?? '');
			}

		$periodStart = $this->date($periodStartValue);
		$periodEnd = $this->date((string) ($recurrenceData['period_end'] ?? ''));
		$allowWeekends = ((string) ($recurrenceData['allow_weekends'] ?? '0') === '1') ? 1 : 0;
		$allDay = (int) $payload['all_day'] === 1;

		return [
			'all_day' => $payload['all_day'],
			'start_time' => $allDay ? null : $this->time((string) ($eventData['start_time'] ?? ''), true),
			'end_time' => $allDay ? null : $this->time((string) ($eventData['end_time'] ?? ''), false),
			'frequency' => $frequency,
			'interval_value' => $interval,
			'period_start' => $periodStart->format('Y-m-d'),
			'period_end' => $periodEnd->format('Y-m-d'),
			'allow_weekends' => $allowWeekends,
		];
		}

	/**
	 * Normalizes participant ids and keeps only tenant-scoped people.
	 **/
	private function normalizeParticipantIds($rawIds, int $tenantId): array
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
	 * Checks whether a status exists in the status catalog.
	 **/
	private function isValidStatus(string $status): bool
		{
		return array_key_exists($status, self::statuses());
		}

	/**
	 * Parses a strict date.
	 **/
	private function date(string $value): \DateTimeImmutable
		{
		$date = \DateTimeImmutable::createFromFormat('!Y-m-d', trim($value));
		$errors = \DateTimeImmutable::getLastErrors();
		if ($date === false || (($errors['warning_count'] ?? 0) > 0) || (($errors['error_count'] ?? 0) > 0))
			{
			throw new \InvalidArgumentException('Informe uma data válida.');
			}

		return $date;
		}

	/**
	 * Parses a strict time and returns it with seconds.
	 **/
	private function time(string $value, bool $required): ?string
		{
		$value = trim($value);
		if ($value === '')
			{
			if ($required)
				{
				throw new \InvalidArgumentException('Informe um horário válido.');
				}

			return null;
			}

		if (preg_match('/^\d{2}:\d{2}$/', $value) === 1)
			{
			$value .= ':00';
			}

		$time = \DateTimeImmutable::createFromFormat('!H:i:s', $value);
		$errors = \DateTimeImmutable::getLastErrors();
		if ($time === false || (($errors['warning_count'] ?? 0) > 0) || (($errors['error_count'] ?? 0) > 0))
			{
			throw new \InvalidArgumentException('Informe um horário válido.');
			}

		return $time->format('H:i:s');
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

		return substr($date, 0, 10) . ' ' . $time;
		}

	/**
	 * Normalizes an ISO-like FullCalendar range value.
	 **/
	private function normalizeCalendarRangeDate(string $value, string $time): string
		{
		$value = trim($value);
		if ($value === '')
			{
			return date('Y-m-d') . ' ' . $time;
			}

		return substr($value, 0, 10) . ' ' . $time;
		}

	/**
	 * Returns a trimmed string or null when the value is empty.
	 *
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
