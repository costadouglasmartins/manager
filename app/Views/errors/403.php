<div class="row">
  <div class="col-12 col-lg-8">
    <h5 class="mb-3">Acesso negado</h5>

    <div class="alert alert-danger" role="alert">
      Você não possui permissão para executar esta ação.
      <?php if (!empty($ability)): ?>
        <div class="mt-2"><small><strong>Permissão:</strong> <?= htmlspecialchars((string) $ability); ?></small></div>
      <?php endif; ?>
    </div>

  </div>
</div>

<div class="view-actions">
  <a class="btn btn-secondary" href="<?= url('/'); ?>"><i class="bi bi-arrow-left"></i> Voltar ao início</a>
</div>
