<?php

namespace App\Core;

class TenantAwareController extends Controller
{
	public function __construct()
	{
		$this->requireAuth();
		$this->requireTenant();
	}
}

?>
