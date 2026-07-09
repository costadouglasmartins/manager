<?php

namespace App\Models;

use App\Core\TenantModel;
use App\Services\FileUploadService;
use PDO;

class Product extends TenantModel
	{
	/**
	 * Returns the base product select query with category and main image data.
	 */
	private function baseSelectSql(): string
		{
		return "SELECT Product.id,
		              Product.user_id,
		              Product.product_category_id,
		              Product.sku,
		              Product.barcode,
		              Product.name,
		              Product.description,
		              Product.cost_price,
		              Product.sale_price,
		              Product.stock_quantity,
		              Product.minimum_stock,
		              Product.unit,
		              Product.active,
		              Product.created,
		              Product.modified,
		              ProductCategory.name AS product_category_name,
		              ProductImage.file_path AS main_image_file_path,
		              ProductImage.thumb_path AS main_image_thumb_path
		       FROM products AS Product
		       LEFT JOIN product_categories AS ProductCategory ON ProductCategory.id = Product.product_category_id
		       LEFT JOIN product_images AS ProductImage ON ProductImage.product_id = Product.id
		                                           AND ProductImage.is_main = 1";
		}

	/**
	 * Creates a product, stores images and registers optional initial stock.
	 *
	 * @param array $data Data to save.
	 * @param array $files Uploaded files from the product image input.
	 */
	public function create(array $data, array $files = []): array
		{
		$payload = $this->normalizePayload($data, '1');
		$userId = $this->normalizeUserId($data['user_id'] ?? null);
		$initialStock = $this->normalizeQuantity($data['initial_stock'] ?? '0');
		$fileUploadService = new FileUploadService();
		$storedImages = [];

		try
			{
			$this->db->beginTransaction();

			$stmt = $this->db->prepare("
				INSERT INTO products
				    (`user_id`, `product_category_id`, `sku`, `barcode`, `name`, `description`,
				     `cost_price`, `sale_price`, `stock_quantity`, `minimum_stock`, `unit`,
				     `active`, `created`, `modified`)
				VALUES
				    (:user_id, :product_category_id, :sku, :barcode, :name, :description,
				     :cost_price, :sale_price, :stock_quantity, :minimum_stock, :unit,
				     :active, :created, :modified)
			");
			$stmt->execute([
				'user_id'             => $userId,
				'product_category_id' => $payload['product_category_id'],
				'sku'                 => $payload['sku'],
				'barcode'             => $payload['barcode'],
				'name'                => $payload['name'],
				'description'         => $payload['description'],
				'cost_price'          => $payload['cost_price'],
				'sale_price'          => $payload['sale_price'],
				'stock_quantity'      => '0.000',
				'minimum_stock'       => $payload['minimum_stock'],
				'unit'                => $payload['unit'],
				'active'              => $payload['active'],
				'created'             => date('Y-m-d H:i:s'),
				'modified'            => date('Y-m-d H:i:s'),
			]);

			$productId = (int) $this->db->lastInsertId();

			if ((float) $initialStock > 0)
				{
				$this->insertStockMovement(
					$productId,
					'IN',
					$initialStock,
					'0.000',
					$initialStock,
					$payload['cost_price'],
					$payload['sale_price'],
					'Estoque inicial',
					null
				);
				$this->updateProductStock($productId, $initialStock);
				}

			if ($fileUploadService->hasUploads($files))
				{
				$storedImages = $fileUploadService->storeUploadedFiles('products', $files, $this->productImageUploadOptions($productId));
				$this->insertImageRows($productId, $storedImages);
				$this->ensureMainImage($productId);
				}

			$productSnapshot = $this->findAllByProductId($productId);
			$this->auditCreate('product', 'products', $productId, $productSnapshot, [
				'affected_tables' => ['products', 'product_images', 'product_stocks_movements'],
			]);

			$this->db->commit();

			return ['product_id' => $productId];
			}
		catch (\Exception $e)
			{
			if ($this->db->inTransaction())
				{
				$this->db->rollBack();
				}

			$fileUploadService->deleteStoredFiles($storedImages);
			throw $e;
			}
		}

	/**
	 * Finds a product by id.
	 *
	 * @param mixed $id Product identifier.
	 */
	public function find($id)
		{
		$stmt = $this->db->prepare($this->baseSelectSql() . "
			WHERE Product.id = :id
		");
		$stmt->execute(['id' => (int) $id]);

		return $stmt->fetch(PDO::FETCH_ASSOC);
		}

	/**
	 * Updates product data and synchronizes product images.
	 *
	 * @param mixed $id Product identifier.
	 * @param array $data Updated product data.
	 * @param array $files Uploaded files from the product image input.
	 */
	public function update($id, array $data, array $files = []): void
		{
		$productId = (int) $id;
		$payload = $this->normalizePayload($data, '0');
		$fileUploadService = new FileUploadService();
		$storedImages = [];
		$deletedImages = [];

		try
			{
			$this->db->beginTransaction();
			$beforeData = $this->findAllByProductId($productId);

			$stmt = $this->db->prepare("
				UPDATE products
				SET `product_category_id` = :product_category_id,
				    `sku` = :sku,
				    `barcode` = :barcode,
				    `name` = :name,
				    `description` = :description,
				    `cost_price` = :cost_price,
				    `sale_price` = :sale_price,
				    `minimum_stock` = :minimum_stock,
				    `unit` = :unit,
				    `active` = :active,
				    `modified` = :modified
				WHERE id = :id
			");
			$stmt->execute([
				'product_category_id' => $payload['product_category_id'],
				'sku'                 => $payload['sku'],
				'barcode'             => $payload['barcode'],
				'name'                => $payload['name'],
				'description'         => $payload['description'],
				'cost_price'          => $payload['cost_price'],
				'sale_price'          => $payload['sale_price'],
				'minimum_stock'       => $payload['minimum_stock'],
				'unit'                => $payload['unit'],
				'active'              => $payload['active'],
				'modified'            => date('Y-m-d H:i:s'),
				'id'                  => $productId,
			]);

			$removeImageIds = $this->normalizeIds($data['remove_images'] ?? []);
			if ($removeImageIds !== [])
				{
				$deletedImages = $this->findImagesByIds($productId, $removeImageIds);
				$this->deleteImageRows($productId, $removeImageIds);
				}

			if ($fileUploadService->hasUploads($files))
				{
				$storedImages = $fileUploadService->storeUploadedFiles('products', $files, $this->productImageUploadOptions($productId));
				$this->insertImageRows($productId, $storedImages);
				}

			$mainImageId = (int) ($data['main_image_id'] ?? 0);
			if ($mainImageId > 0 && $this->imageBelongsToProduct($productId, $mainImageId))
				{
				$this->setMainImage($productId, $mainImageId);
				}

			$this->ensureMainImage($productId);
			$afterData = $this->findAllByProductId($productId);
			$this->auditUpdate('product', 'products', $productId, $beforeData, $afterData, [
				'affected_tables' => ['products', 'product_images'],
			]);
			$this->db->commit();
			}
		catch (\Exception $e)
			{
			if ($this->db->inTransaction())
				{
				$this->db->rollBack();
				}

			$fileUploadService->deleteStoredFiles($storedImages);
			throw $e;
			}

		$fileUploadService->deleteStoredFiles($deletedImages);
		}

	/**
	 * Deletes an existing product and its image files when possible.
	 *
	 * @param mixed $id Product identifier.
	 */
	public function delete($id): void
		{
		$productId = (int) $id;
		if ($productId <= 0)
			{
			throw new \InvalidArgumentException('Produto inválido.');
			}

		$fileUploadService = new FileUploadService();
		$images = [];

		try
			{
			$this->db->beginTransaction();

			$product = $this->findAllByProductId($productId);
			if (empty($product['product']))
				{
				throw new \InvalidArgumentException('Produto não encontrado.');
				}

			if ($this->hasStockMovements($productId))
				{
				throw new \RuntimeException('Não é possível excluir o produto porque existem movimentações de estoque vinculadas.');
				}

			$images = $this->getImages($productId);

			$stmt = $this->db->prepare("DELETE FROM products WHERE id = :id");
			$stmt->execute(['id' => $productId]);
			$this->auditDelete('product', 'products', $productId, $product, [
				'affected_tables' => ['products', 'product_images'],
			]);

			$this->db->commit();
			}
		catch (\Exception $e)
			{
			if ($this->db->inTransaction())
				{
				$this->db->rollBack();
				}

			throw $e;
			}

		$fileUploadService->deleteStoredFiles($images);
		}

	/**
	 * Searches products using filters and pagination.
	 */
	public function search(array $filters = [], int $limit = 10, int $offset = 0): array
		{
		$sql = $this->baseSelectSql() . "
			WHERE 1 = 1
		";
		$params = [];

		$this->appendSearchFilters($sql, $params, $filters);

		$sql .= "
			ORDER BY Product.id DESC
			LIMIT :limit OFFSET :offset
		";

		$stmt = $this->db->prepare($sql);
		$this->bindSearchParams($stmt, $params);
		$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
		$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
		$stmt->execute();

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Counts products matching filters.
	 */
	public function count(array $filters = []): int
		{
		$sql = "SELECT COUNT(*) AS total
		        FROM products AS Product
		        LEFT JOIN product_categories AS ProductCategory ON ProductCategory.id = Product.product_category_id
		        WHERE 1 = 1";
		$params = [];

		$this->appendSearchFilters($sql, $params, $filters);

		$stmt = $this->db->prepare($sql);
		$this->bindSearchParams($stmt, $params);
		$stmt->execute();

		return (int) $stmt->fetchColumn();
		}

	/**
	 * Returns active product options for stock movement forms.
	 */
	public function getOptions(bool $activeOnly = true): array
		{
		$sql = "SELECT Product.id,
		               Product.sku,
		               Product.name,
		               Product.stock_quantity,
		               Product.unit
		        FROM products AS Product
		        WHERE 1 = 1";

		if ($activeOnly)
			{
			$sql .= " AND Product.active = 1";
			}

		$sql .= "
			ORDER BY Product.name ASC
		";

		$stmt = $this->db->query($sql);
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Returns the product details payload used by the information and edit views.
	 *
	 * @param mixed $id Product identifier.
	 */
	public function findAllByProductId($id): array
		{
		$productId = (int) $id;

		return [
			'product'         => $this->find($productId),
			'images'          => $this->getImages($productId),
			'stock_movements' => $this->getStockMovements($productId),
		];
		}

	/**
	 * Returns product images ordered for display.
	 */
	public function getImages(int $productId): array
		{
		$stmt = $this->db->prepare("
			SELECT ProductImage.id,
			       ProductImage.product_id,
			       ProductImage.original_name,
			       ProductImage.file_path,
			       ProductImage.thumb_path,
			       ProductImage.mime_type,
			       ProductImage.file_size,
			       ProductImage.width,
			       ProductImage.height,
			       ProductImage.position,
			       ProductImage.is_main,
			       ProductImage.created,
			       ProductImage.modified
			FROM product_images AS ProductImage
			WHERE ProductImage.product_id = :product_id
			ORDER BY ProductImage.is_main DESC,
			         ProductImage.position ASC,
			         ProductImage.id ASC
		");
		$stmt->execute(['product_id' => $productId]);

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Returns recent stock movements for a product.
	 */
	public function getStockMovements(int $productId, int $limit = 10): array
		{
		$limit = max(1, min(50, $limit));
		$stmt = $this->db->prepare("
			SELECT ProductStockMovement.id,
			       ProductStockMovement.product_id,
			       ProductStockMovement.movement_type,
			       ProductStockMovement.quantity,
			       ProductStockMovement.previous_quantity,
			       ProductStockMovement.new_quantity,
			       ProductStockMovement.unit_cost,
			       ProductStockMovement.unit_price,
			       ProductStockMovement.reference,
			       ProductStockMovement.observations,
			       ProductStockMovement.movement_date,
			       ProductStockMovement.created,
			       ProductStockMovement.modified
			FROM product_stocks_movements AS ProductStockMovement
			WHERE ProductStockMovement.product_id = :product_id
			ORDER BY ProductStockMovement.movement_date DESC,
			         ProductStockMovement.id DESC
			LIMIT {$limit}
		");
		$stmt->execute(['product_id' => $productId]);

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Appends product search filters to a SQL query.
	 */
	private function appendSearchFilters(string &$sql, array &$params, array $filters): void
		{
		if (isset($filters['id']) && $filters['id'] !== '')
			{
			$sql .= " AND Product.id = :id";
			$params['id'] = (int) $filters['id'];
			}

		if (isset($filters['product_category_id']) && $filters['product_category_id'] !== '')
			{
			$sql .= " AND Product.product_category_id = :product_category_id";
			$params['product_category_id'] = (int) $filters['product_category_id'];
			}

		if (isset($filters['sku']) && $filters['sku'] !== '')
			{
			$sql .= " AND Product.sku LIKE :sku";
			$params['sku'] = '%' . $filters['sku'] . '%';
			}

		if (isset($filters['name']) && $filters['name'] !== '')
			{
			$sql .= " AND Product.`name` LIKE :name";
			$params['name'] = '%' . $filters['name'] . '%';
			}

		if (isset($filters['active']) && in_array((string) $filters['active'], ['0', '1'], true))
			{
			$sql .= " AND Product.active = :active";
			$params['active'] = (int) $filters['active'];
			}

		$stockStatus = (string) ($filters['stock_status'] ?? '');
		if ($stockStatus === 'OUT')
			{
			$sql .= " AND Product.stock_quantity <= 0";
			}
		elseif ($stockStatus === 'LOW')
			{
			$sql .= " AND Product.stock_quantity > 0
			          AND Product.stock_quantity <= Product.minimum_stock";
			}
		elseif ($stockStatus === 'AVAILABLE')
			{
			$sql .= " AND Product.stock_quantity > 0";
			}
		}

	/**
	 * Binds search params to a prepared statement.
	 */
	private function bindSearchParams(\PDOStatement $stmt, array $params): void
		{
		foreach ($params as $key => $value)
			{
			$paramType = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
			$stmt->bindValue(':' . $key, $value, $paramType);
			}
		}

	/**
	 * Indicates whether the product has stock movements.
	 */
	private function hasStockMovements(int $productId): bool
		{
		$stmt = $this->db->prepare("
			SELECT 1
			FROM product_stocks_movements AS ProductStockMovement
			WHERE ProductStockMovement.product_id = :product_id
			LIMIT 1
		");
		$stmt->execute(['product_id' => $productId]);

		return $stmt->fetchColumn() !== false;
		}

	/**
	 * Returns upload options for product images.
	 */
	private function productImageUploadOptions(int $productId): array
		{
		return [
			'directory_parts'          => [$productId],
			'file_prefix'              => 'product_' . $productId,
			'allowed_mime_types'       => ['image/jpeg', 'image/png', 'image/webp'],
			'allowed_extensions'       => ['jpg', 'jpeg', 'png', 'webp'],
			'process_image'            => true,
			'upload_error_message'     => 'Não foi possível enviar uma das imagens.',
			'invalid_file_message'     => 'Arquivo de imagem inválido.',
			'max_file_size_message'    => 'Cada imagem deve ter no máximo 8 MB.',
			'mime_type_error_message'  => 'Envie somente imagens JPG, PNG ou WebP.',
			'image_type_error_message' => 'Envie somente imagens JPG, PNG ou WebP.',
		];
		}

	/**
	 * Inserts product image rows.
	 */
	private function insertImageRows(int $productId, array $images): void
		{
		if ($images === [])
			{
			return;
			}

		$nextPosition = $this->nextImagePosition($productId);
		$stmt = $this->db->prepare("
			INSERT INTO product_images
			    (`product_id`, `original_name`, `file_path`, `thumb_path`, `mime_type`,
			     `file_size`, `width`, `height`, `position`, `is_main`, `created`, `modified`)
			VALUES
			    (:product_id, :original_name, :file_path, :thumb_path, :mime_type,
			     :file_size, :width, :height, :position, :is_main, :created, :modified)
		");

		foreach ($images as $image)
			{
			$stmt->execute([
				'product_id'    => $productId,
				'original_name' => trim((string) ($image['original_name'] ?? '')),
				'file_path'     => trim((string) ($image['file_path'] ?? '')),
				'thumb_path'    => trim((string) ($image['thumb_path'] ?? '')),
				'mime_type'     => trim((string) ($image['mime_type'] ?? 'image/jpeg')),
				'file_size'     => (int) ($image['file_size'] ?? 0),
				'width'         => (int) ($image['width'] ?? 0),
				'height'        => (int) ($image['height'] ?? 0),
				'position'      => $nextPosition,
				'is_main'       => 0,
				'created'       => date('Y-m-d H:i:s'),
				'modified'      => date('Y-m-d H:i:s'),
			]);
			$nextPosition++;
			}
		}

	/**
	 * Inserts a stock movement row inside the current transaction.
	 */
	private function insertStockMovement(
		int $productId,
		string $movementType,
		string $quantity,
		string $previousQuantity,
		string $newQuantity,
		?string $unitCost,
		?string $unitPrice,
		?string $reference,
		?string $observations
	): void
		{
		$stmt = $this->db->prepare("
			INSERT INTO product_stocks_movements
			    (`product_id`, `movement_type`, `quantity`, `previous_quantity`, `new_quantity`,
			     `unit_cost`, `unit_price`, `reference`, `observations`, `movement_date`, `created`, `modified`)
			VALUES
			    (:product_id, :movement_type, :quantity, :previous_quantity, :new_quantity,
			     :unit_cost, :unit_price, :reference, :observations, :movement_date, :created, :modified)
		");
		$stmt->execute([
			'product_id'        => $productId,
			'movement_type'     => $movementType,
			'quantity'          => $quantity,
			'previous_quantity' => $previousQuantity,
			'new_quantity'      => $newQuantity,
			'unit_cost'         => $unitCost,
			'unit_price'        => $unitPrice,
			'reference'         => $reference,
			'observations'      => $observations,
			'movement_date'     => date('Y-m-d H:i:s'),
			'created'           => date('Y-m-d H:i:s'),
			'modified'          => date('Y-m-d H:i:s'),
		]);
		}

	/**
	 * Updates the product stock balance.
	 */
	private function updateProductStock(int $productId, string $quantity): void
		{
		$stmt = $this->db->prepare("
			UPDATE products
			SET stock_quantity = :stock_quantity,
			    modified = :modified
			WHERE id = :id
		");
		$stmt->execute([
			'stock_quantity' => $quantity,
			'modified'       => date('Y-m-d H:i:s'),
			'id'             => $productId,
		]);
		}

	/**
	 * Returns the next image position for a product.
	 */
	private function nextImagePosition(int $productId): int
		{
		$stmt = $this->db->prepare("
			SELECT COALESCE(MAX(ProductImage.position), -1) + 1 AS next_position
			FROM product_images AS ProductImage
			WHERE ProductImage.product_id = :product_id
		");
		$stmt->execute(['product_id' => $productId]);

		return (int) $stmt->fetchColumn();
		}

	/**
	 * Finds image rows by ids for a product.
	 */
	private function findImagesByIds(int $productId, array $imageIds): array
		{
		$images = [];
		$stmt = $this->db->prepare("
			SELECT ProductImage.id,
			       ProductImage.file_path,
			       ProductImage.thumb_path
			FROM product_images AS ProductImage
			WHERE ProductImage.id = :id
			  AND ProductImage.product_id = :product_id
		");

		foreach ($imageIds as $imageId)
			{
			$stmt->execute([
				'id'         => (int) $imageId,
				'product_id' => $productId,
			]);
			$image = $stmt->fetch(PDO::FETCH_ASSOC);
			if ($image)
				{
				$images[] = $image;
				}
			}

		return $images;
		}

	/**
	 * Deletes product image rows by ids.
	 */
	private function deleteImageRows(int $productId, array $imageIds): void
		{
		$stmt = $this->db->prepare("
			DELETE FROM product_images
			WHERE id = :id
			  AND product_id = :product_id
		");

		foreach ($imageIds as $imageId)
			{
			$stmt->execute([
				'id'         => (int) $imageId,
				'product_id' => $productId,
			]);
			}
		}

	/**
	 * Ensures the product has exactly one main image when images exist.
	 */
	private function ensureMainImage(int $productId): void
		{
		$stmt = $this->db->prepare("
			SELECT ProductImage.id
			FROM product_images AS ProductImage
			WHERE ProductImage.product_id = :product_id
			ORDER BY ProductImage.is_main DESC,
			         ProductImage.position ASC,
			         ProductImage.id ASC
			LIMIT 1
		");
		$stmt->execute(['product_id' => $productId]);
		$imageId = (int) $stmt->fetchColumn();

		if ($imageId > 0)
			{
			$this->setMainImage($productId, $imageId);
			}
		}

	/**
	 * Marks one product image as main.
	 */
	private function setMainImage(int $productId, int $imageId): void
		{
		$stmt = $this->db->prepare("
			UPDATE product_images
			SET is_main = CASE WHEN id = :image_id THEN 1 ELSE 0 END,
			    modified = :modified
			WHERE product_id = :product_id
		");
		$stmt->execute([
			'image_id'   => $imageId,
			'modified'   => date('Y-m-d H:i:s'),
			'product_id' => $productId,
		]);
		}

	/**
	 * Checks if an image belongs to a product.
	 */
	private function imageBelongsToProduct(int $productId, int $imageId): bool
		{
		$stmt = $this->db->prepare("
			SELECT 1
			FROM product_images AS ProductImage
			WHERE ProductImage.id = :id
			  AND ProductImage.product_id = :product_id
			LIMIT 1
		");
		$stmt->execute([
			'id'         => $imageId,
			'product_id' => $productId,
		]);

		return (bool) $stmt->fetchColumn();
		}

	/**
	 * Normalizes product form payload.
	 */
	private function normalizePayload(array $data, string $defaultActive): array
		{
		return [
			'product_category_id' => $this->nullableInt($data['product_category_id'] ?? null),
			'sku'                 => $this->nullableTrim($data['sku'] ?? null),
			'barcode'             => $this->nullableTrim($data['barcode'] ?? null),
			'name'                => trim((string) ($data['name'] ?? '')),
			'description'         => sanitizeRichText($data['description'] ?? null),
			'cost_price'          => $this->normalizeDecimal($data['cost_price'] ?? '0'),
			'sale_price'          => $this->normalizeDecimal($data['sale_price'] ?? '0'),
			'minimum_stock'       => $this->normalizeQuantity($data['minimum_stock'] ?? '0'),
			'unit'                => strtoupper(trim((string) ($data['unit'] ?? 'UN'))) ?: 'UN',
			'active'              => ((string) ($data['active'] ?? $defaultActive) === '1') ? 1 : 0,
		];
		}

	/**
	 * Returns a valid authenticated user id for product ownership.
	 *
	 * @param mixed $value Raw value.
	 */
	private function normalizeUserId($value): int
		{
		$userId = (int) $value;
		if ($userId <= 0)
			{
			throw new \RuntimeException('Usuário autenticado não encontrado para salvar o produto.');
			}

		return $userId;
		}

	/**
	 * Normalizes a submitted ids array into positive integers.
	 *
	 * @param mixed $raw Raw ids.
	 */
	private function normalizeIds($raw): array
		{
		if (!is_array($raw))
			{
			return [];
			}

		$ids = [];
		foreach ($raw as $value)
			{
			$id = (int) $value;
			if ($id > 0)
				{
				$ids[] = $id;
				}
			}

		return array_values(array_unique($ids));
		}

	/**
	 * Returns a nullable positive integer.
	 *
	 * @param mixed $value Raw value.
	 */
	private function nullableInt($value): ?int
		{
		$id = (int) $value;
		return $id > 0 ? $id : null;
		}

	/**
	 * Returns a trimmed string or null when the value is empty.
	 *
	 * @param mixed $value Raw value.
	 */
	private function nullableTrim($value): ?string
		{
		$text = trim((string) $value);
		return ($text !== '' ? $text : null);
		}

	/**
	 * Normalizes money values submitted by Portuguese or database-style inputs.
	 *
	 * @param mixed $value Raw value.
	 */
	private function normalizeDecimal($value): string
		{
		$text = $this->normalizeNumberText($value);
		return number_format(max(0, (float) $text), 2, '.', '');
		}

	/**
	 * Normalizes stock quantities submitted by Portuguese or database-style inputs.
	 *
	 * @param mixed $value Raw value.
	 */
	private function normalizeQuantity($value): string
		{
		$text = $this->normalizeNumberText($value);
		return number_format(max(0, (float) $text), 3, '.', '');
		}

	/**
	 * Normalizes a numeric text value.
	 *
	 * @param mixed $value Raw value.
	 */
	private function normalizeNumberText($value): string
		{
		$text = trim((string) $value);
		$text = str_replace(['R$', ' '], '', $text);

		if ($text === '')
			{
			return '0';
			}

		if (strpos($text, ',') !== false)
			{
			$text = str_replace('.', '', $text);
			$text = str_replace(',', '.', $text);
			}
		elseif (preg_match('/^-?\d{1,3}(\.\d{3})+$/', $text))
			{
			$text = str_replace('.', '', $text);
			}

		$text = preg_replace('/[^0-9.\-]/', '', $text) ?? '0';
		return is_numeric($text) ? $text : '0';
		}
	}
?>
