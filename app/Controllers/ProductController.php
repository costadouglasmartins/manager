<?php

namespace App\Controllers;

use App\Core\TenantAwareController;
use App\Models\Product;
use App\Models\ProductCategory;

class ProductController extends TenantAwareController
	{
	/**
	 * Lists products with filters and pagination.
	 */
	public function index(): void
		{
		$this->requirePermission('product.index');

		$product = new Product();
		$filters = [
			'id'                  => isset($_GET['id']) ? trim($_GET['id']) : '',
			'product_category_id' => isset($_GET['product_category_id']) ? trim($_GET['product_category_id']) : '',
			'sku'                 => isset($_GET['sku']) ? trim($_GET['sku']) : '',
			'name'                => isset($_GET['name']) ? trim($_GET['name']) : '',
			'active'              => isset($_GET['active']) ? trim($_GET['active']) : '',
			'stock_status'        => isset($_GET['stock_status']) ? trim($_GET['stock_status']) : '',
		];

		$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
		if ($page < 1)
			{
			$page = 1;
			}

		$perPage = 10;
		$offset = ($page - 1) * $perPage;
		$products = $product->search($filters, $perPage, $offset);
		$total = $product->count($filters);
		$totalPages = (int) ceil($total / $perPage);
		if ($totalPages < 1)
			{
			$totalPages = 1;
			}

		$category = new ProductCategory();

		if ($this->wantsJson())
			{
			$this->json([
				'filters'    => $filters,
				'page'       => $page,
				'perPage'    => $perPage,
				'total'      => $total,
				'totalPages' => $totalPages,
				'data'       => $products,
			]);
			}

		$this->view('products/index', [
			'products'    => $products,
			'categories'  => $category->getOptions(false),
			'title'       => 'Produtos',
			'filters'     => $filters,
			'page'        => $page,
			'totalPages'  => $totalPages,
			'total'       => $total,
		]);
		}

	/**
	 * Renders the product creation form.
	 */
	public function create(): void
		{
		$this->requirePermission('product.create');

		$category = new ProductCategory();
		$this->view('products/create', [
			'title'      => 'Novo produto',
			'categories' => $category->getOptions(),
		]);
		}

	/**
	 * Persists a new product and uploaded images.
	 */
	public function store(): void
		{
		$this->requirePermission('product.store');

		$product = new Product();
		$category = new ProductCategory();

		try
			{
			$product->create($_POST, $_FILES['images'] ?? []);
			$this->redirect('product');
			}
		catch (\Throwable $e)
			{
			if ($this->wantsJson())
				{
				$this->json(['error' => $e->getMessage()], 422);
				}

			http_response_code(422);
			$this->view('products/create', [
				'title'      => 'Novo produto',
				'categories' => $category->getOptions(),
				'formData'   => $_POST,
				'formError'  => $e->getMessage(),
			]);
			}
		}

	/**
	 * Renders the product edit form.
	 *
	 * @param mixed $id Product identifier.
	 */
	public function edit($id): void
		{
		$this->requirePermission('product.edit');

		$product = new Product();
		$data = $product->findAllByProductId($id);
		if (empty($data['product']))
			{
			$this->redirect('product');
			}

		$category = new ProductCategory();
		$this->view('products/edit', [
			'title'      => 'Editar produto',
			'product'    => $data['product'],
			'images'     => $data['images'],
			'categories' => $category->getOptions(false),
		]);
		}

	/**
	 * Updates an existing product and synchronizes images.
	 *
	 * @param mixed $id Product identifier.
	 */
	public function update($id): void
		{
		$this->requirePermission('product.update');

		$product = new Product();
		$category = new ProductCategory();

		try
			{
			$product->update($id, $_POST, $_FILES['images'] ?? []);
			$this->redirect('product');
			}
		catch (\Throwable $e)
			{
			$data = $product->findAllByProductId($id);
			if ($this->wantsJson())
				{
				$this->json(['error' => $e->getMessage()], 422);
				}

			http_response_code(422);
			$this->view('products/edit', [
				'title'      => 'Editar produto',
				'product'    => $data['product'] ?? [],
				'images'     => $data['images'] ?? [],
				'categories' => $category->getOptions(false),
				'formData'   => $_POST,
				'formError'  => $e->getMessage(),
			]);
			}
		}

	/**
	 * Displays product details.
	 *
	 * @param mixed $id Product identifier.
	 */
	public function informations($id): void
		{
		$this->requirePermission('product.informations');

		$product = new Product();
		$data = $product->findAllByProductId($id);
		if (empty($data['product']))
			{
			$this->redirect('product');
			}

		$this->view('products/informations', [
			'title'          => 'Informações do produto',
			'product'        => $data['product'],
			'images'         => $data['images'],
			'stockMovements' => $data['stock_movements'],
		]);
		}

	/**
	 * Deletes an existing product.
	 *
	 * @param mixed $id Product identifier.
	 */
	public function delete($id): void
		{
		$this->requirePermission('product.delete');

		$product = new Product();
		$category = new ProductCategory();

		try
			{
			$product->delete($id);
			$this->redirect('product');
			}
		catch (\Throwable $e)
			{
			if ($this->wantsJson())
				{
				$this->json(['error' => $e->getMessage()], 422);
				}

			$filters = [
				'id'                  => isset($_GET['id']) ? trim($_GET['id']) : '',
				'product_category_id' => isset($_GET['product_category_id']) ? trim($_GET['product_category_id']) : '',
				'sku'                 => isset($_GET['sku']) ? trim($_GET['sku']) : '',
				'name'                => isset($_GET['name']) ? trim($_GET['name']) : '',
				'active'              => isset($_GET['active']) ? trim($_GET['active']) : '',
				'stock_status'        => isset($_GET['stock_status']) ? trim($_GET['stock_status']) : '',
			];

			$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
			if ($page < 1)
				{
				$page = 1;
				}

			$perPage = 10;
			$offset = ($page - 1) * $perPage;
			$products = $product->search($filters, $perPage, $offset);
			$total = $product->count($filters);
			$totalPages = (int) ceil($total / $perPage);
			if ($totalPages < 1)
				{
				$totalPages = 1;
				}

			http_response_code(422);
			$this->view('products/index', [
				'products'    => $products,
				'categories'  => $category->getOptions(false),
				'title'       => 'Produtos',
				'filters'     => $filters,
				'page'        => $page,
				'totalPages'  => $totalPages,
				'total'       => $total,
				'formError'   => $e->getMessage(),
			]);
			}
		}
	}
?>
