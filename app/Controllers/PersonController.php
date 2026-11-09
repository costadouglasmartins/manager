<?php

namespace App\Controllers;

use App\Core\Authorization;
use App\Core\TenantContext;
use App\Core\TenantAwareController;
use App\Models\HrEmployee;
use App\Models\Person;
use App\Models\Neighborhood;
use App\Models\City;
use App\Services\ZipCodeLookupService;

class PersonController extends TenantAwareController
	{

	/**
	 * Lists people with filters and pagination.
	 */
	public function index()
		{
		$this->requirePermission('person.index');
		
		$person = new Person();
		$tenantId = $this->currentTenantId();

		// Filters received from the query string.
		$filters = [
			'id' => isset($_GET['id']) ? trim($_GET['id']) : '',
			'name' => isset($_GET['name']) ? trim($_GET['name']) : '',
			'type' => isset($_GET['type']) ? trim($_GET['type']) : '',
			'document' => isset($_GET['document']) ? trim($_GET['document']) : '',
			'birthdate' => isset($_GET['birthdate']) ? trim($_GET['birthdate']) : '',
			'active' => isset($_GET['active']) ? trim($_GET['active']) : '',
		];

		// Current page (?page=2).
		$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
		if ($page < 1)
			{
			$page = 1;
			}

		// Records per page.
		$perPage = 10;
		$offset = ($page - 1) * $perPage;

		// Paginated search with filters.
		$people = $person->search($filters, $perPage, $offset, $tenantId);
		// Total count for pagination using the same filters.
		$total = $person->count($filters, $tenantId);
		$totalPages = (int) ceil($total / $perPage);
		if ($totalPages < 1)
			{
			$totalPages = 1;
			}

		if ($this->wantsJson())
			{
			$this->json([
				'filters' => $filters,
				'page' => $page,
				'perPage' => $perPage,
				'total' => $total,
				'totalPages' => $totalPages,
				'data' => $people,
			]);
			}

      // Default HTML response.
      $this->view('people/index', [
        'people' => $people,
        'title' => 'Lista de pessoas',
        'filters' => $filters,
        'page' => $page,
        'totalPages' => $totalPages,
        'total' => $total,
      ]);
		}

	/**
	 * Renders the person creation form.
	 */
	public function create()
		{
		$this->requirePermission('person.create');
		$this->view('people/create', ['title' => 'Nova pessoa'] + $this->employeeViewData(null, null, $this->canManageEmployee(false)));
		}

	/**
	 * Neighborhood search — used by the address autocomplete on the person form.
	 * Allows create or edit permission without requiring a separate neighborhood ability.
	 */
	public function neighborhoodSearch()
		{
		$this->requirePersonFormAccess();

		$term  = trim($_GET['term'] ?? '');
		$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 20;

		$neighborhood = new Neighborhood();
		$rows = $neighborhood->autocomplete($term, $limit);

		$items = array_map(function ($r)
			{
			$label = trim((string) ($r['name'] ?? ''));
			$cityName = trim((string) ($r['city_name'] ?? ''));

			if ($cityName !== '')
				{
				$label .= ' - ' . $cityName;
				}

			return [
				'id'    => (int) $r['id'],
				'label' => $label,
				'name'  => $r['name'],
				'city_name' => $r['city_name'] ?? null,
			];
			}, $rows);

		$this->json(['items' => $items]);
		}

	/**
	 * Neighborhood create — creates a neighborhood inline during person registration/edit.
	 * Allows create or edit permission.
	 */
	public function neighborhoodCreate()
		{
		$this->requirePersonFormAccess();

		$name   = trim($_POST['name'] ?? '');
		$cityId = (int) ($_POST['city_id'] ?? 0);

		if ($name === '' || $cityId === 0)
			{
			$this->json(['error' => 'Nome e cidade são obrigatórios.'], 422);
			}

		$neighborhood = new Neighborhood();

		try
			{
			$result = $neighborhood->create([
				'name'    => $name,
				'city_id' => $cityId,
			]);
			$this->json([
				'id'   => (int) $result['neighborhood_id'],
				'name' => $name,
			]);
			}
		catch (\Exception $e)
			{
			$this->json(['error' => 'Erro ao cadastrar bairro: ' . $e->getMessage()], 500);
			}
		}

	/**
	 * City search — used by the neighborhood inline create form.
	 * Allows create or edit permission.
	 */
	public function citySearch()
		{
		$this->requirePersonFormAccess();

		$term  = trim($_GET['term'] ?? '');
		$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 20;

		$city = new City();
		$rows = $city->autocomplete($term, $limit);

		$items = array_map(function ($r)
			{
			$label = trim((string) ($r['name'] ?? ''));
			$stateAcronym = trim((string) ($r['state_acronym'] ?? ''));

			if ($stateAcronym !== '')
				{
				$label .= ' - ' . $stateAcronym;
				}

			return [
				'id'    => (int) $r['id'],
				'label' => $label,
				'name'  => $r['name'],
				'state_acronym' => $r['state_acronym'] ?? null,
			];
			}, $rows);

		$this->json(['items' => $items]);
		}

	/**
	 * Looks up a ZIP code using the ViaCEP service for the person form.
	 */
	public function zipCodeSearch()
		{
		$this->requirePersonFormAccess();

		$zipCode = (string) ($_GET['zip_code'] ?? $_GET['cep'] ?? '');
		$service = new ZipCodeLookupService();
		$result = $service->lookup($zipCode);
		$status = (int) ($result['http_status'] ?? 200);
		unset($result['http_status']);

		$this->json($result, $status);
		}
  
	/**
	 * Persists a new person and redirects back to the listing.
	 */
	public function store()
		{
		$this->requirePermission('person.store');
		$person = new Person();

			try
				{
				// HR data is only processed for users that may create employees.
				$person->create($_POST, $this->canManageEmployee(false));
				$this->redirectWithFlash('person', 'success', 'Pessoa salva com sucesso.');
				}
		catch (\InvalidArgumentException $e)
			{
			if ($this->wantsJson())
				{
				$this->json(['error' => $e->getMessage()], 422);
				}

			http_response_code(422);
			$this->view('people/create', [
				'title' => 'Nova pessoa',
				'formData' => $_POST,
				'formError' => $e->getMessage(),
			] + $this->employeeViewData(null, $_POST, $this->canManageEmployee(false)));
			}
		catch (\Throwable $e)
			{
			// Errors raised on purpose by the HR flow carry a message written for the user.
			$message = $this->userFacingFailure($e, 'Não foi possível salvar a pessoa agora.');
			if ($this->wantsJson())
				{
				$this->json(['error' => $message], 500);
				}

			http_response_code(500);
			$this->view('people/create', [
				'title' => 'Nova pessoa',
				'formData' => $_POST,
				'formError' => $message,
			] + $this->employeeViewData(null, $_POST, $this->canManageEmployee(false)));
			}
		}

	/**
	 * Renders the person edit form for the requested record.
	 *
	 * @param mixed $id Person identifier.
	 */
	public function edit($id)
		{
		$this->requirePermission('person.edit');
		$person = new Person();
		$data = $person->findAllByPersonId($id, $this->currentTenantId());

			if (empty($data['person']))
				{
				$this->redirectWithFlash('person', 'danger', 'Pessoa não encontrada.');
				}

		$this->view('people/edit', [
			'title' => 'Editar pessoa',
			'person' => $data['person'],
			'phones' => $data['phones'],
			'emails' => $data['emails'],
			'addresses' => $data['addresses'],
		] + $this->employeeViewData((int) $data['person']['id'], null, $this->canManageEmployee(true)));
		}

	/**
	 * Updates the requested person and redirects back to the listing.
	 *
	 * @param mixed $id Person identifier.
	 */
	public function update($id)
		{
		$this->requirePermission('person.update');
		$person = new Person();
		$data = $person->findAllByPersonId($id, $this->currentTenantId());
			if (empty($data['person']))
				{
				$this->redirectWithFlash('person', 'danger', 'Pessoa não encontrada.');
				}

			try
				{
				// HR data is only processed for users that may edit employees.
				$person->update($id, $_POST, $this->currentTenantId(), $this->canManageEmployee(true));
				$this->redirectWithFlash('person', 'success', 'Pessoa atualizada com sucesso.');
				}
			catch (\InvalidArgumentException $e)
				{
				$this->renderEditError($data, $e->getMessage(), 422);
				}
			catch (\Throwable $e)
				{
				// Unexpected failures are logged; HR partial-failure messages are written for the user.
				error_log('Person update failed: ' . $e->getMessage());
				$this->renderEditError($data, $this->userFacingFailure($e, 'Não foi possível atualizar a pessoa agora.'), 500);
				}
			}

	/**
	 * Re-renders the edit form with the stored data overlaid by the submitted person and employee values.
	 *
	 * @param array $data Result of Person::findAllByPersonId() for the edited person.
	 * @param string $message Error message shown to the user.
	 * @param int $status HTTP status code.
	 */
	private function renderEditError(array $data, string $message, int $status): void
		{
		if ($this->wantsJson())
			{
			$this->json(['error' => $message], $status);
			}

		// Submitted values win over stored ones; role arrives as an array and the view expects the CSV form.
		$personForm = (isset($_POST['person']) && is_array($_POST['person'])) ? $_POST['person'] : [];
		$personForm['role'] = implode(',', Person::parseRoles($personForm['role'] ?? null));

		// The view formats birthdate from Y-m-d, while the form posts dd/mm/aaaa.
		if (array_key_exists('birthdate', $personForm))
			{
			$birthdate = trim((string) $personForm['birthdate']);
			if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $birthdate, $matches) === 1)
				{
				$birthdate = $matches[3] . '-' . $matches[2] . '-' . $matches[1];
				}
			elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthdate) !== 1)
				{
				// Unparseable text is dropped; the server already rejected it or will validate it again.
				$birthdate = '';
				}

			$personForm['birthdate'] = $birthdate;
			}

		$formPerson = array_merge($data['person'], $personForm);

		// Child rows come from the submission (not from the database) so unsaved edits survive the 422.
		$phones = $data['phones'];
		if (isset($_POST['phones']) && is_array($_POST['phones']))
			{
			$phones = array_map(static function ($row): array
				{
				$row = is_array($row) ? $row : [];
				return [
					'id' => (int) ($row['id'] ?? 0),
					'phone_number' => (string) ($row['number'] ?? ''),
					'observations' => (string) ($row['notes'] ?? ''),
				];
				}, array_values($_POST['phones']));
			}

		$emails = $data['emails'];
		if (isset($_POST['emails']) && is_array($_POST['emails']))
			{
			$emails = array_map(static function ($row): array
				{
				$row = is_array($row) ? $row : [];
				return [
					'id' => (int) ($row['id'] ?? 0),
					'email' => (string) ($row['address'] ?? ''),
					'observations' => (string) ($row['description'] ?? ''),
					'is_login' => (int) ($row['is_login'] ?? 0),
				];
				}, array_values($_POST['emails']));
			}

		$addresses = $data['addresses'];
		if (isset($_POST['addresses']) && is_array($_POST['addresses']))
			{
			$addresses = array_map(static function ($row): array
				{
				$row = is_array($row) ? $row : [];
				return [
					'id' => (int) ($row['id'] ?? 0),
					'zip_code' => (string) ($row['zip_code'] ?? ''),
					'street' => (string) ($row['street'] ?? ''),
					'number' => (string) ($row['number'] ?? ''),
					'observations' => (string) ($row['observations'] ?? ''),
					'neighborhood_id' => (int) ($row['neighborhood_id'] ?? 0),
					// The posted label already has the "neighborhood - city" form the view builds.
					'neighborhood_name' => (string) ($row['neighborhood_label'] ?? ''),
					'neighborhood_city_name' => '',
				];
				}, array_values($_POST['addresses']));
			}

		http_response_code($status);
		$this->view('people/edit', [
			'title' => 'Editar pessoa',
			'person' => $formPerson,
			'phones' => $phones,
			'emails' => $emails,
			'addresses' => $addresses,
			'formError' => $message,
		] + $this->employeeViewData((int) $data['person']['id'], $_POST, $this->canManageEmployee(true)));
		}

	/**
	 * Returns the message to show for an unexpected failure: HR partial-failure errors carry their own text.
	 *
	 * @param \Throwable $error Caught error.
	 * @param string $fallback Generic message.
	 * @return string Message for the user.
	 */
	private function userFacingFailure(\Throwable $error, string $fallback): string
		{
		if ($error instanceof \RuntimeException && $error->getCode() === Person::HR_PARTIAL_FAILURE_CODE)
			{
			return $error->getMessage();
			}

		return $fallback;
		}

	/**
	 * Tells whether the user may read and write HR data through the person forms.
	 * HR data (including salary) needs its own abilities: hr_employee.create on the creation form and hr_employee.edit on the edit form.
	 *
	 * @param bool $editing True for the edit form, false for the creation form.
	 * @return bool True when the HR card may be shown and employee[...] processed.
	 */
	private function canManageEmployee(bool $editing): bool
		{
		return Authorization::can($editing ? 'hr_employee.edit' : 'hr_employee.create');
		}

	/**
	 * Builds the HR variables shared by the person create/edit views.
	 *
	 * @param int|null $personId Person identifier, or null on the creation form.
	 * @param array|null $formData Submitted payload when re-rendering after an error.
	 * @param bool $canManage Whether the user holds the HR ability; without it no HR record is loaded or exposed.
	 * @return array{employee: array|null, hrDepartments: array, hrPositions: array, hrAvailable: bool, hrCanManage: bool} View variables.
	 */
	private function employeeViewData(?int $personId, ?array $formData, bool $canManage): array
		{
		if (!$canManage)
			{
			// No HR ability: the HR card is not rendered and no HR data (salary included) reaches the view.
			return [
				'employee' => null,
				'hrDepartments' => [],
				'hrPositions' => [],
				'hrAvailable' => false,
				'hrCanManage' => false,
			];
			}

		$submitted = (is_array($formData['employee'] ?? null)) ? $formData['employee'] : null;
		$hr = (new Person())->employeeFormData($personId, $submitted);

		// Stored record overlaid by the submitted fields, so the card shows what the user typed.
		$employee = $hr['employee'];
		if ($submitted !== null)
			{
			$employee = array_merge($employee ?? [], $submitted);

			// The submitted manager id wins, so its name is looked up again (the stored name may belong to another manager).
			$managerId = (int) ($submitted['manager_hr_employee_id'] ?? 0);
			$employee['manager_name'] = ($managerId > 0 && $hr['hr_available']) ? (new HrEmployee())->employeeLabel($managerId) : '';
			}

		return [
			'employee' => $employee,
			'hrDepartments' => $hr['hr_departments'],
			'hrPositions' => $hr['hr_positions'],
			'hrAvailable' => $hr['hr_available'],
			'hrCanManage' => true,
		];
		}

	/**
	 * Displays the details for the requested person and related tenant data.
	 *
	 * @param mixed $id Person identifier.
	 */
	public function informations($id)
		{
		$this->requirePermission('person.informations');
		$person = new Person();
		$data = $person->findAllByPersonId($id, $this->currentTenantId());
			if (empty($data['person']))
				{
				$this->redirectWithFlash('person', 'danger', 'Pessoa não encontrada.');
				}
		$this->view('people/informations', [
			'title'  => 'Informações sobre a pessoa',
			'person' => $data['person'],
			'user'   => $data['user'],
			'crm' => $data['crm'],
			'phones' => $data['phones'],
			'emails' => $data['emails'],
			'addresses' => $data['addresses'],
			// HR record of the person (null when none); Person::ROLE_LABELS maps the CSV roles to labels.
			// Salary and other HR data are only exposed to users holding the HR informations ability.
			'employee' => authCan('hr_employee.informations') ? (new Person())->employeeFormData((int) $data['person']['id'])['employee'] : null,
		]);
		}

	/**
	 * Returns autocomplete suggestions for people.
	 */
	public function autocomplete()
		{
		$this->requirePermission('person.autocomplete');

		$term = $_GET['term'] ?? '';
		$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 100;

		$person = new Person();
		$rows = $person->autocomplete((string) $term, $limit, $this->currentTenantId());

		$items = array_map(function ($r)
			{
			$label = trim($r['name'] ?? '');
      if ($label === '')
        {
				$label = (string) ($r['id'] ?? '');
        }
			return [
				'id' => isset($r['id']) ? (int) $r['id'] : null,
				'label' => $label,
				'name' => $r['name'] ?? null
			];
			}, $rows);

		$this->json(['items' => $items]);
		}

	/**
	 * Allows access to person form helper endpoints when the user can create or edit people.
	 */
	private function requirePersonFormAccess(): void
		{
		if (Authorization::can('person.create') || Authorization::can('person.edit'))
			{
			return;
			}

		$this->requirePermission('person.create');
		}

	/**
	 * Returns the active tenant id from session context.
	 */
	private function currentTenantId(): int
		{
		$tenant = TenantContext::tenant();
		return (int) ($tenant['id'] ?? 0);
		}
	}
?>
