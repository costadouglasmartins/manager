<!-- E-mail -->
<div class="app-page">
  <div class="app-page-header">
    <div>
      <span class="app-page-kicker"><i class="bi bi-envelope-paper-fill"></i> E-mails</span>
      <h5>Criar e-mail</h5>
    </div>
  </div>

  <form action="<?= url('email/store/'); ?>" method="POST">
    <section class="app-section">
      <div class="row g-3">

      <div class="mb-2 col-md-6 col-lg-12">
        <label class="form-label" for="email">E-mail</label>
        <input autocomplete="off" class="form-control" id="email" name="email" maxlength="255" type="email">
      </div>

      <div class="mb-2 col-md-6 col-lg-12">
        <label class="form-label" for="type">Utilizar para login</label>
        <select class="form-select" id="type" name="type">
          <option value="">Selecione...</option>
          <option value="0">Não</option>
          <option value="1">Sim</option>
        </select>
      </div>

      <div class="mb-2 col-md-6 col-lg-12">
        <label class="form-label" for="observations">Observações</label>
        <input autocomplete="off" class="form-control" id="observations" name="observations" maxlength="500" type="text">
      </div>

      <div class="mb-2 col-md-6 col-lg-12 position-relative">
        <label class="form-label" for="personName">Pessoa</label>
        <input type="hidden" id="person_id" name="person_id" value="">
        <input id="personName" type="text" class="form-control js-autocomplete" autocomplete="off"
          placeholder="Digite o nome da pessoa..."
          data-endpoint="<?= url('person/autocomplete'); ?>"
          data-results="#personResults"
          data-hidden="#person_id"
          data-minlen="1"
          data-param="term">
        <ul id="personResults" class="list-group mt-1 w-100" style="overflow:auto; max-height:240px; position:absolute; z-index:1050;"></ul>
      </div>

      </div>
    </section>
    <div class="form-actions">
      <a href="<?= url('email'); ?>" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Cancelar</a>
      <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> Salvar</button>
    </div>
  </form>
</div>

<!-- /E-mail -->

<script src="/manager_assets/autocomplete/autocomplete.js?v=2"></script>
