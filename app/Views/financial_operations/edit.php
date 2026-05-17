<?php
$amountValue = number_format((float) ($operation['total_amount'] ?? 0), 2, ',', '.');
?>
<link rel="stylesheet" href="<?= url('assets/css/financial_operations/edit.css?v=1'); ?>">

<div class="financial-page">
  <div class="financial-page-header">
    <div>
      <span class="financial-page-kicker"><i class="bi bi-pencil-square"></i> Operações</span>
      <h5>Editar operação financeira</h5>
    </div>
  </div>

  <form action="<?= url('financial_operation/update/' . $operation['id']); ?>" method="POST" class="needs-validation financial-form" novalidate>
    <section class="financial-section">
      <div class="row g-3">

        <div class="col-12 col-lg-7">
          <label class="form-label" for="financial_operation_category_id">Categoria</label>
          <select class="form-select" id="financial_operation_category_id" name="financial_operation_category_id" required>
            <option value="" disabled>Selecione...</option>
            <?php foreach ($categories as $category): ?>
              <option value="<?= (int) $category['id']; ?>" <?= ((int) $operation['financial_operation_category_id'] === (int) $category['id']) ? 'selected' : ''; ?>>
                <?= htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="invalid-feedback">Selecione a categoria.</div>
        </div>

        <div class="col-12 col-lg-5">
          <label class="form-label" for="total_amount">Valor total</label>
          <input autocomplete="off" class="form-control js-money" id="total_amount" inputmode="decimal" name="total_amount" required type="text" value="<?= htmlspecialchars($amountValue, ENT_QUOTES, 'UTF-8'); ?>">
          <div class="invalid-feedback">Informe o valor total.</div>
        </div>

      </div>
    </section>

    <div class="financial-actions view-actions">
      <a href="<?= url('financial_operation'); ?>" class="btn btn-secondary">Cancelar</a>
      <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> Salvar</button>
    </div>
  </form>
</div>

<script src="<?= url('assets/js/financial_operations/edit.js?v=1'); ?>"></script>
