<?php

namespace App\Controllers;

use App\Core\TenantContext;
use App\Core\TenantAwareController;
use App\Models\Email;

class EmailController extends TenantAwareController
	{

	/**
	 * Index method
	 */
	public function index()
		{
		$this->requirePermission('email.index');

		$email = new Email();
		$tenantId = $this->currentTenantId();

		$filters = [
			'id'          => isset($_GET['id'])          ? trim($_GET['id'])          : '',
			'email'       => isset($_GET['email'])       ? trim($_GET['email'])       : '',
			'person_id'   => isset($_GET['person_id'])   ? trim($_GET['person_id'])   : '',
			'person_name' => isset($_GET['person_name']) ? trim($_GET['person_name']) : '',
		];

		$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
		if ($page < 1)
			{
			$page = 1;
			}

		$perPage = 10;
		$offset = ($page - 1) * $perPage;

		$emails = $email->search($filters, $perPage, $offset, $tenantId);
		$total = $email->count($filters, $tenantId);
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
				'data'       => $emails,
			]);
			}

		$this->view('emails/index', [
			'emails'     => $emails,
			'title'      => 'Lista de e-mails',
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
		$this->requirePermission('email.create');
		$this->view('emails/create', ['title' => 'Novo e-mail']);
		}

	/**
	 * Store method
	 */
	public function store()
		{
		$this->requirePermission('email.store');
		$email = new Email();
		try
			{
			$email->create($_POST);
			$this->redirectWithFlash('email', 'success', 'E-mail salvo com sucesso.');
			}
		catch (\Throwable $e)
			{
			error_log('EMAIL STORE ERROR: ' . $e->getMessage());
			if ($this->wantsJson())
				{
				$this->json(['error' => 'Não foi possível salvar o e-mail agora.'], 422);
				}

			$this->redirectWithFlash('email/create', 'danger', 'Não foi possível salvar o e-mail agora.');
			}
		}

	/**
	 * Edit method
	 * @param Integer $id Email id
	 */
	public function edit($id)
		{
		$this->requirePermission('email.edit');
		$email = new Email();
		$data = $email->find($id);
		if (!$data)
			{
			$this->redirectWithFlash('email', 'warning', 'E-mail não encontrado.');
			}

		$this->view('emails/edit', ['title' => 'Editar e-mail', 'email' => $data]);
		}

	/**
	 * Update method
	 * @param Integer $id Email id
	 */
	public function update($id)
		{
		$this->requirePermission('email.update');
		$email = new Email();
		try
			{
			$email->update($id, $_POST);
			$this->redirectWithFlash('email', 'success', 'E-mail atualizado com sucesso.');
			}
		catch (\Throwable $e)
			{
			error_log('EMAIL UPDATE ERROR: ' . $e->getMessage());
			if ($this->wantsJson())
				{
				$this->json(['error' => 'Não foi possível atualizar o e-mail agora.'], 422);
				}

			$this->redirectWithFlash('email/edit/' . (int) $id, 'danger', 'Não foi possível atualizar o e-mail agora.');
			}
		}

	/**
	 * Informations method
	 * @param Integer $id Email id
	 */
	public function informations($id)
		{
		$this->requirePermission('email.informations');
		$email = new Email();
		$data = $email->findAllByEmailId($id);
		if (empty($data['email']))
			{
			$this->redirectWithFlash('email', 'warning', 'E-mail não encontrado.');
			}

		$this->view('emails/informations', ['title' => 'Informações sobre o e-mail', 'email' => $data['email']]);
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
