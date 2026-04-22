<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Authorization;
use App\Core\TenantContext;
use App\Models\Permission;

class PermissionController extends Controller
	{
	/**
	 * Returns the current tenant id after validating authentication, tenant context, and permission.
	 */
	private function currentTenantId(): int
		{
		$this->requireAuth();
		$this->requireTenant();
		$this->requirePermission('permission.manage');

		$tenant = TenantContext::tenant();
		return (int) ($tenant['id'] ?? 0);
		}

	/**
	 * Displays the permission catalog and the selected user's assigned abilities.
	 */
	public function index(): void
		{
		$tenantId = $this->currentTenantId();
		$permissionModel = new Permission();

		$permissions = $permissionModel->getAll();
		$users = $permissionModel->getUsers($tenantId);

		$selectedUserId = (int) ($_GET['user_id'] ?? 0);
		if ($selectedUserId > 0 && !$permissionModel->userBelongsToTenant($selectedUserId, $tenantId))
			{
			$selectedUserId = 0;
			}

		$userPermissionIds = [];
		$effectiveAbilities = [];
		if ($selectedUserId > 0)
			{
			$userPermissionIds = $permissionModel->getPermissionIdsByUser($selectedUserId);
			$effectiveAbilities = $permissionModel->getAbilitiesForUser($selectedUserId);
			}

		$this->view('permissions/index', [
			'permissions' => $permissions,
			'users' => $users,
			'selectedUserId' => $selectedUserId,
			'userPermissionIds' => $userPermissionIds,
			'effectiveAbilities' => $effectiveAbilities,
			'title' => 'Permissões',
		]);
		}

	/**
	 * Replaces the selected user's permissions with the submitted permission ids.
	 */
	public function saveUser(): void
		{
		$tenantId = $this->currentTenantId();
		$userId = (int) ($_POST['user_id'] ?? 0);
		$permissionIds = $this->normalizePermissionIds($_POST['permission_ids'] ?? []);

		$permissionModel = new Permission();
		if (!$permissionModel->userBelongsToTenant($userId, $tenantId))
			{
			$this->redirect('permission');
			}

		$permissionModel->setPermissionsForUser($userId, $permissionIds);
		Authorization::clearCache();

		$this->redirect('permission?user_id=' . $userId);
		}

	/**
	 * Synchronizes the permission catalog with abilities discovered in controller actions.
	 */
	public function sync(): void
		{
		$this->currentTenantId();
		$backUserId = (int) ($_POST['selected_user_id'] ?? 0);

		$permissionModel = new Permission();
		$added = $permissionModel->syncFromControllers(ROOT_PATH . '/app/Controllers');

		Authorization::clearCache();

		$qs = 'permission?synced=' . $added;
		if ($backUserId > 0)
			{
			$qs .= '&user_id=' . $backUserId;
			}

		$this->redirect($qs);
		}

	/**
	 * Creates a new permission entry in the catalog.
	 */
	public function create(): void
		{
		$this->currentTenantId();
		$backUserId = (int) ($_POST['selected_user_id'] ?? 0);

		$ability = (string) ($_POST['ability'] ?? '');
		$description = (string) ($_POST['description'] ?? '');

		$permissionModel = new Permission();
		$ok = $permissionModel->createPermission($ability, $description);

		Authorization::clearCache();

		$qs = 'permission?created=' . ($ok ? '1' : '0');
		if ($backUserId > 0)
			{
			$qs .= '&user_id=' . $backUserId;
			}

		$this->redirect($qs);
		}

	/**
	 * Displays the orphaned permissions report for the manager catalog.
	 */
	public function orphaned(): void
		{
		$this->currentTenantId();

		$permissionModel = new Permission();
		$orphaned = $permissionModel->getOrphanedPermissions();
		$stats = $permissionModel->getOrphanedPermissionStats();

		$this->view('permissions/orphaned', [
			'orphaned' => $orphaned,
			'stats' => $stats,
			'title' => 'Permissões Órfãs',
		]);
		}

	/**
	 * Deletes an orphaned permission from the manager catalog.
	 *
	 * @param mixed $id Permission identifier from the route.
	 */
	public function deleteOrphaned($id): void
		{
		$this->currentTenantId();

		if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST')
			{
			$this->redirect('permission/orphaned');
			}

		$permissionModel = new Permission();

		try
			{
			$result = $permissionModel->deleteOrphanedPermission((int) $id);

			if ($result['success'])
				{
				$this->redirect('permission/orphaned?deleted=1&users_affected=' . (int) $result['users_affected']);
				}

			$this->redirect('permission/orphaned?deleted=0&error=not_orphaned');
			}
		catch (\Throwable $e)
			{
			$this->redirect('permission/orphaned?deleted=0&error=exception');
			}
		}

	/**
	 * Normalizes submitted permission ids into a unique list of positive integers.
	 */
	private function normalizePermissionIds($raw): array
		{
		if (!is_array($raw))
			{
			return [];
			}

		$out = [];
		foreach ($raw as $value)
			{
			$id = (int) $value;
			if ($id > 0)
				{
				$out[] = $id;
				}
			}

		return array_values(array_unique($out));
		}
	}
?>