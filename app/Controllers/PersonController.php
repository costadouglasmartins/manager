<?php

namespace App\Controllers;

use App\Core\Authorization;
use App\Core\TenantAwareController;
use App\Models\Person;
use App\Models\Neighborhood;
use App\Models\City;

class PersonController extends TenantAwareController
	{

	/**
	 * Lists people with filters and pagination.
	 */
	public function index()
		{
		$this->requirePermission('person.index');
		
		$person = new Person();

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
		$people = $person->search($filters, $perPage, $offset);
		// Total count for pagination using the same filters.
		$total = $person->count($filters);
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
        'title' => 'Lista de empresas',
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
		$this->view('people/create', ['title' => 'Nova pessoa']);
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
			return [
				'id'    => (int) $r['id'],
				'label' => $r['name'],
				'name'  => $r['name'],
			];
			}, $rows);

		$this->json(['items' => $items]);
		}
  
	/**
	 * Persists a new person and redirects back to the listing.
	 */
	public function store()
		{
		$this->requirePermission('person.store');
		$person = new Person();
		$person->create($_POST);
		$this->redirect('person');
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
		$data = $person->findAllByPersonId($id);

		if (empty($data['person']))
			{
			$this->redirect('person');
			}

		$this->view('people/edit', [
			'title' => 'Editar pessoa',
			'person' => $data['person'],
			'phones' => $data['phones'],
			'emails' => $data['emails'],
			'addresses' => $data['addresses'],
		]);
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
		$person->update($id, $_POST);
		$this->redirect('person');
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
		$data = $person->findAllByPersonId($id);
		$this->view('people/informations', [
			'title'  => 'Informações sobre a pessoa',
			'person' => $data['person'],
			'user'   => $data['user'],
			'phones' => $data['phones'],
			'emails' => $data['emails'],
			'addresses' => $data['addresses'],
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
		$rows = $person->autocomplete((string) $term, $limit);

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
	}
?>