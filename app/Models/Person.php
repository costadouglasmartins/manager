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
			'name'      => $data['name'],
			'fantasy'   => $data['fantasy'] ?? '',
			'type'      => $data['type'],
			'document'  => onlyDigits($data['document']),
			'active'    => $data['active'],
			'birthdate' => (!empty($data['birthdate']) ? dateBrToUs($data['birthdate']) : null),
			'modified'  => date('Y-m-d H:i:s'),
			'id'        => $id,
		]);
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
