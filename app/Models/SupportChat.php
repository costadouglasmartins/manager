<?php

namespace App\Models;

use App\Core\Model;
use PDO;

class SupportChat extends Model
	{
	/**
	 * Returns the latest chat for the current customer and tenant.
	 */
	public function findLatestChatForCustomer(int $customerUserId, int $tenantId): ?array
		{
		$chatsTable = $this->managerTable('support_chats');
		$usersTable = $this->managerTable('users');
		$peopleTable = $this->managerTable('people');
		$tenantsTable = $this->managerTable('tenants');

		$sql = "SELECT Chat.id,
		               Chat.status,
		               Chat.customer_user_id,
		               Chat.attendant_user_id,
		               Chat.tenant_id,
		               Chat.tenant_database_number,
		               Chat.created,
		               Chat.modified,
		               Chat.last_message_at,
		               Chat.closed_at,
		               CustomerPerson.name AS customer_name,
		               CustomerUser.username AS customer_username,
		               Tenant.observations AS tenant_name,
		               Tenant.database_number,
		               AttendantPerson.name AS attendant_name,
		               AttendantUser.username AS attendant_username
		        FROM {$chatsTable} AS Chat
		        INNER JOIN {$usersTable} AS CustomerUser ON CustomerUser.id = Chat.customer_user_id
		        INNER JOIN {$peopleTable} AS CustomerPerson ON CustomerPerson.id = CustomerUser.person_id
		        LEFT JOIN {$tenantsTable} AS Tenant ON Tenant.id = Chat.tenant_id
		        LEFT JOIN {$usersTable} AS AttendantUser ON AttendantUser.id = Chat.attendant_user_id
		        LEFT JOIN {$peopleTable} AS AttendantPerson ON AttendantPerson.id = AttendantUser.person_id
		        WHERE Chat.customer_user_id = :customer_user_id
		          AND Chat.tenant_id = :tenant_id
		        ORDER BY
		          CASE Chat.status
		            WHEN 'pending' THEN 0
		            WHEN 'open' THEN 1
		            ELSE 2
		          END ASC,
		          COALESCE(Chat.last_message_at, Chat.created) DESC,
		          Chat.id DESC
		        LIMIT 1";

		$stmt = $this->db->prepare($sql);
		$stmt->execute([
			'customer_user_id' => $customerUserId,
			'tenant_id' => $tenantId,
		]);

		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		return $row ?: null;
		}

	/**
	 * Returns one chat for the current customer and tenant.
	 */
	public function findChatForCustomer(int $chatId, int $customerUserId, int $tenantId): ?array
		{
		$chatsTable = $this->managerTable('support_chats');
		$usersTable = $this->managerTable('users');
		$peopleTable = $this->managerTable('people');
		$tenantsTable = $this->managerTable('tenants');

		$sql = "SELECT Chat.id,
		               Chat.status,
		               Chat.customer_user_id,
		               Chat.attendant_user_id,
		               Chat.tenant_id,
		               Chat.tenant_database_number,
		               Chat.created,
		               Chat.modified,
		               Chat.last_message_at,
		               Chat.closed_at,
		               CustomerPerson.name AS customer_name,
		               CustomerUser.username AS customer_username,
		               Tenant.observations AS tenant_name,
		               Tenant.database_number,
		               AttendantPerson.name AS attendant_name,
		               AttendantUser.username AS attendant_username
		        FROM {$chatsTable} AS Chat
		        INNER JOIN {$usersTable} AS CustomerUser ON CustomerUser.id = Chat.customer_user_id
		        INNER JOIN {$peopleTable} AS CustomerPerson ON CustomerPerson.id = CustomerUser.person_id
		        LEFT JOIN {$tenantsTable} AS Tenant ON Tenant.id = Chat.tenant_id
		        LEFT JOIN {$usersTable} AS AttendantUser ON AttendantUser.id = Chat.attendant_user_id
		        LEFT JOIN {$peopleTable} AS AttendantPerson ON AttendantPerson.id = AttendantUser.person_id
		        WHERE Chat.id = :chat_id
		          AND Chat.customer_user_id = :customer_user_id
		          AND Chat.tenant_id = :tenant_id
		        LIMIT 1";

		$stmt = $this->db->prepare($sql);
		$stmt->execute([
			'chat_id' => $chatId,
			'customer_user_id' => $customerUserId,
			'tenant_id' => $tenantId,
		]);

		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		return $row ?: null;
		}

	/**
	 * Returns messages for a customer-owned chat.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getMessages(int $chatId, int $limit = 200): array
		{
		$messagesTable = $this->managerTable('support_chat_messages');
		$stmt = $this->db->prepare("
			SELECT id,
			       support_chat_id,
			       sender_user_id,
			       sender_role,
			       sender_name,
			       message_text,
			       created
			FROM {$messagesTable}
			WHERE support_chat_id = :chat_id
			ORDER BY id ASC
			LIMIT :limit
		");
		$stmt->bindValue(':chat_id', $chatId, PDO::PARAM_INT);
		$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
		$stmt->execute();

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}
	}

?>
