<?php

namespace App\Controllers;

use App\Core\Authorization;
use App\Core\TenantContext;
use App\Core\TenantAwareController;
use App\Models\Address;
use App\Services\ZipCodeLookupService;

class AddressController extends TenantAwareController
	{

	/**
	 * Index method
	 */
	public function index()
		{
		$this->requirePermission('address.index');

		$address = new Address();
		$tenantId = $this->currentTenantId();

		$filters = [
			'id'        => isset($_GET['id'])        ? trim($_GET['id'])        : '',
			'street'    => isset($_GET['street'])    ? trim($_GET['street'])    : '',
			'zip_code'  => isset($_GET['zip_code'])  ? trim($_GET['zip_code'])  : '',
			'person_id' => isset($_GET['person_id']) ? trim($_GET['person_id']) : '',
		];

		$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
		if ($page < 1)
			{
			$page = 1;
			}

		$perPage = 10;
		$offset = ($page - 1) * $perPage;

		$addresses = $address->search($filters, $perPage, $offset, $tenantId);
		$total = $address->count($filters, $tenantId);
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
				'data'       => $addresses,
			]);
			}

		$this->view('addresses/index', [
			'addresses'  => $addresses,
			'title'      => 'Lista de endereços',
			'filters'    => $filters,
			'page'       => $page,
			'totalPages' => $totalPages,
			'total'      => $total,
		]);
		}

	/**
	 * Create method
	 */
	public function create()
		{
		$this->requirePermission('address.create');
		$this->view('addresses/create', ['title' => 'Novo endereço']);
		}

	/**
	 * Looks up a ZIP code using the ViaCEP service.
	 */
	public function zipCodeSearch()
		{
		$this->requireAddressFormAccess();

		$zipCode = (string) ($_GET['zip_code'] ?? $_GET['cep'] ?? '');
		$service = new ZipCodeLookupService();
		$result = $service->lookup($zipCode);
		$status = (int) ($result['http_status'] ?? 200);
		unset($result['http_status']);

		$this->json($result, $status);
		}

	/**
	 * Store method
	 */
	public function store()
		{
		$this->requirePermission('address.store');
		$address = new Address();
		try
			{
			$address->create($_POST);
			$this->redirectWithFlash('address', 'success', 'Endereço salvo com sucesso.');
			}
		catch (\Throwable $e)
			{
			error_log('ADDRESS STORE ERROR: ' . $e->getMessage());
			if ($this->wantsJson())
				{
				$this->json(['error' => 'Não foi possível salvar o endereço agora.'], 422);
				}

			$this->redirectWithFlash('address/create', 'danger', 'Não foi possível salvar o endereço agora.');
			}
		}

	/**
	 * Edit method
	 * @param Integer $id Address id
	 */
	public function edit($id)
		{
		$this->requirePermission('address.edit');
		$address = new Address();
		$data = $address->find($id);
		if (!$data)
			{
			$this->redirectWithFlash('address', 'warning', 'Endereço não encontrado.');
			}

		$this->view('addresses/edit', ['title' => 'Editar endereço', 'address' => $data]);
		}

	/**
	 * Update method
	 * @param Integer $id Address id
	 */
	public function update($id)
		{
		$this->requirePermission('address.update');
		$address = new Address();
		try
			{
			$address->update($id, $_POST);
			$this->redirectWithFlash('address', 'success', 'Endereço atualizado com sucesso.');
			}
		catch (\Throwable $e)
			{
			error_log('ADDRESS UPDATE ERROR: ' . $e->getMessage());
			if ($this->wantsJson())
				{
				$this->json(['error' => 'Não foi possível atualizar o endereço agora.'], 422);
				}

			$this->redirectWithFlash('address/edit/' . (int) $id, 'danger', 'Não foi possível atualizar o endereço agora.');
			}
		}

	/**
	 * Informations method
	 * @param Integer $id Address id
	 */
	public function informations($id)
		{
		$this->requirePermission('address.informations');
		$address = new Address();
		$data = $address->findAllByAddressId($id);
		if (empty($data['address']))
			{
			$this->redirectWithFlash('address', 'warning', 'Endereço não encontrado.');
			}

		$this->view('addresses/informations', ['title' => 'Informações sobre o endereço', 'address' => $data['address']]);
		}

	/**
	 * Returns the active tenant id from session context.
	 */
	private function currentTenantId(): int
		{
		$tenant = TenantContext::tenant();
		return (int) ($tenant['id'] ?? 0);
		}

	/**
	 * Allows access to address helper endpoints when the user can create or edit addresses.
	 */
	private function requireAddressFormAccess(): void
		{
		if (Authorization::can('address.create') || Authorization::can('address.edit'))
			{
			return;
			}

		$this->requirePermission('address.create');
		}
	}

?>
