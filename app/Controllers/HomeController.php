<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\TenantContext;
use App\Models\SchemaVersion;
use App\Models\Tenant;

class HomeController extends Controller
  {
	/**
	 * Renders the dashboard and, when a tenant is selected, loads recent schema versions.
	 */
	public function index()
	  {
		$this->requireAuth();

		if (!TenantContext::hasTenant())
		  {
			$user = $_SESSION['manager']['user'] ?? null;
			$tenantModel = new Tenant();
			$tenants = $tenantModel->getAccessibleByUserId((int) ($user['id'] ?? 0));

			if (count($tenants) === 1)
			  {
				TenantContext::setTenant($tenants[0]);
			  }
			elseif (count($tenants) > 1)
			  {
				$this->redirect('tenant/select');
			  }
		  }

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
