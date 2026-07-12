<?php
$filters = $filters ?? ['id' => '', 'name' => '', 'active' => ''];
$page = $page ?? 1;
$totalPages = $totalPages ?? 1;
$total = $total ?? (is_array($categories) ? count($categories) : 0);
$queryFilters = http_build_query([
	'id' => $filters['id'],
	'name' => $filters['name'],
	'active' => $filters['active'],
]);
?>
<link rel="stylesheet" href="<?= url('assets/css/schedule_categories/index.css?v=1'); ?>">

<h5>Lista de Categorias da Agenda</h5>

<?php if (!empty($formError)): ?>
  <div class="alert alert-danger"><?= htmlspecialchars((string) $formError, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>

<form action="<?= url('schedule_category'); ?>" class="row g-3 mb-3" method="get">
  <div class="col-12 col-lg-9">
    <div class="row g-3">
      <div class="col-12 col-md-3">
        <input class="form-control" id="id" name="id" type="text" placeholder="Código" value="<?= htmlspecialchars($filters['id'], ENT_QUOTES, 'UTF-8'); ?>">
      </div>
      <div class="col-12 col-md-6">
        <input class="form-control" id="name" maxlength="120" name="name" type="text" placeholder="Nome" value="<?= htmlspecialchars($filters['name'], ENT_QUOTES, 'UTF-8'); ?>">
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
      <a href="<?= url('schedule_category'); ?>" class="btn btn-secondary w-100">Limpar</a>
      <?php if (authCan('schedule_category.create')): ?>
        <a href="<?= url('schedule_category/create'); ?>" class="btn btn-success w-100">Novo(a)</a>
      <?php endif; ?>
    </div>
  </div>
</form>

<div class="d-flex flex-wrap gap-2 mb-3">
  <?php if (authCan('schedule_category.index')): ?>
    <?= pdfExportButton('schedule_category', $queryFilters); ?>
    <?= excelExportButton('schedule_category', $queryFilters); ?>
  <?php endif; ?>
</div>

<hr>

<div class="table-responsive">
  <table class="table table-striped table-hover align-middle">
    <thead>
      <tr>
        <th>#</th>
        <th>Nome</th>
        <th>Cores</th>
        <th>Descrição</th>
        <th>Status</th>
        <th class="text-end">Ações</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!empty($categories)): ?>
        <?php foreach ($categories as $row): ?>
          <tr>
            <td style="width:1%;"><?= (int) $row['id']; ?></td>
            <td><?= htmlspecialchars((string) $row['name'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td>
              <span class="schedule-category-sample" style="background-color: <?= htmlspecialchars((string) $row['background_color'], ENT_QUOTES, 'UTF-8'); ?>; color: <?= htmlspecialchars((string) $row['description_color'], ENT_QUOTES, 'UTF-8'); ?>;">Categoria</span>
            </td>
            <td><?= (string) ($row['description'] ?? ''); ?></td>
            <td><span class="badge <?= ((int) $row['active'] === 1) ? 'text-bg-success' : 'text-bg-secondary'; ?>"><?= ((int) $row['active'] === 1) ? 'Ativo' : 'Inativo'; ?></span></td>
            <td style="width:1%;" class="text-nowrap text-end">
              <?php if (authCan('schedule_category.delete')): ?>
                <a href="#" class="js-record-delete-link text-danger" data-bs-toggle="modal" data-bs-target="#recordDeleteModal" data-record-id="<?= (int) $row['id']; ?>" data-record-name="<?= htmlspecialchars((string) $row['name'], ENT_QUOTES, 'UTF-8'); ?>" data-record-delete-description="a categoria" data-record-delete-url="<?= url('schedule_category/delete/' . (int) $row['id']); ?>" data-bs-content="Excluir" data-bs-trigger="hover focus" role="button" aria-label="Excluir categoria da agenda"><i class="bi bi-trash"></i></a>
              <?php endif; ?>
              <?php if (authCan('schedule_category.edit')): ?>
                <a href="<?= url('schedule_category/edit/' . (int) $row['id']); ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Editar"><i class="bi bi-pencil-square"></i></a>
              <?php endif; ?>
              <?php if (authCan('schedule_category.informations')): ?>
                <a href="<?= url('schedule_category/informations/' . (int) $row['id']); ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Saiba mais"><i class="bi bi-search"></i></a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr>
          <td colspan="6" class="text-center">Nenhum registro encontrado.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<p class="text-muted"><?= (int) $total; ?> registro(s) encontrado(s)</p>

<?php if ($totalPages > 1): ?>
  <?= paginationLinks('schedule_category', $page, $totalPages, $queryFilters, 2, 'Anterior', 'Próxima'); ?>
<?php endif; ?>

<?php if (authCan('schedule_category.delete')): ?>
  <?php require __DIR__ . '/../shared/delete_modal.php'; ?>
<?php endif; ?>

<script src="<?= url('assets/js/shared/delete_modal.js?v=1'); ?>"></script>
<script src="<?= url('assets/js/schedule_categories/index.js?v=1'); ?>"></script>
