<?php

namespace App\Controllers;

use App\Core\TenantAwareController;
use App\Models\Bank;
use App\Models\BankAccount;

class BankAccountController extends TenantAwareController
	{
	/**
	 * Lists bank accounts with filters and pagination.
	 */
	public function index(): void
		{
		$this->requirePermission('bank_account.index');

		$bankAccount = new BankAccount();
		$filters = [
			'id'      => isset($_GET['id']) ? trim($_GET['id']) : '',
			'bank_id' => isset($_GET['bank_id']) ? trim($_GET['bank_id']) : '',
			'name'    => isset($_GET['name']) ? trim($_GET['name']) : '',
			'agency'  => isset($_GET['agency']) ? trim($_GET['agency']) : '',
			'account' => isset($_GET['account']) ? trim($_GET['account']) : '',
			'active'  => isset($_GET['active']) ? trim($_GET['active']) : '',
		];

		$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
		if ($page < 1)
			{
			$page = 1;
			}

		$perPage = 10;
		$offset = ($page - 1) * $perPage;
		$bankAccounts = $bankAccount->search($filters, $perPage, $offset);
		$total = $bankAccount->count($filters);
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
				'data'       => $bankAccounts,
			]);
			}

		$this->view('bank_accounts/index', [
			'bankAccounts' => $bankAccounts,
			'title'        => 'Lista de contas bancárias',
			'filters'      => $filters,
			'page'         => $page,
			'totalPages'   => $totalPages,
			'total'        => $total,
		]);
		}

	/**
	 * Renders the bank account creation form.
	 */
	public function create(): void
		{
		$this->requirePermission('bank_account.create');

		$bank = new Bank();
		$this->view('bank_accounts/create', [
			'title' => 'Nova conta bancária',
			'banks' => $bank->getOptions(),
		]);
		}

	/**
	 * Persists a new bank account and redirects back to the listing.
	 */
	public function store(): void
		{
		$this->requirePermission('bank_account.store');

		$bankAccount = new BankAccount();
		$bankAccount->create($_POST);
		$this->redirect('bank_account');
		}

	/**
	 * Renders the bank account edit form.
	 *
	 * @param mixed $id Bank account identifier.
	 */
	public function edit($id): void
		{
		$this->requirePermission('bank_account.edit');

		$bankAccount = new BankAccount();
		$data = $bankAccount->find($id);
		if (!$data)
			{
			$this->redirect('bank_account');
			}

		$bank = new Bank();
		$this->view('bank_accounts/edit', [
			'title'       => 'Editar conta bancária',
			'bankAccount' => $data,
			'banks'       => $bank->getOptions(),
		]);
		}

	/**
	 * Updates an existing bank account.
	 *
	 * @param mixed $id Bank account identifier.
	 */
	public function update($id): void
		{
		$this->requirePermission('bank_account.update');

		$bankAccount = new BankAccount();
		$bankAccount->update($id, $_POST);
		$this->redirect('bank_account');
		}

	/**
	 * Displays bank account details.
	 *
	 * @param mixed $id Bank account identifier.
	 */
	public function informations($id): void
		{
		$this->requirePermission('bank_account.informations');

		$bankAccount = new BankAccount();
		$data = $bankAccount->findAllByBankAccountId($id);
		if (empty($data['bank_account']))
			{
			$this->redirect('bank_account');
			}

		$this->view('bank_accounts/informations', [
			'title'       => 'Informações sobre a conta bancária',
			'bankAccount' => $data['bank_account'],
		]);
		}
	}
?>
