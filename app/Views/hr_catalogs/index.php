<?php
// Variables expected from HrDepartmentController / HrPositionController::index: $catalogMeta (resource, icon, label, list_title,
// delete_description, empty_message), $catalogRows (rows with id, name, description, active), $filters (id, name, active),
// $page, $perPage, $total, $totalPages. $data is accepted as an alias of $catalogRows.
$esc = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$catalogMeta = (isset($catalogMeta) && is_array($catalogMeta)) ? $catalogMeta : [];
$catalogRows = (isset($catalogRows) && is_array($catalogRows)) ? $catalogRows : ((isset($data) && is_array($data)) ? $data : []);
$resource = (string) ($catalogMeta['resource'] ?? '');
$label = (string) ($catalogMeta['label'] ?? 'cadastro');
$filters = array_merge(['id' => '', 'name' => '', 'active' => ''], (isset($filters) && is_array($filters)) ? $filters : []);
$page = max(1, (int) ($page ?? 1));
$totalPages = max(1, (int) ($totalPages ?? 1));
$total = (int) ($total ?? count($catalogRows));
$queryFilters = http_build_query(['id' => $filters['id'], 'name' => $filters['name'], 'active' => $filters['active']]);
?>
<div class="app-page">
  <div class="app-page-header">
    <div>
      <span class="app-page-kicker"><i class="bi <?= $esc($catalogMeta['icon'] ?? 'bi-diagram-3'); ?>" aria-hidden="true"></i> RH</span>
      <h5><?= $esc($catalogMeta['list_title'] ?? 'Cadastros de RH'); ?></h5>
    </div>
  </div>

  <?php if (!empty($formError)): ?>
    <div class="alert alert-danger" role="alert"><?= $esc($formError); ?></div>
  <?php endif; ?>

  <form action="<?= url($resource); ?>" class="row g-3" method="get">
    <div class="col-12 col-lg-9">
      <div class="row g-3">
        <div class="col-12 col-md-3">
          <label class="visually-hidden" for="catalogId">Código</label>
          <input class="form-control" id="catalogId" name="id" placeholder="Código" type="text" inputmode="numeric" value="<?= $esc($filters['id']); ?>">
        </div>

        <div class="col-12 col-md-5">
          <label class="visually-hidden" for="catalogName">Nome</label>
          <input class="form-control" id="catalogName" maxlength="120" name="name" placeholder="Nome" type="text" value="<?= $esc($filters['name']); ?>">
        </div>

        <div class="col-12 col-md-4">
          <label class="visually-hidden" for="catalogActive">Status</label>
          <select class="form-select" id="catalogActive" name="active">
            <option value="" <?= (string) $filters['active'] === '' ? 'selected' : ''; ?>>Ativos e inativos</option>
            <option value="1" <?= (string) $filters['active'] === '1' ? 'selected' : ''; ?>>Ativos</option>
            <option value="0" <?= (string) $filters['active'] === '0' ? 'selected' : ''; ?>>Inativos</option>
          </select>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-3">
      <div class="d-flex flex-column gap-2 align-items-center align-items-lg-end">
        <button class="btn btn-primary w-100" type="submit">Pesquisar</button>
        <a class="btn btn-secondary w-100" href="<?= url($resource); ?>">Limpar</a>
        <?php if (authCan($resource . '.create')): ?>
          <a class="btn btn-success w-100" href="<?= url($resource . '/create'); ?>">Novo(a)</a>
        <?php endif; ?>
      </div>
    </div>
  </form>

  <div class="d-flex flex-wrap gap-2">
    <?php if (authCan($resource . '.index')): ?>
      <?= pdfExportButton($resource, $queryFilters); ?>
      <?= excelExportButton($resource, $queryFilters); ?>
    <?php endif; ?>
  </div>

  <div class="table-responsive">
    <table class="table table-striped table-hover align-middle">
      <thead>
        <tr>
          <th>#</th>
          <th>Nome</th>
          <th>Descrição</th>
          <th>Status</th>
          <th class="text-end">Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($catalogRows !== []): ?>
          <?php foreach ($catalogRows as $row): ?>
            <?php $rowId = (int) ($row['id'] ?? 0); ?>
            <tr>
              <td style="width:1%;"><?= $rowId; ?></td>
              <td class="fw-semibold"><?= $esc($row['name'] ?? ''); ?></td>
              <td><?= nl2br($esc($row['description'] ?? '')); ?></td>
              <td><span class="badge <?= (int) ($row['active'] ?? 0) === 1 ? 'text-bg-success' : 'text-bg-secondary'; ?>"><?= (int) ($row['active'] ?? 0) === 1 ? 'Ativo' : 'Inativo'; ?></span></td>
              <td class="text-nowrap text-end">
                <?php if (authCan($resource . '.delete')): ?>
                  <a aria-label="Excluir <?= $esc($label); ?>" class="js-record-delete-link text-danger" data-bs-content="Excluir" data-bs-target="#recordDeleteModal" data-bs-toggle="modal" data-bs-trigger="hover focus" data-record-delete-description="<?= $esc($catalogMeta['delete_description'] ?? 'o cadastro'); ?>" data-record-delete-url="<?= url($resource . '/delete/' . $rowId); ?>" data-record-id="<?= $rowId; ?>" data-record-name="<?= $esc($row['name'] ?? ''); ?>" href="#" role="button"><i class="bi bi-trash" aria-hidden="true"></i></a>
                <?php endif; ?>
                <?php if (authCan($resource . '.edit')): ?>
                  <a aria-label="Editar <?= $esc($label); ?>" data-bs-content="Editar" data-bs-toggle="popover" data-bs-trigger="hover focus" href="<?= url($resource . '/edit/' . $rowId); ?>"><i class="bi bi-pencil-square" aria-hidden="true"></i></a>
                <?php endif; ?>
                <?php if (authCan($resource . '.informations')): ?>
                  <a aria-label="Ver <?= $esc($label); ?>" data-bs-content="Saiba mais" data-bs-toggle="popover" data-bs-trigger="hover focus" href="<?= url($resource . '/informations/' . $rowId); ?>"><i class="bi bi-search" aria-hidden="true"></i></a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td class="text-center" colspan="5"><?= $esc($catalogMeta['empty_message'] ?? 'Nenhum cadastro encontrado.'); ?></td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <p class="text-muted mb-0"><?= $total; ?> registro(s) encontrado(s)</p>

  <?php if ($totalPages > 1): ?>
    <?= paginationLinks($resource, $page, $totalPages, $queryFilters, 2, 'Anterior', 'Próxima'); ?>
  <?php endif; ?>
</div>

<?php if (authCan($resource . '.delete')): ?>
  <?php require __DIR__ . '/../shared/delete_modal.php'; ?>
  <script src="<?= url('assets/js/shared/delete_modal.js?v=1'); ?>"></script>
<?php endif; ?>
