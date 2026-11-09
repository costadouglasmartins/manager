<?php

namespace App\Core;

use App\Models\HrCatalog;

/**
 * Shared flow of the HR catalog controllers (departments and positions).
 * Concrete controllers keep the public actions, check the ability on their first line and delegate here.
 **/
abstract class HrCatalogController extends TenantAwareController
	{
	/**
	 * Lists one catalog with filters and pagination (HTML or JSON).
	 * @param HrCatalog $catalog Catalog model.
	 * @return void
	 **/
	protected function catalogIndex(HrCatalog $catalog): void
		{
		$filters = $this->readCatalogFilters();
		$page = max(1, (int) ($_GET['page'] ?? 1));
		$perPage = 10;
		$total = $catalog->count($filters);
		$totalPages = max(1, (int) ceil($total / $perPage));
		$rows = $catalog->search($filters, $perPage, ($page - 1) * $perPage);
		$meta = $catalog->meta();

		if ($this->wantsJson())
			{
			$this->json([
				'filters'    => $filters,
				'page'       => $page,
				'perPage'    => $perPage,
				'total'      => $total,
				'totalPages' => $totalPages,
				'data'       => $rows,
			]);
			}

		$this->view('hr_catalogs/index', [
			'title'       => (string) $meta['list_title'],
			'catalogMeta' => $meta,
			'catalogRows' => $rows,
			'filters'     => $filters,
			'page'        => $page,
			'perPage'     => $perPage,
			'total'       => $total,
			'totalPages'  => $totalPages,
		]);
		}

	/**
	 * Renders the creation form.
	 * @param HrCatalog $catalog Catalog model.
	 * @return void
	 **/
	protected function catalogCreate(HrCatalog $catalog): void
		{
		$meta = $catalog->meta();
		$this->view('hr_catalogs/create', [
			'title'       => (string) $meta['create_title'],
			'catalogMeta' => $meta,
		]);
		}

	/**
	 * Persists a new catalog row.
	 * @param HrCatalog $catalog Catalog model.
	 * @return void
	 **/
	protected function catalogStore(HrCatalog $catalog): void
		{
		$this->requirePostRequest((string) $catalog->meta()['resource']);
		$meta = $catalog->meta();

		try
			{
			$catalog->create($_POST);
			$this->redirectWithFlash((string) $meta['resource'], 'success', (string) $meta['saved_message']);
			}
		catch (\InvalidArgumentException $e)
			{
			$this->renderFormError('hr_catalogs/create', $meta, null, $e->getMessage(), 422);
			}
		catch (\Throwable $e)
			{
			// Unexpected failures are logged without exposing internal details.
			error_log('HR catalog store failed [' . $meta['resource'] . ']: ' . $e->getMessage());
			$this->renderFormError('hr_catalogs/create', $meta, null, 'Não foi possível salvar o cadastro agora.', 500);
			}
		}

	/**
	 * Renders the edit form.
	 * @param HrCatalog $catalog Catalog model.
	 * @param mixed $id Record identifier from the route.
	 * @return void
	 **/
	protected function catalogEdit(HrCatalog $catalog, $id): void
		{
		$meta = $catalog->meta();
		$row = $catalog->find($id);
		if (!$row)
			{
			$this->redirectWithFlash((string) $meta['resource'], 'warning', (string) $meta['not_found_message']);
			}

		$this->view('hr_catalogs/edit', [
			'title'       => (string) $meta['edit_title'],
			'catalogMeta' => $meta,
			'catalog'     => $row,
		]);
		}

	/**
	 * Updates a catalog row.
	 * @param HrCatalog $catalog Catalog model.
	 * @param mixed $id Record identifier from the route.
	 * @return void
	 **/
	protected function catalogUpdate(HrCatalog $catalog, $id): void
		{
		$this->requirePostRequest((string) $catalog->meta()['resource']);
		$meta = $catalog->meta();

		try
			{
			$catalog->update($id, $_POST);
			$this->redirectWithFlash((string) $meta['resource'], 'success', (string) $meta['updated_message']);
			}
		catch (\InvalidArgumentException $e)
			{
			$this->renderFormError('hr_catalogs/edit', $meta, $catalog->find($id) ?: ['id' => (int) $id], $e->getMessage(), 422);
			}
		catch (\Throwable $e)
			{
			// Unexpected failures are logged without exposing internal details.
			error_log('HR catalog update failed [' . $meta['resource'] . ']: ' . $e->getMessage());
			if ($this->wantsJson())
				{
				$this->json(['error' => 'Não foi possível atualizar o cadastro agora.'], 500);
				}

			$this->redirectWithFlash((string) $meta['resource'], 'danger', 'Não foi possível atualizar o cadastro agora.');
			}
		}

	/**
	 * Displays one catalog row.
	 * @param HrCatalog $catalog Catalog model.
	 * @param mixed $id Record identifier from the route.
	 * @return void
	 **/
	protected function catalogInformations(HrCatalog $catalog, $id): void
		{
		$meta = $catalog->meta();
		$row = $catalog->find($id);
		if (!$row)
			{
			$this->redirectWithFlash((string) $meta['resource'], 'warning', (string) $meta['not_found_message']);
			}

		$this->view('hr_catalogs/informations', [
			'title'       => (string) $meta['information_title'],
			'catalogMeta' => $meta,
			'catalog'     => $row,
		]);
		}

	/**
	 * Deletes a catalog row; rows used by employees are refused with an explanation.
	 * @param HrCatalog $catalog Catalog model.
	 * @param mixed $id Record identifier from the route.
	 * @return void
	 **/
	protected function catalogDelete(HrCatalog $catalog, $id): void
		{
		$this->requirePostRequest((string) $catalog->meta()['resource']);
		$meta = $catalog->meta();

		try
			{
			$catalog->delete($id);
			if ($this->wantsJson())
				{
				$this->json(['success' => true, 'message' => (string) $meta['deleted_message']]);
				}

			$this->redirectWithFlash((string) $meta['resource'], 'success', (string) $meta['deleted_message']);
			}
		catch (\InvalidArgumentException $e)
			{
			// Business rule violations (not found, in use) are reported in the format the caller expects.
			if ($this->wantsJson())
				{
				$this->json(['error' => $e->getMessage()], 422);
				}

			$this->redirectWithFlash((string) $meta['resource'], 'danger', $e->getMessage());
			}
		catch (\Throwable $e)
			{
			// Unexpected failures are logged without exposing internal details.
			error_log('HR catalog delete failed [' . $meta['resource'] . ']: ' . $e->getMessage());
			if ($this->wantsJson())
				{
				$this->json(['error' => 'Não foi possível excluir o cadastro agora.'], 500);
				}

			$this->redirectWithFlash((string) $meta['resource'], 'danger', 'Não foi possível excluir o cadastro agora.');
			}
		}

	/**
	 * Renders a create/edit form again with the submitted values, or answers JSON clients.
	 * @param string $view View name.
	 * @param array $meta Catalog metadata.
	 * @param array|null $row Stored row when editing, or null when creating.
	 * @param string $message Error message for the user.
	 * @param int $status HTTP status code.
	 * @return void
	 **/
	protected function renderFormError(string $view, array $meta, ?array $row, string $message, int $status): void
		{
		if ($this->wantsJson())
			{
			$this->json(['error' => $message], $status);
			}

		$data = [
			'title'       => (string) ($row === null ? $meta['create_title'] : $meta['edit_title']),
			'catalogMeta' => $meta,
			'formData'    => $_POST,
			'formError'   => $message,
		];
		if ($row !== null)
			{
			$data['catalog'] = $row;
			}

		http_response_code($status);
		$this->view($view, $data);
		}

	/**
	 * Reads the catalog list filters from the query string, ignoring array values.
	 * @return array<string, string> Normalized filters.
	 **/
	protected function readCatalogFilters(): array
		{
		$read = static function (string $key): string
			{
			$value = $_GET[$key] ?? '';

			return is_scalar($value) ? trim((string) $value) : '';
			};

		return [
			'id'     => $read('id'),
			'name'   => $read('name'),
			'active' => $read('active'),
		];
		}

	/**
	 * Accepts write actions only through POST so links and prefetches cannot change data.
	 * @param string $resource Route resource used to redirect browsers.
	 * @return void
	 **/
	protected function requirePostRequest(string $resource): void
		{
		if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST')
			{
			return;
			}

		if ($this->wantsJson())
			{
			$this->json(['error' => 'Método não permitido.'], 405, ['Allow' => 'POST']);
			}

		$this->redirectWithFlash($resource, 'danger', 'Método não permitido.');
		}
	}
?>
