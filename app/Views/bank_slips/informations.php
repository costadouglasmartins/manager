<link rel="stylesheet" href="<?= url('assets/css/bank_slips/informations.css?v=1'); ?>">

<h5>Informações do boleto #<?= (int) ($bankSlip['id'] ?? 0); ?></h5>

<table class="table table-bordered">
  <tr><th>Status</th><td><?= htmlspecialchars((string) ($bankSlip['status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td></tr>
  <tr><th>Documento</th><td><?= htmlspecialchars((string) ($bankSlip['document_number'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td></tr>
  <tr><th>Nosso número</th><td><?= htmlspecialchars((string) ($bankSlip['our_number'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td></tr>
  <tr><th>Conta</th><td><?= htmlspecialchars((string) (($bankSlip['bank_name'] ?? '') . ' / ' . ($bankSlip['bank_account_name'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td></tr>
  <tr><th>Valor</th><td>R$ <?= number_format((float) ($bankSlip['amount'] ?? 0), 2, ',', '.'); ?></td></tr>
  <tr><th>Emissão</th><td><?= htmlspecialchars((string) ($bankSlip['issue_date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td></tr>
  <tr><th>Vencimento</th><td><?= htmlspecialchars((string) ($bankSlip['due_date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td></tr>
  <tr><th>Sacado</th><td><?= htmlspecialchars((string) ($bankSlip['payer_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td></tr>
  <tr><th>Documento sacado</th><td><?= htmlspecialchars((string) ($bankSlip['payer_document'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td></tr>
  <tr><th>Endereço</th><td><?= htmlspecialchars((string) ($bankSlip['payer_street'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td></tr>
</table>

<div class="view-actions">
  <a href="<?= url('bank_slip'); ?>" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
  <?php if (authCan('bank_slip.edit') && (string) ($bankSlip['status'] ?? '') === 'DRAFT'): ?>
    <a href="<?= url('bank_slip/edit/' . (int) ($bankSlip['id'] ?? 0)); ?>" class="btn btn-primary"><i class="bi bi-pencil-square"></i> Editar</a>
  <?php endif; ?>
  <?php if (authCan('bank_slip.informations')): ?>
    <a href="<?= url('bank_slip/generateBankSlip/' . (int) ($bankSlip['id'] ?? 0)); ?>" class="btn btn-primary" target="_blank" rel="noopener"><i class="bi bi-upc-scan"></i> Gerar boleto</a>
  <?php endif; ?>
</div>
