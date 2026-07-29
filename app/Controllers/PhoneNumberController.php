<?php

namespace App\Controllers;

use App\Core\TenantContext;
use App\Core\TenantAwareController;
use App\Models\PhoneNumber;

class PhoneNumberController extends TenantAwareController
	{

	/**
	 * Index method
	 */
	public function index()
		{
		$this->requirePermission('phone_number.index');

		$phoneNumber = new PhoneNumber();
		$tenantId = $this->currentTenantId();

		$filters = [
			'id'           => isset($_GET['id'])           ? trim($_GET['id'])           : '',
			'phone_number' => isset($_GET['phone_number']) ? trim($_GET['phone_number']) : '',
			'person_id'    => isset($_GET['person_id'])    ? trim($_GET['person_id'])    : '',
		];

		$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
		if ($page < 1)
			{
			$page = 1;
			}

		$perPage = 10;
		$offset = ($page - 1) * $perPage;

		$phoneNumbers = $phoneNumber->search($filters, $perPage, $offset, $tenantId);
		$total = $phoneNumber->count($filters, $tenantId);
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
				'data'       => $phoneNumbers,
			]);
			}

		$this->view('phone_numbers/index', [
			'phone_numbers' => $phoneNumbers,
			'title'         => 'Lista de telefones',
			'filters'       => $filters,
			'page'          => $page,
			'totalPages'    => $totalPages,
			'total'         => $total,
		]);
		}

	/**
	 * Create method
	 */
	public function create()
		{
		$this->requirePermission('phone_number.create');
		$this->view('phone_numbers/create', ['title' => 'Novo telefone']);
		}

	/**
	 * Store method
	 */
	public function store()
		{
		$this->requirePermission('phone_number.store');
		$phoneNumber = new PhoneNumber();
		try
			{
			$phoneNumber->create($_POST);
			$this->redirectWithFlash('phone_number', 'success', 'Telefone salvo com sucesso.');
			}
		catch (\Throwable $e)
			{
			error_log('PHONE NUMBER STORE ERROR: ' . $e->getMessage());
			if ($this->wantsJson())
				{
				$this->json(['error' => 'Não foi possível salvar o telefone agora.'], 422);
				}

			$this->redirectWithFlash('phone_number/create', 'danger', 'Não foi possível salvar o telefone agora.');
			}
		}

	/**
	 * Edit method
	 * @param Integer $id PhoneNumber id
	 */
	public function edit($id)
		{
		$this->requirePermission('phone_number.edit');
		$phoneNumber = new PhoneNumber();
		$data = $phoneNumber->find($id);
		if (!$data)
			{
			$this->redirectWithFlash('phone_number', 'warning', 'Telefone não encontrado.');
			}

		$this->view('phone_numbers/edit', ['title' => 'Editar telefone', 'phone_number' => $data]);
		}

	/**
	 * Update method
	 * @param Integer $id PhoneNumber id
	 */
	public function update($id)
		{
		$this->requirePermission('phone_number.update');
		$phoneNumber = new PhoneNumber();
		try
			{
			$phoneNumber->update($id, $_POST);
			$this->redirectWithFlash('phone_number', 'success', 'Telefone atualizado com sucesso.');
			}
		catch (\Throwable $e)
			{
			error_log('PHONE NUMBER UPDATE ERROR: ' . $e->getMessage());
			if ($this->wantsJson())
				{
				$this->json(['error' => 'Não foi possível atualizar o telefone agora.'], 422);
				}

			$this->redirectWithFlash('phone_number/edit/' . (int) $id, 'danger', 'Não foi possível atualizar o telefone agora.');
			}
		}

	/**
	 * Informations method
	 * @param Integer $id PhoneNumber id
	 */
	public function informations($id)
		{
		$this->requirePermission('phone_number.informations');
		$phoneNumber = new PhoneNumber();
		$data = $phoneNumber->findAllByPhoneNumberId($id);
		if (empty($data['phone_number']))
			{
			$this->redirectWithFlash('phone_number', 'warning', 'Telefone não encontrado.');
			}

		$this->view('phone_numbers/informations', ['title' => 'Informações sobre o telefone', 'phone_number' => $data['phone_number']]);
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
