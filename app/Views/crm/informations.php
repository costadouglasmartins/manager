<?php
$ownerName = trim((string) ($crmRecord['owner_name'] ?? '')) ?: (string) ($crmRecord['owner_username'] ?? '');
$notes = trim((string) ($crmRecord['notes'] ?? ''));
?>

<div class="app-page">
  <div class="app-page-header">
    <div>
      <span class="app-page-kicker"><i class="bi bi-search"></i> CRM</span>
      <h5><?= htmlspecialchars((string) ($crmRecord['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h5>
    </div>
    <span class="badge <?= htmlspecialchars((string) ($crmRecord['crm_status_bootstrap_class'] ?? 'text-bg-secondary'), ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars((string) ($crmRecord['crm_status_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
  </div>

  <section class="border rounded p-3 mb-3">
    <h6>Dados do CRM</h6>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <tbody>
          <tr>
            <th class="text-muted" style="width: 220px;">Código CRM</th>
            <td><?= (int) ($crmRecord['id'] ?? 0); ?></td>
          </tr>
          <tr>
            <th class="text-muted">Responsável</th>
            <td><?= htmlspecialchars($ownerName, ENT_QUOTES, 'UTF-8'); ?></td>
          </tr>
          <tr>
            <th class="text-muted">Origem</th>
            <td><?= htmlspecialchars((string) ($crmRecord['source'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
          </tr>
          <tr>
            <th class="text-muted">Vínculo</th>
            <td><?= ((int) ($crmRecord['active'] ?? 0) === 1) ? 'Ativo' : 'Inativo'; ?></td>
          </tr>
          <tr>
            <th class="text-muted">Criado em</th>
            <td><?= htmlspecialchars(\App\Models\Crm::formatDateTime($crmRecord['created'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
          </tr>
          <tr>
            <th class="text-muted">Atualizado em</th>
            <td><?= htmlspecialchars(\App\Models\Crm::formatDateTime($crmRecord['modified'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>

  <section class="border rounded p-3 mb-3">
    <h6>Pessoa</h6>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <tbody>
          <tr>
            <th class="text-muted" style="width: 220px;">Código pessoa</th>
            <td><?= (int) ($crmRecord['person_id'] ?? 0); ?></td>
          </tr>
          <tr>
            <th class="text-muted">Nome</th>
            <td><?= htmlspecialchars((string) ($crmRecord['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
          </tr>
          <tr>
            <th class="text-muted">Nome fantasia</th>
            <td><?= htmlspecialchars((string) ($crmRecord['fantasy'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
          </tr>
          <tr>
            <th class="text-muted">Documento</th>
            <td><?= htmlspecialchars(maskCpfCnpj((string) ($crmRecord['document'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
          </tr>
          <tr>
            <th class="text-muted">Tipo</th>
            <td><?= htmlspecialchars(\App\Models\Crm::personTypeLabel((string) ($crmRecord['type'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
          </tr>
          <tr>
            <th class="text-muted">Data de nascimento</th>
            <td><?= htmlspecialchars(maskDateBr((string) ($crmRecord['birthdate'] ?? '')) ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>

  <section class="border rounded p-3 mb-3">
    <h6>Contatos</h6>
    <div class="row g-3">
      <div class="col-12 col-lg-6">
        <div class="table-responsive">
          <table class="table table-sm table-hover align-middle mb-0">
            <thead>
              <tr>
                <th>E-mail</th>
                <th>Observações</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($emails)): ?>
                <?php foreach ($emails as $email): ?>
                  <tr>
                    <td><?= htmlspecialchars((string) ($email['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?= htmlspecialchars((string) ($email['observations'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="2" class="text-center">Nenhum e-mail cadastrado.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="col-12 col-lg-6">
        <div class="table-responsive">
          <table class="table table-sm table-hover align-middle mb-0">
            <thead>
              <tr>
                <th>Telefone</th>
                <th>Observações</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($phones)): ?>
                <?php foreach ($phones as $phone): ?>
                  <tr>
                    <td><?= htmlspecialchars(maskPhoneBr((string) ($phone['phone_number'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?= htmlspecialchars((string) ($phone['observations'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="2" class="text-center">Nenhum telefone cadastrado.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </section>

  <section class="border rounded p-3 mb-3">
    <h6>Endereços</h6>
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>CEP</th>
            <th>Logradouro</th>
            <th>Número</th>
            <th>Bairro</th>
            <th>Cidade</th>
            <th>Observações</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($addresses)): ?>
            <?php foreach ($addresses as $address): ?>
              <tr>
                <td><?= htmlspecialchars((string) ($address['zip_code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?= htmlspecialchars((string) ($address['street'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?= htmlspecialchars((string) ($address['number'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?= htmlspecialchars((string) ($address['neighborhood_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?= htmlspecialchars((string) ($address['city_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?= htmlspecialchars((string) ($address['observations'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="6" class="text-center">Nenhum endereço cadastrado.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="border rounded p-3 mb-3">
    <h6>Observações comerciais</h6>
    <?php if ($notes !== ''): ?>
      <p class="mb-0"><?= nl2br(htmlspecialchars($notes, ENT_QUOTES, 'UTF-8')); ?></p>
    <?php else: ?>
      <p class="text-muted mb-0">Nenhuma observação cadastrada.</p>
    <?php endif; ?>
  </section>

  <div class="form-actions">
    <a href="<?= url('crm'); ?>" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
    <?php if (authCan('crm.edit')): ?>
      <a href="<?= url('crm/edit/' . (int) ($crmRecord['id'] ?? 0)); ?>" class="btn btn-primary"><i class="bi bi-pencil-square"></i> Editar</a>
    <?php endif; ?>
  </div>
</div>
