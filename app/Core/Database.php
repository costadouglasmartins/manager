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
		return defined('DB_DATABASE_MANAGER') ? DB_DATABASE_MANAGER	: 'manager_main';
	  }

	/**
	 * Creates or reuses a shared PDO connection for the requested database.
	 */
	private static function getConnection(string $databaseName): PDO
	  {
		$databaseName = trim($databaseName);

		if (isset(self::$instances[$databaseName]))
		  {
			return self::$instances[$databaseName];
		  }

		$host = defined('DB_HOST') ? DB_HOST : 'mysql';
		$port = defined('DB_PORT') ? DB_PORT : '3306';
		$username = defined('DB_USERNAME') ? DB_USERNAME : 'root';
		$password = defined('DB_PASSWORD') ? DB_PASSWORD : 'rootpassword';
		$charset = defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4';

		$dsn = sprintf(
			'mysql:host=%s;port=%s;dbname=%s;charset=%s',
			$host,
			$port,
			$databaseName,
			$charset
		);

		try
		  {
			$pdo = new PDO($dsn, $username, $password);
			$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
			self::$instances[$databaseName] = $pdo;
		  }
		catch (PDOException $e)
		  {
			die('Erro de conexao com o banco "' . $databaseName . '": ' . $e->getMessage());
		  }

		return self::$instances[$databaseName];
	  }
  }
?>