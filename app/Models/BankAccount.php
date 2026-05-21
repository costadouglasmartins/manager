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

			$sql = "INSERT INTO bank_accounts
			            (`bank_id`, `name`, `agency`, `account`, `active`, `created`, `modified`)
			        VALUES
			            (:bank_id, :name, :agency, :account, :active, :created, :modified)";
			$stmt = $this->db->prepare($sql);
			$stmt->execute([
				'bank_id'  => (int) ($data['bank_id'] ?? 0),
				'name'     => trim((string) ($data['name'] ?? '')),
				'agency'   => trim((string) ($data['agency'] ?? '')),
				'account'  => trim((string) ($data['account'] ?? '')),
				'active'   => ((string) ($data['active'] ?? '1') === '1') ? 1 : 0,
				'created'  => date('Y-m-d H:i:s'),
				'modified' => date('Y-m-d H:i:s'),
			]);

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
		$stmt = $this->db->prepare("
			UPDATE bank_accounts
			SET `bank_id` = :bank_id,
			    `name` = :name,
			    `agency` = :agency,
			    `account` = :account,
			    `active` = :active,
			    `modified` = :modified
			WHERE id = :id
		");

		$stmt->execute([
			'bank_id'  => (int) ($data['bank_id'] ?? 0),
			'name'     => trim((string) ($data['name'] ?? '')),
			'agency'   => trim((string) ($data['agency'] ?? '')),
			'account'  => trim((string) ($data['account'] ?? '')),
			'active'   => ((string) ($data['active'] ?? '0') === '1') ? 1 : 0,
			'modified' => date('Y-m-d H:i:s'),
			'id'       => (int) $id,
		]);
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
	}
?>
