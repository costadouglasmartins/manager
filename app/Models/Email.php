<?php

namespace App\Models;

use App\Core\Model;
use PDO;

class Email extends Model
	{
	/**
	 * Returns the base email query with the related person name.
	 */
	private function baseSelectSql(): string
		{
		$emailsTable = $this->managerTable('emails');
		$peopleTable = $this->managerTable('people');

		return "SELECT Email.id,
		               Email.email,
		               Email.observations,
		               Email.person_id,
		               Email.is_login,
		               Email.created,
		               Email.modified,
		               Person.name AS person_name
		        FROM {$emailsTable} AS Email
		        LEFT JOIN {$peopleTable} AS Person ON Person.id = Email.person_id";
		}

	/**
	 * Get all method
	 */
	public function getAll()
		{
		$stmt = $this->db->query($this->baseSelectSql() . "
			ORDER BY Email.id DESC
		");
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Create method
	 */
	public function create($data)
		{
		try
			{
			$this->db->beginTransaction();

			$sql = "INSERT INTO emails
			           (email,
			            is_login,
			            observations,
			            person_id,
			            created)
			        VALUES
			           (:email,
			            :is_login,
			            :observations,
			            :person_id,
			            :created)";
			$stmt = $this->db->prepare($sql);
			$stmt->execute([
				'email'        => $data['email'],
				'is_login'     => $data['type'] ?? null,
				'observations' => $data['observations'] ?? null,
				'person_id'    => !empty($data['person_id']) ? (int) $data['person_id'] : null,
				'created'      => date('Y-m-d H:i:s'),
			]);
			$emailId = $this->db->lastInsertId();

			$this->db->commit();

			return ['email_id' => $emailId];
			}
		catch (\Exception $e)
			{
			$this->db->rollBack();
			throw $e;
			}
		}

	/**
	 * Find method
	 */
	public function find($id)
		{
		$stmt = $this->db->prepare($this->baseSelectSql() . "
			WHERE Email.id = :id
		");
		$stmt->execute(['id' => $id]);
		return $stmt->fetch(PDO::FETCH_ASSOC);
		}

	/**
	 * Update method
	 */
	public function update($id, $data)
		{
		$stmt = $this->db->prepare("
			UPDATE emails AS Email
			SET Email.email = :email,
			    Email.observations = :observations,
			    Email.person_id = :person_id
			WHERE Email.id = :id
		");

		$stmt->execute([
			'email'        => $data['email'],
			'observations' => $data['observations'] ?? null,
			'person_id'    => !empty($data['person_id']) ? (int) $data['person_id'] : null,
			'id'           => $id,
		]);
		}

	/**
	 * Search method
	 */
	public function search(array $filters = [], int $limit = 10, int $offset = 0, ?int $tenantId = null): array
		{
		$sql = $this->baseSelectSql() . "
			WHERE 1=1";
		$params = [];

		$this->appendTenantScope($sql, $params, $tenantId);

		if (isset($filters['id']) && $filters['id'] !== '')
			{
			$sql .= " AND Email.id = :id";
			$params['id'] = (int) $filters['id'];
			}

		if (isset($filters['email']) && $filters['email'] !== '')
			{
			$sql .= " AND Email.`email` LIKE :email";
			$params['email'] = '%' . $filters['email'] . '%';
			}

		if (isset($filters['person_id']) && $filters['person_id'] !== '')
			{
			$sql .= " AND Email.person_id = :person_id";
			$params['person_id'] = (int) $filters['person_id'];
			}

		if (isset($filters['person_name']) && $filters['person_name'] !== '')
			{
			$sql .= " AND Person.name LIKE :person_name";
			$params['person_name'] = '%' . $filters['person_name'] . '%';
			}

		$sql .= "
			ORDER BY Email.id DESC
			LIMIT :limit OFFSET :offset";

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
	 */
	public function count(array $filters = [], ?int $tenantId = null): int
		{
		$emailsTable = $this->managerTable('emails');
		$peopleTable = $this->managerTable('people');
		$sql = "SELECT COUNT(*) AS total
		        FROM {$emailsTable} AS Email
		        LEFT JOIN {$peopleTable} AS Person ON Person.id = Email.person_id
		        WHERE 1=1";
		$params = [];

		$this->appendTenantScope($sql, $params, $tenantId);

		if (!empty($filters['id']))
			{
			$sql .= " AND Email.id = :id";
			$params['id'] = (int) $filters['id'];
			}

		if (!empty($filters['email']))
			{
			$sql .= " AND Email.`email` LIKE :email";
			$params['email'] = '%' . $filters['email'] . '%';
			}

		if (!empty($filters['person_id']))
			{
			$sql .= " AND Email.person_id = :person_id";
			$params['person_id'] = (int) $filters['person_id'];
			}

		if (!empty($filters['person_name']))
			{
			$sql .= " AND Person.name LIKE :person_name";
			$params['person_name'] = '%' . $filters['person_name'] . '%';
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
	 * Restricts email queries to people linked to the requested tenant.
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
	 * Find all by email id
	 */
	public function findAllByEmailId($id)
		{
		$stmt = $this->db->prepare($this->baseSelectSql() . "
			WHERE Email.id = :id
		");
		$stmt->execute(['id' => $id]);
		$data = $stmt->fetch(PDO::FETCH_ASSOC);
		return ['email' => $data];
		}
	}

?>
