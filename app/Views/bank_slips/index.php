<?php
$filters = $filters ?? ['id' => '', 'bank_account_id' => '', 'status' => '', 'document_number' => ''];
$statuses = ['DRAFT', 'READY', 'REMITTANCE_GENERATED', 'SENT', 'REGISTERED', 'REJECTED', 'PAID', 'CANCELED', 'WRITTEN_OFF'];
?>
<link rel="stylesheet" href="<?= url('assets/css/bank_slips/index.css?v=2'); ?>">

<h5>Boletos bancários</h5>

<form action="<?= url('bank_slip'); ?>" method="get" class="row g-2 mb-3" id="bankSlipFilterForm">
  <div class="col-12 col-md-2"><input type="text" name="id" class="form-control" placeholder="Código" value="<?= htmlspecialchars($filters['id'], ENT_QUOTES, 'UTF-8'); ?>"></div>
  <div class="col-12 col-md-3"><input type="text" name="document_number" class="form-control" placeholder="Seu número" value="<?= htmlspecialchars($filters['document_number'], ENT_QUOTES, 'UTF-8'); ?>"></div>
  <div class="col-12 col-md-3">
    <select name="bank_account_id" class="form-select">
      <option value="">Conta bancária</option>
      <?php foreach ($bankAccounts as $account): ?>
        <option value="<?= (int) $account['id']; ?>" <?= ((string) $filters['bank_account_id'] === (string) $account['id']) ? 'selected' : ''; ?>>
          <?= htmlspecialchars(($account['bank_name'] ?? '') . ' - ' . ($account['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-12 col-md-2">
    <select name="status" class="form-select">
      <option value="">Status</option>
      <?php foreach ($statuses as $status): ?>
        <option value="<?= $status; ?>" <?= $filters['status'] === $status ? 'selected' : ''; ?>><?= $status; ?></option>
      <?php endforeach; ?>
    </select>
  </div>
</form>

<form action="<?= url('bank_slip/generateRemittance'); ?>" method="post" id="bankSlipRemittanceForm">
  <div class="table-responsive">
    <table class="table table-striped align-middle">
      <thead><tr><th></th><th>#</th><th>Conta</th><th>Documento</th><th>Sacado</th><th>Vencimento</th><th>Valor</th><th>Status</th><th>Ações</th></tr></thead>
      <tbody>
      <?php if (!empty($bankSlips)): foreach ($bankSlips as $row): ?>
        <?php $canGenerateRemittanceForRow = in_array((string) ($row['status'] ?? ''), ['READY', 'REJECTED'], true); ?>
        <tr>
          <td>
            <?php if ($canGenerateRemittanceForRow): ?>
              <input type="checkbox" name="bank_slip_ids[]" value="<?= (int) $row['id']; ?>">
            <?php endif; ?>
          </td>
          <td><?= (int) $row['id']; ?></td>
          <td><?= htmlspecialchars(($row['bank_name'] ?? '') . ' / ' . ($row['bank_account_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?= htmlspecialchars((string) $row['document_number'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?= htmlspecialchars((string) $row['payer_name'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?= htmlspecialchars((string) $row['due_date'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td>R$ <?= number_format((float) $row['amount'], 2, ',', '.'); ?></td>
          <td><?= htmlspecialchars((string) $row['status'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td>
            <div class="bank-slip-actions">
              <a href="<?= url('bank_slip/informations/' . (int) $row['id']); ?>" title="Informações"><i class="bi bi-search"></i></a>
              <?php if (authCan('bank_slip.informations')): ?>
                <a href="<?= url('bank_slip/generateBankSlip/' . (int) $row['id']); ?>" title="Gerar boleto" target="_blank" rel="noopener"><i class="bi bi-upc-scan"></i></a>
              <?php endif; ?>
              <?php if (authCan('bank_slip.generateRemittance') && !empty($row['bank_file_path'])): ?>
                <a href="<?= url((string) $row['bank_file_path']); ?>" title="Baixar remessa" download><i class="bi bi-file-arrow-down"></i></a>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; else: ?>
        <tr><td colspan="9" class="text-center">Nenhum boleto encontrado.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</form>

<div class="view-actions">
  <button class="btn btn-primary" type="submit" form="bankSlipFilterForm"><i class="bi bi-search"></i> Pesquisar</button>
  <?php if (authCan('bank_slip.create')): ?>
    <a class="btn btn-success" href="<?= url('bank_slip/create'); ?>"><i class="bi bi-plus-lg"></i> Novo boleto</a>
  <?php endif; ?>
  <?php if (authCan('bank_slip.generateRemittance')): ?>
    <button type="submit" class="btn btn-primary" form="bankSlipRemittanceForm"><i class="bi bi-file-earmark-arrow-up"></i> Gerar remessa Itaú CNAB400</button>
  <?php endif; ?>
</div>

<script src="<?= url('assets/js/bank_slips/index.js?v=2'); ?>"></script>
