<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\TenantContext;
use App\Models\Address;
use App\Models\Email;
use App\Models\Person;
use App\Models\PhoneNumber;
use App\Models\ScheduleEvent;
use App\Models\SchemaVersion;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;

class HomeController extends Controller
  {
	/**
	 * Renders the tenant dashboard with operational metrics and recent activity.
	 */
	public function index()
	  {
		$this->requireAuth();
		$this->resolveTenantContext();

		$tenant = TenantContext::tenant();
		$dashboard = $this->emptyDashboard();
		$schemaVersions = [];

		if (TenantContext::hasTenant() && $tenant !== null)
		  {
			$dashboard = $this->buildTenantDashboard((int) ($tenant['id'] ?? 0));
			$schemaVersions = $dashboard['schemaVersions'];
		  }

		$data = [
			'title' => 'Dashboard',
			'tenant' => $tenant,
			'tenantDatabaseName' => TenantContext::databaseName(),
			'currentUser' => $_SESSION['manager']['user'] ?? null,
			'dashboard' => $dashboard,
			'schemaVersions' => $schemaVersions,
		];

		$this->view('home/index', $data);
	  }

	/**
	 * Selects the only available tenant automatically or redirects to tenant selection.
	 */
	private function resolveTenantContext(): void
	  {
		if (TenantContext::hasTenant())
		  {
			return;
		  }

		$user = $_SESSION['manager']['user'] ?? null;
		$tenantModel = new Tenant();
		$tenants = $tenantModel->getAccessibleByUserId((int) ($user['id'] ?? 0));

		if (count($tenants) === 1)
		  {
			TenantContext::setTenant($tenants[0]);
			return;
		  }

		if (count($tenants) > 1)
		  {
			$this->redirect('tenant/select');
		  }
	  }

	/**
	 * Builds dashboard data scoped to the current tenant.
	 */
	private function buildTenantDashboard(int $tenantId): array
	  {
		$personModel = new Person();
		$userModel = new User();
		$emailModel = new Email();
		$phoneNumberModel = new PhoneNumber();
		$addressModel = new Address();
		$schemaVersionModel = new SchemaVersion();
		$taskModel = new Task();
		$scheduleModel = new ScheduleEvent();

		$peopleTotal = $this->safeInt(function () use ($personModel, $tenantId) {
			return $personModel->count([], $tenantId);
		});
		$activePeople = $this->safeInt(function () use ($personModel, $tenantId) {
			return $personModel->count(['active' => '1'], $tenantId);
		});
		$inactivePeople = $this->safeInt(function () use ($personModel, $tenantId) {
			return $personModel->count(['active' => '0'], $tenantId);
		});
		$companyPeople = $this->safeInt(function () use ($personModel, $tenantId) {
			return $personModel->count(['type' => 'company'], $tenantId);
		});
		$individualPeople = $this->safeInt(function () use ($personModel, $tenantId) {
			return $personModel->count(['type' => 'individual'], $tenantId);
		});
		$usersTotal = $this->safeInt(function () use ($userModel, $tenantId) {
			return $userModel->count([], $tenantId);
		});
		$activeUsers = $this->safeInt(function () use ($userModel, $tenantId) {
			return $userModel->count(['active' => '1'], $tenantId);
		});
		$emailTotal = $this->safeInt(function () use ($emailModel, $tenantId) {
			return $emailModel->count([], $tenantId);
		});
		$phoneTotal = $this->safeInt(function () use ($phoneNumberModel, $tenantId) {
			return $phoneNumberModel->count([], $tenantId);
		});
		$addressTotal = $this->safeInt(function () use ($addressModel, $tenantId) {
			return $addressModel->count([], $tenantId);
		});
		$schemaTotal = $this->safeInt(function () use ($schemaVersionModel) {
			return $schemaVersionModel->count();
		});
		$pendingTasksTotal = $this->safeInt(function () use ($taskModel) {
			return $taskModel->count(['statuses' => Task::pendingStatuses()]);
		});
		$upcomingScheduleTotal = $this->safeInt(function () use ($scheduleModel) {
			return $scheduleModel->count([
				'status' => ScheduleEvent::STATUS_SCHEDULED,
				'starts_from' => date('Y-m-d'),
			]);
		});

		$schemaVersions = $this->safeArray(function () use ($schemaVersionModel) {
			return $schemaVersionModel->getLatest(10);
		});
		$recentPeople = $this->safeArray(function () use ($personModel, $tenantId) {
			return $personModel->search([], 6, 0, $tenantId);
		});
		$recentUsers = $this->safeArray(function () use ($userModel, $tenantId) {
			return $userModel->search([], 5, 0, $tenantId);
		});
		$pendingTasks = $this->safeArray(function () use ($taskModel) {
			$tasks = $taskModel->dashboardTasks(8);
			foreach ($tasks as &$task)
				{
				$task['assignees'] = $taskModel->getAssignees((int) ($task['id'] ?? 0));
				}
			unset($task);

			return $tasks;
		});
		$upcomingScheduleEvents = $this->safeArray(function () use ($scheduleModel) {
			return $scheduleModel->dashboardEvents(10);
		});

		$latestSchema = $schemaVersions[0] ?? null;
		$contactTotal = $emailTotal + $phoneTotal;
		$activePeoplePercentage = $this->percentage($activePeople, $peopleTotal);
		$activeUsersPercentage = $this->percentage($activeUsers, $usersTotal);
		$contactAverage = $peopleTotal > 0 ? round($contactTotal / $peopleTotal, 1) : 0;

		return [
			'metrics' => [
				[
					'label' => 'Pessoas',
					'value' => $peopleTotal,
					'caption' => $activePeople . ' ativas / ' . $inactivePeople . ' inativas',
					'icon' => 'bi-people-fill',
					'tone' => 'blue',
					'href' => 'person',
					'ability' => 'person.index',
				],
				[
					'label' => 'Empresas',
					'value' => $companyPeople,
					'caption' => $individualPeople . ' pessoas físicas',
					'icon' => 'bi-building-fill',
					'tone' => 'green',
					'href' => 'person?type=company',
					'ability' => 'person.index',
				],
				[
					'label' => 'Usuários',
					'value' => $usersTotal,
					'caption' => $activeUsers . ' ativos no tenant',
					'icon' => 'bi-person-badge-fill',
					'tone' => 'indigo',
					'href' => 'user',
					'ability' => 'user.index',
				],
				[
					'label' => 'Tarefas',
					'value' => $pendingTasksTotal,
					'caption' => 'Abertas, em andamento ou aguardando terceiro',
					'icon' => 'bi-list-check',
					'tone' => 'teal',
					'href' => 'task',
					'ability' => 'task.index',
				],
				[
					'label' => 'Agenda',
					'value' => $upcomingScheduleTotal,
					'caption' => 'Eventos futuros agendados',
					'icon' => 'bi-calendar3',
					'tone' => 'indigo',
					'href' => 'schedule/calendar',
					'ability' => 'schedule.calendar',
				],
				[
					'label' => 'E-mails',
					'value' => $emailTotal,
					'caption' => 'Registros de contato',
					'icon' => 'bi-envelope-paper-fill',
					'tone' => 'teal',
					'href' => 'email',
					'ability' => 'email.index',
				],
				[
					'label' => 'Telefones',
					'value' => $phoneTotal,
					'caption' => 'Linhas cadastradas',
					'icon' => 'bi-telephone-fill',
					'tone' => 'amber',
					'href' => 'phone_number',
					'ability' => 'phone_number.index',
				],
				[
					'label' => 'Endereços',
					'value' => $addressTotal,
					'caption' => 'Pontos vinculados',
					'icon' => 'bi-signpost-split-fill',
					'tone' => 'rose',
					'href' => 'address',
					'ability' => 'address.index',
				],
			],
			'health' => [
				[
					'label' => 'Pessoas ativas',
					'value' => $activePeoplePercentage,
					'progress' => $activePeoplePercentage,
					'suffix' => '%',
					'caption' => $activePeople . ' de ' . $peopleTotal . ' cadastros',
					'tone' => 'blue',
				],
				[
					'label' => 'Usuários ativos',
					'value' => $activeUsersPercentage,
					'progress' => $activeUsersPercentage,
					'suffix' => '%',
					'caption' => $activeUsers . ' de ' . $usersTotal . ' usuários',
					'tone' => 'green',
				],
				[
					'label' => 'Média de contatos',
					'value' => $contactAverage,
					'progress' => min(100, (int) round($contactAverage * 25)),
					'suffix' => '',
					'caption' => $contactAverage . ' por pessoa',
					'tone' => 'amber',
				],
			],
			'quickActions' => [
				['label' => 'Nova pessoa', 'icon' => 'bi-person-fill-add', 'href' => 'person/create', 'ability' => 'person.create', 'variant' => 'primary'],
				['label' => 'Nova tarefa', 'icon' => 'bi-plus-square', 'href' => 'task/create', 'ability' => 'task.create', 'variant' => 'primary'],
				['label' => 'Novo evento', 'icon' => 'bi-calendar-plus', 'href' => 'schedule/create', 'ability' => 'schedule.create', 'variant' => 'primary'],
				['label' => 'Pessoas', 'icon' => 'bi-person-lines-fill', 'href' => 'person', 'ability' => 'person.index', 'variant' => 'secondary'],
				['label' => 'Tarefas', 'icon' => 'bi-list-check', 'href' => 'task', 'ability' => 'task.index', 'variant' => 'secondary'],
				['label' => 'Agenda', 'icon' => 'bi-calendar3', 'href' => 'schedule/calendar', 'ability' => 'schedule.calendar', 'variant' => 'secondary'],
				['label' => 'Usuários', 'icon' => 'bi-person-badge', 'href' => 'user', 'ability' => 'user.index', 'variant' => 'secondary'],
				['label' => 'E-mails', 'icon' => 'bi-envelope-paper', 'href' => 'email', 'ability' => 'email.index', 'variant' => 'secondary'],
				['label' => 'Telefones', 'icon' => 'bi-telephone', 'href' => 'phone_number', 'ability' => 'phone_number.index', 'variant' => 'secondary'],
				['label' => 'Endereços', 'icon' => 'bi-signpost-split', 'href' => 'address', 'ability' => 'address.index', 'variant' => 'secondary'],
			],
			'recentPeople' => $recentPeople,
			'recentUsers' => $recentUsers,
			'pendingTasks' => $pendingTasks,
			'upcomingScheduleEvents' => $upcomingScheduleEvents,
			'schemaVersions' => $schemaVersions,
			'schemaTotal' => $schemaTotal,
			'latestSchema' => $latestSchema,
			'contactTotal' => $contactTotal,
		];
	  }

	/**
	 * Returns the base structure used when no tenant is selected.
	 */
	private function emptyDashboard(): array
	  {
		return [
			'metrics' => [],
			'health' => [],
			'quickActions' => [],
			'recentPeople' => [],
			'recentUsers' => [],
			'pendingTasks' => [],
			'upcomingScheduleEvents' => [],
			'schemaVersions' => [],
			'schemaTotal' => 0,
			'latestSchema' => null,
			'contactTotal' => 0,
		];
	  }

	/**
	 * Executes a callback and converts the result to an integer, logging failures.
	 */
	private function safeInt(callable $callback): int
	  {
		try
		  {
			return (int) $callback();
		  }
		catch (\Throwable $e)
		  {
			error_log('Dashboard metric error: ' . $e->getMessage());
			return 0;
		  }
	  }

	/**
	 * Executes a callback and returns an array result, logging failures.
	 */
	private function safeArray(callable $callback): array
	  {
		try
		  {
			$result = $callback();
			return is_array($result) ? $result : [];
		  }
		catch (\Throwable $e)
		  {
			error_log('Dashboard list error: ' . $e->getMessage());
			return [];
		  }
	  }

	/**
	 * Calculates an integer percentage while avoiding division by zero.
	 */
	private function percentage(int $value, int $total): int
	  {
		if ($total <= 0)
		  {
			return 0;
		  }

		return (int) round(($value / $total) * 100);
	  }
  }
?>
