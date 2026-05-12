<h5>Criar conta bancária</h5>

<form action="<?= url('bank_account/store'); ?>" method="POST">
  <div class="row">

    <div class="mb-2 col-md-6 col-lg-6">
      <label class="form-label" for="bank_id">Banco</label>
      <select class="form-select" id="bank_id" name="bank_id" required>
        <?php foreach ($banks as $bank): ?>
          <option value="<?= (int) $bank['id']; ?>">
            <?= htmlspecialchars(trim(($bank['number'] ?? '') . ' - ' . ($bank['name'] ?? ''), ' -'), ENT_QUOTES, 'UTF-8'); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="mb-2 col-md-6 col-lg-6">
      <label class="form-label" for="name">Nome</label>
      <input autocomplete="off" class="form-control" id="name" maxlength="50" name="name" required type="text">
    </div>

    <div class="mb-2 col-md-6 col-lg-3">
      <label class="form-label" for="agency">Agência</label>
      <input autocomplete="off" class="form-control" id="agency" maxlength="6" name="agency" required type="text">
    </div>

    <div class="mb-2 col-md-6 col-lg-6">
      <label class="form-label" for="account">Conta</label>
      <input autocomplete="off" class="form-control" id="account" maxlength="50" name="account" required type="text">
    </div>

    <div class="mb-2 col-md-6 col-lg-3">
      <label class="form-label" for="active">Status</label>
      <select class="form-select" id="active" name="active">
        <option value="1" selected>Ativa</option>
        <option value="0">Inativa</option>
      </select>
    </div>

  </div>
  <br>
  <div class="row">
    <div class="col-lg-12">
      <div class="d-flex flex-column gap-2 align-items-center align-items-lg-end">
        <button class="btn btn-primary" type="submit">Salvar</button>
      </div>
    </div>
  </div>
</form>
