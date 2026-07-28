<?php

namespace App\Controllers;

use App\Core\TenantAwareController;
use App\Core\TenantContext;
use App\Models\Crm;
use App\Models\CrmActivity;

class CrmController extends TenantAwareController
	{
	/**
	 * Lists tenant-scoped CRM records.
	 */
	public function index(): void
		{
		$this->requirePermission('crm.index');

		$crm = new Crm();
		$filters = $this->readFilters();
		$order = $this->readOrder();
		$page = $this->currentPage();
		$perPage = 10;
		$offset = ($page - 1) * $perPage;
		$tenantId = $this->currentTenantId();

		$rows = $crm->search($filters, $perPage, $offset, $tenantId, $order['sort'], $order['direction']);
		$total = $crm->count($filters, $tenantId);
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

		$this->view('crm/index', [
			'title' => 'CRM',
			'crmPeople' => $rows,
			'filters' => $filters,
			'statuses' => $crm->statusOptions($tenantId),
			'personTypes' => Crm::personTypes(),
			'owners' => $crm->ownerOptions($tenantId),
			'activityScopes' => CrmActivity::activityScopes(),
			'sort' => $order['sort'],
			'direction' => $order['direction'],
			'page' => $page,
			'totalPages' => $totalPages,
			'total' => $total,
		]);
		}

	public function exportPdf(): void
		{
		parent::exportPdf();
		}

	public function exportExcel(): void
		{
		parent::exportExcel();
		}

	/**
	 * Displays a simple CRM dashboard for the selected tenant.
	 */
	public function dashboard(): void
		{
		$this->requirePermission('crm.dashboard');

		$activity = new CrmActivity();
		$this->view('crm/dashboard', [
			'title' => 'Painel CRM',
			'dashboard' => $activity->dashboard($this->currentTenantId()),
		]);
		}

	/**
	 * Renders the CRM creation form.
	 */
	public function create(): void
		{
		$this->requirePermission('crm.create');

		$crm = new Crm();
		$this->view('crm/create', [
			'title' => 'Novo registro CRM',
			'statuses' => $crm->statusOptions($this->currentTenantId()),
			'personTypes' => Crm::personTypes(),
			'owners' => $crm->ownerOptions($this->currentTenantId()),
		]);
		}

	/**
	 * Persists a CRM record.
	 */
	public function store(): void
		{
		$this->requirePermission('crm.store');
		$this->requirePost();

		$crm = new Crm();
		try
			{
			$result = $crm->create($_POST, $this->currentTenantId(), $this->currentUserId());
			$this->redirect('crm/informations/' . (int) ($result['crm_id'] ?? 0));
			}
		catch (\InvalidArgumentException $e)
			{
			$this->renderCreateError($crm, $e->getMessage(), 422);
			}
		catch (\Throwable $e)
			{
			error_log('CRM STORE ERROR: ' . $e->getMessage());
			$this->renderCreateError($crm, 'Não foi possível salvar o registro CRM agora.', 500);
			}
		}

	/**
	 * Renders the CRM edit form.
	 *
	 * @param mixed $id CRM record id.
	 */
	public function edit($id): void
		{
		$this->requirePermission('crm.edit');

		$crm = new Crm();
		$activity = new CrmActivity();
		$data = $crm->findAllByCrmId($id, $this->currentTenantId());
		if (empty($data['crm']))
			{
			$this->redirect('crm');
			}

		$this->view('crm/edit', [
			'title' => 'Editar registro CRM',
			'crmRecord' => $data['crm'],
			'statuses' => $crm->statusOptions($this->currentTenantId(), (int) ($data['crm']['crm_status_id'] ?? 0)),
			'owners' => $crm->ownerOptions($this->currentTenantId(), (int) ($data['crm']['owner_user_id'] ?? 0)),
		]);
		}

	/**
	 * Updates CRM metadata.
	 *
	 * @param mixed $id CRM record id.
	 */
	public function update($id): void
		{
		$this->requirePermission('crm.update');
		$this->requirePost();

		$crm = new Crm();
		try
			{
			$crm->update($id, $_POST, $this->currentTenantId(), $this->currentUserId());
			$this->redirect('crm/informations/' . (int) $id);
			}
		catch (\InvalidArgumentException $e)
			{
			$data = $crm->findAllByCrmId($id, $this->currentTenantId());
			if ($this->wantsJson())
				{
				$this->json(['error' => $e->getMessage()], 422);
				}

			http_response_code(422);
			$this->view('crm/edit', [
				'title' => 'Editar registro CRM',
				'crmRecord' => $data['crm'] ?? [],
				'statuses' => $crm->statusOptions($this->currentTenantId(), (int) ($data['crm']['crm_status_id'] ?? 0)),
				'owners' => $crm->ownerOptions($this->currentTenantId(), (int) ($data['crm']['owner_user_id'] ?? 0)),
				'formData' => $_POST,
				'formError' => $e->getMessage(),
			]);
			}
		catch (\Throwable $e)
			{
			error_log('CRM UPDATE ERROR: ' . $e->getMessage());
			if ($this->wantsJson())
				{
				$this->json(['error' => 'Não foi possível atualizar o registro CRM agora.'], 500);
				}

			$this->redirect('crm');
			}
		}

	/**
	 * Displays CRM details.
	 *
	 * @param mixed $id CRM record id.
	 */
	public function informations($id): void
		{
		$this->requirePermission('crm.informations');

		$crm = new Crm();
		$data = $crm->findAllByCrmId($id, $this->currentTenantId());
		if (empty($data['crm']))
			{
			$this->redirect('crm');
			}

		$this->view('crm/informations', [
			'title' => 'Informações CRM',
			'crmRecord' => $data['crm'],
			'emails' => $data['emails'],
			'phones' => $data['phones'],
			'addresses' => $data['addresses'],
			'activities' => $activity->timelineByCrmId((int) $id, $this->currentTenantId()),
		]);
		}

	/**
	 * Deactivates a CRM link.
	 *
	 * @param mixed $id CRM record id.
	 */
	public function delete($id): void
		{
		$this->requirePermission('crm.delete');
		$this->requirePost();

		$crm = new Crm();
		try
			{
			$crm->delete($id, $this->currentTenantId(), $this->currentUserId());
			}
		catch (\Throwable $e)
			{
			if ($this->wantsJson())
				{
				$this->json(['error' => $e->getMessage()], 422);
				}
			}

		$this->redirect('crm');
		}

	private function renderCreateError(Crm $crm, string $message, int $status): void
		{
		if ($this->wantsJson())
			{
			$this->json(['error' => $message], $status);
			}

		http_response_code($status);
		$this->view('crm/create', [
			'title' => 'Novo registro CRM',
			'statuses' => $crm->statusOptions($this->currentTenantId()),
			'personTypes' => Crm::personTypes(),
			'owners' => $crm->ownerOptions($this->currentTenantId()),
			'formData' => $_POST,
			'formError' => $message,
		]);
		}

	private function readFilters(): array
		{
		return [
			'id' => isset($_GET['id']) ? trim((string) $_GET['id']) : '',
			'name' => isset($_GET['name']) ? trim((string) $_GET['name']) : '',
			'document' => isset($_GET['document']) ? trim((string) $_GET['document']) : '',
			'type' => isset($_GET['type']) ? trim((string) $_GET['type']) : '',
			'crm_status_id' => isset($_GET['crm_status_id']) ? trim((string) $_GET['crm_status_id']) : '',
			'owner_user_id' => isset($_GET['owner_user_id']) ? trim((string) $_GET['owner_user_id']) : '',
			'source' => isset($_GET['source']) ? trim((string) $_GET['source']) : '',
			'next_activity_from' => isset($_GET['next_activity_from']) ? trim((string) $_GET['next_activity_from']) : '',
			'next_activity_to' => isset($_GET['next_activity_to']) ? trim((string) $_GET['next_activity_to']) : '',
			'activity_scope' => isset($_GET['activity_scope']) ? trim((string) $_GET['activity_scope']) : '',
			'active' => isset($_GET['active']) ? trim((string) $_GET['active']) : '1',
		];
		}

	private function readOrder(): array
		{
		$sort = isset($_GET['sort']) ? trim((string) $_GET['sort']) : 'modified';
		$direction = isset($_GET['direction']) ? trim((string) $_GET['direction']) : 'desc';

		return [
			'sort' => $sort,
			'direction' => strtolower($direction) === 'asc' ? 'asc' : 'desc',
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
