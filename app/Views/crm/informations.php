<?php
$ownerName = trim((string) ($crmRecord['owner_name'] ?? '')) ?: (string) ($crmRecord['owner_username'] ?? '');
$notes = trim((string) ($crmRecord['notes'] ?? ''));
$activities = (isset($activities) && is_array($activities)) ? $activities : [];
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
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
      <h6 class="mb-0">Timeline de atividades</h6>
      <?php if (authCan('crm_activity.create')): ?>
        <a href="<?= url('crm_activity/create/' . (int) ($crmRecord['id'] ?? 0)); ?>" class="btn btn-sm btn-success"><i class="bi bi-plus-lg"></i> Nova atividade</a>
      <?php endif; ?>
    </div>

    <?php if (!empty($activities)): ?>
      <div class="list-group list-group-flush">
        <?php foreach ($activities as $activity): ?>
          <?php $responsibleName = trim((string) ($activity['responsible_name'] ?? '')) ?: (string) ($activity['responsible_username'] ?? ''); ?>
          <div class="list-group-item px-0">
            <div class="d-flex flex-wrap justify-content-between gap-2">
              <div>
                <div class="d-flex flex-wrap align-items-center gap-2">
                  <span class="badge <?= htmlspecialchars((string) ($activity['activity_type_bootstrap_class'] ?? 'text-bg-secondary'), ENT_QUOTES, 'UTF-8'); ?>"><i class="bi <?= htmlspecialchars((string) ($activity['activity_type_icon'] ?? 'bi-sticky'), ENT_QUOTES, 'UTF-8'); ?>"></i> <?= htmlspecialchars((string) ($activity['activity_type_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                  <span class="badge <?= htmlspecialchars((string) ($activity['activity_status_bootstrap_class'] ?? 'text-bg-secondary'), ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars((string) ($activity['activity_status_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="fw-semibold mt-2"><?= htmlspecialchars((string) ($activity['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                <?php if (trim((string) ($activity['description'] ?? '')) !== ''): ?>
                  <div class="text-muted small"><?= nl2br(htmlspecialchars((string) ($activity['description'] ?? ''), ENT_QUOTES, 'UTF-8')); ?></div>
                <?php endif; ?>
                <small class="text-muted">
                  <?= htmlspecialchars(\App\Models\Crm::formatDateTime($activity['scheduled_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                  <?php if ($responsibleName !== ''): ?>
                    · <?= htmlspecialchars($responsibleName, ENT_QUOTES, 'UTF-8'); ?>
                  <?php endif; ?>
                </small>
              </div>
              <div class="text-nowrap">
                <?php if (authCan('crm_activity.complete') && (int) ($activity['activity_status_is_final'] ?? 0) !== 1): ?>
                  <form action="<?= url('crm_activity/complete/' . (int) ($activity['id'] ?? 0)); ?>" method="POST" class="d-inline">
                    <input type="hidden" name="back_to_crm" value="1">
                    <input type="hidden" name="tenant_person_id" value="<?= (int) ($crmRecord['id'] ?? 0); ?>">
                    <button type="submit" class="btn btn-sm btn-outline-success"><i class="bi bi-check2-circle"></i> Concluir</button>
                  </form>
                <?php endif; ?>
                <?php if (authCan('crm_activity.cancel') && (int) ($activity['activity_status_is_final'] ?? 0) !== 1): ?>
                  <form action="<?= url('crm_activity/cancel/' . (int) ($activity['id'] ?? 0)); ?>" method="POST" class="d-inline">
                    <input type="hidden" name="back_to_crm" value="1">
                    <input type="hidden" name="tenant_person_id" value="<?= (int) ($crmRecord['id'] ?? 0); ?>">
                    <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-circle"></i> Cancelar</button>
                  </form>
                <?php endif; ?>
                <?php if (authCan('crm_activity.edit')): ?>
                  <a href="<?= url('crm_activity/edit/' . (int) ($activity['id'] ?? 0)); ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil-square"></i> Editar</a>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="text-muted mb-0">Nenhuma atividade registrada para este contato CRM.</p>
    <?php endif; ?>
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
