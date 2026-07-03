<h5>Dados do estado</h5>
<div class="table-responsive mb-3">
  <table class="table table-hover align-middle">
    <tbody>
      <tr>
        <th class="text-muted" style="width: 220px;">Nome</th>
        <td><?= htmlspecialchars($state['name'], ENT_QUOTES, 'UTF-8'); ?></td>
      </tr>
      <tr>
        <th class="text-muted">Sigla</th>
        <td><?= htmlspecialchars($state['acronym'], ENT_QUOTES, 'UTF-8'); ?></td>
      </tr>
      <tr>
        <th class="text-muted">País</th>
        <td><?= htmlspecialchars($state['country_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
      </tr>
    </tbody>
  </table>
</div>

<div class="form-actions">
  <a href="<?= url('state'); ?>" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
</div>
