<?php
$filters = $filters ?? ['id' => '', 'financial_operation_category_id' => '', 'name' => ''];
$page = $page ?? 1;
$totalPages = $totalPages ?? 1;
$total = $total ?? (is_array($subcategories) ? count($subcategories) : 0);

$queryFilters = http_build_query([
	'id'                              => $filters['id'],
	'financial_operation_category_id' => $filters['financial_operation_category_id'],
	'name'                            => $filters['name'],
]);
?>
<h5>Lista de subcategorias de operações financeiras</h5>

  <?php if (!empty($formError)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars((string) $formError, ENT_QUOTES, 'UTF-8'); ?></div>
  <?php endif; ?>

  <form action="<?= url('financial_operation_subcategory'); ?>" class="row g-3 mb-3" method="get">

    <div class="col-12 col-lg-9">
      <div class="row g-3">

        <div class="col-12 col-md-3">
          <input class="form-control" id="id" name="id" type="text" placeholder="Código" value="<?= htmlspecialchars($filters['id'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="col-12 col-md-4">
          <select class="form-select" id="financial_operation_category_id" name="financial_operation_category_id">
            <option value="" <?= $filters['financial_operation_category_id'] === '' ? 'selected' : ''; ?>>Todas as categorias</option>
            <?php foreach ($categories as $category): ?>
              <option value="<?= (int) $category['id']; ?>" <?= ((string) $filters['financial_operation_category_id'] === (string) $category['id']) ? 'selected' : ''; ?>>
                <?= htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12 col-md-5">
          <input class="form-control" id="name" maxlength="255" name="name" type="text" placeholder="Nome" value="<?= htmlspecialchars($filters['name'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>

      </div>
    </div>

    <div class="col-12 col-lg-3">
      <div class="d-flex flex-column gap-2 align-items-center align-items-lg-end">
        <button type="submit" class="btn btn-primary w-100">Pesquisar</button>
        <a href="<?= url('financial_operation_subcategory'); ?>" class="btn btn-secondary w-100">Limpar</a>
        <?php if (authCan('financial_operation_subcategory.create')): ?>
          <a href="<?= url('financial_operation_subcategory/create'); ?>" class="btn btn-success w-100">Novo(a)</a>
        <?php endif; ?>
      </div>
    </div>

  </form>

  <div class="d-flex flex-wrap gap-2 mb-3">
    <?= pdfExportButton('financial_operation_subcategory', $queryFilters); ?>
    <?= excelExportButton('financial_operation_subcategory', $queryFilters); ?>
  </div>

  <hr>

  <div class="table-responsive">
    <table class="table table-striped table-hover">
      <thead>
        <tr>
          <th>#</th>
          <th>Categoria</th>
          <th>Nome</th>
          <th class="text-end">Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($subcategories)): ?>
          <?php foreach ($subcategories as $row): ?>
            <tr>
              <td style="width:1%;"><?= (int) $row['id']; ?></td>
              <td><?= htmlspecialchars($row['financial_operation_category_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'); ?></td>
              <td style="width:1%;" class="text-nowrap text-end">
                <?php if (authCan('financial_operation_subcategory.delete')): ?>
                  <a href="#"
                     class="js-record-delete-link text-danger"
                     data-bs-toggle="modal"
                     data-bs-target="#recordDeleteModal"
                     data-record-id="<?= (int) $row['id']; ?>"
                     data-record-name="<?= htmlspecialchars((string) $row['name'], ENT_QUOTES, 'UTF-8'); ?>"
                     data-record-delete-description="a subcategoria"
                     data-record-delete-url="<?= url('financial_operation_subcategory/delete/' . (int) $row['id']); ?>"
                     data-bs-content="Excluir"
                     data-bs-trigger="hover focus"
                     role="button"
                     aria-label="Excluir subcategoria financeira">
                    <i class="bi bi-trash"></i>
                  </a>
                <?php endif; ?>
                <?php if (authCan('financial_operation_subcategory.edit')): ?>
                  <a href="<?= url('financial_operation_subcategory/edit/' . $row['id']); ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Editar"><i class="bi bi-pencil-square"></i></a>
                <?php endif; ?>
                <?php if (authCan('financial_operation_subcategory.informations')): ?>
                  <a href="<?= url('financial_operation_subcategory/informations/' . $row['id']); ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Saiba mais"><i class="bi bi-search"></i></a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="4" class="text-center">Nenhum registro encontrado.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <p class="text-muted"><?= (int) $total; ?> registro(s) encontrado(s)</p>

  <?php if ($totalPages > 1): ?>
    <?= paginationLinks('financial_operation_subcategory', $page, $totalPages, $queryFilters, 2, 'Anterior', 'Próxima'); ?>
  <?php endif; ?>

<?php if (authCan('financial_operation_subcategory.delete')): ?>
  <?php require __DIR__ . '/../shared/delete_modal.php'; ?>
<?php endif; ?>

<script src="<?= url('assets/js/shared/delete_modal.js?v=1'); ?>"></script>
<script src="<?= url('assets/js/financial_operations_subcategories/index.js?v=1'); ?>"></script>
