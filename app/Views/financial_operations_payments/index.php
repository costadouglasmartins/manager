<?php
$filters = $filters ?? ['id' => '', 'financial_operation_id' => '', 'value' => ''];
$page = $page ?? 1;
$totalPages = $totalPages ?? 1;
$total = $total ?? (is_array($payments) ? count($payments) : 0);

$queryFilters = http_build_query([
	'id'                     => $filters['id'],
	'financial_operation_id' => $filters['financial_operation_id'],
	'value'                  => $filters['value'],
]);

$formatMoney = static function ($value): string {
	return 'R$ ' . number_format((float) $value, 2, ',', '.');
};

$operationLabel = static function (array $operation) use ($formatMoney): string {
	return '#' . (int) $operation['id'] . ' - ' . trim((string) ($operation['financial_operation_category_name'] ?? '')) . ' - ' . $formatMoney($operation['total_amount'] ?? 0);
};
?>
<link rel="stylesheet" href="<?= url('assets/css/financial_operations_payments/index.css?v=1'); ?>">

<div class="financial-page">
  <div class="financial-page-header">
    <div>
      <span class="financial-page-kicker"><i class="bi bi-cash-stack"></i> Financeiro</span>
      <h5>Lista de pagamentos de operações financeiras</h5>
    </div>
  </div>

  <form action="<?= url('financial_operation_payment'); ?>" class="row g-3 mb-3" method="get">

    <div class="col-12 col-lg-9">
      <div class="row g-3">

        <div class="col-12 col-md-3">
          <input class="form-control" id="id" name="id" type="text" placeholder="Código" value="<?= htmlspecialchars($filters['id'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="col-12 col-md-5">
          <select class="form-select" id="financial_operation_id" name="financial_operation_id">
            <option value="" <?= $filters['financial_operation_id'] === '' ? 'selected' : ''; ?>>Todas as operações</option>
            <?php foreach ($operations as $operation): ?>
              <option value="<?= (int) $operation['id']; ?>" <?= ((string) $filters['financial_operation_id'] === (string) $operation['id']) ? 'selected' : ''; ?>>
                <?= htmlspecialchars($operationLabel($operation), ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12 col-md-4">
          <input class="form-control js-money" id="value" inputmode="decimal" name="value" type="text" placeholder="Valor" value="<?= htmlspecialchars($filters['value'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>

      </div>
    </div>

    <div class="col-12 col-lg-3">
      <div class="d-flex flex-column gap-2 align-items-center align-items-lg-end">
        <button type="submit" class="btn btn-primary w-100">Pesquisar</button>
        <a href="<?= url('financial_operation_payment'); ?>" class="btn btn-secondary w-100">Limpar</a>
        <?php if (authCan('financial_operation_payment.create')): ?>
          <a href="<?= url('financial_operation_payment/create'); ?>" class="btn btn-success w-100">Novo(a)</a>
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
          <th>Operação</th>
          <th>Valor</th>
          <th>Criado em</th>
          <th class="text-end">Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($payments)): ?>
          <?php foreach ($payments as $row): ?>
            <tr>
              <td style="width:1%;"><?= (int) $row['id']; ?></td>
              <td>
                #<?= (int) $row['financial_operation_id']; ?>
                - <?= htmlspecialchars($row['financial_operation_category_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                - <?= htmlspecialchars($formatMoney($row['financial_operation_total_amount'] ?? 0), ENT_QUOTES, 'UTF-8'); ?>
              </td>
              <td><?= htmlspecialchars($formatMoney($row['value']), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?= htmlspecialchars((string) ($row['created'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td style="width:1%;" class="text-nowrap text-end">
                <?php if (authCan('financial_operation_payment.edit')): ?>
                  <a href="<?= url('financial_operation_payment/edit/' . $row['id']); ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Editar"><i class="bi bi-pencil-square"></i></a>
                <?php endif; ?>
                <?php if (authCan('financial_operation_payment.informations')): ?>
                  <a href="<?= url('financial_operation_payment/informations/' . $row['id']); ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Saiba mais"><i class="bi bi-search"></i></a>
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
    <?= paginationLinks('financial_operation_payment', $page, $totalPages, $queryFilters, 2, 'Anterior', 'Próxima'); ?>
  <?php endif; ?>
</div>

<script src="<?= url('assets/js/financial_operations_payments/index.js?v=1'); ?>"></script>
