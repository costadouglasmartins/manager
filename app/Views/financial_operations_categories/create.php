<link rel="stylesheet" href="<?= url('assets/css/financial_operations_categories/create.css?v=1'); ?>">

<div class="financial-page app-page">
  <div class="financial-page-header app-page-header">
    <div>
      <span class="financial-page-kicker app-page-kicker"><i class="bi bi-tag"></i> Categorias</span>
      <h5>Criar categoria de operação financeira</h5>
    </div>
  </div>

  <form action="<?= url('financial_operation_category/store'); ?>" method="POST" class="needs-validation financial-form" novalidate>
    <section class="financial-section">
      <div class="row g-3">

        <div class="col-12">
          <label class="form-label" for="name">Nome</label>
          <input autocomplete="off" class="form-control" id="name" maxlength="255" name="name" required type="text">
          <div class="invalid-feedback">Informe o nome da categoria.</div>
        </div>

      </div>
    </section>

    <div class="form-actions">
      <a href="<?= url('financial_operation_category'); ?>" class="btn btn-secondary">Cancelar</a>
      <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> Salvar</button>
    </div>
  </form>
</div>

<script src="<?= url('assets/js/financial_operations_categories/create.js?v=1'); ?>"></script>
