<?php

namespace App\Controllers;

use App\Core\TenantAwareController;
use App\Models\FinancialOperation;
use App\Models\FinancialOperationPayment;

class FinancialOperationPaymentController extends TenantAwareController
	{
	/**
	 * Reads financial operation payment list filters from the current request.
	 */
	private function readFilters(): array
		{
		return [
			'id'                     => isset($_GET['id']) ? trim($_GET['id']) : '',
			'financial_operation_id' => isset($_GET['financial_operation_id']) ? trim($_GET['financial_operation_id']) : '',
			'value'                  => isset($_GET['value']) ? trim($_GET['value']) : '',
		];
		}

	/**
	 * Lists financial operation payments with filters and pagination.
	 */
	public function index(): void
		{
		$this->requirePermission('financial_operation_payment.index');

		$payment = new FinancialOperationPayment();
		$operation = new FinancialOperation();
		$filters = $this->readFilters();

		$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
		if ($page < 1)
			{
			$page = 1;
			}

		$perPage = 10;
		$offset = ($page - 1) * $perPage;
		$payments = $payment->search($filters, $perPage, $offset);
		$total = $payment->count($filters);
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
				'data'       => $payments,
			]);
			}

		$this->view('financial_operations_payments/index', [
			'payments'   => $payments,
			'operations' => $operation->getOptions(),
			'title'      => 'Lista de pagamentos de operações financeiras',
			'filters'    => $filters,
			'page'       => $page,
			'totalPages' => $totalPages,
			'total'      => $total,
		]);
		}

	/**
	 * Renders the financial operation payment creation form.
	 */
	public function create(): void
		{
		$this->requirePermission('financial_operation_payment.create');

		$operation = new FinancialOperation();
		$selectedOperationId = isset($_GET['financial_operation_id']) ? (int) $_GET['financial_operation_id'] : 0;
		$this->view('financial_operations_payments/create', [
			'title'               => 'Novo pagamento financeiro',
			'operations'          => $operation->getOptions(),
			'selectedOperationId' => $selectedOperationId,
		]);
		}

	/**
	 * Persists a new financial operation payment and redirects back to the listing.
	 */
	public function store(): void
		{
		$this->requirePermission('financial_operation_payment.store');

			$payment = new FinancialOperationPayment();
			$payment->create($_POST);
			$this->redirectWithFlash('financial_operation_payment', 'success', 'Pagamento financeiro salvo com sucesso.');
			}

	/**
	 * Renders the financial operation payment edit form.
	 *
	 * @param mixed $id Payment identifier.
	 */
	public function edit($id): void
		{
		$this->requirePermission('financial_operation_payment.edit');

		$payment = new FinancialOperationPayment();
		$data = $payment->find($id);
		if (!$data)
			{
			$this->redirectWithFlash('financial_operation_payment', 'warning', 'Pagamento financeiro não encontrado.');
			}

		$operation = new FinancialOperation();
		$this->view('financial_operations_payments/edit', [
			'title'      => 'Editar pagamento financeiro',
			'payment'    => $data,
			'operations' => $operation->getOptions(),
		]);
		}

	/**
	 * Updates an existing financial operation payment.
	 *
	 * @param mixed $id Payment identifier.
	 */
	public function update($id): void
		{
		$this->requirePermission('financial_operation_payment.update');

			$payment = new FinancialOperationPayment();
			$payment->update($id, $_POST);
			$this->redirectWithFlash('financial_operation_payment', 'success', 'Pagamento financeiro atualizado com sucesso.');
			}

	/**
	 * Displays financial operation payment details.
	 *
	 * @param mixed $id Payment identifier.
	 */
	public function informations($id): void
		{
		$this->requirePermission('financial_operation_payment.informations');

		$payment = new FinancialOperationPayment();
		$data = $payment->findAllByFinancialOperationPaymentId($id);
		if (empty($data['financial_operation_payment']))
			{
			$this->redirectWithFlash('financial_operation_payment', 'warning', 'Pagamento financeiro não encontrado.');
			}

		$this->view('financial_operations_payments/informations', [
			'title'   => 'Informações sobre o pagamento financeiro',
			'payment' => $data['financial_operation_payment'],
		]);
		}

	/**
	 * Deletes an existing financial operation payment.
	 *
	 * @param mixed $id Payment identifier.
	 */
	public function delete($id): void
		{
		$this->requirePermission('financial_operation_payment.delete');

		$payment = new FinancialOperationPayment();
		$operation = new FinancialOperation();

		try
				{
				$payment->delete($id);
				$this->redirectWithFlash('financial_operation_payment', 'success', 'Pagamento financeiro excluído com sucesso.');
				}
		catch (\Throwable $e)
			{
			if ($this->wantsJson())
				{
				$this->json(['error' => $e->getMessage()], 422);
				}

			$filters = $this->readFilters();
			$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
			if ($page < 1)
				{
				$page = 1;
				}

			$perPage = 10;
			$offset = ($page - 1) * $perPage;
			$payments = $payment->search($filters, $perPage, $offset);
			$total = $payment->count($filters);
			$totalPages = (int) ceil($total / $perPage);
			if ($totalPages < 1)
				{
				$totalPages = 1;
				}

			http_response_code(422);
			$this->view('financial_operations_payments/index', [
				'payments'   => $payments,
				'operations' => $operation->getOptions(),
				'title'      => 'Lista de pagamentos de operações financeiras',
				'filters'    => $filters,
				'page'       => $page,
				'totalPages' => $totalPages,
				'total'      => $total,
				'formError'  => $e->getMessage(),
			]);
			}
		}
	}
?>
