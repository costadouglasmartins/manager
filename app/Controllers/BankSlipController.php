<?php

namespace App\Controllers;

use App\Core\TenantAwareController;
use App\Models\BankFile;
use App\Models\BankSlip;
use App\Services\Banking\BankSlipService;
use App\Services\Banking\ItauCnab400RemittanceService;

class BankSlipController extends TenantAwareController
	{
	public function index(): void
		{
		$this->requirePermission('bank_slip.index');

		$model = new BankSlip();
		$filters = [
			'id' => isset($_GET['id']) ? trim($_GET['id']) : '',
			'bank_account_id' => isset($_GET['bank_account_id']) ? trim($_GET['bank_account_id']) : '',
			'status' => isset($_GET['status']) ? trim($_GET['status']) : '',
			'document_number' => isset($_GET['document_number']) ? trim($_GET['document_number']) : '',
		];
		$page = max(1, (int) ($_GET['page'] ?? 1));
		$perPage = 10;
		$offset = ($page - 1) * $perPage;

		$bankSlips = $model->search($filters, $perPage, $offset);
		$total = $model->count($filters);
		$totalPages = max(1, (int) ceil($total / $perPage));

		$this->view('bank_slips/index', [
			'title' => 'Boletos bancários',
			'bankSlips' => $bankSlips,
			'filters' => $filters,
			'page' => $page,
			'totalPages' => $totalPages,
			'total' => $total,
			'bankAccounts' => $model->getBankAccountOptions(),
		]);
		}

	public function create(): void
		{
		$this->requirePermission('bank_slip.create');

		$model = new BankSlip();
		$this->view('bank_slips/create', [
			'title' => 'Novo boleto',
			'bankAccounts' => $model->getBankAccountOptions(),
		]);
		}

	public function store(): void
		{
		$this->requirePermission('bank_slip.store');

		$model = new BankSlip();
		$model->create($_POST);
		$this->redirect('bank_slip');
		}

	public function informations($id): void
		{
		$this->requirePermission('bank_slip.informations');

		$model = new BankSlip();
		$bankSlip = $model->find((int) $id);
		if ($bankSlip === [])
			{
			$this->redirect('bank_slip');
			}

		$this->view('bank_slips/informations', [
			'title' => 'Informações do boleto',
			'bankSlip' => $bankSlip,
		]);
		}

	public function generateBankSlip($id): void
		{
		$this->requirePermission('bank_slip.informations');

		$model = new BankSlip();
		$bankSlip = $model->find((int) $id);
		if ($bankSlip === [])
			{
			$this->redirect('bank_slip');
			}

		$service = new BankSlipService();
		$bank_slip = $service->build($bankSlip);

		$this->view('bank_slips/bank_slip', [
			'title' => 'Boleto #' . (int) $id,
			'bankSlip' => $bankSlip,
			'bank_slip' => $bank_slip,
		], false);
		}

	public function generateRemittance(): void
		{
		$this->requirePermission('bank_slip.generateRemittance');

		$ids = isset($_POST['bank_slip_ids']) && is_array($_POST['bank_slip_ids']) ? $_POST['bank_slip_ids'] : [];
		$model = new BankSlip();
		$slips = $model->findReadyForRemittance($ids);
		if ($slips === [])
			{
			$this->redirect('bank_slip');
			}

		$bankAccount = $slips[0];
		$service = new ItauCnab400RemittanceService();
		$content = $service->generate($bankAccount, $slips);

		$directory = __DIR__ . '/../../public/uploads/bank_files/remittance';
		if (!is_dir($directory))
			{
			mkdir($directory, 0775, true);
			}

		$fileName = 'itau_cnab400_' . date('Ymd_His') . '.rem';
		$fullPath = $directory . '/' . $fileName;
		file_put_contents($fullPath, $content);

		$relativePath = 'uploads/bank_files/remittance/' . $fileName;
		$bankFileModel = new BankFile();
		$file = $bankFileModel->create([
			'bank_account_id' => $bankAccount['bank_account_id'] ?? null,
			'file_type' => 'REMITTANCE',
			'file_name' => $fileName,
			'file_path' => $relativePath,
			'file_hash' => hash('sha256', $content),
		]);

		$model->attachRemittanceFile(array_column($slips, 'id'), (int) ($file['bank_file_id'] ?? 0));

		header('Content-Type: application/octet-stream');
		header('Content-Disposition: attachment; filename="' . $fileName . '"');
		header('Content-Length: ' . strlen($content));
		header('Cache-Control: no-store, no-cache, must-revalidate');
		header('Pragma: no-cache');
		echo $content;
		exit;
		}
	}
?>
