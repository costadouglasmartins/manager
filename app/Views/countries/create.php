<!-- País -->
<h5>Criar país</h5>
<form action="<?= url('country/store/'); ?>" method="POST">
  <div class="row">
    
    <div class="mb-2 col-md-6 col-lg-12">
      <label class="form-label" for="name">Nome</label>
      <input autocomplete="off" class="form-control" id="name" name="name" maxlength="255" type="text">
    </div>

    <div class="mb-2 col-md-6 col-lg-12">
      <label class="form-label" for="acronym">Sigla</label>
      <input autocomplete="off" class="form-control" id="acronym" name="acronym" maxlength="5" type="text">
    </div>
  
  </div>
  <div class="form-actions">
    <button class="btn btn-primary" type="submit">Salvar</button>
  </div>
</form>
<!-- /País -->