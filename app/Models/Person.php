<?php

namespace App\Models;

use App\Core\Database;
use App\Core\TenantModel;
use PDO;

class Person extends TenantModel
	{
	/**
	 * Get all method
	 * @return associative array
	 */
	public function getAll()
		{
		$stmt = $this->db->query("SELECT * FROM people ORDER BY id DESC");
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Create method - saves person, optional user, phones, addresses and emails in a single transaction
	 * @param Array $data Data to save
	 * @return array
	 * @throws \Exception
	 */
	public function create($data)
		{

		try
			{
			$this->db->beginTransaction();

			// Save person
			$sqlPerson = "INSERT INTO people
                        (`name`, `fantasy`, `type`, `document`, `active`, `birthdate`, `created`, `modified`)
                      VALUES
                        (:name, :fantasy, :type, :document, :active, :birthdate, :created, :modified)";

			$birthdate = (!empty($data['person']['birthdate'])) ? dateBrToUs($data['person']['birthdate']) : null;

			$stmtPerson = $this->db->prepare($sqlPerson);
			$stmtPerson->execute([
				'name'      => $data['person']['name'],
				'fantasy'   => $data['person']['fantasy'] ?? '',
				'type'      => $data['person']['type'],
				'document'  => onlyDigits($data['person']['document']),
				'active'    => intval($data['person']['active'] ?? 1),
				'birthdate' => $birthdate,
				'created'   => date('Y-m-d H:i:s'),
				'modified'  => date('Y-m-d H:i:s'),
			]);
			$personId = $this->db->lastInsertId();

			$userId = null;
			$hasUserData = !empty($data['user']) && is_array($data['user']) && (
				trim((string) ($data['user']['username'] ?? '')) !== ''
				|| trim((string) ($data['user']['password'] ?? '')) !== ''
				|| trim((string) ($data['user']['type'] ?? '')) !== ''
			);

			if ($hasUserData)
				{
				$username = trim((string) ($data['user']['username'] ?? ''));
				$password = (string) ($data['user']['password'] ?? '');
				$type     = trim((string) ($data['user']['type'] ?? ''));

				if ($username === '' || $password === '' || $type === '')
					{
					throw new \InvalidArgumentException('Dados do usuário incompletos.');
					}

				$sqlUser = "INSERT INTO users
                      (`person_id`, `username`, `password`, `type`, `active`, `created`, `modified`)
                    VALUES
                      (:person_id, :username, :password, :type, :active, :created, :modified)";
				$stmtUser = $this->db->prepare($sqlUser);
				$stmtUser->execute([
					'person_id' => $personId,
					'username'  => $username,
					'password'  => password_hash($password, PASSWORD_DEFAULT),
					'type'      => $type,
					'active'    => intval($data['user']['active'] ?? 1),
					'created'   => date('Y-m-d H:i:s'),
					'modified'  => date('Y-m-d H:i:s'),
				]);
				$userId = $this->db->lastInsertId();
				}

			// Save phones
			if (!empty($data['phones']) && is_array($data['phones']))
				{
				$sqlPhone = "INSERT INTO phone_numbers (`phone_number`, `observations`, `person_id`, `created`, `modified`)
                             VALUES (:phone_number, :observations, :person_id, :created, :modified)";
				$stmtPhone = $this->db->prepare($sqlPhone);
				foreach ($data['phones'] as $phone)
					{
					if (empty($phone['number']))
						{
						continue;
						}
					$stmtPhone->execute([
						'phone_number' => onlyDigits($phone['number']),
						'observations' => $phone['notes'] ?? null,
						'person_id'    => $personId,
						'created'      => date('Y-m-d H:i:s'),
						'modified'     => date('Y-m-d H:i:s'),
					]);
					}
				}

			// Save addresses
			if (!empty($data['addresses']) && is_array($data['addresses']))
				{
				$sqlAddress = "INSERT INTO addresses
								(`zip_code`, `street`, `number`, `observations`, `person_id`, `neighborhood_id`, `created`, `modified`)
							  VALUES
								(:zip_code, :street, :number, :observations, :person_id, :neighborhood_id, :created, :modified)";
				$stmtAddress = $this->db->prepare($sqlAddress);
				foreach ($data['addresses'] as $address)
					{
					if (empty($address['street']) || empty($address['neighborhood_id']))
						{
						continue;
						}
					$stmtAddress->execute([
						'zip_code'        => $address['zip_code'] ?? '',
						'street'          => $address['street'],
						'number'          => $address['number'] ?? '',
						'observations'    => $address['observations'] ?? null,
						'person_id'       => $personId,
						'neighborhood_id' => (int) $address['neighborhood_id'],
						'created'         => date('Y-m-d H:i:s'),
						'modified'        => date('Y-m-d H:i:s'),
					]);
					}
				}

			// Save emails
			if (!empty($data['emails']) && is_array($data['emails']))
				{
				$sqlEmail = "INSERT INTO emails (`email`, `observations`, `is_login`, `person_id`, `created`, `modified`)
                             VALUES (:email, :observations, :is_login, :person_id, :created, :modified)";
				$stmtEmail = $this->db->prepare($sqlEmail);
				foreach ($data['emails'] as $email)
					{
					if (empty($email['address']))
						{
						continue;
						}
					$stmtEmail->execute([
						'email'        => $email['address'],
						'observations' => $email['description'] ?? null,
						'is_login'     => intval($email['is_login'] ?? 0),
						'person_id'    => $personId,
						'created'      => date('Y-m-d H:i:s'),
						'modified'     => date('Y-m-d H:i:s'),
					]);
					}
				}

			$this->db->commit();

			return [
				'person_id' => $personId,
				'user_id'   => $userId,
			];
			}
		catch (\Exception $e)
			{
			$this->db->rollBack();
			throw $e;
			}
		}

	/**
	 * Find method
	 * @param Integer $id Person id
	 * @return array
	 */
	public function find($id)
		{
		$stmt = $this->db->prepare("SELECT * FROM people WHERE id = :id");
		$stmt->execute(['id' => $id]);
		return $stmt->fetch(PDO::FETCH_ASSOC);
		}

	/**
	 * Update method
	 * @param Integer $id Person id
	 * @param array $data
	 */
	public function update($id, $data)
		{
		$personData = (isset($data['person']) && is_array($data['person'])) ? $data['person'] : $data;

		try
			{
			$this->db->beginTransaction();

			$stmt = $this->db->prepare("
				UPDATE people
				SET
					`name`      = :name,
					`fantasy`   = :fantasy,
					`type`      = :type,
					`document`  = :document,
					`active`    = :active,
					`birthdate` = :birthdate,
					`modified`  = :modified
				WHERE id = :id
			");

			$stmt->execute([
				'name'      => $personData['name'],
				'fantasy'   => $personData['fantasy'] ?? '',
				'type'      => $personData['type'],
				'document'  => onlyDigits($personData['document']),
				'active'    => intval($personData['active'] ?? 1),
				'birthdate' => (!empty($personData['birthdate']) ? dateBrToUs($personData['birthdate']) : null),
				'modified'  => date('Y-m-d H:i:s'),
				'id'        => $id,
			]);

			if (array_key_exists('phones', $data) && is_array($data['phones']))
				{
				$this->syncPhoneNumbers((int) $id, $data['phones']);
				}

			if (array_key_exists('addresses', $data) && is_array($data['addresses']))
				{
				$this->syncAddresses((int) $id, $data['addresses']);
				}

			if (array_key_exists('emails', $data) && is_array($data['emails']))
				{
				$this->syncEmails((int) $id, $data['emails']);
				}

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
	 * Search method
	 */
	public function search(array $filters = [], int $limit = 10, int $offset = 0): array
		{
		$sql = "SELECT * FROM people WHERE 1=1";
		$params = [];

		if (isset($filters['id']) && $filters['id'] !== '')
			{
			$sql .= " AND id = :id";
			$params['id'] = (int) $filters['id'];
			}

		if (isset($filters['name']) && $filters['name'] !== '')
			{
			$sql .= " AND `name` LIKE :name";
			$params['name'] = '%' . $filters['name'] . '%';
			}

		if (isset($filters['type']) && $filters['type'] !== '')
			{
			$sql .= " AND `type` = :type";
			$params['type'] = $filters['type'];
			}

		if (isset($filters['document']) && $filters['document'] !== '')
			{
			$sql .= " AND document LIKE :document";
			$params['document'] = onlyDigits($filters['document']);
			}

		if (isset($filters['active']) && $filters['active'] !== '')
			{
			$active = (int) $filters['active'];
			if (in_array($active, [0, 1], true))
				{
				$sql .= " AND active = :active";
				$params['active'] = $active;
				}
			}

		if (isset($filters['birthdate']) && $filters['birthdate'] !== '')
			{
			$sql .= " AND birthdate = :birthdate";
			$params['birthdate'] = dateBrToUs($filters['birthdate']);
			}

		$sql .= " ORDER BY id DESC LIMIT :limit OFFSET :offset";

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
	 * Count method
	 * @param Array $filters
	 * @return int
	 */
	public function count(array $filters = []): int
		{
		$sql = "SELECT COUNT(*) AS total FROM people WHERE 1=1";
		$params = [];

		if (!empty($filters['id']))
			{
			$sql .= " AND id = :id";
			$params['id'] = (int) $filters['id'];
			}

		if (!empty($filters['name']))
			{
			$sql .= " AND name LIKE :name";
			$params['name'] = '%' . $filters['name'] . '%';
			}

		if (!empty($filters['type']))
			{
			$sql .= " AND `type` = :type";
			$params['type'] = $filters['type'];
			}

		if (!empty($filters['document']))
			{
			$sql .= " AND document LIKE :document";
			$params['document'] = onlyDigits($filters['document']);
			}

		if (!empty($filters['birthdate']))
			{
			$sql .= " AND birthdate = :birthdate";
			$params['birthdate'] = dateBrToUs($filters['birthdate']);
			}

		if (isset($filters['active']) && $filters['active'] !== '')
			{
			$active = (int) $filters['active'];
			if (in_array($active, [0, 1], true))
				{
				$sql .= " AND active = :active";
				$params['active'] = $active;
				}
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
	 * Find all by person id - returns person, user, phones, emails and addresses
	 * @param Integer $id Person id
	 * @return array
	 */
	public function findAllByPersonId($id)
		{
		$managerNeighborhoodsTable = sprintf('`%s`.`%s`', Database::getManagerDatabaseName(), 'neighborhoods');
		$managerCitiesTable = sprintf('`%s`.`%s`', Database::getManagerDatabaseName(), 'cities');

		$stmt = $this->db->prepare("
			SELECT p.*,
			       COALESCE(
			       	(
			       		SELECT JSON_ARRAYAGG(
			       			JSON_OBJECT(
			       				'id', phone_rows.id,
			       				'phone_number', phone_rows.phone_number,
			       				'observations', phone_rows.observations,
			       				'person_id', phone_rows.person_id,
			       				'created', phone_rows.created,
			       				'modified', phone_rows.modified
			       			)
			       		)
			       		FROM (
			       			SELECT id, phone_number, observations, person_id, created, modified
			       			FROM phone_numbers
			       			WHERE person_id = :phones_person_id
			       			ORDER BY id ASC
			       		) AS phone_rows
			       	),
			       	JSON_ARRAY()
			       ) AS phones_json,
			       COALESCE(
			       	(
			       		SELECT JSON_ARRAYAGG(
			       			JSON_OBJECT(
			       				'id', email_rows.id,
			       				'email', email_rows.email,
			       				'observations', email_rows.observations,
			       				'is_login', email_rows.is_login,
			       				'person_id', email_rows.person_id,
			       				'created', email_rows.created,
			       				'modified', email_rows.modified
			       			)
			       		)
			       		FROM (
			       			SELECT id, email, observations, is_login, person_id, created, modified
			       			FROM emails
			       			WHERE person_id = :emails_person_id
			       			ORDER BY id ASC
			       		) AS email_rows
			       	),
			       	JSON_ARRAY()
			       ) AS emails_json
			       ,
			       COALESCE(
			       	(
			       		SELECT JSON_ARRAYAGG(
			       			JSON_OBJECT(
			       				'id', address_rows.id,
			       				'zip_code', address_rows.zip_code,
			       				'street', address_rows.street,
			       				'number', address_rows.number,
			       				'observations', address_rows.observations,
			       				'person_id', address_rows.person_id,
			       				'neighborhood_id', address_rows.neighborhood_id,
			       				'created', address_rows.created,
			       				'modified', address_rows.modified,
			       				'neighborhood_name', address_rows.neighborhood_name,
			       				'neighborhood_city_name', address_rows.neighborhood_city_name
			       			)
			       		)
			       		FROM (
			       			SELECT a.id,
			       			       a.zip_code,
			       			       a.street,
			       			       a.number,
			       			       a.observations,
			       			       a.person_id,
			       			       a.neighborhood_id,
			       			       a.created,
			       			       a.modified,
			       			       n.name AS neighborhood_name,
			       			       c.name AS neighborhood_city_name
			       			FROM addresses a
			       			LEFT JOIN {$managerNeighborhoodsTable} n ON n.id = a.neighborhood_id
			       			LEFT JOIN {$managerCitiesTable} c ON c.id = n.city_id
			       			WHERE a.person_id = :addresses_person_id
			       			ORDER BY a.id ASC
			       		) AS address_rows
			       	),
			       	JSON_ARRAY()
			       ) AS addresses_json
			FROM people p
			WHERE p.id = :person_id
			LIMIT 1
		");
		$stmt->execute([
			'person_id' => $id,
			'phones_person_id' => $id,
			'emails_person_id' => $id,
			'addresses_person_id' => $id,
		]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);

		$person = is_array($row) ? $row : null;
		$phones = [];
		$emails = [];
		$addresses = [];

		if (is_array($person))
			{
			$phones = $this->decodeJsonArray($person['phones_json'] ?? null);
			$emails = $this->decodeJsonArray($person['emails_json'] ?? null);
			$addresses = $this->decodeJsonArray($person['addresses_json'] ?? null);
			unset($person['phones_json'], $person['emails_json'], $person['addresses_json']);
			}

		// Tenant databases do not have a users table; keep the person details view
		// focused on tenant-owned data and expose no user block when unavailable.
		$user = null;

		return [
			'person' => $person,
			'user'   => $user,
			'phones' => $phones,
			'emails' => $emails,
			'addresses' => $addresses,
		];
		}

  /**
   * Helper method to decode JSON arrays stored in the database, ensuring a consistent return type of array
   * even if the stored value is null, empty, or invalid JSON.
   * @param mixed $json The JSON string to decode
   * @return array The decoded array, or an empty array if the input is not a valid JSON string representing an array
   */
	private function decodeJsonArray($json): array
		{
		if (!is_string($json) || trim($json) === '')
			{
			return [];
			}

		$data = json_decode($json, true);
		return is_array($data) ? $data : [];
		}

  /**
   * Synchronizes phone numbers for a given person.
   * @param int $personId The ID of the person for whom to synchronize phone numbers.
   * @param array $phones The array of phone numbers to synchronize.
   */
	private function syncPhoneNumbers(int $personId, array $phones): void
		{
		$existingIds = $this->fetchAssociationIds('phone_numbers', $personId);
		$keptIds = [];

		$insertStmt = $this->db->prepare("
			INSERT INTO phone_numbers (`phone_number`, `observations`, `person_id`, `created`, `modified`)
			VALUES (:phone_number, :observations, :person_id, :created, :modified)
		");
		$updateStmt = $this->db->prepare("
			UPDATE phone_numbers
			SET
				`phone_number` = :phone_number,
				`observations` = :observations,
				`modified` = :modified
			WHERE id = :id
			  AND person_id = :person_id
		");

		foreach ($phones as $phone)
			{
			$number = trim((string) ($phone['number'] ?? ''));
			$notes = trim((string) ($phone['notes'] ?? ''));
			$hasContent = ($number !== '' || $notes !== '');

			if (!$hasContent || $number === '')
				{
				continue;
				}

			$rowId = (int) ($phone['id'] ?? 0);
			$params = [
				'phone_number' => onlyDigits($number),
				'observations' => ($notes !== '' ? $notes : null),
				'person_id'    => $personId,
				'modified'     => date('Y-m-d H:i:s'),
			];

			if ($rowId > 0 && isset($existingIds[$rowId]))
				{
				$params['id'] = $rowId;
				$updateStmt->execute($params);
				$keptIds[$rowId] = true;
				continue;
				}

			$params['created'] = date('Y-m-d H:i:s');
			$insertStmt->execute($params);
			}

		$this->deleteMissingAssociationRows('phone_numbers', $personId, $existingIds, $keptIds);
		}

  /**
   * Synchronizes addresses for a given person.
   * @param int $personId The ID of the person for whom to synchronize addresses.
   * @param array $addresses The array of addresses to synchronize.
   */
	private function syncAddresses(int $personId, array $addresses): void
		{
		$existingIds = $this->fetchAssociationIds('addresses', $personId);
		$keptIds = [];

		$insertStmt = $this->db->prepare("
			INSERT INTO addresses
				(`zip_code`, `street`, `number`, `observations`, `person_id`, `neighborhood_id`, `created`, `modified`)
			VALUES
				(:zip_code, :street, :number, :observations, :person_id, :neighborhood_id, :created, :modified)
		");
		$updateStmt = $this->db->prepare("
			UPDATE addresses
			SET
				`zip_code` = :zip_code,
				`street` = :street,
				`number` = :number,
				`observations` = :observations,
				`neighborhood_id` = :neighborhood_id,
				`modified` = :modified
			WHERE id = :id
			  AND person_id = :person_id
		");

		foreach ($addresses as $address)
			{
			$zipCode = trim((string) ($address['zip_code'] ?? ''));
			$street = trim((string) ($address['street'] ?? ''));
			$number = trim((string) ($address['number'] ?? ''));
			$observations = trim((string) ($address['observations'] ?? ''));
			$neighborhoodId = (int) ($address['neighborhood_id'] ?? 0);
			$hasContent = (
				$zipCode !== ''
				|| $street !== ''
				|| $number !== ''
				|| $observations !== ''
				|| $neighborhoodId > 0
			);

			if (!$hasContent || $street === '' || $neighborhoodId === 0)
				{
				continue;
				}

			$rowId = (int) ($address['id'] ?? 0);
			$params = [
				'zip_code'        => $zipCode,
				'street'          => $street,
				'number'          => $number,
				'observations'    => ($observations !== '' ? $observations : null),
				'person_id'       => $personId,
				'neighborhood_id' => $neighborhoodId,
				'modified'        => date('Y-m-d H:i:s'),
			];

			if ($rowId > 0 && isset($existingIds[$rowId]))
				{
				$params['id'] = $rowId;
				$updateStmt->execute($params);
				$keptIds[$rowId] = true;
				continue;
				}

			$params['created'] = date('Y-m-d H:i:s');
			$insertStmt->execute($params);
			}

		$this->deleteMissingAssociationRows('addresses', $personId, $existingIds, $keptIds);
		}

  /**
   * Synchronizes emails for a given person.
   * @param int $personId The ID of the person for whom to synchronize emails.
   * @param array $emails The array of emails to synchronize.
   */
	private function syncEmails(int $personId, array $emails): void
		{
		$existingIds = $this->fetchAssociationIds('emails', $personId);
		$keptIds = [];

		$insertStmt = $this->db->prepare("
			INSERT INTO emails (`email`, `observations`, `is_login`, `person_id`, `created`, `modified`)
			VALUES (:email, :observations, :is_login, :person_id, :created, :modified)
		");
		$updateStmt = $this->db->prepare("
			UPDATE emails
			SET
				`email` = :email,
				`observations` = :observations,
				`is_login` = :is_login,
				`modified` = :modified
			WHERE id = :id
			  AND person_id = :person_id
		");

		foreach ($emails as $email)
			{
			$address = trim((string) ($email['address'] ?? ''));
			$description = trim((string) ($email['description'] ?? ''));
			$isLogin = intval($email['is_login'] ?? 0);
			$hasContent = ($address !== '' || $description !== '' || $isLogin === 1);

			if (!$hasContent || $address === '')
				{
				continue;
				}

			$rowId = (int) ($email['id'] ?? 0);
			$params = [
				'email'        => $address,
				'observations' => ($description !== '' ? $description : null),
				'is_login'     => $isLogin,
				'person_id'    => $personId,
				'modified'     => date('Y-m-d H:i:s'),
			];

			if ($rowId > 0 && isset($existingIds[$rowId]))
				{
				$params['id'] = $rowId;
				$updateStmt->execute($params);
				$keptIds[$rowId] = true;
				continue;
				}

			$params['created'] = date('Y-m-d H:i:s');
			$insertStmt->execute($params);
			}

		$this->deleteMissingAssociationRows('emails', $personId, $existingIds, $keptIds);
		}

  /**
   * Helper method to fetch existing association IDs for a given person and table.
   * @param string $table The name of the association table (e.g., 'phone_numbers', 'addresses', 'emails').
   * @param int $personId The ID of the person for whom to fetch association IDs.
   * @return array An associative array where keys are existing association IDs and values are true, used for quick existence checks during synchronization.
   */
	private function fetchAssociationIds(string $table, int $personId): array
		{
		$stmt = $this->db->prepare("SELECT id FROM {$table} WHERE person_id = :person_id");
		$stmt->execute(['person_id' => $personId]);

		$ids = [];

		foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id)
			{
			$ids[(int) $id] = true;
			}

		return $ids;
		}
    
  /**
   * Deletes missing association rows for a given person and table.
   * @param string $table The name of the association table (e.g., 'phone_numbers', 'addresses', 'emails').
   * @param int $personId The ID of the person for whom to delete association rows.
   * @param array $existingIds The array of existing association IDs.
   * @param array $keptIds The array of kept association IDs.
   * @return void
   */
	private function deleteMissingAssociationRows(string $table, int $personId, array $existingIds, array $keptIds): void
		{
		$deleteIds = array_diff(array_keys($existingIds), array_keys($keptIds));

		if ($deleteIds === [])
			{
			return;
			}

		$stmt = $this->db->prepare("DELETE FROM {$table} WHERE id = :id AND person_id = :person_id");

		foreach ($deleteIds as $deleteId)
			{
			$stmt->execute([
				'id' => (int) $deleteId,
				'person_id' => $personId,
			]);
			}
		}

	/**
	 * Autocomplete method
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

		$sql = "SELECT id, `name`
						FROM people
						WHERE `name` LIKE :t
						ORDER BY COALESCE(`name`) ASC
						LIMIT {$limit}";

		$stmt = $this->db->prepare($sql);
		$stmt->execute([':t' => '%' . $term . '%']);

		return $stmt->fetchAll(\PDO::FETCH_ASSOC);
		}
	}
?>