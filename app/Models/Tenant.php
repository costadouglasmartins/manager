<?php

namespace App\Models;

use App\Core\Model;
use PDO;

class Tenant extends Model
{
	public function getAccessibleByUserId(int $userId): array
	{
		$stmt = $this->db->prepare("
			SELECT t.id,
			       t.code,
			       t.name,
			       t.database_name,
			       t.status,
			       tu.role,
			       tu.active AS user_active
			FROM tenants t
			INNER JOIN tenant_users tu ON tu.tenant_id = t.id
			WHERE tu.user_id = :user_id
			  AND tu.active = 1
			  AND t.status = 'active'
			ORDER BY t.name ASC
		");
		$stmt->execute(['user_id' => $userId]);

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	public function findByIdForUser(int $tenantId, int $userId): ?array
	{
		$stmt = $this->db->prepare("
			SELECT t.id,
			       t.code,
			       t.name,
			       t.database_name,
			       t.status,
			       tu.role,
			       tu.active AS user_active
			FROM tenants t
			INNER JOIN tenant_users tu ON tu.tenant_id = t.id
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

	public function findByCode(string $code): ?array
	{
		$stmt = $this->db->prepare("
			SELECT id, code, name, database_name, status, created, modified
			FROM tenants
			WHERE code = :code
			LIMIT 1
		");
		$stmt->execute(['code' => trim($code)]);

		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		return $row ?: null;
	}

	public function create(array $data): int
	{
		$now = date('Y-m-d H:i:s');

		$stmt = $this->db->prepare("
			INSERT INTO tenants (code, name, database_name, status, created, modified)
			VALUES (:code, :name, :database_name, :status, :created, :modified)
		");
		$stmt->execute([
			'code' => trim((string) ($data['code'] ?? '')),
			'name' => trim((string) ($data['name'] ?? '')),
			'database_name' => trim((string) ($data['database_name'] ?? '')),
			'status' => trim((string) ($data['status'] ?? 'active')),
			'created' => $now,
			'modified' => $now,
		]);

		return (int) $this->db->lastInsertId();
	}

	public function linkUser(int $tenantId, int $userId, string $role = 'user', bool $active = true): void
	{
		$now = date('Y-m-d H:i:s');

		$stmt = $this->db->prepare("
			INSERT INTO tenant_users (tenant_id, user_id, role, active, created, modified)
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
