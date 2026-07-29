<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Country;

class CountryController extends Controller
	{

	/**
	 * Lists countries with filters and pagination.
	 */
	public function index()
		{
		$this->requirePermission('country.index');
		
		$country = new Country();

		// Filters received from the query string.
		$filters = [
			'id' => isset($_GET['id']) ? trim($_GET['id']) : '',
			'name' => isset($_GET['name']) ? trim($_GET['name']) : '',
      'acronym' => isset($_GET['acronym']) ? trim($_GET['acronym']) : '',
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
		$countries = $country->search($filters, $perPage, $offset);
		// Total count for pagination using the same filters.
		$total = $country->count($filters);
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
				'data' => $countries,
			]);
			}

      // Default HTML response.
      $this->view('countries/index', [
        'countries' => $countries,
        'title' => 'Lista de países',
        'filters' => $filters,
        'page' => $page,
        'totalPages' => $totalPages,
        'total' => $total,
      ]);
		}

	/**
	 * Renders the country creation form.
	 */
	public function create()
		{
		$this->requirePermission('country.create');
		$this->view('countries/create', ['title' => 'Novo país']);
		}

	/**
	 * Persists a new country and redirects back to the listing.
	 */
	public function store()
		{
		$this->requirePermission('country.store');
		$country = new Country();
		try
			{
			$country->create($_POST);
			$this->redirectWithFlash('country', 'success', 'País salvo com sucesso.');
			}
		catch (\Throwable $e)
			{
			error_log('COUNTRY STORE ERROR: ' . $e->getMessage());
			if ($this->wantsJson())
				{
				$this->json(['error' => 'Não foi possível salvar o país agora.'], 422);
				}

			$this->redirectWithFlash('country/create', 'danger', 'Não foi possível salvar o país agora.');
			}
		}

	/**
	 * Displays the details for the requested country.
	 *
	 * @param mixed $id Country identifier.
	 */
	public function informations($id)
		{
		$this->requirePermission('country.informations');
		$country = new Country();
		$data = $country->findAllByCountryId($id);
		if (empty($data['countries']))
			{
			$this->redirectWithFlash('country', 'warning', 'País não encontrado.');
			}

		$this->view('countries/informations', ['title' => 'Informações sobre a país',
			'countries' => $data['countries']]);
		}

	/**
	 * Returns autocomplete suggestions for countries.
	 */
	public function autocomplete()
		{
		$this->requirePermission('country.autocomplete');

		$term = $_GET['term'] ?? '';
		$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 100;

		$country = new Country();
		$rows = $country->autocomplete((string) $term, $limit);

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
	}
?>
