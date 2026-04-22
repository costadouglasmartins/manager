<?php

namespace App\Core;

class TenantAwareController extends Controller
  {
	/**
	 * Ensures the request has an authenticated user and an active tenant context.
	 */
	public function __construct()
	  {
		$this->requireAuth();
		$this->requireTenant();
	  }
  }
?>