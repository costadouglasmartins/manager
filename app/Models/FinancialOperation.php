<?php

namespace App\Models;

use App\Core\TenantModel;
use PDO;

class FinancialOperation extends TenantModel
	{
	/**
	 * Returns the base financial operation select query with category data.
	 */
	private function baseSelectSql(): string
		{
		return "SELECT FinancialOperation.id,
		              FinancialOperation.financial_operation_category_id,
		              FinancialOperation.total_amount,
		              FinancialOperation.created,
		              FinancialOperation.modified,
		              FinancialOperationCategory.name AS financial_operation_category_name
		       FROM financial_operations AS FinancialOperation
		       INNER JOIN financial_operations_categories AS FinancialOperationCategory ON FinancialOperationCategory.id = FinancialOperation.financial_operation_category_id";
		}

	/**
	 * Creates a financial operation and returns the inserted identifier.
	 *
	 * @param array $data Data to save.
	 * @return array
	 * @throws \Exception
	 */
	public function create($data): array
		{
		try
			{
			$this->db->beginTransaction();

			$stmt = $this->db->prepare("
				INSERT INTO financial_operations
				    (`financial_operation_category_id`, `total_amount`, `created`, `modified`)
				VALUES
				    (:financial_operation_category_id, :total_amount, :created, :modified)
			");
			$stmt->execute([
				'financial_operation_category_id' => (int) ($data['financial_operation_category_id'] ?? 0),
				'total_amount'                    => $this->normalizeDecimal($data['total_amount'] ?? '0'),
				'created'                         => date('Y-m-d H:i:s'),
				'modified'                        => date('Y-m-d H:i:s'),
			]);

			$operationId = (int) $this->db->lastInsertId();
			$this->db->commit();

			return ['financial_operation_id' => $operationId];
			}
		catch (\Exception $e)
			{
			$this->db->rollBack();
			throw $e;
			}
		}

	/**
	 * Returns all operations ordered by newest first.
	 */
	public function getOptions(): array
		{
		$stmt = $this->db->query($this->baseSelectSql() . "
			ORDER BY FinancialOperation.id DESC
		");

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Finds a financial operation by id.
	 *
	 * @param mixed $id Operation identifier.
	 */
	public function find($id)
		{
		$stmt = $this->db->prepare($this->baseSelectSql() . "
			WHERE FinancialOperation.id = :id
		");
		$stmt->execute(['id' => (int) $id]);

		return $stmt->fetch(PDO::FETCH_ASSOC);
		}

	/**
	 * Updates an existing financial operation.
	 *
	 * @param mixed $id Operation identifier.
	 * @param array $data Updated operation data.
	 */
	public function update($id, array $data): void
		{
		$stmt = $this->db->prepare("
			UPDATE financial_operations
			SET `financial_operation_category_id` = :financial_operation_category_id,
			    `total_amount` = :total_amount,
			    `modified` = :modified
			WHERE id = :id
		");

		$stmt->execute([
			'financial_operation_category_id' => (int) ($data['financial_operation_category_id'] ?? 0),
			'total_amount'                    => $this->normalizeDecimal($data['total_amount'] ?? '0'),
			'modified'                        => date('Y-m-d H:i:s'),
			'id'                              => (int) $id,
		]);
		}

	/**
	 * Searches operations using the provided filters and pagination.
	 */
	public function search(array $filters = [], int $limit = 10, int $offset = 0): array
		{
		$sql = $this->baseSelectSql() . "
			WHERE 1 = 1
		";
		$params = [];

		if (isset($filters['id']) && $filters['id'] !== '')
			{
			$sql .= " AND FinancialOperation.id = :id";
			$params['id'] = (int) $filters['id'];
			}

		if (isset($filters['financial_operation_category_id']) && $filters['financial_operation_category_id'] !== '')
			{
			$sql .= " AND FinancialOperation.financial_operation_category_id = :financial_operation_category_id";
			$params['financial_operation_category_id'] = (int) $filters['financial_operation_category_id'];
			}

		if (isset($filters['total_amount']) && $filters['total_amount'] !== '')
			{
			$sql .= " AND FinancialOperation.total_amount = :total_amount";
			$params['total_amount'] = $this->normalizeDecimal($filters['total_amount']);
			}

		$sql .= "
			ORDER BY FinancialOperation.id DESC
			LIMIT :limit OFFSET :offset
		";

		$stmt = $this->db->prepare($sql);

		foreach ($params as $key => $value)
			{
			$paramType = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
			$stmt->bindValue(':' . $key, $value, $paramType);
			}

		$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
		$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
		$stmt->execute();

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Counts operations that match the provided filters.
	 */
	public function count(array $filters = []): int
		{
		$sql = "SELECT COUNT(*) AS total
		        FROM financial_operations AS FinancialOperation
		        INNER JOIN financial_operations_categories AS FinancialOperationCategory ON FinancialOperationCategory.id = FinancialOperation.financial_operation_category_id
		        WHERE 1 = 1";
		$params = [];

		if (isset($filters['id']) && $filters['id'] !== '')
			{
			$sql .= " AND FinancialOperation.id = :id";
			$params['id'] = (int) $filters['id'];
			}

		if (isset($filters['financial_operation_category_id']) && $filters['financial_operation_category_id'] !== '')
			{
			$sql .= " AND FinancialOperation.financial_operation_category_id = :financial_operation_category_id";
			$params['financial_operation_category_id'] = (int) $filters['financial_operation_category_id'];
			}

		if (isset($filters['total_amount']) && $filters['total_amount'] !== '')
			{
			$sql .= " AND FinancialOperation.total_amount = :total_amount";
			$params['total_amount'] = $this->normalizeDecimal($filters['total_amount']);
			}

		$stmt = $this->db->prepare($sql);

		foreach ($params as $key => $value)
			{
			$paramType = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
			$stmt->bindValue(':' . $key, $value, $paramType);
			}

		$stmt->execute();

		return (int) $stmt->fetchColumn();
		}

	/**
	 * Returns payments for a financial operation.
	 *
	 * @param mixed $id Operation identifier.
	 */
	public function getPayments($id): array
		{
		$stmt = $this->db->prepare("
			SELECT FinancialOperationPayment.id,
			       FinancialOperationPayment.financial_operation_id,
			       FinancialOperationPayment.value,
			       FinancialOperationPayment.created,
			       FinancialOperationPayment.modified
			FROM financial_operations_payments AS FinancialOperationPayment
			WHERE FinancialOperationPayment.financial_operation_id = :financial_operation_id
			ORDER BY FinancialOperationPayment.id DESC
		");
		$stmt->execute(['financial_operation_id' => (int) $id]);

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Returns the operation details payload used by the information view.
	 *
	 * @param mixed $id Operation identifier.
	 */
	public function findAllByFinancialOperationId($id): array
		{
		return [
			'financial_operation' => $this->find($id),
			'payments'           => $this->getPayments($id),
		];
		}

	/**
	 * Normalizes decimal values submitted by Portuguese or database-style inputs.
	 *
	 * @param mixed $value Raw value.
	 */
	private function normalizeDecimal($value): string
		{
		$text = trim((string) $value);
		$text = str_replace(['R$', ' '], '', $text);

		if ($text === '')
			{
			return '0.00';
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

		if (!is_numeric($text))
			{
			$text = '0';
			}

		return number_format((float) $text, 2, '.', '');
		}
	}
?>
