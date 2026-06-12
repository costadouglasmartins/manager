<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\City;
use App\Models\Country;
use App\Models\State;

class CityController extends Controller
	{

	/**
	 * Reads city list filters from the current request.
	 *
	 * @return array{id: string, name: string, country_id: string, state_id: string}
	 */
	private function readFilters(): array
		{
		return [
			'id'       => isset($_GET['id']) ? trim((string) $_GET['id']) : '',
			'name'     => isset($_GET['name']) ? trim((string) $_GET['name']) : '',
			'country_id' => isset($_GET['country_id']) ? trim((string) $_GET['country_id']) : '',
			'state_id' => isset($_GET['state_id']) ? trim((string) $_GET['state_id']) : '',
		];
		}

	/**
	 * Resolves the country when an existing URL contains only a state filter.
	 *
	 * @param array{id: string, name: string, country_id: string, state_id: string} $filters
	 * @return array{id: string, name: string, country_id: string, state_id: string}
	 */
	private function normalizeLocationFilters(array $filters, State $state): array
		{
		if ($filters['country_id'] !== '' || $filters['state_id'] === '')
			{
			return $filters;
			}

		$selectedState = $state->find((int) $filters['state_id']);
		if (is_array($selectedState) && !empty($selectedState['country_id']))
			{
			$filters['country_id'] = (string) $selectedState['country_id'];
			}

		return $filters;
		}

	/**
	 * Index method
	 */
	public function index()
		{
		$this->requirePermission('city.index');

		$city = new City();
		$country = new Country();
		$state = new State();
		$filters = $this->normalizeLocationFilters($this->readFilters(), $state);
		$countries = $country->getAllForSelect();
		$states = $filters['country_id'] !== ''
			? $state->findByCountryId((int) $filters['country_id'])
			: [];

		$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
		if ($page < 1)
			{
			$page = 1;
			}

		$perPage = 10;
		$offset = ($page - 1) * $perPage;

		$cities = $city->search($filters, $perPage, $offset);
		$total = $city->count($filters);
		$totalPages = (int) ceil($total / $perPage);
		if ($totalPages < 1)
			{
			$totalPages = 1;
			}

		if ($this->wantsJson())
			{
			$this->json([
				'filters'    => $filters,
				'page'       => $page,
				'perPage'    => $perPage,
				'total'      => $total,
				'totalPages' => $totalPages,
				'data'       => $cities,
			]);
			}

		$this->view('cities/index', [
			'cities'     => $cities,
			'countries'  => $countries,
			'states'     => $states,
			'title'      => 'Lista de cidades',
			'filters'    => $filters,
			'page'       => $page,
			'totalPages' => $totalPages,
			'total'      => $total,
		]);
		}

	/**
	 * Downloads all city records matching the current list filters.
	 */
	public function exportPdf(): void
		{
		$this->requirePermission('city.index');

		$state = new State();
		$filters = $this->normalizeLocationFilters($this->readFilters(), $state);
		$city = new City();
		$country = new Country();
		$cities = $city->search($filters, null);
		$selectedCountry = $filters['country_id'] !== ''
			? $country->find((int) $filters['country_id'])
			: null;
		$selectedState = $filters['state_id'] !== ''
			? $state->find((int) $filters['state_id'])
			: null;

		$this->pdfService()->downloadTableReport(
			'Relatório de cidades',
			[
				'id' => [
					'label' => 'Código',
					'align' => 'right',
					'width' => '14%',
				],
				'name' => [
					'label' => 'Cidade',
					'width' => '32%',
				],
				'country_name' => [
					'label' => 'País',
					'width' => '27%',
				],
				'state_name' => [
					'label' => 'Estado',
					'width' => '27%',
				],
			],
			$cities,
			[
				'Código' => $filters['id'],
				'Nome' => $filters['name'],
				'País' => is_array($selectedCountry) ? ($selectedCountry['name'] ?? '') : '',
				'Estado' => is_array($selectedState) ? ($selectedState['name'] ?? '') : '',
			],
			'cidades-' . date('Ymd-His') . '.pdf'
		);
		}

	/**
	 * Returns states available for the selected country filter.
	 */
	public function statesByCountry(): void
		{
		$this->requirePermission('city.index');

		$countryId = isset($_GET['country_id']) ? (int) $_GET['country_id'] : 0;
		$state = new State();
		$rows = $state->findByCountryId($countryId);

		$items = array_map(static function (array $row): array
			{
			$name = trim((string) ($row['name'] ?? ''));
			$acronym = trim((string) ($row['acronym'] ?? ''));
			$label = $name;
			if ($label !== '' && $acronym !== '')
				{
				$label .= ' (' . $acronym . ')';
				}

			return [
				'id' => isset($row['id']) ? (int) $row['id'] : null,
				'label' => $label,
				'name' => $name,
				'acronym' => $acronym,
			];
			}, $rows);

		$this->json(['items' => $items]);
		}

	/**
	 * Informations method
	 * @param Integer $id City id
	 */
	public function informations($id)
		{
		$this->requirePermission('city.informations');
		$city = new City();
		$data = $city->findAllByCityId($id);
		$this->view('cities/informations', ['title' => 'Informações sobre a cidade', 'city' => $data['city']]);
		}

	/**
	 * Autocomplete method
	 */
	public function autocomplete()
		{
		$this->requirePermission('city.autocomplete');

		$term = $_GET['term'] ?? '';
		$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 100;

		$city = new City();
		$rows = $city->autocomplete((string) $term, $limit);

		$items = array_map(function ($r)
			{
			$label = trim($r['name'] ?? '');
			$stateAcronym = trim((string) ($r['state_acronym'] ?? ''));

			if ($label !== '' && $stateAcronym !== '')
				{
				$label .= ' - ' . $stateAcronym;
				}

			if ($label === '')
				{
				$label = (string) ($r['id'] ?? '');
				}
			return [
				'id'            => isset($r['id']) ? (int) $r['id'] : null,
				'label'         => $label,
				'name'          => $r['name'] ?? null,
				'state_acronym' => $r['state_acronym'] ?? null,
			];
			}, $rows);

		$this->json(['items' => $items]);
		}
	}

?>
