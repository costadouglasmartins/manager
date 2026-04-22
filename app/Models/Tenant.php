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

		$stmt = $this->db->prepare("
			SELECT t.id,
			       t.name,
			       t.fantasy,
			       t.document,
			       t.database_number,
			       t.status,
			       tu.role,
			       tu.active AS user_active
			FROM {$tenantsTable} t
			INNER JOIN {$tenantUsersTable} tu ON tu.tenant_id = t.id
			WHERE tu.user_id = :user_id
			  AND tu.active = 1
			  AND t.status = 'active'
			ORDER BY t.name ASC
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

		$stmt = $this->db->prepare("
			SELECT t.id,
			       t.name,
			       t.fantasy,
			       t.document,
			       t.database_number,
			       t.status,
			       tu.role,
			       tu.active AS user_active
			FROM {$tenantsTable} t
			INNER JOIN {$tenantUsersTable} tu ON tu.tenant_id = t.id
			WHERE t.id = :tenant_id
			  AND tu.user_id = :user_id
			  AND tu.active = 1
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
			SELECT id, name, fantasy, document, database_number, status, created, modified
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
			INSERT INTO {$tenantsTable} (name, fantasy, document, database_number, status, created, modified)
			VALUES (:name, :fantasy, :document, :database_number, :status, :created, :modified)
		");
		$stmt->execute([
			'name' => trim((string) ($data['name'] ?? '')),
			'fantasy' => trim((string) ($data['fantasy'] ?? '')),
			'document' => trim((string) ($data['document'] ?? '')),
			'database_number' => trim((string) ($data['database_number'] ?? '')),
			'status' => trim((string) ($data['status'] ?? 'pending')),
			'created' => $now,
			'modified' => $now,
		]);

		return (int) $this->db->lastInsertId();
	  }

	/**
	 * Links a user to a tenant and updates the relation when it already exists.
	 */
	public function linkUser(int $tenantId, int $userId, string $role = 'user', bool $active = true): void
	  {
		$now = date('Y-m-d H:i:s');
		$tenantUsersTable = $this->managerTable('tenant_users');

		$stmt = $this->db->prepare("
			INSERT INTO {$tenantUsersTable} (tenant_id, user_id, role, active, created, modified)
			VALUES (:tenant_id, :user_id, :role, :active, :created, :modified)
			ON DUPLICATE KEY UPDATE
				role = VALUES(role),
				active = VALUES(active),
				modified = VALUES(modified)
		");
		$stmt->execute([
			'tenant_id' => $tenantId,
			'user_id' => $userId,
			'role' => $role,
			'active' => $active ? 1 : 0,
			'created' => $now,
			'modified' => $now,
		]);
	  }
  }
?>