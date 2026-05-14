<?php

namespace App\Models;

use App\Core\TenantModel;
use PDO;

class FinancialOperationPayment extends TenantModel
	{
	/**
	 * Returns the base financial operation payment select query with operation data.
	 */
	private function baseSelectSql(): string
		{
		return "SELECT FinancialOperationPayment.id,
		              FinancialOperationPayment.financial_operation_id,
		              FinancialOperationPayment.value,
		              FinancialOperationPayment.created,
		              FinancialOperationPayment.modified,
		              FinancialOperation.total_amount AS financial_operation_total_amount,
		              FinancialOperationCategory.name AS financial_operation_category_name
		       FROM financial_operations_payments AS FinancialOperationPayment
		       INNER JOIN financial_operations AS FinancialOperation ON FinancialOperation.id = FinancialOperationPayment.financial_operation_id
		       INNER JOIN financial_operations_categories AS FinancialOperationCategory ON FinancialOperationCategory.id = FinancialOperation.financial_operation_category_id";
		}

	/**
	 * Creates a financial operation payment and returns the inserted identifier.
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
				INSERT INTO financial_operations_payments
				    (`financial_operation_id`, `value`, `created`, `modified`)
				VALUES
				    (:financial_operation_id, :value, :created, :modified)
			");
			$stmt->execute([
				'financial_operation_id' => (int) ($data['financial_operation_id'] ?? 0),
				'value'                  => $this->normalizeDecimal($data['value'] ?? '0'),
				'created'                => date('Y-m-d H:i:s'),
				'modified'               => date('Y-m-d H:i:s'),
			]);

			$paymentId = (int) $this->db->lastInsertId();
			$this->db->commit();

			return ['financial_operation_payment_id' => $paymentId];
			}
		catch (\Exception $e)
			{
			$this->db->rollBack();
			throw $e;
			}
		}

	/**
	 * Finds a financial operation payment by id.
	 *
	 * @param mixed $id Payment identifier.
	 */
	public function find($id)
		{
		$stmt = $this->db->prepare($this->baseSelectSql() . "
			WHERE FinancialOperationPayment.id = :id
		");
		$stmt->execute(['id' => (int) $id]);

		return $stmt->fetch(PDO::FETCH_ASSOC);
		}

	/**
	 * Updates an existing financial operation payment.
	 *
	 * @param mixed $id Payment identifier.
	 * @param array $data Updated payment data.
	 */
	public function update($id, array $data): void
		{
		$stmt = $this->db->prepare("
			UPDATE financial_operations_payments
			SET `financial_operation_id` = :financial_operation_id,
			    `value` = :value,
			    `modified` = :modified
			WHERE id = :id
		");

		$stmt->execute([
			'financial_operation_id' => (int) ($data['financial_operation_id'] ?? 0),
			'value'                  => $this->normalizeDecimal($data['value'] ?? '0'),
			'modified'               => date('Y-m-d H:i:s'),
			'id'                     => (int) $id,
		]);
		}

	/**
	 * Searches payments using the provided filters and pagination.
	 */
	public function search(array $filters = [], int $limit = 10, int $offset = 0): array
		{
		$sql = $this->baseSelectSql() . "
			WHERE 1 = 1
		";
		$params = [];

		if (isset($filters['id']) && $filters['id'] !== '')
			{
			$sql .= " AND FinancialOperationPayment.id = :id";
			$params['id'] = (int) $filters['id'];
			}

		if (isset($filters['financial_operation_id']) && $filters['financial_operation_id'] !== '')
			{
			$sql .= " AND FinancialOperationPayment.financial_operation_id = :financial_operation_id";
			$params['financial_operation_id'] = (int) $filters['financial_operation_id'];
			}

		if (isset($filters['value']) && $filters['value'] !== '')
			{
			$sql .= " AND FinancialOperationPayment.value = :value";
			$params['value'] = $this->normalizeDecimal($filters['value']);
			}

		$sql .= "
			ORDER BY FinancialOperationPayment.id DESC
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
	 * Counts payments that match the provided filters.
	 */
	public function count(array $filters = []): int
		{
		$sql = "SELECT COUNT(*) AS total
		        FROM financial_operations_payments AS FinancialOperationPayment
		        INNER JOIN financial_operations AS FinancialOperation ON FinancialOperation.id = FinancialOperationPayment.financial_operation_id
		        INNER JOIN financial_operations_categories AS FinancialOperationCategory ON FinancialOperationCategory.id = FinancialOperation.financial_operation_category_id
		        WHERE 1 = 1";
		$params = [];

		if (isset($filters['id']) && $filters['id'] !== '')
			{
			$sql .= " AND FinancialOperationPayment.id = :id";
			$params['id'] = (int) $filters['id'];
			}

		if (isset($filters['financial_operation_id']) && $filters['financial_operation_id'] !== '')
			{
			$sql .= " AND FinancialOperationPayment.financial_operation_id = :financial_operation_id";
			$params['financial_operation_id'] = (int) $filters['financial_operation_id'];
			}

		if (isset($filters['value']) && $filters['value'] !== '')
			{
			$sql .= " AND FinancialOperationPayment.value = :value";
			$params['value'] = $this->normalizeDecimal($filters['value']);
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
	 * Returns the payment details payload used by the information view.
	 *
	 * @param mixed $id Payment identifier.
	 */
	public function findAllByFinancialOperationPaymentId($id): array
		{
		return ['financial_operation_payment' => $this->find($id)];
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
