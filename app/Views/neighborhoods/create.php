<!-- Bairro -->
<h5>Criar bairro</h5>
<form action="<?= url('neighborhood/store/'); ?>" method="POST">
  <div class="row">

    <div class="mb-2 col-md-6 col-lg-12">
      <label class="form-label" for="name">Nome</label>
      <input autocomplete="off" class="form-control" id="name" name="name" maxlength="255" type="text">
    </div>

    <div class="mb-2 col-md-6 col-lg-12 position-relative">
      <label class="form-label" for="cityName">Cidade</label>
      <input type="hidden" id="city_id" name="city_id" value="">
      <input id="cityName" type="text" class="form-control js-autocomplete" autocomplete="off"
        placeholder="Digite o nome da cidade..."
        data-endpoint="<?= url('city/autocomplete'); ?>"
        data-results="#cityResults"
        data-hidden="#city_id"
        data-minlen="1"
        data-param="term">
      <ul id="cityResults" class="list-group mt-1 w-100" style="overflow:auto; max-height:240px; position:absolute; z-index:1050;"></ul>
    </div>

  </div>
  <div class="d-flex flex-column gap-2 align-items-center align-items-lg-end mt-3">
    <button class="btn btn-primary" type="submit">Salvar</button>
  </div>
</form>
<!-- /Bairro -->

<script src="<?= url('assets/js/autocomplete.js?v=2'); ?>"></script>
