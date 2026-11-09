<?php

namespace App\Controllers;

use App\Core\Authorization;
use App\Core\Controller;
use App\Core\TenantContext;
use App\Models\Crm;
use App\Models\FinancialOperation;
use App\Models\ScheduleEvent;
use App\Models\Task;
use App\Models\Tenant;

class HomeController extends Controller
  {
	/**
	 * Renders the home dashboard with the tenant selected in session.
	 */
	public function index()
	  {
		$this->requireAuth();
		$this->resolveTenantContext();

		$tenant = TenantContext::tenant();
		$dashboard = $this->emptyDashboard();

		if (TenantContext::hasTenant() && $tenant !== null)
		  {
			$dashboard = $this->buildTenantDashboard((int) ($tenant['id'] ?? 0));
		  }

		$this->view('home/index', [
			'title' => 'Dashboard',
			'tenant' => $tenant,
			'currentUser' => $_SESSION['manager']['user'] ?? null,
			'dashboard' => $dashboard,
		]);
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
		$today = date('Y-m-d');
		$crmModel = new Crm();
		$financialOperationModel = new FinancialOperation();
		$taskModel = new Task();
		$scheduleModel = new ScheduleEvent();

		// Metric values stay as callbacks so visibleMetrics() only queries the ones the user may open.
		$crmToday = function () use ($crmModel, $tenantId) {
			return $crmModel->count(['activity_scope' => 'today'], $tenantId);
		};
		$scheduleToday = function () use ($scheduleModel, $today) {
			return $scheduleModel->count([
				'status_not' => ScheduleEvent::STATUS_CANCELED,
				'starts_from' => $today,
				'starts_to' => $today,
			]);
		};
		$tasksToday = function () use ($taskModel, $today) {
			return $taskModel->count([
				'created_from' => $today,
				'created_to' => $today,
			]);
		};
		$payableToday = function () use ($financialOperationModel, $today) {
			return $financialOperationModel->countBySectionAndDate('expense', $today);
		};
		$receivableToday = function () use ($financialOperationModel, $today) {
			return $financialOperationModel->countBySectionAndDate('income', $today);
		};

		// Today's lists are loaded only for users allowed to open their modules.
		$todayTasks = [];
		if (Authorization::can('task.index'))
		  {
			$todayTasks = $this->safeArray(function () use ($taskModel, $today) {
				$tasks = $taskModel->search([
					'created_from' => $today,
					'created_to' => $today,
				], 8, 0, 'created', 'desc');

				foreach ($tasks as &$task)
					{
					$task['assignees'] = $taskModel->getAssignees((int) ($task['id'] ?? 0));
					}
				unset($task);

				return $tasks;
			});
		  }

		$todayScheduleEvents = [];
		if (Authorization::can('schedule.index'))
		  {
			$todayScheduleEvents = $this->safeArray(function () use ($scheduleModel, $today) {
				return $scheduleModel->search([
					'status_not' => ScheduleEvent::STATUS_CANCELED,
					'starts_from' => $today,
					'starts_to' => $today,
				], 10, 0, 'starts_at', 'asc');
			});
		  }

		return [
			'metrics' => $this->visibleMetrics([
				[
					'label' => 'CRM',
					'value' => $crmToday,
					'caption' => 'Registros com atividade prevista para hoje',
					'icon' => 'bi-person-workspace',
					'tone' => 'blue',
					'href' => 'crm?activity_scope=today',
					'ability' => 'crm.index',
				],
				[
					'label' => 'Agenda',
					'value' => $scheduleToday,
					'caption' => 'Eventos programados para hoje',
					'icon' => 'bi-calendar3',
					'tone' => 'green',
					'href' => 'schedule?starts_from=' . $today . '&starts_to=' . $today,
					'ability' => 'schedule.index',
				],
				[
					'label' => 'Tarefas',
					'value' => $tasksToday,
					'caption' => 'Tarefas criadas hoje',
					'icon' => 'bi-list-check',
					'tone' => 'indigo',
					'href' => 'task?created_from=' . $today . '&created_to=' . $today,
					'ability' => 'task.index',
				],
				[
					'label' => 'Contas a pagar',
					'value' => $payableToday,
					'caption' => 'Despesas cadastradas hoje',
					'icon' => 'bi-wallet2',
					'tone' => 'teal',
					'href' => 'financial_operation',
					'ability' => 'financial_operation.index',
				],
				[
					'label' => 'Contas a receber',
					'value' => $receivableToday,
					'caption' => 'Receitas cadastradas hoje',
					'icon' => 'bi-cash-coin',
					'tone' => 'amber',
					'href' => 'financial_operation',
					'ability' => 'financial_operation.index',
				],
			]),
			'todayTasks' => $todayTasks,
			'todayScheduleEvents' => $todayScheduleEvents,
		];
	  }

	/**
	 * Keeps the metrics the current user may open and resolves only their values.
	 * @param array $metrics Metric definitions whose `value` is a callback returning the count.
	 * @return array Visible metrics with integer values.
	 **/
	private function visibleMetrics(array $metrics): array
	  {
		$visible = [];

		foreach ($metrics as $metric)
		  {
			// Metrics without a granted ability are skipped before their query runs.
			if (!Authorization::can((string) ($metric['ability'] ?? '')))
			  {
				continue;
			  }

			$metric['value'] = $this->safeInt($metric['value']);
			$visible[] = $metric;
		  }

		return $visible;
	  }

	/**
	 * Returns the base structure used when no tenant is selected.
	 */
	private function emptyDashboard(): array
	  {
		return [
			'metrics' => [],
			'todayTasks' => [],
			'todayScheduleEvents' => [],
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
  }
?>
