<h5>Dados do bairro</h5>
<div class="table-responsive mb-3">
  <table class="table table-hover align-middle">
    <tbody>
      <tr>
        <th class="text-muted" style="width: 220px;">Nome</th>
        <td><?= htmlspecialchars($neighborhood['name'], ENT_QUOTES, 'UTF-8'); ?></td>
      </tr>
      <tr>
        <th class="text-muted">Cidade</th>
        <td><?= htmlspecialchars($neighborhood['city_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
      </tr>
    </tbody>
  </table>
</div>

<div class="form-actions">
  <a href="<?= url('neighborhood'); ?>" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
</div>
