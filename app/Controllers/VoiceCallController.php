<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\TenantContext;
use App\Services\SupportChatTokenService;
use App\Services\VoiceCallIceService;
use App\Services\VoiceCallService;

/**
 * Voice calls between tenant users and with support. The call runs inside the
 * chat window; these endpoints only issue the socket token and ICE servers.
 */
class VoiceCallController extends Controller
	{
	/**
	 * Returns the authenticated user and tenant, refusing tenants without the feature.
	 *
	 * @return array{user: array<string, mixed>, tenant: array<string, mixed>}
	 */
	private function currentContext(): array
		{
		$this->requireAuth();
		$this->requireTenant();

		$user = $_SESSION['manager']['user'] ?? [];
		$tenant = TenantContext::tenant() ?? [];

		if (!VoiceCallService::enabledForTenant((int) ($tenant['id'] ?? 0)))
			{
			if ($this->wantsJson())
				{
				$this->json([
					'error' => 'voice_calls_disabled',
					'message' => 'Chamadas de voz desativadas para este tenant.',
				], 403);
				}

			http_response_code(403);
			echo 'Chamadas de voz desativadas para este tenant.';
			exit;
			}

		return ['user' => $user, 'tenant' => $tenant];
		}

	/**
	 * Returns a WebSocket token for the voice call channel.
	 */
	public function wsAuth()
		{
		$context = $this->currentContext();
		$user = $context['user'];
		$tenant = $context['tenant'];

		$this->json([
			'token' => SupportChatTokenService::issue([
				'system' => 'manager',
				'role' => 'voice',
				'user_id' => (int) ($user['id'] ?? 0),
				'user_name' => (string) ($user['name'] ?? ''),
				'username' => (string) ($user['username'] ?? ''),
				'tenant_id' => (int) ($tenant['id'] ?? 0),
				'tenant_database_number' => (string) ($tenant['database_number'] ?? ''),
				'tenant_name' => (string) ($tenant['fantasy'] ?? $tenant['name'] ?? ''),
			]),
			'websocketUrl' => SupportChatTokenService::websocketUrl(),
		]);
		}

	/**
	 * Returns STUN/TURN servers with short-lived credentials.
	 */
	public function iceServers()
		{
		$context = $this->currentContext();

		$this->json([
			'iceServers' => VoiceCallIceService::iceServers((int) ($context['user']['id'] ?? 0)),
		]);
		}
	}

?>
