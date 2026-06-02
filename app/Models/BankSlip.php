<?php

namespace App\Models;

use App\Core\Database;
use App\Core\TenantModel;
use PDO;

class BankSlip extends TenantModel
	{
	public const STATUSES = ['DRAFT', 'READY', 'REMITTANCE_GENERATED', 'SENT', 'REGISTERED', 'REJECTED', 'PAID', 'CANCELED', 'WRITTEN_OFF'];

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
		              BankAccount.bank_slip_instructions_line_1,
		              BankAccount.bank_slip_instructions_line_2,
		              BankAccount.bank_slip_instructions_line_3,
		              BankAccount.bank_slip_instructions_line_4,
		              BankAccount.bank_slip_protest,
		              BankAccount.bank_slip_protest_number_of_days,
		              BankAccount.bank_slip_wallet,
		              BankAccount.bank_slip_species_title,
		              BankAccount.bank_slip_interest_type,
		              BankAccount.bank_slip_interest_amount_type,
		              BankAccount.bank_slip_interest,
		              BankAccount.bank_slip_late_fee_amount,
		              Bank.name AS bank_name,
		              Bank.number AS bank_number,
		              Bank.cnab AS bank_cnab,
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
			       Bank.number AS bank_number,
			       Bank.cnab AS bank_cnab
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
		$payload = $this->normalizePayload($data);

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
			'financial_operation_payment_id' => $payload['financial_operation_payment_id'],
			'bank_account_id'                => $payload['bank_account_id'],
			'status'                         => $payload['status'],
			'document_number'                => $payload['document_number'],
			'our_number'                     => $payload['our_number'],
			'portfolio'                      => $payload['portfolio'],
			'payer_document_type'            => $payload['payer_document_type'],
			'payer_document'                 => $payload['payer_document'],
			'payer_name'                     => $payload['payer_name'],
			'payer_street'                   => $payload['payer_street'],
			'payer_neighborhood'             => $payload['payer_neighborhood'],
			'payer_city'                     => $payload['payer_city'],
			'payer_state'                    => $payload['payer_state'],
			'payer_zip_code'                 => $payload['payer_zip_code'],
			'amount'                         => $payload['amount'],
			'due_date'                       => $payload['due_date'],
			'issue_date'                     => $payload['issue_date'],
			'interest_amount_per_day'        => $payload['interest_amount_per_day'],
			'discount_amount'                => $payload['discount_amount'],
			'fine_amount'                    => $payload['fine_amount'],
			'created'                        => date('Y-m-d H:i:s'),
			'modified'                       => date('Y-m-d H:i:s'),
		]);

		return ['bank_slip_id' => (int) $this->db->lastInsertId()];
		}

	/**
	 * Updates an existing draft bank slip.
	 *
	 * @param mixed $id Bank slip identifier.
	 */
	public function update($id, array $data): void
		{
		$bankSlipId = (int) $id;
		if ($bankSlipId <= 0)
			{
			throw new \InvalidArgumentException('Boleto inválido.');
			}

		$payload = $this->normalizePayload($data);

		try
			{
			$this->db->beginTransaction();

			$bankSlip = $this->find($bankSlipId);
			if (!$bankSlip)
				{
				throw new \InvalidArgumentException('Boleto não encontrado.');
				}

			if ((string) ($bankSlip['status'] ?? '') !== 'DRAFT')
				{
				throw new \RuntimeException('Apenas boletos com status DRAFT podem ser editados.');
				}

			$stmt = $this->db->prepare("
				UPDATE bank_slips
				SET `financial_operation_payment_id` = :financial_operation_payment_id,
				    `bank_account_id` = :bank_account_id,
				    `status` = :status,
				    `document_number` = :document_number,
				    `our_number` = :our_number,
				    `portfolio` = :portfolio,
				    `payer_document_type` = :payer_document_type,
				    `payer_document` = :payer_document,
				    `payer_name` = :payer_name,
				    `payer_street` = :payer_street,
				    `payer_neighborhood` = :payer_neighborhood,
				    `payer_city` = :payer_city,
				    `payer_state` = :payer_state,
				    `payer_zip_code` = :payer_zip_code,
				    `amount` = :amount,
				    `due_date` = :due_date,
				    `issue_date` = :issue_date,
				    `interest_amount_per_day` = :interest_amount_per_day,
				    `discount_amount` = :discount_amount,
				    `fine_amount` = :fine_amount,
				    `modified` = :modified
				WHERE id = :id
			");
			$payload['modified'] = date('Y-m-d H:i:s');
			$payload['id'] = $bankSlipId;
			$stmt->execute($payload);

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

	/**
	 * Returns all bank slips ready for remittance in a bank account.
	 */
	public function findReadyForRemittanceByBankAccount(int $bankAccountId): array
		{
		if ($bankAccountId <= 0)
			{
			return [];
			}

		$sql = $this->baseSelectSql() . "
			WHERE BankSlip.bank_account_id = :bank_account_id
			  AND BankSlip.status IN ('READY','REJECTED')
			ORDER BY BankSlip.id ASC
		";
		$stmt = $this->db->prepare($sql);
		$stmt->execute(['bank_account_id' => $bankAccountId]);

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
	 * Updates the current status of an existing bank slip.
	 *
	 * @param mixed $id Bank slip identifier.
	 */
	public function updateStatus($id, string $status): array
		{
		$bankSlipId = (int) $id;
		if ($bankSlipId <= 0)
			{
			throw new \InvalidArgumentException('Boleto inválido.');
			}

		$status = strtoupper(trim($status));
		if (!in_array($status, self::STATUSES, true))
			{
			throw new \InvalidArgumentException('Status inválido para o boleto.');
			}

		$bankSlip = $this->find($bankSlipId);
		if ($bankSlip === [])
			{
			throw new \InvalidArgumentException('Boleto não encontrado.');
			}

		$stmt = $this->db->prepare("
			UPDATE bank_slips
			SET `status` = :status,
			    `modified` = :modified
			WHERE id = :id
		");
		$stmt->execute([
			'status' => $status,
			'modified' => date('Y-m-d H:i:s'),
			'id' => $bankSlipId,
		]);

		$bankSlip['status'] = $status;
		return $bankSlip;
		}

	/**
	 * Deletes an existing bank slip.
	 *
	 * @param mixed $id Bank slip identifier.
	 */
	public function delete($id): void
		{
		$bankSlipId = (int) $id;
		if ($bankSlipId <= 0)
			{
			throw new \InvalidArgumentException('Boleto inválido.');
			}

		try
			{
			$this->db->beginTransaction();

			$bankSlip = $this->find($bankSlipId);
			if (!$bankSlip)
				{
				throw new \InvalidArgumentException('Boleto não encontrado.');
				}

			$stmt = $this->db->prepare("DELETE FROM bank_slips WHERE id = :id");
			$stmt->execute(['id' => $bankSlipId]);

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

	/**
	 * Normalizes bank slip form data for create and update operations.
	 */
	private function normalizePayload(array $data): array
		{
		$status = strtoupper(trim((string) ($data['status'] ?? 'READY')));
		if (!in_array($status, ['DRAFT', 'READY'], true))
			{
			throw new \InvalidArgumentException('Status inválido para o boleto.');
			}

		$payload = [
			'financial_operation_payment_id' => ($data['financial_operation_payment_id'] ?? '') !== '' ? (int) $data['financial_operation_payment_id'] : null,
			'bank_account_id'                => (int) ($data['bank_account_id'] ?? 0),
			'status'                         => $status,
			'document_number'                => trim((string) ($data['document_number'] ?? '')),
			'our_number'                     => trim((string) ($data['our_number'] ?? '')) ?: null,
			'portfolio'                      => trim((string) ($data['portfolio'] ?? '109')),
			'payer_document_type'            => strtoupper(trim((string) ($data['payer_document_type'] ?? 'CPF'))),
			'payer_document'                 => normalizeCpfCnpjByType((string) ($data['payer_document'] ?? ''), (string) ($data['payer_document_type'] ?? 'CPF')),
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
		];
		if (!in_array($payload['payer_document_type'], ['CPF', 'CNPJ'], true))
			{
			throw new \InvalidArgumentException('Tipo de documento do pagador inválido.');
			}
		if (!isValidCpfCnpj($payload['payer_document'], $payload['payer_document_type']))
			{
			throw new \InvalidArgumentException('Documento do pagador inválido para o tipo informado.');
			}

		return $payload;
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