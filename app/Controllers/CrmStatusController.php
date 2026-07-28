<?php

namespace App\Controllers;

use App\Core\TenantAwareController;
use App\Models\CrmStatus;

class CrmStatusController extends TenantAwareController
	{
	/**
	 * Lists CRM statuses for the current tenant.
	 */
	public function index(): void
		{
		$this->requirePermission('crm_status.index');

		$crmStatus = new CrmStatus();
		$filters = $this->readFilters();
		$page = $this->currentPage();
		$perPage = 10;
		$offset = ($page - 1) * $perPage;

		$statuses = $crmStatus->search($filters, $perPage, $offset);
		$total = $crmStatus->count($filters);
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

		$this->view('crm_statuses/index', [
			'title' => 'Status do CRM',
			'statuses' => $statuses,
			'filters' => $filters,
			'page' => $page,
			'totalPages' => $totalPages,
			'total' => $total,
		]);
		}

	/**
	 * Renders the CRM status creation form.
	 */
	public function create(): void
		{
		$this->requirePermission('crm_status.create');

		$this->view('crm_statuses/create', [
			'title' => 'Novo status do CRM',
		]);
		}

	/**
	 * Persists a CRM status.
	 */
	public function store(): void
		{
		$this->requirePermission('crm_status.store');
		$this->requirePost();

		$crmStatus = new CrmStatus();
		try
			{
			$result = $crmStatus->create($_POST, $this->currentUserId());
			$this->redirect('crm_status/informations/' . (int) ($result['crm_status_id'] ?? 0));
			}
		catch (\InvalidArgumentException $e)
			{
			$this->renderCreateError($e->getMessage(), 422);
			}
		catch (\Throwable $e)
			{
			error_log('CRM STATUS STORE ERROR: ' . $e->getMessage());
			$this->renderCreateError('Não foi possível salvar o status CRM agora.', 500);
			}
		}

	/**
	 * Renders the CRM status edit form.
	 *
	 * @param mixed $id CRM status id.
	 */
	public function edit($id): void
		{
		$this->requirePermission('crm_status.edit');

		$crmStatus = new CrmStatus();
		$status = $crmStatus->find($id);
		if (!$status)
			{
			$this->redirect('crm_status');
			}

		$this->view('crm_statuses/edit', [
			'title' => 'Editar status do CRM',
			'status' => $status,
		]);
		}

	/**
	 * Updates a CRM status.
	 *
	 * @param mixed $id CRM status id.
	 */
	public function update($id): void
		{
		$this->requirePermission('crm_status.update');
		$this->requirePost();

		$crmStatus = new CrmStatus();
		try
			{
			$crmStatus->update($id, $_POST, $this->currentUserId());
			$this->redirect('crm_status/informations/' . (int) $id);
			}
		catch (\InvalidArgumentException $e)
			{
			$status = $crmStatus->find($id);
			if ($this->wantsJson())
				{
				$this->json(['error' => $e->getMessage()], 422);
				}

			http_response_code(422);
			$this->view('crm_statuses/edit', [
				'title' => 'Editar status do CRM',
				'status' => $status ?: [],
				'formData' => $_POST,
				'formError' => $e->getMessage(),
			]);
			}
		catch (\Throwable $e)
			{
			error_log('CRM STATUS UPDATE ERROR: ' . $e->getMessage());
			if ($this->wantsJson())
				{
				$this->json(['error' => 'Não foi possível atualizar o status CRM agora.'], 500);
				}

			$this->redirect('crm_status');
			}
		}

	/**
	 * Displays CRM status details.
	 *
	 * @param mixed $id CRM status id.
	 */
	public function informations($id): void
		{
		$this->requirePermission('crm_status.informations');

		$crmStatus = new CrmStatus();
		$data = $crmStatus->findAllByCrmStatusId($id);
		if (empty($data['crm_status']))
			{
			$this->redirect('crm_status');
			}

		$this->view('crm_statuses/informations', [
			'title' => 'Informações do status CRM',
			'status' => $data['crm_status'],
		]);
		}

	/**
	 * Deactivates a CRM status.
	 *
	 * @param mixed $id CRM status id.
	 */
	public function delete($id): void
		{
		$this->requirePermission('crm_status.delete');
		$this->requirePost();

		$crmStatus = new CrmStatus();
		try
			{
			$crmStatus->delete($id, $this->currentUserId());
			}
		catch (\Throwable $e)
			{
			if ($this->wantsJson())
				{
				$this->json(['error' => $e->getMessage()], 422);
				}
			}

		$this->redirect('crm_status');
		}

	private function renderCreateError(string $message, int $status): void
		{
		if ($this->wantsJson())
			{
			$this->json(['error' => $message], $status);
			}

		http_response_code($status);
		$this->view('crm_statuses/create', [
			'title' => 'Novo status do CRM',
			'formData' => $_POST,
			'formError' => $message,
		]);
		}

	private function readFilters(): array
		{
		return [
			'id' => isset($_GET['id']) ? trim((string) $_GET['id']) : '',
			'name' => isset($_GET['name']) ? trim((string) $_GET['name']) : '',
			'bootstrap_class' => isset($_GET['bootstrap_class']) ? trim((string) $_GET['bootstrap_class']) : '',
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
