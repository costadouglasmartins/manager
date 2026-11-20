<?php

namespace App\Services;

/**
 * Voice call helpers shared by the controller and the chat widget.
 */
class VoiceCallService
	{
	/**
	 * Returns whether voice calls are enabled for a tenant. Without the
	 * migration the setting does not exist and the feature stays off.
	 */
	public static function enabledForTenant(int $tenantId): bool
		{
		return $tenantId > 0 && (new TenantSettingService())->get($tenantId, 'voice_calls_enabled') === '1';
		}
	}

?>
