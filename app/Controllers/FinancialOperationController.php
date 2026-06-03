<?php

namespace App\Controllers;

use App\Core\TenantAwareController;
use App\Models\FinancialOperation;
use App\Models\FinancialOperationCategory;

class FinancialOperationController extends TenantAwareController
	{
	/**
	 * Lists financial operations with filters and pagination.
	 */
	public function index(): void
		{
		$this->requirePermission('financial_operation.index');

		$operation = new FinancialOperation();
		$category = new FinancialOperationCategory();
		$filters = [
			'id'                              => isset($_GET['id']) ? trim($_GET['id']) : '',
			'financial_operation_category_id' => isset($_GET['financial_operation_category_id']) ? trim($_GET['financial_operation_category_id']) : '',
			'total_amount'                    => isset($_GET['total_amount']) ? trim($_GET['total_amount']) : '',
		];

		$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
		if ($page < 1)
			{
			$page = 1;
			}

		$perPage = 10;
		$offset = ($page - 1) * $perPage;
		$operations = $operation->search($filters, $perPage, $offset);
		$total = $operation->count($filters);
		$totalPages = (int) ceil($total / $perPage);
		if ($totalPages < 1)
			{
			$totalPages = 1;
			}

		if ($this->wantsJson())
			{
			$this->json([
				'filters'    => $filters,
				'page'       => $page,
				'perPage'    => $perPage,
				'total'      => $total,
				'totalPages' => $totalPages,
				'data'       => $operations,
			]);
			}

		$this->view('financial_operations/index', [
			'operations' => $operations,
			'categories' => $category->getOptions(),
			'title'      => 'Lista de operações financeiras',
			'filters'    => $filters,
			'page'       => $page,
			'totalPages' => $totalPages,
			'total'      => $total,
		]);
		}

	/**
	 * Displays the annual spreadsheet of income, expenses, balances, and percentages.
	 */
	public function spreadsheet(): void
		{
		$this->requirePermission('financial_operation.index');

		$year = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');
		if ($year < 1900 || $year > 2100)
			{
			$year = (int) date('Y');
			}

		$operation = new FinancialOperation();
		$report = $operation->yearlySpreadsheet($year);

		if ($this->wantsJson())
			{
			$this->json($report);
			}

		$this->view('financial_operations/spreadsheet', [
			'title'  => 'Planilha anual financeira',
			'year'   => $year,
			'report' => $report,
		]);
		}

	/**
	 * Renders the financial operation creation form.
	 */
	public function create(): void
		{
		$this->requirePermission('financial_operation.create');

		$category = new FinancialOperationCategory();
		$this->view('financial_operations/create', [
			'title'      => 'Nova operação financeira',
			'categories' => $category->getOptions(),
		]);
		}

	/**
	 * Persists a new financial operation and redirects back to the listing.
	 */
	public function store(): void
		{
		$this->requirePermission('financial_operation.store');

		$operation = new FinancialOperation();
		$operation->create($_POST);
		$this->redirect('financial_operation');
		}

	/**
	 * Renders the financial operation edit form.
	 *
	 * @param mixed $id Operation identifier.
	 */
	public function edit($id): void
		{
		$this->requirePermission('financial_operation.edit');

		$operation = new FinancialOperation();
		$data = $operation->find($id);
		if (!$data)
			{
			$this->redirect('financial_operation');
			}

		$category = new FinancialOperationCategory();
		$this->view('financial_operations/edit', [
			'title'      => 'Editar operação financeira',
			'operation'  => $data,
			'categories' => $category->getOptions(),
		]);
		}

	/**
	 * Updates an existing financial operation.
	 *
	 * @param mixed $id Operation identifier.
	 */
	public function update($id): void
		{
		$this->requirePermission('financial_operation.update');

		$operation = new FinancialOperation();
		$operation->update($id, $_POST);
		$this->redirect('financial_operation');
		}

	/**
	 * Displays financial operation details and related payments.
	 *
	 * @param mixed $id Operation identifier.
	 */
	public function informations($id): void
		{
		$this->requirePermission('financial_operation.informations');

		$operation = new FinancialOperation();
		$data = $operation->findAllByFinancialOperationId($id);
		if (empty($data['financial_operation']))
			{
			$this->redirect('financial_operation');
			}

		$this->view('financial_operations/informations', [
			'title'     => 'Informações sobre a operação financeira',
			'operation' => $data['financial_operation'],
			'payments'  => $data['payments'],
		]);
		}
	}
?>
