<?php
namespace App\Core;

use App\Core\Database;

class Model
  {
	use AuditsChanges;

	/**
	 * Protected db
	 * @var $db
	 */
  protected $db;

	/**
	 * Construct method
	 */
  public function __construct()
    {
    $this->db = Database::getManagerConnection();
    }

	/**
	 * Returns a table qualified with the manager database name.
	 */
	protected function managerTable(string $table): string
		{
		return sprintf('`%s`.`%s`', Database::getManagerDatabaseName(), trim($table));
		}
  }
?>
