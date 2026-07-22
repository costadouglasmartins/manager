<?php

namespace App\Models;

use App\Core\TenantModel;
use PDO;

class ProductStockMovement extends TenantModel
	{
	private function baseSelectSql(): string
		{
		return "SELECT Movement.id, Movement.product_item_id AS product_id,
			Movement.product_item_variation_id AS variation_id, Movement.movement_type,
			Movement.quantity, Movement.previous_quantity, Movement.new_quantity,
			Movement.reference, Movement.notes AS observations, Movement.movement_date,
			Movement.created, Product.name AS product_name, Product.sku AS product_sku,
			Variation.name AS variation_name, Variation.sku AS variation_sku,
			Unit.code AS product_unit, Unit.symbol AS product_unit_symbol
			FROM product_stock_movements AS Movement
			INNER JOIN product_items AS Product ON Product.id = Movement.product_item_id
			LEFT JOIN product_item_variations AS Variation ON Variation.id = Movement.product_item_variation_id
			INNER JOIN product_units AS Unit ON Unit.id = Product.sale_unit_id";
		}

	public function create(array $data): array
		{
		$payload = $this->normalizePayload($data);
		try
			{
			$this->db->beginTransaction();
			$target = $this->lockTarget($payload['product_id'], $payload['variation_id']);
			[$previous, $new] = $this->calculateBalance($target['stock_quantity'], $payload['movement_type'], $payload['quantity']);
			$stmt = $this->db->prepare("INSERT INTO product_stock_movements
				(product_item_id, product_item_variation_id, movement_type, quantity,
				 previous_quantity, new_quantity, reference, notes, movement_date, created)
				VALUES (:product_id, :variation_id, :movement_type, :quantity,
				 :previous_quantity, :new_quantity, :reference, :notes, :movement_date, NOW())");
			$stmt->execute($payload + ['previous_quantity' => $previous, 'new_quantity' => $new]);
			$id = (int) $this->db->lastInsertId();
			$this->updateTargetStock($payload['product_id'], $payload['variation_id'], $new);
			$this->auditCreate('product_stock_movement', 'product_stock_movements', $id, $this->findAllByProductStockMovementId($id), [
				'affected_tables' => ['product_stock_movements', $payload['variation_id'] ? 'product_item_variations' : 'product_items'],
				'product_item_id' => $payload['product_id'],
				'product_item_variation_id' => $payload['variation_id'],
			]);
			$this->db->commit();
			return ['product_stock_movement_id' => $id];
			}
		catch (\Throwable $e)
			{
			if ($this->db->inTransaction()) $this->db->rollBack();
			throw $e;
			}
		}

	public function find($id)
		{
		$stmt = $this->db->prepare($this->baseSelectSql() . ' WHERE Movement.id = :id');
		$stmt->execute(['id' => (int) $id]);
		return $stmt->fetch(PDO::FETCH_ASSOC);
		}

	public function update($id, array $data): void
		{
		$movementId = (int) $id;
		$payload = $this->normalizePayload($data);
		try
			{
			$this->db->beginTransaction();
			$before = $this->find($movementId);
			if (!$before) throw new \InvalidArgumentException('Movimentação não encontrada.');
			$oldProductId = (int) $before['product_id'];
			$oldVariationId = (int) ($before['variation_id'] ?? 0) ?: null;
			if ($payload['product_id'] !== $oldProductId || $payload['variation_id'] !== $oldVariationId) throw new \RuntimeException('O produto ou a variação da movimentação não pode ser alterado.');
			$this->assertLatestMovement($movementId, $oldProductId, $oldVariationId);
			$target = $this->lockTarget($oldProductId, $oldVariationId);
			$oldDelta = (float) $before['new_quantity'] - (float) $before['previous_quantity'];
			$restored = (float) $target['stock_quantity'] - $oldDelta;
			if ($restored < -0.0000005) throw new \RuntimeException('A alteração deixaria o estoque anterior negativo.');
			[$previous, $new] = $this->calculateBalance($this->formatQuantity(max(0, $restored)), $payload['movement_type'], $payload['quantity']);
			$stmt = $this->db->prepare("UPDATE product_stock_movements SET
				movement_type = :movement_type, quantity = :quantity, previous_quantity = :previous_quantity,
				new_quantity = :new_quantity, reference = :reference, notes = :notes,
				movement_date = :movement_date WHERE id = :id");
			$stmt->execute([
				'movement_type' => $payload['movement_type'], 'quantity' => $payload['quantity'],
				'previous_quantity' => $previous, 'new_quantity' => $new, 'reference' => $payload['reference'],
				'notes' => $payload['notes'], 'movement_date' => $payload['movement_date'], 'id' => $movementId,
			]);
			$this->updateTargetStock($oldProductId, $oldVariationId, $new);
			$after = $this->findAllByProductStockMovementId($movementId);
			$this->auditUpdate('product_stock_movement', 'product_stock_movements', $movementId, $before, $after, [
				'affected_tables' => ['product_stock_movements', $oldVariationId ? 'product_item_variations' : 'product_items'],
				'product_item_id' => $oldProductId, 'product_item_variation_id' => $oldVariationId,
			]);
			$this->db->commit();
			}
		catch (\Throwable $e)
			{
			if ($this->db->inTransaction()) $this->db->rollBack();
			throw $e;
			}
		}

	public function delete($id): void
		{
		$movementId = (int) $id;
		try
			{
			$this->db->beginTransaction();
			$before = $this->find($movementId);
			if (!$before) throw new \InvalidArgumentException('Movimentação não encontrada.');
			$productId = (int) $before['product_id'];
			$variationId = (int) ($before['variation_id'] ?? 0) ?: null;
			$this->assertLatestMovement($movementId, $productId, $variationId);
			$target = $this->lockTarget($productId, $variationId);
			$delta = (float) $before['new_quantity'] - (float) $before['previous_quantity'];
			$restored = (float) $target['stock_quantity'] - $delta;
			if ($restored < -0.0000005) throw new \RuntimeException('Não é possível excluir: o estoque resultante seria negativo.');
			$this->db->prepare('DELETE FROM product_stock_movements WHERE id = :id')->execute(['id' => $movementId]);
			$this->updateTargetStock($productId, $variationId, $this->formatQuantity(max(0, $restored)));
			$this->auditDelete('product_stock_movement', 'product_stock_movements', $movementId, $before, [
				'affected_tables' => ['product_stock_movements', $variationId ? 'product_item_variations' : 'product_items'],
				'product_item_id' => $productId, 'product_item_variation_id' => $variationId,
			]);
			$this->db->commit();
			}
		catch (\Throwable $e)
			{
			if ($this->db->inTransaction()) $this->db->rollBack();
			throw $e;
			}
		}

	public function search(array $filters = [], int $limit = 10, int $offset = 0): array
		{
		$sql = $this->baseSelectSql() . ' WHERE 1 = 1';
		$params = [];
		$this->appendFilters($sql, $params, $filters);
		$sql .= ' ORDER BY Movement.id DESC LIMIT :limit OFFSET :offset';
		$stmt = $this->db->prepare($sql);
		$this->bindParams($stmt, $params);
		$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
		$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
		$stmt->execute();
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	public function count(array $filters = []): int
		{
		$sql = 'SELECT COUNT(*) FROM product_stock_movements AS Movement WHERE 1 = 1';
		$params = [];
		$this->appendFilters($sql, $params, $filters);
		$stmt = $this->db->prepare($sql);
		$this->bindParams($stmt, $params);
		$stmt->execute();
		return (int) $stmt->fetchColumn();
		}

	public function findAllByProductStockMovementId($id): array
		{
		return ['product_stock_movement' => $this->find($id)];
		}

	private function normalizePayload(array $data): array
		{
		$productId = (int) ($data['product_id'] ?? $data['product_item_id'] ?? 0);
		if ($productId <= 0) throw new \InvalidArgumentException('Selecione um produto.');
		$variationId = (int) ($data['variation_id'] ?? $data['product_item_variation_id'] ?? 0) ?: null;
		$type = strtoupper(trim((string) ($data['movement_type'] ?? '')));
		if (!in_array($type, ['IN', 'OUT', 'ADJUSTMENT'], true)) throw new \InvalidArgumentException('Selecione um tipo de movimentação válido.');
		$quantity = $this->normalizeQuantity($data['quantity'] ?? '');
		$date = trim((string) ($data['movement_date'] ?? ''));
		if ($date === '') $date = date('Y-m-d H:i:s');
		else
			{
			$date = str_replace('T', ' ', $date);
			if (strlen($date) === 16) $date .= ':00';
			$parsed = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $date);
			if (!$parsed || $parsed->format('Y-m-d H:i:s') !== $date) throw new \InvalidArgumentException('Informe uma data válida.');
			}
		return [
			'product_id' => $productId,
			'variation_id' => $variationId,
			'movement_type' => $type,
			'quantity' => $quantity,
			'reference' => $this->nullableString($data['reference'] ?? null, 120),
			'notes' => sanitizeRichText($data['notes'] ?? $data['observations'] ?? null),
			'movement_date' => $date,
		];
		}

	private function lockTarget(int $productId, ?int $variationId): array
		{
		$productStmt = $this->db->prepare('SELECT id, stock_quantity, track_stock FROM product_items WHERE id = :id FOR UPDATE');
		$productStmt->execute(['id' => $productId]);
		$product = $productStmt->fetch(PDO::FETCH_ASSOC);
		if (!$product) throw new \InvalidArgumentException('Produto não encontrado.');
		if ($variationId === null)
			{
			if ((int) $product['track_stock'] !== 1) throw new \RuntimeException('O produto principal não possui controle de estoque. Selecione uma variação.');
			return $product;
			}
		$stmt = $this->db->prepare('SELECT id, stock_quantity, track_stock FROM product_item_variations WHERE id = :id AND product_item_id = :product_id FOR UPDATE');
		$stmt->execute(['id' => $variationId, 'product_id' => $productId]);
		$variation = $stmt->fetch(PDO::FETCH_ASSOC);
		if (!$variation) throw new \InvalidArgumentException('Variação não encontrada para o produto selecionado.');
		if ((int) $variation['track_stock'] !== 1) throw new \RuntimeException('Esta variação não possui controle de estoque.');
		return $variation;
		}

	private function calculateBalance($current, string $type, string $quantity): array
		{
		$previous = (float) $current;
		$amount = (float) $quantity;
		$new = $type === 'IN' ? $previous + $amount : ($type === 'OUT' ? $previous - $amount : $amount);
		if ($new < -0.0000005) throw new \RuntimeException('A saída informada é maior que o estoque disponível.');
		return [$this->formatQuantity($previous), $this->formatQuantity(max(0, $new))];
		}

	private function normalizeQuantity($value): string
		{
		$value = trim((string) $value);
		if (str_contains($value, ',') && str_contains($value, '.')) $value = str_replace('.', '', $value);
		$value = str_replace(',', '.', $value);
		if (!is_numeric($value) || (float) $value < 0) throw new \InvalidArgumentException('Informe uma quantidade válida.');
		return $this->formatQuantity((float) $value);
		}

	private function formatQuantity(float $value): string
		{
		return number_format($value, 6, '.', '');
		}

	private function nullableString($value, int $length): ?string
		{
		$value = trim((string) $value);
		return $value === '' ? null : mb_substr($value, 0, $length);
		}

	private function updateTargetStock(int $productId, ?int $variationId, string $quantity): void
		{
		if ($variationId !== null)
			{
			$this->db->prepare('UPDATE product_item_variations SET stock_quantity = :quantity, modified = NOW() WHERE id = :id AND product_item_id = :product_id')
				->execute(['quantity' => $quantity, 'id' => $variationId, 'product_id' => $productId]);
			return;
			}
		$this->db->prepare('UPDATE product_items SET stock_quantity = :quantity, modified = NOW() WHERE id = :id')
			->execute(['quantity' => $quantity, 'id' => $productId]);
		}

	private function assertLatestMovement(int $movementId, int $productId, ?int $variationId): void
		{
		$stmt = $this->db->prepare('SELECT MAX(id) FROM product_stock_movements WHERE product_item_id = :product_id AND product_item_variation_id <=> :variation_id');
		$stmt->execute(['product_id' => $productId, 'variation_id' => $variationId]);
		if ((int) $stmt->fetchColumn() !== $movementId) throw new \RuntimeException('Somente a movimentação mais recente deste item de estoque pode ser alterada ou excluída.');
		}

	private function appendFilters(string &$sql, array &$params, array $filters): void
		{
		if (($filters['id'] ?? '') !== '') { $sql .= ' AND Movement.id = :id'; $params['id'] = (int) $filters['id']; }
		if (($filters['product_id'] ?? '') !== '') { $sql .= ' AND Movement.product_item_id = :product_id'; $params['product_id'] = (int) $filters['product_id']; }
		if (($filters['variation_id'] ?? '') !== '') { $sql .= ' AND Movement.product_item_variation_id = :variation_id'; $params['variation_id'] = (int) $filters['variation_id']; }
		if (($filters['movement_type'] ?? '') !== '') { $sql .= ' AND Movement.movement_type = :movement_type'; $params['movement_type'] = strtoupper((string) $filters['movement_type']); }
		if (($filters['reference'] ?? '') !== '') { $sql .= ' AND Movement.reference LIKE :reference'; $params['reference'] = '%' . trim((string) $filters['reference']) . '%'; }
		}

	private function bindParams(\PDOStatement $stmt, array $params): void
		{
		foreach ($params as $key => $value) $stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
		}
	}
?>
