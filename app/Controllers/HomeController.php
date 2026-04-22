<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\TenantContext;
use App\Models\SchemaVersion;

class HomeController extends Controller
  {
	/**
	 * Renders the dashboard and, when a tenant is selected, loads recent schema versions.
	 */
	public function index()
	  {
		$this->requireAuth();

		$data = [
			'title' => 'Início',
			'tenant' => TenantContext::tenant(),
			'schemaVersions' => [],
		];

		// When a tenant is selected, load the tenant database data.
		if (TenantContext::hasTenant())
		  {
			$schemaVersion = new SchemaVersion();
			$data['schemaVersions'] = $schemaVersion->getLatest(10);
		  }

		$this->view('home/index', $data);
	  }
  }
?>