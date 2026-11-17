<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\TenantContext;
use App\Services\UserPresenceLimitException;
use App\Services\UserPresenceService;

class UserPresenceController extends Controller
	{
	/**
	 * Returns the current user presence.
	 **/
	public function current(): void
		{
		$this->requireAuth();

		$user = $_SESSION['manager']['user'] ?? [];
		$presence = (new UserPresenceService())->current((int) ($user['id'] ?? 0));

		$this->json([
			'presence' => $presence,
			'statuses' => UserPresenceService::statuses(),
		]);
		}

	/**
	 * Updates the current user presence status.
	 **/
	public function status(): void
		{
		$this->requireAuth();

		if ($_SERVER['REQUEST_METHOD'] !== 'POST')
			{
			$this->json(['error' => 'method_not_allowed', 'message' => 'Method not allowed.'], 405, ['Allow' => 'POST']);
			}

		$user = $_SESSION['manager']['user'] ?? [];
		$tenant = TenantContext::tenant();
		$userId = (int) ($user['id'] ?? 0);
		$tenantId = isset($tenant['id']) && (int) $tenant['id'] > 0 ? (int) $tenant['id'] : null;
		$status = (string) ($_POST['status'] ?? '');

		try
			{
			$presence = (new UserPresenceService())->setStatus($userId, $tenantId, $status);
			$_SESSION['manager']['user']['presence_status'] = $presence['status'];
			}
		catch (UserPresenceLimitException $e)
			{
			$this->json(['error' => 'online_limit_reached', 'message' => $e->getMessage()], 409);
			}
		catch (\Throwable $e)
			{
			error_log('USER PRESENCE ERROR: ' . $e->getMessage());
			$this->json(['error' => 'presence_unavailable', 'message' => 'Não foi possível atualizar a presença agora. Tente novamente.'], 503);
			}

		$this->json(['presence' => $presence]);
		}
	}
?>
