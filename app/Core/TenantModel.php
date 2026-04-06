<?php

namespace App\Core;

class TenantModel
{
	protected $db;

	public function __construct()
	{
		$this->db = Database::getCurrentTenantConnection();
	}
}

?>
