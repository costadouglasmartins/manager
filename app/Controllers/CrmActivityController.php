<?php

namespace App\Controllers;

use App\Core\TenantAwareController;
use App\Core\TenantContext;
use App\Models\Crm;
use App\Models\CrmActivity;
use App\Models\CrmActivityStatus;
use App\Models\CrmActivityType;

class CrmActivityController extends TenantAwareController
	{
	public function index(): void
		{
		$this->requirePermission('crm_activity.index');

		$activity = new CrmActivity();
		$filters = $this->readFilters();
		$order = $this->readOrder();
		$page = $this->currentPage();
		$perPage = 10;
		$offset = ($page - 1) * $perPage;
		$tenantId = $this->currentTenantId();

		$rows = $activity->search($filters, $perPage, $offset, $tenantId, $order['sort'], $order['direction']);
		$total = $activity->count($filters, $tenantId);
		$totalPages = max(1, (int) ceil($total / $perPage));

		if ($this->wantsJson())
			{
			$this->json([
				'filters' => $filters,
				'page' => $page,
				'perPage' => $perPage,
				'total' => $total,
				'totalPages' => $totalPages,
				'data' => $rows,
			]);
			}

		$this->view('crm_activities/index', [
			'title' => 'Atividades CRM',
			'activities' => $rows,
			'filters' => $filters,
			'activityTypes' => (new CrmActivityType())->getOptions(false),
			'activityStatuses' => (new CrmActivityStatus())->getOptions(false),
			'owners' => (new Crm())->ownerOptions($tenantId),
			'activityScopes' => CrmActivity::activityScopes(),
			'sort' => $order['sort'],
			'direction' => $order['direction'],
			'page' => $page,
			'totalPages' => $totalPages,
			'total' => $total,
		]);
		}

	public function create($crmId = null): void
		{
		$this->requirePermission('crm_activity.create');
		$this->renderForm('crm_activities/create', 'Nova atividade CRM', [
			'formData' => ['tenant_person_id' => (int) ($crmId ?? 0)],
		]);
		}

	public function store(): void
		{
		$this->requirePermission('crm_activity.store');
		$this->requirePost();

		$activity = new CrmActivity();
			try
				{
				$result = $activity->create($_POST, $this->currentTenantId(), $this->currentUserId());
				$backToCrm = (string) ($_POST['back_to_crm'] ?? '') === '1';
				$this->redirectWithFlash(
					$backToCrm ? 'crm/informations/' . (int) ($result['tenant_person_id'] ?? 0) : 'crm_activity/informations/' . (int) ($result['crm_activity_id'] ?? 0),
					'success',
					'Atividade CRM salva com sucesso.'
				);
				}
		catch (\InvalidArgumentException $e)
			{
			$this->renderForm('crm_activities/create', 'Nova atividade CRM', [
				'formData' => $_POST,
				'formError' => $e->getMessage(),
			], 422);
			}
		catch (\Throwable $e)
			{
			error_log('CRM ACTIVITY STORE ERROR: ' . $e->getMessage());
			$this->renderForm('crm_activities/create', 'Nova atividade CRM', [
				'formData' => $_POST,
				'formError' => 'Não foi possível salvar a atividade CRM agora.',
			], 500);
			}
		}

	public function edit($id): void
		{
		$this->requirePermission('crm_activity.edit');

		$activity = new CrmActivity();
		$data = $activity->findAllByCrmActivityId($id, $this->currentTenantId());
		if (empty($data['crm_activity']))
			{
			$this->redirectWithFlash('crm_activity', 'warning', 'Atividade CRM não encontrada.');
			}

		$this->renderForm('crm_activities/edit', 'Editar atividade CRM', [
			'crmActivity' => $data['crm_activity'],
		]);
		}

	public function update($id): void
		{
		$this->requirePermission('crm_activity.update');
		$this->requirePost();

		$activity = new CrmActivity();
		try
			{
			$activity->update($id, $_POST, $this->currentTenantId(), $this->currentUserId());
			$this->redirectWithFlash(
				'crm_activity/informations/' . (int) $id,
				'success',
				'Atividade CRM atualizada com sucesso.'
			);
			}
		catch (\InvalidArgumentException $e)
			{
			$data = $activity->findAllByCrmActivityId($id, $this->currentTenantId());
			$this->renderForm('crm_activities/edit', 'Editar atividade CRM', [
				'crmActivity' => $data['crm_activity'] ?? [],
				'formData' => $_POST,
				'formError' => $e->getMessage(),
			], 422);
			}
			catch (\Throwable $e)
				{
				error_log('CRM ACTIVITY UPDATE ERROR: ' . $e->getMessage());
				$this->redirectWithFlash('crm_activity', 'danger', 'Não foi possível atualizar a atividade CRM agora.');
				}
		}

	public function informations($id): void
		{
		$this->requirePermission('crm_activity.informations');

		$activity = new CrmActivity();
		$data = $activity->findAllByCrmActivityId($id, $this->currentTenantId());
		if (empty($data['crm_activity']))
			{
			$this->redirectWithFlash('crm_activity', 'warning', 'Atividade CRM não encontrada.');
			}

		$this->view('crm_activities/informations', [
			'title' => 'Informações da atividade CRM',
			'crmActivity' => $data['crm_activity'],
		]);
		}

	public function complete($id): void
		{
		$this->requirePermission('crm_activity.complete');
		$this->requirePost();
		$this->runStateAction($id, 'complete');
		}

	public function cancel($id): void
		{
		$this->requirePermission('crm_activity.cancel');
		$this->requirePost();
		$this->runStateAction($id, 'cancel');
		}

	public function delete($id): void
		{
		$this->requirePermission('crm_activity.delete');
		$this->requirePost();

		$activity = new CrmActivity();
			try
				{
				$activity->delete($id, $this->currentTenantId(), $this->currentUserId());
				}
		catch (\Throwable $e)
			{
			if ($this->wantsJson())
				{
				$this->json(['error' => $e->getMessage()], 422);
				}

			$this->redirectWithFlash('crm_activity', 'danger', 'Não foi possível excluir a atividade CRM agora.');
			}

		$this->redirectWithFlash('crm_activity', 'success', 'Atividade CRM excluída com sucesso.');
		}

	private function runStateAction($id, string $action): void
		{
		$activity = new CrmActivity();
		try
			{
			if ($action === 'complete')
				{
				$activity->complete($id, $this->currentTenantId(), $this->currentUserId());
				}
			else
				{
				$activity->cancel($id, $this->currentTenantId(), $this->currentUserId());
				}
			}
		catch (\Throwable $e)
			{
			if ($this->wantsJson())
				{
				$this->json(['error' => $e->getMessage()], 422);
				}

			$backToCrm = (string) ($_POST['back_to_crm'] ?? '') === '1';
			$crmId = (int) ($_POST['tenant_person_id'] ?? 0);
			$this->redirectWithFlash(
				$backToCrm && $crmId > 0 ? 'crm/informations/' . $crmId : 'crm_activity',
				'danger',
				$action === 'complete'
					? 'Não foi possível concluir a atividade CRM agora.'
					: 'Não foi possível cancelar a atividade CRM agora.'
			);
			}

		$backToCrm = (string) ($_POST['back_to_crm'] ?? '') === '1';
		$crmId = (int) ($_POST['tenant_person_id'] ?? 0);
		$this->redirectWithFlash(
			$backToCrm && $crmId > 0 ? 'crm/informations/' . $crmId : 'crm_activity',
			'success',
			$action === 'complete' ? 'Atividade CRM concluída com sucesso.' : 'Atividade CRM cancelada com sucesso.'
		);
		}

	private function renderForm(string $view, string $title, array $data = [], int $status = 200): void
		{
		if ($this->wantsJson() && !empty($data['formError']))
			{
			$this->json(['error' => $data['formError']], $status);
			}

		http_response_code($status);
		$activity = new CrmActivity();
		$tenantId = $this->currentTenantId();
		$current = (isset($data['crmActivity']) && is_array($data['crmActivity'])) ? $data['crmActivity'] : [];
		$formData = (isset($data['formData']) && is_array($data['formData'])) ? $data['formData'] : [];
		$selectedCrmId = (int) ($formData['tenant_person_id'] ?? $formData['crm_id'] ?? $current['tenant_person_id'] ?? 0);

		$this->view($view, array_merge($data, [
			'title' => $title,
			'crmOptions' => $activity->crmOptions($tenantId, $selectedCrmId),
			'activityTypes' => (new CrmActivityType())->getOptions(true, (int) ($current['activity_type_id'] ?? 0)),
			'activityStatuses' => (new CrmActivityStatus())->getOptions(true, (int) ($current['activity_status_id'] ?? 0)),
			'owners' => (new Crm())->ownerOptions($tenantId, (int) ($current['responsible_user_id'] ?? 0)),
		]));
		}

	private function readFilters(): array
		{
		return [
			'id' => isset($_GET['id']) ? trim((string) $_GET['id']) : '',
			'tenant_person_id' => isset($_GET['tenant_person_id']) ? trim((string) $_GET['tenant_person_id']) : '',
			'person_name' => isset($_GET['person_name']) ? trim((string) $_GET['person_name']) : '',
			'activity_type_id' => isset($_GET['activity_type_id']) ? trim((string) $_GET['activity_type_id']) : '',
			'activity_status_id' => isset($_GET['activity_status_id']) ? trim((string) $_GET['activity_status_id']) : '',
			'responsible_user_id' => isset($_GET['responsible_user_id']) ? trim((string) $_GET['responsible_user_id']) : '',
			'scheduled_from' => isset($_GET['scheduled_from']) ? trim((string) $_GET['scheduled_from']) : '',
			'scheduled_to' => isset($_GET['scheduled_to']) ? trim((string) $_GET['scheduled_to']) : '',
			'activity_scope' => isset($_GET['activity_scope']) ? trim((string) $_GET['activity_scope']) : '',
			'active' => isset($_GET['active']) ? trim((string) $_GET['active']) : '1',
		];
		}

	private function readOrder(): array
		{
		$sort = isset($_GET['sort']) ? trim((string) $_GET['sort']) : 'scheduled_at';
		$direction = isset($_GET['direction']) ? trim((string) $_GET['direction']) : 'asc';

		return [
			'sort' => $sort,
			'direction' => strtolower($direction) === 'desc' ? 'desc' : 'asc',
		];
		}

	private function currentPage(): int
		{
		return max(1, (int) ($_GET['page'] ?? 1));
		}

	private function currentUserId(): int
		{
		return (int) ($_SESSION['manager']['user']['id'] ?? 0);
		}

	private function currentTenantId(): int
		{
		$tenant = TenantContext::tenant();
		return (int) ($tenant['id'] ?? 0);
		}

	private function requirePost(): void
		{
		if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST')
			{
			return;
			}

		if ($this->wantsJson())
			{
			$this->json(['error' => 'method_not_allowed'], 405);
			}

		http_response_code(405);
		echo 'Método não permitido.';
		exit;
		}
	}
?>
