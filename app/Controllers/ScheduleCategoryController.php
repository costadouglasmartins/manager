<?php

namespace App\Controllers;

use App\Core\TenantAwareController;
use App\Models\ScheduleCategory;

class ScheduleCategoryController extends TenantAwareController
	{
	/**
	 * Reads schedule category list filters from the current request.
	 **/
	private function readFilters(): array
		{
		return [
			'id'     => isset($_GET['id']) ? trim((string) $_GET['id']) : '',
			'name'   => isset($_GET['name']) ? trim((string) $_GET['name']) : '',
			'active' => isset($_GET['active']) ? trim((string) $_GET['active']) : '',
		];
		}

	/**
	 * Lists schedule categories with filters and pagination.
	 **/
	public function index(): void
		{
		$this->requirePermission('schedule_category.index');

		$scheduleCategory = new ScheduleCategory();
		$filters = $this->readFilters();

		$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
		if ($page < 1)
			{
			$page = 1;
			}

		$perPage = 10;
		$offset = ($page - 1) * $perPage;
		$categories = $scheduleCategory->search($filters, $perPage, $offset);
		$total = $scheduleCategory->count($filters);
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
				'data'       => $categories,
			]);
			}

		$this->view('schedule_categories/index', [
			'categories' => $categories,
			'title'      => 'Categorias da agenda',
			'filters'    => $filters,
			'page'       => $page,
			'totalPages' => $totalPages,
			'total'      => $total,
		]);
		}

	/**
	 * Downloads all schedule category records matching the current filters as PDF.
	 **/
	public function exportPdf(): void
		{
		parent::exportPdf();
		}

	/**
	 * Downloads all schedule category records matching the current filters as XLSX.
	 **/
	public function exportExcel(): void
		{
		parent::exportExcel();
		}

	/**
	 * Renders the schedule category creation form.
	 **/
	public function create(): void
		{
		$this->requirePermission('schedule_category.create');
		$this->view('schedule_categories/create', ['title' => 'Nova categoria da agenda']);
		}

	/**
	 * Persists a new schedule category.
	 **/
	public function store(): void
		{
		$this->requirePermission('schedule_category.store');

			$scheduleCategory = new ScheduleCategory();
			try
				{
				$scheduleCategory->create($_POST);
				$this->redirectWithFlash('schedule_category', 'success', 'Categoria de agenda salva com sucesso.');
				}
		catch (\Throwable $e)
			{
			http_response_code(422);
			$this->view('schedule_categories/create', [
				'title' => 'Nova categoria da agenda',
				'formData' => $_POST,
				'formError' => $e->getMessage(),
			]);
			}
		}

	/**
	 * Renders the schedule category edit form.
	 *
	 * @param mixed $id Category identifier.
	 **/
	public function edit($id): void
		{
		$this->requirePermission('schedule_category.edit');

		$scheduleCategory = new ScheduleCategory();
		$category = $scheduleCategory->find($id);
		if (!$category)
			{
			$this->redirectWithFlash('schedule_category', 'warning', 'Categoria da agenda não encontrada.');
			}

		$this->view('schedule_categories/edit', [
			'title' => 'Editar categoria da agenda',
			'category' => $category,
		]);
		}

	/**
	 * Updates an existing schedule category.
	 *
	 * @param mixed $id Category identifier.
	 **/
	public function update($id): void
		{
		$this->requirePermission('schedule_category.update');

		$scheduleCategory = new ScheduleCategory();
			try
				{
				$scheduleCategory->update($id, $_POST);
				$this->redirectWithFlash('schedule_category', 'success', 'Categoria de agenda atualizada com sucesso.');
				}
		catch (\Throwable $e)
			{
			$category = $scheduleCategory->find($id);
			http_response_code(422);
			$this->view('schedule_categories/edit', [
				'title' => 'Editar categoria da agenda',
				'category' => $category ?: [],
				'formData' => $_POST,
				'formError' => $e->getMessage(),
			]);
			}
		}

	/**
	 * Displays schedule category details.
	 *
	 * @param mixed $id Category identifier.
	 **/
	public function informations($id): void
		{
		$this->requirePermission('schedule_category.informations');

		$scheduleCategory = new ScheduleCategory();
		$data = $scheduleCategory->findAllByScheduleCategoryId($id);
		if (empty($data['schedule_category']))
			{
			$this->redirectWithFlash('schedule_category', 'warning', 'Categoria da agenda não encontrada.');
			}

		$this->view('schedule_categories/informations', [
			'title' => 'Informações da categoria da agenda',
			'category' => $data['schedule_category'],
		]);
		}

	/**
	 * Deletes an existing schedule category.
	 *
	 * @param mixed $id Category identifier.
	 **/
	public function delete($id): void
		{
		$this->requirePermission('schedule_category.delete');

		$scheduleCategory = new ScheduleCategory();

			try
				{
				$scheduleCategory->delete($id);
				$this->redirectWithFlash('schedule_category', 'success', 'Categoria de agenda excluída com sucesso.');
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
			$categories = $scheduleCategory->search($filters, $perPage, $offset);
			$total = $scheduleCategory->count($filters);
			$totalPages = (int) ceil($total / $perPage);
			if ($totalPages < 1)
				{
				$totalPages = 1;
				}

			http_response_code(422);
			$this->view('schedule_categories/index', [
				'categories' => $categories,
				'title'      => 'Categorias da agenda',
				'filters'    => $filters,
				'page'       => $page,
				'totalPages' => $totalPages,
				'total'      => $total,
				'formError'  => $e->getMessage(),
			]);
			}
		}
	}
?>
