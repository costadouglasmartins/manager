<?php

namespace App\Controllers;

use App\Core\TenantAwareController;
use App\Models\FinancialOperationCategory;
use App\Models\FinancialOperationSubcategory;

class FinancialOperationSubcategoryController extends TenantAwareController
	{
	/**
	 * Reads financial operation subcategory list filters from the current request.
	 */
	private function readFilters(): array
		{
		return [
			'id'                              => isset($_GET['id']) ? trim($_GET['id']) : '',
			'financial_operation_category_id' => isset($_GET['financial_operation_category_id']) ? trim($_GET['financial_operation_category_id']) : '',
			'name'                            => isset($_GET['name']) ? trim($_GET['name']) : '',
		];
		}

	/**
	 * Lists financial operation subcategories with filters and pagination.
	 */
	public function index(): void
		{
		$this->requirePermission('financial_operation_subcategory.index');

		$subcategory = new FinancialOperationSubcategory();
		$category = new FinancialOperationCategory();
		$filters = $this->readFilters();

		$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
		if ($page < 1)
			{
			$page = 1;
			}

		$perPage = 10;
		$offset = ($page - 1) * $perPage;
		$subcategories = $subcategory->search($filters, $perPage, $offset);
		$total = $subcategory->count($filters);
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
				'data'       => $subcategories,
			]);
			}

		$this->view('financial_operations_subcategories/index', [
			'subcategories' => $subcategories,
			'categories'    => $category->getOptions(),
			'title'         => 'Lista de subcategorias de operações financeiras',
			'filters'       => $filters,
			'page'          => $page,
			'totalPages'    => $totalPages,
			'total'         => $total,
		]);
		}

	/**
	 * Renders the financial operation subcategory creation form.
	 */
	public function create(): void
		{
		$this->requirePermission('financial_operation_subcategory.create');

		$category = new FinancialOperationCategory();
		$this->view('financial_operations_subcategories/create', [
			'title'      => 'Nova subcategoria financeira',
			'categories' => $category->getOptions(),
		]);
		}

	/**
	 * Persists a new financial operation subcategory and redirects back to the listing.
	 */
	public function store(): void
		{
		$this->requirePermission('financial_operation_subcategory.store');

			$subcategory = new FinancialOperationSubcategory();
			$subcategory->create($_POST);
			$this->redirectWithFlash('financial_operation_subcategory', 'success', 'Subcategoria financeira salva com sucesso.');
			}

	/**
	 * Renders the financial operation subcategory edit form.
	 *
	 * @param mixed $id Subcategory identifier.
	 */
	public function edit($id): void
		{
		$this->requirePermission('financial_operation_subcategory.edit');

		$subcategory = new FinancialOperationSubcategory();
		$data = $subcategory->find($id);
		if (!$data)
			{
			$this->redirectWithFlash('financial_operation_subcategory', 'warning', 'Subcategoria financeira não encontrada.');
			}

		$category = new FinancialOperationCategory();
		$this->view('financial_operations_subcategories/edit', [
			'title'       => 'Editar subcategoria financeira',
			'subcategory' => $data,
			'categories'  => $category->getOptions(),
		]);
		}

	/**
	 * Updates an existing financial operation subcategory.
	 *
	 * @param mixed $id Subcategory identifier.
	 */
	public function update($id): void
		{
		$this->requirePermission('financial_operation_subcategory.update');

			$subcategory = new FinancialOperationSubcategory();
			$subcategory->update($id, $_POST);
			$this->redirectWithFlash('financial_operation_subcategory', 'success', 'Subcategoria financeira atualizada com sucesso.');
			}

	/**
	 * Displays financial operation subcategory details.
	 *
	 * @param mixed $id Subcategory identifier.
	 */
	public function informations($id): void
		{
		$this->requirePermission('financial_operation_subcategory.informations');

		$subcategory = new FinancialOperationSubcategory();
		$data = $subcategory->findAllByFinancialOperationSubcategoryId($id);
		if (empty($data['financial_operation_subcategory']))
			{
			$this->redirectWithFlash('financial_operation_subcategory', 'warning', 'Subcategoria financeira não encontrada.');
			}

		$this->view('financial_operations_subcategories/informations', [
			'title'       => 'Informações sobre a subcategoria financeira',
			'subcategory' => $data['financial_operation_subcategory'],
		]);
		}

	/**
	 * Deletes an existing financial operation subcategory.
	 *
	 * @param mixed $id Subcategory identifier.
	 */
	public function delete($id): void
		{
		$this->requirePermission('financial_operation_subcategory.delete');

		$subcategory = new FinancialOperationSubcategory();
		$category = new FinancialOperationCategory();

		try
				{
				$subcategory->delete($id);
				$this->redirectWithFlash('financial_operation_subcategory', 'success', 'Subcategoria financeira excluída com sucesso.');
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
			$subcategories = $subcategory->search($filters, $perPage, $offset);
			$total = $subcategory->count($filters);
			$totalPages = (int) ceil($total / $perPage);
			if ($totalPages < 1)
				{
				$totalPages = 1;
				}

			http_response_code(422);
			$this->view('financial_operations_subcategories/index', [
				'subcategories' => $subcategories,
				'categories'    => $category->getOptions(),
				'title'         => 'Lista de subcategorias de operações financeiras',
				'filters'       => $filters,
				'page'          => $page,
				'totalPages'    => $totalPages,
				'total'         => $total,
				'formError'     => $e->getMessage(),
			]);
			}
		}
	}
?>
