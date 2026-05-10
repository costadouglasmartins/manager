<div class="d-flex justify-content-between align-items-center mb-3">
  <h5 class="mb-0">Dados gerais da pessoa</h5>
  <?php if (authCan('person.edit')): ?>
    <a href="<?= url('person/edit/' . $person['id']); ?>" class="btn btn-sm btn-outline-primary">Editar</a>
  <?php endif; ?>
</div>

<!-- Pessoa -->
<div class="card shadow-sm mb-3">
  <div class="card-header fw-semibold">Pessoa</div>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <tbody>
        <tr>
          <th class="text-muted" style="width: 220px;">Nome</th>
          <td><?= htmlspecialchars($person['name']); ?></td>
        </tr>
        <tr>
          <th class="text-muted">Nome fantasia</th>
          <td><?= htmlspecialchars($person['fantasy'] ?? '—'); ?></td>
        </tr>
        <tr>
          <th class="text-muted">Tipo</th>
          <td><?= ($person['type'] === 'individual' ? 'Pessoa física' : 'Pessoa jurídica'); ?></td>
        </tr>
        <tr>
          <th class="text-muted">Documento</th>
          <td><?= maskCpfCnpj($person['document']); ?></td>
        </tr>
        <tr>
          <th class="text-muted">Situação</th>
          <td>
            <?php if ((int)$person['active'] === 1): ?>
              <span class="badge text-bg-success">Ativo</span>
            <?php else: ?>
              <span class="badge text-bg-secondary">Inativo</span>
            <?php endif; ?>
          </td>
        </tr>
        <tr>
          <th class="text-muted">Data de nascimento</th>
          <td><?= (!empty($person['birthdate']) ? maskDateBr($person['birthdate']) : '—'); ?></td>
        </tr>
      </tbody>
    </table>
  </div>
</div>
<!-- /Pessoa -->

<!-- Usuário -->
<?php if (!empty($user)): ?>
<div class="card shadow-sm mb-3">
  <div class="card-header fw-semibold">Usuário</div>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <tbody>
        <tr>
          <th class="text-muted" style="width: 220px;">Nome de usuário</th>
          <td><?= htmlspecialchars($user['username']); ?></td>
        </tr>
        <tr>
          <th class="text-muted">Tipo de acesso</th>
          <td>
            <?php
            $userTypes = ['root' => 'Root', 'admin' => 'Administrador', 'user' => 'Usuário'];
            echo htmlspecialchars($userTypes[$user['type']] ?? $user['type']);
            ?>
          </td>
        </tr>
        <tr>
          <th class="text-muted">Situação</th>
          <td>
            <?php if ((int)$user['active'] === 1): ?>
              <span class="badge text-bg-success">Ativo</span>
            <?php else: ?>
              <span class="badge text-bg-secondary">Inativo</span>
            <?php endif; ?>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
<!-- /Usuário -->

<!-- Telefones -->
<div class="card shadow-sm mb-3">
  <div class="card-header fw-semibold">Telefones</div>
  <?php if (!empty($phones)): ?>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>#</th>
            <th>Número</th>
            <th>Observações</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($phones as $phone): ?>
            <tr>
              <td class="text-muted" style="width: 1%;"><?= $phone['id']; ?></td>
              <td><?= maskPhoneBr($phone['phone_number']); ?></td>
              <td><?= htmlspecialchars($phone['observations'] ?? '—'); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <div class="card-body text-muted">Nenhum telefone cadastrado.</div>
  <?php endif; ?>
</div>
<!-- /Telefones -->

<!-- Endereços -->
<div class="card shadow-sm mb-3">
  <div class="card-header fw-semibold">Endereços</div>
  <?php if (!empty($addresses)): ?>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>#</th>
            <th>CEP</th>
            <th>Logradouro</th>
            <th>Número</th>
            <th>Bairro</th>
            <th>Cidade</th>
            <th>Observações</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($addresses as $address): ?>
            <?php
              $zipDigits = onlyDigits((string) ($address['zip_code'] ?? ''));
              $zipCode = $zipDigits;
              if (strlen($zipDigits) === 8)
                {
                $zipCode = substr($zipDigits, 0, 5) . '-' . substr($zipDigits, 5);
                }
            ?>
            <tr>
              <td class="text-muted" style="width: 1%;"><?= $address['id']; ?></td>
              <td><?= htmlspecialchars($zipCode !== '' ? $zipCode : '—'); ?></td>
              <td><?= htmlspecialchars($address['street'] ?? '—'); ?></td>
              <td><?= htmlspecialchars(($address['number'] ?? '') !== '' ? $address['number'] : '—'); ?></td>
              <td><?= htmlspecialchars(($address['neighborhood_name'] ?? '') !== '' ? $address['neighborhood_name'] : '—'); ?></td>
              <td><?= htmlspecialchars(($address['neighborhood_city_name'] ?? '') !== '' ? $address['neighborhood_city_name'] : '—'); ?></td>
              <td><?= htmlspecialchars(($address['observations'] ?? '') !== '' ? $address['observations'] : '—'); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <div class="card-body text-muted">Nenhum endereço cadastrado.</div>
  <?php endif; ?>
</div>
<!-- /Endereços -->

<!-- E-mails -->
<div class="card shadow-sm mb-3">
  <div class="card-header fw-semibold">E-mails</div>
  <?php if (!empty($emails)): ?>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>#</th>
            <th>E-mail</th>
            <th>Observações</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($emails as $email): ?>
            <tr>
              <td class="text-muted" style="width: 1%;"><?= $email['id']; ?></td>
              <td><?= htmlspecialchars($email['email']); ?></td>
              <td><?= htmlspecialchars($email['observations'] ?? '—'); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <div class="card-body text-muted">Nenhum e-mail cadastrado.</div>
  <?php endif; ?>
</div>
<!-- /E-mails -->
