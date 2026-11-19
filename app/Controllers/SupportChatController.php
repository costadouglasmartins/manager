<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\TenantContext;
use App\Models\SupportChat;
use App\Services\SupportChatTokenService;

class SupportChatController extends Controller
	{
	/**
	 * Returns the current authenticated tenant user.
	 *
	 * @return array<string, mixed>
	 */
	private function currentContext(): array
		{
		$this->requireAuth();
		$this->requireTenant();

		$user = $_SESSION['manager']['user'] ?? [];
		$tenant = TenantContext::tenant() ?? [];

		return [
			'user' => $user,
			'tenant' => $tenant,
		];
		}

	/**
	 * Returns the support widget bootstrap payload.
	 */
	public function bootstrap()
		{
		$context = $this->currentContext();
		$user = $context['user'];
		$tenant = $context['tenant'];

		$model = new SupportChat();
		$chat = $model->findActiveChatForCustomer((int) ($user['id'] ?? 0), (int) ($tenant['id'] ?? 0));
		$messages = [];
		$history = $model->listClosedChatsForCustomer((int) ($user['id'] ?? 0), (int) ($tenant['id'] ?? 0));

		if ($chat)
			{
			$messages = $model->getMessages((int) $chat['id']);
			}

		$this->json([
			'currentUser' => [
				'id' => (int) ($user['id'] ?? 0),
				'name' => (string) ($user['name'] ?? ''),
				'username' => (string) ($user['username'] ?? ''),
			],
			'tenant' => [
				'id' => (int) ($tenant['id'] ?? 0),
				'name' => (string) ($tenant['name'] ?? ''),
				'fantasy' => (string) ($tenant['fantasy'] ?? ''),
				'database_number' => (string) ($tenant['database_number'] ?? ''),
			],
			'chat' => $chat,
			'messages' => $messages,
			'history' => $history,
			'attendants' => $model->listOnlineAttendants((int) ($user['id'] ?? 0)),
			'unread_count' => $model->getUnreadCount((int) ($user['id'] ?? 0), (int) ($tenant['id'] ?? 0)),
		]);
		}

	/**
	 * Acknowledges only support messages displayed to the current customer.
	 */
	public function seen()
		{
		if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST')
			{
			$this->json([
				'error' => 'method_not_allowed',
				'message' => 'Método não permitido.',
			], 405);
			}

		$context = $this->currentContext();
		$userId = (int) ($context['user']['id'] ?? 0);
		$tenantId = (int) ($context['tenant']['id'] ?? 0);
		$input = $_POST;
		$json = json_decode((string) file_get_contents('php://input'), true);
		if (is_array($json))
			{
			$input = array_merge($input, $json);
			}
		$chatId = (int) ($input['chat_id'] ?? 0);
		$lastMessageId = (int) ($input['last_message_id'] ?? 0);
		$model = new SupportChat();

		if (!$model->markSeen($chatId, $userId, $tenantId, $lastMessageId))
			{
			$this->json([
				'error' => 'chat_not_found',
				'message' => 'Chat ou mensagem não encontrada.',
			], 404);
			}

		$chat = $model->findChatForCustomer($chatId, $userId, $tenantId);
		$this->json([
			'chat_id' => $chatId,
			'unread_count' => $model->getUnreadCount($userId, $tenantId),
			'chat_unread_count' => (int) ($chat['unread_count'] ?? 0),
		]);
		}

	/**
	 * Returns the WebSocket token for the current tenant-aware customer.
	 */
	public function wsAuth()
		{
		$context = $this->currentContext();
		$user = $context['user'];
		$tenant = $context['tenant'];

		$token = SupportChatTokenService::issue([
			'system' => 'manager',
			'role' => 'customer',
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
	 * Returns one chat owned by the current customer and tenant.
	 *
	 * @param mixed $chatId
	 */
	public function show($chatId)
		{
		$context = $this->currentContext();
		$user = $context['user'];
		$tenant = $context['tenant'];

		$model = new SupportChat();
		$chat = $model->findChatForCustomer((int) $chatId, (int) ($user['id'] ?? 0), (int) ($tenant['id'] ?? 0));

		if (!$chat)
			{
			$this->json([
				'error' => 'chat_not_found',
				'message' => 'Chat nao encontrado.',
			], 404);
			}

		$this->json([
			'chat' => $chat,
			'messages' => $model->getMessages((int) $chatId),
		]);
		}
	}

?>
