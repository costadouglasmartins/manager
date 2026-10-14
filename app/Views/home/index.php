<?php
$dashboard = is_array($dashboard ?? null) ? $dashboard : [];
$metrics = is_array($dashboard['metrics'] ?? null) ? $dashboard['metrics'] : [];
$todayTasks = is_array($dashboard['todayTasks'] ?? null) ? $dashboard['todayTasks'] : [];
$todayScheduleEvents = is_array($dashboard['todayScheduleEvents'] ?? null) ? $dashboard['todayScheduleEvents'] : [];
$currentUser = is_array($currentUser ?? null) ? $currentUser : [];

$formatNumber = static function ($value): string {
	return number_format((float) $value, 0, ',', '.');
};

$formatDateTime = static function ($value): string {
	$value = trim((string) ($value ?? ''));

	if ($value === '')
		{
		return '-';
		}

	try
		{
		return (new DateTimeImmutable($value))->format('d/m/Y H:i');
		}
	catch (Throwable $e)
		{
		return $value;
		}
};

$canOpen = static function (array $item): bool {
	if (empty($item['href']))
		{
		return false;
		}

	if (empty($item['ability']))
		{
		return true;
		}

	return function_exists('authCan') && authCan((string) $item['ability']);
};

$userName = trim((string) ($currentUser['name'] ?? ''));
$metricsCount = count($metrics);
$metricsDesktopColumns = min(6, max(1, $metricsCount));
$today = date('Y-m-d');
?>

<link rel="stylesheet" href="<?= url('assets/css/home/index.css?v=3'); ?>">

