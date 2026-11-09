<?php

namespace App\Models;

use App\Core\Model;
use App\Services\UserPresenceService;
use PDO;

class InternalChat extends Model
	{
	/**
	 * Returns active tenant users available for internal conversations.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function listTenantUsers(int $tenantId, int $currentUserId): array
		{
		$usersTable = $this->managerTable('users');
		$peopleTable = $this->managerTable('people');
		$tenantUsersTable = $this->managerTable('tenant_users');
		$userPresencesTable = $this->managerTable('user_presences');

		$stmt = $this->db->prepare("
			SELECT DISTINCT User.id,
			       User.username,
			       Person.name,
			       COALESCE(UserPresence.status, 'offline') AS presence_status,
			       COALESCE(
			         TenantUser.role,
			         CASE
			           WHEN User.type = 'root' THEN 'owner'
			           WHEN User.type = 'admin' THEN 'admin'
			           ELSE 'user'
			         END
			       ) AS role
			FROM {$usersTable} AS User
			INNER JOIN {$peopleTable} AS Person ON Person.id = User.person_id
			LEFT JOIN {$tenantUsersTable} AS TenantUser
				ON TenantUser.user_id = User.id
				AND TenantUser.tenant_id = :tenant_id
			LEFT JOIN {$userPresencesTable} AS UserPresence
				ON UserPresence.user_id = User.id
			WHERE User.active = 1
			  AND User.id <> :current_user_id
			  AND (TenantUser.user_id IS NOT NULL OR User.tenant_id = :tenant_id)
			ORDER BY Person.name ASC, User.username ASC
		");
		$stmt->execute([
			'tenant_id' => $tenantId,
			'current_user_id' => $currentUserId,
		]);

		$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
		foreach ($users as &$user)
			{
			$meta = UserPresenceService::statusMeta((string) ($user['presence_status'] ?? 'offline'));
			$user['presence_label'] = $meta['label'];
			$user['presence_class'] = $meta['class'];
			$user['presence_icon'] = $meta['icon'];
			}

		unset($user);
		return $users;
		}

	/**
	 * Indicates whether the user belongs to the tenant.
	 */
	public function userBelongsToTenant(int $userId, int $tenantId): bool
		{
		$usersTable = $this->managerTable('users');
		$tenantUsersTable = $this->managerTable('tenant_users');

		$stmt = $this->db->prepare("
			SELECT 1
			FROM {$usersTable} AS User
			LEFT JOIN {$tenantUsersTable} AS TenantUser
				ON TenantUser.user_id = User.id
				AND TenantUser.tenant_id = :tenant_id
			WHERE User.id = :user_id
			  AND User.active = 1
			  AND (TenantUser.user_id IS NOT NULL OR User.tenant_id = :tenant_id)
			LIMIT 1
		");
		$stmt->execute([
			'tenant_id' => $tenantId,
			'user_id' => $userId,
		]);

		return (bool) $stmt->fetchColumn();
		}

	/**
	 * Lists internal chats visible to the current participant.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function listChatsForUser(int $tenantId, int $userId, int $limit = 40): array
		{
		$chatsTable = $this->managerTable('internal_chats');
		$participantsTable = $this->managerTable('internal_chat_participants');
		$messagesTable = $this->managerTable('internal_chat_messages');
		$usersTable = $this->managerTable('users');
		$peopleTable = $this->managerTable('people');

		$stmt = $this->db->prepare("
			SELECT Chat.id,
			       Chat.tenant_id,
			       Chat.tenant_database_number,
			       Chat.chat_type,
			       Chat.title,
			       Chat.created_by_user_id,
			       Chat.status,
			       Chat.last_message_at,
			       Chat.created,
			       Chat.modified,
			       Participant.last_seen_at,
			       GROUP_CONCAT(
			         CASE WHEN OtherUser.id <> :user_id THEN OtherPerson.name ELSE NULL END
			         ORDER BY OtherPerson.name ASC SEPARATOR ', '
			       ) AS participant_names,
			       GROUP_CONCAT(
			         CASE WHEN OtherUser.id <> :user_id THEN OtherUser.username ELSE NULL END
			         ORDER BY OtherPerson.name ASC SEPARATOR ', '
			       ) AS participant_usernames,
			       (
			         SELECT LastMessage.message_text
			         FROM {$messagesTable} AS LastMessage
			         WHERE LastMessage.internal_chat_id = Chat.id
			         ORDER BY LastMessage.id DESC
			         LIMIT 1
			       ) AS last_message_text,
			       (
			         SELECT COUNT(*)
			         FROM {$messagesTable} AS UnreadMessage
			         WHERE UnreadMessage.internal_chat_id = Chat.id
			           AND UnreadMessage.sender_user_id <> :user_id
			           AND (
			             Participant.last_seen_at IS NULL
			             OR UnreadMessage.created > Participant.last_seen_at
			           )
			       ) AS unread_count
			FROM {$chatsTable} AS Chat
			INNER JOIN {$participantsTable} AS Participant
				ON Participant.internal_chat_id = Chat.id
				AND Participant.user_id = :user_id
			INNER JOIN {$participantsTable} AS OtherParticipant
				ON OtherParticipant.internal_chat_id = Chat.id
			INNER JOIN {$usersTable} AS OtherUser ON OtherUser.id = OtherParticipant.user_id
			INNER JOIN {$peopleTable} AS OtherPerson ON OtherPerson.id = OtherUser.person_id
			WHERE Chat.tenant_id = :tenant_id
			  AND Chat.status = 'active'
			  AND Participant.archived_at IS NULL
			  AND EXISTS (
			    SELECT 1
			    FROM {$messagesTable} AS ExistingMessage
			    WHERE ExistingMessage.internal_chat_id = Chat.id
			  )
			GROUP BY Chat.id,
			         Chat.tenant_id,
			         Chat.tenant_database_number,
			         Chat.chat_type,
			         Chat.title,
			         Chat.created_by_user_id,
			         Chat.status,
			         Chat.last_message_at,
			         Chat.created,
			         Chat.modified,
			         Participant.last_seen_at
			ORDER BY Chat.last_message_at DESC,
			         (
			           SELECT MAX(OrderMessage.id)
			           FROM {$messagesTable} AS OrderMessage
			           WHERE OrderMessage.internal_chat_id = Chat.id
			         ) DESC,
			         Chat.id DESC
			LIMIT :limit
		");
		$stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
		$stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
		$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
		$stmt->execute();

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Starts or returns a direct internal chat between two users.
	 */
	public function startDirectChat(int $tenantId, string $tenantDatabaseNumber, int $currentUserId, int $targetUserId): ?array
		{
		if ($currentUserId === $targetUserId)
			{
			return null;
			}

		if (!$this->userBelongsToTenant($currentUserId, $tenantId) || !$this->userBelongsToTenant($targetUserId, $tenantId))
			{
			return null;
			}

		$conversationKey = $this->directConversationKey($tenantId, $currentUserId, $targetUserId);
		$chat = $this->findChatByConversationKey($conversationKey, $tenantId, $currentUserId);

		if (!$chat)
			{
			$this->db->beginTransaction();

			try
				{
				$chatsTable = $this->managerTable('internal_chats');
				$stmt = $this->db->prepare("
					INSERT INTO {$chatsTable}
						(tenant_id, tenant_database_number, chat_type, conversation_key, created_by_user_id, status, created, modified)
					VALUES
						(:tenant_id, :tenant_database_number, 'direct', :conversation_key, :created_by_user_id, 'active', NOW(), NOW())
				");
				$stmt->execute([
					'tenant_id' => $tenantId,
					'tenant_database_number' => $tenantDatabaseNumber,
					'conversation_key' => $conversationKey,
					'created_by_user_id' => $currentUserId,
				]);

				$chatId = (int) $this->db->lastInsertId();
				$this->upsertParticipant($chatId, $currentUserId);
				$this->upsertParticipant($chatId, $targetUserId);
				$this->db->commit();
				}
			catch (\Throwable $e)
				{
				$this->db->rollBack();
				$chatId = (int) ($this->findChatByConversationKey($conversationKey, $tenantId, $currentUserId)['id'] ?? 0);
				}

			$chat = $this->findChatForParticipant($chatId, $tenantId, $currentUserId);
			}

		return $chat;
		}

	/**
	 * Returns a chat visible to the given participant.
	 */
	public function findChatForParticipant(int $chatId, int $tenantId, int $userId): ?array
		{
		$chatsTable = $this->managerTable('internal_chats');
		$participantsTable = $this->managerTable('internal_chat_participants');
		$messagesTable = $this->managerTable('internal_chat_messages');
		$usersTable = $this->managerTable('users');
		$peopleTable = $this->managerTable('people');

		$stmt = $this->db->prepare("
			SELECT Chat.id,
			       Chat.tenant_id,
			       Chat.tenant_database_number,
			       Chat.chat_type,
			       Chat.title,
			       Chat.created_by_user_id,
			       Chat.status,
			       Chat.last_message_at,
			       Chat.created,
			       Chat.modified,
			       Participant.last_seen_at,
			       GROUP_CONCAT(
			         CASE WHEN OtherUser.id <> :user_id THEN OtherPerson.name ELSE NULL END
			         ORDER BY OtherPerson.name ASC SEPARATOR ', '
			       ) AS participant_names,
			       GROUP_CONCAT(
			         CASE WHEN OtherUser.id <> :user_id THEN OtherUser.username ELSE NULL END
			         ORDER BY OtherPerson.name ASC SEPARATOR ', '
			       ) AS participant_usernames,
			       (
			         SELECT LastMessage.message_text
			         FROM {$messagesTable} AS LastMessage
			         WHERE LastMessage.internal_chat_id = Chat.id
			         ORDER BY LastMessage.id DESC
			         LIMIT 1
			       ) AS last_message_text
			FROM {$chatsTable} AS Chat
			INNER JOIN {$participantsTable} AS Participant
				ON Participant.internal_chat_id = Chat.id
				AND Participant.user_id = :user_id
			INNER JOIN {$participantsTable} AS OtherParticipant
				ON OtherParticipant.internal_chat_id = Chat.id
			INNER JOIN {$usersTable} AS OtherUser ON OtherUser.id = OtherParticipant.user_id
			INNER JOIN {$peopleTable} AS OtherPerson ON OtherPerson.id = OtherUser.person_id
			WHERE Chat.id = :chat_id
			  AND Chat.tenant_id = :tenant_id
			  AND Chat.status = 'active'
			  AND Participant.archived_at IS NULL
			GROUP BY Chat.id,
			         Chat.tenant_id,
			         Chat.tenant_database_number,
			         Chat.chat_type,
			         Chat.title,
			         Chat.created_by_user_id,
			         Chat.status,
			         Chat.last_message_at,
			         Chat.created,
			         Chat.modified,
			         Participant.last_seen_at
			LIMIT 1
		");
		$stmt->execute([
			'chat_id' => $chatId,
			'tenant_id' => $tenantId,
			'user_id' => $userId,
		]);

		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		return $row ?: null;
		}

	/**
	 * Returns messages from an internal chat.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getMessages(int $chatId, int $limit = 200): array
		{
		$messagesTable = $this->managerTable('internal_chat_messages');
		$stmt = $this->db->prepare("
			SELECT id,
			       internal_chat_id,
			       sender_user_id,
			       sender_name,
			       message_text,
			       created
			FROM {$messagesTable}
			WHERE internal_chat_id = :chat_id
			ORDER BY id ASC
			LIMIT :limit
		");
		$stmt->bindValue(':chat_id', $chatId, PDO::PARAM_INT);
		$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
		$stmt->execute();

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Creates a new internal chat message.
	 */
	public function createMessage(int $chatId, int $tenantId, int $senderUserId, string $senderName, string $message): ?array
		{
		$message = trim($message);
		if ($message === '' || !$this->findChatForParticipant($chatId, $tenantId, $senderUserId))
			{
			return null;
			}

		$messagesTable = $this->managerTable('internal_chat_messages');
		$chatsTable = $this->managerTable('internal_chats');

		$this->db->beginTransaction();

		try
			{
			$stmt = $this->db->prepare("
				INSERT INTO {$messagesTable} (internal_chat_id, sender_user_id, sender_name, message_text, created)
				VALUES (:chat_id, :sender_user_id, :sender_name, :message_text, NOW())
			");
			$stmt->execute([
				'chat_id' => $chatId,
				'sender_user_id' => $senderUserId,
				'sender_name' => $senderName,
				'message_text' => $message,
			]);

			$messageId = (int) $this->db->lastInsertId();

			$stmt = $this->db->prepare("
				UPDATE {$chatsTable}
				SET last_message_at = NOW(), modified = NOW()
				WHERE id = :chat_id
			");
			$stmt->execute(['chat_id' => $chatId]);

			$this->db->commit();
			}
		catch (\Throwable $e)
			{
			$this->db->rollBack();
			return null;
			}

		return $this->findMessage($messageId);
		}

	/**
	 * Marks the internal chat as seen by the participant.
	 */
	public function markSeen(int $chatId, int $userId): void
		{
		$participantsTable = $this->managerTable('internal_chat_participants');
		$stmt = $this->db->prepare("
			UPDATE {$participantsTable}
			SET last_seen_at = NOW(), modified = NOW()
			WHERE internal_chat_id = :chat_id
			  AND user_id = :user_id
		");
		$stmt->execute([
			'chat_id' => $chatId,
			'user_id' => $userId,
		]);
		}

	/**
	 * Stores the current participant typing state for a few seconds.
	 */
	public function setTypingState(int $chatId, int $tenantId, int $userId, bool $isTyping): bool
		{
		if (!$this->findChatForParticipant($chatId, $tenantId, $userId))
			{
			return false;
			}

		$participantsTable = $this->managerTable('internal_chat_participants');
		$stmt = $this->db->prepare("
			UPDATE {$participantsTable}
			SET typing_until_at = CASE
					WHEN :is_typing = 1 THEN DATE_ADD(NOW(), INTERVAL 4 SECOND)
					ELSE NULL
				END,
				modified = NOW()
			WHERE internal_chat_id = :chat_id
			  AND user_id = :user_id
		");
		$stmt->execute([
			'is_typing' => $isTyping ? 1 : 0,
			'chat_id' => $chatId,
			'user_id' => $userId,
		]);

		return $stmt->rowCount() > 0;
		}

	/**
	 * Lists participants currently typing in the chat.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function listTypingUsers(int $chatId, int $tenantId, int $currentUserId): array
		{
		$chatsTable = $this->managerTable('internal_chats');
		$participantsTable = $this->managerTable('internal_chat_participants');
		$usersTable = $this->managerTable('users');
		$peopleTable = $this->managerTable('people');

		$stmt = $this->db->prepare("
			SELECT TypingParticipant.user_id AS id,
			       User.username,
			       Person.name
			FROM {$participantsTable} AS CurrentParticipant
			INNER JOIN {$chatsTable} AS Chat
				ON Chat.id = CurrentParticipant.internal_chat_id
			INNER JOIN {$participantsTable} AS TypingParticipant
				ON TypingParticipant.internal_chat_id = Chat.id
			INNER JOIN {$usersTable} AS User ON User.id = TypingParticipant.user_id
			INNER JOIN {$peopleTable} AS Person ON Person.id = User.person_id
			WHERE CurrentParticipant.internal_chat_id = :chat_id
			  AND CurrentParticipant.user_id = :current_user_id
			  AND Chat.tenant_id = :tenant_id
			  AND Chat.status = 'active'
			  AND TypingParticipant.user_id <> :current_user_id
			  AND TypingParticipant.typing_until_at > NOW()
			ORDER BY Person.name ASC, User.username ASC
		");
		$stmt->execute([
			'chat_id' => $chatId,
			'tenant_id' => $tenantId,
			'current_user_id' => $currentUserId,
		]);

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Returns a direct chat conversation key.
	 */
	private function directConversationKey(int $tenantId, int $firstUserId, int $secondUserId): string
		{
		$userIds = [$firstUserId, $secondUserId];
		sort($userIds, SORT_NUMERIC);

		return sprintf('tenant:%d:direct:%d:%d', $tenantId, $userIds[0], $userIds[1]);
		}

	/**
	 * Restores or inserts a participant relation.
	 */
	private function upsertParticipant(int $chatId, int $userId): void
		{
		$participantsTable = $this->managerTable('internal_chat_participants');
		$stmt = $this->db->prepare("
			INSERT INTO {$participantsTable} (internal_chat_id, user_id, joined_at, created, modified)
			VALUES (:chat_id, :user_id, NOW(), NOW(), NOW())
			ON DUPLICATE KEY UPDATE
				archived_at = NULL,
				modified = NOW()
		");
		$stmt->execute([
			'chat_id' => $chatId,
			'user_id' => $userId,
		]);
		}

	/**
	 * Returns a visible chat by its stable conversation key.
	 */
	private function findChatByConversationKey(string $conversationKey, int $tenantId, int $userId): ?array
		{
		$chatsTable = $this->managerTable('internal_chats');
		$stmt = $this->db->prepare("
			SELECT id
			FROM {$chatsTable}
			WHERE conversation_key = :conversation_key
			  AND tenant_id = :tenant_id
			  AND status = 'active'
			LIMIT 1
		");
		$stmt->execute([
			'conversation_key' => $conversationKey,
			'tenant_id' => $tenantId,
		]);

		$chatId = (int) $stmt->fetchColumn();
		if ($chatId <= 0)
			{
			return null;
			}

		return $this->findChatForParticipant($chatId, $tenantId, $userId);
		}

	/**
	 * Finds one message by id.
	 */
	private function findMessage(int $messageId): ?array
		{
		$messagesTable = $this->managerTable('internal_chat_messages');
		$stmt = $this->db->prepare("
			SELECT id,
			       internal_chat_id,
			       sender_user_id,
			       sender_name,
			       message_text,
			       created
			FROM {$messagesTable}
			WHERE id = :message_id
			LIMIT 1
		");
		$stmt->execute(['message_id' => $messageId]);

		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		return $row ?: null;
		}
	}

?>
