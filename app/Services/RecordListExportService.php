<?php

namespace App\Services;

use App\Core\TenantContext;
use App\Models\Address;
use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\BankFile;
use App\Models\BankSlip;
use App\Models\City;
use App\Models\Country;
use App\Models\Email;
use App\Models\FinancialOperation;
use App\Models\FinancialOperationCategory;
use App\Models\FinancialOperationPayment;
use App\Models\FinancialOperationSubcategory;
use App\Models\Neighborhood;
use App\Models\Permission;
use App\Models\Person;
use App\Models\PhoneNumber;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductStockMovement;
use App\Models\State;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\User;

/**
 * Provides shared definitions, filters, and row formatting for list exports.
 */
class RecordListExportService
	{
	/**
	 * Checks whether a resource has a configured list export.
	 *
	 * @param string $resource Resource key derived from the controller route.
	 * @return bool
	 */
	public function supports(string $resource): bool
		{
		return isset($this->definitions()[$resource]);
		}

	/**
	 * Returns whether a resource needs an active tenant context before exporting.
	 *
	 * @param string $resource Resource key.
	 * @return bool
	 */
	public function needsTenant(string $resource): bool
		{
		$definition = $this->definitions()[$resource] ?? [];
		return !empty($definition['needsTenant']);
		}

	/**
	 * Returns the permission ability required for a resource export.
	 *
	 * @param string $resource Resource key.
	 * @return string
	 */
	public function ability(string $resource): string
		{
		$definition = $this->definitions()[$resource] ?? [];
		return (string) ($definition['ability'] ?? ($resource . '.index'));
		}

	/**
	 * Returns the columns available for the resource export picker.
	 *
	 * @param string $resource Resource key.
	 * @return array<int, array{key: string, label: string}>
	 */
	public function exportColumns(string $resource): array
		{
		$definition = $this->definition($resource);
		$columns = [];

		foreach (($definition['columns'] ?? []) as $index => $column)
			{
			$columns[] = [
				'key' => (string) $index,
				'label' => (string) ($column['label'] ?? ('Coluna ' . ((int) $index + 1))),
			];
			}

		return $columns;
		}

	/**
	 * Builds the shared export payload used by concrete export formats.
	 *
	 * @param string $resource Resource key derived from the controller route.
	 * @param array<string, mixed> $query Query string values.
	 * @return array{definition: array<string, mixed>, filters: array<string, mixed>, total: int, rows: array<int, array<string, mixed>>}
	 */
	protected function buildExportPayload(string $resource, array $query = []): array
		{
		$definition = $this->definition($resource);
		$definition['columns'] = $this->selectedColumns($definition['columns'] ?? [], $query);
		$filters = $this->extractFilters($query, $definition['filters'] ?? []);
		if (!empty($definition['normalizeFilters']) && is_callable($definition['normalizeFilters']))
			{
			$filters = call_user_func($definition['normalizeFilters'], $filters, $query);
			}

		if (!empty($definition['rowsCallback']) && is_callable($definition['rowsCallback']))
			{
			$rows = call_user_func($definition['rowsCallback'], $filters, $query);
			return [
				'definition' => $definition,
				'filters' => $filters,
				'total' => count($rows),
				'rows' => $rows,
			];
			}

		$modelClass = $definition['model'];
		$model = new $modelClass();
		$countMethod = $definition['countMethod'] ?? 'count';
		$searchMethod = $definition['searchMethod'] ?? 'search';

		if (!method_exists($model, $countMethod) || !method_exists($model, $searchMethod))
			{
			throw new \RuntimeException('Export model contract is invalid.');
			}

		$total = (int) $model->{$countMethod}(...$this->countArguments($definition, $filters));
		$rows = [];
		if ($total > 0)
			{
			$rows = $model->{$searchMethod}(...$this->searchArguments($definition, $filters, $query, $total));
			}

		return [
			'definition' => $definition,
			'filters' => $filters,
			'total' => $total,
			'rows' => is_array($rows) ? $rows : [],
		];
		}

	/**
	 * Returns all supported resource definitions.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function definitions(): array
		{
		return [
			'address' => $this->standardDefinition(Address::class, 'Lista de endereços', 'enderecos', ['id', 'street', 'zip_code', 'person_id'], [
				['label' => '#', 'field' => 'id', 'align' => 'right'],
				['label' => 'CEP', 'field' => 'zip_code'],
				['label' => 'Logradouro', 'field' => 'street'],
				['label' => 'Número', 'field' => 'number'],
				['label' => 'Bairro', 'field' => 'neighborhood_name'],
				['label' => 'Cidade', 'field' => 'neighborhood_city_name'],
				['label' => 'Pessoa', 'field' => 'person_name'],
				['label' => 'Observações', 'field' => 'observations'],
			], ['needsTenant' => true, 'tenantIdArgument' => true]),
			'bank_account' => $this->standardDefinition(BankAccount::class, 'Lista de contas bancárias', 'contas_bancarias', ['id', 'bank_id', 'name', 'agency', 'account', 'active'], [
				['label' => '#', 'field' => 'id', 'align' => 'right'],
				['label' => 'Banco', 'callback' => fn (array $row): string => $this->bankLabel($row)],
				['label' => 'Nome', 'field' => 'name'],
				['label' => 'Agência', 'field' => 'agency'],
				['label' => 'Conta', 'field' => 'account'],
				['label' => 'Tipo PIX', 'field' => 'pix_key_type'],
				['label' => 'Chave PIX', 'field' => 'pix_key'],
				['label' => 'Status', 'callback' => fn (array $row): string => $this->activeLabel($row['active'] ?? null)],
			], ['needsTenant' => true]),
			'bank_file' => $this->standardDefinition(BankFile::class, 'Lista de remessas', 'remessas', ['id', 'file_type', 'file_name', 'bank_account_id'], [
				['label' => '#', 'field' => 'id', 'align' => 'right'],
				['label' => 'Tipo', 'field' => 'file_type'],
				['label' => 'Arquivo', 'field' => 'file_name'],
				['label' => 'Conta bancária', 'field' => 'bank_account_name'],
				['label' => 'Banco', 'callback' => fn (array $row): string => $this->bankLabel($row)],
				['label' => 'Boletos', 'field' => 'bank_slips_count', 'align' => 'right'],
				['label' => 'Criado em', 'callback' => fn (array $row): string => $this->dateTime($row['created'] ?? '')],
			], ['needsTenant' => true]),
			'bank_slip' => $this->standardDefinition(BankSlip::class, 'Lista de boletos bancários', 'boletos_bancarios', ['id', 'bank_account_id', 'bank_account_ids', 'status', 'document_number'], [
				['label' => '#', 'field' => 'id', 'align' => 'right'],
				['label' => 'Documento', 'field' => 'document_number'],
				['label' => 'Banco/conta', 'callback' => fn (array $row): string => trim($this->bankLabel($row) . ' - ' . (string) ($row['bank_account_name'] ?? ''), ' -')],
				['label' => 'Sacado', 'field' => 'payer_name'],
				['label' => 'Vencimento', 'callback' => fn (array $row): string => $this->date($row['due_date'] ?? '')],
				['label' => 'Valor', 'callback' => fn (array $row): string => $this->money($row['amount'] ?? 0), 'align' => 'right'],
				['label' => 'Status', 'field' => 'status'],
				['label' => 'Remessa', 'field' => 'bank_file_name'],
			], ['needsTenant' => true, 'normalizeFilters' => fn (array $filters, array $query): array => $this->normalizeBankSlipFilters($filters, $query)]),
			'bank' => $this->standardDefinition(Bank::class, 'Lista de bancos', 'bancos', ['id', 'name', 'number'], [
				['label' => '#', 'field' => 'id', 'align' => 'right'],
				['label' => 'Número', 'field' => 'number'],
				['label' => 'Nome', 'field' => 'name'],
				['label' => 'CNAB', 'field' => 'cnab'],
				['label' => 'Status', 'callback' => fn (array $row): string => $this->activeLabel($row['active'] ?? null)],
			]),
			'city' => $this->standardDefinition(City::class, 'Lista de cidades', 'cidades', ['id', 'name', 'country_id', 'state_id'], [
				['label' => '#', 'field' => 'id', 'align' => 'right'],
				['label' => 'Nome', 'field' => 'name'],
				['label' => 'Estado', 'field' => 'state_name'],
				['label' => 'UF', 'field' => 'state_acronym'],
				['label' => 'País', 'field' => 'country_name'],
			]),
			'country' => $this->standardDefinition(Country::class, 'Lista de países', 'paises', ['id', 'name', 'acronym'], [
				['label' => '#', 'field' => 'id', 'align' => 'right'],
				['label' => 'Nome', 'field' => 'name'],
				['label' => 'Sigla', 'field' => 'acronym'],
			]),
			'email' => $this->standardDefinition(Email::class, 'Lista de e-mails', 'emails', ['id', 'email', 'person_id', 'person_name'], [
				['label' => '#', 'field' => 'id', 'align' => 'right'],
				['label' => 'E-mail', 'field' => 'email'],
				['label' => 'Pessoa', 'field' => 'person_name'],
				['label' => 'Login', 'callback' => fn (array $row): string => $this->yesNo($row['is_login'] ?? null)],
				['label' => 'Observações', 'field' => 'observations'],
			], ['needsTenant' => true, 'tenantIdArgument' => true]),
			'financial_operation' => $this->standardDefinition(FinancialOperation::class, 'Lista de operações financeiras', 'operacoes_financeiras', ['id', 'financial_operation_category_id', 'total_amount'], [
				['label' => '#', 'field' => 'id', 'align' => 'right'],
				['label' => 'Categoria', 'field' => 'financial_operation_category_name'],
				['label' => 'Valor total', 'callback' => fn (array $row): string => $this->money($row['total_amount'] ?? 0), 'align' => 'right'],
				['label' => 'Criado em', 'callback' => fn (array $row): string => $this->dateTime($row['created'] ?? '')],
			], ['needsTenant' => true]),
			'financial_operation_category' => $this->standardDefinition(FinancialOperationCategory::class, 'Lista de categorias financeiras', 'categorias_financeiras', ['id', 'name'], [
				['label' => '#', 'field' => 'id', 'align' => 'right'],
				['label' => 'Nome', 'field' => 'name'],
				['label' => 'Criado em', 'callback' => fn (array $row): string => $this->dateTime($row['created'] ?? '')],
			], ['needsTenant' => true]),
			'financial_operation_payment' => $this->standardDefinition(FinancialOperationPayment::class, 'Lista de pagamentos financeiros', 'pagamentos_financeiros', ['id', 'financial_operation_id', 'value'], [
				['label' => '#', 'field' => 'id', 'align' => 'right'],
				['label' => 'Operação', 'callback' => fn (array $row): string => $this->operationLabel($row)],
				['label' => 'Valor', 'callback' => fn (array $row): string => $this->money($row['value'] ?? 0), 'align' => 'right'],
				['label' => 'Criado em', 'callback' => fn (array $row): string => $this->dateTime($row['created'] ?? '')],
			], ['needsTenant' => true]),
			'financial_operation_subcategory' => $this->standardDefinition(FinancialOperationSubcategory::class, 'Lista de subcategorias financeiras', 'subcategorias_financeiras', ['id', 'financial_operation_category_id', 'name'], [
				['label' => '#', 'field' => 'id', 'align' => 'right'],
				['label' => 'Categoria', 'field' => 'financial_operation_category_name'],
				['label' => 'Nome', 'field' => 'name'],
				['label' => 'Criado em', 'callback' => fn (array $row): string => $this->dateTime($row['created'] ?? '')],
			], ['needsTenant' => true]),
			'neighborhood' => $this->standardDefinition(Neighborhood::class, 'Lista de bairros', 'bairros', ['id', 'name', 'city_id'], [
				['label' => '#', 'field' => 'id', 'align' => 'right'],
				['label' => 'Nome', 'field' => 'name'],
				['label' => 'Cidade', 'field' => 'city_name'],
			]),
			'permission' => [
				'title' => 'Lista de permissões',
				'filename' => 'permissoes',
				'filters' => ['user_id'],
				'ability' => 'permission.manage',
				'needsTenant' => true,
				'rowsCallback' => fn (array $filters, array $query): array => $this->permissionRows($filters),
				'columns' => [
					['label' => '#', 'field' => 'id', 'align' => 'right'],
					['label' => 'Grupo', 'field' => 'group'],
					['label' => 'Ability', 'field' => 'ability'],
					['label' => 'Descrição', 'field' => 'description'],
					['label' => 'Selecionada', 'field' => 'assigned'],
				],
			],
			'permission_orphaned' => [
				'title' => 'Lista de permissões órfãs',
				'filename' => 'permissoes_orfas',
				'filters' => [],
				'ability' => 'permission.manage',
				'needsTenant' => true,
				'rowsCallback' => fn (array $filters, array $query): array => $this->permissionOrphanedRows(),
				'columns' => [
					['label' => '#', 'field' => 'id', 'align' => 'right'],
					['label' => 'Ability', 'field' => 'ability'],
					['label' => 'Descrição', 'field' => 'description'],
					['label' => 'Usuários afetados', 'field' => 'user_count', 'align' => 'right'],
				],
			],
			'person' => $this->standardDefinition(Person::class, 'Lista de pessoas', 'pessoas', ['id', 'name', 'type', 'document', 'birthdate', 'active'], [
				['label' => '#', 'field' => 'id', 'align' => 'right'],
				['label' => 'Nome', 'field' => 'name'],
				['label' => 'Fantasia', 'field' => 'fantasy'],
				['label' => 'Documento', 'callback' => fn (array $row): string => \maskCpfCnpj((string) ($row['document'] ?? ''))],
				['label' => 'Nascimento', 'callback' => fn (array $row): string => $this->date($row['birthdate'] ?? '')],
				['label' => 'Tipo', 'callback' => fn (array $row): string => $this->personTypeLabel($row['type'] ?? '')],
				['label' => 'Status', 'callback' => fn (array $row): string => $this->activeLabel($row['active'] ?? null)],
			], ['needsTenant' => true, 'tenantIdArgument' => true]),
			'phone_number' => $this->standardDefinition(PhoneNumber::class, 'Lista de telefones', 'telefones', ['id', 'phone_number', 'person_id'], [
				['label' => '#', 'field' => 'id', 'align' => 'right'],
				['label' => 'Telefone', 'callback' => fn (array $row): string => \maskPhoneBr((string) ($row['phone_number'] ?? ''))],
				['label' => 'Pessoa', 'field' => 'person_name'],
				['label' => 'Observações', 'field' => 'observations'],
			], ['needsTenant' => true, 'tenantIdArgument' => true]),
			'product' => $this->standardDefinition(Product::class, 'Lista de produtos', 'produtos', ['id', 'product_category_id', 'sku', 'name', 'active', 'stock_status'], [
				['label' => '#', 'field' => 'id', 'align' => 'right'],
				['label' => 'SKU', 'field' => 'sku'],
				['label' => 'Nome', 'field' => 'name'],
				['label' => 'Categoria', 'field' => 'product_category_name'],
				['label' => 'Estoque', 'callback' => fn (array $row): string => $this->quantity($row['stock_quantity'] ?? 0), 'align' => 'right'],
				['label' => 'Mínimo', 'callback' => fn (array $row): string => $this->quantity($row['minimum_stock'] ?? 0), 'align' => 'right'],
				['label' => 'Preço de custo', 'callback' => fn (array $row): string => $this->money($row['cost_price'] ?? 0), 'align' => 'right'],
				['label' => 'Preço de venda', 'callback' => fn (array $row): string => $this->money($row['sale_price'] ?? 0), 'align' => 'right'],
				['label' => 'Status', 'callback' => fn (array $row): string => $this->activeLabel($row['active'] ?? null)],
			], ['needsTenant' => true]),
			'product_category' => $this->standardDefinition(ProductCategory::class, 'Lista de categorias de produtos', 'categorias_produtos', ['id', 'name', 'active'], [
				['label' => '#', 'field' => 'id', 'align' => 'right'],
				['label' => 'Nome', 'field' => 'name'],
				['label' => 'Descrição', 'field' => 'description'],
				['label' => 'Status', 'callback' => fn (array $row): string => $this->activeLabel($row['active'] ?? null)],
			], ['needsTenant' => true]),
			'product_stock_movement' => $this->standardDefinition(ProductStockMovement::class, 'Lista de movimentações de estoque', 'movimentacoes_estoque', ['id', 'product_id', 'movement_type', 'reference'], [
				['label' => '#', 'field' => 'id', 'align' => 'right'],
				['label' => 'Produto', 'callback' => fn (array $row): string => $this->productLabel($row)],
				['label' => 'Tipo', 'callback' => fn (array $row): string => $this->movementTypeLabel($row['movement_type'] ?? '')],
				['label' => 'Quantidade', 'callback' => fn (array $row): string => $this->quantity($row['quantity'] ?? 0), 'align' => 'right'],
				['label' => 'Saldo anterior', 'callback' => fn (array $row): string => $this->quantity($row['previous_quantity'] ?? 0), 'align' => 'right'],
				['label' => 'Novo saldo', 'callback' => fn (array $row): string => $this->quantity($row['new_quantity'] ?? 0), 'align' => 'right'],
				['label' => 'Referência', 'field' => 'reference'],
				['label' => 'Data', 'callback' => fn (array $row): string => $this->dateTime($row['movement_date'] ?? '')],
			], ['needsTenant' => true]),
			'state' => $this->standardDefinition(State::class, 'Lista de estados', 'estados', ['id', 'name', 'acronym'], [
				['label' => '#', 'field' => 'id', 'align' => 'right'],
				['label' => 'Nome', 'field' => 'name'],
				['label' => 'Sigla', 'field' => 'acronym'],
				['label' => 'País', 'field' => 'country_name'],
			]),
			'task' => $this->standardDefinition(Task::class, 'Lista de tarefas', 'tarefas', ['id', 'task_category_id', 'status', 'assignee_person_id', 'title', 'description', 'third_party_name', 'created_from', 'created_to'], [
				['label' => '#', 'field' => 'id', 'align' => 'right'],
				['label' => 'Tarefa', 'field' => 'title'],
				['label' => 'Categoria', 'field' => 'task_category_name'],
				['label' => 'Situação', 'callback' => fn (array $row): string => Task::statusLabel((string) ($row['status'] ?? ''))],
				['label' => 'Responsáveis', 'field' => 'assignee_names'],
				['label' => 'Terceiro', 'callback' => fn (array $row): string => $this->thirdPartyLabel($row)],
				['label' => 'Tempo', 'callback' => fn (array $row): string => Task::formatDuration((int) ($row['total_seconds'] ?? 0))],
				['label' => 'Criada em', 'callback' => fn (array $row): string => Task::formatDateTime($row['created'] ?? '')],
			], [
				'needsTenant' => true,
				'searchArguments' => fn (array $filters, array $query, int $total): array => [$filters, max(1, $total), 0, $this->taskSort($query), $this->taskDirection($query)],
			]),
			'task_category' => $this->standardDefinition(TaskCategory::class, 'Lista de categorias de tarefas', 'categorias_tarefas', ['id', 'name', 'active'], [
				['label' => '#', 'field' => 'id', 'align' => 'right'],
				['label' => 'Nome', 'field' => 'name'],
				['label' => 'Descrição', 'field' => 'description'],
				['label' => 'Status', 'callback' => fn (array $row): string => $this->activeLabel($row['active'] ?? null)],
			], ['needsTenant' => true]),
			'user' => $this->standardDefinition(User::class, 'Lista de usuários', 'usuarios', ['id', 'username', 'type', 'active'], [
				['label' => '#', 'field' => 'id', 'align' => 'right'],
				['label' => 'Usuário', 'field' => 'username'],
				['label' => 'Tipo', 'callback' => fn (array $row): string => $this->userTypeLabel($row['type'] ?? '')],
				['label' => 'Status', 'callback' => fn (array $row): string => $this->activeLabel($row['active'] ?? null)],
			], ['needsTenant' => true, 'tenantIdArgument' => true]),
		];
		}

	/**
	 * Builds a standard model-backed definition.
	 *
	 * @param string $modelClass Model class name.
	 * @param string $title Export title.
	 * @param string $filename Filename base.
	 * @param array<int, string> $filters Accepted filter keys.
	 * @param array<int, array<string, mixed>> $columns Export columns.
	 * @param array<string, mixed> $extra Extra definition metadata.
	 * @return array<string, mixed>
	 */
	private function standardDefinition(
		string $modelClass,
		string $title,
		string $filename,
		array $filters,
		array $columns,
		array $extra = []
	): array
		{
		return array_merge([
			'model' => $modelClass,
			'title' => $title,
			'filename' => $filename,
			'filters' => $filters,
			'columns' => $columns,
		], $extra);
		}

	/**
	 * Returns one resource definition.
	 *
	 * @param string $resource Resource key.
	 * @return array<string, mixed>
	 */
	private function definition(string $resource): array
		{
		$definitions = $this->definitions();
		if (!isset($definitions[$resource]))
			{
			throw new \InvalidArgumentException('List export is not configured for this resource.');
			}

		return $definitions[$resource];
		}

	/**
	 * Extracts only configured filter keys from the request query.
	 *
	 * @param array<string, mixed> $query Request query.
	 * @param array<int, string> $keys Accepted filter keys.
	 * @return array<string, mixed>
	 */
	private function extractFilters(array $query, array $keys): array
		{
		$filters = [];
		foreach ($keys as $key)
			{
			$value = $query[$key] ?? '';
			if (is_array($value))
				{
				$filters[$key] = array_values(array_filter(array_map(static fn ($item): string => trim((string) $item), $value), static fn (string $item): bool => $item !== ''));
				continue;
				}

			$filters[$key] = trim((string) $value);
			}

		return $filters;
		}

	/**
	 * Builds count method arguments for a definition.
	 *
	 * @param array<string, mixed> $definition Export definition.
	 * @param array<string, mixed> $filters Active filters.
	 * @return array<int, mixed>
	 */
	private function countArguments(array $definition, array $filters): array
		{
		$args = [$filters];
		if (!empty($definition['tenantIdArgument']))
			{
			$args[] = $this->currentTenantId();
			}

		return $args;
		}

	/**
	 * Builds search method arguments for a definition.
	 *
	 * @param array<string, mixed> $definition Export definition.
	 * @param array<string, mixed> $filters Active filters.
	 * @param array<string, mixed> $query Request query.
	 * @param int $total Total records.
	 * @return array<int, mixed>
	 */
	private function searchArguments(array $definition, array $filters, array $query, int $total): array
		{
		if (!empty($definition['searchArguments']) && is_callable($definition['searchArguments']))
			{
			return call_user_func($definition['searchArguments'], $filters, $query, $total);
			}

		$args = [$filters, max(1, $total), 0];
		if (!empty($definition['tenantIdArgument']))
			{
			$args[] = $this->currentTenantId();
			}

		return $args;
		}

	/**
	 * Keeps only columns requested by the export UI, preserving definition order.
	 *
	 * @param array<int, array<string, mixed>> $columns Configured export columns.
	 * @param array<string, mixed> $query Request query.
	 * @return array<int, array<string, mixed>>
	 */
	private function selectedColumns(array $columns, array $query): array
		{
		if (!array_key_exists('export_columns', $query))
			{
			return $columns;
			}

		$selected = $query['export_columns'];
		if (!is_array($selected))
			{
			$selected = [$selected];
			}

		$selected = array_values(array_unique(array_filter(
			array_map(static fn ($key): string => trim((string) $key), $selected),
			static fn (string $key): bool => $key !== ''
		)));

		if (empty($selected))
			{
			return $columns;
			}

		$selectedLookup = array_fill_keys($selected, true);
		$filteredColumns = [];
		foreach ($columns as $index => $column)
			{
			if (isset($selectedLookup[(string) $index]))
				{
				$filteredColumns[] = $column;
				}
			}

		return !empty($filteredColumns) ? $filteredColumns : $columns;
		}

	/**
	 * Resolves a formatted column value for one row.
	 *
	 * @param array<string, mixed> $column Column definition.
	 * @param array<string, mixed> $row Row data.
	 * @return string
	 */
	protected function columnValue(array $column, array $row): string
		{
		if (!empty($column['callback']) && is_callable($column['callback']))
			{
			return $this->stringValue(call_user_func($column['callback'], $row));
			}

		$field = (string) ($column['field'] ?? '');
		return $this->stringValue($field !== '' ? ($row[$field] ?? '') : '');
		}

	/**
	 * Builds a safe timestamped filename.
	 *
	 * @param string $base Filename base.
	 * @param string $extension Filename extension.
	 * @return string
	 */
	protected function filename(string $base, string $extension = 'xlsx'): string
		{
		$base = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $base) ?: 'registros';
		$extension = preg_replace('/[^a-zA-Z0-9]+/', '', $extension) ?: 'xlsx';
		return strtolower(trim($base, '_')) . '_' . date('Ymd_His') . '.' . strtolower($extension);
		}

	/**
	 * Converts technical filter keys to readable labels.
	 */
	protected function humanLabel(string $key): string
		{
		$labels = [
			'id' => 'Código',
			'name' => 'Nome',
			'number' => 'Número',
			'acronym' => 'Sigla',
			'active' => 'Status',
			'status' => 'Situação',
			'type' => 'Tipo',
			'document' => 'Documento',
			'birthdate' => 'Data de nascimento',
			'zip_code' => 'CEP',
			'street' => 'Logradouro',
			'person_id' => 'Pessoa',
			'person_name' => 'Pessoa',
			'country_id' => 'País',
			'state_id' => 'Estado',
			'city_id' => 'Cidade',
			'username' => 'Usuário',
			'email' => 'E-mail',
			'bank_id' => 'Banco',
			'bank_account_id' => 'Conta bancária',
			'bank_account_ids' => 'Contas bancárias',
			'file_type' => 'Tipo de arquivo',
			'file_name' => 'Arquivo',
			'document_number' => 'Documento',
			'financial_operation_category_id' => 'Categoria',
			'financial_operation_id' => 'Operação',
			'total_amount' => 'Valor total',
			'value' => 'Valor',
			'product_category_id' => 'Categoria',
			'product_id' => 'Produto',
			'movement_type' => 'Tipo de movimentação',
			'reference' => 'Referência',
			'stock_status' => 'Estoque',
			'task_category_id' => 'Categoria',
			'assignee_person_id' => 'Responsável',
			'title' => 'Título',
			'description' => 'Descrição',
			'third_party_name' => 'Terceiro',
			'created_from' => 'Criada de',
			'created_to' => 'Criada até',
			'user_id' => 'Usuário',
		];

		return $labels[$key] ?? ucwords(str_replace('_', ' ', $key));
		}

	/**
	 * Formats any scalar value as a string.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	protected function stringValue($value): string
		{
		if ($value === null)
			{
			return '';
			}

		if (is_bool($value))
			{
			return $value ? 'Sim' : 'Não';
			}

		if (is_array($value))
			{
			return implode(', ', array_map([$this, 'stringValue'], $value));
			}

		return (string) $value;
		}

	private function currentTenantId(): int
		{
		$tenant = TenantContext::tenant();
		return (int) ($tenant['id'] ?? 0);
		}

	private function normalizeBankSlipFilters(array $filters, array $query): array
		{
		$ids = $this->integerList($filters['bank_account_ids'] ?? []);
		if ($ids === [] && ($filters['bank_account_id'] ?? '') !== '')
			{
			$ids = $this->integerList([$filters['bank_account_id']]);
			}

		if (count($ids) > 1)
			{
			$filters['bank_account_ids'] = $ids;
			$filters['bank_account_id'] = '';
			return $filters;
			}

		$filters['bank_account_ids'] = [];
		$filters['bank_account_id'] = $ids[0] ?? ($filters['bank_account_id'] ?? '');

		return $filters;
		}

	private function integerList($values): array
		{
		if (!is_array($values))
			{
			$values = [$values];
			}

		$ids = [];
		foreach ($values as $value)
			{
			$id = (int) $value;
			if ($id > 0)
				{
				$ids[$id] = $id;
				}
			}

		return array_values($ids);
		}

	private function permissionRows(array $filters): array
		{
		$model = new Permission();
		$permissions = $model->getAll();
		$selectedUserId = (int) ($filters['user_id'] ?? 0);
		$tenantId = $this->currentTenantId();
		$assignedIds = [];

		if ($selectedUserId > 0 && $model->userBelongsToTenant($selectedUserId, $tenantId))
			{
			$assignedIds = $model->getPermissionIdsByUser($selectedUserId);
			}

		$assignedLookup = array_fill_keys(array_map('intval', $assignedIds), true);
		$rows = [];
		foreach ($permissions as $permission)
			{
			$ability = (string) ($permission['ability'] ?? '');
			$rows[] = [
				'id' => (int) ($permission['id'] ?? 0),
				'group' => $this->permissionGroup($ability),
				'ability' => $ability,
				'description' => (string) ($permission['description'] ?? ''),
				'assigned' => isset($assignedLookup[(int) ($permission['id'] ?? 0)]) ? 'Sim' : 'Não',
			];
			}

		return $rows;
		}

	private function permissionOrphanedRows(): array
		{
		$model = new Permission();
		$orphaned = $model->getOrphanedPermissions();
		$stats = $model->getOrphanedPermissionStats();
		$permissionStats = $stats['permissions'] ?? [];
		$rows = [];

		foreach ($orphaned as $permission)
			{
			$permissionId = (int) ($permission['id'] ?? 0);
			$rows[] = [
				'id' => $permissionId,
				'ability' => (string) ($permission['ability'] ?? ''),
				'description' => (string) ($permission['description'] ?? ''),
				'user_count' => (int) ($permissionStats[$permissionId]['user_count'] ?? 0),
			];
			}

		return $rows;
		}

	private function permissionGroup(string $ability): string
		{
		$position = strpos($ability, '.');
		return $position === false ? 'outros' : substr($ability, 0, $position);
		}

	private function taskSort(array $query): string
		{
		$sort = trim((string) ($query['sort'] ?? 'created'));
		return $sort !== '' ? $sort : 'created';
		}

	private function taskDirection(array $query): string
		{
		return strtolower(trim((string) ($query['direction'] ?? 'desc'))) === 'asc' ? 'asc' : 'desc';
		}

	private function activeLabel($value): string
		{
		return ((string) $value === '1') ? 'Ativo' : 'Inativo';
		}

	private function yesNo($value): string
		{
		return ((string) $value === '1') ? 'Sim' : 'Não';
		}

	private function userTypeLabel($value): string
		{
		return [
			'root' => 'ROOT',
			'admin' => 'ADMIN',
			'user' => 'USUÁRIO(A)',
		][(string) $value] ?? (string) $value;
		}

	private function personTypeLabel($value): string
		{
		return [
			'individual' => 'Pessoa física',
			'company' => 'Pessoa jurídica',
		][(string) $value] ?? (string) $value;
		}

	private function movementTypeLabel($value): string
		{
		return [
			'IN' => 'Entrada',
			'OUT' => 'Saída',
			'ADJUSTMENT' => 'Ajuste',
		][(string) $value] ?? (string) $value;
		}

	private function money($value): string
		{
		return 'R$ ' . number_format((float) $value, 2, ',', '.');
		}

	private function quantity($value): string
		{
		return number_format((float) $value, 3, ',', '.');
		}

	private function date($value): string
		{
		return \maskDateBr($this->stringValue($value)) ?? '';
		}

	private function dateTime($value): string
		{
		$value = trim($this->stringValue($value));
		if ($value === '' || $value === '0000-00-00 00:00:00')
			{
			return '';
			}

		try
			{
			return (new \DateTimeImmutable($value))->format('d/m/Y H:i');
			}
		catch (\Throwable $e)
			{
			return $value;
			}
		}

	private function bankLabel(array $row): string
		{
		$number = trim((string) ($row['bank_number'] ?? $row['number'] ?? ''));
		$name = trim((string) ($row['bank_name'] ?? $row['name'] ?? ''));

		return trim(($number !== '' ? $number . ' - ' : '') . $name);
		}

	private function operationLabel(array $row): string
		{
		$label = '#' . (int) ($row['financial_operation_id'] ?? 0);
		if (!empty($row['financial_operation_category_name']))
			{
			$label .= ' - ' . (string) $row['financial_operation_category_name'];
			}
		if (isset($row['financial_operation_total_amount']))
			{
			$label .= ' - ' . $this->money($row['financial_operation_total_amount']);
			}

		return $label;
		}

	private function productLabel(array $row): string
		{
		$sku = trim((string) ($row['product_sku'] ?? $row['sku'] ?? ''));
		$name = trim((string) ($row['product_name'] ?? $row['name'] ?? ''));

		return ($sku !== '' ? $sku . ' - ' : '') . $name;
		}

	private function thirdPartyLabel(array $row): string
		{
		$personName = trim((string) ($row['third_party_person_name'] ?? ''));
		return $personName !== '' ? $personName : trim((string) ($row['third_party_name'] ?? ''));
		}
	}

?>
