<?php

namespace App\Controllers;

use App\Core\TenantAwareController;
use App\Models\TaskCategory;

class TaskCategoryController extends TenantAwareController
	{
	/**
	 * Reads task category list filters from the current request.
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
	 * Lists task categories with filters and pagination.
	 **/
	public function index(): void
		{
		$this->requirePermission('task_category.index');

		$taskCategory = new TaskCategory();
		$filters = $this->readFilters();

		$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
		if ($page < 1)
			{
			$page = 1;
			}

		$perPage = 10;
		$offset = ($page - 1) * $perPage;
		$categories = $taskCategory->search($filters, $perPage, $offset);
		$total = $taskCategory->count($filters);
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

		$this->view('task_categories/index', [
			'categories' => $categories,
			'title'      => 'Categorias de tarefas',
			'filters'    => $filters,
			'page'       => $page,
			'totalPages' => $totalPages,
			'total'      => $total,
		]);
		}

	/**
	 * Downloads all task category records matching the current filters as PDF.
	 **/
	public function exportPdf(): void
		{
		parent::exportPdf();
		}

	/**
	 * Downloads all task category records matching the current filters as XLSX.
	 **/
	public function exportExcel(): void
		{
		parent::exportExcel();
		}

	/**
	 * Renders the task category creation form.
	 **/
	public function create(): void
		{
		$this->requirePermission('task_category.create');
		$this->view('task_categories/create', ['title' => 'Nova categoria de tarefa']);
		}

	/**
	 * Persists a new task category.
	 **/
	public function store(): void
		{
		$this->requirePermission('task_category.store');

		$taskCategory = new TaskCategory();
		try
			{
			$taskCategory->create($_POST);
			$this->redirect('task_category');
			}
		catch (\Throwable $e)
			{
			http_response_code(422);
			$this->view('task_categories/create', [
				'title' => 'Nova categoria de tarefa',
				'formData' => $_POST,
				'formError' => $e->getMessage(),
			]);
			}
		}

	/**
	 * Renders the task category edit form.
	 * @param mixed $id Category identifier.
	 **/
	public function edit($id): void
		{
		$this->requirePermission('task_category.edit');

		$taskCategory = new TaskCategory();
		$category = $taskCategory->find($id);
		if (!$category)
			{
			$this->redirect('task_category');
			}

		$this->view('task_categories/edit', [
			'title' => 'Editar categoria de tarefa',
			'category' => $category,
		]);
		}

	/**
	 * Updates an existing task category.
	 * @param mixed $id Category identifier.
	 **/
	public function update($id): void
		{
		$this->requirePermission('task_category.update');

		$taskCategory = new TaskCategory();
		try
			{
			$taskCategory->update($id, $_POST);
			$this->redirect('task_category');
			}
		catch (\Throwable $e)
			{
			$category = $taskCategory->find($id);
			http_response_code(422);
			$this->view('task_categories/edit', [
				'title' => 'Editar categoria de tarefa',
				'category' => $category ?: [],
				'formData' => $_POST,
				'formError' => $e->getMessage(),
			]);
			}
		}

	/**
	 * Displays task category details.
	 * @param mixed $id Category identifier.
	 **/
	public function informations($id): void
		{
		$this->requirePermission('task_category.informations');

		$taskCategory = new TaskCategory();
		$data = $taskCategory->findAllByTaskCategoryId($id);
		if (empty($data['task_category']))
			{
			$this->redirect('task_category');
			}

		$this->view('task_categories/informations', [
			'title' => 'Informações da categoria de tarefa',
			'category' => $data['task_category'],
		]);
		}

	/**
	 * Converts an active filter value to a label.
	 **/
	private function activeLabel(string $active): string
		{
		if ($active === '1')
			{
			return 'Ativo';
			}

		if ($active === '0')
			{
			return 'Inativo';
			}

		return '';
		}

	}
?>
