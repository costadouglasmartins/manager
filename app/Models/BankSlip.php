<?php

namespace App\Models;

use App\Core\Database;
use App\Core\TenantModel;
use PDO;

class BankSlip extends TenantModel
	{
	/**
	 * Returns the manager bank table.
	 */
	private function bankTable(): string
		{
		return sprintf('`%s`.`banks`', Database::getManagerDatabaseName());
		}

	/**
	 * Base select for bank slips.
	 */
	private function baseSelectSql(): string
		{
		return "SELECT BankSlip.*,
		              BankAccount.name AS name,
		              BankAccount.name AS bank_account_name,
		              BankAccount.agency,
		              BankAccount.account,
		              Bank.name AS bank_name,
		              Bank.number AS bank_number,
		              BankFile.file_name AS bank_file_name,
		              BankFile.file_path AS bank_file_path,
		              FinancialOperationPayment.value AS payment_value
		       FROM bank_slips AS BankSlip
		       INNER JOIN bank_accounts AS BankAccount ON BankAccount.id = BankSlip.bank_account_id
		       INNER JOIN " . $this->bankTable() . " AS Bank ON Bank.id = BankAccount.bank_id
		                                                  AND Bank.active = 1
		       LEFT JOIN bank_files AS BankFile ON BankFile.id = BankSlip.bank_file_id
		       LEFT JOIN financial_operations_payments AS FinancialOperationPayment ON FinancialOperationPayment.id = BankSlip.financial_operation_payment_id";
		}

	public function getBankAccountOptions(): array
		{
		$stmt = $this->db->query("
			SELECT BankAccount.id,
			       BankAccount.name,
			       BankAccount.agency,
			       BankAccount.account,
			       Bank.name AS bank_name,
			       Bank.number AS bank_number
			FROM bank_accounts AS BankAccount
			INNER JOIN " . $this->bankTable() . " AS Bank ON Bank.id = BankAccount.bank_id
			                                      AND Bank.active = 1
			WHERE BankAccount.active = 1
			ORDER BY Bank.name ASC, BankAccount.name ASC
		");

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	public function create(array $data): array
		{
		$stmt = $this->db->prepare("
			INSERT INTO bank_slips
			    (`financial_operation_payment_id`, `bank_account_id`, `status`, `document_number`, `our_number`, `portfolio`,
			     `payer_document_type`, `payer_document`, `payer_name`, `payer_street`, `payer_neighborhood`, `payer_city`,
			     `payer_state`, `payer_zip_code`, `amount`, `due_date`, `issue_date`, `interest_amount_per_day`,
			     `discount_amount`, `fine_amount`, `created`, `modified`)
			VALUES
			    (:financial_operation_payment_id, :bank_account_id, :status, :document_number, :our_number, :portfolio,
			     :payer_document_type, :payer_document, :payer_name, :payer_street, :payer_neighborhood, :payer_city,
			     :payer_state, :payer_zip_code, :amount, :due_date, :issue_date, :interest_amount_per_day,
			     :discount_amount, :fine_amount, :created, :modified)
		");

		$stmt->execute([
			'financial_operation_payment_id' => ($data['financial_operation_payment_id'] ?? '') !== '' ? (int) $data['financial_operation_payment_id'] : null,
			'bank_account_id'                => (int) ($data['bank_account_id'] ?? 0),
			'status'                         => trim((string) ($data['status'] ?? 'READY')),
			'document_number'                => trim((string) ($data['document_number'] ?? '')),
			'our_number'                     => trim((string) ($data['our_number'] ?? '')) ?: null,
			'portfolio'                      => trim((string) ($data['portfolio'] ?? '109')),
			'payer_document_type'            => trim((string) ($data['payer_document_type'] ?? 'CPF')),
			'payer_document'                 => preg_replace('/\D+/', '', (string) ($data['payer_document'] ?? '')) ?? '',
			'payer_name'                     => trim((string) ($data['payer_name'] ?? '')),
			'payer_street'                   => trim((string) ($data['payer_street'] ?? '')),
			'payer_neighborhood'             => trim((string) ($data['payer_neighborhood'] ?? '')) ?: null,
			'payer_city'                     => trim((string) ($data['payer_city'] ?? '')),
			'payer_state'                    => strtoupper(trim((string) ($data['payer_state'] ?? ''))),
			'payer_zip_code'                 => preg_replace('/\D+/', '', (string) ($data['payer_zip_code'] ?? '')) ?? '',
			'amount'                         => $this->normalizeDecimal($data['amount'] ?? '0'),
			'due_date'                       => trim((string) ($data['due_date'] ?? date('Y-m-d'))),
			'issue_date'                     => trim((string) ($data['issue_date'] ?? date('Y-m-d'))),
			'interest_amount_per_day'        => $this->normalizeDecimal($data['interest_amount_per_day'] ?? '0'),
			'discount_amount'                => $this->normalizeDecimal($data['discount_amount'] ?? '0'),
			'fine_amount'                    => $this->normalizeDecimal($data['fine_amount'] ?? '0'),
			'created'                        => date('Y-m-d H:i:s'),
			'modified'                       => date('Y-m-d H:i:s'),
		]);

		return ['bank_slip_id' => (int) $this->db->lastInsertId()];
		}

	public function search(array $filters = [], int $limit = 10, int $offset = 0): array
		{
		$sql = $this->baseSelectSql() . " WHERE 1 = 1";
		$params = [];

		if (($filters['id'] ?? '') !== '')
			{
			$sql .= " AND BankSlip.id = :id";
			$params['id'] = (int) $filters['id'];
			}
		$bankAccountIds = $this->bankAccountFilterIds($filters);
		if ($bankAccountIds !== [])
			{
			$placeholders = [];
			foreach ($bankAccountIds as $index => $bankAccountId)
				{
				$key = 'bank_account_id_' . $index;
				$placeholders[] = ':' . $key;
				$params[$key] = $bankAccountId;
				}
			$sql .= " AND BankSlip.bank_account_id IN (" . implode(',', $placeholders) . ")";
			}
		elseif (($filters['bank_account_id'] ?? '') !== '')
			{
			$sql .= " AND BankSlip.bank_account_id = :bank_account_id";
			$params['bank_account_id'] = (int) $filters['bank_account_id'];
			}
		if (($filters['status'] ?? '') !== '')
			{
			$sql .= " AND BankSlip.status = :status";
			$params['status'] = trim((string) $filters['status']);
			}
		if (($filters['document_number'] ?? '') !== '')
			{
			$sql .= " AND BankSlip.document_number LIKE :document_number";
			$params['document_number'] = '%' . trim((string) $filters['document_number']) . '%';
			}

		$sql .= " ORDER BY Bank.name ASC, BankAccount.name ASC, BankSlip.id DESC LIMIT :limit OFFSET :offset";
		$stmt = $this->db->prepare($sql);
		foreach ($params as $key => $value)
			{
			$stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
			}
		$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
		$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
		$stmt->execute();

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	public function count(array $filters = []): int
		{
		$sql = "SELECT COUNT(*)
		        FROM bank_slips AS BankSlip
		        INNER JOIN bank_accounts AS BankAccount ON BankAccount.id = BankSlip.bank_account_id
		        INNER JOIN " . $this->bankTable() . " AS Bank ON Bank.id = BankAccount.bank_id
		                                                   AND Bank.active = 1
		        WHERE 1 = 1";
		$params = [];

		if (($filters['id'] ?? '') !== '')
			{
			$sql .= " AND BankSlip.id = :id";
			$params['id'] = (int) $filters['id'];
			}
		$bankAccountIds = $this->bankAccountFilterIds($filters);
		if ($bankAccountIds !== [])
			{
			$placeholders = [];
			foreach ($bankAccountIds as $index => $bankAccountId)
				{
				$key = 'bank_account_id_' . $index;
				$placeholders[] = ':' . $key;
				$params[$key] = $bankAccountId;
				}
			$sql .= " AND BankSlip.bank_account_id IN (" . implode(',', $placeholders) . ")";
			}
		elseif (($filters['bank_account_id'] ?? '') !== '')
			{
			$sql .= " AND BankSlip.bank_account_id = :bank_account_id";
			$params['bank_account_id'] = (int) $filters['bank_account_id'];
			}
		if (($filters['status'] ?? '') !== '')
			{
			$sql .= " AND BankSlip.status = :status";
			$params['status'] = trim((string) $filters['status']);
			}
		if (($filters['document_number'] ?? '') !== '')
			{
			$sql .= " AND BankSlip.document_number LIKE :document_number";
			$params['document_number'] = '%' . trim((string) $filters['document_number']) . '%';
			}

		$stmt = $this->db->prepare($sql);
		foreach ($params as $key => $value)
			{
			$stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
			}
		$stmt->execute();
		return (int) $stmt->fetchColumn();
		}

	public function find(int $id): array
		{
		$stmt = $this->db->prepare($this->baseSelectSql() . " WHERE BankSlip.id = :id LIMIT 1");
		$stmt->execute(['id' => $id]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		return $row ?: [];
		}

	public function findReadyForRemittance(array $ids): array
		{
		$ids = array_values(array_filter(array_map('intval', $ids), static fn ($id) => $id > 0));
		if ($ids === [])
			{
			return [];
			}

		$placeholders = implode(',', array_fill(0, count($ids), '?'));
		$sql = $this->baseSelectSql() . " WHERE BankSlip.id IN ({$placeholders}) AND BankSlip.status IN ('READY','REJECTED') ORDER BY BankSlip.bank_account_id ASC, BankSlip.id ASC";
		$stmt = $this->db->prepare($sql);
		$stmt->execute($ids);
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	public function attachRemittanceFile(array $ids, int $bankFileId): void
		{
		$ids = array_values(array_filter(array_map('intval', $ids), static fn ($id) => $id > 0));
		if ($ids === [])
			{
			return;
			}

		$placeholders = implode(',', array_fill(0, count($ids), '?'));
		$sql = "UPDATE bank_slips
		        SET bank_file_id = ?, status = 'REMITTANCE_GENERATED', modified = ?
		        WHERE id IN ({$placeholders})";
		$params = array_merge([$bankFileId, date('Y-m-d H:i:s')], $ids);
		$stmt = $this->db->prepare($sql);
		$stmt->execute($params);
		}

	/**
	 * Normalizes the multi-account filter ids.
	 */
	private function bankAccountFilterIds(array $filters): array
		{
		$ids = $filters['bank_account_ids'] ?? [];
		if (!is_array($ids))
			{
			$ids = [$ids];
			}

		$ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn ($id) => $id > 0)));
		return $ids;
		}

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
		$text = preg_replace('/[^0-9.\-]/', '', $text) ?? '0';
		if (!is_numeric($text))
			{
			$text = '0';
			}
		return number_format((float) $text, 2, '.', '');
		}
	}
?>
