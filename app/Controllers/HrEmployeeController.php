<?php

namespace App\Controllers;

use App\Core\Authorization;
use App\Core\TenantAwareController;
use App\Core\TenantContext;
use App\Models\HrDepartment;
use App\Models\HrEmployee;
use App\Models\HrPosition;

/**
 * HR employees of the active tenant. Identity data stays in the person record; creation of the person happens in the person form.
 **/
class HrEmployeeController extends TenantAwareController
	{
	/**
	 * Reads list filters, sort key and direction from the current request.
	 * @return array{filters: array<string, string>, sort: string, direction: string} Normalized request values.
	 **/
	private function readFilters(): array
		{
		$read = static function (string $key): string
			{
			$value = $_GET[$key] ?? '';

			return is_scalar($value) ? trim((string) $value) : '';
			};

		$sort = $read('sort');
		$direction = strtolower($read('direction'));

		return [
			'filters' => [
				'id'                  => $read('id'),
				'name'                => $read('name'),
				'document'            => $read('document'),
				'registration_number' => $read('registration_number'),
				'hr_department_id'    => $read('hr_department_id'),
				'hr_position_id'      => $read('hr_position_id'),
				'employment_type'     => $read('employment_type'),
				'status'              => $read('status'),
				'active'              => $read('active'),
			],
			'sort'      => in_array($sort, HrEmployee::SORT_KEYS, true) ? $sort : 'name',
			'direction' => $direction === 'desc' ? 'desc' : 'asc',
		];
		}

	/**
	 * Accepts write actions only through POST so links and prefetches cannot change data.
	 * @return void
	 **/
	private function requirePostRequest(): void
		{
		if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST')
			{
			return;
			}

		if ($this->wantsJson())
			{
			$this->json(['error' => 'Método não permitido.'], 405, ['Allow' => 'POST']);
			}

		$this->redirectWithFlash('hr_employee', 'danger', 'Método não permitido.');
		}

	/**
	 * Returns the HR fields of a submission: the employee[...] group sent by the forms, or the flat POST body.
	 * @return array Raw HR fields.
	 **/
	private function readEmployeeInput(): array
		{
		return (isset($_POST['employee']) && is_array($_POST['employee'])) ? $_POST['employee'] : $_POST;
		}

	/**
	 * Adds the labels the form needs to show the selected person and manager again after a rejected submission.
	 * @param HrEmployee $model Employee model.
	 * @param array $input Submitted HR fields.
	 * @return array Submitted fields plus person_name and manager_name.
	 **/
	private function withLabels(HrEmployee $model, array $input): array
		{
		$input['person_name'] = $model->personLabel((int) ($input['person_id'] ?? 0), $this->currentTenantId());
		$input['manager_name'] = $model->employeeLabel((int) ($input['manager_hr_employee_id'] ?? 0));

		return $input;
		}

	/**
	 * Builds the lists shared by the HR forms.
	 * @param int|null $departmentId Department kept selectable even when inactive.
	 * @param int|null $positionId Position kept selectable even when inactive.
	 * @return array{hrDepartments: array, hrPositions: array} View variables.
	 **/
	private function formLists(?int $departmentId = null, ?int $positionId = null): array
		{
		return [
			'hrDepartments' => (new HrDepartment())->getOptions(true, $departmentId),
			'hrPositions'   => (new HrPosition())->getOptions(true, $positionId),
		];
		}

	/**
	 * Allows the helper endpoints to users that can use any form that embeds the field, or that hold the endpoint ability.
	 * @param string $ability Ability of the endpoint itself.
	 * @param array<int, string> $formAbilities Abilities of the forms that embed the field.
	 * @return void
	 **/
	private function requireFormAccess(string $ability, array $formAbilities): void
		{
		foreach ($formAbilities as $formAbility)
			{
			if (Authorization::can($formAbility))
				{
				$this->requireAuth();
				return;
				}
			}

		$this->requirePermission($ability);
		}

	/**
	 * Returns the active tenant id from the session context.
	 * @return int Tenant identifier, or 0 when none is selected.
	 **/
	private function currentTenantId(): int
		{
		$tenant = TenantContext::tenant();

		return (int) ($tenant['id'] ?? 0);
		}

	/**
	 * Lists employees with filters, sorting and pagination.
	 **/
	public function index(): void
		{
		$this->requirePermission('hr_employee.index');

		$request = $this->readFilters();
		$filters = $request['filters'];
		$model = new HrEmployee();

		$page = max(1, (int) ($_GET['page'] ?? 1));
		$perPage = 10;
		$total = $model->count($filters);
		$totalPages = max(1, (int) ceil($total / $perPage));
		$rows = $model->search($filters, $perPage, ($page - 1) * $perPage, $request['sort'], $request['direction']);

		if ($this->wantsJson())
			{
			// Salary is only exposed to users that can open the employee informations screen.
			if (!Authorization::can('hr_employee.informations'))
				{
				foreach ($rows as $index => $row)
					{
					unset($rows[$index]['salary']);
					}
				}

			$this->json([
				'filters'    => $filters,
				'page'       => $page,
				'perPage'    => $perPage,
				'total'      => $total,
				'totalPages' => $totalPages,
				'sort'       => $request['sort'],
				'direction'  => $request['direction'],
				'data'       => $rows,
			]);
			}

		// Filter selects list every row, including inactive ones, so old records stay filterable.
		$this->view('hr_employees/index', [
			'title'         => 'Funcionários',
			'employees'     => $rows,
			'filters'       => $filters,
			'page'          => $page,
			'perPage'       => $perPage,
			'total'         => $total,
			'totalPages'    => $totalPages,
			'sort'          => $request['sort'],
			'direction'     => $request['direction'],
			'hrDepartments' => (new HrDepartment())->getOptions(false),
			'hrPositions'   => (new HrPosition())->getOptions(false),
		]);
		}

	/**
	 * Downloads every employee matching the current filters as PDF.
	 **/
	public function exportPdf(): void
		{
		parent::exportPdf();
		}

	/**
	 * Downloads every employee matching the current filters as XLSX.
	 **/
	public function exportExcel(): void
		{
		parent::exportExcel();
		}

	/**
	 * Renders the form that links an existing employee-role person to an HR record.
	 **/
	public function create(): void
		{
		$this->requirePermission('hr_employee.create');

		$this->view('hr_employees/create', ['title' => 'Novo funcionário', 'employee' => null] + $this->formLists());
		}

	/**
	 * Persists a new HR record for an existing person (POST only).
	 **/
	public function store(): void
		{
		$this->requirePermission('hr_employee.store');
		$this->requirePostRequest();

		$model = new HrEmployee();
		$input = $this->readEmployeeInput();

		try
			{
			$model->create($input);
			$this->redirectWithFlash('hr_employee', 'success', 'Funcionário cadastrado com sucesso.');
			}
		catch (\InvalidArgumentException $e)
			{
			$this->renderCreateError($model, $input, $e->getMessage(), 422);
			}
		catch (\Throwable $e)
			{
			// Unexpected failures are logged without exposing internal details.
			error_log('HrEmployee store failed: ' . $e->getMessage());
			$this->renderCreateError($model, $input, 'Não foi possível salvar o funcionário agora.', 500);
			}
		}

	/**
	 * Re-renders the creation form with the submitted values, or answers JSON clients.
	 * @param HrEmployee $model Employee model.
	 * @param array $input Submitted HR fields.
	 * @param string $message Error message for the user.
	 * @param int $status HTTP status code.
	 * @return void
	 **/
	private function renderCreateError(HrEmployee $model, array $input, string $message, int $status): void
		{
		if ($this->wantsJson())
			{
			$this->json(['error' => $message], $status);
			}

		$employee = $this->withLabels($model, $input);
		http_response_code($status);
		$this->view('hr_employees/create', [
			'title'     => 'Novo funcionário',
			'employee'  => $employee,
			'formData'  => ['employee' => $employee],
			'formError' => $message,
		] + $this->formLists((int) ($input['hr_department_id'] ?? 0) ?: null, (int) ($input['hr_position_id'] ?? 0) ?: null));
		}

	/**
	 * Renders the edit form.
	 * @param mixed $id Employee identifier from the route.
	 **/
	public function edit($id): void
		{
		$this->requirePermission('hr_employee.edit');

		$employee = (new HrEmployee())->findById($id);
		if (!$employee)
			{
			$this->redirectWithFlash('hr_employee', 'warning', 'Funcionário não encontrado.');
			}

		$this->view('hr_employees/edit', [
			'title'    => 'Editar funcionário',
			'employee' => $employee,
		] + $this->formLists((int) $employee['hr_department_id'] ?: null, (int) $employee['hr_position_id'] ?: null));
		}

	/**
	 * Updates the HR data of an employee (POST only).
	 * @param mixed $id Employee identifier from the route.
	 **/
	public function update($id): void
		{
		$this->requirePermission('hr_employee.update');
		$this->requirePostRequest();

		$model = new HrEmployee();
		$input = $this->readEmployeeInput();

		try
			{
			$model->update($id, $input);
			$this->redirectWithFlash('hr_employee', 'success', 'Funcionário atualizado com sucesso.');
			}
		catch (\InvalidArgumentException $e)
			{
			$this->renderEditError($model, $id, $input, $e->getMessage(), 422);
			}
		catch (\Throwable $e)
			{
			// Unexpected failures are logged without exposing internal details.
			error_log('HrEmployee update failed: ' . $e->getMessage());
			if ($this->wantsJson())
				{
				$this->json(['error' => 'Não foi possível atualizar o funcionário agora.'], 500);
				}

			$this->redirectWithFlash('hr_employee', 'danger', 'Não foi possível atualizar o funcionário agora.');
			}
		}

	/**
	 * Re-renders the edit form with the stored record plus the submitted values, or answers JSON clients.
	 * @param HrEmployee $model Employee model.
	 * @param mixed $id Employee identifier.
	 * @param array $input Submitted HR fields.
	 * @param string $message Error message for the user.
	 * @param int $status HTTP status code.
	 * @return void
	 **/
	private function renderEditError(HrEmployee $model, $id, array $input, string $message, int $status): void
		{
		if ($this->wantsJson())
			{
			$this->json(['error' => $message], $status);
			}

		$record = $model->findById($id);
		if (!$record)
			{
			$this->redirectWithFlash('hr_employee', 'warning', 'Funcionário não encontrado.');
			}

		http_response_code($status);
		$this->view('hr_employees/edit', [
			'title'     => 'Editar funcionário',
			'employee'  => $record,
			'formData'  => ['employee' => $this->withLabels($model, $input)],
			'formError' => $message,
		] + $this->formLists(
			(int) ($input['hr_department_id'] ?? $record['hr_department_id']) ?: null,
			(int) ($input['hr_position_id'] ?? $record['hr_position_id']) ?: null
		));
		}

	/**
	 * Displays the details of an employee.
	 * @param mixed $id Employee identifier from the route.
	 **/
	public function informations($id): void
		{
		$this->requirePermission('hr_employee.informations');

		$employee = (new HrEmployee())->findById($id);
		if (!$employee)
			{
			$this->redirectWithFlash('hr_employee', 'warning', 'Funcionário não encontrado.');
			}

		$this->view('hr_employees/informations', [
			'title'    => 'Informações do funcionário',
			'employee' => $employee,
		]);
		}

	/**
	 * Deletes the HR record of an employee (POST only). The person and the employee role stay untouched.
	 * @param mixed $id Employee identifier from the route.
	 **/
	public function delete($id): void
		{
		$this->requirePermission('hr_employee.delete');
		$this->requirePostRequest();

		try
			{
			(new HrEmployee())->delete($id);
			if ($this->wantsJson())
				{
				$this->json(['success' => true, 'message' => 'Funcionário excluído com sucesso.']);
				}

			$this->redirectWithFlash('hr_employee', 'success', 'Funcionário excluído com sucesso.');
			}
		catch (\InvalidArgumentException $e)
			{
			// Business rule violations (not found, has subordinates) are reported in the format the caller expects.
			if ($this->wantsJson())
				{
				$this->json(['error' => $e->getMessage()], 422);
				}

			$this->redirectWithFlash('hr_employee', 'danger', $e->getMessage());
			}
		catch (\Throwable $e)
			{
			// Unexpected failures are logged without exposing internal details.
			error_log('HrEmployee delete failed: ' . $e->getMessage());
			if ($this->wantsJson())
				{
				$this->json(['error' => 'Não foi possível excluir o funcionário agora.'], 500);
				}

			$this->redirectWithFlash('hr_employee', 'danger', 'Não foi possível excluir o funcionário agora.');
			}
		}

	/**
	 * Returns person suggestions for the new-employee form: employee-role people of the tenant without an HR record.
	 * Response: {items: [{id, label, name}]} where id is the person id.
	 **/
	public function personAutocomplete(): void
		{
		$this->requireFormAccess('hr_employee.personAutocomplete', ['hr_employee.create', 'hr_employee.store']);

		$term = isset($_GET['term']) ? trim((string) $_GET['term']) : '';
		$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 20;
		$rows = (new HrEmployee())->autocompletePeople($term, $this->currentTenantId(), $limit);

		$this->json(['items' => $this->toItems($rows)]);
		}

	/**
	 * Returns employee suggestions for the manager field (used by the HR forms and by the person form).
	 * Response: {items: [{id, label, name}]} where id is the hr_employees id. The optional "exclude" parameter hides one employee.
	 **/
	public function employeeAutocomplete(): void
		{
		$this->requireFormAccess('hr_employee.employeeAutocomplete', [
			'hr_employee.create',
			'hr_employee.store',
			'hr_employee.edit',
			'hr_employee.update',
			'person.create',
			'person.store',
			'person.edit',
			'person.update',
		]);

		$term = isset($_GET['term']) ? trim((string) $_GET['term']) : '';
		$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 20;
		$exclude = isset($_GET['exclude']) ? (int) $_GET['exclude'] : null;
		$rows = (new HrEmployee())->autocompleteEmployees($term, $limit, $exclude);

		$this->json(['items' => $this->toItems($rows)]);
		}

	/**
	 * Converts autocomplete rows to the {id, label, name} shape of the shared autocomplete library.
	 * @param array<int, array<string, mixed>> $rows Rows with id and name.
	 * @return array<int, array{id: int|null, label: string, name: mixed}> Autocomplete items.
	 **/
	private function toItems(array $rows): array
		{
		return array_map(static function (array $row): array
			{
			$label = trim((string) ($row['name'] ?? ''));
			if ($label === '')
				{
				$label = (string) ($row['id'] ?? '');
				}

			return [
				'id'    => isset($row['id']) ? (int) $row['id'] : null,
				'label' => $label,
				'name'  => $row['name'] ?? null,
			];
			}, $rows);
		}
	}
?>
