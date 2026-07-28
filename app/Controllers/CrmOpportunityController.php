<?php

namespace App\Controllers;

use App\Core\TenantAwareController;
use App\Core\TenantContext;
use App\Models\Crm;
use App\Models\CrmCatalog;
use App\Models\CrmOpportunity;

class CrmOpportunityController extends TenantAwareController
	{
	public function index(): void
		{
		$this->requirePermission('crm_opportunity.index');

		$opportunity = new CrmOpportunity();
		$filters = $this->readFilters();
		$order = $this->readOrder();
		$page = $this->currentPage();
		$perPage = 10;
		$offset = ($page - 1) * $perPage;
		$tenantId = $this->currentTenantId();

		$rows = $opportunity->search($filters, $perPage, $offset, $tenantId, $order['sort'], $order['direction']);
		$total = $opportunity->count($filters, $tenantId);
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

		$this->view('crm_opportunities/index', [
			'title' => 'Oportunidades CRM',
			'opportunities' => $rows,
			'filters' => $filters,
			'sources' => (new CrmCatalog('source'))->getOptions(false),
			'opportunityTypes' => (new CrmCatalog('opportunity_type'))->getOptions(false),
			'pipelineStages' => (new CrmCatalog('pipeline_stage'))->getOptions(false),
			'lossReasons' => (new CrmCatalog('loss_reason'))->getOptions(false),
			'owners' => (new Crm())->ownerOptions($tenantId),
			'sort' => $order['sort'],
			'direction' => $order['direction'],
			'page' => $page,
			'totalPages' => $totalPages,
			'total' => $total,
		]);
		}

	public function create($crmId = null): void
		{
		$this->requirePermission('crm_opportunity.create');
		$this->renderForm('crm_opportunities/create', 'Nova oportunidade CRM', [
			'formData' => ['tenant_person_id' => (int) ($crmId ?? 0)],
		]);
		}

	public function store(): void
		{
		$this->requirePermission('crm_opportunity.store');
		$this->requirePost();

		$opportunity = new CrmOpportunity();
		try
			{
			$result = $opportunity->create($_POST, $this->currentTenantId(), $this->currentUserId());
			$backToCrm = (string) ($_POST['back_to_crm'] ?? '') === '1';
			$this->redirect($backToCrm ? 'crm/informations/' . (int) ($result['tenant_person_id'] ?? 0) : 'crm_opportunity/informations/' . (int) ($result['crm_opportunity_id'] ?? 0));
			}
		catch (\InvalidArgumentException $e)
			{
			$this->renderForm('crm_opportunities/create', 'Nova oportunidade CRM', [
				'formData' => $_POST,
				'formError' => $e->getMessage(),
			], 422);
			}
		catch (\Throwable $e)
			{
			error_log('CRM OPPORTUNITY STORE ERROR: ' . $e->getMessage());
			$this->renderForm('crm_opportunities/create', 'Nova oportunidade CRM', [
				'formData' => $_POST,
				'formError' => 'Não foi possível salvar a oportunidade CRM agora.',
			], 500);
			}
		}

	public function edit($id): void
		{
		$this->requirePermission('crm_opportunity.edit');

		$opportunity = new CrmOpportunity();
		$data = $opportunity->findAllByCrmOpportunityId($id, $this->currentTenantId());
		if (empty($data['crm_opportunity']))
			{
			$this->redirect('crm_opportunity');
			}

		$this->renderForm('crm_opportunities/edit', 'Editar oportunidade CRM', [
			'crmOpportunity' => $data['crm_opportunity'],
		]);
		}

	public function update($id): void
		{
		$this->requirePermission('crm_opportunity.update');
		$this->requirePost();

		$opportunity = new CrmOpportunity();
		try
			{
			$opportunity->update($id, $_POST, $this->currentTenantId(), $this->currentUserId());
			$this->redirect('crm_opportunity/informations/' . (int) $id);
			}
		catch (\InvalidArgumentException $e)
			{
			$data = $opportunity->findAllByCrmOpportunityId($id, $this->currentTenantId());
			$this->renderForm('crm_opportunities/edit', 'Editar oportunidade CRM', [
				'crmOpportunity' => $data['crm_opportunity'] ?? [],
				'formData' => $_POST,
				'formError' => $e->getMessage(),
			], 422);
			}
		catch (\Throwable $e)
			{
			error_log('CRM OPPORTUNITY UPDATE ERROR: ' . $e->getMessage());
			$this->redirect('crm_opportunity');
			}
		}

	public function informations($id): void
		{
		$this->requirePermission('crm_opportunity.informations');

		$opportunity = new CrmOpportunity();
		$data = $opportunity->findAllByCrmOpportunityId($id, $this->currentTenantId());
		if (empty($data['crm_opportunity']))
			{
			$this->redirect('crm_opportunity');
			}

		$this->view('crm_opportunities/informations', [
			'title' => 'Informações da oportunidade CRM',
			'crmOpportunity' => $data['crm_opportunity'],
			'pipelineStages' => (new CrmCatalog('pipeline_stage'))->getOptions(true, (int) ($data['crm_opportunity']['pipeline_stage_id'] ?? 0)),
		]);
		}

	public function moveStage($id): void
		{
		$this->requirePermission('crm_opportunity.moveStage');
		$this->requirePost();

		$opportunity = new CrmOpportunity();
		try
			{
			$opportunity->moveStage($id, (int) ($_POST['pipeline_stage_id'] ?? 0), $this->currentTenantId(), $this->currentUserId());
			}
		catch (\Throwable $e)
			{
			if ($this->wantsJson())
				{
				$this->json(['error' => $e->getMessage()], 422);
				}
			}

		$redirectTo = trim((string) ($_POST['redirect_to'] ?? ''));
		$this->redirect($redirectTo !== '' ? $redirectTo : 'crm_opportunity/informations/' . (int) $id);
		}

	public function delete($id): void
		{
		$this->requirePermission('crm_opportunity.delete');
		$this->requirePost();

		$opportunity = new CrmOpportunity();
		try
			{
			$opportunity->delete($id, $this->currentTenantId(), $this->currentUserId());
			}
		catch (\Throwable $e)
			{
			if ($this->wantsJson())
				{
				$this->json(['error' => $e->getMessage()], 422);
				}
			}

		$this->redirect('crm_opportunity');
		}

	public function kanban(): void
		{
		$this->requirePermission('crm_opportunity.kanban');

		$this->view('crm_opportunities/kanban', [
			'title' => 'Funil de oportunidades',
			'columns' => (new CrmOpportunity())->kanban($this->currentTenantId()),
		]);
		}

	public function report(): void
		{
		$this->requirePermission('crm_opportunity.report');

		$this->view('crm_opportunities/report', [
			'title' => 'Relatório comercial CRM',
			'report' => (new CrmOpportunity())->report($this->currentTenantId()),
		]);
		}

	private function renderForm(string $view, string $title, array $data = [], int $status = 200): void
		{
		if ($this->wantsJson() && !empty($data['formError']))
			{
			$this->json(['error' => $data['formError']], $status);
			}

		http_response_code($status);
		$opportunity = new CrmOpportunity();
		$tenantId = $this->currentTenantId();
		$current = (isset($data['crmOpportunity']) && is_array($data['crmOpportunity'])) ? $data['crmOpportunity'] : [];
		$formData = (isset($data['formData']) && is_array($data['formData'])) ? $data['formData'] : [];
		$selectedCrmId = (int) ($formData['tenant_person_id'] ?? $formData['crm_id'] ?? $current['tenant_person_id'] ?? 0);

		$this->view($view, array_merge($data, [
			'title' => $title,
			'crmOptions' => $opportunity->crmOptions($tenantId, $selectedCrmId),
			'sources' => (new CrmCatalog('source'))->getOptions(true, (int) ($current['crm_source_id'] ?? 0)),
			'opportunityTypes' => (new CrmCatalog('opportunity_type'))->getOptions(true, (int) ($current['opportunity_type_id'] ?? 0)),
			'pipelineStages' => (new CrmCatalog('pipeline_stage'))->getOptions(true, (int) ($current['pipeline_stage_id'] ?? 0)),
			'lossReasons' => (new CrmCatalog('loss_reason'))->getOptions(true, (int) ($current['loss_reason_id'] ?? 0)),
			'owners' => (new Crm())->ownerOptions($tenantId, (int) ($current['responsible_user_id'] ?? 0)),
		]));
		}

	private function readFilters(): array
		{
		return [
			'id' => isset($_GET['id']) ? trim((string) $_GET['id']) : '',
			'tenant_person_id' => isset($_GET['tenant_person_id']) ? trim((string) $_GET['tenant_person_id']) : '',
			'person_name' => isset($_GET['person_name']) ? trim((string) $_GET['person_name']) : '',
			'crm_source_id' => isset($_GET['crm_source_id']) ? trim((string) $_GET['crm_source_id']) : '',
			'opportunity_type_id' => isset($_GET['opportunity_type_id']) ? trim((string) $_GET['opportunity_type_id']) : '',
			'pipeline_stage_id' => isset($_GET['pipeline_stage_id']) ? trim((string) $_GET['pipeline_stage_id']) : '',
			'loss_reason_id' => isset($_GET['loss_reason_id']) ? trim((string) $_GET['loss_reason_id']) : '',
			'responsible_user_id' => isset($_GET['responsible_user_id']) ? trim((string) $_GET['responsible_user_id']) : '',
			'expected_from' => isset($_GET['expected_from']) ? trim((string) $_GET['expected_from']) : '',
			'expected_to' => isset($_GET['expected_to']) ? trim((string) $_GET['expected_to']) : '',
			'result' => isset($_GET['result']) ? trim((string) $_GET['result']) : '',
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
