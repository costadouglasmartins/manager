<?php

namespace App\Models;

use App\Core\TenantModel;
use PDO;

class FinancialOperation extends TenantModel
	{
	private const INCOME_CATEGORY_TERMS = [
		'receita',
		'renda',
		'salario',
		'salário',
		'13',
		'ferias',
		'férias',
	];

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
	 * Builds the annual spreadsheet grouped by financial category and subcategory.
	 */
	public function yearlySpreadsheet(int $year): array
		{
		$year = max(1900, min(2100, $year));
		$rows = $this->spreadsheetBaseRows();
		$amounts = $this->spreadsheetAmounts($year);

		foreach ($amounts as $amount)
			{
			$key = $this->spreadsheetRowKey(
				(int) $amount['financial_operation_category_id'],
				$amount['financial_operation_subcategory_id'] !== null ? (int) $amount['financial_operation_subcategory_id'] : null
			);

			if (!isset($rows[$key]))
				{
				$rows[$key] = [
					'key'              => $key,
					'section'          => $this->sectionForCategory((string) $amount['category_name']),
					'category_id'      => (int) $amount['financial_operation_category_id'],
					'subcategory_id'   => $amount['financial_operation_subcategory_id'] !== null ? (int) $amount['financial_operation_subcategory_id'] : null,
					'category_name'    => (string) $amount['category_name'],
					'subcategory_name' => $amount['subcategory_name'] !== null ? (string) $amount['subcategory_name'] : '',
					'label'            => $this->spreadsheetRowLabel((string) $amount['category_name'], $amount['subcategory_name'] !== null ? (string) $amount['subcategory_name'] : ''),
					'months'           => array_fill(1, 12, 0.0),
					'total'            => 0.0,
				];
			}

			$month = (int) $amount['month_number'];
			if ($month < 1 || $month > 12)
				{
				continue;
				}

			$value = (float) $amount['amount'];
			$rows[$key]['months'][$month] += $value;
			$rows[$key]['total'] += $value;
			}

		$sections = [
			'income'  => [
				'title'        => 'RECEITAS',
				'rows'         => [],
				'month_totals' => array_fill(1, 12, 0.0),
				'total'        => 0.0,
			],
			'expense' => [
				'title'        => 'DESPESAS',
				'rows'         => [],
				'month_totals' => array_fill(1, 12, 0.0),
				'total'        => 0.0,
			],
		];

		foreach ($rows as $row)
			{
			$section = $row['section'];
			$sections[$section]['rows'][] = $row;

			foreach ($row['months'] as $month => $value)
				{
				$sections[$section]['month_totals'][$month] += (float) $value;
				}

			$sections[$section]['total'] += (float) $row['total'];
			}

		foreach ($sections as &$section)
			{
			usort($section['rows'], function (array $left, array $right): int {
				return strcasecmp($left['label'], $right['label']);
			});
			}
		unset($section);

		$balanceMonths = [];
		$expensePercentMonths = [];

		for ($month = 1; $month <= 12; $month++)
			{
			$income = (float) $sections['income']['month_totals'][$month];
			$expense = (float) $sections['expense']['month_totals'][$month];
			$balanceMonths[$month] = $income - $expense;
			$expensePercentMonths[$month] = $income > 0 ? ($expense / $income) * 100 : null;
			}

		return [
			'year'                    => $year,
			'months'                  => $this->spreadsheetMonths(),
			'sections'                => $sections,
			'balance_months'          => $balanceMonths,
			'balance_total'           => $sections['income']['total'] - $sections['expense']['total'],
			'expense_percent_months'  => $expensePercentMonths,
			'expense_percent_total'   => $sections['income']['total'] > 0 ? ($sections['expense']['total'] / $sections['income']['total']) * 100 : null,
		];
		}

	/**
	 * Returns all categories/subcategories as spreadsheet rows, even before launches exist.
	 */
	private function spreadsheetBaseRows(): array
		{
		$stmt = $this->db->query("
			SELECT FinancialOperationCategory.id AS category_id,
			       FinancialOperationCategory.name AS category_name,
			       FinancialOperationSubcategory.id AS subcategory_id,
			       FinancialOperationSubcategory.name AS subcategory_name
			FROM financial_operations_categories AS FinancialOperationCategory
			LEFT JOIN financial_operations_subcategories AS FinancialOperationSubcategory ON FinancialOperationSubcategory.financial_operation_category_id = FinancialOperationCategory.id
			ORDER BY FinancialOperationCategory.name ASC,
			         FinancialOperationSubcategory.name ASC
		");

		$rows = [];

		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row)
			{
			$categoryName = (string) $row['category_name'];
			$subcategoryName = $row['subcategory_name'] !== null ? (string) $row['subcategory_name'] : '';
			$key = $this->spreadsheetRowKey((int) $row['category_id'], $row['subcategory_id'] !== null ? (int) $row['subcategory_id'] : null);

			$rows[$key] = [
				'key'              => $key,
				'section'          => $this->sectionForCategory($categoryName),
				'category_id'      => (int) $row['category_id'],
				'subcategory_id'   => $row['subcategory_id'] !== null ? (int) $row['subcategory_id'] : null,
				'category_name'    => $categoryName,
				'subcategory_name' => $subcategoryName,
				'label'            => $this->spreadsheetRowLabel($categoryName, $subcategoryName),
				'months'           => array_fill(1, 12, 0.0),
				'total'            => 0.0,
			];
			}

		return $rows;
		}

	/**
	 * Returns operation/payment amounts grouped by row and month for the requested year.
	 */
	private function spreadsheetAmounts(int $year): array
		{
		$stmt = $this->db->prepare("
			SELECT FinancialOperation.financial_operation_category_id,
			       FinancialOperation.financial_operation_subcategory_id,
			       FinancialOperationCategory.name AS category_name,
			       FinancialOperationSubcategory.name AS subcategory_name,
			       MONTH(COALESCE(FinancialOperationPayment.created, FinancialOperation.created)) AS month_number,
			       SUM(CASE
			             WHEN FinancialOperationPayment.id IS NULL THEN FinancialOperation.total_amount
			             ELSE FinancialOperationPayment.value
			           END) AS amount
			FROM financial_operations AS FinancialOperation
			INNER JOIN financial_operations_categories AS FinancialOperationCategory ON FinancialOperationCategory.id = FinancialOperation.financial_operation_category_id
			LEFT JOIN financial_operations_subcategories AS FinancialOperationSubcategory ON FinancialOperationSubcategory.id = FinancialOperation.financial_operation_subcategory_id
			LEFT JOIN financial_operations_payments AS FinancialOperationPayment ON FinancialOperationPayment.financial_operation_id = FinancialOperation.id
			WHERE YEAR(COALESCE(FinancialOperationPayment.created, FinancialOperation.created)) = :year
			GROUP BY FinancialOperation.financial_operation_category_id,
			         FinancialOperation.financial_operation_subcategory_id,
			         FinancialOperationCategory.name,
			         FinancialOperationSubcategory.name,
			         MONTH(COALESCE(FinancialOperationPayment.created, FinancialOperation.created))
			ORDER BY FinancialOperationCategory.name ASC,
			         FinancialOperationSubcategory.name ASC,
			         month_number ASC
		");
		$stmt->execute(['year' => $year]);

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Returns the static month labels used by the spreadsheet.
	 */
	private function spreadsheetMonths(): array
		{
		return [
			1  => 'JANEIRO',
			2  => 'FEVEREIRO',
			3  => 'MARÇO',
			4  => 'ABRIL',
			5  => 'MAIO',
			6  => 'JUNHO',
			7  => 'JULHO',
			8  => 'AGOSTO',
			9  => 'SETEMBRO',
			10 => 'OUTUBRO',
			11 => 'NOVEMBRO',
			12 => 'DEZEMBRO',
		];
		}

	/**
	 * Builds a stable row key for category/subcategory groups.
	 */
	private function spreadsheetRowKey(int $categoryId, ?int $subcategoryId): string
		{
		return $categoryId . ':' . ($subcategoryId ?? 0);
		}

	/**
	 * Formats the spreadsheet row label.
	 */
	private function spreadsheetRowLabel(string $categoryName, string $subcategoryName): string
		{
		$categoryName = trim($categoryName);
		$subcategoryName = trim($subcategoryName);

		if ($subcategoryName === '')
			{
			return $categoryName;
			}

		return $categoryName . ' - ' . $subcategoryName;
		}

	/**
	 * Classifies a category between income and expense sections.
	 */
	private function sectionForCategory(string $categoryName): string
		{
		$normalized = $this->normalizeCategoryName($categoryName);

		foreach (self::INCOME_CATEGORY_TERMS as $term)
			{
			if (str_contains($normalized, $this->normalizeCategoryName($term)))
				{
				return 'income';
				}
			}

		return 'expense';
		}

	/**
	 * Normalizes category text for section classification.
	 */
	private function normalizeCategoryName(string $categoryName): string
		{
		$converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', trim($categoryName));
		$text = $converted !== false ? $converted : trim($categoryName);

		return strtolower($text);
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
