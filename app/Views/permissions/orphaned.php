<?php
// Expected variables: $orphaned, $stats, $title

$deleted = null;
if (isset($_GET['deleted']))
	{
	$deleted = (int) $_GET['deleted'];
	}

$usersAffected = null;
if (isset($_GET['users_affected']))
	{
	$usersAffected = (int) $_GET['users_affected'];
	}

$error = null;
if (isset($_GET['error']))
	{
	$error = (string) $_GET['error'];
	}
?>

<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item"><a href="<?= url('permission'); ?>">Permissões</a></li>
    <li class="breadcrumb-item active" aria-current="page">Permissões Órfãs</li>
  </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h5>Permissões Órfãs</h5>
  <a href="<?= url('permission'); ?>" class="btn btn-secondary btn-sm">Voltar</a>
</div>

<p class="text-muted">
  Estas permissões existem no catálogo do sistema <code>manager</code>, mas não foram encontradas nos controllers atuais.
  Ao removê-las, os vínculos existentes em usuários também serão excluídos.
</p>

<?php if ($deleted === 1): ?>
  <div class="alert alert-success py-2">
    <strong>Permissão órfã removida com sucesso.</strong>
    <?php if ($usersAffected !== null): ?>
      <br><small><?= (int) $usersAffected; ?> usuário(s) afetado(s).</small>
    <?php endif; ?>
  </div>
<?php elseif ($deleted === 0): ?>
  <div class="alert alert-danger py-2">
    <strong>Não foi possível remover a permissão órfã.</strong>
    <?php if ($error === 'not_orphaned'): ?>
      <br><small>Ela não é mais órfã ou não pertence ao catálogo esperado.</small>
    <?php elseif ($error === 'exception'): ?>
      <br><small>Ocorreu um erro ao processar a exclusão.</small>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if (($stats['total_orphaned'] ?? 0) === 0): ?>
  <div class="alert alert-info mb-0">
    Nenhuma permissão órfã encontrada no catálogo <code>manager</code>.
  </div>
<?php else: ?>
  <div class="card">
    <div class="card-header bg-light">
      <strong><?= (int) ($stats['total_orphaned'] ?? 0); ?> permissão(ões) órfã(s)</strong>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Ability</th>
            <th>Descrição</th>
            <th class="text-center">Usuários afetados</th>
            <th class="text-center">Ação</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($orphaned as $perm): ?>
            <?php
              $permissionId = (int) ($perm['id'] ?? 0);
              $userCount = (int) ($stats['permissions'][$permissionId]['user_count'] ?? 0);
            ?>
            <tr class="<?= ($userCount > 0 ? 'table-warning' : ''); ?>">
              <td><code><?= htmlspecialchars((string) ($perm['ability'] ?? '')); ?></code></td>
              <td><?= htmlspecialchars((string) ($perm['description'] ?? '-')); ?></td>
              <td class="text-center">
                <?php if ($userCount > 0): ?>
                  <span class="badge text-bg-danger"><?= $userCount; ?></span>
                <?php else: ?>
                  <span class="text-muted">0</span>
                <?php endif; ?>
              </td>
              <td class="text-center">
                <button
                  class="btn btn-sm btn-danger"
                  type="button"
                  data-bs-toggle="modal"
                  data-bs-target="#deleteModal"
                  data-id="<?= $permissionId; ?>"
                  data-ability="<?= htmlspecialchars((string) ($perm['ability'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                  data-user-count="<?= $userCount; ?>"
                >
                  Remover
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Confirmar remoção de permissão órfã</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <p>Tem certeza que deseja remover esta permissão do catálogo?</p>
        <div class="bg-light p-3 rounded border mb-3">
          <code id="orphanedAbilityName" class="fw-semibold"></code>
        </div>
        <div id="orphanedAffectedUsers" class="alert alert-warning mb-0">
          Esta operação removerá a permissão de <strong><span id="orphanedUserCount">0</span></strong> usuário(s).
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <form method="POST" action="#" id="deleteOrphanedForm" class="d-inline">
          <button type="submit" class="btn btn-danger">Remover Permissão</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
document.getElementById('deleteModal').addEventListener('show.bs.modal', function (event) {
  const trigger = event.relatedTarget;
  const permissionId = trigger.getAttribute('data-id');
  const ability = trigger.getAttribute('data-ability') || '';
  const userCount = parseInt(trigger.getAttribute('data-user-count') || '0', 10);

  document.getElementById('orphanedAbilityName').textContent = ability;
  document.getElementById('orphanedUserCount').textContent = String(userCount);
  document.getElementById('deleteOrphanedForm').action = '<?= url('permission/deleteOrphaned'); ?>/' + permissionId;
});
</script>
