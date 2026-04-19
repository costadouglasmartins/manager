<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Authorization;
use App\Core\TenantContext;
use App\Models\Permission;

class PermissionController extends Controller
	{
	private function currentTenantId(): int
		{
		$this->requireAuth();
		$this->requireTenant();
		$this->requirePermission('permission.manage');

		$tenant = TenantContext::tenant();
		return (int) ($tenant['id'] ?? 0);
		}

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
