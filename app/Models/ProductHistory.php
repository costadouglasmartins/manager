<?php

namespace App\Models;

use App\Core\Database;
use App\Core\TenantContext;
use PDO;

class ProductHistory
	{
	public function recentForProduct(int $productId, int $limit = 20): array
		{
		if ($productId <= 0) return [];
		$limit = max(1, min(100, $limit));
		$db = Database::getManagerConnection();
		$stmt = $db->prepare("SELECT id, action, entity_name, entity_id, performed_by_user_id,
			performed_by_username, performed_by_name, route, occurred_at, changed_fields,
			context_json
			FROM logs
			WHERE tenant_database = :tenant_database
			  AND module = 'product'
			  AND entity_name = 'product_items'
			  AND entity_id = :entity_id
			ORDER BY occurred_at DESC, id DESC
			LIMIT {$limit}");
		$stmt->execute([
			'tenant_database' => TenantContext::databaseName(),
			'entity_id' => (string) $productId,
		]);
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}
	}
?>
