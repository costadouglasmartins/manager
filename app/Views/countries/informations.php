<h5>Dados gerais do país</h5><div class="table-responsive mb-3">
<table class="table table-hover align-middle">
  <tbody>
    <tr>
      <th class="text-muted" style="width: 220px;">Nome</th>
      <td><?= htmlspecialchars($data['countries']['name']); ?></td>
    </tr>
    <tr>
      <th class="text-muted">Acronym</th>
      <td><?= $data['countries']['acronym']; ?></td>
    </tr>
  </tbody>
</table>