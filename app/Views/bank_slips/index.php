<?php
$filters = $filters ?? ['id' => '', 'bank_account_id' => '', 'bank_account_ids' => [], 'status' => '', 'document_number' => ''];
$statuses = ['DRAFT', 'READY', 'REMITTANCE_GENERATED', 'SENT', 'REGISTERED', 'REJECTED', 'PAID', 'CANCELED', 'WRITTEN_OFF'];
$selectedBankAccountIds = $filters['bank_account_ids'] ?? [];
if (!is_array($selectedBankAccountIds))
  {
  $selectedBankAccountIds = [$selectedBankAccountIds];
  }
$selectedBankAccountIds = array_values(array_unique(array_filter(array_map('intval', $selectedBankAccountIds), static fn ($id) => $id > 0)));
$bankSlipGroups = $bankSlipGroups ?? [];
$accountLabel = static function (array $account): string {
  $label = trim(($account['bank_name'] ?? '') . ' - ' . ($account['name'] ?? $account['bank_account_name'] ?? ''), ' -');
  $cnab = preg_replace('/\D+/', '', (string) ($account['bank_cnab'] ?? '')) ?? '';
  if ($cnab !== '')
    {
    $label .= ' (CNAB' . $cnab . ')';
    }

  return $label;
};
$canGenerateRemittance = static function (array $bankSlip): bool {
  return in_array((string) ($bankSlip['status'] ?? ''), ['READY', 'REJECTED'], true);
};
$canEditBankSlip = static function (array $bankSlip): bool {
  return (string) ($bankSlip['status'] ?? '') === 'DRAFT';
};
?>
<link rel="stylesheet" href="<?= url('assets/css/bank_slips/index.css?v=7'); ?>">

<h5>Boletos bancários</h5>

