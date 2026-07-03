<?php
$status = (string) ($task['status'] ?? '');
$thirdParty = trim((string) ($task['third_party_person_name'] ?? ''));
if ($thirdParty === '')
	{
	$thirdParty = trim((string) ($task['third_party_name'] ?? ''));
	}
?>
<link rel="stylesheet" href="<?= url('assets/css/tasks/informations.css?v=1'); ?>">

<div class="task-page">
  <div class="task-page-header">
    <div>
      <span class="task-page-kicker"><i class="bi bi-search"></i> Tarefas</span>
      <h5><?= htmlspecialchars((string) ($task['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h5>
    </div>
    <span class="badge <?= htmlspecialchars(\App\Models\Task::statusBadgeClass($status), ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars(\App\Models\Task::statusLabel($status), ENT_QUOTES, 'UTF-8'); ?></span>
  </div>

  <section class="task-section">
    <div class="table-responsive">
      <table class="table table-hover align-middle">
        <tbody>
          <tr>
            <th class="text-muted" style="width: 220px;">Código</th>
            <td><?= (int) $task['id']; ?></td>
          </tr>
          <tr>
            <th class="text-muted">Categoria</th>
            <td><?= htmlspecialchars((string) ($task['task_category_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
          </tr>
          <tr>
            <th class="text-muted">Terceiro</th>
            <td><?= htmlspecialchars($thirdParty, ENT_QUOTES, 'UTF-8'); ?></td>
          </tr>
          <tr>
            <th class="text-muted">Tempo total</th>
            <td data-task-total-duration="<?= (int) ($task['id'] ?? 0); ?>"><?= htmlspecialchars(\App\Models\Task::formatDuration((int) ($task['total_seconds'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?></td>
          </tr>
          <tr>
            <th class="text-muted">Criada em</th>
            <td><?= htmlspecialchars(\App\Models\Task::formatDateTime($task['created'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
          </tr>
          <tr>
            <th class="text-muted">Alterada em</th>
            <td><?= htmlspecialchars(\App\Models\Task::formatDateTime($task['modified'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>

  <section class="task-section">
    <h6>Descrição</h6>
    <div class="task-description"><?= (string) ($task['description'] ?? ''); ?></div>
  </section>

  <section class="task-section">
    <h6>Responsáveis e tempo</h6>
    <div class="task-assignee-list">
      <?php foreach ($assignees as $assignee): ?>
        <?php $isRunning = !empty($assignee['open_execution_id']); ?>
        <div class="task-assignee-row" data-task-assignee-row="<?= (int) $assignee['id']; ?>">
          <div>
            <strong><?= htmlspecialchars((string) ($assignee['person_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong>
            <small class="text-muted" data-assignee-duration="<?= (int) $assignee['id']; ?>"><?= htmlspecialchars(\App\Models\Task::formatDuration((int) ($assignee['total_seconds'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?></small>
          </div>
          <div class="task-assignee-actions">
            <span class="badge <?= $isRunning ? 'text-bg-warning' : 'text-bg-secondary'; ?>" data-assignee-running-label="<?= (int) $assignee['id']; ?>"><?= $isRunning ? 'Em execução' : 'Pausada'; ?></span>
            <?php if (authCan('task.startExecution')): ?>
              <button class="btn btn-success btn-sm js-task-execution-action" data-action="start" data-endpoint="<?= url('task/startExecution'); ?>" data-task-assignee-id="<?= (int) $assignee['id']; ?>" type="button" <?= $isRunning ? 'disabled' : ''; ?>><i class="bi bi-play-fill"></i></button>
            <?php endif; ?>
            <?php if (authCan('task.pauseExecution')): ?>
              <button class="btn btn-warning btn-sm js-task-execution-action" data-action="pause" data-endpoint="<?= url('task/pauseExecution'); ?>" data-task-assignee-id="<?= (int) $assignee['id']; ?>" type="button" <?= $isRunning ? '' : 'disabled'; ?>><i class="bi bi-pause-fill"></i></button>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="task-section">
    <h6>Histórico de execução</h6>
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle">
        <thead>
          <tr>
            <th>Responsável</th>
            <th>Início</th>
            <th>Pausa</th>
            <th>Tempo</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($executions)): ?>
            <?php foreach ($executions as $execution): ?>
              <tr>
                <td><?= htmlspecialchars((string) ($execution['person_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?= htmlspecialchars(\App\Models\Task::formatDateTime($execution['started_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?= htmlspecialchars(\App\Models\Task::formatDateTime($execution['paused_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?= htmlspecialchars(\App\Models\Task::formatDuration((int) ($execution['elapsed_seconds'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="4" class="text-center">Nenhuma execução registrada.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="task-section">
    <h6>Comentários</h6>
    <?php if (authCan('task.commentStore')): ?>
      <form action="<?= url('task/commentStore/' . (int) $task['id']); ?>" method="POST" class="mb-3">
        <label class="form-label" for="taskComment">Novo comentário</label>
        <textarea class="form-control" id="taskComment" name="comment" required rows="3"></textarea>
        <div class="mt-2 text-end">
          <button class="btn btn-primary" type="submit"><i class="bi bi-chat-left-text"></i> Comentar</button>
        </div>
      </form>
    <?php endif; ?>

    <div class="task-comments">
      <?php if (!empty($comments)): ?>
        <?php foreach ($comments as $comment): ?>
          <article class="task-comment">
            <div class="task-comment-head">
              <strong><?= htmlspecialchars((string) ($comment['user_name'] ?? $comment['username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong>
              <small class="text-muted"><?= htmlspecialchars(\App\Models\Task::formatDateTime($comment['created'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></small>
            </div>
            <p><?= nl2br(htmlspecialchars((string) ($comment['comment'] ?? ''), ENT_QUOTES, 'UTF-8')); ?></p>
          </article>
        <?php endforeach; ?>
      <?php else: ?>
        <p class="text-muted mb-0">Nenhum comentário cadastrado.</p>
      <?php endif; ?>
    </div>
  </section>

  <div class="form-actions">
    <a href="<?= url('task'); ?>" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
    <?php if (authCan('task.edit')): ?>
      <a href="<?= url('task/edit/' . (int) $task['id']); ?>" class="btn btn-primary"><i class="bi bi-pencil-square"></i> Editar</a>
    <?php endif; ?>
  </div>
</div>

<script src="<?= url('assets/js/tasks/informations.js?v=1'); ?>"></script>
