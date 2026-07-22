<?php
$categories = $categories ?? [];
$batches = $batches ?? [];
$selectedCategoryId = (int) ($selectedCategoryId ?? 0);
$importResult = is_array($importResult ?? null) ? $importResult : null;
$statusLabels = ['PROCESSING' => 'Processando', 'COMPLETED' => 'Concluída', 'PARTIAL' => 'Parcial', 'FAILED' => 'Falhou'];
$statusClasses = ['PROCESSING' => 'text-bg-info', 'COMPLETED' => 'text-bg-success', 'PARTIAL' => 'text-bg-warning', 'FAILED' => 'text-bg-danger'];
?>
<link rel="stylesheet" href="<?= url('assets/css/products/create.css?v=4'); ?>">

<div class="store-page app-page">
  <div class="store-page-header app-page-header">
    <div>
      <span class="store-page-kicker app-page-kicker"><i class="bi bi-file-earmark-spreadsheet" aria-hidden="true"></i> Produtos</span>
      <h5>Importar produtos por planilha</h5>
    </div>
  </div>

  <?php if (!empty($formError)): ?>
    <div class="alert alert-danger" role="alert"><?= htmlspecialchars((string) $formError, ENT_QUOTES, 'UTF-8'); ?></div>
  <?php endif; ?>

  <?php if ($importResult !== null): ?>
    <div class="alert <?= ($importResult['failed_rows'] ?? 0) > 0 ? 'alert-warning' : 'alert-success'; ?>" role="status">
      Importação finalizada: <strong><?= (int) ($importResult['imported_rows'] ?? 0); ?></strong> produto(s) cadastrado(s) e
      <strong><?= (int) ($importResult['failed_rows'] ?? 0); ?></strong> linha(s) com erro.
    </div>
    <?php if (!empty($importResult['errors'])): ?>
      <section class="store-section">
        <div class="store-subheader"><h6>Erros encontrados</h6></div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Linha</th><th>Motivo</th></tr></thead>
            <tbody><?php foreach ($importResult['errors'] as $error): ?><tr><td><?= (int) $error['row_number']; ?></td><td><?= htmlspecialchars((string) $error['message'], ENT_QUOTES, 'UTF-8'); ?></td></tr><?php endforeach; ?></tbody>
          </table>
        </div>
      </section>
    <?php endif; ?>
  <?php endif; ?>

  <form action="<?= url('product/import-spreadsheet'); ?>" class="needs-validation store-form" enctype="multipart/form-data" method="POST" novalidate>
    <section class="store-section">
      <div class="store-subheader"><div><h6>Arquivo CSV</h6><small class="text-muted">O modelo inclui as características dinâmicas da categoria e uma linha de exemplo.</small></div></div>
      <div class="row g-3 align-items-end">
        <div class="col-12 col-lg-5">
          <label class="form-label" for="product_category_id">Categoria</label>
          <select class="form-select js-product-import-category" id="product_category_id" name="product_category_id" required>
            <option value="">Selecione</option>
            <?php foreach ($categories as $category): ?>
              <option value="<?= (int) $category['id']; ?>" <?= $selectedCategoryId === (int) $category['id'] ? 'selected' : ''; ?>><?= htmlspecialchars((string) $category['name'], ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
          <div class="invalid-feedback">Selecione a categoria da planilha.</div>
        </div>
        <div class="col-12 col-lg-5">
          <label class="form-label" for="spreadsheet">Planilha</label>
          <input accept=".csv,text/csv" class="form-control" id="spreadsheet" name="spreadsheet" required type="file">
          <div class="invalid-feedback">Selecione um arquivo CSV.</div>
        </div>
        <div class="col-12 col-lg-2 d-grid">
          <a class="btn btn-outline-primary disabled js-product-import-template" data-base-url="<?= url('product/download-import-template'); ?>" href="#" aria-disabled="true"><i class="bi bi-download" aria-hidden="true"></i> Baixar modelo</a>
        </div>
      </div>
    </section>

    <div class="form-actions">
      <a href="<?= url('product'); ?>" class="btn btn-secondary"><i class="bi bi-arrow-left" aria-hidden="true"></i> Voltar</a>
      <button class="btn btn-primary" type="submit"><i class="bi bi-upload" aria-hidden="true"></i> Importar</button>
    </div>
  </form>

  <?php if (!empty($batches)): ?>
    <section class="store-section mt-4">
      <div class="store-subheader"><h6>Importações recentes</h6></div>
      <div class="table-responsive">
        <table class="table table-striped table-hover align-middle mb-0">
          <thead><tr><th>#</th><th>Arquivo</th><th>Categoria</th><th>Resultado</th><th>Status</th><th>Data</th></tr></thead>
          <tbody>
            <?php foreach ($batches as $batch): $status = (string) ($batch['status'] ?? 'PROCESSING'); ?>
              <tr>
                <td><?= (int) $batch['id']; ?></td>
                <td><?= htmlspecialchars((string) $batch['file_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?= htmlspecialchars((string) $batch['category_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?= (int) $batch['imported_rows']; ?> importado(s), <?= (int) $batch['failed_rows']; ?> erro(s)</td>
                <td><span class="badge <?= htmlspecialchars($statusClasses[$status] ?? 'text-bg-secondary', ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars($statusLabels[$status] ?? $status, ENT_QUOTES, 'UTF-8'); ?></span></td>
                <td><?= htmlspecialchars((new DateTimeImmutable((string) $batch['created']))->format('d/m/Y H:i'), ENT_QUOTES, 'UTF-8'); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
  <?php endif; ?>
</div>

<script src="<?= url('assets/js/products/import.js?v=1'); ?>"></script>
