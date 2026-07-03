<?php
$dashboard = is_array($dashboard ?? null) ? $dashboard : [];
$metrics = is_array($dashboard['metrics'] ?? null) ? $dashboard['metrics'] : [];
$health = is_array($dashboard['health'] ?? null) ? $dashboard['health'] : [];
$quickActions = is_array($dashboard['quickActions'] ?? null) ? $dashboard['quickActions'] : [];
$recentPeople = is_array($dashboard['recentPeople'] ?? null) ? $dashboard['recentPeople'] : [];
$recentUsers = is_array($dashboard['recentUsers'] ?? null) ? $dashboard['recentUsers'] : [];
$pendingTasks = is_array($dashboard['pendingTasks'] ?? null) ? $dashboard['pendingTasks'] : [];
$schemaVersions = is_array($schemaVersions ?? null) ? $schemaVersions : [];
$latestSchema = is_array($dashboard['latestSchema'] ?? null) ? $dashboard['latestSchema'] : null;
$currentUser = is_array($currentUser ?? null) ? $currentUser : [];
$tenantDatabaseName = (string) ($tenantDatabaseName ?? '');

$formatNumber = static function ($value): string {
	return number_format((float) $value, 0, ',', '.');
};

$formatValue = static function ($value): string {
	return is_float($value) ? number_format($value, 1, ',', '.') : number_format((float) $value, 0, ',', '.');
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

$personTypeLabel = static function ($type): string {
	return $type === 'company' ? 'Pessoa jurídica' : 'Pessoa física';
};

$userTypeLabel = static function ($type): string {
	$type = (string) $type;

	if ($type === 'admin')
		{
		return 'Administrador';
		}

	if ($type === 'root')
		{
		return 'Root';
		}

	return 'Usuário';
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

$tenantName = trim((string) ($tenant['fantasy'] ?? '')) !== '' ? (string) $tenant['fantasy'] : (string) ($tenant['name'] ?? 'Tenant');
$tenantStatus = (string) ($tenant['status'] ?? '');
$tenantStatusLabel = $tenantStatus === 'active' ? 'Ativo' : ($tenantStatus !== '' ? ucfirst($tenantStatus) : '-');
$tenantDocument = trim((string) ($tenant['document'] ?? ''));
$userName = trim((string) ($currentUser['name'] ?? ''));
?>

<link rel="stylesheet" href="<?= url('assets/css/home/index.css?v=2'); ?>">

<div class="dashboard-shell">
	<?php if (!empty($tenant)): ?>
		<section class="dashboard-hero">
			<div class="dashboard-hero-copy">
				<span class="dashboard-kicker"><i class="bi bi-speedometer2"></i> Dashboard do tenant</span>
				<h1><?= htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8'); ?></h1>
				<p>
					<?= $userName !== '' ? htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') . ', ' : ''; ?>visão operacional de
					<strong><?= htmlspecialchars($tenant['name'] ?? 'tenant', ENT_QUOTES, 'UTF-8'); ?></strong>.
				</p>
			</div>

			<div class="dashboard-tenant-grid" aria-label="Resumo do tenant ativo">
				<div class="dashboard-tenant-item">
					<span>Banco</span>
					<strong><?= htmlspecialchars($tenantDatabaseName, ENT_QUOTES, 'UTF-8'); ?></strong>
				</div>
				<div class="dashboard-tenant-item">
					<span>Status</span>
					<strong><?= htmlspecialchars($tenantStatusLabel, ENT_QUOTES, 'UTF-8'); ?></strong>
				</div>
				<div class="dashboard-tenant-item">
					<span>Tenant ID</span>
					<strong>#<?= htmlspecialchars((string) ($tenant['id'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></strong>
				</div>
				<div class="dashboard-tenant-item">
					<span>Último schema</span>
					<strong><?= htmlspecialchars((string) ($latestSchema['version'] ?? $latestSchema['id'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></strong>
				</div>
			</div>
		</section>

		<section class="dashboard-metrics" aria-label="Indicadores do tenant">
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
					<h2>Pendentes</h2>
				</div>
				<?php if (function_exists('authCan') && authCan('task.index')): ?>
					<a class="dashboard-panel-link" href="<?= url('task'); ?>">Ver tarefas</a>
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
						<?php if (!empty($pendingTasks)): ?>
							<?php foreach ($pendingTasks as $taskRow): ?>
								<tr data-dashboard-task-row="<?= (int) ($taskRow['id'] ?? 0); ?>">
									<td><?= htmlspecialchars((string) ($taskRow['id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
									<td>
										<strong><?= htmlspecialchars((string) ($taskRow['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong>
										<small class="text-muted d-block"><?= htmlspecialchars((string) ($taskRow['task_category_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></small>
									</td>
									<td><span class="badge <?= htmlspecialchars(\App\Models\Task::statusBadgeClass((string) ($taskRow['status'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>" data-dashboard-task-status="<?= (int) ($taskRow['id'] ?? 0); ?>"><?= htmlspecialchars(\App\Models\Task::statusLabel((string) ($taskRow['status'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></span></td>
									<td><?= htmlspecialchars((string) ($taskRow['assignee_names'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
									<td data-dashboard-task-duration="<?= (int) ($taskRow['id'] ?? 0); ?>"><?= htmlspecialchars(\App\Models\Task::formatDuration((int) ($taskRow['total_seconds'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?></td>
									<td class="text-end">
										<div class="dashboard-task-actions">
											<?php foreach (($taskRow['assignees'] ?? []) as $assignee): ?>
												<?php $isRunning = !empty($assignee['open_execution_id']); ?>
												<div class="dashboard-task-action" data-task-assignee-row="<?= (int) $assignee['id']; ?>">
													<span><?= htmlspecialchars((string) ($assignee['person_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
													<small data-assignee-duration="<?= (int) $assignee['id']; ?>"><?= htmlspecialchars(\App\Models\Task::formatDuration((int) ($assignee['total_seconds'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?></small>
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
								<td colspan="6" class="text-center text-muted py-4">Nenhuma tarefa pendente encontrada.</td>
							</tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</section>

		<section class="dashboard-grid">
			<div class="dashboard-panel dashboard-panel-wide">
				<div class="dashboard-panel-heading">
					<div>
						<span class="dashboard-section-label">Atividade</span>
						<h2>Pessoas recentes</h2>
					</div>
					<?php if (function_exists('authCan') && authCan('person.index')): ?>
						<a class="dashboard-panel-link" href="<?= url('person'); ?>">Ver lista</a>
					<?php endif; ?>
				</div>

				<div class="table-responsive dashboard-table-wrap">
					<table class="table table-hover dashboard-table">
						<thead>
							<tr>
								<th>#</th>
								<th>Nome</th>
								<th>Tipo</th>
								<th>Status</th>
								<th>Cadastro</th>
							</tr>
						</thead>
						<tbody>
							<?php if (!empty($recentPeople)): ?>
								<?php foreach ($recentPeople as $person): ?>
									<tr>
										<td><?= htmlspecialchars((string) ($person['id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
										<td><?= htmlspecialchars((string) ($person['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
										<td><?= htmlspecialchars($personTypeLabel($person['type'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
										<td>
											<span class="dashboard-status <?= ((int) ($person['active'] ?? 0) === 1) ? 'is-active' : 'is-inactive'; ?>">
												<?= ((int) ($person['active'] ?? 0) === 1) ? 'Ativa' : 'Inativa'; ?>
											</span>
										</td>
										<td><?= htmlspecialchars($formatDateTime($person['created'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
									</tr>
								<?php endforeach; ?>
							<?php else: ?>
								<tr>
									<td colspan="5" class="text-center text-muted py-4">Nenhuma pessoa encontrada.</td>
								</tr>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>

			<div class="dashboard-panel">
				<div class="dashboard-panel-heading">
					<div>
						<span class="dashboard-section-label">Saúde</span>
						<h2>Resumo do tenant</h2>
					</div>
				</div>

				<div class="dashboard-health-list">
					<?php foreach ($health as $item): ?>
						<?php
						$progress = max(0, min(100, (int) ($item['progress'] ?? $item['value'] ?? 0)));
						$tone = preg_replace('/[^a-z0-9_-]/i', '', (string) ($item['tone'] ?? 'blue'));
						?>
						<div class="dashboard-health-item is-<?= htmlspecialchars($tone, ENT_QUOTES, 'UTF-8'); ?>">
							<div class="dashboard-health-top">
								<span><?= htmlspecialchars((string) ($item['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
								<strong><?= $formatValue($item['value'] ?? 0); ?><?= htmlspecialchars((string) ($item['suffix'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong>
							</div>
							<div class="dashboard-progress" aria-hidden="true">
								<span style="width: <?= $progress; ?>%;"></span>
							</div>
							<small><?= htmlspecialchars((string) ($item['caption'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></small>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</section>

		<section class="dashboard-grid dashboard-grid-secondary">
			<div class="dashboard-panel">
				<div class="dashboard-panel-heading">
					<div>
						<span class="dashboard-section-label">Acesso</span>
						<h2>Usuários recentes</h2>
					</div>
					<?php if (function_exists('authCan') && authCan('user.index')): ?>
						<a class="dashboard-panel-link" href="<?= url('user'); ?>">Ver usuários</a>
					<?php endif; ?>
				</div>

				<div class="dashboard-list">
					<?php if (!empty($recentUsers)): ?>
						<?php foreach ($recentUsers as $user): ?>
							<div class="dashboard-list-row">
								<span class="dashboard-avatar"><i class="bi bi-person"></i></span>
								<div>
									<strong><?= htmlspecialchars((string) ($user['username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong>
									<small><?= htmlspecialchars($userTypeLabel($user['type'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> · <?= ((int) ($user['active'] ?? 0) === 1) ? 'Ativo' : 'Inativo'; ?></small>
								</div>
							</div>
						<?php endforeach; ?>
					<?php else: ?>
						<p class="dashboard-empty">Nenhum usuário encontrado.</p>
					<?php endif; ?>
				</div>
			</div>

			<div class="dashboard-panel dashboard-panel-wide">
				<div class="dashboard-panel-heading">
					<div>
						<span class="dashboard-section-label">Schema Versions</span>
						<h2>Últimas execuções</h2>
					</div>
					<span class="dashboard-chip"><?= $formatNumber($dashboard['schemaTotal'] ?? 0); ?> registro(s)</span>
				</div>

				<div class="table-responsive dashboard-table-wrap">
					<table class="table table-hover dashboard-table">
						<thead>
							<tr>
								<th>ID</th>
								<th>Version</th>
								<th>Description</th>
								<th>Executed At</th>
							</tr>
						</thead>
						<tbody>
							<?php if (!empty($schemaVersions)): ?>
								<?php foreach ($schemaVersions as $row): ?>
									<tr>
										<td><?= htmlspecialchars((string) ($row['id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
										<td><?= htmlspecialchars((string) ($row['version'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
										<td><?= htmlspecialchars((string) ($row['description'] ?? $row['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
										<td><?= htmlspecialchars($formatDateTime($row['executed_at'] ?? $row['created'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
									</tr>
								<?php endforeach; ?>
							<?php else: ?>
								<tr>
									<td colspan="4" class="text-center text-muted py-4">Nenhuma versão de schema encontrada.</td>
								</tr>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>
		</section>

		<?php
		$visibleActions = array_values(array_filter($quickActions, $canOpen));
		?>
		<?php if (!empty($visibleActions) || $tenantDocument !== ''): ?>
			<section class="dashboard-actions">
				<div class="dashboard-actions-copy">
					<span class="dashboard-section-label">Atalhos</span>
					<h2>Operações do tenant</h2>
					<?php if ($tenantDocument !== ''): ?>
						<p>Documento: <?= htmlspecialchars($tenantDocument, ENT_QUOTES, 'UTF-8'); ?></p>
					<?php endif; ?>
				</div>

				<?php if (!empty($visibleActions)): ?>
					<div class="dashboard-action-list">
						<?php foreach ($visibleActions as $action): ?>
							<a class="btn btn-<?= htmlspecialchars((string) ($action['variant'] ?? 'secondary'), ENT_QUOTES, 'UTF-8'); ?>" href="<?= url((string) $action['href']); ?>">
								<i class="bi <?= htmlspecialchars((string) ($action['icon'] ?? 'bi-arrow-right'), ENT_QUOTES, 'UTF-8'); ?>"></i>
								<?= htmlspecialchars((string) ($action['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
							</a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</section>
		<?php endif; ?>
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

<script src="<?= url('assets/js/home/index.js?v=1'); ?>"></script>
