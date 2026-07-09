<?php

namespace App\Controllers;

use App\Core\TenantAwareController;
use App\Models\ProductCategory;

class ProductCategoryController extends TenantAwareController
	{
	/**
	 * Reads product category list filters from the current request.
	 */
	private function readFilters(): array
		{
		return [
			'id'     => isset($_GET['id']) ? trim($_GET['id']) : '',
			'name'   => isset($_GET['name']) ? trim($_GET['name']) : '',
			'active' => isset($_GET['active']) ? trim($_GET['active']) : '',
		];
		}

	/**
	 * Lists product categories with filters and pagination.
	 */
	public function index(): void
		{
		$this->requirePermission('product_category.index');

		$productCategory = new ProductCategory();
		$filters = $this->readFilters();

		$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
		if ($page < 1)
			{
			$page = 1;
			}

		$perPage = 10;
		$offset = ($page - 1) * $perPage;
		$categories = $productCategory->search($filters, $perPage, $offset);
		$total = $productCategory->count($filters);
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

		$this->view('product_categories/index', [
			'categories' => $categories,
			'title'      => 'Categorias de produtos',
			'filters'    => $filters,
			'page'       => $page,
			'totalPages' => $totalPages,
			'total'      => $total,
		]);
		}

	/**
	 * Renders the product category creation form.
	 */
	public function create(): void
		{
		$this->requirePermission('product_category.create');
		$this->view('product_categories/create', ['title' => 'Nova categoria de produto']);
		}

	/**
	 * Persists a new product category.
	 */
	public function store(): void
		{
		$this->requirePermission('product_category.store');

		$productCategory = new ProductCategory();
		$productCategory->create($_POST);
		$this->redirect('product_category');
		}

	/**
	 * Renders the product category edit form.
	 *
	 * @param mixed $id Category identifier.
	 */
	public function edit($id): void
		{
		$this->requirePermission('product_category.edit');

		$productCategory = new ProductCategory();
		$category = $productCategory->find($id);
		if (!$category)
			{
			$this->redirect('product_category');
			}

		$this->view('product_categories/edit', [
			'title'    => 'Editar categoria de produto',
			'category' => $category,
		]);
		}

	/**
	 * Updates an existing product category.
	 *
	 * @param mixed $id Category identifier.
	 */
	public function update($id): void
		{
		$this->requirePermission('product_category.update');

		$productCategory = new ProductCategory();
		$productCategory->update($id, $_POST);
		$this->redirect('product_category');
		}

	/**
	 * Displays product category details.
	 *
	 * @param mixed $id Category identifier.
	 */
	public function informations($id): void
		{
		$this->requirePermission('product_category.informations');

		$productCategory = new ProductCategory();
		$data = $productCategory->findAllByProductCategoryId($id);
		if (empty($data['product_category']))
			{
			$this->redirect('product_category');
			}

		$this->view('product_categories/informations', [
			'title'    => 'Informações da categoria de produto',
			'category' => $data['product_category'],
		]);
		}

	/**
	 * Deletes an existing product category.
	 *
	 * @param mixed $id Category identifier.
	 */
	public function delete($id): void
		{
		$this->requirePermission('product_category.delete');

		$productCategory = new ProductCategory();

		try
			{
			$productCategory->delete($id);
			$this->redirect('product_category');
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
			$categories = $productCategory->search($filters, $perPage, $offset);
			$total = $productCategory->count($filters);
			$totalPages = (int) ceil($total / $perPage);
			if ($totalPages < 1)
				{
				$totalPages = 1;
				}

			http_response_code(422);
			$this->view('product_categories/index', [
				'categories' => $categories,
				'title'      => 'Categorias de produtos',
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
