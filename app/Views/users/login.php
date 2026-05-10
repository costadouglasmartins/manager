<div class="login-page">
  <div class="login-panel">
    <div class="login-brand">
      <span class="login-brand-icon"><i class="bi bi-shield-lock"></i></span>
      <div>
        <h3 class="login-title">Identifique-se</h3>
        <div class="text-body-secondary small">Acesse sua área de gestão.</div>
      </div>
    </div>

    <?php if (!empty($error)): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= url('user/login'); ?>">
      <div class="mb-3">
        <label class="form-label" for="email">E-mail</label>
        <input autocomplete="off" id="email" name="email" type="email" class="form-control" placeholder="E-mail" required="required" value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
      </div>
      <div class="mb-3">
        <label class="form-label" for="password">Senha</label>
        <input autocomplete="off" id="password" name="password" type="password" class="form-control" placeholder="Senha" required="required">
      </div>
      <button class="btn btn-primary w-100" type="submit"><i class="bi bi-box-arrow-in-right"></i> Autenticar</button>
    </form>
  </div>
</div>
