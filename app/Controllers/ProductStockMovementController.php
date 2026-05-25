<?php

namespace App\Controllers;

use App\Core\TenantAwareController;
use App\Models\Product;
use App\Models\ProductStockMovement;

class ProductStockMovementController extends TenantAwareController
	{
	/**
	 * Lists stock movements with filters and pagination.
	 */
	public function index(): void
		{
		$this->requirePermission('product_stock_movement.index');

		$movement = new ProductStockMovement();
		$filters = [
			'id'            => isset($_GET['id']) ? trim($_GET['id']) : '',
			'product_id'    => isset($_GET['product_id']) ? trim($_GET['product_id']) : '',
			'movement_type' => isset($_GET['movement_type']) ? trim($_GET['movement_type']) : '',
			'reference'     => isset($_GET['reference']) ? trim($_GET['reference']) : '',
		];

		$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
		if ($page < 1)
			{
			$page = 1;
			}

		$perPage = 10;
		$offset = ($page - 1) * $perPage;
		$movements = $movement->search($filters, $perPage, $offset);
		$total = $movement->count($filters);
		$totalPages = (int) ceil($total / $perPage);
		if ($totalPages < 1)
			{
			$totalPages = 1;
			}

		$product = new Product();

		if ($this->wantsJson())
			{
			$this->json([
				'filters'    => $filters,
				'page'       => $page,
				'perPage'    => $perPage,
				'total'      => $total,
				'totalPages' => $totalPages,
				'data'       => $movements,
			]);
			}

		$this->view('products_stocks_movements/index', [
			'movements'  => $movements,
			'products'   => $product->getOptions(false),
			'title'      => 'Movimentações de estoque',
			'filters'    => $filters,
			'page'       => $page,
			'totalPages' => $totalPages,
			'total'      => $total,
		]);
		}

	/**
	 * Renders the stock movement creation form.
	 */
	public function create(): void
		{
		$this->requirePermission('product_stock_movement.create');

		$product = new Product();
		$this->view('products_stocks_movements/create', [
			'title'    => 'Nova movimentação de estoque',
			'products' => $product->getOptions(),
		]);
		}

	/**
	 * Persists a new stock movement.
	 */
	public function store(): void
		{
		$this->requirePermission('product_stock_movement.store');

		$movement = new ProductStockMovement();
		$product = new Product();

		try
			{
			$movement->create($_POST);
			$this->redirect('product_stock_movement');
			}
		catch (\Throwable $e)
			{
			if ($this->wantsJson())
				{
				$this->json(['error' => $e->getMessage()], 422);
				}

			http_response_code(422);
			$this->view('products_stocks_movements/create', [
				'title'     => 'Nova movimentação de estoque',
				'products'  => $product->getOptions(),
				'formData'  => $_POST,
				'formError' => $e->getMessage(),
			]);
			}
		}

	/**
	 * Displays stock movement details.
	 *
	 * @param mixed $id Movement identifier.
	 */
	public function informations($id): void
		{
		$this->requirePermission('product_stock_movement.informations');

		$movement = new ProductStockMovement();
		$data = $movement->findAllByProductStockMovementId($id);
		if (empty($data['product_stock_movement']))
			{
			$this->redirect('product_stock_movement');
			}

		$this->view('products_stocks_movements/informations', [
			'title'    => 'Informações da movimentação de estoque',
			'movement' => $data['product_stock_movement'],
		]);
		}
	}
?>
