<?php
$filters = $filters ?? ['id' => '', 'financial_operation_category_id' => '', 'total_amount' => ''];
$page = $page ?? 1;
$totalPages = $totalPages ?? 1;
$total = $total ?? (is_array($operations) ? count($operations) : 0);

$queryFilters = http_build_query([
	'id'                              => $filters['id'],
	'financial_operation_category_id' => $filters['financial_operation_category_id'],
	'total_amount'                    => $filters['total_amount'],
]);

$formatMoney = static function ($value): string {
	return 'R$ ' . number_format((float) $value, 2, ',', '.');
};
?>
<h5>Lista de Operações Financeiras</h5>

  <form action="<?= url('financial_operation'); ?>" class="row g-3 mb-3" method="get">

    <div class="col-12 col-lg-9">
      <div class="row g-3">

        <div class="col-12 col-md-3">
          <input class="form-control" id="id" name="id" type="text" placeholder="Código" value="<?= htmlspecialchars($filters['id'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="col-12 col-md-5">
          <select class="form-select" id="financial_operation_category_id" name="financial_operation_category_id">
            <option value="" <?= $filters['financial_operation_category_id'] === '' ? 'selected' : ''; ?>>Todas as categorias</option>
            <?php foreach ($categories as $category): ?>
              <option value="<?= (int) $category['id']; ?>" <?= ((string) $filters['financial_operation_category_id'] === (string) $category['id']) ? 'selected' : ''; ?>>
                <?= htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12 col-md-4">
          <input class="form-control js-money" id="total_amount" inputmode="decimal" name="total_amount" type="text" placeholder="Valor total" value="<?= htmlspecialchars($filters['total_amount'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>

      </div>
    </div>

    <div class="col-12 col-lg-3">
      <div class="d-flex flex-column gap-2 align-items-center align-items-lg-end">
        <button type="submit" class="btn btn-primary w-100">Pesquisar</button>
        <a href="<?= url('financial_operation'); ?>" class="btn btn-secondary w-100">Limpar</a>
        <?php if (authCan('financial_operation.create')): ?>
          <a href="<?= url('financial_operation/create'); ?>" class="btn btn-success w-100">Novo(a)</a>
        <?php endif; ?>
      </div>
    </div>

  </form>

  <div class="d-flex flex-wrap gap-2 mb-3">
    <?= pdfExportButton('financial_operation', $queryFilters); ?>
    <?= excelExportButton('financial_operation', $queryFilters); ?>
  </div>

  <hr>

  <div class="table-responsive">
    <table class="table table-striped table-hover">
      <thead>
        <tr>
          <th>#</th>
          <th>Categoria</th>
          <th>Valor total</th>
          <th>Criada em</th>
          <th class="text-end">Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($operations)): ?>
          <?php foreach ($operations as $row): ?>
            <tr>
              <td style="width:1%;"><?= (int) $row['id']; ?></td>
              <td><?= htmlspecialchars($row['financial_operation_category_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?= htmlspecialchars($formatMoney($row['total_amount']), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?= htmlspecialchars((string) ($row['created'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td style="width:1%;" class="text-nowrap text-end">
                <?php if (authCan('financial_operation.edit')): ?>
                  <a href="<?= url('financial_operation/edit/' . $row['id']); ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Editar"><i class="bi bi-pencil-square"></i></a>
                <?php endif; ?>
                <?php if (authCan('financial_operation.informations')): ?>
                  <a href="<?= url('financial_operation/informations/' . $row['id']); ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Saiba mais"><i class="bi bi-search"></i></a>
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
    <?= paginationLinks('financial_operation', $page, $totalPages, $queryFilters, 2, 'Anterior', 'Próxima'); ?>
  <?php endif; ?>

<script src="<?= url('assets/js/financial_operations/index.js?v=1'); ?>"></script>
