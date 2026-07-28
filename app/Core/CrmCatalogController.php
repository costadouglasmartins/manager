<?php

namespace App\Core;

use App\Models\CrmCatalog;

abstract class CrmCatalogController extends TenantAwareController
	{
	/**
	 * Lists one tenant-scoped CRM catalog.
	 **/
	protected function catalogIndex(string $catalogKey, string $ability): void
		{
		$this->requirePermission($ability);

		$catalog = new CrmCatalog($catalogKey);
		$filters = $this->readCatalogFilters();
		$page = max(1, (int) ($_GET['page'] ?? 1));
		$perPage = 10;
		$offset = ($page - 1) * $perPage;
		$rows = $catalog->search($filters, $perPage, $offset);
		$total = $catalog->count($filters);
		$totalPages = max(1, (int) ceil($total / $perPage));
		$meta = $catalog->meta();

		if ($this->wantsJson())
			{
			$this->json([
				'filters' => $filters,
				'page' => $page,
				'perPage' => $perPage,
				'total' => $total,
				'totalPages' => $totalPages,
				'data' => $rows,
			]);
			}

		$this->view('crm_catalogs/index', [
			'title' => (string) $meta['list_title'],
			'catalogMeta' => $meta,
			'catalogRows' => $rows,
			'filters' => $filters,
			'page' => $page,
			'totalPages' => $totalPages,
			'total' => $total,
		]);
		}

	/**
	 * Renders one CRM catalog creation form.
	 **/
	protected function catalogCreate(string $catalogKey, string $ability): void
		{
		$this->requirePermission($ability);

		$catalog = new CrmCatalog($catalogKey);
		$meta = $catalog->meta();
		$this->view('crm_catalogs/create', [
			'title' => (string) $meta['create_title'],
			'catalogMeta' => $meta,
		]);
		}

	/**
	 * Persists one CRM catalog record.
	 **/
	protected function catalogStore(string $catalogKey, string $ability): void
		{
		$this->requirePermission($ability);
		$this->requirePostRequest();

		$catalog = new CrmCatalog($catalogKey);
		$meta = $catalog->meta();

		try
			{
			$result = $catalog->create($_POST, $this->currentManagerUserId());
			$this->redirect((string) $meta['resource'] . '/informations/' . (int) ($result['id'] ?? 0));
			}
		catch (\InvalidArgumentException $e)
			{
			$this->renderCatalogError('crm_catalogs/create', $meta, $_POST, $e->getMessage(), 422);
			}
		catch (\Throwable $e)
			{
			error_log('CRM CATALOG STORE ERROR [' . $catalogKey . ']: ' . $e->getMessage());
			$this->renderCatalogError(
				'crm_catalogs/create',
				$meta,
				$_POST,
				'Não foi possível salvar o cadastro CRM agora.',
				500
			);
			}
		}

	/**
	 * Renders one CRM catalog edit form.
	 *
	 * @param mixed $id Catalog record identifier.
	 **/
	protected function catalogEdit(string $catalogKey, string $ability, $id): void
		{
		$this->requirePermission($ability);

		$catalogModel = new CrmCatalog($catalogKey);
		$catalog = $catalogModel->find($id);
		$meta = $catalogModel->meta();
		if (!$catalog)
			{
			$this->redirect((string) $meta['resource']);
			}

		$this->view('crm_catalogs/edit', [
			'title' => (string) $meta['edit_title'],
			'catalogMeta' => $meta,
			'catalog' => $catalog,
		]);
		}

	/**
	 * Updates one CRM catalog record.
	 *
	 * @param mixed $id Catalog record identifier.
	 **/
	protected function catalogUpdate(string $catalogKey, string $ability, $id): void
		{
		$this->requirePermission($ability);
		$this->requirePostRequest();

		$catalogModel = new CrmCatalog($catalogKey);
		$meta = $catalogModel->meta();

		try
			{
			$catalogModel->update($id, $_POST, $this->currentManagerUserId());
			$this->redirect((string) $meta['resource'] . '/informations/' . (int) $id);
			}
		catch (\InvalidArgumentException $e)
			{
			$catalog = $catalogModel->find($id);
			if ($this->wantsJson())
				{
				$this->json(['error' => $e->getMessage()], 422);
				}

			http_response_code(422);
			$this->view('crm_catalogs/edit', [
				'title' => (string) $meta['edit_title'],
				'catalogMeta' => $meta,
				'catalog' => $catalog ?: ['id' => (int) $id],
				'formData' => $_POST,
				'formError' => $e->getMessage(),
			]);
			}
		catch (\Throwable $e)
			{
			error_log('CRM CATALOG UPDATE ERROR [' . $catalogKey . ']: ' . $e->getMessage());
			if ($this->wantsJson())
				{
				$this->json(['error' => 'Não foi possível atualizar o cadastro CRM agora.'], 500);
				}

			$this->redirect((string) $meta['resource']);
			}
		}

	/**
	 * Displays one CRM catalog record.
	 *
	 * @param mixed $id Catalog record identifier.
	 **/
	protected function catalogInformations(string $catalogKey, string $ability, $id): void
		{
		$this->requirePermission($ability);

		$catalogModel = new CrmCatalog($catalogKey);
		$catalog = $catalogModel->find($id);
		$meta = $catalogModel->meta();
		if (!$catalog)
			{
			$this->redirect((string) $meta['resource']);
			}

		$this->view('crm_catalogs/informations', [
			'title' => (string) $meta['information_title'],
			'catalogMeta' => $meta,
			'catalog' => $catalog,
		]);
		}

	/**
	 * Deactivates one CRM catalog record.
	 *
	 * @param mixed $id Catalog record identifier.
	 **/
	protected function catalogDelete(string $catalogKey, string $ability, $id): void
		{
		$this->requirePermission($ability);
		$this->requirePostRequest();

		$catalogModel = new CrmCatalog($catalogKey);
		$meta = $catalogModel->meta();

		try
			{
			$catalogModel->delete($id, $this->currentManagerUserId());
			}
		catch (\InvalidArgumentException $e)
			{
			if ($this->wantsJson())
				{
				$this->json(['error' => $e->getMessage()], 422);
				}
			}
		catch (\Throwable $e)
			{
			error_log('CRM CATALOG DELETE ERROR [' . $catalogKey . ']: ' . $e->getMessage());
			if ($this->wantsJson())
				{
				$this->json(['error' => 'Não foi possível desativar o cadastro CRM agora.'], 500);
				}
			}

		$this->redirect((string) $meta['resource']);
		}

	/**
	 * Renders a catalog creation error for HTML or JSON clients.
	 *
	 * @param array<string, mixed> $meta Catalog metadata.
	 * @param array<string, mixed> $formData Submitted values.
	 **/
	protected function renderCatalogError(
		string $view,
		array $meta,
		array $formData,
		string $message,
		int $status
	): void
		{
		if ($this->wantsJson())
			{
			$this->json(['error' => $message], $status);
			}

		http_response_code($status);
		$this->view($view, [
			'title' => (string) $meta['create_title'],
			'catalogMeta' => $meta,
			'formData' => $formData,
			'formError' => $message,
		]);
		}

	/**
	 * Reads the shared catalog filters from the query string.
	 *
	 * @return array<string, string>
	 **/
	protected function readCatalogFilters(): array
		{
		return [
			'id' => $this->catalogQueryValue('id'),
			'name' => $this->catalogQueryValue('name'),
			'slug' => $this->catalogQueryValue('slug'),
			'bootstrap_class' => $this->catalogQueryValue('bootstrap_class'),
			'active' => $this->catalogQueryValue('active'),
			'is_won' => $this->catalogQueryValue('is_won'),
			'is_lost' => $this->catalogQueryValue('is_lost'),
		];
		}

	/**
	 * Returns one scalar query parameter without accepting array structures.
	 **/
	protected function catalogQueryValue(string $key): string
		{
		$value = $_GET[$key] ?? '';
		if (is_array($value) || is_object($value))
			{
			return '';
			}

		return trim((string) ($value ?? ''));
		}

	/**
	 * Returns the authenticated manager user id.
	 **/
	protected function currentManagerUserId(): int
		{
		return (int) ($_SESSION['manager']['user']['id'] ?? 0);
		}

	/**
	 * Restricts mutating catalog actions to POST requests.
	 **/
	protected function requirePostRequest(): void
		{
		if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST')
			{
			return;
			}

		if ($this->wantsJson())
			{
			$this->json(['error' => 'method_not_allowed'], 405);
			}

		http_response_code(405);
		echo 'Método não permitido.';
		exit;
		}
	}
?>