<div class="dashboard-shell">
	<?php if (!empty($tenant)): ?>
		<section class="dashboard-hero">
			<div class="dashboard-hero-copy">
				<span class="dashboard-kicker"><i class="bi bi-speedometer2"></i> Dashboard do dia</span>
				<h1>Painel operacionalTESTE</h1>
				<p>
					<?= $userName !== '' ? htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') . ', ' : ''; ?>acompanhe os indicadores diários dos módulos principais.
				</p>
			</div>
		</section>

		<section class="dashboard-metrics" style="--dashboard-metrics-desktop-columns: <?= $metricsDesktopColumns; ?>;" aria-label="Indicadores diários dos módulos">
			<?php foreach ($metrics as $metric): ?>
				<?php
				$tone = preg_replace('/[^a-z0-9_-]/i', '', (string) ($metric['tone'] ?? 'blue'));
				$metricClass = 'dashboard-metric is-' . $tone;
				$metricCanOpen = $canOpen($metric);
				?>

				<?php if ($metricCanOpen): ?>
					<a class="<?= htmlspecialchars($metricClass, ENT_QUOTES, 'UTF-8'); ?>" href="<?= url((string) $metric['href']); ?>">
						<span class="dashboard-metric-icon"><i class="bi <?= htmlspecialchars((string) ($metric['icon'] ?? 'bi-circle'), ENT_QUOTES, 'UTF-8'); ?>"></i></span>
						<span class="dashboard-metric-label"><?= htmlspecialchars((string) ($metric['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
						<strong><?= $formatNumber($metric['value'] ?? 0); ?></strong>
						<small><?= htmlspecialchars((string) ($metric['caption'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></small>
					</a>
				<?php else: ?>
					<div class="<?= htmlspecialchars($metricClass, ENT_QUOTES, 'UTF-8'); ?>">
						<span class="dashboard-metric-icon"><i class="bi <?= htmlspecialchars((string) ($metric['icon'] ?? 'bi-circle'), ENT_QUOTES, 'UTF-8'); ?>"></i></span>
						<span class="dashboard-metric-label"><?= htmlspecialchars((string) ($metric['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
						<strong><?= $formatNumber($metric['value'] ?? 0); ?></strong>
						<small><?= htmlspecialchars((string) ($metric['caption'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></small>
					</div>
				<?php endif; ?>
			<?php endforeach; ?>
		</section>

		<section class="dashboard-panel dashboard-task-panel">
			<div class="dashboard-panel-heading">
				<div>
					<span class="dashboard-section-label">Tarefas</span>
					<h2>Criadas hoje</h2>
				</div>
				<?php if (function_exists('authCan') && authCan('task.index')): ?>
					<a class="dashboard-panel-link" href="<?= url('task?created_from=' . $today . '&created_to=' . $today); ?>">Ver tarefas</a>
				<?php endif; ?>
			</div>

			<div class="table-responsive dashboard-table-wrap">
				<table class="table table-hover dashboard-table dashboard-task-table">
					<thead>
						<tr>
							<th>#</th>
							<th>Tarefa</th>
							<th>Situação</th>
							<th>Responsáveis</th>
							<th>Tempo</th>
							<th class="text-end">Execução</th>
						</tr>
					</thead>
					<tbody>
						<?php if (!empty($todayTasks)): ?>
							<?php foreach ($todayTasks as $taskRow): ?>
								<tr data-dashboard-task-row="<?= (int) ($taskRow['id'] ?? 0); ?>">
									<td><?= htmlspecialchars((string) ($taskRow['id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
									<td>
										<strong><?= htmlspecialchars((string) ($taskRow['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong>
										<small class="text-muted d-block"><?= htmlspecialchars((string) ($taskRow['task_category_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></small>
									</td>
									<td><span class="badge <?= htmlspecialchars(\App\Models\Task::statusBadgeClass((string) ($taskRow['status'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>" data-dashboard-task-status="<?= (int) ($taskRow['id'] ?? 0); ?>"><?= htmlspecialchars(\App\Models\Task::statusLabel((string) ($taskRow['status'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></span></td>
									<td><?= htmlspecialchars((string) ($taskRow['assignee_names'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
									<td data-dashboard-task-duration="<?= (int) ($taskRow['id'] ?? 0); ?>" data-task-duration-seconds="<?= (int) ($taskRow['total_seconds'] ?? 0); ?>" data-task-duration-running-count="<?= (int) ($taskRow['open_execution_count'] ?? (!empty($taskRow['has_open_execution']) ? 1 : 0)); ?>"><?= htmlspecialchars(\App\Models\Task::formatDuration((int) ($taskRow['total_seconds'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?></td>
									<td class="text-end">
										<div class="dashboard-task-actions">
											<?php foreach (($taskRow['assignees'] ?? []) as $assignee): ?>
												<?php $isRunning = !empty($assignee['open_execution_id']); ?>
												<div class="dashboard-task-action" data-task-assignee-row="<?= (int) $assignee['id']; ?>">
													<span><?= htmlspecialchars((string) ($assignee['person_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
													<small data-assignee-duration="<?= (int) $assignee['id']; ?>" data-task-duration-seconds="<?= (int) ($assignee['total_seconds'] ?? 0); ?>" data-task-duration-running-count="<?= $isRunning ? 1 : 0; ?>"><?= htmlspecialchars(\App\Models\Task::formatDuration((int) ($assignee['total_seconds'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?></small>
													<?php if (authCan('task.startExecution')): ?>
														<button class="btn btn-success btn-sm js-task-execution-action" data-action="start" data-endpoint="<?= url('task/startExecution'); ?>" data-task-assignee-id="<?= (int) $assignee['id']; ?>" type="button" <?= $isRunning ? 'disabled' : ''; ?>><i class="bi bi-play-fill"></i></button>
													<?php endif; ?>
													<?php if (authCan('task.pauseExecution')): ?>
														<button class="btn btn-warning btn-sm js-task-execution-action" data-action="pause" data-endpoint="<?= url('task/pauseExecution'); ?>" data-task-assignee-id="<?= (int) $assignee['id']; ?>" type="button" <?= $isRunning ? '' : 'disabled'; ?>><i class="bi bi-pause-fill"></i></button>
													<?php endif; ?>
												</div>
											<?php endforeach; ?>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php else: ?>
							<tr>
								<td colspan="6" class="text-center text-muted py-4">Nenhuma tarefa criada hoje.</td>
							</tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</section>

		<section class="dashboard-panel dashboard-schedule-panel">
			<div class="dashboard-panel-heading">
				<div>
					<span class="dashboard-section-label">Agenda</span>
					<h2>Eventos de hoje</h2>
				</div>
				<?php if (function_exists('authCan') && authCan('schedule.index')): ?>
					<a class="dashboard-panel-link" href="<?= url('schedule?starts_from=' . $today . '&starts_to=' . $today); ?>">Ver agenda</a>
				<?php endif; ?>
			</div>

			<div class="dashboard-schedule-list">
				<?php if (!empty($todayScheduleEvents)): ?>
					<?php foreach ($todayScheduleEvents as $scheduleEvent): ?>
						<?php
						$categoryBg = (string) ($scheduleEvent['schedule_category_background_color'] ?? '#0d6efd');
						$categoryColor = (string) ($scheduleEvent['schedule_category_description_color'] ?? '#ffffff');
						$scheduleDay = '-';
						$scheduleDateLabel = $formatDateTime($scheduleEvent['starts_at'] ?? '');
						try
							{
							$scheduleDate = new DateTimeImmutable((string) ($scheduleEvent['starts_at'] ?? ''));
							$scheduleDay = $scheduleDate->format('d');
							$scheduleDateLabel = ((int) ($scheduleEvent['all_day'] ?? 0) === 1) ? $scheduleDate->format('m/Y') : $scheduleDate->format('m/Y H:i');
							}
						catch (Throwable $e)
							{
							}
						?>
						<a class="dashboard-list-row dashboard-schedule-row" href="<?= url('schedule/informations/' . (int) ($scheduleEvent['id'] ?? 0)); ?>">
							<span class="dashboard-schedule-date">
								<strong><?= htmlspecialchars($scheduleDay, ENT_QUOTES, 'UTF-8'); ?></strong>
								<span><?= htmlspecialchars($scheduleDateLabel, ENT_QUOTES, 'UTF-8'); ?></span>
							</span>
							<span class="dashboard-schedule-main">
								<span class="dashboard-schedule-title">
									<strong><?= htmlspecialchars((string) ($scheduleEvent['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong>
									<span class="badge <?= htmlspecialchars(\App\Models\ScheduleEvent::statusBadgeClass((string) ($scheduleEvent['status'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars(\App\Models\ScheduleEvent::statusLabel((string) ($scheduleEvent['status'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></span>
								</span>
								<small><?= htmlspecialchars((string) ($scheduleEvent['participant_names'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></small>
								<?php if (!empty($scheduleEvent['schedule_category_name'])): ?>
									<span class="dashboard-schedule-meta">
										<span class="dashboard-schedule-category" style="background-color: <?= htmlspecialchars($categoryBg, ENT_QUOTES, 'UTF-8'); ?>; color: <?= htmlspecialchars($categoryColor, ENT_QUOTES, 'UTF-8'); ?>;"><?= htmlspecialchars((string) ($scheduleEvent['schedule_category_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
									</span>
								<?php endif; ?>
							</span>
						</a>
					<?php endforeach; ?>
				<?php else: ?>
					<p class="dashboard-empty">Nenhum evento para hoje.</p>
				<?php endif; ?>
			</div>
		</section>
	<?php else: ?>
		<section class="dashboard-empty-state">
			<i class="bi bi-building-lock"></i>
			<h1>Nenhum tenant selecionado</h1>
			<p>Selecione um tenant para visualizar os dados do dashboard.</p>
			<a class="btn btn-primary" href="<?= url('tenant/select'); ?>">
				<i class="bi bi-building-check"></i>
				Selecionar tenant
			</a>
		</section>
	<?php endif; ?>
</div>

<script src="<?= url('assets/js/tasks/duration_timer.js?v=1'); ?>"></script>
<script src="<?= url('assets/js/home/index.js?v=2'); ?>"></script>
