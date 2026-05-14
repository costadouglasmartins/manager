<?php

namespace App\Controllers;

use App\Core\TenantAwareController;
use App\Models\FinancialOperationCategory;

class FinancialOperationCategoryController extends TenantAwareController
	{
	/**
	 * Lists financial operation categories with filters and pagination.
	 */
	public function index(): void
		{
		$this->requirePermission('financial_operation_category.index');

		$category = new FinancialOperationCategory();
		$filters = [
			'id'   => isset($_GET['id']) ? trim($_GET['id']) : '',
			'name' => isset($_GET['name']) ? trim($_GET['name']) : '',
		];

		$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
		if ($page < 1)
			{
			$page = 1;
			}

		$perPage = 10;
		$offset = ($page - 1) * $perPage;
		$categories = $category->search($filters, $perPage, $offset);
		$total = $category->count($filters);
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
				'data'       => $categories,
			]);
			}

		$this->view('financial_operations_categories/index', [
			'categories' => $categories,
			'title'      => 'Lista de categorias de operações financeiras',
			'filters'    => $filters,
			'page'       => $page,
			'totalPages' => $totalPages,
			'total'      => $total,
		]);
		}

	/**
	 * Renders the financial operation category creation form.
	 */
	public function create(): void
		{
		$this->requirePermission('financial_operation_category.create');
		$this->view('financial_operations_categories/create', ['title' => 'Nova categoria de operação financeira']);
		}

	/**
	 * Persists a new financial operation category and redirects back to the listing.
	 */
	public function store(): void
		{
		$this->requirePermission('financial_operation_category.store');

		$category = new FinancialOperationCategory();
		$category->create($_POST);
		$this->redirect('financial_operation_category');
		}

	/**
	 * Renders the financial operation category edit form.
	 *
	 * @param mixed $id Category identifier.
	 */
	public function edit($id): void
		{
		$this->requirePermission('financial_operation_category.edit');

		$category = new FinancialOperationCategory();
		$data = $category->find($id);
		if (!$data)
			{
			$this->redirect('financial_operation_category');
			}

		$this->view('financial_operations_categories/edit', [
			'title'    => 'Editar categoria de operação financeira',
			'category' => $data,
		]);
		}

	/**
	 * Updates an existing financial operation category.
	 *
	 * @param mixed $id Category identifier.
	 */
	public function update($id): void
		{
		$this->requirePermission('financial_operation_category.update');

		$category = new FinancialOperationCategory();
		$category->update($id, $_POST);
		$this->redirect('financial_operation_category');
		}

	/**
	 * Displays financial operation category details.
	 *
	 * @param mixed $id Category identifier.
	 */
	public function informations($id): void
		{
		$this->requirePermission('financial_operation_category.informations');

		$category = new FinancialOperationCategory();
		$data = $category->findAllByFinancialOperationCategoryId($id);
		if (empty($data['financial_operation_category']))
			{
			$this->redirect('financial_operation_category');
			}

		$this->view('financial_operations_categories/informations', [
			'title'    => 'Informações sobre a categoria da operação financeira',
			'category' => $data['financial_operation_category'],
		]);
		}
	}
?>
