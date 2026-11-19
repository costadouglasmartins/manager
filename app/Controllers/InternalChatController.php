<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\TenantContext;
use App\Models\InternalChat;
use App\Services\SupportChatTokenService;

class InternalChatController extends Controller
	{
	/**
	 * Returns the current authenticated tenant context.
	 *
	 * @return array<string, mixed>
	 */
	private function currentContext(): array
		{
		$this->requireAuth();
		$this->requireTenant();

		return [
			'user' => $_SESSION['manager']['user'] ?? [],
			'tenant' => TenantContext::tenant() ?? [],
		];
		}

	/**
	 * Returns the internal chat bootstrap payload.
	 */
	public function bootstrap()
		{
		$context = $this->currentContext();
		$user = $context['user'];
		$tenant = $context['tenant'];
		$userId = (int) ($user['id'] ?? 0);
		$tenantId = (int) ($tenant['id'] ?? 0);

		$model = new InternalChat();
		$activeChatId = (int) ($_GET['chat_id'] ?? 0);
		$activeChat = $activeChatId > 0 ? $model->findChatForParticipant($activeChatId, $tenantId, $userId) : null;
		$messages = [];
		$typingUsers = [];

		if ($activeChat)
			{
			$messages = $model->getMessages((int) $activeChat['id']);
			$typingUsers = $model->listTypingUsers((int) $activeChat['id'], $tenantId, $userId);
			}

		$this->json([
			'currentUser' => $this->serializeCurrentUser($user),
			'tenant' => $this->serializeTenant($tenant),
			'users' => $model->listTenantUsers($tenantId, $userId),
			'chats' => $model->listChatsForUser($tenantId, $userId),
			'unread_count' => $model->getUnreadCount($tenantId, $userId),
			'activeChat' => $activeChat,
			'messages' => $messages,
			'typingUsers' => $typingUsers,
		]);
		}

	/**
	 * Returns one internal chat owned by the current participant.
	 *
	 * @param mixed $chatId
	 */
	public function show($chatId)
		{
		$context = $this->currentContext();
		$user = $context['user'];
		$tenant = $context['tenant'];
		$userId = (int) ($user['id'] ?? 0);
		$tenantId = (int) ($tenant['id'] ?? 0);

		$model = new InternalChat();
		$chat = $model->findChatForParticipant((int) $chatId, $tenantId, $userId);

		if (!$chat)
			{
			$this->json([
				'error' => 'chat_not_found',
				'message' => 'Conversa interna nao encontrada.',
			], 404);
			}

		$this->json([
			'chat' => $chat,
			'messages' => $model->getMessages((int) $chat['id']),
			'typingUsers' => $model->listTypingUsers((int) $chat['id'], $tenantId, $userId),
		]);
		}

	/**
	 * Acknowledges only messages displayed to the current participant.
	 */
	public function seen()
		{
		$this->requirePost();
		$context = $this->currentContext();
		$userId = (int) ($context['user']['id'] ?? 0);
		$tenantId = (int) ($context['tenant']['id'] ?? 0);
		$input = $this->requestData();
		$chatId = (int) ($input['chat_id'] ?? 0);
		$lastMessageId = (int) ($input['last_message_id'] ?? 0);
		$model = new InternalChat();

		if (!$model->markSeen($chatId, $tenantId, $userId, $lastMessageId))
			{
			$this->json([
				'error' => 'chat_not_found',
				'message' => 'Conversa ou mensagem interna não encontrada.',
			], 404);
			}

		$this->json([
			'chat_id' => $chatId,
			'unread_count' => $model->getUnreadCount($tenantId, $userId),
			'chats' => $model->listChatsForUser($tenantId, $userId),
		]);
		}

	/**
	 * Starts or returns a direct internal chat.
	 */
	public function start()
		{
		$this->requirePost();
		$context = $this->currentContext();
		$user = $context['user'];
		$tenant = $context['tenant'];
		$input = $this->requestData();
		$userId = (int) ($user['id'] ?? 0);
		$tenantId = (int) ($tenant['id'] ?? 0);
		$targetUserId = (int) ($input['target_user_id'] ?? 0);

		$model = new InternalChat();
		$chat = $model->startDirectChat(
			$tenantId,
			(string) ($tenant['database_number'] ?? ''),
			$userId,
			$targetUserId
		);

		if (!$chat)
			{
			$this->json([
				'error' => 'invalid_target_user',
				'message' => 'Usuario indisponivel para conversa interna.',
			], 422);
			}

		$this->json([
			'chat' => $chat,
			'messages' => $model->getMessages((int) $chat['id']),
			'typingUsers' => $model->listTypingUsers((int) $chat['id'], $tenantId, $userId),
			'chats' => $model->listChatsForUser($tenantId, $userId),
			'unread_count' => $model->getUnreadCount($tenantId, $userId),
		]);
		}

	/**
	 * Persists a new internal chat message.
	 */
	public function send()
		{
		$this->requirePost();
		$context = $this->currentContext();
		$user = $context['user'];
		$tenant = $context['tenant'];
		$input = $this->requestData();
		$userId = (int) ($user['id'] ?? 0);
		$tenantId = (int) ($tenant['id'] ?? 0);
		$chatId = (int) ($input['chat_id'] ?? 0);
		$messageText = trim((string) ($input['message'] ?? ''));

		if ($chatId <= 0 || $messageText === '')
			{
			$this->json([
				'error' => 'invalid_message',
				'message' => 'Informe uma conversa e uma mensagem.',
			], 422);
			}

		$model = new InternalChat();
		$message = $model->createMessage($chatId, $tenantId, $userId, (string) ($user['name'] ?? $user['username'] ?? ''), $messageText);

		if (!$message)
			{
			$this->json([
				'error' => 'message_not_created',
				'message' => 'Nao foi possivel enviar a mensagem.',
			], 422);
			}

		$model->setTypingState($chatId, $tenantId, $userId, false);
		$chat = $model->findChatForParticipant($chatId, $tenantId, $userId);

		$this->json([
			'chat' => $chat,
			'message' => $message,
			'messages' => $model->getMessages($chatId),
			'typingUsers' => $model->listTypingUsers($chatId, $tenantId, $userId),
			'chats' => $model->listChatsForUser($tenantId, $userId),
			'unread_count' => $model->getUnreadCount($tenantId, $userId),
		]);
		}

	/**
	 * Updates and returns the current typing state for an internal chat.
	 */
	public function typing()
		{
		$this->requirePost();
		$context = $this->currentContext();
		$user = $context['user'];
		$tenant = $context['tenant'];
		$input = $this->requestData();
		$userId = (int) ($user['id'] ?? 0);
		$tenantId = (int) ($tenant['id'] ?? 0);
		$chatId = (int) ($input['chat_id'] ?? 0);
		$isTyping = filter_var($input['is_typing'] ?? false, FILTER_VALIDATE_BOOLEAN);

		if ($chatId <= 0)
			{
			$this->json([
				'error' => 'invalid_chat',
				'message' => 'Conversa interna invalida.',
			], 422);
			}

		$model = new InternalChat();
		if (!$model->setTypingState($chatId, $tenantId, $userId, $isTyping))
			{
			$this->json([
				'error' => 'typing_not_updated',
				'message' => 'Nao foi possivel atualizar o estado de digitacao.',
			], 422);
			}

		$this->json([
			'typingUsers' => $model->listTypingUsers($chatId, $tenantId, $userId),
		]);
		}

	/**
	 * Returns the current typing users for one internal chat.
	 *
	 * @param mixed $chatId
	 */
	public function typingStatus($chatId)
		{
		$context = $this->currentContext();
		$user = $context['user'];
		$tenant = $context['tenant'];
		$userId = (int) ($user['id'] ?? 0);
		$tenantId = (int) ($tenant['id'] ?? 0);

		$model = new InternalChat();
		if (!$model->findChatForParticipant((int) $chatId, $tenantId, $userId))
			{
			$this->json([
				'error' => 'chat_not_found',
				'message' => 'Conversa interna nao encontrada.',
			], 404);
			}

		$this->json([
			'typingUsers' => $model->listTypingUsers((int) $chatId, $tenantId, $userId),
		]);
		}

	/**
	 * Returns a WebSocket token scoped to internal tenant chat.
	 */
	public function wsAuth()
		{
		$context = $this->currentContext();
		$user = $context['user'];
		$tenant = $context['tenant'];

		$token = SupportChatTokenService::issue([
			'system' => 'manager',
			'role' => 'internal',
			'channel' => 'internal_chat',
			'user_id' => (int) ($user['id'] ?? 0),
			'user_name' => (string) ($user['name'] ?? ''),
			'username' => (string) ($user['username'] ?? ''),
			'tenant_id' => (int) ($tenant['id'] ?? 0),
			'tenant_database_number' => (string) ($tenant['database_number'] ?? ''),
			'tenant_name' => (string) ($tenant['fantasy'] ?? $tenant['name'] ?? ''),
		]);

		$this->json([
			'token' => $token,
			'websocketUrl' => SupportChatTokenService::websocketUrl(),
		]);
		}

	/**
	 * Requires POST for mutating endpoints.
	 */
	private function requirePost(): void
		{
		if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST')
			{
			return;
			}

		$this->json([
			'error' => 'method_not_allowed',
			'message' => 'Metodo nao permitido.',
		], 405);
		}

	/**
	 * Returns merged JSON and form request data.
	 *
	 * @return array<string, mixed>
	 */
	private function requestData(): array
		{
		$data = $_POST;
		$rawBody = trim((string) file_get_contents('php://input'));

		if ($rawBody !== '')
			{
			$json = json_decode($rawBody, true);
			if (is_array($json))
				{
				$data = array_merge($data, $json);
				}
			}

		return $data;
		}

	/**
	 * Serializes the authenticated user.
	 *
	 * @param array<string, mixed> $user
	 * @return array<string, mixed>
	 */
	private function serializeCurrentUser(array $user): array
		{
		return [
			'id' => (int) ($user['id'] ?? 0),
			'name' => (string) ($user['name'] ?? ''),
			'username' => (string) ($user['username'] ?? ''),
		];
		}

	/**
	 * Serializes the selected tenant.
	 *
	 * @param array<string, mixed> $tenant
	 * @return array<string, mixed>
	 */
	private function serializeTenant(array $tenant): array
		{
		return [
			'id' => (int) ($tenant['id'] ?? 0),
			'name' => (string) ($tenant['name'] ?? ''),
			'fantasy' => (string) ($tenant['fantasy'] ?? ''),
			'database_number' => (string) ($tenant['database_number'] ?? ''),
		];
		}
	}

?>
