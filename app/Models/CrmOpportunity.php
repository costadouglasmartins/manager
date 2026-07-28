<?php

namespace App\Models;

use App\Core\Database;
use App\Core\TenantModel;
use PDO;

class CrmOpportunity extends TenantModel
	{
	public function search(array $filters = [], int $limit = 10, int $offset = 0, int $tenantId = 0, string $sort = 'modified', string $direction = 'desc'): array
		{
		$sql = $this->baseSelectSql() . "
			WHERE TenantPerson.tenant_id = :tenant_id
		";
		$params = ['tenant_id' => $tenantId];

		$this->appendFilters($sql, $params, $filters);
		$sql .= $this->orderBySql($sort, $direction);
		$sql .= " LIMIT :limit OFFSET :offset";

		$stmt = $this->db->prepare($sql);
		$this->bindParams($stmt, $params);
		$stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
		$stmt->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
		$stmt->execute();

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	public function count(array $filters = [], int $tenantId = 0): int
		{
		$sql = $this->baseCountSql() . "
			WHERE TenantPerson.tenant_id = :tenant_id
		";
		$params = ['tenant_id' => $tenantId];

		$this->appendFilters($sql, $params, $filters);

		$stmt = $this->db->prepare($sql);
		$this->bindParams($stmt, $params);
		$stmt->execute();

		return (int) $stmt->fetchColumn();
		}

	public function byCrmId(int $crmId, int $tenantId, int $limit = 20): array
		{
		$sql = $this->baseSelectSql() . "
			WHERE Opportunity.tenant_person_id = :crm_id
			  AND TenantPerson.tenant_id = :tenant_id
			  AND Opportunity.active = 1
			ORDER BY Stage.sort_order ASC, Opportunity.expected_close_date IS NULL ASC,
			         Opportunity.expected_close_date ASC, Opportunity.id DESC
			LIMIT :limit
		";

		$stmt = $this->db->prepare($sql);
		$stmt->bindValue(':crm_id', $crmId, PDO::PARAM_INT);
		$stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
		$stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
		$stmt->execute();

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	public function findAllByCrmOpportunityId($id, int $tenantId): array
		{
		$stmt = $this->db->prepare($this->baseSelectSql() . "
			WHERE Opportunity.id = :id
			  AND TenantPerson.tenant_id = :tenant_id
			LIMIT 1
		");
		$stmt->execute([
			'id' => (int) $id,
			'tenant_id' => $tenantId,
		]);

		return ['crm_opportunity' => $stmt->fetch(PDO::FETCH_ASSOC) ?: null];
		}

	public function create(array $data, int $tenantId, int $userId): array
		{
		$payload = $this->normalizePayload($data, $tenantId, $userId);
		$now = date('Y-m-d H:i:s');

		try
			{
			$this->db->beginTransaction();
			$stmt = $this->db->prepare("
				INSERT INTO crm_opportunities
					(tenant_person_id, person_id, crm_source_id, opportunity_type_id, pipeline_stage_id,
					 loss_reason_id, title, description, estimated_value, probability, expected_close_date,
					 closed_at, responsible_user_id, active, created_by_user_id, updated_by_user_id, created, modified)
				VALUES
					(:tenant_person_id, :person_id, :crm_source_id, :opportunity_type_id, :pipeline_stage_id,
					 :loss_reason_id, :title, :description, :estimated_value, :probability, :expected_close_date,
					 :closed_at, :responsible_user_id, :active, :created_by_user_id, :updated_by_user_id, :created, :modified)
			");
			$stmt->execute([
				'tenant_person_id' => $payload['tenant_person_id'],
				'person_id' => $payload['person_id'],
				'crm_source_id' => $payload['crm_source_id'],
				'opportunity_type_id' => $payload['opportunity_type_id'],
				'pipeline_stage_id' => $payload['pipeline_stage_id'],
				'loss_reason_id' => $payload['loss_reason_id'],
				'title' => $payload['title'],
				'description' => $payload['description'],
				'estimated_value' => $payload['estimated_value'],
				'probability' => $payload['probability'],
				'expected_close_date' => $payload['expected_close_date'],
				'closed_at' => $payload['closed_at'],
				'responsible_user_id' => $payload['responsible_user_id'],
				'active' => $payload['active'],
				'created_by_user_id' => $userId > 0 ? $userId : null,
				'updated_by_user_id' => $userId > 0 ? $userId : null,
				'created' => $now,
				'modified' => $now,
			]);

			$opportunityId = (int) $this->db->lastInsertId();
			$afterData = $this->findAllByCrmOpportunityId($opportunityId, $tenantId);
			$this->auditCreate('crm_opportunity', 'crm_opportunities', $opportunityId, $afterData, [
				'affected_tables' => ['crm_opportunities'],
			]);
			$this->db->commit();

			return ['crm_opportunity_id' => $opportunityId, 'tenant_person_id' => $payload['tenant_person_id']];
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

	public function update($id, array $data, int $tenantId, int $userId): void
		{
		$opportunityId = (int) $id;
		$beforeData = $this->findAllByCrmOpportunityId($opportunityId, $tenantId);
		if (empty($beforeData['crm_opportunity']))
			{
			throw new \InvalidArgumentException('Oportunidade CRM não encontrada.');
			}

		$payload = $this->normalizePayload($data, $tenantId, $userId, $beforeData['crm_opportunity']);

		try
			{
			$this->db->beginTransaction();
			$stmt = $this->db->prepare("
				UPDATE crm_opportunities
				SET tenant_person_id = :tenant_person_id,
				    person_id = :person_id,
				    crm_source_id = :crm_source_id,
				    opportunity_type_id = :opportunity_type_id,
				    pipeline_stage_id = :pipeline_stage_id,
				    loss_reason_id = :loss_reason_id,
				    title = :title,
				    description = :description,
				    estimated_value = :estimated_value,
				    probability = :probability,
				    expected_close_date = :expected_close_date,
				    closed_at = :closed_at,
				    responsible_user_id = :responsible_user_id,
				    active = :active,
				    updated_by_user_id = :updated_by_user_id,
				    modified = :modified
				WHERE id = :id
			");
			$stmt->execute([
				'tenant_person_id' => $payload['tenant_person_id'],
				'person_id' => $payload['person_id'],
				'crm_source_id' => $payload['crm_source_id'],
				'opportunity_type_id' => $payload['opportunity_type_id'],
				'pipeline_stage_id' => $payload['pipeline_stage_id'],
				'loss_reason_id' => $payload['loss_reason_id'],
				'title' => $payload['title'],
				'description' => $payload['description'],
				'estimated_value' => $payload['estimated_value'],
				'probability' => $payload['probability'],
				'expected_close_date' => $payload['expected_close_date'],
				'closed_at' => $payload['closed_at'],
				'responsible_user_id' => $payload['responsible_user_id'],
				'active' => $payload['active'],
				'updated_by_user_id' => $userId > 0 ? $userId : null,
				'modified' => date('Y-m-d H:i:s'),
				'id' => $opportunityId,
			]);

			$afterData = $this->findAllByCrmOpportunityId($opportunityId, $tenantId);
			$this->auditUpdate('crm_opportunity', 'crm_opportunities', $opportunityId, $beforeData, $afterData);
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

	public function moveStage($id, int $stageId, int $tenantId, int $userId): void
		{
		$opportunityId = (int) $id;
		$beforeData = $this->findAllByCrmOpportunityId($opportunityId, $tenantId);
		if (empty($beforeData['crm_opportunity']))
			{
			throw new \InvalidArgumentException('Oportunidade CRM não encontrada.');
			}

		$current = $beforeData['crm_opportunity'];
		$stage = $this->assertStage($stageId, (int) ($current['pipeline_stage_id'] ?? 0));
		$lossReasonId = (int) ($current['loss_reason_id'] ?? 0) ?: null;
		if ((int) ($stage['is_lost'] ?? 0) === 1 && $lossReasonId === null)
			{
			$lossReasonId = $this->defaultLossReasonId();
			}
		if ((int) ($stage['is_lost'] ?? 0) !== 1)
			{
			$lossReasonId = null;
			}

		$closedAt = $this->closedAtForStage($stage, $current['closed_at'] ?? null);
		$probability = (int) ($stage['default_probability'] ?? $current['probability'] ?? 0);

		try
			{
			$this->db->beginTransaction();
			$stmt = $this->db->prepare("
				UPDATE crm_opportunities
				SET pipeline_stage_id = :pipeline_stage_id,
				    probability = :probability,
				    loss_reason_id = :loss_reason_id,
				    closed_at = :closed_at,
				    updated_by_user_id = :updated_by_user_id,
				    modified = :modified
				WHERE id = :id
			");
			$stmt->execute([
				'pipeline_stage_id' => (int) $stage['id'],
				'probability' => $probability,
				'loss_reason_id' => $lossReasonId,
				'closed_at' => $closedAt,
				'updated_by_user_id' => $userId > 0 ? $userId : null,
				'modified' => date('Y-m-d H:i:s'),
				'id' => $opportunityId,
			]);

			$afterData = $this->findAllByCrmOpportunityId($opportunityId, $tenantId);
			$this->auditUpdate('crm_opportunity', 'crm_opportunities', $opportunityId, $beforeData, $afterData, [
				'operation' => 'move_stage',
			]);
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

	public function delete($id, int $tenantId, int $userId): void
		{
		$opportunityId = (int) $id;
		$beforeData = $this->findAllByCrmOpportunityId($opportunityId, $tenantId);
		if (empty($beforeData['crm_opportunity']))
			{
			throw new \InvalidArgumentException('Oportunidade CRM não encontrada.');
			}

		try
			{
			$this->db->beginTransaction();
			$stmt = $this->db->prepare("
				UPDATE crm_opportunities
				SET active = 0,
				    updated_by_user_id = :updated_by_user_id,
				    modified = :modified
				WHERE id = :id
			");
			$stmt->execute([
				'updated_by_user_id' => $userId > 0 ? $userId : null,
				'modified' => date('Y-m-d H:i:s'),
				'id' => $opportunityId,
			]);

			$afterData = $this->findAllByCrmOpportunityId($opportunityId, $tenantId);
			$this->auditUpdate('crm_opportunity', 'crm_opportunities', $opportunityId, $beforeData, $afterData, [
				'operation' => 'deactivate_opportunity',
			]);
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

	public function kanban(int $tenantId): array
		{
		$stages = (new CrmCatalog('pipeline_stage'))->getOptions(false);
		$rows = $this->search(['active' => '1'], 1000, 0, $tenantId, 'expected_close_date', 'asc');
		$columns = [];

		foreach ($stages as $stage)
			{
			$columns[(int) $stage['id']] = [
				'stage' => $stage,
				'opportunities' => [],
				'total_value' => 0.0,
			];
			}

		foreach ($rows as $row)
			{
			$stageId = (int) ($row['pipeline_stage_id'] ?? 0);
			if (!isset($columns[$stageId]))
				{
				continue;
				}

			$columns[$stageId]['opportunities'][] = $row;
			$columns[$stageId]['total_value'] += (float) ($row['estimated_value'] ?? 0);
			}

		return array_values($columns);
		}

	public function report(int $tenantId): array
		{
		$stmt = $this->db->prepare("
			SELECT Stage.id,
			       Stage.name,
			       Stage.bootstrap_class,
			       Stage.sort_order,
			       Stage.is_won,
			       Stage.is_lost,
			       COUNT(Opportunity.id) AS total,
			       COALESCE(SUM(Opportunity.estimated_value), 0) AS total_value,
			       COALESCE(SUM(Opportunity.estimated_value * Opportunity.probability / 100), 0) AS weighted_value
			FROM crm_pipeline_stages AS Stage
			LEFT JOIN (
				SELECT Opportunity.*
				FROM crm_opportunities AS Opportunity
				INNER JOIN " . $this->managerTable('tenant_people') . " AS TenantPerson
					ON TenantPerson.id = Opportunity.tenant_person_id
					AND TenantPerson.person_id = Opportunity.person_id
					AND TenantPerson.tenant_id = :tenant_id
				WHERE Opportunity.active = 1
			) AS Opportunity
				ON Opportunity.pipeline_stage_id = Stage.id
			WHERE Stage.active = 1
			GROUP BY Stage.id, Stage.name, Stage.bootstrap_class, Stage.sort_order, Stage.is_won, Stage.is_lost
			ORDER BY Stage.sort_order ASC, Stage.id ASC
		");
		$stmt->execute(['tenant_id' => $tenantId]);
		$stages = $stmt->fetchAll(PDO::FETCH_ASSOC);

		$stmt = $this->db->prepare("
			SELECT COUNT(Opportunity.id) AS total,
			       COALESCE(SUM(Opportunity.estimated_value), 0) AS total_value,
			       COALESCE(SUM(Opportunity.estimated_value * Opportunity.probability / 100), 0) AS weighted_value,
			       SUM(CASE WHEN Stage.is_won = 1 THEN 1 ELSE 0 END) AS won_total,
			       SUM(CASE WHEN Stage.is_lost = 1 THEN 1 ELSE 0 END) AS lost_total
			FROM crm_opportunities AS Opportunity
			INNER JOIN " . $this->managerTable('tenant_people') . " AS TenantPerson
				ON TenantPerson.id = Opportunity.tenant_person_id
				AND TenantPerson.person_id = Opportunity.person_id
				AND TenantPerson.tenant_id = :tenant_id
			LEFT JOIN crm_pipeline_stages AS Stage ON Stage.id = Opportunity.pipeline_stage_id
			WHERE Opportunity.active = 1
		");
		$stmt->execute(['tenant_id' => $tenantId]);
		$summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

		return [
			'summary' => $summary,
			'stages' => $stages,
		];
		}

	public function crmOptions(int $tenantId, ?int $includeCrmId = null, int $limit = 200): array
		{
		return (new CrmActivity())->crmOptions($tenantId, $includeCrmId, $limit);
		}

	private function normalizePayload(array $data, int $tenantId, int $userId, ?array $current = null): array
		{
		$crmId = (int) ($data['tenant_person_id'] ?? $data['crm_id'] ?? $current['tenant_person_id'] ?? 0);
		$link = $this->crmLinkById($crmId, $tenantId);
		if (!$link)
			{
			throw new \InvalidArgumentException('Selecione um contato CRM válido para este tenant.');
			}

		$stageId = (int) ($data['pipeline_stage_id'] ?? $current['pipeline_stage_id'] ?? 0);
		if ($stageId <= 0)
			{
			$stageId = $this->defaultStageId();
			}
		$stage = $this->assertStage($stageId, isset($current['pipeline_stage_id']) ? (int) $current['pipeline_stage_id'] : null);

		$sourceId = $this->nullableCatalogId($data, 'crm_source_id', $current, 'crm_source_id', 'source');
		$typeId = $this->nullableCatalogId($data, 'opportunity_type_id', $current, 'opportunity_type_id', 'opportunity_type');
		$lossReasonId = $this->nullableCatalogId($data, 'loss_reason_id', $current, 'loss_reason_id', 'loss_reason');

		if ((int) ($stage['is_lost'] ?? 0) === 1 && $lossReasonId === null)
			{
			throw new \InvalidArgumentException('Informe o motivo de perda para oportunidades perdidas.');
			}
		if ((int) ($stage['is_lost'] ?? 0) !== 1)
			{
			$lossReasonId = null;
			}

		$title = substr(trim((string) ($data['title'] ?? $current['title'] ?? '')), 0, 180);
		if ($title === '')
			{
			throw new \InvalidArgumentException('Informe o título da oportunidade.');
			}

		$responsibleUserId = (int) ($data['responsible_user_id'] ?? $current['responsible_user_id'] ?? $link['owner_user_id'] ?? $userId);
		$responsibleUserId = $responsibleUserId > 0 ? $responsibleUserId : null;
		$this->assertAssignableUser($responsibleUserId, $tenantId);

		$probabilityInput = $data['probability'] ?? $current['probability'] ?? $stage['default_probability'] ?? 0;
		$probability = $this->normalizeInteger($probabilityInput, 'probabilidade');
		if ($probability < 0 || $probability > 100)
			{
			throw new \InvalidArgumentException('A probabilidade deve estar entre 0 e 100.');
			}

		$estimatedValue = $this->normalizeMoney($data['estimated_value'] ?? $current['estimated_value'] ?? 0);
		if ($estimatedValue < 0)
			{
			throw new \InvalidArgumentException('O valor estimado deve ser maior ou igual a zero.');
			}

		$active = isset($data['active']) ? (int) $data['active'] : (int) ($current['active'] ?? 1);

		return [
			'tenant_person_id' => (int) $link['id'],
			'person_id' => (int) $link['person_id'],
			'crm_source_id' => $sourceId,
			'opportunity_type_id' => $typeId,
			'pipeline_stage_id' => (int) $stage['id'],
			'loss_reason_id' => $lossReasonId,
			'title' => $title,
			'description' => substr(trim((string) ($data['description'] ?? $current['description'] ?? '')), 0, 3000),
			'estimated_value' => number_format($estimatedValue, 2, '.', ''),
			'probability' => $probability,
			'expected_close_date' => $this->normalizeDate($data['expected_close_date'] ?? $current['expected_close_date'] ?? null),
			'closed_at' => $this->closedAtForStage($stage, $data['closed_at'] ?? $current['closed_at'] ?? null),
			'responsible_user_id' => $responsibleUserId,
			'active' => $active === 0 ? 0 : 1,
		];
		}

	private function baseSelectSql(): string
		{
		$tenantPeopleTable = $this->managerTable('tenant_people');
		$peopleTable = $this->managerTable('people');
		$usersTable = $this->managerTable('users');

		return "
			SELECT Opportunity.id,
			       Opportunity.tenant_person_id,
			       Opportunity.person_id,
			       Opportunity.crm_source_id,
			       Opportunity.opportunity_type_id,
			       Opportunity.pipeline_stage_id,
			       Opportunity.loss_reason_id,
			       Opportunity.title,
			       Opportunity.description,
			       Opportunity.estimated_value,
			       Opportunity.probability,
			       Opportunity.expected_close_date,
			       Opportunity.closed_at,
			       Opportunity.responsible_user_id,
			       Opportunity.active,
			       Opportunity.created_by_user_id,
			       Opportunity.updated_by_user_id,
			       Opportunity.created,
			       Opportunity.modified,
			       TenantPerson.tenant_id,
			       TenantPerson.owner_user_id AS crm_owner_user_id,
			       Person.name AS person_name,
			       Person.document AS person_document,
			       Source.name AS crm_source_name,
			       Source.bootstrap_class AS crm_source_bootstrap_class,
			       Type.name AS opportunity_type_name,
			       Type.bootstrap_class AS opportunity_type_bootstrap_class,
			       Stage.name AS pipeline_stage_name,
			       Stage.slug AS pipeline_stage_slug,
			       Stage.bootstrap_class AS pipeline_stage_bootstrap_class,
			       Stage.default_probability AS pipeline_stage_default_probability,
			       Stage.is_won AS pipeline_stage_is_won,
			       Stage.is_lost AS pipeline_stage_is_lost,
			       LossReason.name AS loss_reason_name,
			       LossReason.bootstrap_class AS loss_reason_bootstrap_class,
			       ResponsibleUser.username AS responsible_username,
			       ResponsiblePerson.name AS responsible_name
			FROM crm_opportunities AS Opportunity
			INNER JOIN {$tenantPeopleTable} AS TenantPerson
				ON TenantPerson.id = Opportunity.tenant_person_id
				AND TenantPerson.person_id = Opportunity.person_id
			INNER JOIN {$peopleTable} AS Person ON Person.id = Opportunity.person_id
			LEFT JOIN crm_sources AS Source ON Source.id = Opportunity.crm_source_id
			LEFT JOIN crm_opportunity_types AS Type ON Type.id = Opportunity.opportunity_type_id
			LEFT JOIN crm_pipeline_stages AS Stage ON Stage.id = Opportunity.pipeline_stage_id
			LEFT JOIN crm_loss_reasons AS LossReason ON LossReason.id = Opportunity.loss_reason_id
			LEFT JOIN {$usersTable} AS ResponsibleUser ON ResponsibleUser.id = Opportunity.responsible_user_id
			LEFT JOIN {$peopleTable} AS ResponsiblePerson ON ResponsiblePerson.id = ResponsibleUser.person_id
		";
		}

	private function baseCountSql(): string
		{
		$tenantPeopleTable = $this->managerTable('tenant_people');
		$peopleTable = $this->managerTable('people');

		return "
			SELECT COUNT(*) AS total
			FROM crm_opportunities AS Opportunity
			INNER JOIN {$tenantPeopleTable} AS TenantPerson
				ON TenantPerson.id = Opportunity.tenant_person_id
				AND TenantPerson.person_id = Opportunity.person_id
			INNER JOIN {$peopleTable} AS Person ON Person.id = Opportunity.person_id
			LEFT JOIN crm_pipeline_stages AS Stage ON Stage.id = Opportunity.pipeline_stage_id
		";
		}

	private function appendFilters(string &$sql, array &$params, array $filters): void
		{
		if (isset($filters['id']) && $filters['id'] !== '')
			{
			$sql .= " AND Opportunity.id = :id";
			$params['id'] = (int) $filters['id'];
			}
		if (isset($filters['tenant_person_id']) && $filters['tenant_person_id'] !== '')
			{
			$sql .= " AND Opportunity.tenant_person_id = :tenant_person_id";
			$params['tenant_person_id'] = (int) $filters['tenant_person_id'];
			}
		if (isset($filters['person_name']) && $filters['person_name'] !== '')
			{
			$sql .= " AND Person.name LIKE :person_name";
			$params['person_name'] = '%' . trim((string) $filters['person_name']) . '%';
			}
		foreach (['crm_source_id', 'opportunity_type_id', 'pipeline_stage_id', 'loss_reason_id', 'responsible_user_id'] as $field)
			{
			if (isset($filters[$field]) && $filters[$field] !== '')
				{
				$sql .= " AND Opportunity.{$field} = :{$field}";
				$params[$field] = (int) $filters[$field];
				}
			}
		if (isset($filters['expected_from']) && $filters['expected_from'] !== '')
			{
			$sql .= " AND Opportunity.expected_close_date >= :expected_from";
			$params['expected_from'] = $this->normalizeDate((string) $filters['expected_from']);
			}
		if (isset($filters['expected_to']) && $filters['expected_to'] !== '')
			{
			$sql .= " AND Opportunity.expected_close_date <= :expected_to";
			$params['expected_to'] = $this->normalizeDate((string) $filters['expected_to']);
			}
		if (isset($filters['result']) && $filters['result'] !== '')
			{
			$result = (string) $filters['result'];
			if ($result === 'open')
				{
				$sql .= " AND COALESCE(Stage.is_won, 0) = 0 AND COALESCE(Stage.is_lost, 0) = 0";
				}
			elseif ($result === 'won')
				{
				$sql .= " AND COALESCE(Stage.is_won, 0) = 1";
				}
			elseif ($result === 'lost')
				{
				$sql .= " AND COALESCE(Stage.is_lost, 0) = 1";
				}
			}
		if (isset($filters['active']) && $filters['active'] !== '')
			{
			$active = (int) $filters['active'];
			if (in_array($active, [0, 1], true))
				{
				$sql .= " AND Opportunity.active = :active";
				$params['active'] = $active;
				}
			}
		}

	private function orderBySql(string $sort, string $direction): string
		{
		$columns = [
			'id' => 'Opportunity.id',
			'person_name' => 'Person.name',
			'title' => 'Opportunity.title',
			'estimated_value' => 'Opportunity.estimated_value',
			'probability' => 'Opportunity.probability',
			'expected_close_date' => 'Opportunity.expected_close_date',
			'pipeline_stage' => 'Stage.sort_order',
			'created' => 'Opportunity.created',
			'modified' => 'Opportunity.modified',
		];
		$column = $columns[$sort] ?? $columns['modified'];
		$dir = strtolower($direction) === 'asc' ? 'ASC' : 'DESC';

		return " ORDER BY {$column} {$dir}, Opportunity.id DESC";
		}

	private function crmLinkById(int $crmId, int $tenantId): ?array
		{
		if ($crmId <= 0)
			{
			return null;
			}

		$stmt = $this->db->prepare("
			SELECT id, tenant_id, person_id, owner_user_id, active, crm_source_id
			FROM " . $this->managerTable('tenant_people') . "
			WHERE id = :id
			  AND tenant_id = :tenant_id
			LIMIT 1
		");
		$stmt->execute([
			'id' => $crmId,
			'tenant_id' => $tenantId,
		]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);

		return $row ?: null;
		}

	private function nullableCatalogId(array $data, string $field, ?array $current, string $currentField, string $catalogKey): ?int
		{
		$value = $data[$field] ?? $current[$currentField] ?? null;
		$id = (int) ($value ?? 0);
		if ($id <= 0)
			{
			return null;
			}

		$currentId = isset($current[$currentField]) ? (int) $current[$currentField] : null;
		if (!(new CrmCatalog($catalogKey))->isAssignable($id, $currentId))
			{
			throw new \InvalidArgumentException('Selecione um cadastro CRM válido.');
			}

		return $id;
		}

	private function assertStage(int $stageId, ?int $currentStageId = null): array
		{
		$row = (new CrmCatalog('pipeline_stage'))->find($stageId);
		if (!$row || ((int) ($row['active'] ?? 0) !== 1 && $stageId !== (int) ($currentStageId ?? 0)))
			{
			throw new \InvalidArgumentException('Selecione uma etapa do funil válida.');
			}

		return $row;
		}

	private function defaultStageId(): int
		{
		$id = (new CrmCatalog('pipeline_stage'))->defaultId('novo');
		if (!$id)
			{
			throw new \InvalidArgumentException('Cadastre ao menos uma etapa ativa no funil CRM.');
			}

		return $id;
		}

	private function defaultLossReasonId(): int
		{
		$id = (new CrmCatalog('loss_reason'))->defaultId('sem_retorno');
		if (!$id)
			{
			throw new \InvalidArgumentException('Cadastre ao menos um motivo de perda ativo.');
			}

		return $id;
		}

	private function assertAssignableUser(?int $userId, int $tenantId): void
		{
		if ($userId === null)
			{
			return;
			}

		$stmt = $this->db->prepare("
			SELECT User.id
			FROM " . $this->managerTable('users') . " AS User
			INNER JOIN " . $this->managerTable('tenant_users') . " AS TenantUser
				ON TenantUser.user_id = User.id
				AND TenantUser.tenant_id = :tenant_id
			WHERE User.id = :user_id
			  AND User.active = 1
			  AND User.type IN ('admin', 'user')
			LIMIT 1
		");
		$stmt->execute([
			'tenant_id' => $tenantId,
			'user_id' => $userId,
		]);

		if (!$stmt->fetchColumn())
			{
			throw new \InvalidArgumentException('Selecione um responsável válido para este tenant.');
			}
		}

	private function closedAtForStage(array $stage, $value): ?string
		{
		$isFinal = (int) ($stage['is_won'] ?? 0) === 1 || (int) ($stage['is_lost'] ?? 0) === 1;
		if (!$isFinal)
			{
			return null;
			}

		return $this->normalizeDateTime($value) ?? date('Y-m-d H:i:s');
		}

	private function normalizeDate($value): ?string
		{
		$value = trim((string) ($value ?? ''));
		if ($value === '' || $value === '0000-00-00')
			{
			return null;
			}

		$formats = ['!Y-m-d', '!d/m/Y'];
		foreach ($formats as $format)
			{
			$date = \DateTimeImmutable::createFromFormat($format, $value);
			$errors = \DateTimeImmutable::getLastErrors();
			$hasErrors = is_array($errors)
				&& (((int) ($errors['warning_count'] ?? 0) > 0) || ((int) ($errors['error_count'] ?? 0) > 0));
			if ($date !== false && !$hasErrors)
				{
				return $date->format('Y-m-d');
				}
			}

		throw new \InvalidArgumentException('Informe uma data válida.');
		}

	private function normalizeDateTime($value): ?string
		{
		$value = trim((string) ($value ?? ''));
		if ($value === '' || $value === '0000-00-00 00:00:00')
			{
			return null;
			}

		$formats = ['!Y-m-d\TH:i', '!Y-m-d H:i:s', '!Y-m-d H:i', '!d/m/Y H:i'];
		foreach ($formats as $format)
			{
			$date = \DateTimeImmutable::createFromFormat($format, $value);
			$errors = \DateTimeImmutable::getLastErrors();
			$hasErrors = is_array($errors)
				&& (((int) ($errors['warning_count'] ?? 0) > 0) || ((int) ($errors['error_count'] ?? 0) > 0));
			if ($date !== false && !$hasErrors)
				{
				return $date->format('Y-m-d H:i:s');
				}
			}

		throw new \InvalidArgumentException('Informe uma data e hora válidas.');
		}

	private function normalizeMoney($value): float
		{
		$value = trim((string) ($value ?? '0'));
		if ($value === '')
			{
			return 0.0;
			}
		$value = str_replace(['R$', ' '], '', $value);
		if (str_contains($value, ','))
			{
			$value = str_replace('.', '', $value);
			$value = str_replace(',', '.', $value);
			}
		if (!is_numeric($value))
			{
			throw new \InvalidArgumentException('Informe um valor monetário válido.');
			}

		return (float) $value;
		}

	private function normalizeInteger($value, string $label): int
		{
		if (is_array($value) || is_object($value))
			{
			throw new \InvalidArgumentException('Formato inválido para ' . $label . '.');
			}
		$validated = filter_var(trim((string) $value), FILTER_VALIDATE_INT);
		if ($validated === false)
			{
			throw new \InvalidArgumentException('Informe um valor inteiro válido para ' . $label . '.');
			}

		return (int) $validated;
		}

	private function managerTable(string $table): string
		{
		return sprintf('`%s`.`%s`', Database::getManagerDatabaseName(), trim($table));
		}

	private function bindParams(\PDOStatement $stmt, array $params): void
		{
		foreach ($params as $key => $value)
			{
			$stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
			}
		}
	}
?>
