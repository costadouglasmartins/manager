<?php

namespace App\Models;

use App\Core\Model;
use PDO;

class Country extends Model
	{

	/**
	 * Returns all countries ordered by newest first.
	 *
	 * @return array
	 */
	public function getAll()
		{
		$countriesTable = $this->managerTable('countries');
		$stmt = $this->db->query("SELECT * FROM {$countriesTable} ORDER BY id DESC");
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Returns countries ordered for use in select fields.
	 */
	public function getAllForSelect(): array
		{
		$countriesTable = $this->managerTable('countries');
		$stmt = $this->db->query("
			SELECT id, `name`, acronym
			FROM {$countriesTable}
			ORDER BY `name` ASC, id ASC
		");

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Creates a country and returns the inserted identifier.
	 *
	 * @param array $data Data to save.
	 * @return array
	 * @throws \Exception
	 */
	public function create($data)
		{

		try
			{
			// Start the transaction.
			$this->db->beginTransaction();
			$countriesTable = $this->managerTable('countries');

			$sql = "INSERT INTO {$countriesTable}
                        (`name`, `acronym`, `created`, `modified`) 
                      VALUES 
                        ( :name, :acronym, :created, :modified)";
			$stmt = $this->db->prepare($sql);
			$stmt->execute([
				'name' => $data['name'],
				'acronym' => $data['acronym'],
				'created' => date('Y-m-d H:i:s'),
				'modified' => date('Y-m-d H:i:s')
			]);
			// Store the created country id.
			$countryId = $this->db->lastInsertId();
			$countrySnapshot = $this->find($countryId);
			$this->auditCreate('country', 'countries', $countryId, $countrySnapshot);

			// Commit the transaction.
			$this->db->commit();

			// Return the created identifiers.
			return [
				'country_id' => $countryId,
			];
			}
		catch (\Exception $e)
			{
			// Reverts in case of failure
			$this->db->rollBack();
			throw $e;
			}
		}

	/**
	 * Finds a country by id.
	 *
	 * @param mixed $id Country identifier.
	 * @return array
	 */
	public function find($id)
		{
		$countriesTable = $this->managerTable('countries');
		$stmt = $this->db->prepare("SELECT * FROM {$countriesTable} WHERE id = :id");
		$stmt->execute(['id' => $id]);
		return $stmt->fetch(PDO::FETCH_ASSOC);
		}

	/**
	 * Updates an existing country.
	 *
	 * @param mixed $id Country identifier.
	 * @param array $data Updated country data.
	 */
	public function update($id, $data)
    {
    $countryId = (int) $id;
    $beforeData = $this->find($countryId);
    $countriesTable = $this->managerTable('countries');
    $stmt = $this->db->prepare("
        UPDATE {$countriesTable}
        SET
            `name`      = :name,
            `acronym`   = :acronym,
            `modified`  = :modified
        WHERE id = :id
    ");

    $stmt->execute([
        'name'      => $data['name'],
        'acronym'   => $data['acronym'],
        'modified'  => date('Y-m-d H:i:s'),
        'id'        => $id
    ]);

    $afterData = $this->find($countryId);
    $this->auditUpdate('country', 'countries', $countryId, $beforeData, $afterData);
    }

	/**
	 * Searches countries using the provided filters and pagination.
	 */
	public function search(array $filters = [], int $limit = 10, int $offset = 0): array
		{
		$countriesTable = $this->managerTable('countries');
		$sql = "SELECT * FROM {$countriesTable} WHERE 1=1";
		$params = [];

		// Id filter.
		if (isset($filters['id']) && $filters['id'] !== '')
			{
			$sql .= " AND id = :id";
			$params['id'] = (int) $filters['id'];
			}

		// Name filter.
		if (isset($filters['name']) && $filters['name'] !== '')
			{
			$sql .= " AND `name` LIKE :name";
			$params['name'] = '%' . $filters['name'] . '%';
			}

		// Acronym filter.
		if (isset($filters['acronym']) && $filters['acronym'] !== '')
			{
			$sql .= " AND `acronym` = :acronym";
			$params['acronym'] = $filters['acronym'];
			}

		$sql .= " ORDER BY id DESC LIMIT :limit OFFSET :offset";

		$stmt = $this->db->prepare($sql);

		// Bind filter values.
		foreach ($params as $key => $value)
			{
			$paramType = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
			$stmt->bindValue(':' . $key, $value, $paramType);
			}

		// Bind pagination values.
		$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
		$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

		$stmt->execute();

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Counts countries that match the provided filters.
	 *
	 * @param array $filters
	 * @return int
	 */
	public function count(array $filters = []): int
		{
		$countriesTable = $this->managerTable('countries');
		$sql = "SELECT COUNT(*) AS total FROM {$countriesTable} WHERE 1=1";
		$params = [];

		// Exact id filter.
		if (!empty($filters['id']))
			{
			$sql .= " AND id = :id";
			$params['id'] = (int) $filters['id'];
			}

		// Name filter.
		if (!empty($filters['name']))
			{
			$sql .= " AND name LIKE :name";
			$params['name'] = '%' . $filters['name'] . '%';
			}

		// Acronym filter.
		if (!empty($filters['acronym']))
			{
			$sql .= " AND `acronym` = :acronym";
			$params['acronym'] = $filters['acronym'];
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
	 * Returns the country details payload used by the information view.
	 */
	public function findAllByCountryId($id)
		{
		$countriesTable = $this->managerTable('countries');
		$personStmt = $this->db->prepare("SELECT * FROM {$countriesTable} WHERE id = :id");
		$personStmt->execute(['id' => $id]);
		$personData = $personStmt->fetch(PDO::FETCH_ASSOC);
		return ['countries' => $personData];
		}

	/**
	 * Returns autocomplete suggestions for countries.
	 *
	 * @param string $term
	 * @param int $limit
	 * @return array
	 */
	public function autocomplete(string $term, int $limit = 100): array
		{
		$term = trim($term);
		if ($term === '')
			{
			return [];
			}

		$limit = max(1, min(50, (int) $limit));
		$countriesTable = $this->managerTable('countries');

		$sql = "SELECT id, `name`, acronym
						FROM {$countriesTable}
						WHERE `name` LIKE :t
						ORDER BY COALESCE(`name`) ASC
						LIMIT {$limit}";

		$stmt = $this->db->prepare($sql);
		$stmt->execute([':t' => '%' . $term . '%']);

		return $stmt->fetchAll(\PDO::FETCH_ASSOC);
		}
	}
?>
