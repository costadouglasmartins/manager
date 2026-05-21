<?php

namespace App\Controllers;

use App\Core\TenantAwareController;
use App\Models\BankFile;
use App\Models\BankSlip;
use App\Services\Banking\BankSlipService;
use App\Services\Banking\RemittanceGeneratorFactory;
use ZipArchive;

class BankSlipController extends TenantAwareController
	{
	public function index(): void
		{
		$this->requirePermission('bank_slip.index');

		$model = new BankSlip();
		$bankAccountIds = isset($_GET['bank_account_ids']) && is_array($_GET['bank_account_ids']) ? $_GET['bank_account_ids'] : [];
		if ($bankAccountIds === [] && isset($_GET['bank_account_id']))
			{
			$bankAccountIds = [$_GET['bank_account_id']];
			}
		$bankAccountIds = array_values(array_unique(array_filter(array_map('intval', $bankAccountIds), static fn ($id) => $id > 0)));

		$filters = [
			'id' => isset($_GET['id']) ? trim($_GET['id']) : '',
			'bank_account_id' => $bankAccountIds[0] ?? '',
			'bank_account_ids' => $bankAccountIds,
			'status' => isset($_GET['status']) ? trim($_GET['status']) : '',
			'document_number' => isset($_GET['document_number']) ? trim($_GET['document_number']) : '',
		];
		$page = max(1, (int) ($_GET['page'] ?? 1));
		$perPage = 10;
		$offset = ($page - 1) * $perPage;

		$bankSlips = $model->search($filters, $perPage, $offset);
		$total = $model->count($filters);
		$totalPages = max(1, (int) ceil($total / $perPage));
		$bankSlipGroups = $this->groupBankSlipsByBankAccount($bankSlips);
		$remittanceFactory = new RemittanceGeneratorFactory();
		$bankSlipGroups = $this->addRemittanceOptionsToGroups($bankSlipGroups, $remittanceFactory);

		$this->view('bank_slips/index', [
			'title' => 'Boletos bancários',
			'bankSlips' => $bankSlips,
			'bankSlipGroups' => $bankSlipGroups,
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
		$layout = isset($_POST['remittance_layout']) ? trim((string) $_POST['remittance_layout']) : '';
		$remittanceBankAccountId = (int) ($_POST['remittance_bank_account_id'] ?? 0);
		$remittanceAction = isset($_POST['remittance_action']) ? trim((string) $_POST['remittance_action']) : '';
		if ($remittanceAction !== '')
			{
			$actionParts = explode(':', $remittanceAction, 2);
			if (count($actionParts) === 2)
				{
				$remittanceBankAccountId = (int) $actionParts[0];
				$layout = trim($actionParts[1]);
				}
			}

		$model = new BankSlip();
		$slips = $model->findReadyForRemittance($ids);
		if ($remittanceBankAccountId > 0)
			{
			$slips = array_values(array_filter($slips, static function (array $slip) use ($remittanceBankAccountId): bool {
				return (int) ($slip['bank_account_id'] ?? 0) === $remittanceBankAccountId;
				}));
			}
		if ($slips === [])
			{
			$this->redirect('bank_slip');
			}

		$factory = new RemittanceGeneratorFactory();
		$layout = $factory->normalizeLayout($layout);
		if ($layout === '')
			{
			$layout = $factory->normalizeLayout((string) ($slips[0]['bank_cnab'] ?? 'CNAB240'));
			}
		$groups = $this->groupBankSlipsByBankAccount($slips);
		$jobs = [];
		$sequence = 1;

		try
			{
			foreach ($groups as $group)
				{
				$bankAccount = $group['bank_account'];
				$accountSlips = $group['slips'];
				$generator = $factory->make($bankAccount, $layout);
				$content = $generator->generate($bankAccount, $accountSlips);

				$jobs[] = [
					'bank_account' => $bankAccount,
					'slips' => $accountSlips,
					'file_name' => $generator->fileName($bankAccount, $sequence),
					'content' => $content,
				];
				$sequence++;
				}
			}
		catch (\Throwable $e)
			{
			http_response_code(422);
			echo htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
			exit;
			}

		$directory = __DIR__ . '/../../public/uploads/bank_files/remittance';
		if (!is_dir($directory))
			{
			mkdir($directory, 0775, true);
			}

		$bankFileModel = new BankFile();
		foreach ($jobs as $index => $job)
			{
			$fileName = $job['file_name'];
			$fullPath = $directory . '/' . $fileName;
			file_put_contents($fullPath, $job['content']);
			$jobs[$index]['full_path'] = $fullPath;

			$relativePath = 'uploads/bank_files/remittance/' . $fileName;
			$file = $bankFileModel->create([
				'bank_account_id' => $job['bank_account']['bank_account_id'] ?? null,
				'file_type' => 'REMITTANCE',
				'file_name' => $fileName,
				'file_path' => $relativePath,
				'file_hash' => hash('sha256', $job['content']),
			]);

			$model->attachRemittanceFile(array_column($job['slips'], 'id'), (int) ($file['bank_file_id'] ?? 0));
			}

		if (count($jobs) === 1)
			{
			$job = $jobs[0];
			$this->downloadContent($job['file_name'], $job['content'], 'text/plain; charset=ISO-8859-1');
			}

		$zipFileName = 'remessas_' . strtolower(preg_replace('/[^A-Z0-9]+/i', '_', $layout) ?? 'cnab') . '_' . date('Ymd_His') . '.zip';
		$zipFullPath = $directory . '/' . $zipFileName;
		$zip = new ZipArchive();
		if ($zip->open($zipFullPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true)
			{
			http_response_code(500);
			echo 'Não foi possível gerar o arquivo ZIP de remessas.';
			exit;
			}

		foreach ($jobs as $job)
			{
			$zip->addFile($job['full_path'], $job['file_name']);
			}
		$zip->close();

		$this->downloadContent($zipFileName, (string) file_get_contents($zipFullPath), 'application/zip');
		}

	/**
	 * Groups bank slips by bank account for display and remittance generation.
	 */
	private function groupBankSlipsByBankAccount(array $bankSlips): array
		{
		$groups = [];

		foreach ($bankSlips as $bankSlip)
			{
			$bankAccountId = (int) ($bankSlip['bank_account_id'] ?? 0);
			if (!isset($groups[$bankAccountId]))
				{
				$groups[$bankAccountId] = [
					'bank_account' => $bankSlip,
					'slips' => [],
					'total_amount' => 0.0,
					'remittance_count' => 0,
					'remittance_total_amount' => 0.0,
				];
				}

			$groups[$bankAccountId]['slips'][] = $bankSlip;
			$groups[$bankAccountId]['total_amount'] += (float) ($bankSlip['amount'] ?? 0);
			if (in_array((string) ($bankSlip['status'] ?? ''), ['READY', 'REJECTED'], true))
				{
				$groups[$bankAccountId]['remittance_count']++;
				$groups[$bankAccountId]['remittance_total_amount'] += (float) ($bankSlip['amount'] ?? 0);
				}
			}

		return $groups;
		}

	/**
	 * Adds the remittance options supported by each bank account group.
	 */
	private function addRemittanceOptionsToGroups(array $bankSlipGroups, RemittanceGeneratorFactory $factory): array
		{
		foreach ($bankSlipGroups as $bankAccountId => $group)
			{
			$bankSlipGroups[$bankAccountId]['remittance_options'] = $factory->optionsForBankAccount($group['bank_account'] ?? []);
			}

		return $bankSlipGroups;
		}

	/**
	 * Sends generated remittance content to the browser.
	 */
	private function downloadContent(string $fileName, string $content, string $contentType): void
		{
		header('Content-Type: ' . $contentType);
		header('Content-Disposition: attachment; filename="' . $fileName . '"');
		header('Content-Length: ' . strlen($content));
		header('Cache-Control: no-store, no-cache, must-revalidate');
		header('Pragma: no-cache');
		echo $content;
		exit;
		}
	}
?>
