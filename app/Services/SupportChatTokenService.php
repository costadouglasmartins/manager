<?php

namespace App\Services;

class SupportChatTokenService
	{
	/**
	 * Returns the shared HMAC secret used to authenticate sockets.
	 */
	private static function secret(): string
		{
		$secret = getenv('SUPPORT_CHAT_SECRET') ?: '9323d5e71fe951602b452cc683ff7c49f8d5cec8';
		return (string) $secret;
		}

	/**
	 * Issues a signed token for WebSocket authentication.
	 *
	 * @param array<string, mixed> $claims
	 */
	public static function issue(array $claims): string
		{
		$payload = $claims;
		$payload['iat'] = time();
		$payload['exp'] = time() + 3600;

		$encodedPayload = self::base64UrlEncode(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
		$signature = hash_hmac('sha256', $encodedPayload, self::secret(), true);

		return $encodedPayload . '.' . self::base64UrlEncode($signature);
		}

	/**
	 * Returns the configured WebSocket URL.
	 */
	public static function websocketUrl(): string
		{
		$configured = trim((string) (getenv('SUPPORT_CHAT_WS_URL') ?: ''));
		if ($configured !== '')
			{
			return $configured;
			}

		$scheme = 'ws';
		if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? null) == 443))
			{
			$scheme = 'wss';
			}

		$host = (string) ($_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost'));
		$host = preg_replace('/:\d+$/', '', $host) ?: 'localhost';

		return $scheme . '://' . $host . ':8081';
		}

	/**
	 * Encodes text/binary values using base64url format.
	 */
	private static function base64UrlEncode(string $value): string
		{
		return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
		}
	}

?>
