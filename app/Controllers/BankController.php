<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Bank;

class BankController extends Controller
	{
	/**
	 * Lists banks with filters and pagination.
	 */
	public function index(): void
		{
		$this->requirePermission('bank.index');

		$bank = new Bank();
		$filters = [
			'id'     => isset($_GET['id']) ? trim($_GET['id']) : '',
			'name'   => isset($_GET['name']) ? trim($_GET['name']) : '',
			'number' => isset($_GET['number']) ? trim($_GET['number']) : '',
		];

		$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
		if ($page < 1)
			{
			$page = 1;
			}

		$perPage = 10;
		$offset = ($page - 1) * $perPage;
		$banks = $bank->search($filters, $perPage, $offset);
		$total = $bank->count($filters);
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
				'data'       => $banks,
			]);
			}

		$this->view('banks/index', [
			'banks'      => $banks,
			'title'      => 'Lista de bancos',
			'filters'    => $filters,
			'page'       => $page,
			'totalPages' => $totalPages,
			'total'      => $total,
		]);
		}

	/**
	 * Displays bank details.
	 *
	 * @param mixed $id Bank identifier.
	 */
	public function informations($id): void
		{
		$this->requirePermission('bank.informations');

		$bank = new Bank();
		$data = $bank->findAllByBankId($id);
		if (empty($data['bank']))
			{
			$this->redirect('bank');
			}

		$this->view('banks/informations', ['title' => 'Informações sobre o banco', 'bank' => $data['bank']]);
		}
	}
?>
