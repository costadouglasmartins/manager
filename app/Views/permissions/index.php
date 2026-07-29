<?php
// Expected variables:
// $permissions, $users, $selectedUserId, $userPermissionIds, $effectiveAbilities

$permGroup = static function (string $ability): string {
	$pos = strpos($ability, '.');
	if ($pos === false)
		{
		return 'outros';
		}
	return substr($ability, 0, $pos);
};

$groups = [];
foreach ($permissions as $p)
	{
	$ability = (string) $p['ability'];
	$group = $permGroup($ability);
	if (!isset($groups[$group]))
		{
		$groups[$group] = [];
		}
	$groups[$group][] = $p;
	}
ksort($groups);

	$queryFilters = http_build_query([
		'user_id' => (int) ($selectedUserId ?? 0),
	]);

$selectedPermissionIds = array_values(array_unique(array_filter(array_map('intval', (array) ($userPermissionIds ?? [])))));
$totalPermissions = count($permissions);
$selectedPermissionsTotal = count($selectedPermissionIds);
?>
<link rel="stylesheet" href="<?= url('assets/css/permissions/index.css?v=1'); ?>">

<div class="permissions-page app-page">
  <div class="permissions-page-header app-page-header">
    <div>
      <span class="permissions-page-kicker app-page-kicker"><i class="bi bi-shield-lock"></i> Segurança</span>
      <h5>Gerenciar permissões</h5>
      <p class="permissions-page-subtitle">
        Permissões atribuídas diretamente por usuário, sem alterar perfis, grupos ou regras de negócio.
      </p>
    </div>
    <div class="permissions-header-stats" aria-label="Resumo do catálogo de permissões">
      <span class="permissions-stat">
        <strong><?= (int) $totalPermissions; ?></strong>
        <small>permissões</small>
      </span>
      <span class="permissions-stat">
        <strong><?= (int) count($groups); ?></strong>
        <small>grupos</small>
      </span>
      <span class="permissions-stat">
        <strong><?= (int) $selectedPermissionsTotal; ?></strong>
        <small>ativas</small>
      </span>
    </div>
  </div>

  <section class="permissions-toolbar app-section">
    <div class="permissions-toolbar-main">
      <form method="POST" action="<?= url('permission/sync'); ?>" class="permissions-inline-form">
        <input type="hidden" name="selected_user_id" value="<?= (int) ($selectedUserId ?? 0); ?>">
        <button class="btn btn-primary" type="submit">
          <i class="bi bi-arrow-repeat"></i> Sincronizar permissões
        </button>
      </form>

      <a href="<?= url('permission/orphaned'); ?>" class="btn btn-warning">
        <i class="bi bi-exclamation-triangle"></i> Permissões órfãs
      </a>

      <button class="btn btn-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#addPermCatalog" aria-expanded="false" aria-controls="addPermCatalog">
        <i class="bi bi-plus-lg"></i> Adicionar manualmente
      </button>
    </div>

    <div class="permissions-toolbar-export">
      <?= pdfExportButton('permission', $queryFilters); ?>
      <?= excelExportButton('permission', $queryFilters); ?>
    </div>
  </section>

  <div class="collapse" id="addPermCatalog">
    <section class="permissions-manual-panel app-section">
      <div class="permissions-section-heading">
        <div>
          <span class="permissions-section-label">Catálogo</span>
          <h6>Adicionar permissão manualmente</h6>
        </div>
      </div>
      <form method="POST" action="<?= url('permission/create'); ?>" class="row g-3 align-items-end">
        <input type="hidden" name="selected_user_id" value="<?= (int) ($selectedUserId ?? 0); ?>">
        <div class="col-12 col-md-4">
          <label class="form-label" for="permissionAbility">Nova ability</label>
          <input class="form-control" id="permissionAbility" name="ability" placeholder="ex.: report.index" required>
        </div>
        <div class="col-12 col-md-6">
          <label class="form-label" for="permissionDescription">Descrição</label>
          <input class="form-control" id="permissionDescription" name="description" placeholder="Opcional">
        </div>
        <div class="col-12 col-md-2">
          <button class="btn btn-primary w-100" type="submit">Adicionar</button>
        </div>
        <div class="col-12">
          <small class="text-muted">
            Depois de criar a ability, conceda-a ao usuário abaixo para efetivar o acesso.
          </small>
        </div>
      </form>
    </section>
  </div>

  <form method="GET" action="<?= url('permission'); ?>" class="permissions-user-form">
    <section class="permissions-user-section app-section">
      <div class="permissions-section-heading">
        <div>
          <span class="permissions-section-label">Usuário</span>
          <h6>Selecione quem receberá as permissões</h6>
        </div>
      </div>
      <div class="permissions-user-select">
        <select name="user_id" class="form-select" onchange="this.form.submit()" aria-label="Selecionar usuário">
          <option value="0">Selecione um usuário...</option>
          <?php foreach ($users as $u): ?>
            <?php
              $uid = (int) $u['id'];
              $label = trim((string) $u['username']);
              if (!empty($u['email']))
                {
                $label .= ' (' . $u['email'] . ')';
                }
              if (!empty($u['type']))
                {
                $label .= ' [' . $u['type'] . ']';
                }
            ?>
            <option value="<?= $uid; ?>" <?= ($uid === (int) $selectedUserId ? 'selected' : ''); ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
          <?php endforeach; ?>
        </select>
        <small class="text-muted">Marque ou desmarque as funcionalidades para o usuário selecionado.</small>
      </div>
    </section>
  </form>

  <?php if ((int) $selectedUserId <= 0): ?>
    <section class="permissions-empty-state app-section">
      <span class="permissions-empty-icon"><i class="bi bi-person-check"></i></span>
      <div>
        <h6>Selecione um usuário para editar permissões</h6>
        <p class="mb-0 text-muted">O catálogo permanece visível somente depois que um usuário é escolhido.</p>
      </div>
    </section>
  <?php else: ?>

    <form method="POST" action="<?= url('permission/saveUser'); ?>" class="permissions-assignment-form">
      <input type="hidden" name="user_id" value="<?= (int) $selectedUserId; ?>">

      <section class="permissions-master-panel app-section">
        <div class="permissions-master-text">
          <span class="permissions-section-label">Permissões</span>
          <h6>Todas as permissões do catálogo</h6>
          <small class="text-muted">Use os controles globais ou de cada grupo para acelerar a seleção.</small>
        </div>
        <div class="form-check form-switch permissions-switch m-0">
          <input class="form-check-input" type="checkbox" id="permToggleAll" title="Marcar/desmarcar todas as permissões">
          <label class="form-check-label" for="permToggleAll">Todas</label>
        </div>
      </section>

      <div class="permissions-groups-grid">
        <?php $groupIndex = 0; ?>
        <?php foreach ($groups as $groupName => $items): ?>
          <?php
            $groupIndex++;
            $groupSelectedCount = 0;
            foreach ($items as $p)
              {
              if (in_array((int) $p['id'], $selectedPermissionIds, true))
                {
                $groupSelectedCount++;
                }
              }
          ?>
          <section class="permission-group-card">
            <header class="permission-group-header">
              <div>
                <span class="permission-group-name"><?= htmlspecialchars($groupName, ENT_QUOTES, 'UTF-8'); ?></span>
                <small><?= (int) $groupSelectedCount; ?> de <?= (int) count($items); ?> ativa(s)</small>
              </div>
              <div class="form-check form-switch permissions-switch m-0">
                <input class="form-check-input perm-group-toggle" type="checkbox" id="permGroupToggle<?= (int) $groupIndex; ?>" data-group="<?= (int) $groupIndex; ?>" title="Marcar/desmarcar todas deste grupo">
                <label class="form-check-label" for="permGroupToggle<?= (int) $groupIndex; ?>">Todas</label>
              </div>
            </header>

            <div class="permission-group-table table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead>
                  <tr>
                    <th class="permission-toggle-column">On</th>
                    <th>Ability</th>
                    <th>Descrição</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($items as $p): ?>
                    <?php
                      $pid = (int) $p['id'];
                      $checked = in_array($pid, $selectedPermissionIds, true);
                    ?>
                    <tr>
                      <td class="text-center">
                        <input class="form-check-input" type="checkbox" name="permission_ids[]" value="<?= $pid; ?>" data-group="<?= (int) $groupIndex; ?>" aria-label="Permissão <?= htmlspecialchars((string) $p['ability'], ENT_QUOTES, 'UTF-8'); ?>" <?= ($checked ? 'checked' : ''); ?>>
                      </td>
                      <td><code class="permission-ability"><?= htmlspecialchars((string) $p['ability'], ENT_QUOTES, 'UTF-8'); ?></code></td>
                      <td class="permission-description"><?= htmlspecialchars((string) ($p['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </section>
        <?php endforeach; ?>
      </div>

      <div class="collapse permissions-effective-collapse" id="effectivePerms">
        <section class="permissions-effective-panel app-section">
          <div class="permissions-section-heading">
            <div>
              <span class="permissions-section-label">Usuário selecionado</span>
              <h6>Permissões ativas</h6>
            </div>
          </div>
          <?php if (empty($effectiveAbilities)): ?>
            <div class="text-muted">Nenhuma permissão ativa encontrada.</div>
          <?php else: ?>
            <div class="permissions-effective-list">
              <?php foreach ($effectiveAbilities as $ab): ?>
                <span class="permission-badge"><code><?= htmlspecialchars((string) $ab, ENT_QUOTES, 'UTF-8'); ?></code></span>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>
      </div>

      <div class="form-actions permissions-save-actions">
        <a class="btn btn-secondary" href="<?= url('permission?user_id=' . (int) $selectedUserId); ?>">
          <i class="bi bi-arrow-clockwise"></i> Recarregar
        </a>
        <button class="btn btn-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#effectivePerms" aria-expanded="false" aria-controls="effectivePerms">
          <i class="bi bi-list-check"></i> Ver permissões ativas
        </button>
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-check-lg"></i> Salvar permissões
        </button>
      </div>
    </form>

  <?php endif; ?>
</div>

<script src="<?= url('assets/js/permissions/index.js?v=1'); ?>"></script>
