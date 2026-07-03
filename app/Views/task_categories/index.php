<?php
$filters = $filters ?? ['id' => '', 'name' => '', 'active' => ''];
$page = $page ?? 1;
$totalPages = $totalPages ?? 1;
$total = $total ?? (is_array($categories) ? count($categories) : 0);

$queryFilters = http_build_query([
	'id'     => $filters['id'],
	'name'   => $filters['name'],
	'active' => $filters['active'],
]);
?>
<h5>Lista de Categorias de Tarefas</h5>

  <form action="<?= url('task_category'); ?>" class="row g-3 mb-3" method="get">
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
        <a href="<?= url('task_category'); ?>" class="btn btn-secondary w-100">Limpar</a>
        <?php if (authCan('task_category.create')): ?>
          <a href="<?= url('task_category/create'); ?>" class="btn btn-success w-100">Novo(a)</a>
        <?php endif; ?>
      </div>
    </div>
  </form>

  <div class="d-flex flex-wrap gap-2 mb-3">
    <?php if (authCan('task_category.index')): ?>
      <?= pdfExportButton('task_category', $queryFilters); ?>
      <?= excelExportButton('task_category', $queryFilters); ?>
    <?php endif; ?>
  </div>

  <hr>

  <div class="table-responsive">
    <table class="table table-striped table-hover">
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
        <?php if (!empty($categories)): ?>
          <?php foreach ($categories as $row): ?>
            <tr>
              <td style="width:1%;"><?= (int) $row['id']; ?></td>
              <td><?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?= htmlspecialchars((string) ($row['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><span class="badge <?= ((int) $row['active'] === 1) ? 'text-bg-success' : 'text-bg-secondary'; ?>"><?= ((int) $row['active'] === 1) ? 'Ativo' : 'Inativo'; ?></span></td>
              <td style="width:1%;" class="text-nowrap text-end">
                <?php if (authCan('task_category.edit')): ?>
                  <a href="<?= url('task_category/edit/' . $row['id']); ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Editar"><i class="bi bi-pencil-square"></i></a>
                <?php endif; ?>
                <?php if (authCan('task_category.informations')): ?>
                  <a href="<?= url('task_category/informations/' . $row['id']); ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Saiba mais"><i class="bi bi-search"></i></a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="5" class="text-center">Nenhum registro encontrado.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <p class="text-muted"><?= (int) $total; ?> registro(s) encontrado(s)</p>

  <?php if ($totalPages > 1): ?>
    <?= paginationLinks('task_category', $page, $totalPages, $queryFilters, 2, 'Anterior', 'Próxima'); ?>
  <?php endif; ?>

<script src="<?= url('assets/js/task_categories/index.js?v=1'); ?>"></script>
