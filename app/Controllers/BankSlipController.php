<?php

namespace App\Controllers;

use App\Core\TenantContext;
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

	/**
	 * Renders the bank slip edit form when the slip is still a draft.
	 *
	 * @param mixed $id Bank slip identifier.
	 */
	public function edit($id): void
		{
		$this->requirePermission('bank_slip.edit');

		$model = new BankSlip();
		$bankSlip = $model->find((int) $id);
		if ($bankSlip === [] || (string) ($bankSlip['status'] ?? '') !== 'DRAFT')
			{
			$this->redirect('bank_slip');
			}

		$this->view('bank_slips/edit', [
			'title' => 'Editar boleto',
			'bankSlip' => $bankSlip,
			'bankAccounts' => $model->getBankAccountOptions(),
		]);
		}

	/**
	 * Updates an existing draft bank slip.
	 *
	 * @param mixed $id Bank slip identifier.
	 */
	public function update($id): void
		{
		$this->requirePermission('bank_slip.update');

		$model = new BankSlip();

		try
			{
			$model->update($id, $_POST);
			$this->redirect('bank_slip');
			}
		catch (\Throwable $e)
			{
			if ($this->wantsJson())
				{
				$this->json(['error' => $e->getMessage()], 422);
				}

			$bankSlip = $model->find((int) $id);
			if ($bankSlip === [] || (string) ($bankSlip['status'] ?? '') !== 'DRAFT')
				{
				$this->redirect('bank_slip');
				}

			http_response_code(422);
			$this->view('bank_slips/edit', [
				'title' => 'Editar boleto',
				'bankSlip' => array_merge($bankSlip, $_POST),
				'bankAccounts' => $model->getBankAccountOptions(),
				'formError' => $e->getMessage(),
			]);
			}
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

	/**
	 * Downloads a remittance file using the logical file name stored in metadata.
	 *
	 * @param mixed $id Bank file identifier.
	 */
	public function downloadRemittance($id): void
		{
		$this->requirePermission('bank_slip.generateRemittance');

		$bankFileModel = new BankFile();
		$bankFile = $bankFileModel->find((int) $id);
		if ($bankFile === [] || empty($bankFile['file_path']))
			{
			http_response_code(404);
			echo 'Arquivo de remessa não encontrado.';
			exit;
			}

		$fullPath = $this->publicPath((string) $bankFile['file_path']);
		if (!is_file($fullPath))
			{
			http_response_code(404);
			echo 'Arquivo de remessa não encontrado no armazenamento.';
			exit;
			}

		$downloadName = $bankFileModel->resolveDownloadFileName($bankFile);
		if ($downloadName === '')
			{
			$downloadName = basename($fullPath);
			}

		$content = (string) file_get_contents($fullPath);
		$this->downloadContent($downloadName, $content, 'text/plain; charset=ISO-8859-1');
		}

	/**
	 * Deletes an existing bank slip.
	 *
	 * @param mixed $id Bank slip identifier.
	 */
	public function delete($id): void
		{
		$this->requirePermission('bank_slip.delete');

		$model = new BankSlip();

		try
			{
			$model->delete($id);
			$this->redirect('bank_slip');
			}
		catch (\Throwable $e)
			{
			if ($this->wantsJson())
				{
				$this->json(['error' => $e->getMessage()], 422);
				}

			$bankAccountIds = isset($_GET['bank_account_ids']) && is_array($_GET['bank_account_ids']) ? $_GET['bank_account_ids'] : [];
			if ($bankAccountIds === [] && isset($_GET['bank_account_id']))
				{
				$bankAccountIds = [$_GET['bank_account_id']];
				}
			$bankAccountIds = array_values(array_unique(array_filter(array_map('intval', $bankAccountIds), static fn ($bankAccountId) => $bankAccountId > 0)));

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

			http_response_code(422);
			$this->view('bank_slips/index', [
				'title' => 'Boletos bancários',
				'bankSlips' => $bankSlips,
				'bankSlipGroups' => $bankSlipGroups,
				'filters' => $filters,
				'page' => $page,
				'totalPages' => $totalPages,
				'total' => $total,
				'bankAccounts' => $model->getBankAccountOptions(),
				'formError' => $e->getMessage(),
			]);
			}
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
		$slips = $ids !== []
			? $model->findReadyForRemittance($ids)
			: $model->findReadyForRemittanceByBankAccount($remittanceBankAccountId);
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
		$bankFileModel = new BankFile();
		$sequence = $bankFileModel->nextRemittanceSequence('C', date('Y-m-d'));

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

		$relativeDirectory = $this->remittanceRelativeDirectory();
		$directory = $this->publicPath($relativeDirectory);
		if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory))
			{
			http_response_code(500);
			echo 'Não foi possível preparar a pasta de remessas.';
			exit;
			}

		foreach ($jobs as $index => $job)
			{
			$downloadFileName = (string) $job['file_name'];
			$relativePath = $relativeDirectory . '/' . $downloadFileName;
			$fullPath = $directory . '/' . $downloadFileName;

			if (is_file($fullPath))
				{
				http_response_code(500);
				echo 'Já existe uma remessa com o mesmo nome no servidor.';
				exit;
				}

			$file = $bankFileModel->create([
				'bank_account_id' => $job['bank_account']['bank_account_id'] ?? null,
				'file_type' => 'REMITTANCE',
				'file_name' => $downloadFileName,
				'file_path' => $relativePath,
				'file_hash' => hash('sha256', $job['content']),
			]);
			$bankFileId = (int) ($file['bank_file_id'] ?? 0);
			if ($bankFileId <= 0)
				{
				http_response_code(500);
				echo 'Não foi possível registrar a remessa.';
				exit;
				}

			if (file_put_contents($fullPath, $job['content']) === false)
				{
				http_response_code(500);
				echo 'Não foi possível salvar a remessa.';
				exit;
				}

			$jobs[$index]['file_name'] = $downloadFileName;
			$jobs[$index]['full_path'] = $fullPath;

			$model->attachRemittanceFile(array_column($job['slips'], 'id'), $bankFileId);
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

	/**
	 * Returns the tenant-aware remittance upload directory.
	 */
	private function remittanceRelativeDirectory(): string
		{
		$databaseName = TenantContext::databaseName();
		if (!$databaseName)
			{
			throw new \RuntimeException('Tenant context is required to store remittance files.');
			}

		return 'tenant_files/' . $databaseName . '/remittance';
		}

	/**
	 * Resolves a public relative path to an absolute filesystem path.
	 */
	private function publicPath(string $relativePath): string
		{
		$publicPath = defined('PUBLIC_PATH') ? PUBLIC_PATH : dirname(__DIR__, 2) . '/public';
		return rtrim($publicPath, '/\\') . '/' . ltrim($relativePath, '/\\');
		}
	}
?>
