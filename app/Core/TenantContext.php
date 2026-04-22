<?php

namespace App\Core;

class TenantContext
  {
	const DATABASE_PREFIX = 'manager_';

	/**
	 * Returns the active tenant stored in the current session.
	 */
	public static function tenant(): ?array
	  {
		$tenant = $_SESSION['manager']['tenant'] ?? null;
		return is_array($tenant) ? $tenant : null;
	  }

	/**
	 * Stores the active tenant context in the current session.
	 */
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

	/**
	 * Removes the active tenant context from the current session.
	 */
	public static function clear(): void
	  {
		unset($_SESSION['manager']['tenant']);
	  }

	/**
	 * Returns the full tenant database name (`manager_XXXX`).
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

	/**
	 * Indicates whether a tenant is currently selected.
	 */
	public static function hasTenant(): bool
	  {
		return self::databaseName() !== null;
	  }

	/**
	 * Returns only the tenant database number (`XXXX`).
	 */
	public static function databaseNumber(): ?string
	  {
		$tenant = self::tenant();
		return !empty($tenant['database_number']) ? (string) $tenant['database_number'] : null;
	  }
  }
?>