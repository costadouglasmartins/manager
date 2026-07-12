<?php

namespace App\Models;

use App\Core\Model;
use PDO;

/**
 * User-only permissions model (no groups/roles).
 *
 * Tables:
 * - permissions (catalog)
 * - user_permissions (pivot: user_id x permission_id)
 */
class Permission extends Model
	{
	private const CATALOG_SYSTEM = 'manager';

	/**
	 * Returns the permission catalog grouped by canonical ability.
	 */
	public function getAll(): array
		{
		$permissionsTable = $this->managerTable('permissions');

		$stmt = $this->db->prepare("
			SELECT MIN(id) AS id,
			       ability,
			       MIN(`system`) AS `system`,
			       MAX(description) AS description,
			       MIN(created) AS created,
			       MAX(modified) AS modified
			FROM {$permissionsTable}
			WHERE `system` = :system
			GROUP BY ability
			ORDER BY ability ASC
		");
		$stmt->execute(['system' => self::CATALOG_SYSTEM]);
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Creates a permission in the catalog.
	 * Returns true if inserted, false if it already existed or ability is invalid.
	 */
	public function createPermission(string $ability, string $description = ''): bool
		{
		$ability = trim($ability);
		$description = trim($description);

		if ($ability === '')
			{
			return false;
			}

		if ($this->findCanonicalPermissionIdByAbility($ability) !== null)
			{
			return false;
			}

		$permissionsTable = $this->managerTable('permissions');
		$stmt = $this->db->prepare("
			INSERT INTO {$permissionsTable} (`system`, ability, description, created, modified)
			VALUES (:system, :ability, :description, :created, :modified)
		");
		$stmt->execute([
			'system' => self::CATALOG_SYSTEM,
			'ability' => $ability,
			'description' => ($description === '' ? null : $description),
			'created' => date('Y-m-d H:i:s'),
			'modified' => date('Y-m-d H:i:s'),
		]);

		if ($stmt->rowCount() > 0)
			{
			$permissionId = (int) $this->db->lastInsertId();
			$this->auditCreate('permission', 'permissions', $permissionId, [
				'id' => $permissionId,
				'ability' => $ability,
				'description' => ($description === '' ? null : $description),
				'system' => self::CATALOG_SYSTEM,
			]);
			}

		return ($stmt->rowCount() > 0);
		}

	/**
	 * Sync permission catalog by discovering controller actions.
	 * Returns the count of new permissions inserted.
	 */
	public function syncFromControllers(string $controllersDir): int
		{
		$discovered = $this->discoverAbilitiesFromControllers($controllersDir);
		$existingAbilities = $this->getExistingAbilitiesLookup();

		$added = 0;
		$createdPermissions = [];
		$permissionsTable = $this->managerTable('permissions');
		$stmt = $this->db->prepare("
			INSERT INTO {$permissionsTable} (`system`, ability, description, created, modified)
			VALUES (:system, :ability, :description, :created, :modified)
		");

		foreach ($discovered as $ability => $description)
			{
			if (isset($existingAbilities[$ability]))
				{
				continue;
				}

			$stmt->execute([
				'system' => self::CATALOG_SYSTEM,
				'ability' => $ability,
				'description' => $description,
				'created' => date('Y-m-d H:i:s'),
				'modified' => date('Y-m-d H:i:s'),
			]);

			if ($stmt->rowCount() > 0)
				{
				$added++;
				$createdPermissions[] = [
					'ability' => $ability,
					'description' => $description,
					'system' => self::CATALOG_SYSTEM,
				];
				$existingAbilities[$ability] = true;
				}
			}

		if ($createdPermissions !== [])
			{
			$this->auditCreate('permission', 'permissions', null, [
				'created_permissions' => $createdPermissions,
			], [
				'source' => 'sync_from_controllers',
				'count' => count($createdPermissions),
			]);
			}

		return $added;
		}

	/**
	 * Builds a fast lookup table for existing abilities in the permission catalog.
	 */
	private function getExistingAbilitiesLookup(): array
		{
		$permissionsTable = $this->managerTable('permissions');
		$stmt = $this->db->prepare("
			SELECT DISTINCT ability
			FROM {$permissionsTable}
			WHERE `system` = :system
		");
		$stmt->execute(['system' => self::CATALOG_SYSTEM]);

		$lookup = [];
		while ($row = $stmt->fetch(PDO::FETCH_ASSOC))
			{
			$ability = trim((string) ($row['ability'] ?? ''));
			if ($ability !== '')
				{
				$lookup[$ability] = true;
				}
			}

		return $lookup;
		}

	/**
	 * Discovers abilities by parsing controllers and their public methods.
	 * Ability format: <module>.<action>
	 */
	private function discoverAbilitiesFromControllers(string $controllersDir): array
		{
		$controllersDir = rtrim($controllersDir, '/');
		$abilities = [];

		$files = glob($controllersDir . '/*Controller.php');
		if (!is_array($files))
			{
			return $abilities;
			}

		foreach ($files as $file)
			{
			$base = basename($file);
			if ($base === 'PermissionController.php')
				{
				continue;
				}

			$controllerName = preg_replace('/Controller\.php$/', '', $base);
			$module = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', (string) $controllerName));

			$code = @file_get_contents($file);
			if (!is_string($code) || $code === '')
				{
				continue;
				}

			if (!preg_match_all('/public\s+function\s+([a-zA-Z0-9_]+)\s*\(/', $code, $matches))
				{
				continue;
				}

			foreach ($matches[1] as $method)
				{
				$method = (string) $method;

				if ($method === '__construct' || strpos($method, '_') === 0)
					{
					continue;
					}

				$ability = $module . '.' . $method;
				$abilities[$ability] = 'Acesso: ' . $module . ' -> ' . $method;
				}
			}

		ksort($abilities);
		return $abilities;
		}

	/**
	 * Returns active users, optionally filtered to a specific tenant.
	 */
	public function getUsers(?int $tenantId = null): array
		{
		$usersTable = $this->managerTable('users');
		$peopleTable = $this->managerTable('people');
		$emailsTable = $this->managerTable('emails');
		$tenantUsersTable = $this->managerTable('tenant_users');
		$params = [];

		$sql = "
			SELECT User.id,
			       User.username,
			       User.type,
			       MIN(Email.email) AS email
			FROM {$usersTable} AS User
			INNER JOIN {$peopleTable} AS Person ON Person.id = User.person_id
			LEFT JOIN {$emailsTable} AS Email
				ON Email.person_id = Person.id
				AND Email.is_login = '1'
		";

		if ($tenantId !== null)
			{
			$sql .= "
			INNER JOIN {$tenantUsersTable} AS TenantUser
				ON TenantUser.user_id = User.id
				AND TenantUser.tenant_id = :tenant_id
			";
			$params['tenant_id'] = $tenantId;
			}

		$sql .= "
			WHERE User.active = 1
			GROUP BY User.id, User.username, User.type
			ORDER BY User.username ASC
		";

		$stmt = $this->db->prepare($sql);
		$stmt->execute($params);
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Checks whether a user belongs to the given tenant.
	 */
	public function userBelongsToTenant(int $userId, int $tenantId): bool
		{
		if ($userId <= 0 || $tenantId <= 0)
			{
			return false;
			}

		$tenantUsersTable = $this->managerTable('tenant_users');
		$stmt = $this->db->prepare("
			SELECT 1
			FROM {$tenantUsersTable}
			WHERE user_id = :user_id
			  AND tenant_id = :tenant_id
			LIMIT 1
		");
		$stmt->execute([
			'user_id' => $userId,
			'tenant_id' => $tenantId,
		]);

		return $stmt->fetchColumn() !== false;
		}

	/**
	 * Returns the canonical permission ids currently assigned to the given user.
	 */
	public function getPermissionIdsByUser(int $userId): array
		{
		$userPermissionsTable = $this->managerTable('user_permissions');
		$permissionsTable = $this->managerTable('permissions');

		$stmt = $this->db->prepare("
			SELECT DISTINCT canonical.id
			FROM {$userPermissionsTable} up
			INNER JOIN {$permissionsTable} p ON p.id = up.permission_id
			INNER JOIN (
				SELECT MIN(id) AS id, ability
				FROM {$permissionsTable}
				WHERE `system` = :canonical_system
				GROUP BY ability
			) canonical ON canonical.ability = p.ability
			WHERE up.user_id = :user_id
			  AND p.`system` = :assigned_system
		");
		$stmt->execute([
			'user_id' => $userId,
			'canonical_system' => self::CATALOG_SYSTEM,
			'assigned_system' => self::CATALOG_SYSTEM,
		]);

		$ids = [];
		while ($row = $stmt->fetch(PDO::FETCH_ASSOC))
			{
			$ids[] = (int) $row['id'];
			}

		sort($ids);
		return array_values(array_unique($ids));
		}

	/**
	 * Sets the permissions for a user (full replace).
	 *
	 * @param int|null $tenantId Tenant scope selected when the permission change was submitted.
	 */
	public function setPermissionsForUser(int $userId, array $permissionIds, ?int $tenantId = null): void
		{
		if ($userId <= 0)
			{
			return;
			}

		$this->db->beginTransaction();

		try
			{
			$beforeData = $this->buildUserPermissionSnapshot($userId, $tenantId);
			$beforePermissionIds = array_map('intval', $beforeData['permission_ids'] ?? []);
			$canonicalPermissionIds = $this->canonicalizePermissionIds($permissionIds);
			$permissionIdsToDelete = array_values(array_diff($beforePermissionIds, $canonicalPermissionIds));
			$permissionIdsToInsert = array_values(array_diff($canonicalPermissionIds, $beforePermissionIds));
			$userPermissionsTable = $this->managerTable('user_permissions');
			$permissionsTable = $this->managerTable('permissions');

			if ($permissionIdsToDelete !== [])
				{
				$deletePlaceholders = implode(', ', array_fill(0, count($permissionIdsToDelete), '?'));
				$del = $this->db->prepare("
					DELETE up
					FROM {$userPermissionsTable} up
					INNER JOIN {$permissionsTable} p ON p.id = up.permission_id
					WHERE up.user_id = ?
					  AND p.`system` = ?
					  AND up.permission_id IN ({$deletePlaceholders})
				");
				$del->execute(array_merge([$userId, self::CATALOG_SYSTEM], $permissionIdsToDelete));
				}

			if ($permissionIdsToInsert !== [])
				{
				$ins = $this->db->prepare("
					INSERT INTO {$userPermissionsTable} (user_id, permission_id, created, modified)
					VALUES (:user_id, :permission_id, :created, :modified)
				");
				$now = date('Y-m-d H:i:s');

				foreach ($permissionIdsToInsert as $permissionId)
					{
					$ins->execute([
						'user_id' => $userId,
						'permission_id' => $permissionId,
						'created' => $now,
						'modified' => $now,
					]);
					}
				}

			$afterData = $this->buildUserPermissionSnapshot($userId, $tenantId);
			$afterPermissionIds = array_map('intval', $afterData['permission_ids'] ?? []);
			$this->auditUpdate('permission', 'user_permissions', $userId, $beforeData, $afterData, [
				'source' => 'set_permissions_for_user',
				'target_user_id' => $userId,
				'tenant_id' => $tenantId,
				'submitted_permission_ids' => $this->normalizePermissionIdsForAudit($permissionIds),
				'submitted_canonical_permission_ids' => $canonicalPermissionIds,
				'before_permission_count' => count($beforePermissionIds),
				'after_permission_count' => count($afterPermissionIds),
				'no_changes_detected' => ($beforePermissionIds === $afterPermissionIds),
				'permission_changes' => $this->buildPermissionChangeSummary($beforePermissionIds, $afterPermissionIds),
			]);
			$this->db->commit();
			}
		catch (\Throwable $e)
			{
			$this->db->rollBack();
			throw $e;
			}
		}

	/**
	 * Converts submitted permission ids to their canonical catalog ids.
	 */
	private function canonicalizePermissionIds(array $permissionIds): array
		{
		if ($permissionIds === [])
			{
			return [];
			}

		$permissionIds = array_values(array_unique(array_map('intval', $permissionIds)));
		$permissionIds = array_values(array_filter($permissionIds, static function ($id)
			{
			return $id > 0;
			}));

		if ($permissionIds === [])
			{
			return [];
			}

		$permissionsTable = $this->managerTable('permissions');
		$placeholders = implode(', ', array_fill(0, count($permissionIds), '?'));
		$stmt = $this->db->prepare("
			SELECT MIN(canonical.id) AS id
			FROM {$permissionsTable} selected
			INNER JOIN {$permissionsTable} canonical
				ON canonical.ability = selected.ability
				AND canonical.`system` = ?
			WHERE selected.`system` = ?
			  AND selected.id IN ({$placeholders})
			GROUP BY selected.ability
		");
		$stmt->execute(array_merge([
			self::CATALOG_SYSTEM,
			self::CATALOG_SYSTEM,
		], $permissionIds));

		$canonicalIds = [];
		while ($row = $stmt->fetch(PDO::FETCH_ASSOC))
			{
			$id = (int) ($row['id'] ?? 0);
			if ($id > 0)
				{
				$canonicalIds[] = $id;
				}
			}

		sort($canonicalIds);
		return array_values(array_unique($canonicalIds));
		}

	/**
	 * Returns the canonical permission id for the given ability, if it exists.
	 */
	private function findCanonicalPermissionIdByAbility(string $ability): ?int
		{
		$permissionsTable = $this->managerTable('permissions');
		$stmt = $this->db->prepare("
			SELECT MIN(id) AS id
			FROM {$permissionsTable}
			WHERE ability = :ability
			  AND `system` = :system
			LIMIT 1
		");
		$stmt->execute([
			'ability' => $ability,
			'system' => self::CATALOG_SYSTEM,
		]);

		$id = $stmt->fetchColumn();
		return $id !== false ? (int) $id : null;
		}

	/**
	 * Backwards-compatible alias (older controller name).
	 */
	public function setExtraPermissionsForUser(int $userId, array $permissionIds): void
		{
		$this->setPermissionsForUser($userId, $permissionIds);
		}

	/**
	 * Returns the user's abilities (strings) based only on user_permissions.
	 */
	public function getAbilitiesForUser(int $userId): array
		{
		$userPermissionsTable = $this->managerTable('user_permissions');
		$permissionsTable = $this->managerTable('permissions');

		$stmt = $this->db->prepare("
			SELECT DISTINCT p.ability
			FROM {$userPermissionsTable} up
			INNER JOIN {$permissionsTable} p ON p.id = up.permission_id
			WHERE up.user_id = :user_id
			ORDER BY p.ability ASC
		");
		$stmt->execute(['user_id' => $userId]);

		$abilities = [];
		while ($row = $stmt->fetch(PDO::FETCH_ASSOC))
			{
			$abilities[] = (string) $row['ability'];
			}

		return $abilities;
		}

	/**
	 * Backwards-compatible alias used by Authorization in earlier versions.
	 */
	public function getEffectiveAbilitiesForUser(int $userId): array
		{
		return $this->getAbilitiesForUser($userId);
		}

	/**
	 * Returns permissions that no longer exist in the manager controllers.
	 */
	public function getOrphanedPermissions(): array
		{
		$discovered = $this->discoverAbilitiesFromControllers(ROOT_PATH . '/app/Controllers');
		$permissionsTable = $this->managerTable('permissions');
		$stmt = $this->db->prepare("
			SELECT id,
			       ability,
			       `system`,
			       description,
			       created,
			       modified
			FROM {$permissionsTable}
			WHERE `system` = :system
			ORDER BY ability ASC, id ASC
		");
		$stmt->execute(['system' => self::CATALOG_SYSTEM]);

		$orphaned = [];
		while ($row = $stmt->fetch(PDO::FETCH_ASSOC))
			{
			$ability = trim((string) ($row['ability'] ?? ''));
			if ($ability === '' || isset($discovered[$ability]))
				{
				continue;
				}

			$orphaned[] = $row;
			}

		return $orphaned;
		}

	/**
	 * Returns statistics for orphaned permissions.
	 */
	public function getOrphanedPermissionStats(): array
		{
		$orphaned = $this->getOrphanedPermissions();
		$stats = [
			'total_orphaned' => count($orphaned),
			'permissions' => [],
		];

		if ($orphaned === [])
			{
			return $stats;
			}

		$permissionIds = [];
		foreach ($orphaned as $perm)
			{
			$permissionIds[] = (int) $perm['id'];
			}

		$userCounts = $this->getUserCountByPermissionIds($permissionIds);
		foreach ($orphaned as $perm)
			{
			$permissionId = (int) $perm['id'];
			$stats['permissions'][$permissionId] = [
				'ability' => (string) ($perm['ability'] ?? ''),
				'description' => (string) ($perm['description'] ?? ''),
				'user_count' => (int) ($userCounts[$permissionId] ?? 0),
			];
			}

		return $stats;
		}

	/**
	 * Deletes an orphaned permission and its user assignments.
	 *
	 * @return array{success: bool, users_affected: int}
	 */
	public function deleteOrphanedPermission(int $permissionId): array
		{
		if ($permissionId <= 0)
			{
			return ['success' => false, 'users_affected' => 0];
			}

		$this->db->beginTransaction();

		try
			{
			$permissionSnapshot = $this->findPermissionSnapshot($permissionId);
			$isOrphaned = false;
			foreach ($this->getOrphanedPermissions() as $perm)
				{
				if ((int) ($perm['id'] ?? 0) === $permissionId)
					{
					$isOrphaned = true;
					break;
					}
				}

			if (!$isOrphaned)
				{
				$this->db->rollBack();
				return ['success' => false, 'users_affected' => 0];
				}

			$userPermissionsTable = $this->managerTable('user_permissions');
			$permissionsTable = $this->managerTable('permissions');

			$countStmt = $this->db->prepare("
				SELECT COUNT(*) AS total
				FROM {$userPermissionsTable}
				WHERE permission_id = :permission_id
			");
			$countStmt->execute(['permission_id' => $permissionId]);
			$usersAffected = (int) $countStmt->fetchColumn();

			$deleteAssignmentsStmt = $this->db->prepare("
				DELETE FROM {$userPermissionsTable}
				WHERE permission_id = :permission_id
			");
			$deleteAssignmentsStmt->execute(['permission_id' => $permissionId]);

			$deletePermissionStmt = $this->db->prepare("
				DELETE FROM {$permissionsTable}
				WHERE id = :id
				  AND `system` = :system
			");
			$deletePermissionStmt->execute([
				'id' => $permissionId,
				'system' => self::CATALOG_SYSTEM,
			]);

			if ($deletePermissionStmt->rowCount() !== 1)
				{
				$this->db->rollBack();
				return ['success' => false, 'users_affected' => 0];
				}

			$this->auditDelete('permission', 'permissions', $permissionId, $permissionSnapshot, [
				'source' => 'delete_orphaned_permission',
				'users_affected' => $usersAffected,
			]);
			$this->db->commit();

			return [
				'success' => true,
				'users_affected' => $usersAffected,
			];
			}
		catch (\Throwable $e)
			{
			$this->db->rollBack();
			throw $e;
			}
		}

	/**
	 * Builds the current permission assignment snapshot for a user.
	 *
	 * @param int $userId User identifier.
	 * @param int|null $tenantId Optional tenant scope.
	 * @return array<string, mixed>
	 */
	private function buildUserPermissionSnapshot(int $userId, ?int $tenantId = null): array
		{
		return [
			'user_id' => $userId,
			'tenant_id' => $tenantId,
			'target_user' => $this->findUserSnapshotForPermissionsAudit($userId),
			'tenant_links' => $this->findTenantUserLinksForPermissionsAudit($userId, $tenantId),
			'permission_ids' => $this->getPermissionIdsByUser($userId),
			'abilities' => $this->getAbilitiesForUser($userId),
			'user_permissions' => $this->findUserPermissionRowsForAudit($userId),
		];
		}

	/**
	 * Returns the user identity snapshot used by permission assignment audit logs.
	 *
	 * @param int $userId User identifier.
	 * @return array<string, mixed>|null
	 */
	private function findUserSnapshotForPermissionsAudit(int $userId): ?array
		{
		$usersTable = $this->managerTable('users');
		$peopleTable = $this->managerTable('people');
		$emailsTable = $this->managerTable('emails');

		$stmt = $this->db->prepare("
			SELECT User.id,
			       User.person_id,
			       User.tenant_id,
			       User.username,
			       User.type,
			       User.active,
			       User.password AS password_hash,
			       User.created,
			       User.modified,
			       Person.name AS person_name,
			       Person.fantasy AS person_fantasy,
			       Person.type AS person_type,
			       Person.document AS person_document,
			       Person.active AS person_active,
			       Email.id AS login_email_id,
			       Email.email AS login_email
			FROM {$usersTable} AS User
			LEFT JOIN {$peopleTable} AS Person ON Person.id = User.person_id
			LEFT JOIN {$emailsTable} AS Email
				ON Email.person_id = Person.id
				AND Email.is_login = '1'
			WHERE User.id = :user_id
			LIMIT 1
		");
		$stmt->execute(['user_id' => $userId]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);

		return $row ?: null;
		}

	/**
	 * Returns tenant-user rows relevant to the permission assignment audit log.
	 *
	 * @param int $userId User identifier.
	 * @param int|null $tenantId Optional tenant scope.
	 * @return array<int, array<string, mixed>>
	 */
	private function findTenantUserLinksForPermissionsAudit(int $userId, ?int $tenantId = null): array
		{
		$tenantUsersTable = $this->managerTable('tenant_users');
		$tenantsTable = $this->managerTable('tenants');
		$params = ['user_id' => $userId];

		$sql = "
			SELECT TenantUser.tenant_id,
			       Tenant.database_number,
			       Tenant.status AS tenant_status,
			       TenantUser.user_id,
			       TenantUser.role,
			       TenantUser.created,
			       TenantUser.modified
			FROM {$tenantUsersTable} AS TenantUser
			LEFT JOIN {$tenantsTable} AS Tenant ON Tenant.id = TenantUser.tenant_id
			WHERE TenantUser.user_id = :user_id
		";

		if ($tenantId !== null)
			{
			$sql .= " AND TenantUser.tenant_id = :tenant_id";
			$params['tenant_id'] = $tenantId;
			}

		$sql .= " ORDER BY TenantUser.tenant_id ASC";

		$stmt = $this->db->prepare($sql);
		$stmt->execute($params);
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Returns complete user-permission rows with the permission catalog data.
	 *
	 * @param int $userId User identifier.
	 * @return array<string, array<string, mixed>>
	 */
	private function findUserPermissionRowsForAudit(int $userId): array
		{
		$userPermissionsTable = $this->managerTable('user_permissions');
		$permissionsTable = $this->managerTable('permissions');

		$stmt = $this->db->prepare("
			SELECT up.user_id,
			       up.permission_id AS assignment_permission_id,
			       up.created AS assignment_created,
			       up.modified AS assignment_modified,
			       p.id AS permission_id,
			       p.ability,
			       p.`system`,
			       p.description,
			       p.created AS permission_created,
			       p.modified AS permission_modified
			FROM {$userPermissionsTable} AS up
			INNER JOIN {$permissionsTable} AS p ON p.id = up.permission_id
			WHERE up.user_id = :user_id
			  AND p.`system` = :system
			ORDER BY p.ability ASC, p.id ASC
		");
		$stmt->execute([
			'user_id' => $userId,
			'system' => self::CATALOG_SYSTEM,
		]);

		$rows = [];
		while ($row = $stmt->fetch(PDO::FETCH_ASSOC))
			{
			$permissionId = (int) ($row['permission_id'] ?? 0);
			if ($permissionId <= 0)
				{
				continue;
				}

			$rows[(string) $permissionId] = [
				'user_permission' => [
					'user_id' => (int) ($row['user_id'] ?? 0),
					'permission_id' => (int) ($row['assignment_permission_id'] ?? 0),
					'created' => $row['assignment_created'] ?? null,
					'modified' => $row['assignment_modified'] ?? null,
				],
				'permission' => [
					'id' => $permissionId,
					'ability' => $row['ability'] ?? null,
					'system' => $row['system'] ?? null,
					'description' => $row['description'] ?? null,
					'created' => $row['permission_created'] ?? null,
					'modified' => $row['permission_modified'] ?? null,
				],
			];
			}

		return $rows;
		}

	/**
	 * Builds an explicit permission delta for the audit context.
	 *
	 * @param array<int> $beforePermissionIds Permission ids before the change.
	 * @param array<int> $afterPermissionIds Permission ids after the change.
	 * @return array<string, mixed>
	 */
	private function buildPermissionChangeSummary(array $beforePermissionIds, array $afterPermissionIds): array
		{
		$beforePermissionIds = $this->normalizePermissionIdsForAudit($beforePermissionIds);
		$afterPermissionIds = $this->normalizePermissionIdsForAudit($afterPermissionIds);
		$addedIds = array_values(array_diff($afterPermissionIds, $beforePermissionIds));
		$removedIds = array_values(array_diff($beforePermissionIds, $afterPermissionIds));

		sort($addedIds);
		sort($removedIds);

		return [
			'added_count' => count($addedIds),
			'removed_count' => count($removedIds),
			'unchanged_count' => count(array_intersect($beforePermissionIds, $afterPermissionIds)),
			'added' => $this->findPermissionsForChangeAudit($addedIds),
			'removed' => $this->findPermissionsForChangeAudit($removedIds),
		];
		}

	/**
	 * Normalizes permission id lists used by audit helpers.
	 *
	 * @param array<int|string> $permissionIds Permission ids.
	 * @return array<int>
	 */
	private function normalizePermissionIdsForAudit(array $permissionIds): array
		{
		$permissionIds = array_values(array_unique(array_map('intval', $permissionIds)));
		$permissionIds = array_values(array_filter($permissionIds, static function ($permissionId)
			{
			return $permissionId > 0;
			}));
		sort($permissionIds);

		return $permissionIds;
		}

	/**
	 * Returns permission catalog rows for a permission delta.
	 *
	 * @param array<int> $permissionIds Permission ids.
	 * @return array<int, array<string, mixed>>
	 */
	private function findPermissionsForChangeAudit(array $permissionIds): array
		{
		$permissionIds = $this->normalizePermissionIdsForAudit($permissionIds);
		if ($permissionIds === [])
			{
			return [];
			}

		$permissionsTable = $this->managerTable('permissions');
		$placeholders = implode(', ', array_fill(0, count($permissionIds), '?'));
		$stmt = $this->db->prepare("
			SELECT id,
			       ability,
			       `system`,
			       description,
			       created,
			       modified
			FROM {$permissionsTable}
			WHERE id IN ({$placeholders})
			  AND `system` = ?
			ORDER BY ability ASC, id ASC
		");
		$stmt->execute(array_merge($permissionIds, [self::CATALOG_SYSTEM]));

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Returns the permission snapshot used by audit logs.
	 *
	 * @param int $permissionId Permission identifier.
	 * @return array<string, mixed>|null
	 */
	private function findPermissionSnapshot(int $permissionId): ?array
		{
		$permissionsTable = $this->managerTable('permissions');
		$stmt = $this->db->prepare("
			SELECT id,
			       ability,
			       description,
			       `system`,
			       created,
			       modified
			FROM {$permissionsTable}
			WHERE id = :id
			LIMIT 1
		");
		$stmt->execute(['id' => $permissionId]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);

		return $row ?: null;
		}

	/**
	 * Returns a count of affected users for the given permission ids.
	 *
	 * @param array<int> $permissionIds
	 * @return array<int, int>
	 */
	private function getUserCountByPermissionIds(array $permissionIds): array
		{
		$permissionIds = array_values(array_unique(array_map('intval', $permissionIds)));
		$permissionIds = array_values(array_filter($permissionIds, static function ($id)
			{
			return $id > 0;
			}));

		if ($permissionIds === [])
			{
			return [];
			}

		$userPermissionsTable = $this->managerTable('user_permissions');
		$placeholders = implode(', ', array_fill(0, count($permissionIds), '?'));
		$stmt = $this->db->prepare("
			SELECT permission_id, COUNT(*) AS total
			FROM {$userPermissionsTable}
			WHERE permission_id IN ({$placeholders})
			GROUP BY permission_id
		");
		$stmt->execute($permissionIds);

		$counts = [];
		while ($row = $stmt->fetch(PDO::FETCH_ASSOC))
			{
			$counts[(int) ($row['permission_id'] ?? 0)] = (int) ($row['total'] ?? 0);
			}

		return $counts;
		}
	}
?>
