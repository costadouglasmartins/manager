<?php

namespace App\Models;

use App\Core\Model;
use PDO;

class SupportChat extends Model
	{
	/**
	 * Returns the latest active chat for the current customer and tenant.
	 */
	public function findActiveChatForCustomer(int $customerUserId, int $tenantId): ?array
		{
		$chatsTable = $this->managerTable('support_chats');
		$usersTable = $this->managerTable('users');
		$peopleTable = $this->managerTable('people');
		$tenantsTable = $this->managerTable('tenants');
		$userPresencesTable = $this->managerTable('user_presences');
		$unreadCountSql = $this->unreadCountSql();

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
		               {$unreadCountSql} AS unread_count,
		               CustomerPerson.name AS customer_name,
		               CustomerUser.username AS customer_username,
		               Tenant.observations AS tenant_name,
		               Tenant.database_number,
		               AttendantPerson.name AS attendant_name,
		               AttendantUser.username AS attendant_username,
		               COALESCE(AttendantPresence.status, 'offline') AS attendant_presence_status
		        FROM {$chatsTable} AS Chat
		        INNER JOIN {$usersTable} AS CustomerUser ON CustomerUser.id = Chat.customer_user_id
		        INNER JOIN {$peopleTable} AS CustomerPerson ON CustomerPerson.id = CustomerUser.person_id
		        LEFT JOIN {$tenantsTable} AS Tenant ON Tenant.id = Chat.tenant_id
		        LEFT JOIN {$usersTable} AS AttendantUser ON AttendantUser.id = Chat.attendant_user_id
		        LEFT JOIN {$peopleTable} AS AttendantPerson ON AttendantPerson.id = AttendantUser.person_id
		        LEFT JOIN {$userPresencesTable} AS AttendantPresence ON AttendantPresence.user_id = Chat.attendant_user_id
		        WHERE Chat.customer_user_id = :customer_user_id
		          AND Chat.tenant_id = :tenant_id
		          AND Chat.status IN ('pending', 'open')
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
	 * Lists root users who can take support chats right now. Uses the same rule
	 * as the support server when it assigns chats: active root, presence online.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function listOnlineAttendants(int $excludeUserId): array
		{
		$usersTable = $this->managerTable('users');
		$peopleTable = $this->managerTable('people');
		$userPresencesTable = $this->managerTable('user_presences');

		$stmt = $this->db->prepare("
			SELECT User.id,
			       User.username,
			       Person.name,
			       UserPresence.status AS presence_status
			FROM {$usersTable} AS User
			INNER JOIN {$peopleTable} AS Person ON Person.id = User.person_id
			INNER JOIN {$userPresencesTable} AS UserPresence ON UserPresence.user_id = User.id
			WHERE User.type = 'root'
			  AND User.active = 1
			  AND UserPresence.status = 'online'
			  AND User.id <> :exclude_user_id
			ORDER BY Person.name ASC, User.username ASC
		");
		$stmt->execute(['exclude_user_id' => $excludeUserId]);

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Returns the latest active chat for backward-compatible callers.
	 */
	public function findLatestChatForCustomer(int $customerUserId, int $tenantId): ?array
		{
		return $this->findActiveChatForCustomer($customerUserId, $tenantId);
		}

	/**
	 * Closed support chats stay listed in the widget for one month; older ones
	 * remain in the database but leave the list and the unread count.
	 */
	protected function listWindowStart(): string
		{
		return date('Y-m-d H:i:s', strtotime('-1 month'));
		}

	/**
	 * Returns the closed chats of the last month for the current customer and tenant.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function listClosedChatsForCustomer(int $customerUserId, int $tenantId, int $limit = 100): array
		{
		$chatsTable = $this->managerTable('support_chats');
		$usersTable = $this->managerTable('users');
		$peopleTable = $this->managerTable('people');
		$tenantsTable = $this->managerTable('tenants');
		$unreadCountSql = $this->unreadCountSql();

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
		               {$unreadCountSql} AS unread_count,
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
		          AND Chat.status = 'closed'
		          AND COALESCE(Chat.closed_at, Chat.last_message_at, Chat.created) >= :listed_since
		        ORDER BY CASE WHEN unread_count > 0 THEN 0 ELSE 1 END ASC,
		                 COALESCE(Chat.closed_at, Chat.last_message_at, Chat.created) DESC,
		                 Chat.id DESC
		        LIMIT :limit";

		$stmt = $this->db->prepare($sql);
		$stmt->bindValue(':customer_user_id', $customerUserId, PDO::PARAM_INT);
		$stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
		$stmt->bindValue(':listed_since', $this->listWindowStart());
		$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
		$stmt->execute();

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
		$unreadCountSql = $this->unreadCountSql();

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
		               {$unreadCountSql} AS unread_count,
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
		$messagesTable = $this->managerTable('support_chats_messages');
		$stmt = $this->db->prepare("
			SELECT RecentMessage.id,
			       RecentMessage.support_chat_id,
			       RecentMessage.sender_user_id,
			       RecentMessage.sender_role,
			       RecentMessage.sender_name,
			       RecentMessage.message_text,
			       RecentMessage.created
			FROM (
				SELECT id,
				       support_chat_id,
				       sender_user_id,
				       sender_role,
				       sender_name,
				       message_text,
				       created
				FROM {$messagesTable}
				WHERE support_chat_id = :chat_id
				ORDER BY id DESC
				LIMIT :limit
			) AS RecentMessage
			ORDER BY RecentMessage.id ASC
		");
		$stmt->bindValue(':chat_id', $chatId, PDO::PARAM_INT);
		$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
		$stmt->execute();

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Counts unread attendant messages in active chats and in the closed chats
	 * still listed (last month), so every counted message can be opened.
	 */
	public function getUnreadCount(int $customerUserId, int $tenantId): int
		{
		$chatsTable = $this->managerTable('support_chats');
		$messagesTable = $this->managerTable('support_chats_messages');
		$stmt = $this->db->prepare("
			SELECT COUNT(*)
			FROM {$messagesTable} AS Message
			INNER JOIN {$chatsTable} AS Chat ON Chat.id = Message.support_chat_id
			WHERE Chat.customer_user_id = :customer_user_id
			  AND Chat.tenant_id = :tenant_id
			  AND (
			    Chat.status <> 'closed'
			    OR COALESCE(Chat.closed_at, Chat.last_message_at, Chat.created) >= :listed_since
			  )
			  AND Message.sender_role = 'attendant'
			  AND (Chat.customer_last_seen_at IS NULL OR Message.created > Chat.customer_last_seen_at)
		");
		$stmt->execute([
			'customer_user_id' => $customerUserId,
			'tenant_id' => $tenantId,
			'listed_since' => $this->listWindowStart(),
		]);

		return (int) $stmt->fetchColumn();
		}

	/**
	 * Advances the customer cursor only through a displayed, owned message.
	 */
	public function markSeen(int $chatId, int $customerUserId, int $tenantId, int $lastMessageId): bool
		{
		$chatsTable = $this->managerTable('support_chats');
		$messagesTable = $this->managerTable('support_chats_messages');
		$seen = $this->db->prepare("
			SELECT Message.created
			FROM {$messagesTable} AS Message
			INNER JOIN {$chatsTable} AS Chat ON Chat.id = Message.support_chat_id
			WHERE Chat.id = :chat_id
			  AND Chat.customer_user_id = :customer_user_id
			  AND Chat.tenant_id = :tenant_id
			  AND Message.id = :last_message_id
			LIMIT 1
		");
		$seen->execute([
			'chat_id' => $chatId,
			'customer_user_id' => $customerUserId,
			'tenant_id' => $tenantId,
			'last_message_id' => $lastMessageId,
		]);
		$seenAt = $seen->fetchColumn();
		if ($seenAt === false)
			{
			return false;
			}

		$stmt = $this->db->prepare("
			UPDATE {$chatsTable}
			SET customer_last_seen_at = CASE
					WHEN customer_last_seen_at IS NULL OR customer_last_seen_at < :seen_at THEN :seen_value
					ELSE customer_last_seen_at
				END,
			    modified = NOW()
			WHERE id = :chat_id
			  AND customer_user_id = :customer_user_id
			  AND tenant_id = :tenant_id
		");
		$stmt->execute([
			'chat_id' => $chatId,
			'customer_user_id' => $customerUserId,
			'tenant_id' => $tenantId,
			'seen_at' => $seenAt,
			'seen_value' => $seenAt,
		]);

		return true;
		}

	/**
	 * Returns the unread count expression for a selected customer chat.
	 */
	private function unreadCountSql(): string
		{
		$messagesTable = $this->managerTable('support_chats_messages');
		return "(
			SELECT COUNT(*)
			FROM {$messagesTable} AS UnreadMessage
			WHERE UnreadMessage.support_chat_id = Chat.id
			  AND UnreadMessage.sender_role = 'attendant'
			  AND (Chat.customer_last_seen_at IS NULL OR UnreadMessage.created > Chat.customer_last_seen_at)
		)";
		}
	}

?>
