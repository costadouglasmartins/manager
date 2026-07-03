<div class="app-page">
  <div class="app-page-header">
    <div>
      <span class="app-page-kicker"><i class="bi bi-person-gear"></i> Usuários</span>
      <h5>Editar Usuário</h5>
    </div>
  </div>

<form method="POST" action="<?= url('/user/update/' . $user['id']); ?>"> 
  <section class="app-section">
    <div class="row g-3">
    <div class="mb-2 col-md-6 col-lg-12">
      <label class="form-label" for="username">Usuário(a):</label>
      <input autocomplete="off" class="form-control" type="text" id="username" name="username" value="<?= $user['username']; ?>" readonly="readonly" required="required" value="<?= $user['username']; ?>">
    </div>
    <div class="mb-2 col-md-6 col-lg-12">
      <label class="form-label" for="password">Senha</label>
      <input autocomplete="off" class="form-control" type="password" id="password" name="password" required="required">
    </div>
    <div class="mb-2 col-md-6 col-lg-12">
      <label class="form-label" for="email">Email:</label><br>
      <input autocomplete="off" class="form-control" type="email" id="email" name="email" value="<?= $user['email']; ?>" required="required">
    </div>
    <div class="mb-2 col-md-6 col-lg-12">
      <label class="form-label" for="type">Tipo</label>
      <select autocomplete="off" class="form-control" id="type" name="type" required="required">
        <option value="">Selecione</option>
        <option value="root" <?= ($user['type'] === 'root' ? 'selected="selected"' : '' ); ?>>Root</option>
        <option value="admin" <?= ($user['type'] === 'admin' ? 'selected="selected"' : ''); ?>>Admin</option>
        <option value="user" <?= ($user['type'] === 'user' ? 'selected="selected"' : ''); ?>>Usuário</option>
      </select>
    </div>
    <div class="col-md-6 col-lg-12">
      <label class="form-label" for="active">Ativo</label>
      <select autocomplete="off" class="form-control" id="active" name="active" required="required">
        <option value="">Selecione</option>
        <option value="1" <?= ($user['active'] === 1 ? 'selected="selected"' : '' ); ?>>Ativo</option>
        <option value="0" <?= ($user['active'] === 0 ? 'selected="selected"' : ''); ?>>Inativo</option>
      </select>
    </div>
    </div>
  </section>
  <div class="form-actions">
    <a href="<?= url('user'); ?>" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Cancelar</a>
    <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> Salvar</button>
  </div>
</form>
</div>
<!-- /User -->
