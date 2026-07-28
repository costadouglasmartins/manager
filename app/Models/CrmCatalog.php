<?php

namespace App\Models;

use App\Core\TenantModel;
use PDO;

class CrmCatalog extends TenantModel
	{
	/**
	 * Catalog definitions allowed to resolve tenant table identifiers.
	 *
	 * @var array<string, array<string, mixed>>
	 **/
	private const CATALOGS = [
		'source' => [
			'table' => 'crm_sources',
			'resource' => 'crm_source',
			'label' => 'Origem',
			'list_title' => 'Origens do CRM',
			'create_title' => 'Nova origem do CRM',
			'edit_title' => 'Editar origem do CRM',
			'information_title' => 'Origem do CRM',
			'delete_description' => 'a origem CRM',
			'empty_message' => 'Nenhuma origem CRM encontrada.',
			'icon' => 'bi-box-arrow-in-right',
			'is_pipeline' => false,
		],
		'inactivity_reason' => [
			'table' => 'crm_inactivity_reasons',
			'resource' => 'crm_inactivity_reason',
			'label' => 'Motivo de inatividade',
			'list_title' => 'Motivos de inatividade do CRM',
			'create_title' => 'Novo motivo de inatividade do CRM',
			'edit_title' => 'Editar motivo de inatividade do CRM',
			'information_title' => 'Motivo de inatividade do CRM',
			'delete_description' => 'o motivo de inatividade',
			'empty_message' => 'Nenhum motivo de inatividade encontrado.',
			'icon' => 'bi-person-dash',
			'is_pipeline' => false,
		],
		'loss_reason' => [
			'table' => 'crm_loss_reasons',
			'resource' => 'crm_loss_reason',
			'label' => 'Motivo de perda',
			'list_title' => 'Motivos de perda do CRM',
			'create_title' => 'Novo motivo de perda do CRM',
			'edit_title' => 'Editar motivo de perda do CRM',
			'information_title' => 'Motivo de perda do CRM',
			'delete_description' => 'o motivo de perda',
			'empty_message' => 'Nenhum motivo de perda encontrado.',
			'icon' => 'bi-x-octagon',
			'is_pipeline' => false,
		],
		'opportunity_type' => [
			'table' => 'crm_opportunity_types',
			'resource' => 'crm_opportunity_type',
			'label' => 'Tipo de oportunidade',
			'list_title' => 'Tipos de oportunidade do CRM',
			'create_title' => 'Novo tipo de oportunidade do CRM',
			'edit_title' => 'Editar tipo de oportunidade do CRM',
			'information_title' => 'Tipo de oportunidade do CRM',
			'delete_description' => 'o tipo de oportunidade',
			'empty_message' => 'Nenhum tipo de oportunidade encontrado.',
			'icon' => 'bi-briefcase',
			'is_pipeline' => false,
		],
		'pipeline_stage' => [
			'table' => 'crm_pipeline_stages',
			'resource' => 'crm_pipeline_stage',
			'label' => 'Etapa do funil',
			'list_title' => 'Etapas do funil do CRM',
			'create_title' => 'Nova etapa do funil do CRM',
			'edit_title' => 'Editar etapa do funil do CRM',
			'information_title' => 'Etapa do funil do CRM',
			'delete_description' => 'a etapa do funil',
			'empty_message' => 'Nenhuma etapa do funil encontrada.',
			'icon' => 'bi-funnel',
			'is_pipeline' => true,
		],
	];

	private string $catalogKey;
	private array $catalogMeta;

	/**
	 * Connects the model to the selected tenant and resolves an allowed catalog.
	 *
	 * @param string $catalogKey Catalog allowlist key.
	 **/
	public function __construct(string $catalogKey)
		{
		$catalogKey = trim($catalogKey);
		if (!isset(self::CATALOGS[$catalogKey]))
			{
			throw new \InvalidArgumentException('Catálogo CRM inválido.');
			}

		$this->catalogKey = $catalogKey;
		$this->catalogMeta = self::CATALOGS[$catalogKey];
		parent::__construct();
		}

	/**
	 * Returns safe metadata used by concrete controllers and shared views.
	 *
	 * @return array<string, mixed>
	 **/
	public function meta(): array
		{
		return ['key' => $this->catalogKey] + $this->catalogMeta;
		}

	/**
	 * Searches the selected catalog in the current tenant.
	 *
	 * @param array<string, mixed> $filters Search filters.
	 * @param int $limit Page size.
	 * @param int $offset Page offset.
	 * @return array<int, array<string, mixed>>
	 **/
	public function search(array $filters = [], int $limit = 10, int $offset = 0): array
		{
		$sql = $this->baseSelectSql() . " WHERE 1 = 1";
		$params = [];
		$this->appendFilters($sql, $params, $filters);
		$sql .= " ORDER BY Catalog.sort_order ASC, Catalog.name ASC, Catalog.id ASC
		          LIMIT :limit OFFSET :offset";

		$stmt = $this->db->prepare($sql);
		$this->bindParams($stmt, $params);
		$stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
		$stmt->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
		$stmt->execute();

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Counts records using the same catalog filters.
	 *
	 * @param array<string, mixed> $filters Search filters.
	 **/
	public function count(array $filters = []): int
		{
		$table = $this->tableName();
		$sql = "SELECT COUNT(*) AS total
		        FROM {$table} AS Catalog
		        WHERE 1 = 1";
		$params = [];
		$this->appendFilters($sql, $params, $filters);

		$stmt = $this->db->prepare($sql);
		$this->bindParams($stmt, $params);
		$stmt->execute();

		return (int) $stmt->fetchColumn();
		}

	/**
	 * Returns catalog records ordered for form selects.
	 *
	 * @return array<int, array<string, mixed>>
	 **/
	public function getOptions(bool $activeOnly = true, ?int $includeId = null): array
		{
		$sql = $this->baseSelectSql() . " WHERE 1 = 1";
		$params = [];

		if ($activeOnly)
			{
			$sql .= " AND (Catalog.active = 1";
			if ($includeId !== null && $includeId > 0)
				{
				$sql .= " OR Catalog.id = :include_id";
				$params['include_id'] = $includeId;
				}
			$sql .= ")";
			}

		$sql .= " ORDER BY Catalog.active DESC, Catalog.sort_order ASC, Catalog.name ASC, Catalog.id ASC";
		$stmt = $this->db->prepare($sql);
		$this->bindParams($stmt, $params);
		$stmt->execute();

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Finds one catalog record by id.
	 *
	 * @param mixed $id Catalog record identifier.
	 * @return array<string, mixed>|false
	 **/
	public function find($id): array|false
		{
		$id = (int) $id;
		if ($id <= 0)
			{
			return false;
			}

		$stmt = $this->db->prepare($this->baseSelectSql() . "
			WHERE Catalog.id = :id
			LIMIT 1
		");
		$stmt->execute(['id' => $id]);

		return $stmt->fetch(PDO::FETCH_ASSOC);
		}

	/**
	 * Creates one catalog record and audits the resulting snapshot.
	 *
	 * @param array<string, mixed> $data Submitted data.
	 * @param int $userId Authenticated manager user id.
	 * @return array{id: int}
	 **/
	public function create(array $data, int $userId): array
		{
		$payload = $this->normalizePayload($data);
		$this->assertUniqueSlug($payload['slug']);
		$now = date('Y-m-d H:i:s');

		try
			{
			$this->db->beginTransaction();
			$columns = "name, slug, description, bootstrap_class, sort_order, active";
			$values = ":name, :slug, :description, :bootstrap_class, :sort_order, :active";
			$params = [
				'name' => $payload['name'],
				'slug' => $payload['slug'],
				'description' => $payload['description'],
				'bootstrap_class' => $payload['bootstrap_class'],
				'sort_order' => $payload['sort_order'],
				'active' => $payload['active'],
			];

			if ($this->isPipeline())
				{
				$columns .= ", default_probability, is_won, is_lost";
				$values .= ", :default_probability, :is_won, :is_lost";
				$params['default_probability'] = $payload['default_probability'];
				$params['is_won'] = $payload['is_won'];
				$params['is_lost'] = $payload['is_lost'];
				}

			$columns .= ", created_by_user_id, updated_by_user_id, created, modified";
			$values .= ", :created_by_user_id, :updated_by_user_id, :created, :modified";
			$params['created_by_user_id'] = $userId > 0 ? $userId : null;
			$params['updated_by_user_id'] = $userId > 0 ? $userId : null;
			$params['created'] = $now;
			$params['modified'] = $now;
			$table = $this->tableName();

			$stmt = $this->db->prepare("
				INSERT INTO {$table} ({$columns})
				VALUES ({$values})
			");
			$stmt->execute($params);

			$id = (int) $this->db->lastInsertId();
			$afterData = $this->find($id);
			$this->auditCreate(
				(string) $this->catalogMeta['resource'],
				$this->tableName(),
				$id,
				$afterData
			);
			$this->db->commit();

			return ['id' => $id];
			}
		catch (\Throwable $e)
			{
			if ($this->db->inTransaction())
				{
				$this->db->rollBack();
				}

			throw $e;
			}
		}

	/**
	 * Updates one catalog record and audits its before and after snapshots.
	 *
	 * @param mixed $id Catalog record identifier.
	 * @param array<string, mixed> $data Submitted data.
	 * @param int $userId Authenticated manager user id.
	 **/
	public function update($id, array $data, int $userId): void
		{
		$id = (int) $id;
		$beforeData = $this->find($id);
		if (!$beforeData)
			{
			throw new \InvalidArgumentException($this->catalogMeta['label'] . ' não encontrado(a).');
			}

		$payload = $this->normalizePayload($data, $beforeData);
		$this->assertUniqueSlug($payload['slug'], $id);

		try
			{
			$this->db->beginTransaction();
			$assignments = "
				name = :name,
				slug = :slug,
				description = :description,
				bootstrap_class = :bootstrap_class,
				sort_order = :sort_order,
				active = :active
			";
			$params = [
				'name' => $payload['name'],
				'slug' => $payload['slug'],
				'description' => $payload['description'],
				'bootstrap_class' => $payload['bootstrap_class'],
				'sort_order' => $payload['sort_order'],
				'active' => $payload['active'],
			];

			if ($this->isPipeline())
				{
				$assignments .= ",
					default_probability = :default_probability,
					is_won = :is_won,
					is_lost = :is_lost
				";
				$params['default_probability'] = $payload['default_probability'];
				$params['is_won'] = $payload['is_won'];
				$params['is_lost'] = $payload['is_lost'];
				}

			$assignments .= ",
				updated_by_user_id = :updated_by_user_id,
				modified = :modified
			";
			$params['updated_by_user_id'] = $userId > 0 ? $userId : null;
			$params['modified'] = date('Y-m-d H:i:s');
			$params['id'] = $id;
			$table = $this->tableName();

			$stmt = $this->db->prepare("
				UPDATE {$table}
				SET {$assignments}
				WHERE id = :id
			");
			$stmt->execute($params);

			$afterData = $this->find($id);
			$this->auditUpdate(
				(string) $this->catalogMeta['resource'],
				$this->tableName(),
				$id,
				$beforeData,
				$afterData
			);
			$this->db->commit();
			}
		catch (\Throwable $e)
			{
			if ($this->db->inTransaction())
				{
				$this->db->rollBack();
				}

			throw $e;
			}
		}

	/**
	 * Deactivates a catalog record while retaining historical relationships.
	 *
	 * @param mixed $id Catalog record identifier.
	 * @param int $userId Authenticated manager user id.
	 **/
	public function delete($id, int $userId): void
		{
		$id = (int) $id;
		$beforeData = $this->find($id);
		if (!$beforeData)
			{
			throw new \InvalidArgumentException($this->catalogMeta['label'] . ' não encontrado(a).');
			}

		try
			{
			$this->db->beginTransaction();
			$table = $this->tableName();
			$stmt = $this->db->prepare("
				UPDATE {$table}
				SET active = 0,
				    updated_by_user_id = :updated_by_user_id,
				    modified = :modified
				WHERE id = :id
			");
			$stmt->execute([
				'updated_by_user_id' => $userId > 0 ? $userId : null,
				'modified' => date('Y-m-d H:i:s'),
				'id' => $id,
			]);

			$afterData = $this->find($id);
			$this->auditUpdate(
				(string) $this->catalogMeta['resource'],
				$this->tableName(),
				$id,
				$beforeData,
				$afterData,
				['operation' => 'deactivate_catalog_record']
			);
			$this->db->commit();
			}
		catch (\Throwable $e)
			{
			if ($this->db->inTransaction())
				{
				$this->db->rollBack();
				}

			throw $e;
			}
		}

	/**
	 * Checks whether a catalog id is active or is the current historical value.
	 **/
	public function isAssignable(int $id, ?int $currentId = null): bool
		{
		$row = $this->find($id);
		if (!$row)
			{
			return false;
			}

		return (int) ($row['active'] ?? 0) === 1
			|| ($currentId !== null && $id === $currentId);
		}

	/**
	 * Returns a preferred active id or the first active record by display order.
	 **/
	public function defaultId(?string $preferredSlug = null): ?int
		{
		$preferredSlug = trim((string) ($preferredSlug ?? ''));
		$table = $this->tableName();
		if ($preferredSlug !== '')
			{
			$stmt = $this->db->prepare("
				SELECT id
				FROM {$table}
				WHERE slug = :slug
				  AND active = 1
				LIMIT 1
			");
			$stmt->execute(['slug' => $preferredSlug]);
			$id = $stmt->fetchColumn();
			if ($id !== false)
				{
				return (int) $id;
				}
			}

		$stmt = $this->db->query("
			SELECT id
			FROM {$table}
			WHERE active = 1
			ORDER BY sort_order ASC, name ASC, id ASC
			LIMIT 1
		");
		$id = $stmt->fetchColumn();

		return $id !== false ? (int) $id : null;
		}

	/**
	 * Returns the explicit column list for the selected catalog.
	 **/
	private function baseSelectSql(): string
		{
		$table = $this->tableName();
		$pipelineColumns = '';
		if ($this->isPipeline())
			{
			$pipelineColumns = ",
			       Catalog.default_probability,
			       Catalog.is_won,
			       Catalog.is_lost";
			}

		return "SELECT Catalog.id,
		              Catalog.name,
		              Catalog.slug,
		              Catalog.description,
		              Catalog.bootstrap_class,
		              Catalog.sort_order,
		              Catalog.active{$pipelineColumns},
		              Catalog.created_by_user_id,
		              Catalog.updated_by_user_id,
		              Catalog.created,
		              Catalog.modified
		       FROM {$table} AS Catalog";
		}

	/**
	 * Adds shared and pipeline-specific search filters.
	 *
	 * @param array<string, mixed> $params Bound query values.
	 **/
	private function appendFilters(string &$sql, array &$params, array $filters): void
		{
		$idFilter = $this->filterValue($filters, 'id');
		if ($idFilter !== '')
			{
			$sql .= " AND Catalog.id = :id";
			$params['id'] = (int) $idFilter;
			}

		foreach (['name', 'slug', 'bootstrap_class'] as $field)
			{
			$value = $this->filterValue($filters, $field);
			if ($value === '')
				{
				continue;
				}

			$sql .= " AND Catalog.{$field} LIKE :{$field}";
			$params[$field] = '%' . $value . '%';
			}

		$activeFilter = $this->filterValue($filters, 'active');
		if (in_array($activeFilter, ['0', '1'], true))
			{
			$sql .= " AND Catalog.active = :active";
			$params['active'] = (int) $activeFilter;
			}

		if ($this->isPipeline())
			{
			foreach (['is_won', 'is_lost'] as $field)
				{
				$value = $this->filterValue($filters, $field);
				if (!in_array($value, ['0', '1'], true))
					{
					continue;
					}

				$sql .= " AND Catalog.{$field} = :{$field}";
				$params[$field] = (int) $value;
				}
			}
		}

	/**
	 * Returns one scalar filter or an empty value for unsupported structures.
	 **/
	private function filterValue(array $filters, string $key): string
		{
		$value = $filters[$key] ?? '';
		if (is_array($value) || is_object($value))
			{
			return '';
			}

		return trim((string) ($value ?? ''));
		}

	/**
	 * Normalizes and validates common catalog fields and pipeline semantics.
	 *
	 * @param array<string, mixed> $data Submitted data.
	 * @param array<string, mixed> $defaults Existing values during update.
	 * @return array<string, mixed>
	 **/
	private function normalizePayload(array $data, array $defaults = []): array
		{
		$name = trim($this->scalarInput($data, 'name', (string) ($defaults['name'] ?? '')));
		$this->assertLength($name, 120, 'O nome');
		if ($name === '')
			{
			throw new \InvalidArgumentException('Informe o nome do cadastro.');
			}

		$slugInput = trim($this->scalarInput($data, 'slug', (string) ($defaults['slug'] ?? '')));
		$slug = $slugInput !== '' ? strtolower($slugInput) : $this->slugFromName($name);
		$this->assertLength($slug, 140, 'A chave');
		if (!preg_match('/^[a-z0-9]+(?:[_-][a-z0-9]+)*$/', $slug))
			{
			throw new \InvalidArgumentException('Informe uma chave com letras minúsculas, números, hífens ou sublinhados.');
			}

		$description = trim($this->scalarInput($data, 'description', (string) ($defaults['description'] ?? '')));
		$this->assertLength($description, 1000, 'A descrição');

		$bootstrapClass = trim($this->scalarInput(
			$data,
			'bootstrap_class',
			(string) ($defaults['bootstrap_class'] ?? 'text-bg-secondary')
		));
		if ($bootstrapClass === '')
			{
			$bootstrapClass = 'text-bg-secondary';
			}
		$this->assertLength($bootstrapClass, 120, 'A classe Bootstrap');
		if (!preg_match('/^[A-Za-z0-9:_ -]+$/', $bootstrapClass))
			{
			throw new \InvalidArgumentException('Informe apenas classes CSS Bootstrap válidas para o estilo.');
			}

		$sortOrder = $this->integerInput($data, 'sort_order', (int) ($defaults['sort_order'] ?? 0), 'ordem');
		if ($sortOrder < 0)
			{
			throw new \InvalidArgumentException('A ordem deve ser maior ou igual a zero.');
			}

		$active = $this->booleanInput($data, 'active', (int) ($defaults['active'] ?? 1), 'status');
		$payload = [
			'name' => $name,
			'slug' => $slug,
			'description' => $description !== '' ? $description : null,
			'bootstrap_class' => $bootstrapClass,
			'sort_order' => $sortOrder,
			'active' => $active,
		];

		if ($this->isPipeline())
			{
			$probability = $this->integerInput(
				$data,
				'default_probability',
				(int) ($defaults['default_probability'] ?? 0),
				'probabilidade padrão'
			);
			if ($probability < 0 || $probability > 100)
				{
				throw new \InvalidArgumentException('A probabilidade padrão deve estar entre 0 e 100.');
				}

			$isWon = $this->booleanInput($data, 'is_won', (int) ($defaults['is_won'] ?? 0), 'etapa ganha');
			$isLost = $this->booleanInput($data, 'is_lost', (int) ($defaults['is_lost'] ?? 0), 'etapa perdida');
			if ($isWon === 1 && $isLost === 1)
				{
				throw new \InvalidArgumentException('Uma etapa não pode representar ganho e perda ao mesmo tempo.');
				}

			$payload['default_probability'] = $probability;
			$payload['is_won'] = $isWon;
			$payload['is_lost'] = $isLost;
			}

		return $payload;
		}

	/**
	 * Ensures the slug is unique inside the current tenant catalog.
	 **/
	private function assertUniqueSlug(string $slug, ?int $ignoreId = null): void
		{
		$table = $this->tableName();
		$sql = "SELECT id
		        FROM {$table}
		        WHERE slug = :slug";
		$params = ['slug' => $slug];

		if ($ignoreId !== null)
			{
			$sql .= " AND id <> :ignore_id";
			$params['ignore_id'] = $ignoreId;
			}

		$sql .= " LIMIT 1";
		$stmt = $this->db->prepare($sql);
		$this->bindParams($stmt, $params);
		$stmt->execute();

		if ($stmt->fetchColumn())
			{
			throw new \InvalidArgumentException('Já existe um cadastro com essa chave neste tenant.');
			}
		}

	/**
	 * Reads a scalar request field and rejects arrays or objects.
	 **/
	private function scalarInput(array $data, string $key, string $default): string
		{
		if (!array_key_exists($key, $data))
			{
			return $default;
			}

		$value = $data[$key];
		if (is_array($value) || is_object($value))
			{
			throw new \InvalidArgumentException('Formato inválido para o campo ' . $key . '.');
			}

		return (string) ($value ?? '');
		}

	/**
	 * Reads and validates an integer request field.
	 **/
	private function integerInput(array $data, string $key, int $default, string $label): int
		{
		if (!array_key_exists($key, $data))
			{
			return $default;
			}

		$value = $data[$key];
		if (is_array($value) || is_object($value))
			{
			throw new \InvalidArgumentException('Formato inválido para o campo ' . $label . '.');
			}

		$value = trim((string) $value);
		$validated = filter_var($value, FILTER_VALIDATE_INT);
		if ($validated === false)
			{
			throw new \InvalidArgumentException('Informe um valor inteiro válido para ' . $label . '.');
			}

		return (int) $validated;
		}

	/**
	 * Reads a strict zero-or-one request field.
	 **/
	private function booleanInput(array $data, string $key, int $default, string $label): int
		{
		if (!array_key_exists($key, $data))
			{
			return $default === 1 ? 1 : 0;
			}

		$value = $data[$key];
		if (is_array($value) || is_object($value) || !in_array((string) $value, ['0', '1'], true))
			{
			throw new \InvalidArgumentException('Informe um valor válido para ' . $label . '.');
			}

		return (string) $value === '1' ? 1 : 0;
		}

	/**
	 * Enforces a UTF-8 aware maximum string length.
	 **/
	private function assertLength(string $value, int $maxLength, string $label): void
		{
		$length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
		if ($length > $maxLength)
			{
			throw new \InvalidArgumentException($label . ' deve ter no máximo ' . $maxLength . ' caracteres.');
			}
		}

	/**
	 * Generates a safe slug when the form leaves it empty.
	 **/
	private function slugFromName(string $name): string
		{
		$slug = trim($name);
		if (function_exists('iconv'))
			{
			$converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $slug);
			if (is_string($converted) && $converted !== '')
				{
				$slug = $converted;
				}
			}

		$slug = strtolower($slug);
		$slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
		$slug = trim($slug, '-');

		return $slug !== '' ? $slug : 'cadastro';
		}

	/**
	 * Returns the fixed table identifier selected from the allowlist.
	 **/
	private function tableName(): string
		{
		return (string) $this->catalogMeta['table'];
		}

	/**
	 * Indicates whether the selected catalog stores pipeline semantics.
	 **/
	private function isPipeline(): bool
		{
		return !empty($this->catalogMeta['is_pipeline']);
		}

	/**
	 * Binds scalar parameters with their correct PDO types.
	 *
	 * @param array<string, mixed> $params Query parameters.
	 **/
	private function bindParams(\PDOStatement $stmt, array $params): void
		{
		foreach ($params as $key => $value)
			{
			$stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
			}
		}
	}
?>
