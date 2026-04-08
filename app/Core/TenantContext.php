<?php

namespace App\Core;

class TenantContext
{
	public static function tenant(): ?array
	{
		$tenant = $_SESSION['manager']['tenant'] ?? null;
		return is_array($tenant) ? $tenant : null;
	}

	public static function setTenant(array $tenant): void
	{
		$_SESSION['manager']['tenant'] = [
			'id' => (int) ($tenant['id'] ?? 0),
			'code' => (string) ($tenant['code'] ?? ''),
			'name' => (string) ($tenant['name'] ?? ''),
			'database_name' => (string) ($tenant['database_name'] ?? ''),
			'status' => (string) ($tenant['status'] ?? ''),
		];
	}

	public static function clear(): void
	{
		unset($_SESSION['manager']['tenant']);
	}

	public static function databaseName(): ?string
	{
		$tenant = self::tenant();

		if (!$tenant || empty($tenant['database_name']))
		{
			return null;
		}

		return (string) $tenant['database_name'];
	}

	public static function hasTenant(): bool
	{
		return self::databaseName() !== null;
	}
}

?>
