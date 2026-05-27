<?php

namespace App\Controllers;

use App\Core\TenantAwareController;
use App\Models\BankFile;

class BankFileController extends TenantAwareController
	{
	/**
	 * Lists remittance files with filters and pagination.
	 */
	public function index(): void
		{
		$this->requirePermission('bank_file.index');

		$model = new BankFile();
		$filters = [
			'id' => isset($_GET['id']) ? trim($_GET['id']) : '',
			'file_type' => isset($_GET['file_type']) ? trim($_GET['file_type']) : 'REMITTANCE',
			'file_name' => isset($_GET['file_name']) ? trim($_GET['file_name']) : '',
			'bank_account_id' => isset($_GET['bank_account_id']) ? trim($_GET['bank_account_id']) : '',
		];

		$page = max(1, (int) ($_GET['page'] ?? 1));
		$perPage = 10;
		$offset = ($page - 1) * $perPage;

		$bankFiles = $model->search($filters, $perPage, $offset);
		$total = $model->count($filters);
		$totalPages = max(1, (int) ceil($total / $perPage));

		if ($this->wantsJson())
			{
			$this->json([
				'filters' => $filters,
				'page' => $page,
				'perPage' => $perPage,
				'total' => $total,
				'totalPages' => $totalPages,
				'data' => $bankFiles,
			]);
			}

		$this->view('bank_files/index', [
			'title' => 'Remessas',
			'bankFiles' => $bankFiles,
			'filters' => $filters,
			'page' => $page,
			'totalPages' => $totalPages,
			'total' => $total,
		]);
		}

	/**
	 * Displays remittance details.
	 *
	 * @param mixed $id Remittance identifier.
	 */
	public function informations($id): void
		{
		$this->requirePermission('bank_file.informations');

		$model = new BankFile();
		$bankFile = $model->find((int) $id);
		if ($bankFile === [])
			{
			$this->redirect('bank_file');
			}

		$fileContent = '';
		$filePath = trim((string) ($bankFile['file_path'] ?? ''));
		if ($filePath !== '')
			{
			$absolutePath = $this->publicPath($filePath);
			if (is_file($absolutePath))
				{
				$fileContent = (string) file_get_contents($absolutePath);
				}
			}

		$this->view('bank_files/informations', [
			'title' => 'Informações da remessa',
			'bankFile' => $bankFile,
			'fileContent' => $fileContent,
		]);
		}

	/**
	 * Deletes a remittance and all linked bank slips.
	 *
	 * @param mixed $id Remittance identifier.
	 */
	public function delete($id): void
		{
		$this->requirePermission('bank_file.delete');

		$model = new BankFile();
		$bankFile = $model->find((int) $id);
		if ($bankFile === [])
			{
			$this->redirect('bank_file');
			}

		$filePath = trim((string) ($bankFile['file_path'] ?? ''));
		$absolutePath = $filePath !== '' ? $this->publicPath($filePath) : '';

		try
			{
			if ($absolutePath !== '' && is_file($absolutePath))
				{
				if (!@unlink($absolutePath))
					{
					throw new \RuntimeException('Não foi possível remover o arquivo da remessa no servidor.');
					}
				}

			$model->deleteRemittanceWithBankSlips((int) $id);

			$this->redirect('bank_file');
			}
		catch (\Throwable $e)
			{
			if ($this->wantsJson())
				{
				$this->json(['error' => $e->getMessage()], 422);
				}

			$filters = [
				'id' => isset($_GET['id']) ? trim($_GET['id']) : '',
				'file_type' => isset($_GET['file_type']) ? trim($_GET['file_type']) : 'REMITTANCE',
				'file_name' => isset($_GET['file_name']) ? trim($_GET['file_name']) : '',
				'bank_account_id' => isset($_GET['bank_account_id']) ? trim($_GET['bank_account_id']) : '',
			];
			$page = max(1, (int) ($_GET['page'] ?? 1));
			$perPage = 10;
			$offset = ($page - 1) * $perPage;
			$bankFiles = $model->search($filters, $perPage, $offset);
			$total = $model->count($filters);
			$totalPages = max(1, (int) ceil($total / $perPage));

			http_response_code(422);
			$this->view('bank_files/index', [
				'title' => 'Remessas',
				'bankFiles' => $bankFiles,
				'filters' => $filters,
				'page' => $page,
				'totalPages' => $totalPages,
				'total' => $total,
				'formError' => $e->getMessage(),
			]);
			}
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
