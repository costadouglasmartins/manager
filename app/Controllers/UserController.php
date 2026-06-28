<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\TenantContext;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AuditLogService;

class UserController extends Controller
	{

	/**
	 * Returns the current tenant id after validating auth, tenant and ability.
	 */
	private function currentTenantId(string $ability): int
		{
		$this->requireAuth();
		$this->requireTenant();
		$this->requirePermission($ability);

		$tenant = TenantContext::tenant();
		return (int) ($tenant['id'] ?? 0);
		}

	/**
	 * Aborts the request when the user does not belong to the current tenant.
	 */
	private function abortUserNotFound(): void
		{
		if ($this->wantsJson())
			{
			$this->json([
				'error' => 'user_not_found',
				'message' => 'Usuario nao encontrado ou sem acesso para o tenant selecionado.',
			], 404);
			}

		http_response_code(404);
		echo 'Usuario nao encontrado ou sem acesso para o tenant selecionado.';
		exit;
		}

	/**
	 * Writes an authentication audit event without blocking the auth flow.
	 * @param string $action Authentication action.
	 * @param array $user Authenticated user payload.
	 * @param array|null $tenant Tenant selected in the session, when available.
	 * @param array $context Additional context.
	 * @return void
	 **/
	private function writeAuthenticationLog(string $action, array $user, ?array $tenant = null, array $context = []): void
		{
		try
			{
			$logger = new AuditLogService(Database::getManagerConnection());

			if ($action === 'login')
				{
				$logger->logLogin($user, $tenant, $context);
				return;
				}

			if ($action === 'logout')
				{
				$logger->logLogout($user, $tenant, $context);
				}
			}
		catch (\Throwable $e)
			{
			error_log('AUDIT AUTH LOG ERROR: ' . $e->getMessage());
			}
		}

	/**
	 * Lists users for the current tenant with filters and pagination.
	 */
	public function index()
		{
		$tenantId = $this->currentTenantId('user.index');
		$user = new User();

		// Filters received from the query string.
		$filters = [
			'id'				=> isset($_GET['id'])				? trim($_GET['id'])				: '',
			'username'	=> isset($_GET['username'])	? trim($_GET['username'])			: '',
			'type'	    => isset($_GET['type'])     ? trim($_GET['type']) : '',
			'active'		=> isset($_GET['active'])		? trim($_GET['active'])		: '',
		];

		// Current page (?page=2).
		$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
		if ($page < 1)
			{
			$page = 1;
			}

		// Records per page.
		$perPage = 10;
		$offset = ($page - 1) * $perPage;

		// Paginated search with filters.
		$users = $user->search($filters, $perPage, $offset, $tenantId);

		// Total count for pagination using the same filters.
		$total = $user->count($filters, $tenantId);
		$totalPages = (int) ceil($total / $perPage);
		if ($totalPages < 1)
			{
			$totalPages = 1;
			}

		$this->view('users/index', [
			'users'		    => $users,
			'title'				=> 'Lista de usuários',
			'filters'			=> $filters,
			'page'				=> $page,
			'totalPages'	=> $totalPages,
			'total'				=> $total,
		]);
		}

	/**
	 * Renders the user edit form for the requested tenant-scoped user.
	 *
	 * @param mixed $id User identifier.
	 */
	public function edit($id)
		{
		$tenantId = $this->currentTenantId('user.edit');
		$user = new User();
		$data = $user->find($id, $tenantId);
		if (!$data)
			{
			$this->abortUserNotFound();
			}
		$this->view('users/edit', ['user' => $data]);
		}

	/**
	 * Updates the requested tenant-scoped user and redirects back to the listing.
	 *
	 * @param mixed $id User identifier.
	 */
	public function update($id)
		{
		$tenantId = $this->currentTenantId('user.update');
		$user = new User();

		if (!$user->find($id, $tenantId))
			{
			$this->abortUserNotFound();
			}

		$user->update($id, $_POST, $tenantId);
		$this->redirect('user');
		}

	/**
	 * Displays the login form and handles authentication attempts.
	 */
	public function login()
		{
		// If the user is already logged in, redirect to the home page.
		if (!empty($_SESSION['manager']['user']))
			{
			$this->redirect('/');
			exit;
			}

		// When the request is POST, process the login attempt.
		if ($_SERVER['REQUEST_METHOD'] === 'POST')
			{
			$email = isset($_POST['email']) ? trim($_POST['email']) : '';
			$password = isset($_POST['password']) ? $_POST['password'] : '';

			$userModel = new \App\Models\User();
			$user = $userModel->authenticate($email, $password);

				if ($user)
					{
					\App\Core\Authorization::clearCache();
					$_SESSION['manager']['user'] = [
						'id'				=> $user['id'],
						'username'	=> $user['username'],
						'email'			=> $user['email'],
						'type'			=> $user['type'],
						'name'			=> $user['name'],
						'tenant_id'	=> (int) ($user['tenant_id'] ?? 0)
					];

					// Select the user's own tenant first, then fall back to the accessible tenant list.
					$authenticationLogged = false;
					try
						{
						$tenantModel = new Tenant();
						$userId = (int) $user['id'];
						$userTenantId = (int) ($user['tenant_id'] ?? 0);

						if ($userTenantId > 0)
							{
							$tenant = $tenantModel->findByIdForUser($userTenantId, $userId);

							if ($tenant)
								{
								TenantContext::setTenant($tenant);
								$this->writeAuthenticationLog('login', $user, $tenant, [
									'tenant_selection' => 'primary_user_tenant',
								]);
								$this->redirect('/');
								exit;
								}
							}

						$tenants = $tenantModel->getAccessibleByUserId($userId);

						if (count($tenants) === 1)
							{
							TenantContext::setTenant($tenants[0]);
							$this->writeAuthenticationLog('login', $user, $tenants[0], [
								'tenant_selection' => 'single_accessible_tenant',
							]);
							$this->redirect('/');
							exit;
							}
						elseif (count($tenants) > 1)
							{
							$this->writeAuthenticationLog('login', $user, null, [
								'tenant_selection' => 'pending_user_choice',
								'available_tenants_count' => count($tenants),
							]);
							$this->redirect('tenant/select');
							exit;
							}
						}
					catch (\Exception $e)
						{
						error_log('ERROR LOGIN: Exception during tenant selection: ' . $e->getMessage());
						error_log('ERROR LOGIN: Trace: ' . $e->getTraceAsString());
						$this->writeAuthenticationLog('login', $user, null, [
							'tenant_selection' => 'selection_error',
							'error_message' => $e->getMessage(),
						]);
						$authenticationLogged = true;
						}

				// If execution reaches this point, no tenant was found or an error occurred, so redirect home anyway.
				if (!$authenticationLogged)
					{
					$this->writeAuthenticationLog('login', $user, TenantContext::tenant(), [
						'tenant_selection' => TenantContext::hasTenant() ? 'already_selected' : 'not_found',
					]);
					}
				$this->redirect('/');
				exit;
				}
			else
				{
				$error = 'Email ou senha inválidos.';
				$this->view('users/login', ['error' => $error, 'title' => 'Login']);
				return;
				}
			}

		// GET request: render the login form.
		$this->view('users/login', ['title' => 'Login']);
		}

	/**
	 * Ends the current session and redirects the user to the login page.
	 */
	public function logout()
		{
		$currentUser = $_SESSION['manager']['user'] ?? [];
		$currentTenant = TenantContext::tenant();

		if (!empty($currentUser))
			{
			$this->writeAuthenticationLog('logout', $currentUser, $currentTenant, [
				'tenant_selection' => $currentTenant ? 'selected' : 'none',
			]);
			}

		\App\Core\Authorization::clearCache();
		// Destroy the session and redirect.
		$_SESSION = [];
		if (ini_get("session.use_cookies"))
			{
			$params = session_get_cookie_params();
			setcookie(session_name(), '', time() - 42000,
				$params["path"], $params["domain"],
				$params["secure"], $params["httponly"]
			);
			}
		session_destroy();
		$this->redirect('user/login');
		exit;
		}

  /**
   * Displays the details for the requested tenant-scoped user.
   *
   * @param mixed $id User identifier.
   */
  public function informations($id)
		{
		$tenantId = $this->currentTenantId('user.informations');
		$user = new User();
		$data = $user->find($id, $tenantId);
		if (!$data)
			{
			$this->abortUserNotFound();
			}
		$this->view('users/informations', [
			'title' => 'Informações sobre o(a) usuário(a)',
			'user' => $data,
			'data' => ['user' => $data],
		]);
		}
	}
?>
