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
		$chat = $model->findLatestChatForCustomer((int) ($user['id'] ?? 0), (int) ($tenant['id'] ?? 0));
		$messages = [];

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
