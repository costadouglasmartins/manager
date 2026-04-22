<?php

namespace App\Core;

class TenantModel
  {
	protected $db;

	/**
	 * Connects the model to the currently selected tenant database.
	 */
	public function __construct()
  	{
		$this->db = Database::getCurrentTenantConnection();
	  }
  }
?>