<?php
$filters = $filters ?? ['id' => '', 'name' => '', 'is_final' => '', 'active' => ''];
$page = $page ?? 1;
$totalPages = $totalPages ?? 1;
$total = $total ?? (is_array($activityStatuses) ? count($activityStatuses) : 0);
$queryFilters = http_build_query([
	'id' => $filters['id'],
	'name' => $filters['name'],
	'is_final' => $filters['is_final'],
	'active' => $filters['active'],
]);
?>

<div class="app-page">
  <div class="app-page-header">
    <div>
      <span class="app-page-kicker"><i class="bi bi-ui-checks"></i> CRM</span>
      <h5>Status de atividade CRM</h5>
    </div>
  </div>

  <?php if (!empty($formError)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars((string) $formError, ENT_QUOTES, 'UTF-8'); ?></div>
  <?php endif; ?>

  <form action="<?= url('crm_activity_status'); ?>" class="row g-3 mb-3" method="get">
    <div class="col-12 col-lg-9">
      <div class="row g-3">
        <div class="col-12 col-md-2">
          <input class="form-control" id="id" name="id" placeholder="Código" type="text" value="<?= htmlspecialchars($filters['id'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="col-12 col-md-4">
          <input class="form-control" id="name" maxlength="120" name="name" placeholder="Nome" type="text" value="<?= htmlspecialchars($filters['name'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="col-12 col-md-3">
          <select class="form-select" id="isFinal" name="is_final">
            <option value="" <?= $filters['is_final'] === '' ? 'selected' : ''; ?>>Todos os finais</option>
            <option value="1" <?= $filters['is_final'] === '1' ? 'selected' : ''; ?>>Finais</option>
            <option value="0" <?= $filters['is_final'] === '0' ? 'selected' : ''; ?>>Não finais</option>
          </select>
        </div>

        <div class="col-12 col-md-3">
          <select class="form-select" id="active" name="active">
            <option value="" <?= $filters['active'] === '' ? 'selected' : ''; ?>>Todos</option>
            <option value="1" <?= $filters['active'] === '1' ? 'selected' : ''; ?>>Ativos</option>
            <option value="0" <?= $filters['active'] === '0' ? 'selected' : ''; ?>>Inativos</option>
          </select>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-3">
      <div class="d-flex flex-column gap-2 align-items-center align-items-lg-end">
        <button type="submit" class="btn btn-primary w-100">Pesquisar</button>
        <a href="<?= url('crm_activity_status'); ?>" class="btn btn-secondary w-100">Limpar</a>
        <?php if (authCan('crm_activity_status.create')): ?>
          <a href="<?= url('crm_activity_status/create'); ?>" class="btn btn-success w-100">Novo(a)</a>
        <?php endif; ?>
      </div>
    </div>
  </form>

  <hr>

  <div class="table-responsive">
    <table class="table table-striped table-hover align-middle">
      <thead>
        <tr>
          <th>#</th>
          <th>Nome</th>
          <th>Descrição</th>
          <th>Final?</th>
          <th>Ordem</th>
          <th>Status</th>
          <th class="text-end">Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($activityStatuses)): ?>
          <?php foreach ($activityStatuses as $row): ?>
            <tr>
              <td style="width:1%;"><?= (int) $row['id']; ?></td>
              <td><span class="badge <?= htmlspecialchars((string) ($row['bootstrap_class'] ?? 'text-bg-secondary'), ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars((string) ($row['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span></td>
              <td><?= nl2br(htmlspecialchars((string) ($row['description'] ?? ''), ENT_QUOTES, 'UTF-8')); ?></td>
              <td><span class="badge <?= ((int) ($row['is_final'] ?? 0) === 1) ? 'text-bg-success' : 'text-bg-light text-dark'; ?>"><?= ((int) ($row['is_final'] ?? 0) === 1) ? 'Sim' : 'Não'; ?></span></td>
              <td><?= (int) ($row['sort_order'] ?? 0); ?></td>
              <td><span class="badge <?= ((int) ($row['active'] ?? 0) === 1) ? 'text-bg-success' : 'text-bg-secondary'; ?>"><?= ((int) ($row['active'] ?? 0) === 1) ? 'Ativo' : 'Inativo'; ?></span></td>
              <td style="width:1%;" class="text-nowrap text-end">
                <?php if (authCan('crm_activity_status.delete') && (int) ($row['active'] ?? 0) === 1): ?>
                  <a href="#"
                     class="js-record-delete-link text-danger"
                     data-bs-toggle="modal"
                     data-bs-target="#recordDeleteModal"
                     data-record-id="<?= (int) $row['id']; ?>"
                     data-record-name="<?= htmlspecialchars((string) ($row['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                     data-record-delete-description="o status de atividade CRM"
                     data-record-delete-url="<?= url('crm_activity_status/delete/' . (int) $row['id']); ?>"
                     data-bs-content="Desativar"
                     data-bs-trigger="hover focus"
                     role="button"
                     aria-label="Desativar status de atividade CRM">
                    <i class="bi bi-trash"></i>
                  </a>
                <?php endif; ?>
                <?php if (authCan('crm_activity_status.edit')): ?>
                  <a href="<?= url('crm_activity_status/edit/' . (int) $row['id']); ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Editar"><i class="bi bi-pencil-square"></i></a>
                <?php endif; ?>
                <?php if (authCan('crm_activity_status.informations')): ?>
                  <a href="<?= url('crm_activity_status/informations/' . (int) $row['id']); ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Saiba mais"><i class="bi bi-search"></i></a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="7" class="text-center">Nenhum status de atividade encontrado.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <p class="text-muted"><?= (int) $total; ?> registro(s) encontrado(s)</p>

  <?php if ($totalPages > 1): ?>
    <?= paginationLinks('crm_activity_status', $page, $totalPages, $queryFilters, 2, 'Anterior', 'Próxima'); ?>
  <?php endif; ?>
</div>

<?php if (authCan('crm_activity_status.delete')): ?>
  <?php require __DIR__ . '/../shared/delete_modal.php'; ?>
<?php endif; ?>

<script src="<?= url('assets/js/shared/delete_modal.js?v=1'); ?>"></script>
