<?php

namespace App\Models;

use App\Core\Model;
use PDO;

class Tenant extends Model
  {
	/**
	 * Returns the active tenants accessible to the given user.
	 */
	public function getAccessibleByUserId(int $userId): array
	  {
		$tenantsTable = $this->managerTable('tenants');
		$tenantUsersTable = $this->managerTable('tenant_users');
		$usersTable = $this->managerTable('users');

		$stmt = $this->db->prepare("
			SELECT DISTINCT
			       t.id,
			       CONCAT('Tenant ', t.database_number) AS name,
			       COALESCE(t.observations, '') AS fantasy,
			       '' AS document,
			       t.database_number,
			       t.status,
			       COALESCE(
			           tu.role,
			           CASE
			               WHEN u.type = 'admin' THEN 'admin'
			               WHEN u.type = 'root' THEN 'owner'
			               ELSE 'user'
			           END
			       ) AS role
			FROM {$tenantsTable} t
			LEFT JOIN {$tenantUsersTable} tu
				ON tu.tenant_id = t.id
				AND tu.user_id = :user_id
			LEFT JOIN {$usersTable} u
				ON u.id = :user_id
				AND u.tenant_id = t.id
			WHERE (tu.user_id IS NOT NULL OR u.id IS NOT NULL)
			  AND t.status = 'active'
			ORDER BY t.database_number ASC
		");
		$stmt->execute(['user_id' => $userId]);

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	  }

	/**
	 * Returns a single active tenant accessible to the given user.
	 */
	public function findByIdForUser(int $tenantId, int $userId): ?array
	  {
		$tenantsTable = $this->managerTable('tenants');
		$tenantUsersTable = $this->managerTable('tenant_users');
		$usersTable = $this->managerTable('users');

		$stmt = $this->db->prepare("
			SELECT t.id,
			       CONCAT('Tenant ', t.database_number) AS name,
			       COALESCE(t.observations, '') AS fantasy,
			       '' AS document,
			       t.database_number,
			       t.status,
			       COALESCE(
			           tu.role,
			           CASE
			               WHEN u.type = 'admin' THEN 'admin'
			               WHEN u.type = 'root' THEN 'owner'
			               ELSE 'user'
			           END
			       ) AS role
			FROM {$tenantsTable} t
			LEFT JOIN {$tenantUsersTable} tu
				ON tu.tenant_id = t.id
				AND tu.user_id = :user_id
			LEFT JOIN {$usersTable} u
				ON u.id = :user_id
				AND u.tenant_id = t.id
			WHERE t.id = :tenant_id
			  AND (tu.user_id IS NOT NULL OR u.id IS NOT NULL)
			  AND t.status = 'active'
			LIMIT 1
		");
		$stmt->execute([
			'tenant_id' => $tenantId,
			'user_id' => $userId,
		]);

		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		return $row ?: null;
	  }

	/**
	 * Finds a tenant by its database number.
	 */
	public function findByDatabaseNumber(string $databaseNumber): ?array
	  {
		$tenantsTable = $this->managerTable('tenants');

		$stmt = $this->db->prepare("
			SELECT id,
			       CONCAT('Tenant ', database_number) AS name,
			       COALESCE(observations, '') AS fantasy,
			       '' AS document,
			       database_number,
			       status,
			       created,
			       modified
			FROM {$tenantsTable}
			WHERE database_number = :database_number
			LIMIT 1
		");
		$stmt->execute(['database_number' => trim($databaseNumber)]);

		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		return $row ?: null;
	  }

	/**
	 * Creates a tenant and returns its identifier.
	 */
	public function create(array $data): int
	  {
		$now = date('Y-m-d H:i:s');
		$tenantsTable = $this->managerTable('tenants');

		$stmt = $this->db->prepare("
			INSERT INTO {$tenantsTable} (database_number, status, observations, created, modified)
			VALUES (:database_number, :status, :observations, :created, :modified)
		");
		$stmt->execute([
			'database_number' => trim((string) ($data['database_number'] ?? '')),
			'status' => trim((string) ($data['status'] ?? 'pending')),
			'observations' => trim((string) ($data['observations'] ?? $data['fantasy'] ?? $data['name'] ?? '')),
			'created' => $now,
			'modified' => $now,
		]);

		return (int) $this->db->lastInsertId();
	  }

	/**
	 * Links a user to a tenant and updates the relation when it already exists.
	 */
	public function linkUser(int $tenantId, int $userId, string $role = 'user'): void
	  {
		$now = date('Y-m-d H:i:s');
		$tenantUsersTable = $this->managerTable('tenant_users');

		$stmt = $this->db->prepare("
			INSERT INTO {$tenantUsersTable} (tenant_id, user_id, role, created, modified)
			VALUES (:tenant_id, :user_id, :role, :created, :modified)
			ON DUPLICATE KEY UPDATE
				role = VALUES(role),
				modified = VALUES(modified)
		");
		$stmt->execute([
			'tenant_id' => $tenantId,
			'user_id' => $userId,
			'role' => $role,
			'created' => $now,
			'modified' => $now,
		]);
	  }
  }
?>
