<?php

namespace App\Models;

use App\Core\Model;
use App\Core\TenantContext;
use PDO;

class Person extends Model
	{
	/**
	 * Returns all people ordered by newest first.
	 */
	public function getAll()
		{
		$stmt = $this->db->query("SELECT * FROM people ORDER BY id DESC");
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Creates a person and, optionally, a linked manager user.
	 *
	 * @param array $data Request payload from the person form.
	 * @return array
	 * @throws \Exception
	 */
	public function create($data)
		{
		$personData = $this->normalizePersonPayload(
			(is_array($data['person'] ?? null) ? $data['person'] : [])
		);
		$emails = (is_array($data['emails'] ?? null) ? $data['emails'] : []);
		$userData = $this->normalizeUserPayload($data, $personData['active']);
		$now = date('Y-m-d H:i:s');

		try
			{
			$this->db->beginTransaction();

			$this->assertLoginEmailsAvailable($emails);

			$stmtPerson = $this->db->prepare("
				INSERT INTO people
					(name, fantasy, type, document, active, birthdate, created, modified)
				VALUES
					(:name, :fantasy, :type, :document, :active, :birthdate, :created, :modified)
			");
			$stmtPerson->execute([
				'name' => $personData['name'],
				'fantasy' => $personData['fantasy'],
				'type' => $personData['type'],
				'document' => cleanCpfCnpj((string)$personData['document']),
				'active' => $personData['active'],
				'birthdate' => $personData['birthdate'],
				'created' => $now,
				'modified' => $now,
			]);
			$personId = (int) $this->db->lastInsertId();

			$this->insertPhoneNumbers($personId, (is_array($data['phones'] ?? null) ? $data['phones'] : []), $now);
			$this->insertAddresses($personId, (is_array($data['addresses'] ?? null) ? $data['addresses'] : []), $now);
			$this->insertEmails($personId, $emails, $now);

			$userId = null;

			if ($userData['should_create'])
				{
				$loginEmail = $this->findLoginEmailPayload($emails);

				if ($loginEmail === null)
					{
					throw new \InvalidArgumentException('Informe ao menos um e-mail válido marcado como e-mail de login para criar o usuário.');
					}

				$this->assertManagerUsernameAvailable($userData['username']);
				$userId = $this->createManagerUserForTenant($personId, $userData, $now);
				}

			$this->db->commit();

			return [
				'person_id' => $personId,
				'user_id' => $userId,
			];
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
	 * Finds a person by id.
	 *
	 * @param mixed $id
	 * @return array|false
	 */
	public function find($id)
		{
		$stmt = $this->db->prepare("SELECT * FROM people WHERE id = :id");
		$stmt->execute(['id' => $id]);
		return $stmt->fetch(PDO::FETCH_ASSOC);
		}

	/**
	 * Updates a person and its related records.
	 *
	 * @param mixed $id
	 * @param array $data
	 */
	public function update($id, $data)
		{
		$personData = (isset($data['person']) && is_array($data['person'])) ? $data['person'] : $data;
		$normalizedPersonData = $this->normalizePersonPayload($personData);
		$emails = (is_array($data['emails'] ?? null) ? $data['emails'] : []);
		$now = date('Y-m-d H:i:s');

		try
			{
			$this->db->beginTransaction();

			$this->assertLoginEmailsAvailable($emails, (int) $id);

			$stmt = $this->db->prepare("
				UPDATE people
				SET
					name = :name,
					fantasy = :fantasy,
					type = :type,
					document = :document,
					active = :active,
					birthdate = :birthdate,
					modified = :modified
				WHERE id = :id
			");
			$stmt->execute([
				'name' => $normalizedPersonData['name'],
				'fantasy' => $normalizedPersonData['fantasy'],
				'type' => $normalizedPersonData['type'],
				'document' => $normalizedPersonData['document'],
				'active' => $normalizedPersonData['active'],
				'birthdate' => $normalizedPersonData['birthdate'],
				'modified' => $now,
				'id' => $id,
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
	 * Searches people with filters and pagination.
	 */
	public function search(array $filters = [], int $limit = 10, int $offset = 0, ?int $tenantId = null): array
		{
		$peopleTable = $this->managerTable('people');
		$sql = "SELECT Person.* FROM {$peopleTable} AS Person WHERE 1=1";
		$params = [];

		$this->appendTenantScope($sql, $params, $tenantId);

		if (isset($filters['id']) && $filters['id'] !== '')
			{
			$sql .= " AND Person.id = :id";
			$params['id'] = (int) $filters['id'];
			}

		if (isset($filters['name']) && $filters['name'] !== '')
			{
			$sql .= " AND Person.name LIKE :name";
			$params['name'] = '%' . $filters['name'] . '%';
			}

		if (isset($filters['type']) && $filters['type'] !== '')
			{
			$sql .= " AND Person.type = :type";
			$params['type'] = $filters['type'];
			}

		if (isset($filters['document']) && $filters['document'] !== '')
			{
			$sql .= " AND Person.document LIKE :document";
			$params['document'] = onlyDigits($filters['document']);
			}

		if (isset($filters['active']) && $filters['active'] !== '')
			{
			$active = (int) $filters['active'];

			if (in_array($active, [0, 1], true))
				{
				$sql .= " AND Person.active = :active";
				$params['active'] = $active;
				}
			}

		if (isset($filters['birthdate']) && $filters['birthdate'] !== '')
			{
			$sql .= " AND Person.birthdate = :birthdate";
			$params['birthdate'] = dateBrToUs($filters['birthdate']);
			}

		$sql .= " ORDER BY Person.id DESC LIMIT :limit OFFSET :offset";

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
	 * Counts people matching the provided filters.
	 */
	public function count(array $filters = [], ?int $tenantId = null): int
		{
		$peopleTable = $this->managerTable('people');
		$sql = "SELECT COUNT(*) AS total FROM {$peopleTable} AS Person WHERE 1=1";
		$params = [];

		$this->appendTenantScope($sql, $params, $tenantId);

		if (!empty($filters['id']))
			{
			$sql .= " AND Person.id = :id";
			$params['id'] = (int) $filters['id'];
			}

		if (!empty($filters['name']))
			{
			$sql .= " AND Person.name LIKE :name";
			$params['name'] = '%' . $filters['name'] . '%';
			}

		if (!empty($filters['type']))
			{
			$sql .= " AND Person.type = :type";
			$params['type'] = $filters['type'];
			}

		if (!empty($filters['document']))
			{
			$sql .= " AND Person.document LIKE :document";
			$params['document'] = onlyDigits($filters['document']);
			}

		if (!empty($filters['birthdate']))
			{
			$sql .= " AND Person.birthdate = :birthdate";
			$params['birthdate'] = dateBrToUs($filters['birthdate']);
			}

		if (isset($filters['active']) && $filters['active'] !== '')
			{
			$active = (int) $filters['active'];

			if (in_array($active, [0, 1], true))
				{
				$sql .= " AND Person.active = :active";
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
	 * Returns a person with related data for the information/edit screens.
	 *
	 * @param mixed $id
	 * @return array
	 */
	public function findAllByPersonId($id)
		{
		$neighborhoodsTable = $this->managerTable('neighborhoods');
		$citiesTable = $this->managerTable('cities');

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
			       ) AS emails_json,
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
			       			LEFT JOIN {$neighborhoodsTable} n ON n.id = a.neighborhood_id
			       			LEFT JOIN {$citiesTable} c ON c.id = n.city_id
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

		$user = null;

		if (is_array($person))
			{
			$user = $this->findUserByPersonId(
				(int) ($person['id'] ?? 0),
				$this->currentTenantId()
			);
			}

		return [
			'person' => $person,
			'user' => $user,
			'phones' => $phones,
			'emails' => $emails,
			'addresses' => $addresses,
		];
		}

	/**
	 * Returns autocomplete suggestions for people.
	 */
	public function autocomplete(string $term, int $limit = 100, ?int $tenantId = null): array
		{
		$term = trim($term);

		if ($term === '')
			{
			return [];
			}

		$limit = max(1, min(50, (int) $limit));

		$peopleTable = $this->managerTable('people');
		$sql = "SELECT Person.id, Person.name
			FROM {$peopleTable} AS Person
			WHERE Person.name LIKE :term";
		$params = ['term' => '%' . $term . '%'];

		$this->appendTenantScope($sql, $params, $tenantId);

		$sql .= "
			ORDER BY COALESCE(Person.name) ASC
			LIMIT {$limit}";

		$stmt = $this->db->prepare($sql);
		$stmt->execute($params);

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Restricts person queries to the requested tenant through manager users.
	 */
	private function appendTenantScope(string &$sql, array &$params, ?int $tenantId): void
		{
		if ($tenantId === null)
			{
			return;
			}

		$usersTable = $this->managerTable('users');
		$tenantUsersTable = $this->managerTable('tenant_users');
		$sql .= "
			AND EXISTS (
				SELECT 1
				FROM {$usersTable} AS User
				INNER JOIN {$tenantUsersTable} AS TenantUser
					ON TenantUser.user_id = User.id
					AND TenantUser.tenant_id = :tenant_id
				WHERE User.person_id = Person.id
			)
		";
		$params['tenant_id'] = $tenantId;
		}

	/**
	 * Inserts the initial set of phone numbers.
	 */
	private function insertPhoneNumbers(int $personId, array $phones, string $now): void
		{
		if ($phones === [])
			{
			return;
			}

		$stmt = $this->db->prepare("
			INSERT INTO phone_numbers (phone_number, observations, person_id, created, modified)
			VALUES (:phone_number, :observations, :person_id, :created, :modified)
		");

		foreach ($phones as $phone)
			{
			$number = trim((string) ($phone['number'] ?? ''));

			if ($number === '')
				{
				continue;
				}

			$stmt->execute([
				'phone_number' => onlyDigits($number),
				'observations' => $this->nullableTrim($phone['notes'] ?? null),
				'person_id' => $personId,
				'created' => $now,
				'modified' => $now,
			]);
			}
		}

	/**
	 * Inserts the initial set of addresses.
	 */
	private function insertAddresses(int $personId, array $addresses, string $now): void
		{
		if ($addresses === [])
			{
			return;
			}

		$stmt = $this->db->prepare("
			INSERT INTO addresses
				(zip_code, street, number, observations, person_id, neighborhood_id, created, modified)
			VALUES
				(:zip_code, :street, :number, :observations, :person_id, :neighborhood_id, :created, :modified)
		");

		foreach ($addresses as $address)
			{
			$street = trim((string) ($address['street'] ?? ''));
			$neighborhoodId = (int) ($address['neighborhood_id'] ?? 0);

			if ($street === '' || $neighborhoodId === 0)
				{
				continue;
				}

			$stmt->execute([
				'zip_code' => trim((string) ($address['zip_code'] ?? '')),
				'street' => $street,
				'number' => trim((string) ($address['number'] ?? '')),
				'observations' => $this->nullableTrim($address['observations'] ?? null),
				'person_id' => $personId,
				'neighborhood_id' => $neighborhoodId,
				'created' => $now,
				'modified' => $now,
			]);
			}
		}

	/**
	 * Inserts the initial set of emails.
	 */
	private function insertEmails(int $personId, array $emails, string $now): void
		{
		if ($emails === [])
			{
			return;
			}

		$stmt = $this->db->prepare("
			INSERT INTO emails (email, observations, is_login, person_id, created, modified)
			VALUES (:email, :observations, :is_login, :person_id, :created, :modified)
		");

		foreach ($emails as $email)
			{
			$address = trim((string) ($email['address'] ?? ''));

			if ($address === '')
				{
				continue;
				}

			$stmt->execute([
				'email' => $address,
				'observations' => $this->nullableTrim($email['description'] ?? null),
				'is_login' => (int) ($email['is_login'] ?? 0),
				'person_id' => $personId,
				'created' => $now,
				'modified' => $now,
			]);
			}
		}

	/**
	 * Synchronizes phone numbers for the requested person.
	 */
	private function syncPhoneNumbers(int $personId, array $phones): void
		{
		$existingIds = $this->fetchAssociationIds('phone_numbers', $personId);
		$keptIds = [];

		$insertStmt = $this->db->prepare("
			INSERT INTO phone_numbers (phone_number, observations, person_id, created, modified)
			VALUES (:phone_number, :observations, :person_id, :created, :modified)
		");
		$updateStmt = $this->db->prepare("
			UPDATE phone_numbers
			SET
				phone_number = :phone_number,
				observations = :observations,
				modified = :modified
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
				'person_id' => $personId,
				'modified' => date('Y-m-d H:i:s'),
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
	 * Synchronizes addresses for the requested person.
	 */
	private function syncAddresses(int $personId, array $addresses): void
		{
		$existingIds = $this->fetchAssociationIds('addresses', $personId);
		$keptIds = [];

		$insertStmt = $this->db->prepare("
			INSERT INTO addresses
				(zip_code, street, number, observations, person_id, neighborhood_id, created, modified)
			VALUES
				(:zip_code, :street, :number, :observations, :person_id, :neighborhood_id, :created, :modified)
		");
		$updateStmt = $this->db->prepare("
			UPDATE addresses
			SET
				zip_code = :zip_code,
				street = :street,
				number = :number,
				observations = :observations,
				neighborhood_id = :neighborhood_id,
				modified = :modified
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
				'zip_code' => $zipCode,
				'street' => $street,
				'number' => $number,
				'observations' => ($observations !== '' ? $observations : null),
				'person_id' => $personId,
				'neighborhood_id' => $neighborhoodId,
				'modified' => date('Y-m-d H:i:s'),
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
	 * Synchronizes emails for the requested person.
	 */
	private function syncEmails(int $personId, array $emails): void
		{
		$existingIds = $this->fetchAssociationIds('emails', $personId);
		$keptIds = [];

		$insertStmt = $this->db->prepare("
			INSERT INTO emails (email, observations, is_login, person_id, created, modified)
			VALUES (:email, :observations, :is_login, :person_id, :created, :modified)
		");
		$updateStmt = $this->db->prepare("
			UPDATE emails
			SET
				email = :email,
				observations = :observations,
				is_login = :is_login,
				modified = :modified
			WHERE id = :id
			  AND person_id = :person_id
		");

		foreach ($emails as $email)
			{
			$address = trim((string) ($email['address'] ?? ''));
			$description = trim((string) ($email['description'] ?? ''));
			$isLogin = (int) ($email['is_login'] ?? 0);
			$hasContent = ($address !== '' || $description !== '' || $isLogin === 1);

			if (!$hasContent || $address === '')
				{
				continue;
				}

			$rowId = (int) ($email['id'] ?? 0);
			$params = [
				'email' => $address,
				'observations' => ($description !== '' ? $description : null),
				'is_login' => $isLogin,
				'person_id' => $personId,
				'modified' => date('Y-m-d H:i:s'),
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
	 * Fetches existing association row ids for a person.
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
	 * Deletes association rows that were removed from the form payload.
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
	 * Normalizes the person form payload.
	 */
	private function normalizePersonPayload(array $personData): array
		{
		return [
			'name' => trim((string) ($personData['name'] ?? '')),
			'fantasy' => trim((string) ($personData['fantasy'] ?? '')),
			'type' => trim((string) ($personData['type'] ?? '')),
			'document' => cleanCpfCnpj((string) ($personData['document'] ?? '')),
			'active' => (int) ($personData['active'] ?? 1),
			'birthdate' => (!empty($personData['birthdate']) ? dateBrToUs($personData['birthdate']) : null),
		];
		}

	/**
	 * Normalizes the optional user form payload.
	 */
	private function normalizeUserPayload(array $data, int $defaultActive): array
		{
		$rawUserData = (is_array($data['user'] ?? null) ? $data['user'] : []);
		$hasUserPayload = (
			trim((string) ($rawUserData['username'] ?? '')) !== ''
			|| trim((string) ($rawUserData['password'] ?? '')) !== ''
			|| trim((string) ($rawUserData['type'] ?? '')) !== ''
		);
		$rawCreateUser = (string) ($data['create_user'] ?? '');
		$shouldCreate = ($rawCreateUser === '1') || ($rawCreateUser === '' && $hasUserPayload);

		$username = trim((string) ($rawUserData['username'] ?? ''));
		$password = (string) ($rawUserData['password'] ?? '');
		$type = trim((string) ($rawUserData['type'] ?? ''));
		$active = isset($rawUserData['active']) ? (int) $rawUserData['active'] : $defaultActive;

		if (!$shouldCreate)
			{
			return [
				'should_create' => false,
				'username' => $username,
				'password' => $password,
				'type' => $type,
				'active' => $active,
			];
			}

		if ($username === '' || $password === '' || $type === '')
			{
			throw new \InvalidArgumentException('Preencha todos os dados do usuário para gerar o acesso.');
			}

		if (!in_array($type, ['root', 'admin', 'user'], true))
			{
			throw new \InvalidArgumentException('Selecione um tipo de usuário válido.');
			}

		if (strlen($username) > 45)
			{
			throw new \InvalidArgumentException('O nome de usuário deve ter no máximo 45 caracteres.');
			}

		return [
			'should_create' => true,
			'username' => $username,
			'password' => $password,
			'type' => $type,
			'active' => ($active === 0 ? 0 : 1),
		];
		}

	/**
	 * Ensures login emails are valid and unique among people.
	 */
	private function assertLoginEmailsAvailable(array $emails, ?int $personId = null): void
		{
		$seen = [];

		foreach ($emails as $email)
			{
			$address = trim((string) ($email['address'] ?? ''));
			$isLogin = (int) ($email['is_login'] ?? 0);

			if ($isLogin !== 1)
				{
				continue;
				}

			if ($address === '' || !filter_var($address, FILTER_VALIDATE_EMAIL))
				{
				throw new \InvalidArgumentException('Informe um e-mail de login válido.');
				}

			$key = strtolower($address);

			if (isset($seen[$key]))
				{
				continue;
				}

			$seen[$key] = true;
			$this->assertLoginEmailAvailable($address, $personId);
			}
		}

	/**
	 * Ensures the chosen login email is not already assigned to another person.
	 */
	private function assertLoginEmailAvailable(string $address, ?int $personId = null): void
		{
		$emailsTable = $this->managerTable('emails');
		$sql = "
			SELECT id
			FROM {$emailsTable}
			WHERE email = :email
			  AND is_login = 1
		";
		$params = ['email' => $address];

		if ($personId !== null)
			{
			$sql .= " AND person_id <> :person_id";
			$params['person_id'] = $personId;
			}

		$sql .= " LIMIT 1";

		$stmt = $this->db->prepare($sql);
		$stmt->execute($params);

		if ((int) ($stmt->fetchColumn() ?: 0) > 0)
			{
			throw new \InvalidArgumentException('O e-mail de login informado já está associado a outra pessoa.');
			}
		}

	/**
	 * Returns the first valid login email from the payload.
	 */
	private function findLoginEmailPayload(array $emails): ?array
		{
		foreach ($emails as $email)
			{
			$address = trim((string) ($email['address'] ?? ''));
			$isLogin = (int) ($email['is_login'] ?? 0);

			if ($isLogin === 1 && $address !== '' && filter_var($address, FILTER_VALIDATE_EMAIL))
				{
				return [
					'address' => $address,
					'description' => trim((string) ($email['description'] ?? '')),
				];
				}
			}

		return null;
		}

	/**
	 * Creates the user in manager_main and links it to the active tenant.
	 */
	private function createManagerUserForTenant(int $personId, array $userData, string $now): int
		{
		$tenantId = $this->currentTenantId();

		if ($tenantId <= 0)
			{
			throw new \RuntimeException('Nenhum tenant ativo foi encontrado para vincular o usuário.');
			}

		$usersTable = $this->managerTable('users');
		$stmt = $this->db->prepare("
			INSERT INTO {$usersTable}
				(person_id, tenant_id, username, password, type, active, created, modified)
			VALUES
				(:person_id, :tenant_id, :username, :password, :type, :active, :created, :modified)
		");
		$stmt->execute([
			'person_id' => $personId,
			'tenant_id' => $tenantId,
			'username' => $userData['username'],
			'password' => password_hash($userData['password'], PASSWORD_DEFAULT),
			'type' => $userData['type'],
			'active' => $userData['active'],
			'created' => $now,
			'modified' => $now,
		]);

		$userId = (int) $this->db->lastInsertId();
		$this->linkManagerUserToTenant($tenantId, $userId, $userData['type'], $now);

		return $userId;
		}

	/**
	 * Ensures the username is unique in manager_main.users.
	 */
	private function assertManagerUsernameAvailable(string $username, ?int $ignoreUserId = null): void
		{
		$usersTable = $this->managerTable('users');
		$stmt = $this->db->prepare("
			SELECT id
			FROM {$usersTable}
			WHERE username = :username
			LIMIT 1
		");
		$stmt->execute(['username' => $username]);
		$existingId = (int) ($stmt->fetchColumn() ?: 0);

		if ($existingId > 0 && $existingId !== (int) $ignoreUserId)
			{
			throw new \InvalidArgumentException('O nome de usuário informado já está em uso.');
			}
		}

	/**
	 * Links a user to the current tenant in tenant_users.
	 */
	private function linkManagerUserToTenant(int $tenantId, int $userId, string $userType, string $now): void
		{
		$tenantUsersTable = $this->managerTable('tenant_users');
		$role = $this->mapUserTypeToTenantRole($userType);

		$stmt = $this->db->prepare("
			INSERT INTO {$tenantUsersTable}
				(tenant_id, user_id, role, created, modified)
			VALUES
				(:tenant_id, :user_id, :role, :created, :modified)
			ON DUPLICATE KEY UPDATE
				role = VALUES(role),
				modified = VALUES(modified)
		");
		$stmt->execute([
			'tenant_id' => $tenantId,
			'user_id' => $userId,
			'role' => $role,
			'created' => $now,
			'modified' => $now,
		]);
		}

	/**
	 * Finds the user associated with the person, optionally constrained to a tenant.
	 */
	private function findUserByPersonId(int $personId, ?int $tenantId = null): ?array
		{
		if ($personId <= 0)
			{
			return null;
			}

		$usersTable = $this->managerTable('users');
		$emailsTable = $this->managerTable('emails');
		$tenantUsersTable = $this->managerTable('tenant_users');
		$sql = "
			SELECT User.id,
			       User.person_id,
			       User.username,
			       User.type,
			       User.active,
			       (
			       	SELECT Email.email
			       	FROM {$emailsTable} AS Email
			       	WHERE Email.person_id = User.person_id
			       	  AND Email.is_login = 1
			       	ORDER BY Email.id ASC
			       	LIMIT 1
			       ) AS email
			FROM {$usersTable} AS User
		";
		$params = ['person_id' => $personId];

		if ($tenantId !== null && $tenantId > 0)
			{
			$sql .= "
				INNER JOIN {$tenantUsersTable} AS TenantUser
					ON TenantUser.user_id = User.id
					AND TenantUser.tenant_id = :tenant_id
			";
			$params['tenant_id'] = $tenantId;
			}

		$sql .= "
			WHERE User.person_id = :person_id
			ORDER BY User.id DESC
			LIMIT 1
		";

		$stmt = $this->db->prepare($sql);
		$stmt->execute($params);

		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		return ($row ?: null);
		}

	/**
	 * Returns the current tenant id from session.
	 */
	private function currentTenantId(): int
		{
		$tenant = TenantContext::tenant();
		return (int) ($tenant['id'] ?? 0);
		}

	/**
	 * Maps the manager user type to the tenant_users role.
	 */
	private function mapUserTypeToTenantRole(string $userType): string
		{
		return ($userType === 'admin' || $userType === 'root') ? 'admin' : 'user';
		}

	/**
	 * Decodes JSON array values from the aggregated detail query.
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
	 * Returns a trimmed string or null when the value is empty.
	 */
	private function nullableTrim($value): ?string
		{
		$text = trim((string) $value);
		return ($text !== '' ? $text : null);
		}
	}
?>