<?php if (!empty($formError)): ?>
  <div class="alert alert-danger"><?= htmlspecialchars((string) $formError, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>

<form action="<?= url('bank_slip'); ?>" method="get" class="row g-2 mb-3" id="bankSlipFilterForm">
  <div class="col-12 col-lg-9">
    <div class="row g-2">
      <div class="col-12 col-md-2"><input type="text" name="id" class="form-control" placeholder="Código" value="<?= htmlspecialchars($filters['id'], ENT_QUOTES, 'UTF-8'); ?>"></div>
      <div class="col-12 col-md-3"><input type="text" name="document_number" class="form-control" placeholder="Seu número" value="<?= htmlspecialchars($filters['document_number'], ENT_QUOTES, 'UTF-8'); ?>"></div>
      <div class="col-12 col-md-4">
        <select name="bank_account_id" class="form-select">
          <option value="">Conta bancária</option>
          <?php foreach ($bankAccounts as $account): ?>
            <option value="<?= (int) $account['id']; ?>" <?= in_array((int) $account['id'], $selectedBankAccountIds, true) ? 'selected' : ''; ?>>
              <?= htmlspecialchars($accountLabel($account), ENT_QUOTES, 'UTF-8'); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 col-md-3">
        <select name="status" class="form-select">
          <option value="">Status</option>
          <?php foreach ($statuses as $status): ?>
            <option value="<?= $status; ?>" <?= $filters['status'] === $status ? 'selected' : ''; ?>><?= $status; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
  </div>
  <div class="col-12 col-lg-3">
    <div class="d-flex flex-column gap-2 align-items-stretch align-items-lg-end">
      <button class="btn btn-primary w-100" type="submit"><i class="bi bi-search"></i> Pesquisar</button>
      <?php if (authCan('bank_slip.create')): ?>
        <a class="btn btn-success w-100" href="<?= url('bank_slip/create'); ?>"><i class="bi bi-plus-lg"></i> Novo boleto</a>
      <?php endif; ?>
    </div>
  </div>
</form>

<form action="<?= url('bank_slip/generateRemittance'); ?>" method="post" id="bankSlipRemittanceForm">
  <?php if (!empty($bankSlipGroups)): ?>
    <?php foreach ($bankSlipGroups as $group): ?>
      <?php
      $bankAccount = $group['bank_account'] ?? [];
      $bankAccountId = (int) ($bankAccount['bank_account_id'] ?? $bankAccount['id'] ?? 0);
      $remittanceCount = (int) ($group['remittance_count'] ?? 0);
      $remittanceOptions = $group['remittance_options'] ?? [];
      ?>
      <section class="bank-slip-account-block">
        <div class="bank-slip-account-heading">
          <h6><?= htmlspecialchars($accountLabel($bankAccount), ENT_QUOTES, 'UTF-8'); ?></h6>
          <span><?= count($group['slips'] ?? []); ?> boleto(s) · <?= $remittanceCount; ?> apto(s) para remessa</span>
        </div>

        <div class="table-responsive bank-slip-account-table">
          <table class="table table-striped align-middle">
            <thead>
              <tr>
                <th>
                  <input
                    class="form-check-input bank-slip-select-account"
                    type="checkbox"
                    data-bank-account-id="<?= $bankAccountId; ?>"
                    title="Selecionar boletos desta conta"
                    <?= $remittanceCount > 0 ? '' : 'disabled'; ?>>
                </th>
                <th>#</th>
                <th>Documento</th>
                <th>Sacado</th>
                <th>Vencimento</th>
                <th>Valor</th>
                <th>Status</th>
                <th class="text-end">Ações</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach (($group['slips'] ?? []) as $row): ?>
                <?php $canGenerateRemittanceForRow = $canGenerateRemittance($row); ?>
                <?php $canEditBankSlipForRow = $canEditBankSlip($row); ?>
                <tr>
                  <td>
                    <?php if ($canGenerateRemittanceForRow): ?>
                      <input class="form-check-input bank-slip-remittance-checkbox" type="checkbox" name="bank_slip_ids[]" value="<?= (int) $row['id']; ?>" data-bank-account-id="<?= (int) $row['bank_account_id']; ?>">
                    <?php endif; ?>
                  </td>
                  <td><?= (int) $row['id']; ?></td>
                  <td><?= htmlspecialchars((string) $row['document_number'], ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?= htmlspecialchars((string) $row['payer_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?= htmlspecialchars((string) $row['due_date'], ENT_QUOTES, 'UTF-8'); ?></td>
                  <td>R$ <?= number_format((float) $row['amount'], 2, ',', '.'); ?></td>
                  <td><?= htmlspecialchars((string) $row['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                  <td class="text-end">
                    <div class="bank-slip-actions">
                      <?php if (authCan('bank_slip.delete')): ?>
                        <a href="#"
                           class="js-bank-slip-delete-link text-danger"
                           data-bs-toggle="modal"
                           data-bs-target="#bankSlipDeleteModal"
                           data-bank-slip-id="<?= (int) $row['id']; ?>"
                           data-bank-slip-delete-label="#<?= (int) $row['id']; ?><?= trim((string) ($row['document_number'] ?? '')) !== '' ? ' - Documento ' . htmlspecialchars((string) $row['document_number'], ENT_QUOTES, 'UTF-8') : ''; ?>"
                           data-bank-slip-delete-url="<?= url('bank_slip/delete/' . (int) $row['id']); ?>"
                           role="button"
                           aria-label="Excluir boleto"
                           title="Excluir">
                          <i class="bi bi-trash"></i>
                        </a>
                      <?php endif; ?>
                      <?php if (authCan('bank_slip.edit') && $canEditBankSlipForRow): ?>
                        <a href="<?= url('bank_slip/edit/' . (int) $row['id']); ?>" title="Editar"><i class="bi bi-pencil-square"></i></a>
                      <?php endif; ?>
                      <a href="<?= url('bank_slip/informations/' . (int) $row['id']); ?>" title="Informações"><i class="bi bi-search"></i></a>
                      <?php if (authCan('bank_slip.informations')): ?>
                        <a href="<?= url('bank_slip/generateBankSlip/' . (int) $row['id']); ?>" title="Gerar boleto" target="_blank" rel="noopener"><i class="bi bi-upc-scan"></i></a>
                      <?php endif; ?>
                      <?php if (authCan('bank_slip.generateRemittance') && !empty($row['bank_file_path'])): ?>
                        <a href="<?= url((string) $row['bank_file_path']); ?>" title="Baixar remessa" download="<?= htmlspecialchars((string) ($row['bank_file_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"><i class="bi bi-file-arrow-down"></i></a>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <?php if (authCan('bank_slip.generateRemittance') && !empty($remittanceOptions)): ?>
          <div class="bank-slip-remittance-panel">
            <div class="bank-slip-remittance-summary">
              <strong>Ações de remessa</strong>
              <span><?= $remittanceCount; ?> boleto(s) apto(s) · R$ <?= number_format((float) ($group['remittance_total_amount'] ?? 0), 2, ',', '.'); ?></span>
            </div>
            <div class="bank-slip-remittance-buttons">
              <?php foreach ($remittanceOptions as $option): ?>
                <?php
                $layout = (string) ($option['layout'] ?? '');
                $actionValue = $bankAccountId . ':' . $layout;
                ?>
                <button
                  type="submit"
                  class="btn btn-outline-primary bank-slip-remittance-button"
                  name="remittance_action"
                  value="<?= htmlspecialchars($actionValue, ENT_QUOTES, 'UTF-8'); ?>"
                  data-bank-account-id="<?= $bankAccountId; ?>"
                  <?= $remittanceCount > 0 ? '' : 'disabled'; ?>>
                  <i class="bi bi-file-earmark-arrow-up"></i>
                  Gerar remessa <?= htmlspecialchars((string) ($option['label'] ?? $layout), ENT_QUOTES, 'UTF-8'); ?>
                </button>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
      </section>
    <?php endforeach; ?>
  <?php else: ?>
    <div class="bank-slip-empty-state">Nenhum boleto encontrado.</div>
  <?php endif; ?>
</form>

<?php if (authCan('bank_slip.delete')): ?>
  <div class="modal fade" id="bankSlipDeleteModal" tabindex="-1" aria-labelledby="bankSlipDeleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <form id="bankSlipDeleteForm" method="POST" action="">
          <div class="modal-header">
            <h5 class="modal-title" id="bankSlipDeleteModalLabel">Confirmar exclusão</h5>
            <a href="#" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></a>
          </div>
          <div class="modal-body">
            <p class="mb-0">Deseja realmente excluir o boleto <strong id="bankSlipDeleteName">#</strong>?</p>
          </div>
          <div class="modal-footer">
            <a href="#" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</a>
            <button type="submit" class="btn btn-danger">Excluir</button>
          </div>
        </form>
      </div>
    </div>
  </div>
<?php endif; ?>

<script src="<?= url('assets/js/bank_slips/index.js?v=7'); ?>"></script>
