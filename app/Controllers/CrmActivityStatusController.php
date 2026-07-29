<?php

namespace App\Controllers;

use App\Core\TenantAwareController;
use App\Models\CrmActivityStatus;

class CrmActivityStatusController extends TenantAwareController
	{
	public function index(): void
		{
		$this->requirePermission('crm_activity_status.index');

		$activityStatus = new CrmActivityStatus();
		$filters = $this->readFilters();
		$page = $this->currentPage();
		$perPage = 10;
		$offset = ($page - 1) * $perPage;
		$statuses = $activityStatus->search($filters, $perPage, $offset);
		$total = $activityStatus->count($filters);
		$totalPages = max(1, (int) ceil($total / $perPage));

		if ($this->wantsJson())
			{
			$this->json([
				'filters' => $filters,
				'page' => $page,
				'perPage' => $perPage,
				'total' => $total,
				'totalPages' => $totalPages,
				'data' => $statuses,
			]);
			}

		$this->view('crm_activity_statuses/index', [
			'title' => 'Status de atividade CRM',
			'activityStatuses' => $statuses,
			'filters' => $filters,
			'page' => $page,
			'totalPages' => $totalPages,
			'total' => $total,
		]);
		}

	public function create(): void
		{
		$this->requirePermission('crm_activity_status.create');

		$this->view('crm_activity_statuses/create', [
			'title' => 'Novo status de atividade CRM',
		]);
		}

	public function store(): void
		{
		$this->requirePermission('crm_activity_status.store');
		$this->requirePost();

		$activityStatus = new CrmActivityStatus();
			try
				{
				$result = $activityStatus->create($_POST, $this->currentUserId());
				$this->redirectWithFlash('crm_activity_status/informations/' . (int) ($result['crm_activity_status_id'] ?? 0), 'success', 'Status de atividade CRM salvo com sucesso.');
				}
		catch (\InvalidArgumentException $e)
			{
			$this->renderCreateError($e->getMessage(), 422);
			}
		catch (\Throwable $e)
			{
			error_log('CRM ACTIVITY STATUS STORE ERROR: ' . $e->getMessage());
			$this->renderCreateError('Não foi possível salvar o status de atividade CRM agora.', 500);
			}
		}

	public function edit($id): void
		{
		$this->requirePermission('crm_activity_status.edit');

		$activityStatus = new CrmActivityStatus();
		$status = $activityStatus->find($id);
		if (!$status)
			{
			$this->redirectWithFlash('crm_activity_status', 'warning', 'Status de atividade CRM não encontrado.');
			}

		$this->view('crm_activity_statuses/edit', [
			'title' => 'Editar status de atividade CRM',
			'activityStatus' => $status,
		]);
		}

	public function update($id): void
		{
		$this->requirePermission('crm_activity_status.update');
		$this->requirePost();

		$activityStatus = new CrmActivityStatus();
			try
				{
				$activityStatus->update($id, $_POST, $this->currentUserId());
				$this->redirectWithFlash('crm_activity_status/informations/' . (int) $id, 'success', 'Status de atividade CRM atualizado com sucesso.');
				}
		catch (\InvalidArgumentException $e)
			{
			$status = $activityStatus->find($id);
			if ($this->wantsJson())
				{
				$this->json(['error' => $e->getMessage()], 422);
				}

			http_response_code(422);
			$this->view('crm_activity_statuses/edit', [
				'title' => 'Editar status de atividade CRM',
				'activityStatus' => $status ?: [],
				'formData' => $_POST,
				'formError' => $e->getMessage(),
			]);
			}
			catch (\Throwable $e)
				{
				error_log('CRM ACTIVITY STATUS UPDATE ERROR: ' . $e->getMessage());
				if ($this->wantsJson())
					{
					$this->json(['error' => 'Não foi possível atualizar o status de atividade CRM agora.'], 500);
					}

				$this->redirectWithFlash('crm_activity_status', 'danger', 'Não foi possível atualizar o status de atividade CRM agora.');
				}
		}

	public function informations($id): void
		{
		$this->requirePermission('crm_activity_status.informations');

		$activityStatus = new CrmActivityStatus();
		$data = $activityStatus->findAllByCrmActivityStatusId($id);
		if (empty($data['crm_activity_status']))
			{
			$this->redirectWithFlash('crm_activity_status', 'warning', 'Status de atividade CRM não encontrado.');
			}

		$this->view('crm_activity_statuses/informations', [
			'title' => 'Informações do status de atividade CRM',
			'activityStatus' => $data['crm_activity_status'],
		]);
		}

	public function delete($id): void
		{
		$this->requirePermission('crm_activity_status.delete');
		$this->requirePost();

		$activityStatus = new CrmActivityStatus();
			try
				{
				$activityStatus->delete($id, $this->currentUserId());
				}
		catch (\Throwable $e)
			{
			if ($this->wantsJson())
				{
				$this->json(['error' => $e->getMessage()], 422);
				}

			$this->redirectWithFlash('crm_activity_status', 'danger', 'Não foi possível excluir o status de atividade CRM agora.');
			}

		$this->redirectWithFlash('crm_activity_status', 'success', 'Status de atividade CRM excluído com sucesso.');
		}

	private function renderCreateError(string $message, int $status): void
		{
		if ($this->wantsJson())
			{
			$this->json(['error' => $message], $status);
			}

		http_response_code($status);
		$this->view('crm_activity_statuses/create', [
			'title' => 'Novo status de atividade CRM',
			'formData' => $_POST,
			'formError' => $message,
		]);
		}

	private function readFilters(): array
		{
		return [
			'id' => isset($_GET['id']) ? trim((string) $_GET['id']) : '',
			'name' => isset($_GET['name']) ? trim((string) $_GET['name']) : '',
			'is_final' => isset($_GET['is_final']) ? trim((string) $_GET['is_final']) : '',
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
