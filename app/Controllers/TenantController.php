<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\TenantContext;
use App\Models\Tenant;

class TenantController extends Controller
{
	public function index()
	{
		$this->requireAuth();

		$user = $_SESSION['manager']['user'] ?? null;
		$tenantModel = new Tenant();
		$tenants = $tenantModel->getAccessibleByUserId((int) ($user['id'] ?? 0));

		$this->json([
			'current_tenant' => TenantContext::tenant(),
			'tenants' => $tenants,
		]);
	}

	public function switch($tenantId)
	{
		$this->requireAuth();

		$user = $_SESSION['manager']['user'] ?? null;
		$tenantModel = new Tenant();
		$tenant = $tenantModel->findByIdForUser((int) $tenantId, (int) ($user['id'] ?? 0));

		if (!$tenant)
		{
			$this->json([
				'error' => 'tenant_not_found',
				'message' => 'Tenant nao encontrado ou sem acesso para o usuario autenticado.',
			], 404);
		}

		TenantContext::setTenant($tenant);

		$this->json([
			'message' => 'Tenant selecionado com sucesso.',
			'tenant' => TenantContext::tenant(),
		]);
	}

	public function clear()
	{
		$this->requireAuth();
		TenantContext::clear();

		$this->json([
			'message' => 'Contexto do tenant removido.',
		]);
	}
}

?>
