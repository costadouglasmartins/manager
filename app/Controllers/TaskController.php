<?php

namespace App\Controllers;

use App\Core\Authorization;
use App\Core\TenantAwareController;
use App\Core\TenantContext;
use App\Models\Task;
use App\Models\TaskCategory;

class TaskController extends TenantAwareController
	{
	/**
	 * Returns the authenticated manager user id.
	 **/
	private function currentUserId(): int
		{
		return (int) ($_SESSION['manager']['user']['id'] ?? 0);
		}

	/**
	 * Returns the active tenant id from session context.
	 **/
	private function currentTenantId(): int
		{
		$tenant = TenantContext::tenant();
		return (int) ($tenant['id'] ?? 0);
		}

	/**
	 * Reads task list filters from the current request.
	 **/
	private function readFilters(): array
		{
		return [
			'id'                 => isset($_GET['id']) ? trim((string) $_GET['id']) : '',
			'task_category_id'   => isset($_GET['task_category_id']) ? trim((string) $_GET['task_category_id']) : '',
			'status'             => isset($_GET['status']) ? trim((string) $_GET['status']) : '',
			'assignee_person_id' => isset($_GET['assignee_person_id']) ? trim((string) $_GET['assignee_person_id']) : '',
			'title'              => isset($_GET['title']) ? trim((string) $_GET['title']) : '',
			'description'        => isset($_GET['description']) ? trim((string) $_GET['description']) : '',
			'third_party_name'   => isset($_GET['third_party_name']) ? trim((string) $_GET['third_party_name']) : '',
			'created_from'       => isset($_GET['created_from']) ? trim((string) $_GET['created_from']) : '',
			'created_to'         => isset($_GET['created_to']) ? trim((string) $_GET['created_to']) : '',
		];
		}

	/**
	 * Reads and normalizes task ordering inputs.
	 **/
	private function readOrder(): array
		{
		$sort = isset($_GET['sort']) ? trim((string) $_GET['sort']) : 'created';
		$direction = isset($_GET['direction']) ? trim((string) $_GET['direction']) : 'desc';

		return [
			'sort' => $sort,
			'direction' => strtolower($direction) === 'asc' ? 'asc' : 'desc',
		];
		}

	/**
	 * Lists tasks with filters, ordering and pagination.
	 **/
	public function index(): void
		{
		$this->requirePermission('task.index');

		$task = new Task();
		$category = new TaskCategory();
		$filters = $this->readFilters();
		$order = $this->readOrder();

		$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
		if ($page < 1)
			{
			$page = 1;
			}

		$perPage = 10;
		$offset = ($page - 1) * $perPage;
		$tasks = $task->search($filters, $perPage, $offset, $order['sort'], $order['direction']);
		$total = $task->count($filters);
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
				'data'       => $tasks,
			]);
			}

		$this->view('tasks/index', [
			'tasks'       => $tasks,
			'categories'  => $category->getOptions(false),
			'people'      => $task->getPersonOptions($this->currentTenantId()),
			'statuses'    => Task::statuses(),
			'title'       => 'Tarefas',
			'filters'     => $filters,
			'sort'        => $order['sort'],
			'direction'   => $order['direction'],
			'page'        => $page,
			'totalPages'  => $totalPages,
			'total'       => $total,
		]);
		}

	/**
	 * Downloads all task records matching the current list filters as PDF.
	 **/
	public function exportPdf(): void
		{
		parent::exportPdf();
		}

	/**
	 * Downloads all task records matching the current list filters as XLSX.
	 **/
	public function exportExcel(): void
		{
		parent::exportExcel();
		}

	/**
	 * Renders the task creation form.
	 **/
	public function create(): void
		{
		$this->requirePermission('task.create');

		$task = new Task();
		$category = new TaskCategory();
		$this->view('tasks/create', [
			'title'      => 'Nova tarefa',
			'categories' => $category->getOptions(),
			'people'     => $task->getPersonOptions($this->currentTenantId()),
			'statuses'   => Task::statuses(),
		]);
		}

	/**
	 * Persists a new task.
	 **/
	public function store(): void
		{
		$this->requirePermission('task.store');

		$task = new Task();
		$category = new TaskCategory();

		try
			{
			$task->create($_POST, $this->currentUserId(), $this->currentTenantId());
			$this->redirectWithFlash('task', 'success', 'Tarefa salva com sucesso.');
			}
		catch (\Throwable $e)
			{
			if ($this->wantsJson())
				{
				$this->json(['error' => $e->getMessage()], 422);
				}

			http_response_code(422);
			$this->view('tasks/create', [
				'title'      => 'Nova tarefa',
				'categories' => $category->getOptions(),
				'people'     => $task->getPersonOptions($this->currentTenantId()),
				'statuses'   => Task::statuses(),
				'formData'   => $_POST,
				'formError'  => $e->getMessage(),
			]);
			}
		}

	/**
	 * Renders the task edit form.
	 * @param mixed $id Task identifier.
	 **/
	public function edit($id): void
		{
		$this->requirePermission('task.edit');

		$task = new Task();
		$data = $task->findAllByTaskId($id);
		if (empty($data['task']))
			{
			$this->redirectWithFlash('task', 'warning', 'Tarefa não encontrada.');
			}

		$category = new TaskCategory();
		$this->view('tasks/edit', [
			'title'      => 'Editar tarefa',
			'task'       => $data['task'],
			'assignees'  => $data['assignees'],
			'categories' => $category->getOptions(false),
			'people'     => $task->getPersonOptions($this->currentTenantId()),
			'statuses'   => Task::statuses(),
		]);
		}

	/**
	 * Updates an existing task.
	 * @param mixed $id Task identifier.
	 **/
	public function update($id): void
		{
		$this->requirePermission('task.update');

		$task = new Task();
		$category = new TaskCategory();

		try
			{
			$task->update($id, $_POST, $this->currentTenantId());
			$this->redirectWithFlash('task', 'success', 'Tarefa atualizada com sucesso.');
			}
		catch (\Throwable $e)
			{
			if ($this->wantsJson())
				{
				$this->json(['error' => $e->getMessage()], 422);
				}

			$data = $task->findAllByTaskId($id);
			http_response_code(422);
			$this->view('tasks/edit', [
				'title'      => 'Editar tarefa',
				'task'       => $data['task'] ?? [],
				'assignees'  => $data['assignees'] ?? [],
				'categories' => $category->getOptions(false),
				'people'     => $task->getPersonOptions($this->currentTenantId()),
				'statuses'   => Task::statuses(),
				'formData'   => $_POST,
				'formError'  => $e->getMessage(),
			]);
			}
		}

	/**
	 * Deletes an existing task and its associations.
	 * @param mixed $id Task identifier.
	 **/
	public function delete($id): void
		{
		$this->requirePermission('task.delete');

		$task = new Task();
		$category = new TaskCategory();

		try
			{
			$task->delete($id);
			$this->redirectWithFlash('task', 'success', 'Tarefa excluída com sucesso.');
			}
		catch (\Throwable $e)
			{
			if ($this->wantsJson())
				{
				$this->json(['error' => $e->getMessage()], 422);
				}

			$filters = $this->readFilters();
			$order = $this->readOrder();
			$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
			if ($page < 1)
				{
				$page = 1;
				}

			$perPage = 10;
			$offset = ($page - 1) * $perPage;
			$tasks = $task->search($filters, $perPage, $offset, $order['sort'], $order['direction']);
			$total = $task->count($filters);
			$totalPages = (int) ceil($total / $perPage);
			if ($totalPages < 1)
				{
				$totalPages = 1;
				}

			http_response_code(422);
			$this->view('tasks/index', [
				'tasks'      => $tasks,
				'categories' => $category->getOptions(false),
				'people'     => $task->getPersonOptions($this->currentTenantId()),
				'statuses'   => Task::statuses(),
				'title'      => 'Tarefas',
				'filters'    => $filters,
				'sort'       => $order['sort'],
				'direction'  => $order['direction'],
				'page'       => $page,
				'totalPages' => $totalPages,
				'total'      => $total,
				'formError'  => $e->getMessage(),
			]);
			}
		}

	/**
	 * Displays task details, execution history and comments.
	 * @param mixed $id Task identifier.
	 **/
	public function informations($id): void
		{
		$this->requirePermission('task.informations');

		$task = new Task();
		$data = $task->findAllByTaskId($id);
		if (empty($data['task']))
			{
			$this->redirectWithFlash('task', 'warning', 'Tarefa não encontrada.');
			}

		$this->view('tasks/informations', [
			'title'      => 'Informações da tarefa',
			'task'       => $data['task'],
			'assignees'  => $data['assignees'],
			'executions' => $data['executions'],
			'comments'   => $data['comments'],
			'statuses'   => Task::statuses(),
		]);
		}

	/**
	 * Persists a task comment.
	 * @param mixed $id Task identifier.
	 **/
	public function commentStore($id): void
		{
		$this->requirePermission('task.commentStore');

		$task = new Task();
		try
			{
			$task->addComment((int) $id, $this->currentUserId(), (string) ($_POST['comment'] ?? ''));
			$this->redirectWithFlash('task/informations/' . (int) $id, 'success', 'Comentário salvo com sucesso.');
			}
		catch (\Throwable $e)
			{
			if ($this->wantsJson())
				{
				$this->json(['error' => $e->getMessage()], 422);
				}

			$this->redirectWithFlash(
				'task/informations/' . (int) $id,
				'danger',
				'Não foi possível salvar o comentário agora.'
			);
			}
		}

	/**
	 * Starts a task execution interval for one assignee.
	 **/
	public function startExecution(): void
		{
		$this->requirePermission('task.startExecution');

		$taskAssigneeId = (int) ($_POST['task_assignee_id'] ?? 0);
		$task = new Task();

		try
			{
			$this->json([
				'success' => true,
				'data' => $task->startExecution($taskAssigneeId, $this->currentUserId()),
			]);
			}
		catch (\Throwable $e)
			{
			$this->json([
				'success' => false,
				'error' => $e->getMessage(),
			], 422);
			}
		}

	/**
	 * Pauses the current task execution interval for one assignee.
	 **/
	public function pauseExecution(): void
		{
		$this->requirePermission('task.pauseExecution');

		$taskAssigneeId = (int) ($_POST['task_assignee_id'] ?? 0);
		$task = new Task();

		try
			{
			$this->json([
				'success' => true,
				'data' => $task->pauseExecution($taskAssigneeId, $this->currentUserId()),
			]);
			}
		catch (\Throwable $e)
			{
			$this->json([
				'success' => false,
				'error' => $e->getMessage(),
			], 422);
			}
		}

	/**
	 * Returns tenant-scoped person suggestions for third party fields.
	 **/
	public function thirdPartyAutocomplete(): void
		{
		$this->requireTaskFormAccess();

		$term = isset($_GET['term']) ? trim((string) $_GET['term']) : '';
		$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 20;
		$task = new Task();
		$rows = $task->autocompletePeople($term, $this->currentTenantId(), $limit);

		$items = array_map(static function (array $row): array
			{
			$label = trim((string) ($row['name'] ?? ''));
			if ($label === '')
				{
				$label = (string) ($row['id'] ?? '');
				}

			return [
				'id' => isset($row['id']) ? (int) $row['id'] : null,
				'label' => $label,
				'name' => $row['name'] ?? null,
			];
			}, $rows);

		$this->json(['items' => $items]);
		}

	/**
	 * Allows access to helper endpoints for users that can create or edit tasks.
	 **/
	private function requireTaskFormAccess(): void
		{
		if (Authorization::can('task.create') || Authorization::can('task.edit') || Authorization::can('task.update'))
			{
			return;
			}

		$this->requirePermission('task.create');
		}

	/**
	 * Builds normalized rows for task reports.
	 **/
	private function reportRows(array $tasks): array
		{
		$rows = [];
		foreach ($tasks as $task)
			{
			$thirdPartyLabel = trim((string) ($task['third_party_person_name'] ?? ''));
			if ($thirdPartyLabel === '')
				{
				$thirdPartyLabel = trim((string) ($task['third_party_name'] ?? ''));
				}

			$rows[] = [
				'id' => (int) ($task['id'] ?? 0),
				'title' => (string) ($task['title'] ?? ''),
				'task_category_name' => (string) ($task['task_category_name'] ?? ''),
				'status_label' => Task::statusLabel((string) ($task['status'] ?? '')),
				'assignee_names' => (string) ($task['assignee_names'] ?? ''),
				'third_party_label' => $thirdPartyLabel,
				'total_duration' => Task::formatDuration((int) ($task['total_seconds'] ?? 0)),
				'created_label' => Task::formatDateTime($task['created'] ?? ''),
			];
			}

		return $rows;
		}

	/**
	 * Builds display labels for report filters.
	 **/
	private function reportFilters(array $filters): array
		{
		return [
			'Código' => $filters['id'],
			'Título' => $filters['title'],
			'Situação' => Task::statusLabel($filters['status']),
			'Responsável' => $filters['assignee_person_id'],
			'Terceiro' => $filters['third_party_name'],
			'Criada de' => $filters['created_from'],
			'Criada até' => $filters['created_to'],
		];
		}

	}
?>
