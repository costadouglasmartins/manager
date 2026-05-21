<?php

namespace App\Models;

use App\Core\Database;
use App\Core\TenantModel;
use PDO;

class BankAccount extends TenantModel
	{
	/**
	 * Returns the manager bank table.
	 */
	private function bankTable(): string
		{
		return sprintf('`%s`.`banks`', Database::getManagerDatabaseName());
		}

	/**
	 * Returns the base bank account select query with bank data.
	 */
	private function baseSelectSql(): string
		{
		return "SELECT BankAccount.id,
		              BankAccount.bank_id,
		              BankAccount.name,
		              BankAccount.agency,
		              BankAccount.account,
		              BankAccount.pix_key_type,
		              BankAccount.pix_key,
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
		              BankAccount.active,
		              BankAccount.created,
		              BankAccount.modified,
		              Bank.name AS bank_name,
		              Bank.number AS bank_number,
		              Bank.cnab AS bank_cnab
		       FROM bank_accounts AS BankAccount
		       INNER JOIN " . $this->bankTable() . " AS Bank ON Bank.id = BankAccount.bank_id
		                                                  AND Bank.active = 1";
		}

	/**
	 * Creates a bank account and returns the inserted identifier.
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
			$payload = $this->normalizePayload($data, '1');

			$sql = "INSERT INTO bank_accounts
			            (`bank_id`, `name`, `agency`, `account`, `pix_key_type`, `pix_key`,
			             `bank_slip_instructions_line_1`, `bank_slip_instructions_line_2`, `bank_slip_instructions_line_3`,
			             `bank_slip_instructions_line_4`, `bank_slip_protest`, `bank_slip_protest_number_of_days`,
			             `bank_slip_wallet`, `bank_slip_species_title`, `bank_slip_interest_type`,
			             `bank_slip_interest_amount_type`, `bank_slip_interest`, `bank_slip_late_fee_amount`,
			             `active`, `created`, `modified`)
			        VALUES
			            (:bank_id, :name, :agency, :account, :pix_key_type, :pix_key,
			             :bank_slip_instructions_line_1, :bank_slip_instructions_line_2, :bank_slip_instructions_line_3,
			             :bank_slip_instructions_line_4, :bank_slip_protest, :bank_slip_protest_number_of_days,
			             :bank_slip_wallet, :bank_slip_species_title, :bank_slip_interest_type,
			             :bank_slip_interest_amount_type, :bank_slip_interest, :bank_slip_late_fee_amount,
			             :active, :created, :modified)";
			$stmt = $this->db->prepare($sql);
			$payload['created'] = date('Y-m-d H:i:s');
			$payload['modified'] = date('Y-m-d H:i:s');
			$stmt->execute($payload);

			$bankAccountId = (int) $this->db->lastInsertId();
			$this->db->commit();

			return ['bank_account_id' => $bankAccountId];
			}
		catch (\Exception $e)
			{
			$this->db->rollBack();
			throw $e;
			}
		}

	/**
	 * Finds a bank account by id.
	 *
	 * @param mixed $id Bank account identifier.
	 */
	public function find($id)
		{
		$stmt = $this->db->prepare($this->baseSelectSql() . "
			WHERE BankAccount.id = :id
		");
		$stmt->execute(['id' => (int) $id]);

		return $stmt->fetch(PDO::FETCH_ASSOC);
		}

	/**
	 * Updates an existing bank account.
	 *
	 * @param mixed $id Bank account identifier.
	 * @param array $data Updated bank account data.
	 */
	public function update($id, array $data): void
		{
		$payload = $this->normalizePayload($data, '0');
		$payload['modified'] = date('Y-m-d H:i:s');
		$payload['id'] = (int) $id;

		$stmt = $this->db->prepare("
			UPDATE bank_accounts
			SET `bank_id` = :bank_id,
			    `name` = :name,
			    `agency` = :agency,
			    `account` = :account,
			    `pix_key_type` = :pix_key_type,
			    `pix_key` = :pix_key,
			    `bank_slip_instructions_line_1` = :bank_slip_instructions_line_1,
			    `bank_slip_instructions_line_2` = :bank_slip_instructions_line_2,
			    `bank_slip_instructions_line_3` = :bank_slip_instructions_line_3,
			    `bank_slip_instructions_line_4` = :bank_slip_instructions_line_4,
			    `bank_slip_protest` = :bank_slip_protest,
			    `bank_slip_protest_number_of_days` = :bank_slip_protest_number_of_days,
			    `bank_slip_wallet` = :bank_slip_wallet,
			    `bank_slip_species_title` = :bank_slip_species_title,
			    `bank_slip_interest_type` = :bank_slip_interest_type,
			    `bank_slip_interest_amount_type` = :bank_slip_interest_amount_type,
			    `bank_slip_interest` = :bank_slip_interest,
			    `bank_slip_late_fee_amount` = :bank_slip_late_fee_amount,
			    `active` = :active,
			    `modified` = :modified
			WHERE id = :id
		");

		$stmt->execute($payload);
		}

	/**
	 * Searches bank accounts using the provided filters and pagination.
	 */
	public function search(array $filters = [], int $limit = 10, int $offset = 0): array
		{
		$sql = $this->baseSelectSql() . "
			WHERE 1 = 1
		";
		$params = [];

		if (isset($filters['id']) && $filters['id'] !== '')
			{
			$sql .= " AND BankAccount.id = :id";
			$params['id'] = (int) $filters['id'];
			}

		if (isset($filters['bank_id']) && $filters['bank_id'] !== '')
			{
			$sql .= " AND BankAccount.bank_id = :bank_id";
			$params['bank_id'] = (int) $filters['bank_id'];
			}

		if (isset($filters['name']) && $filters['name'] !== '')
			{
			$sql .= " AND BankAccount.`name` LIKE :name";
			$params['name'] = '%' . $filters['name'] . '%';
			}

		if (isset($filters['agency']) && $filters['agency'] !== '')
			{
			$sql .= " AND BankAccount.`agency` LIKE :agency";
			$params['agency'] = '%' . $filters['agency'] . '%';
			}

		if (isset($filters['account']) && $filters['account'] !== '')
			{
			$sql .= " AND BankAccount.`account` LIKE :account";
			$params['account'] = '%' . $filters['account'] . '%';
			}

		if (isset($filters['active']) && in_array((string) $filters['active'], ['0', '1'], true))
			{
			$sql .= " AND BankAccount.`active` = :active";
			$params['active'] = (int) $filters['active'];
			}

		$sql .= "
			ORDER BY BankAccount.id DESC
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
	 * Counts bank accounts that match the provided filters.
	 */
	public function count(array $filters = []): int
		{
		$sql = "SELECT COUNT(*) AS total
		        FROM bank_accounts AS BankAccount
		        INNER JOIN " . $this->bankTable() . " AS Bank ON Bank.id = BankAccount.bank_id
		                                                   AND Bank.active = 1
		        WHERE 1 = 1";
		$params = [];

		if (isset($filters['id']) && $filters['id'] !== '')
			{
			$sql .= " AND BankAccount.id = :id";
			$params['id'] = (int) $filters['id'];
			}

		if (isset($filters['bank_id']) && $filters['bank_id'] !== '')
			{
			$sql .= " AND BankAccount.bank_id = :bank_id";
			$params['bank_id'] = (int) $filters['bank_id'];
			}

		if (isset($filters['name']) && $filters['name'] !== '')
			{
			$sql .= " AND BankAccount.`name` LIKE :name";
			$params['name'] = '%' . $filters['name'] . '%';
			}

		if (isset($filters['agency']) && $filters['agency'] !== '')
			{
			$sql .= " AND BankAccount.`agency` LIKE :agency";
			$params['agency'] = '%' . $filters['agency'] . '%';
			}

		if (isset($filters['account']) && $filters['account'] !== '')
			{
			$sql .= " AND BankAccount.`account` LIKE :account";
			$params['account'] = '%' . $filters['account'] . '%';
			}

		if (isset($filters['active']) && in_array((string) $filters['active'], ['0', '1'], true))
			{
			$sql .= " AND BankAccount.`active` = :active";
			$params['active'] = (int) $filters['active'];
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
	 * Returns the bank account details payload used by the information view.
	 *
	 * @param mixed $id Bank account identifier.
	 */
	public function findAllByBankAccountId($id): array
		{
		return ['bank_account' => $this->find($id)];
		}

	/**
	 * Normalizes bank account data according to database column types.
	 *
	 * @param array $data Raw bank account data.
	 * @param string $defaultActive Default active value when the field is missing.
	 */
	private function normalizePayload(array $data, string $defaultActive): array
		{
		return [
			'bank_id'                              => (int) ($data['bank_id'] ?? 0),
			'name'                                 => trim((string) ($data['name'] ?? '')),
			'agency'                               => trim((string) ($data['agency'] ?? '')),
			'account'                              => trim((string) ($data['account'] ?? '')),
			'pix_key_type'                         => $this->nullableEnum($data['pix_key_type'] ?? null, ['CPF', 'CNPJ', 'EMAIL', 'PHONE', 'RANDOM']),
			'pix_key'                              => $this->nullableTrim($data['pix_key'] ?? null),
			'bank_slip_instructions_line_1'        => $this->nullableTrim($data['bank_slip_instructions_line_1'] ?? null),
			'bank_slip_instructions_line_2'        => $this->nullableTrim($data['bank_slip_instructions_line_2'] ?? null),
			'bank_slip_instructions_line_3'        => $this->nullableTrim($data['bank_slip_instructions_line_3'] ?? null),
			'bank_slip_instructions_line_4'        => $this->nullableTrim($data['bank_slip_instructions_line_4'] ?? null),
			'bank_slip_protest'                    => $this->nullableBoolean($data['bank_slip_protest'] ?? null),
			'bank_slip_protest_number_of_days'     => $this->nullableTinyint($data['bank_slip_protest_number_of_days'] ?? null),
			'bank_slip_wallet'                     => $this->nullableTrim($data['bank_slip_wallet'] ?? null),
			'bank_slip_species_title'              => $this->nullableTrim($data['bank_slip_species_title'] ?? null),
			'bank_slip_interest_type'              => $this->nullableEnum($data['bank_slip_interest_type'] ?? null, ['DAILY', 'MONTHLY']),
			'bank_slip_interest_amount_type'       => $this->nullableEnum($data['bank_slip_interest_amount_type'] ?? null, ['PERCENTAGE', 'FIXED_AMOUNT']),
			'bank_slip_interest'                   => $this->normalizeNullableDecimal($data['bank_slip_interest'] ?? null),
			'bank_slip_late_fee_amount'            => $this->normalizeNullableDecimal($data['bank_slip_late_fee_amount'] ?? null),
			'active'                               => ((string) ($data['active'] ?? $defaultActive) === '1') ? 1 : 0,
		];
		}

	/**
	 * Returns an enum value or null when the value is empty or invalid.
	 *
	 * @param mixed $value Raw value.
	 * @param array $allowed Allowed enum values.
	 */
	private function nullableEnum($value, array $allowed): ?string
		{
		$text = strtoupper(trim((string) $value));
		if ($text === '')
			{
			return null;
			}

		return in_array($text, $allowed, true) ? $text : null;
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
	 * Returns a tinyint boolean value or null when the value is empty.
	 *
	 * @param mixed $value Raw value.
	 */
	private function nullableBoolean($value): ?int
		{
		$text = trim((string) $value);
		if ($text === '')
			{
			return null;
			}

		return $text === '1' ? 1 : 0;
		}

	/**
	 * Returns a nullable tinyint value constrained to unsigned day values.
	 *
	 * @param mixed $value Raw value.
	 */
	private function nullableTinyint($value): ?int
		{
		$text = trim((string) $value);
		if ($text === '' || !is_numeric($text))
			{
			return null;
			}

		$days = (int) $text;
		if ($days < 0)
			{
			return 0;
			}

		return min($days, 127);
		}

	/**
	 * Normalizes optional decimal values submitted by Portuguese or database-style inputs.
	 *
	 * @param mixed $value Raw value.
	 */
	private function normalizeNullableDecimal($value): ?string
		{
		$text = trim((string) $value);
		$text = str_replace(['R$', ' '], '', $text);

		if ($text === '')
			{
			return null;
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

		$text = preg_replace('/[^0-9.\-]/', '', $text) ?? '';
		if ($text === '' || !is_numeric($text))
			{
			return null;
			}

		return number_format((float) $text, 2, '.', '');
		}
	}
?>
