<?php
// Expected variables: $orphaned, $stats, $title

$hiddenOrphanedAbilities = [
	'permission.manage' => true,
];
$visibleOrphaned = [];

foreach ($orphaned as $perm)
	{
	$ability = (string) ($perm['ability'] ?? '');
	if (isset($hiddenOrphanedAbilities[$ability]))
		{
		continue;
		}
	$visibleOrphaned[] = $perm;
	}

$totalOrphaned = count($visibleOrphaned);
$orphanedWithUsers = 0;
foreach ($visibleOrphaned as $perm)
	{
	$permissionId = (int) ($perm['id'] ?? 0);
	$userCount = (int) ($stats['permissions'][$permissionId]['user_count'] ?? 0);
	if ($userCount > 0)
		{
		$orphanedWithUsers++;
		}
	}
?>
<link rel="stylesheet" href="<?= url('assets/css/permissions/orphaned.css?v=1'); ?>">

<div class="permissions-orphaned-page app-page">
  <div class="permissions-orphaned-header app-page-header">
    <div>
      <span class="permissions-orphaned-kicker app-page-kicker"><i class="bi bi-shield-exclamation"></i> Segurança</span>
      <h5>Permissões órfãs</h5>
      <p class="permissions-orphaned-subtitle">
        Estas permissões existem no catálogo do sistema <code>manager</code>, mas não foram encontradas nos controllers atuais.
      </p>
    </div>
    <div class="permissions-orphaned-actions">
      <a href="<?= url('permission/orphaned-export-pdf'); ?>" class="btn btn-outline-danger">
        <i class="bi bi-file-earmark-pdf"></i> Exportar PDF
      </a>
      <a href="<?= url('permission/orphaned-export-excel'); ?>" class="btn btn-outline-success">
        <i class="bi bi-file-earmark-spreadsheet"></i> Exportar Excel
      </a>
    </div>
  </div>

  <section class="permissions-orphaned-summary app-section">
    <div class="permissions-orphaned-summary-item">
      <span class="permissions-summary-icon"><i class="bi bi-list-check"></i></span>
      <div>
        <strong><?= (int) $totalOrphaned; ?></strong>
        <small>permissão(ões) órfã(s)</small>
      </div>
    </div>
    <div class="permissions-orphaned-summary-item">
      <span class="permissions-summary-icon permissions-summary-icon-warning"><i class="bi bi-people"></i></span>
      <div>
        <strong><?= (int) $orphanedWithUsers; ?></strong>
        <small>com usuários vinculados</small>
      </div>
    </div>
    <p class="mb-0 text-muted">
      Ao remover uma permissão órfã, os vínculos existentes em usuários também serão excluídos.
    </p>
  </section>

  <?php if ($totalOrphaned === 0): ?>
    <section class="permissions-orphaned-empty app-section">
      <span class="permissions-empty-icon"><i class="bi bi-check2-circle"></i></span>
      <div>
        <h6>Nenhuma permissão órfã encontrada</h6>
        <p class="mb-0 text-muted">O catálogo <code>manager</code> está alinhado com os controllers atuais.</p>
      </div>
    </section>
  <?php else: ?>
    <section class="permissions-orphaned-card app-section">
      <div class="permissions-orphaned-card-header">
        <div>
          <span class="permissions-section-label">Catálogo</span>
          <h6><?= (int) $totalOrphaned; ?> permissão(ões) para revisar</h6>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0 permissions-orphaned-table">
          <thead>
            <tr>
              <th>Ability</th>
              <th>Descrição</th>
              <th class="text-center">Usuários afetados</th>
              <th class="text-end">Ações</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($visibleOrphaned as $perm): ?>
              <?php
                $permissionId = (int) ($perm['id'] ?? 0);
                $userCount = (int) ($stats['permissions'][$permissionId]['user_count'] ?? 0);
              ?>
              <tr class="<?= ($userCount > 0 ? 'permissions-orphaned-row-warning' : ''); ?>">
                <td><code class="permission-ability"><?= htmlspecialchars((string) ($perm['ability'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code></td>
                <td class="permission-description"><?= htmlspecialchars((string) ($perm['description'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                <td class="text-center">
                  <?php if ($userCount > 0): ?>
                    <span class="badge text-bg-danger"><?= (int) $userCount; ?></span>
                  <?php else: ?>
                    <span class="permissions-zero-count">0</span>
                  <?php endif; ?>
                </td>
                <td style="width:1%;" class="text-nowrap text-end">
                  <a href="#"
                     class="js-orphaned-delete-link text-danger"
                     data-bs-toggle="modal"
                     data-bs-target="#orphanedDeleteModal"
                     data-orphaned-id="<?= (int) $permissionId; ?>"
                     data-orphaned-ability="<?= htmlspecialchars((string) ($perm['ability'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                     data-orphaned-user-count="<?= (int) $userCount; ?>"
                     data-orphaned-delete-url="<?= url('permission/deleteOrphaned/' . (int) $permissionId); ?>"
                     data-bs-content="Excluir"
                     data-bs-trigger="hover focus"
                     role="button"
                     aria-label="Excluir permissão órfã">
                    <i class="bi bi-trash"></i>
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
  <?php endif; ?>

  <div class="view-actions">
    <a href="<?= url('permission'); ?>" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
  </div>
</div>

<div class="modal fade" id="orphanedDeleteModal" tabindex="-1" aria-labelledby="orphanedDeleteModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="orphanedDeleteForm" method="POST" action="">
        <div class="modal-header">
          <h5 class="modal-title" id="orphanedDeleteModalLabel">Confirmar exclusão</h5>
          <a href="#" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></a>
        </div>
        <div class="modal-body">
          <p class="mb-0">Deseja realmente excluir a permissão órfã <strong id="orphanedDeleteName">#</strong>?</p>
          <div id="orphanedAffectedUsers" class="alert alert-warning mt-3 mb-0">
            Esta operação removerá a permissão de <strong><span id="orphanedUserCount">0</span></strong> usuário(s).
          </div>
        </div>
        <div class="modal-footer">
          <a href="#" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</a>
          <button type="submit" class="btn btn-danger">Excluir</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="<?= url('assets/js/permissions/orphaned.js?v=2'); ?>"></script>
