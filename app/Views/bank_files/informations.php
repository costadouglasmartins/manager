<?php
$bankFile = $bankFile ?? [];
$slipsCount = (int) ($bankFile['bank_slips_count'] ?? 0);
$fileContent = $fileContent ?? '';
?>
<link rel="stylesheet" href="<?= url('assets/css/bank_files/informations.css?v=1'); ?>">

<h5>Informações da remessa #<?= (int) ($bankFile['id'] ?? 0); ?></h5>

<table class="table table-bordered">
  <tr><th>Tipo</th><td><?= htmlspecialchars((string) ($bankFile['file_type'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td></tr>
  <tr><th>Nome do arquivo</th><td><?= htmlspecialchars((string) ($bankFile['file_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td></tr>
  <tr><th>Caminho</th><td><?= htmlspecialchars((string) ($bankFile['file_path'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td></tr>
  <tr><th>Hash</th><td><?= htmlspecialchars((string) ($bankFile['file_hash'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td></tr>
  <tr><th>Conta bancária</th><td><?= htmlspecialchars((string) ($bankFile['bank_account_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td></tr>
  <tr><th>Boletos vinculados</th><td><?= $slipsCount; ?></td></tr>
  <tr><th>Criado em</th><td><?= htmlspecialchars((string) ($bankFile['created'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td></tr>
  <tr><th>Modificado em</th><td><?= htmlspecialchars((string) ($bankFile['modified'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td></tr>
</table>

<h6 class="mt-4">Conteúdo do arquivo</h6>
<?php if ($fileContent !== ''): ?>
  <pre class="bank-file-content"><?= htmlspecialchars($fileContent, ENT_QUOTES, 'UTF-8'); ?></pre>
<?php else: ?>
  <p class="text-muted">Não foi possível carregar o conteúdo desta remessa.</p>
<?php endif; ?>

<div class="form-actions">
  <a href="<?= url('bank_file'); ?>" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
  <?php if (authCan('bank_slip.generateRemittance') && strtoupper((string) ($bankFile['file_type'] ?? '')) === 'REMITTANCE'): ?>
    <a href="<?= url('bank_slip/downloadRemittance/' . (int) ($bankFile['id'] ?? 0)); ?>" class="btn btn-primary"><i class="bi bi-file-arrow-down"></i> Baixar remessa</a>
  <?php endif; ?>
</div>

<script src="<?= url('assets/js/bank_files/informations.js?v=1'); ?>"></script>
