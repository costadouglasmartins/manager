<?php

namespace App\Models;

use App\Core\TenantModel;
use PDO;

class ProductStockMovement extends TenantModel
	{
	private const MOVEMENT_TYPES = ['IN', 'OUT', 'ADJUSTMENT'];

	/**
	 * Returns the base stock movement select query with product data.
	 */
	private function baseSelectSql(): string
		{
		return "SELECT ProductStockMovement.id,
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
		              ProductStockMovement.modified,
		              Product.name AS product_name,
		              Product.sku AS product_sku,
		              Product.unit AS product_unit,
		              ProductCategory.name AS product_category_name
		       FROM products_stocks_movements AS ProductStockMovement
		       INNER JOIN products AS Product ON Product.id = ProductStockMovement.product_id
		       LEFT JOIN products_categories AS ProductCategory ON ProductCategory.id = Product.product_category_id";
		}

	/**
	 * Creates a stock movement and updates the product balance.
	 *
	 * @param array $data Data to save.
	 */
	public function create(array $data): array
		{
		$productId = (int) ($data['product_id'] ?? 0);
		$movementType = $this->normalizeMovementType($data['movement_type'] ?? 'IN');
		$quantity = $this->normalizeQuantity($data['quantity'] ?? '0');
		$unitCost = $this->normalizeNullableDecimal($data['unit_cost'] ?? null);
		$unitPrice = $this->normalizeNullableDecimal($data['unit_price'] ?? null);
		$reference = $this->nullableTrim($data['reference'] ?? null);
		$observations = $this->nullableTrim($data['observations'] ?? null);
		$movementDate = $this->normalizeDateTime($data['movement_date'] ?? null);

		if ($productId <= 0)
			{
			throw new \InvalidArgumentException('Selecione um produto para movimentar o estoque.');
			}

		if ((float) $quantity <= 0 && $movementType !== 'ADJUSTMENT')
			{
			throw new \InvalidArgumentException('Informe uma quantidade maior que zero.');
			}

		try
			{
			$this->db->beginTransaction();

			$product = $this->lockProduct($productId);
			if (!$product)
				{
				throw new \InvalidArgumentException('Produto não encontrado.');
				}

			$previousQuantity = $this->normalizeQuantity($product['stock_quantity'] ?? '0');
			$newQuantity = $this->calculateNewQuantity($movementType, $previousQuantity, $quantity);

			$stmt = $this->db->prepare("
				INSERT INTO products_stocks_movements
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
				'movement_date'     => $movementDate,
				'created'           => date('Y-m-d H:i:s'),
				'modified'          => date('Y-m-d H:i:s'),
			]);

			$movementId = (int) $this->db->lastInsertId();
			$this->updateProductStock($productId, $newQuantity);
			$this->db->commit();

			return ['product_stock_movement_id' => $movementId];
			}
		catch (\Exception $e)
			{
			if ($this->db->inTransaction())
				{
				$this->db->rollBack();
				}

			throw $e;
			}
		}

	/**
	 * Finds a stock movement by id.
	 *
	 * @param mixed $id Movement identifier.
	 */
	public function find($id)
		{
		$stmt = $this->db->prepare($this->baseSelectSql() . "
			WHERE ProductStockMovement.id = :id
		");
		$stmt->execute(['id' => (int) $id]);

		return $stmt->fetch(PDO::FETCH_ASSOC);
		}

	/**
	 * Searches stock movements using filters and pagination.
	 */
	public function search(array $filters = [], int $limit = 10, int $offset = 0): array
		{
		$sql = $this->baseSelectSql() . "
			WHERE 1 = 1
		";
		$params = [];

		$this->appendSearchFilters($sql, $params, $filters);

		$sql .= "
			ORDER BY ProductStockMovement.movement_date DESC,
			         ProductStockMovement.id DESC
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
	 * Counts stock movements matching filters.
	 */
	public function count(array $filters = []): int
		{
		$sql = "SELECT COUNT(*) AS total
		        FROM products_stocks_movements AS ProductStockMovement
		        INNER JOIN products AS Product ON Product.id = ProductStockMovement.product_id
		        LEFT JOIN products_categories AS ProductCategory ON ProductCategory.id = Product.product_category_id
		        WHERE 1 = 1";
		$params = [];

		$this->appendSearchFilters($sql, $params, $filters);

		$stmt = $this->db->prepare($sql);
		$this->bindSearchParams($stmt, $params);
		$stmt->execute();

		return (int) $stmt->fetchColumn();
		}

	/**
	 * Returns the stock movement details payload used by the information view.
	 *
	 * @param mixed $id Movement identifier.
	 */
	public function findAllByProductStockMovementId($id): array
		{
		return ['product_stock_movement' => $this->find($id)];
		}

	/**
	 * Appends stock movement search filters to a SQL query.
	 */
	private function appendSearchFilters(string &$sql, array &$params, array $filters): void
		{
		if (isset($filters['id']) && $filters['id'] !== '')
			{
			$sql .= " AND ProductStockMovement.id = :id";
			$params['id'] = (int) $filters['id'];
			}

		if (isset($filters['product_id']) && $filters['product_id'] !== '')
			{
			$sql .= " AND ProductStockMovement.product_id = :product_id";
			$params['product_id'] = (int) $filters['product_id'];
			}

		if (isset($filters['movement_type']) && in_array((string) $filters['movement_type'], self::MOVEMENT_TYPES, true))
			{
			$sql .= " AND ProductStockMovement.movement_type = :movement_type";
			$params['movement_type'] = (string) $filters['movement_type'];
			}

		if (isset($filters['reference']) && $filters['reference'] !== '')
			{
			$sql .= " AND ProductStockMovement.reference LIKE :reference";
			$params['reference'] = '%' . $filters['reference'] . '%';
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
	 * Locks and returns a product row for stock updates.
	 */
	private function lockProduct(int $productId)
		{
		$stmt = $this->db->prepare("
			SELECT Product.id,
			       Product.stock_quantity
			FROM products AS Product
			WHERE Product.id = :id
			FOR UPDATE
		");
		$stmt->execute(['id' => $productId]);

		return $stmt->fetch(PDO::FETCH_ASSOC);
		}

	/**
	 * Calculates the new stock quantity for a movement.
	 */
	private function calculateNewQuantity(string $movementType, string $previousQuantity, string $quantity): string
		{
		$previous = (float) $previousQuantity;
		$current = (float) $quantity;

		if ($movementType === 'IN')
			{
			return number_format($previous + $current, 3, '.', '');
			}

		if ($movementType === 'OUT')
			{
			$newQuantity = $previous - $current;
			if ($newQuantity < 0)
				{
				throw new \InvalidArgumentException('A saída não pode deixar o estoque negativo.');
				}

			return number_format($newQuantity, 3, '.', '');
			}

		return number_format(max(0, $current), 3, '.', '');
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
	 * Normalizes movement type values.
	 *
	 * @param mixed $value Raw value.
	 */
	private function normalizeMovementType($value): string
		{
		$type = strtoupper(trim((string) $value));
		return in_array($type, self::MOVEMENT_TYPES, true) ? $type : 'IN';
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
	 * Normalizes nullable money values.
	 *
	 * @param mixed $value Raw value.
	 */
	private function normalizeNullableDecimal($value): ?string
		{
		$text = trim((string) $value);
		if ($text === '')
			{
			return null;
			}

		$numberText = $this->normalizeNumberText($text);
		return number_format(max(0, (float) $numberText), 2, '.', '');
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
	 * Normalizes a browser datetime-local value to SQL datetime.
	 *
	 * @param mixed $value Raw value.
	 */
	private function normalizeDateTime($value): string
		{
		$text = trim((string) $value);
		if ($text === '')
			{
			return date('Y-m-d H:i:s');
			}

		$date = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $text);
		if ($date instanceof \DateTimeImmutable)
			{
			return $date->format('Y-m-d H:i:s');
			}

		return date('Y-m-d H:i:s');
		}
	}
?>
