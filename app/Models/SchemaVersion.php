<?php

namespace App\Models;

use App\Core\TenantModel;

class SchemaVersion extends TenantModel
{

	public function getAll(): array
	{
		$stmt = $this->db->query('SELECT * FROM schema_versions ORDER BY id DESC');
		return $stmt->fetchAll();
	}

	public function count(): int
	{
		$stmt = $this->db->query('SELECT COUNT(*) AS total FROM schema_versions');
		$row = $stmt->fetch();
		return (int) ($row['total'] ?? 0);
	}

	public function getLatest(int $limit = 5): array
	{
		$stmt = $this->db->prepare('SELECT * FROM schema_versions ORDER BY id DESC LIMIT :limit');
		$stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
		$stmt->execute();
		return $stmt->fetchAll();
	}
}

?>
