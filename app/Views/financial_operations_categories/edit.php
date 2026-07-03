<link rel="stylesheet" href="<?= url('assets/css/financial_operations_categories/edit.css?v=1'); ?>">

<div class="financial-page app-page">
  <div class="financial-page-header app-page-header">
    <div>
      <span class="financial-page-kicker app-page-kicker"><i class="bi bi-pencil-square"></i> Categorias</span>
      <h5>Editar categoria de operação financeira</h5>
    </div>
  </div>

  <form action="<?= url('financial_operation_category/update/' . $category['id']); ?>" method="POST" class="needs-validation financial-form" novalidate>
    <section class="financial-section">
      <div class="row g-3">

        <div class="col-12">
          <label class="form-label" for="name">Nome</label>
          <input autocomplete="off" class="form-control" id="name" maxlength="255" name="name" required type="text" value="<?= htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8'); ?>">
          <div class="invalid-feedback">Informe o nome da categoria.</div>
        </div>

      </div>
    </section>

    <div class="form-actions">
      <a href="<?= url('financial_operation_category'); ?>" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Cancelar</a>
      <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> Salvar</button>
    </div>
  </form>
</div>

<script src="<?= url('assets/js/financial_operations_categories/edit.js?v=1'); ?>"></script>
