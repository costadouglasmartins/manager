<?php

namespace App\Controllers;

use App\Core\TenantAwareController;
use App\Models\CrmActivityType;

class CrmActivityTypeController extends TenantAwareController
	{
	public function index(): void
		{
		$this->requirePermission('crm_activity_type.index');

		$activityType = new CrmActivityType();
		$filters = $this->readFilters();
		$page = $this->currentPage();
		$perPage = 10;
		$offset = ($page - 1) * $perPage;
		$types = $activityType->search($filters, $perPage, $offset);
		$total = $activityType->count($filters);
		$totalPages = max(1, (int) ceil($total / $perPage));

		if ($this->wantsJson())
			{
			$this->json([
				'filters' => $filters,
				'page' => $page,
				'perPage' => $perPage,
				'total' => $total,
				'totalPages' => $totalPages,
				'data' => $types,
			]);
			}

		$this->view('crm_activity_types/index', [
			'title' => 'Tipos de atividade CRM',
			'activityTypes' => $types,
			'filters' => $filters,
			'page' => $page,
			'totalPages' => $totalPages,
			'total' => $total,
		]);
		}

	public function create(): void
		{
		$this->requirePermission('crm_activity_type.create');

		$this->view('crm_activity_types/create', [
			'title' => 'Novo tipo de atividade CRM',
		]);
		}

	public function store(): void
		{
		$this->requirePermission('crm_activity_type.store');
		$this->requirePost();

		$activityType = new CrmActivityType();
		try
			{
			$result = $activityType->create($_POST, $this->currentUserId());
			$this->redirect('crm_activity_type/informations/' . (int) ($result['crm_activity_type_id'] ?? 0));
			}
		catch (\InvalidArgumentException $e)
			{
			$this->renderCreateError($e->getMessage(), 422);
			}
		catch (\Throwable $e)
			{
			error_log('CRM ACTIVITY TYPE STORE ERROR: ' . $e->getMessage());
			$this->renderCreateError('Não foi possível salvar o tipo de atividade CRM agora.', 500);
			}
		}

	public function edit($id): void
		{
		$this->requirePermission('crm_activity_type.edit');

		$activityType = new CrmActivityType();
		$type = $activityType->find($id);
		if (!$type)
			{
			$this->redirect('crm_activity_type');
			}

		$this->view('crm_activity_types/edit', [
			'title' => 'Editar tipo de atividade CRM',
			'activityType' => $type,
		]);
		}

	public function update($id): void
		{
		$this->requirePermission('crm_activity_type.update');
		$this->requirePost();

		$activityType = new CrmActivityType();
		try
			{
			$activityType->update($id, $_POST, $this->currentUserId());
			$this->redirect('crm_activity_type/informations/' . (int) $id);
			}
		catch (\InvalidArgumentException $e)
			{
			$type = $activityType->find($id);
			if ($this->wantsJson())
				{
				$this->json(['error' => $e->getMessage()], 422);
				}

			http_response_code(422);
			$this->view('crm_activity_types/edit', [
				'title' => 'Editar tipo de atividade CRM',
				'activityType' => $type ?: [],
				'formData' => $_POST,
				'formError' => $e->getMessage(),
			]);
			}
		catch (\Throwable $e)
			{
			error_log('CRM ACTIVITY TYPE UPDATE ERROR: ' . $e->getMessage());
			if ($this->wantsJson())
				{
				$this->json(['error' => 'Não foi possível atualizar o tipo de atividade CRM agora.'], 500);
				}

			$this->redirect('crm_activity_type');
			}
		}

	public function informations($id): void
		{
		$this->requirePermission('crm_activity_type.informations');

		$activityType = new CrmActivityType();
		$data = $activityType->findAllByCrmActivityTypeId($id);
		if (empty($data['crm_activity_type']))
			{
			$this->redirect('crm_activity_type');
			}

		$this->view('crm_activity_types/informations', [
			'title' => 'Informações do tipo de atividade CRM',
			'activityType' => $data['crm_activity_type'],
		]);
		}

	public function delete($id): void
		{
		$this->requirePermission('crm_activity_type.delete');
		$this->requirePost();

		$activityType = new CrmActivityType();
		try
			{
			$activityType->delete($id, $this->currentUserId());
			}
		catch (\Throwable $e)
			{
			if ($this->wantsJson())
				{
				$this->json(['error' => $e->getMessage()], 422);
				}
			}

		$this->redirect('crm_activity_type');
		}

	private function renderCreateError(string $message, int $status): void
		{
		if ($this->wantsJson())
			{
			$this->json(['error' => $message], $status);
			}

		http_response_code($status);
		$this->view('crm_activity_types/create', [
			'title' => 'Novo tipo de atividade CRM',
			'formData' => $_POST,
			'formError' => $message,
		]);
		}

	private function readFilters(): array
		{
		return [
			'id' => isset($_GET['id']) ? trim((string) $_GET['id']) : '',
			'name' => isset($_GET['name']) ? trim((string) $_GET['name']) : '',
			'icon' => isset($_GET['icon']) ? trim((string) $_GET['icon']) : '',
			'active' => isset($_GET['active']) ? trim((string) $_GET['active']) : '',
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
