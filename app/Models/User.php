<?php

namespace App\Models;

use App\Core\Model;
use PDO;

class User extends Model
	{

  /**
	 * Searches users with optional tenant filtering and pagination.
	 */
	public function search(array $filters = [], int $limit = 10, int $offset = 0, ?int $tenantId = null): array
		{
		$usersTable = $this->managerTable('users');
		$tenantUsersTable = $this->managerTable('tenant_users');
		$sql = "SELECT DISTINCT User.* FROM {$usersTable} AS User";
		$params = [];

		if ($tenantId !== null)
			{
			$sql .= " INNER JOIN {$tenantUsersTable} AS TenantUser
				ON TenantUser.user_id = User.id
				AND TenantUser.tenant_id = :tenant_id";
			$params['tenant_id'] = $tenantId;
			}

		$sql .= " WHERE 1=1";

		// Id filter.
		if (isset($filters['id']) && $filters['id'] !== '')
			{
			$sql .= " AND User.id = :id";
			$params['id'] = (int) $filters['id'];
			}

		// Username filter.
		if (isset($filters['username']) && $filters['username'] !== '')
			{
			$sql .= " AND User.username LIKE :username";
			$params['username'] = '%' . $filters['username'] . '%';
			}

		// Email filter.
		if (isset($filters['email']) && $filters['email'] !== '')
			{
			$sql .= " AND email LIKE :email";
			$params['email'] = '%' . $filters['email'] . '%';
			}

		// Type filter.
		if (isset($filters['type']) && $filters['type'] !== '')
			{
			$sql .= " AND User.`type` = :type";
			$params['type'] = $filters['type'];
			}

		// Active status filter.
		if (isset($filters['active']) && $filters['active'] !== '')
			{
			$active = (int) $filters['active'];

			if (in_array($active, [0, 1], true))
				{
				$sql .= " AND User.active = :active";
				$params['active'] = $active;
				}
			}

		$sql .= " ORDER BY User.id DESC LIMIT :limit OFFSET :offset";

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
	 * Counts users that match the provided filters.
	 */
	public function count(array $filters = [], ?int $tenantId = null): int
		{
		$usersTable = $this->managerTable('users');
		$tenantUsersTable = $this->managerTable('tenant_users');
		$sql = "SELECT COUNT(DISTINCT User.id) AS total FROM {$usersTable} AS User";
		$params = [];

		if ($tenantId !== null)
			{
			$sql .= " INNER JOIN {$tenantUsersTable} AS TenantUser
				ON TenantUser.user_id = User.id
				AND TenantUser.tenant_id = :tenant_id";
			$params['tenant_id'] = $tenantId;
			}

		$sql .= " WHERE 1=1";

		// Exact id filter.
		if (!empty($filters['id']))
			{
			$sql .= " AND User.id = :id";
			$params['id'] = (int) $filters['id'];
			}

		// Username filter.
		if (!empty($filters['username']))
			{
			$sql .= " AND User.username LIKE :username";
			$params['username'] = '%' . $filters['username'] . '%';
			}

		// Email filter.
		if (!empty($filters['email']))
			{
			$sql .= " AND email LIKE :email";
			$params['email'] = '%' . $filters['email'] . '%';
			}

    // Type filter.
		if (!empty($filters['type']))
			{
			$sql .= " AND User.`type` = :type";
			$params['type'] = $filters['type'];
			}
    
		if (isset($filters['active']) && $filters['active'] !== '')
			{
			$active = (int) $filters['active'];

			if (in_array($active, [0, 1], true))
				{
				$sql .= " AND User.active = :active";
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
	 * Returns all users ordered by newest first.
	 * @return array
	 */
	public function getAll()
		{
		$usersTable = $this->managerTable('users');
		$stmt = $this->db->query("SELECT * FROM {$usersTable} ORDER BY id DESC");
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Finds a user by id, optionally constrained to a tenant.
	 *
	 * @param mixed $id User identifier.
	 * @return array
	 */
	public function find($id, ?int $tenantId = null)
		{
		$usersTable = $this->managerTable('users');
		$peopleTable = $this->managerTable('people');
		$emailsTable = $this->managerTable('emails');
		$tenantUsersTable = $this->managerTable('tenant_users');

		$sql = "SELECT User.id,
                       User.username,
                       User.type,
                       User.active,
                       Email.email
                FROM {$usersTable} AS User
                INNER JOIN {$peopleTable} AS Person ON Person.id = User.person_id
                INNER JOIN {$emailsTable} AS Email ON Email.person_id = Person.id";
		$params = ['id' => $id];

		if ($tenantId !== null)
			{
			$sql .= " INNER JOIN {$tenantUsersTable} AS TenantUser
				ON TenantUser.user_id = User.id
				AND TenantUser.tenant_id = :tenant_id";
			$params['tenant_id'] = $tenantId;
			}

		$sql .= " WHERE User.active = 1
			AND User.id = :id
			LIMIT 1";

		$stmt = $this->db->prepare($sql);
		$stmt->execute($params);
		return $stmt->fetch(PDO::FETCH_ASSOC);
		}

	/**
	 * Updates a user, optionally constrained to a tenant relation.
	 *
	 * @param mixed $id User identifier.
	 * @param array $data Updated user data.
	 */
	public function update($id, $data, ?int $tenantId = null)
		{
		$usersTable = $this->managerTable('users');
		$tenantUsersTable = $this->managerTable('tenant_users');
		$sql = "UPDATE {$usersTable} AS User";
		$params = [
			'username' => $data['username'],
			'password' => password_hash($data['password'], PASSWORD_DEFAULT),
			'type'     => $data['type'],
			'active'   => $data['active'],
			'id'       => $id,
		];

		if ($tenantId !== null)
			{
			$sql .= " INNER JOIN {$tenantUsersTable} AS TenantUser
				ON TenantUser.user_id = User.id
				AND TenantUser.tenant_id = :tenant_id";
			$params['tenant_id'] = $tenantId;
			}

		$sql .= " SET User.`username` = :username,
				User.`password` = :password,
				User.`type`     = :type,
				User.`active`   = :active
			WHERE User.id = :id";

		$stmt = $this->db->prepare($sql);
		$stmt->execute($params);
		}

	/**
	 * Finds an active login-enabled user by email address.
	 *
	 * @param string $email User email address.
	 * @return array
	 */
	public function findByEmail(string $email): ?array
    {
    $usersTable = $this->managerTable('users');
    $peopleTable = $this->managerTable('people');
    $emailsTable = $this->managerTable('emails');

	    $sql = "SELECT  User.id,
	                    User.username,
	                    User.password,
	                    User.active,
	                    User.tenant_id,
	                    Email.email,
	                    User.type,
	                    Person.name,
	                    Person.type AS person_type,
	                    Person.document
	            FROM {$usersTable} AS User
            INNER JOIN {$peopleTable} AS Person ON Person.id = User.person_id
            INNER JOIN {$emailsTable} AS Email ON Email.person_id = Person.id
            WHERE User.active = 1
              AND User.type IN ('admin', 'user')
              AND Email.email = :email
              AND Email.is_login = '1'
            LIMIT 1";

    $stmt = $this->db->prepare($sql);
    $stmt->execute([':email' => trim($email)]);

    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
    return $row ?: null;
    }

	/**
	 * Authenticates a user by email and password.
	 *
	 * @param string $email User email.
	 * @param string $password Plain-text user password.
	 * @return bool|array
	 */	
	public function authenticate($email, $password)
		{
		$user = $this->findByEmail($email);
		if (!$user)
			{
			return false;
			}

		if (!$user['active'])
			{
			return false;
			}

		if (password_verify($password, $user['password']))
			{
			// Optionally rehash the password when needed.
			if (password_needs_rehash($user['password'], PASSWORD_DEFAULT))
				{
				$usersTable = $this->managerTable('users');
				$newHash = password_hash($password, PASSWORD_DEFAULT);
				$stmt = $this->db->prepare("UPDATE {$usersTable} SET password = :password WHERE id = :id");
				$stmt->execute(['password' => $newHash, 'id' => $user['id']]);
				}
			// Remove the password hash before returning the user payload.
			unset($user['password']);
			return $user;
			}

		return false;
		}
	}
?>
