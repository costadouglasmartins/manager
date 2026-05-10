<h5>Dados gerais da pessoa</h5><div class="table-responsive mb-3">
<table class="table table-hover align-middle">
  <tbody>
    <tr>
      <th class="text-muted" style="width: 220px;">Username</th>
      <td><?= htmlspecialchars($data['user']['username']); ?></td>
    </tr>
    <tr>
      <th class="text-muted">Email</th>
      <td><?= $data['user']['email']; ?></td>
    </tr>
    <tr>
      <th class="text-muted">Tipo</th>
      <td> <?
        switch($data['user']['type'])
          {
          case 'root':
            echo 'Root (apenas sistema gerenciador central)';
          break;
          case 'admin':
            echo 'Administrador (Funcionários(as) da empresa)';
          break;
          case 'user':
            echo 'Usuário(a)';
          break;
          } ?>
        </td>
    </tr>
    <tr>
      <th class="text-muted">Ativa</th>
      <td><?= ((int)$data['user']['active'] === 1 ? 'Sim' : 'Não'); ?></td>
    </tr>
  </tbody>
</table>