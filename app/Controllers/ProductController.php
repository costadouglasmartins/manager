<?php

namespace App\Controllers;

use App\Core\TenantAwareController;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductCategory;
use App\Models\ProductHistory;
use App\Models\ProductUnit;
use App\Services\ChunkedUploadService;
use App\Services\ProductImportService;

class ProductController extends TenantAwareController
	{
	private const PRODUCT_IMAGE_MAX_FILE_SIZE = 8388608;
	private const PRODUCT_IMAGE_MAX_CHUNK_SIZE = 1048576;

	protected $routeAliases = [
		'export-excel' => 'exportExcel',
		'export-pdf'   => 'exportPdf',
		'upload-chunk' => 'uploadChunk',
		'import-spreadsheet' => 'importSpreadsheet',
		'download-import-template' => 'downloadImportTemplate',
	];

	/**
	 * Returns the authenticated manager user id.
	 */
	private function currentUserId(): int
		{
		return (int) ($_SESSION['manager']['user']['id'] ?? 0);
		}

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
			'attribute_filters'   => is_array($_GET['attribute_filters'] ?? null) ? $_GET['attribute_filters'] : [],
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
		$filterAttributes = [];
		$selectedCategoryId = (int) $filters['product_category_id'];
		if ($selectedCategoryId > 0)
			{
			$filterAttributes = array_values(array_filter((new ProductAttribute())->getCategorySchema($selectedCategoryId), static function (array $attribute): bool {
				return (int) ($attribute['filterable'] ?? 0) === 1;
			}));
			}

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
			'filterAttributes' => $filterAttributes,
		]);
		}

	/**
	 * Renders the product creation form.
	 */
	public function create(): void
		{
		$this->requirePermission('product.create');

		$category = new ProductCategory();
		$categories = $category->getOptions();
		$this->view('products/create', [
			'title'           => 'Novo produto',
			'categories'      => $categories,
			'units'           => (new ProductUnit())->groupedOptions(),
			'categorySchemas' => $this->categorySchemas($categories),
			'attributeValues' => [],
			'priceRules'      => [],
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
		$chunkedUploadService = new ChunkedUploadService();
		$temporaryImageFiles = [];

		try
			{
			$data = $_POST;
			$data['user_id'] = $this->currentUserId();
			$imageFiles = $this->imageFilesFromRequest($chunkedUploadService, $temporaryImageFiles);
			$product->create($data, $imageFiles);
			}
		catch (\Throwable $e)
			{
			if ($this->wantsJson())
				{
				$chunkedUploadService->deleteTemporaryFiles($temporaryImageFiles);
				$this->json(['error' => $e->getMessage()], 422);
				}

			http_response_code(422);
			$categories = $category->getOptions();
			$this->view('products/create', [
				'title'           => 'Novo produto',
				'categories'      => $categories,
				'units'           => (new ProductUnit())->groupedOptions(),
				'categorySchemas' => $this->categorySchemas($categories),
				'attributeValues' => $this->submittedAttributeValues($_POST['attributes'] ?? []),
				'variations'       => $this->submittedVariations($_POST['variations'] ?? []),
				'priceRules'        => $this->submittedPriceRules($_POST['price_rules'] ?? []),
				'formData'        => $_POST,
				'formError'       => $e->getMessage(),
			]);
			return;
			}
		finally
			{
			$chunkedUploadService->deleteTemporaryFiles($temporaryImageFiles);
			}

		$this->redirect('product');
		}

	/**
	 * Receives one chunk of a product image upload.
	 */
	public function uploadChunk(): void
		{
		$this->requirePermission('product.store');

		try
			{
			$service = new ChunkedUploadService();
			$this->json($service->receiveChunk('products', $_POST, $_FILES, [
				'max_file_size'      => self::PRODUCT_IMAGE_MAX_FILE_SIZE,
				'max_chunk_size'     => self::PRODUCT_IMAGE_MAX_CHUNK_SIZE,
				'allowed_mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
			]));
			}
		catch (\Throwable $e)
			{
			$this->json(['error' => $e->getMessage()], 422);
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
		$categories = $category->getOptions(false);
		$this->view('products/edit', [
			'title'           => 'Editar produto',
			'product'         => $data['product'],
			'images'          => $data['images'],
			'categories'      => $categories,
			'units'           => (new ProductUnit())->groupedOptions(),
			'categorySchemas' => $this->categorySchemas($categories),
			'attributeValues' => (new ProductAttribute())->getItemValueMap((int) $id),
			'variations'      => $data['variations'] ?? [],
			'priceRules'       => $data['price_rules'] ?? [],
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
			$categories = $category->getOptions(false);
			$this->view('products/edit', [
				'title'           => 'Editar produto',
				'product'         => $data['product'] ?? [],
				'images'          => $data['images'] ?? [],
				'categories'      => $categories,
				'units'           => (new ProductUnit())->groupedOptions(),
				'categorySchemas' => $this->categorySchemas($categories),
				'attributeValues' => $this->submittedAttributeValues($_POST['attributes'] ?? []),
				'variations'       => $this->submittedVariations($_POST['variations'] ?? []),
				'priceRules'        => $this->submittedPriceRules($_POST['price_rules'] ?? []),
				'formData'        => $_POST,
				'formError'       => $e->getMessage(),
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
			'attributes'     => $data['attributes'],
			'variations'     => $data['variations'] ?? [],
			'priceRules'      => $data['price_rules'] ?? [],
			'stockMovements' => $data['stock_movements'],
			'history'        => (new ProductHistory())->recentForProduct((int) $id),
		]);
		}

	public function importSpreadsheet(): void
		{
		$category = new ProductCategory();
		$service = new ProductImportService();
		$result = null;
		$formError = null;
		if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST')
			{
			$this->requirePermission('product.store');
			try
				{
				$result = $service->importCsv((int) ($_POST['product_category_id'] ?? 0), $_FILES['spreadsheet'] ?? [], $this->currentUserId());
				}
			catch (\Throwable $e)
				{
				http_response_code(422);
				$formError = $e->getMessage();
				}
			}
		else $this->requirePermission('product.create');

		$this->view('products/import', [
			'title' => 'Importar produtos',
			'categories' => $category->getOptions(),
			'batches' => $service->recentBatches(),
			'importResult' => $result,
			'formError' => $formError,
			'selectedCategoryId' => (int) ($_POST['product_category_id'] ?? $_GET['product_category_id'] ?? 0),
		]);
		}

	public function downloadImportTemplate(): void
		{
		$this->requirePermission('product.create');
		$categoryId = (int) ($_GET['product_category_id'] ?? 0);
		try
			{
			$content = (new ProductImportService())->templateCsv($categoryId);
			header('Content-Type: text/csv; charset=UTF-8');
			header('Content-Disposition: attachment; filename="modelo_importacao_produtos_categoria_' . $categoryId . '.csv"');
			header('Content-Length: ' . strlen($content));
			echo $content;
			exit;
			}
		catch (\Throwable $e)
			{
			http_response_code(422);
			echo htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
			exit;
			}
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

	/**
	 * Returns product image files from normal uploads or completed chunk tokens.
	 */
	private function imageFilesFromRequest(ChunkedUploadService $service, array &$temporaryImageFiles): array
		{
		$tokens = $_POST['uploaded_images'] ?? [];
		if (!is_array($tokens))
			{
			$tokens = [$tokens];
			}

		$temporaryImageFiles = $service->consumeCompletedUploads('products', $tokens);
		if ($temporaryImageFiles !== [])
			{
			return $temporaryImageFiles;
			}

		return $_FILES['images'] ?? [];
		}

	/** Returns the dynamic attribute definition for each visible category. */
	private function categorySchemas(array $categories): array
		{
		$model = new ProductAttribute();
		$schemas = [];
		foreach ($categories as $category)
			{
			$id = (int) ($category['id'] ?? 0);
			if ($id > 0) $schemas[$id] = $model->getCategorySchema($id);
			}
		return $schemas;
		}

	/** Converts submitted form attributes to the same map used by persisted values. */
	private function submittedAttributeValues($submitted): array
		{
		$values = [];
		if (!is_array($submitted)) return $values;
		foreach ($submitted as $attributeId => $input)
			{
			$input = is_array($input) ? $input : ['value' => $input];
			$values[(int) $attributeId] = [
				'submitted_value' => $input['value'] ?? '',
				'product_unit_id' => $input['unit_id'] ?? null,
			];
			}
		return $values;
		}

	private function submittedVariations($submitted): array
		{
		if (!is_array($submitted)) return [];
		return array_values(array_filter($submitted, static function ($row): bool {
			if (!is_array($row)) return false;
			foreach (['id', 'name', 'sku', 'barcode', 'cost_price', 'sale_price', 'stock_quantity', 'minimum_stock', 'track_stock', 'active'] as $field)
				{
				if (isset($row[$field]) && trim((string) $row[$field]) !== '') return true;
				}
			return false;
		}));
		}

	private function submittedPriceRules($submitted): array
		{
		if (!is_array($submitted)) return [];
		return array_values(array_filter($submitted, static function ($row): bool {
			return is_array($row) && (trim((string) ($row['minimum_quantity'] ?? '')) !== '' || trim((string) ($row['sale_price'] ?? '')) !== '');
		}));
		}
	}
?>
