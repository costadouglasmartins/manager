<?php

namespace App\Services;

use App\Core\Database;
use App\Core\TenantContext;
use PDO;

class AuditLogService
	{
	private PDO $db;

	/**
	 * Creates the audit logger using the active write connection.
	 **/
	public function __construct(PDO $db)
		{
		$this->db = $db;
		}

	/**
	 * Persists a create event in manager_main.logs.
	 * @param string $module Source module name.
	 * @param string $entityName Main entity/table name.
	 * @param mixed $entityId Main entity identifier.
	 * @param mixed $afterData Snapshot after the write succeeds.
	 * @param array $context Additional context for the event.
	 **/
	public function logCreate(string $module, string $entityName, $entityId, $afterData, array $context = []): void
		{
		$this->logEvent($module, 'create', $entityName, $entityId, null, $afterData, $context);
		}

	/**
	 * Persists an update event in manager_main.logs.
	 * @param string $module Source module name.
	 * @param string $entityName Main entity/table name.
	 * @param mixed $entityId Main entity identifier.
	 * @param mixed $beforeData Snapshot before the write.
	 * @param mixed $afterData Snapshot after the write.
	 * @param array $context Additional context for the event.
	 **/
	public function logUpdate(string $module, string $entityName, $entityId, $beforeData, $afterData, array $context = []): void
		{
		$this->logEvent($module, 'update', $entityName, $entityId, $beforeData, $afterData, $context);
		}

	/**
	 * Persists a delete event in manager_main.logs.
	 * @param string $module Source module name.
	 * @param string $entityName Main entity/table name.
	 * @param mixed $entityId Main entity identifier.
	 * @param mixed $beforeData Snapshot before the delete.
	 * @param array $context Additional context for the event.
	 **/
	public function logDelete(string $module, string $entityName, $entityId, $beforeData, array $context = []): void
		{
		$this->logEvent($module, 'delete', $entityName, $entityId, $beforeData, null, $context);
		}

	/**
	 * Persists a successful login event in manager_main.logs.
	 * @param array $user Authenticated user payload.
	 * @param array|null $tenant Tenant selected during login, when available.
	 * @param array $context Additional context for the event.
	 **/
	public function logLogin(array $user, ?array $tenant = null, array $context = []): void
		{
		$context['source'] = 'authentication';
		$this->logEvent(
			'auth',
			'login',
			'users',
			$user['id'] ?? null,
			null,
			$this->authenticationUserSnapshot($user),
			$context,
			$user,
			$tenant
		);
		}

	/**
	 * Persists a logout event in manager_main.logs.
	 * @param array $user Authenticated user payload.
	 * @param array|null $tenant Tenant selected during logout, when available.
	 * @param array $context Additional context for the event.
	 **/
	public function logLogout(array $user, ?array $tenant = null, array $context = []): void
		{
		$context['source'] = 'authentication';
		$this->logEvent(
			'auth',
			'logout',
			'users',
			$user['id'] ?? null,
			$this->authenticationUserSnapshot($user),
			null,
			$context,
			$user,
			$tenant
		);
		}

	/**
	 * Persists a listing or filtered search event in manager_main.logs.
	 * @param string $module Source module name.
	 * @param string $entityName Main entity/table name.
	 * @param array $context Additional context for the listing event.
	 **/
	public function logList(string $module, string $entityName, array $context = []): void
		{
		$context['source'] = 'listing';
		$this->logEvent($module, 'list', $entityName, null, null, null, $context);
		}

	/**
	 * Writes a normalized audit row to manager_main.logs.
	 * @param string $module Source module name.
	 * @param string $action Event action.
	 * @param string $entityName Main entity/table name.
	 * @param mixed $entityId Main entity identifier.
	 * @param mixed $beforeData Snapshot before the change.
	 * @param mixed $afterData Snapshot after the change.
	 * @param array $context Additional context for the event.
	 **/
	private function logEvent(
		string $module,
		string $action,
		string $entityName,
		$entityId,
		$beforeData,
		$afterData,
		array $context = [],
		?array $actor = null,
		?array $tenantOverride = null
	): void
		{
		$tenant = $tenantOverride ?? TenantContext::tenant();
		$user = $actor ?? ($_SESSION['manager']['user'] ?? []);

		$now = date('Y-m-d H:i:s');
		$beforePayload = $this->normalizeValue($beforeData);
		$afterPayload = $this->normalizeValue($afterData);
		$changedFields = $this->buildChangedFields($beforePayload, $afterPayload);
		$contextPayload = $this->buildContextPayload($context);
		$logsTable = sprintf('`%s`.`logs`', Database::getManagerDatabaseName());
		$tenantDatabase = $tenantOverride !== null ? $this->databaseNameFromTenant($tenantOverride) : TenantContext::databaseName();

		$stmt = $this->db->prepare("
			INSERT INTO {$logsTable}
				(event_uuid, tenant_id, tenant_database, tenant_name_snapshot, module, action, entity_name, entity_id,
				 performed_by_user_id, performed_by_username, performed_by_name, request_method, route, ip_address,
				 user_agent, occurred_at, before_data, after_data, changed_fields, context_json, created, modified)
			VALUES
				(:event_uuid, :tenant_id, :tenant_database, :tenant_name_snapshot, :module, :action, :entity_name, :entity_id,
				 :performed_by_user_id, :performed_by_username, :performed_by_name, :request_method, :route, :ip_address,
				 :user_agent, :occurred_at, :before_data, :after_data, :changed_fields, :context_json, :created, :modified)
		");

		$stmt->execute([
			'event_uuid' => $this->generateUuidV4(),
			'tenant_id' => isset($tenant['id']) ? (int) $tenant['id'] : null,
			'tenant_database' => $tenantDatabase,
			'tenant_name_snapshot' => $this->truncate((string) ($tenant['name'] ?? ''), 160),
			'module' => trim($module),
			'action' => $action,
			'entity_name' => trim($entityName),
			'entity_id' => $entityId !== null ? (string) $entityId : null,
			'performed_by_user_id' => !empty($user['id']) ? (int) $user['id'] : null,
			'performed_by_username' => $this->truncate((string) ($user['username'] ?? ''), 120),
			'performed_by_name' => $this->truncate((string) ($user['name'] ?? ''), 160),
			'request_method' => $this->truncate((string) ($_SERVER['REQUEST_METHOD'] ?? ''), 10),
			'route' => $this->truncate($this->currentRoute(), 255),
			'ip_address' => $this->truncate((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 45),
			'user_agent' => $this->truncate((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 255),
			'occurred_at' => $now,
			'before_data' => $beforePayload === null ? null : $this->encodeJson($beforePayload),
			'after_data' => $afterPayload === null ? null : $this->encodeJson($afterPayload),
			'changed_fields' => $changedFields === null ? null : $this->encodeJson($changedFields),
			'context_json' => $contextPayload === [] ? null : $this->encodeJson($contextPayload),
			'created' => $now,
			'modified' => $now,
		]);
		}

	/**
	 * Builds the user payload used by authentication events.
	 * @param array $user Raw authenticated user data.
	 * @return array
	 **/
	private function authenticationUserSnapshot(array $user): array
		{
		return [
			'id' => isset($user['id']) ? (int) $user['id'] : null,
			'username' => $user['username'] ?? null,
			'email' => $user['email'] ?? null,
			'type' => $user['type'] ?? null,
			'name' => $user['name'] ?? null,
			'tenant_id' => isset($user['tenant_id']) ? (int) $user['tenant_id'] : null,
		];
		}

	/**
	 * Resolves the database name from an explicit tenant payload.
	 * @param array $tenant Tenant payload.
	 * @return string|null
	 **/
	private function databaseNameFromTenant(array $tenant): ?string
		{
		if (empty($tenant['database_number']))
			{
			return null;
			}

		return TenantContext::DATABASE_PREFIX . str_pad((string) $tenant['database_number'], 4, '0', STR_PAD_LEFT);
		}

	/**
	 * Adds request information to the provided context payload.
	 * @param array $context Additional context received from the caller.
	 * @return array
	 **/
	private function buildContextPayload(array $context): array
		{
		$payload = $this->normalizeValue($context);

		if (!is_array($payload))
			{
			return [];
			}

		$payload['request_query'] = $this->normalizeValue($_GET ?? []);
		return $payload;
		}

	/**
	 * Builds the diff payload stored in changed_fields.
	 * @param mixed $beforeData Snapshot before the change.
	 * @param mixed $afterData Snapshot after the change.
	 * @return mixed
	 **/
	private function buildChangedFields($beforeData, $afterData)
		{
		if ($beforeData === $afterData)
			{
			return null;
			}

		if (is_array($beforeData) && is_array($afterData))
			{
			if (array_is_list($beforeData) || array_is_list($afterData))
				{
				return [
					'from' => $beforeData,
					'to' => $afterData,
				];
				}

			$changes = [];
			$keys = array_unique(array_merge(array_keys($beforeData), array_keys($afterData)));

			foreach ($keys as $key)
				{
				$childChange = $this->buildChangedFields($beforeData[$key] ?? null, $afterData[$key] ?? null);

				if ($childChange !== null)
					{
					$changes[$key] = $childChange;
					}
				}

			return $changes === [] ? null : $changes;
			}

		return [
			'from' => $beforeData,
			'to' => $afterData,
		];
		}

	/**
	 * Normalizes values before persisting them as JSON.
	 * @param mixed $value Raw value.
	 * @return mixed
	 **/
	private function normalizeValue($value)
		{
		if ($value === null || is_scalar($value))
			{
			return $value;
			}

		if ($value instanceof \DateTimeInterface)
			{
			return $value->format('Y-m-d H:i:s');
			}

		if (is_object($value))
			{
			if ($value instanceof \JsonSerializable)
				{
				return $this->normalizeValue($value->jsonSerialize());
				}

			return $this->normalizeValue((array) $value);
			}

		if (!is_array($value))
			{
			return (string) $value;
			}

		$normalized = [];

		foreach ($value as $key => $item)
			{
			$normalized[$key] = $this->normalizeValue($item);
			}

		return $normalized;
		}

	/**
	 * Encodes data using the project JSON options.
	 * @param mixed $value Payload to encode.
	 * @return string
	 **/
	private function encodeJson($value): string
		{
		return (string) json_encode(
			$value,
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR
		);
		}

	/**
	 * Returns the current application route path.
	 * @return string
	 **/
	private function currentRoute(): string
		{
		$route = trim((string) ($_GET['url'] ?? ''));
		return ($route !== '') ? $route : '/';
		}

	/**
	 * Generates a UUID v4 string.
	 * @return string
	 **/
	private function generateUuidV4(): string
		{
		$bytes = random_bytes(16);
		$bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
		$bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

		return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
		}

	/**
	 * Truncates strings to the informed database field size.
	 * @param string $value Raw value.
	 * @param int $length Maximum length.
	 * @return string|null
	 **/
	private function truncate(string $value, int $length): ?string
		{
		$value = trim($value);

		if ($value === '')
			{
			return null;
			}

		if (function_exists('mb_substr'))
			{
			return mb_substr($value, 0, $length);
			}

		return substr($value, 0, $length);
		}
	}
?>
