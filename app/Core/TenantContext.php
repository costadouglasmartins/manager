<?php

namespace App\Core;

class TenantContext
{
	const DATABASE_PREFIX = 'manager_';

	public static function tenant(): ?array
	{
		$tenant = $_SESSION['manager']['tenant'] ?? null;
		return is_array($tenant) ? $tenant : null;
	}

	public static function setTenant(array $tenant): void
	{
		$_SESSION['manager']['tenant'] = [
			'id' => (int) ($tenant['id'] ?? 0),
			'name' => (string) ($tenant['name'] ?? ''),
			'fantasy' => (string) ($tenant['fantasy'] ?? ''),
			'document' => (string) ($tenant['document'] ?? ''),
			'database_number' => (string) ($tenant['database_number'] ?? ''),
			'status' => (string) ($tenant['status'] ?? ''),
		];
	}

	public static function clear(): void
	{
		unset($_SESSION['manager']['tenant']);
	}

	/**
	 * Retorna o nome completo do banco (manager_XXXX)
	 */
	public static function databaseName(): ?string
	{
		$tenant = self::tenant();

		if (!$tenant || empty($tenant['database_number']))
		{
			return null;
		}

		return self::DATABASE_PREFIX . str_pad($tenant['database_number'], 4, '0', STR_PAD_LEFT);
	}

	public static function hasTenant(): bool
	{
		return self::databaseName() !== null;
	}

	/**
	 * Retorna apenas o número do banco (XXXX)
	 */
	public static function databaseNumber(): ?string
	{
		$tenant = self::tenant();
		return !empty($tenant['database_number']) ? (string) $tenant['database_number'] : null;
	}
}

?>
