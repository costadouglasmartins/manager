<?php

namespace App\Core;

use PDO;
use PDOException;

class Database
  {
	private static array $instances = [];

	/**
	 * Returns a shared PDO connection for the manager database.
	 */
	public static function getManagerConnection(): PDO
	  {
		return self::getConnection(self::getManagerDatabaseName());
	  }

	/**
	 * Returns a shared PDO connection for the given tenant database.
	 */
	public static function getTenantConnection(string $databaseName): PDO
	  {
		$databaseName = trim($databaseName);

		if ($databaseName === '')
		  {
			throw new PDOException('Tenant database name cannot be empty.');
		  }

		return self::getConnection($databaseName);
	  }

	/**
	 * Returns a shared PDO connection for the tenant currently selected in session.
	 */
	public static function getCurrentTenantConnection(): PDO
	  {
		$databaseName = TenantContext::databaseName();

		if (!$databaseName)
		  {
			throw new PDOException('No tenant selected in session.');
		  }

		return self::getTenantConnection($databaseName);
	  }

	/**
	 * Returns a manager or tenant connection depending on the provided database name.
	 */
	public static function getInstance(?string $databaseName = null): PDO
	  {
		if ($databaseName === null || trim($databaseName) === '')
		  {
			return self::getManagerConnection();
		  }

		return self::getConnection($databaseName);
	  }

	/**
	 * Returns the configured manager database name.
	 */
	public static function getManagerDatabaseName(): string
	  {
		return DB_DATABASE_MANAGER;
	  }

	/**
	 * Creates or reuses a shared PDO connection for the requested database.
	 */
	private static function getConnection(string $databaseName): PDO
	  {
		$databaseName = self::validateDatabaseName($databaseName);

		if (isset(self::$instances[$databaseName]))
		  {
			return self::$instances[$databaseName];
		  }

		$dsn = sprintf(
			'mysql:host=%s;port=%s;dbname=%s;charset=%s',
			DB_HOST,
			DB_PORT,
			$databaseName,
			DB_CHARSET
		);

		try
		  {
			$pdo = new PDO($dsn, DB_USERNAME, DB_PASSWORD);
			$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
			self::$instances[$databaseName] = $pdo;
		  }
		catch (PDOException $e)
		  {
			error_log(sprintf('Database connection failed for "%s": %s', $databaseName, $e->getMessage()));
			throw new PDOException('Não foi possível conectar ao banco de dados.');
		  }

		return self::$instances[$databaseName];
	  }

	/**
	 * Validates a database identifier before including it in a DSN.
	 */
	private static function validateDatabaseName(string $databaseName): string
	  {
		$databaseName = trim($databaseName);

		if ($databaseName === '' || !preg_match('/^[A-Za-z0-9_]+$/', $databaseName))
		  {
			throw new \InvalidArgumentException('Nome de banco de dados inválido.');
		  }

		return $databaseName;
	  }
  }
?>
