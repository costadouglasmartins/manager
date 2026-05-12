<?php

namespace App\Models;

use App\Core\TenantModel;
use PDO;

class Bank extends TenantModel
	{
	/**
	 * Returns the base bank select query.
	 */
	private function baseSelectSql(): string
		{
		return "SELECT Bank.id,
		              Bank.name,
		              Bank.number,
		              Bank.active,
		              Bank.created,
		              Bank.modified
		       FROM banks AS Bank";
		}

	/**
	 * Creates a bank and returns the inserted identifier.
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

			$sql = "INSERT INTO banks
			            (`name`, `number`, `active`, `created`, `modified`)
			        VALUES
			            (:name, :number, :active, :created, :modified)";
			$stmt = $this->db->prepare($sql);
			$stmt->execute([
				'name'     => trim((string) ($data['name'] ?? '')),
				'number'   => trim((string) ($data['number'] ?? '')),
				'active'   => ((string) ($data['active'] ?? '1') === '1') ? 1 : 0,
				'created'  => date('Y-m-d H:i:s'),
				'modified' => date('Y-m-d H:i:s'),
			]);

			$bankId = (int) $this->db->lastInsertId();
			$this->db->commit();

			return ['bank_id' => $bankId];
			}
		catch (\Exception $e)
			{
			$this->db->rollBack();
			throw $e;
			}
		}

	/**
	 * Returns all banks ordered by name.
	 */
	public function getOptions(): array
		{
		$stmt = $this->db->query($this->baseSelectSql() . "
			ORDER BY Bank.name ASC,
			         Bank.number ASC
		");

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Finds a bank by id.
	 *
	 * @param mixed $id Bank identifier.
	 */
	public function find($id)
		{
		$stmt = $this->db->prepare($this->baseSelectSql() . "
			WHERE Bank.id = :id
		");
		$stmt->execute(['id' => (int) $id]);

		return $stmt->fetch(PDO::FETCH_ASSOC);
		}

	/**
	 * Updates an existing bank.
	 *
	 * @param mixed $id Bank identifier.
	 * @param array $data Updated bank data.
	 */
	public function update($id, array $data): void
		{
		$stmt = $this->db->prepare("
			UPDATE banks
			SET `name` = :name,
			    `number` = :number,
			    `active` = :active,
			    `modified` = :modified
			WHERE id = :id
		");

		$stmt->execute([
			'name'     => trim((string) ($data['name'] ?? '')),
			'number'   => trim((string) ($data['number'] ?? '')),
			'active'   => ((string) ($data['active'] ?? '0') === '1') ? 1 : 0,
			'modified' => date('Y-m-d H:i:s'),
			'id'       => (int) $id,
		]);
		}

	/**
	 * Searches banks using the provided filters and pagination.
	 */
	public function search(array $filters = [], int $limit = 10, int $offset = 0): array
		{
		$sql = $this->baseSelectSql() . "
			WHERE 1 = 1
		";
		$params = [];

		if (isset($filters['id']) && $filters['id'] !== '')
			{
			$sql .= " AND Bank.id = :id";
			$params['id'] = (int) $filters['id'];
			}

		if (isset($filters['name']) && $filters['name'] !== '')
			{
			$sql .= " AND Bank.`name` LIKE :name";
			$params['name'] = '%' . $filters['name'] . '%';
			}

		if (isset($filters['number']) && $filters['number'] !== '')
			{
			$sql .= " AND Bank.`number` LIKE :number";
			$params['number'] = '%' . $filters['number'] . '%';
			}

		if (isset($filters['active']) && in_array((string) $filters['active'], ['0', '1'], true))
			{
			$sql .= " AND Bank.`active` = :active";
			$params['active'] = (int) $filters['active'];
			}

		$sql .= "
			ORDER BY Bank.id DESC
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
	 * Counts banks that match the provided filters.
	 */
	public function count(array $filters = []): int
		{
		$sql = "SELECT COUNT(*) AS total
		        FROM banks AS Bank
		        WHERE 1 = 1";
		$params = [];

		if (isset($filters['id']) && $filters['id'] !== '')
			{
			$sql .= " AND Bank.id = :id";
			$params['id'] = (int) $filters['id'];
			}

		if (isset($filters['name']) && $filters['name'] !== '')
			{
			$sql .= " AND Bank.`name` LIKE :name";
			$params['name'] = '%' . $filters['name'] . '%';
			}

		if (isset($filters['number']) && $filters['number'] !== '')
			{
			$sql .= " AND Bank.`number` LIKE :number";
			$params['number'] = '%' . $filters['number'] . '%';
			}

		if (isset($filters['active']) && in_array((string) $filters['active'], ['0', '1'], true))
			{
			$sql .= " AND Bank.`active` = :active";
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
	 * Returns the bank details payload used by the information view.
	 *
	 * @param mixed $id Bank identifier.
	 */
	public function findAllByBankId($id): array
		{
		return ['bank' => $this->find($id)];
		}
	}
?>
