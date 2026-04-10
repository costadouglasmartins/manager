<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\TenantContext;
use App\Models\SchemaVersion;

class HomeController extends Controller
{

	public function index()
	{
		$this->requireAuth();

		$data = [
			'title' => 'Início',
			'tenant' => TenantContext::tenant(),
			'schemaVersions' => [],
		];

		// Se há um tenant selecionado, busca os dados do banco do tenant
		if (TenantContext::hasTenant())
		{
			$schemaVersion = new SchemaVersion();
			$data['schemaVersions'] = $schemaVersion->getLatest(10);
		}

		$this->view('home/index', $data);
	}
}

?>
