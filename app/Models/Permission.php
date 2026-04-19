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
	public function getAll(): array
		{
		$permissionsTable = $this->managerTable('permissions');

		$stmt = $this->db->query("
			SELECT MIN(id) AS id,
			       ability,
			       MAX(description) AS description
			FROM {$permissionsTable}
			GROUP BY ability
			ORDER BY ability ASC
		");
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
			INSERT INTO {$permissionsTable} (ability, description, created, modified)
			VALUES (:ability, :description, :created, :modified)
		");
		$stmt->execute([
			'ability' => $ability,
			'description' => ($description === '' ? null : $description),
			'created' => date('Y-m-d H:i:s'),
			'modified' => date('Y-m-d H:i:s'),
		]);

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
		$permissionsTable = $this->managerTable('permissions');
		$stmt = $this->db->prepare("
			INSERT INTO {$permissionsTable} (ability, description, created, modified)
			VALUES (:ability, :description, :created, :modified)
		");

		foreach ($discovered as $ability => $description)
			{
			if (isset($existingAbilities[$ability]))
				{
				continue;
				}

			$stmt->execute([
				'ability' => $ability,
				'description' => $description,
				'created' => date('Y-m-d H:i:s'),
				'modified' => date('Y-m-d H:i:s'),
			]);

			if ($stmt->rowCount() > 0)
				{
				$added++;
				$existingAbilities[$ability] = true;
				}
			}

		return $added;
		}

	private function getExistingAbilitiesLookup(): array
		{
		$permissionsTable = $this->managerTable('permissions');
		$stmt = $this->db->query("SELECT DISTINCT ability FROM {$permissionsTable}");

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
				AND TenantUser.active = 1
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
			  AND active = 1
			LIMIT 1
		");
		$stmt->execute([
			'user_id' => $userId,
			'tenant_id' => $tenantId,
		]);

		return $stmt->fetchColumn() !== false;
		}

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
				GROUP BY ability
			) canonical ON canonical.ability = p.ability
			WHERE up.user_id = :user_id
		");
		$stmt->execute(['user_id' => $userId]);

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
	 */
	public function setPermissionsForUser(int $userId, array $permissionIds): void
		{
		if ($userId <= 0)
			{
			return;
			}

		$this->db->beginTransaction();

		try
			{
			$userPermissionsTable = $this->managerTable('user_permissions');
			$del = $this->db->prepare("DELETE FROM {$userPermissionsTable} WHERE user_id = :user_id");
			$del->execute(['user_id' => $userId]);

			$canonicalPermissionIds = $this->canonicalizePermissionIds($permissionIds);
			if ($canonicalPermissionIds !== [])
				{
				$ins = $this->db->prepare("
					INSERT INTO {$userPermissionsTable} (user_id, permission_id, created, modified)
					VALUES (:user_id, :permission_id, :created, :modified)
				");

				foreach ($canonicalPermissionIds as $permissionId)
					{
					$ins->execute([
						'user_id' => $userId,
						'permission_id' => $permissionId,
						'created' => date('Y-m-d H:i:s'),
						'modified' => date('Y-m-d H:i:s'),
					]);
					}
				}

			$this->db->commit();
			}
		catch (\Throwable $e)
			{
			$this->db->rollBack();
			throw $e;
			}
		}

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
			INNER JOIN {$permissionsTable} canonical ON canonical.ability = selected.ability
			WHERE selected.id IN ({$placeholders})
			GROUP BY selected.ability
		");
		$stmt->execute($permissionIds);

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

	private function findCanonicalPermissionIdByAbility(string $ability): ?int
		{
		$permissionsTable = $this->managerTable('permissions');
		$stmt = $this->db->prepare("
			SELECT MIN(id) AS id
			FROM {$permissionsTable}
			WHERE ability = :ability
			LIMIT 1
		");
		$stmt->execute(['ability' => $ability]);

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
	}

?>
