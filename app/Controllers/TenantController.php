<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\TenantContext;
use App\Models\Tenant;

class TenantController extends Controller
  {
	/**
	 * Displays the tenant selection page and handles selection submissions.
	 */
	public function select()
	  {
		$this->requireAuth();

		$user = $_SESSION['manager']['user'] ?? null;
		$userId = (int) ($user['id'] ?? 0);
		$tenantModel = new Tenant();
		$error = null;

		if ($_SERVER['REQUEST_METHOD'] === 'POST')
		  {
			$tenantId = (int) ($_POST['tenant_id'] ?? 0);
			$tenant = $tenantModel->findByIdForUser($tenantId, $userId);

			if ($tenant)
			  {
				TenantContext::setTenant($tenant);
				$this->redirect('/');
			  }

			$error = 'Tenant nao encontrado ou sem acesso para o usuario autenticado.';
		  }

		$tenants = $tenantModel->getAccessibleByUserId($userId);

		$this->view('tenants/select', [
			'title' => 'Selecionar tenant',
			'currentTenant' => TenantContext::tenant(),
			'tenants' => $tenants,
			'error' => $error,
		]);
	  }

	/**
	 * Returns the tenants available to the authenticated user along with the current tenant context.
	 */
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

	/**
	 * Selects the tenant for the authenticated user session.
	 *
	 * @param mixed $tenantId Tenant identifier from the route.
	 */
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

	/**
	 * Clears the active tenant context from the current session.
	 */
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
