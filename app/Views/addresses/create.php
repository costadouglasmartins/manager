<!-- Endereço -->
<div class="app-page">
  <div class="app-page-header">
    <div>
      <span class="app-page-kicker"><i class="bi bi-signpost-split-fill"></i> Endereços</span>
      <h5>Criar endereço</h5>
    </div>
  </div>

  <form action="<?= url('address/store/'); ?>" method="POST">
    <div class="row">

      <div class="mb-2 col-md-6 col-lg-4">
        <label class="form-label" for="zip_code">CEP</label>
        <input autocomplete="off" class="form-control only-numbers" id="zip_code" name="zip_code" maxlength="9" inputmode="numeric" data-zip-code-lookup="true" data-zip-code-endpoint="<?= url('address/zipCodeSearch'); ?>" data-zip-code-street-target="#street" data-zip-code-feedback-target="#zipCodeFeedback" type="text">
        <div class="form-text" id="zipCodeFeedback" aria-live="polite"></div>
      </div>

      <div class="mb-2 col-md-6 col-lg-6">
        <label class="form-label" for="street">Logradouro</label>
        <input autocomplete="off" class="form-control" id="street" name="street" maxlength="255" type="text">
      </div>

      <div class="mb-2 col-md-6 col-lg-2">
        <label class="form-label" for="number">Número</label>
        <input autocomplete="off" class="form-control" id="number" name="number" maxlength="10" type="text">
      </div>

      <div class="mb-2 col-md-6 col-lg-12">
        <label class="form-label" for="observations">Observações</label>
        <input autocomplete="off" class="form-control" id="observations" name="observations" maxlength="255" type="text">
      </div>

      <div class="mb-2 col-md-6 col-lg-6 position-relative">
        <label class="form-label" for="personName">Pessoa</label>
        <input type="hidden" id="person_id" name="person_id" value="">
        <input id="personName" type="text" class="form-control js-autocomplete" autocomplete="off" placeholder="Digite o nome da pessoa..." data-endpoint="<?= url('person/autocomplete'); ?>" data-results="#personResults" data-hidden="#person_id" data-minlen="1" data-param="term">
        <ul id="personResults" class="list-group mt-1 w-100" style="overflow:auto; max-height:240px; position:absolute; z-index:1050;"></ul>
      </div>

      <div class="mb-2 col-md-6 col-lg-6 position-relative">
        <label class="form-label" for="neighborhoodName">Bairro</label>
        <input type="hidden" id="neighborhood_id" name="neighborhood_id" value="">
        <input id="neighborhoodName" type="text" class="form-control js-autocomplete" autocomplete="off" placeholder="Digite o nome do bairro..." data-endpoint="<?= url('neighborhood/autocomplete'); ?>" data-results="#neighborhoodResults" data-hidden="#neighborhood_id" data-minlen="1" data-param="term">
        <ul id="neighborhoodResults" class="list-group mt-1 w-100" style="overflow:auto; max-height:240px; position:absolute; z-index:1050;"></ul>
      </div>

    </div>
    <br>
    <div class="form-actions">
      <button class="btn btn-primary" type="submit">Salvar</button>
    </div>
  </form>
</div>
<!-- /Endereço -->

<script src="<?= url('assets/js/autocomplete.js?v=2'); ?>"></script>
<script src="<?= url('assets/js/addresses/create.js?v=1'); ?>"></script>
