<?php

namespace App\Controllers;

use App\Core\TenantAwareController;
use App\Models\Bank;
use App\Models\BankAccount;

class BankAccountController extends TenantAwareController
	{
	private const BANK_ACCOUNT_FIELDS = [
		'bank_id',
		'name',
		'agency',
		'account',
		'pix_key_type',
		'pix_key',
		'bank_slip_instructions_line_1',
		'bank_slip_instructions_line_2',
		'bank_slip_instructions_line_3',
		'bank_slip_instructions_line_4',
		'bank_slip_protest',
		'bank_slip_protest_number_of_days',
		'bank_slip_wallet',
		'bank_slip_species_title',
		'bank_slip_interest_type',
		'bank_slip_interest_amount_type',
		'bank_slip_interest',
		'bank_slip_late_fee_amount',
		'active',
	];

	/**
	 * Reads bank account list filters from the current request.
	 */
	private function readFilters(): array
		{
		return [
			'id'      => isset($_GET['id']) ? trim($_GET['id']) : '',
			'bank_id' => isset($_GET['bank_id']) ? trim($_GET['bank_id']) : '',
			'name'    => isset($_GET['name']) ? trim($_GET['name']) : '',
			'agency'  => isset($_GET['agency']) ? trim($_GET['agency']) : '',
			'account' => isset($_GET['account']) ? trim($_GET['account']) : '',
			'active'  => isset($_GET['active']) ? trim($_GET['active']) : '',
		];
		}

	/**
	 * Lists bank accounts with filters and pagination.
	 */
	public function index(): void
		{
		$this->requirePermission('bank_account.index');

		$bankAccount = new BankAccount();
		$filters = $this->readFilters();

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
			$bankAccount->create($this->bankAccountPayload($_POST));
			$this->redirectWithFlash('bank_account', 'success', 'Conta bancária salva com sucesso.');
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
			$this->redirectWithFlash('bank_account', 'warning', 'Conta bancária não encontrada.');
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
			$bankAccount->update($id, $this->bankAccountPayload($_POST));
			$this->redirectWithFlash('bank_account', 'success', 'Conta bancária atualizada com sucesso.');
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
			$this->redirectWithFlash('bank_account', 'warning', 'Conta bancária não encontrada.');
			}

		$this->view('bank_accounts/informations', [
			'title'       => 'Informações sobre a conta bancária',
			'bankAccount' => $data['bank_account'],
		]);
		}

	/**
	 * Deletes an existing bank account.
	 *
	 * @param mixed $id Bank account identifier.
	 */
	public function delete($id): void
		{
		$this->requirePermission('bank_account.delete');

		$bankAccount = new BankAccount();

		try
				{
				$bankAccount->delete($id);
				$this->redirectWithFlash('bank_account', 'success', 'Conta bancária excluída com sucesso.');
				}
		catch (\Throwable $e)
			{
			if ($this->wantsJson())
				{
				$this->json(['error' => $e->getMessage()], 422);
				}

			$filters = $this->readFilters();
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

			http_response_code(422);
			$this->view('bank_accounts/index', [
				'bankAccounts' => $bankAccounts,
				'title'        => 'Lista de contas bancárias',
				'filters'      => $filters,
				'page'         => $page,
				'totalPages'   => $totalPages,
				'total'        => $total,
				'formError'    => $e->getMessage(),
			]);
			}
		}

	/**
	 * Returns only bank account fields handled by the form.
	 *
	 * @param array $input Raw request data.
	 */
	private function bankAccountPayload(array $input): array
		{
		$payload = [];
		foreach (self::BANK_ACCOUNT_FIELDS as $field)
			{
			if (array_key_exists($field, $input))
				{
				$payload[$field] = $input[$field];
				}
			}

		return $payload;
		}
	}
?>
