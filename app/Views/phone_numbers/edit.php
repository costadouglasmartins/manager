<!-- Telefone -->
<div class="app-page">
  <div class="app-page-header">
    <div>
      <span class="app-page-kicker"><i class="bi bi-telephone-fill"></i> Telefones</span>
      <h5>Editar telefone</h5>
    </div>
  </div>

  <form action="<?= url('phone_number/update/' . $phone_number['id']); ?>" method="POST">
    <div class="row">

      <div class="mb-2 col-md-6 col-lg-12">
        <label class="form-label" for="phone_number">Telefone</label>
        <input autocomplete="off" class="form-control" id="phone_number" name="phone_number" maxlength="255" type="text" value="<?= htmlspecialchars($phone_number['phone_number'], ENT_QUOTES, 'UTF-8'); ?>">
      </div>

      <div class="mb-2 col-md-6 col-lg-12">
        <label class="form-label" for="observations">Observações</label>
        <input autocomplete="off" class="form-control" id="observations" name="observations" maxlength="500" type="text" value="<?= htmlspecialchars($phone_number['observations'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
      </div>

      <div class="mb-2 col-md-6 col-lg-12 position-relative">
        <label class="form-label" for="personName">Pessoa</label>
        <input type="hidden" id="person_id" name="person_id" value="<?= (int) ($phone_number['person_id'] ?? 0); ?>">
        <input id="personName" type="text" class="form-control js-autocomplete" autocomplete="off"
          value="<?= htmlspecialchars($phone_number['person_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
          placeholder="Digite o nome da pessoa..."
          data-endpoint="<?= url('person/autocomplete'); ?>"
          data-results="#personResults"
          data-hidden="#person_id"
          data-minlen="1"
          data-param="term">
        <ul id="personResults" class="list-group mt-1 w-100" style="overflow:auto; max-height:240px; position:absolute; z-index:1050;"></ul>
      </div>

    </div>
    <div class="form-actions">
      <button class="btn btn-primary" type="submit">Salvar</button>
    </div>
  </form>
</div>
<!-- /Telefone -->

<script src="<?= url('assets/js/autocomplete.js?v=2'); ?>"></script>
