<?php
$bankSlip = $bankSlip ?? [];
$boleto = $boleto ?? [];

$esc = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$money = static fn ($value): string => 'R$ ' . number_format((float) $value, 2, ',', '.');
$date = static fn ($value): string => maskDateBr((string) $value) ?? (string) $value;

$bankNumber = str_pad(preg_replace('/\D+/', '', (string) ($bankSlip['bank_number'] ?? '341')) ?? '341', 3, '0', STR_PAD_LEFT);
$bankName = trim((string) ($bankSlip['bank_name'] ?? 'Banco'));
$bankCodeDisplay = $bankNumber === '341' ? '341-7' : $bankNumber;
$bankAccountName = trim((string) ($bankSlip['bank_account_name'] ?? ''));
$beneficiary = $bankAccountName !== '' ? $bankAccountName : $bankName;
$documentNumber = (string) ($bankSlip['document_number'] ?? '');
$payerDocument = maskCpfCnpj((string) ($bankSlip['payer_document'] ?? ''));
$payerAddress = trim((string) ($bankSlip['payer_street'] ?? ''));
$payerNeighborhood = trim((string) ($bankSlip['payer_neighborhood'] ?? ''));
$payerCity = trim((string) ($bankSlip['payer_city'] ?? ''));
$payerState = trim((string) ($bankSlip['payer_state'] ?? ''));
$payerZipCode = preg_replace('/\D+/', '', (string) ($bankSlip['payer_zip_code'] ?? '')) ?? '';
$payerLocation = trim($payerNeighborhood . ' - ' . $payerCity . '/' . $payerState, ' -/');
$placeOfPayment = $bankNumber === '341'
	? 'Até o vencimento, pague preferencialmente no Itaú. Após o vencimento, pague somente no Itaú.'
	: 'Pagável em qualquer banco até o vencimento.';
$ourNumberDisplay = (string) ($boleto['our_number_display'] ?? ($bankSlip['our_number'] ?? ''));
$agencyAccountDisplay = (string) ($boleto['agency_account_display'] ?? '');
$digitableLine = (string) ($boleto['digitable_line'] ?? '');
$barcode = (string) ($boleto['barcode'] ?? '');
$barcodeSvg = (string) ($boleto['barcode_svg'] ?? '');
$fineAmount = (float) ($bankSlip['fine_amount'] ?? 0);
$interestAmount = (float) ($bankSlip['interest_amount_per_day'] ?? 0);
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Boleto #<?= (int) ($bankSlip['id'] ?? 0); ?></title>
  <link rel="stylesheet" href="<?= url('assets/css/bank_slips/boleto.css?v=1'); ?>">
</head>
<body>
  <div class="boleto-toolbar no-print">
    <a href="<?= url('bank_slip'); ?>" class="toolbar-button">Voltar</a>
    <button type="button" id="printBoleto" class="toolbar-button primary">Imprimir</button>
  </div>

  <main class="boleto-page">
    <section class="boleto-section">
      <div class="section-title">Recibo do pagador</div>
      <header class="boleto-header">
        <div class="bank-logo <?= $bankNumber === '341' ? 'itau-logo' : 'generic-logo'; ?>">
          <?= $bankNumber === '341' ? 'Itaú' : $esc($bankName); ?>
        </div>
        <div class="bank-code"><?= $esc($bankCodeDisplay); ?></div>
        <div class="digitable-line"><?= $esc($digitableLine); ?></div>
      </header>

      <div class="field-grid receipt-grid">
        <div class="field wide">
          <span>Local de pagamento</span>
          <strong><?= $esc($placeOfPayment); ?></strong>
        </div>
        <div class="field">
          <span>Vencimento</span>
          <strong><?= $esc($date($bankSlip['due_date'] ?? '')); ?></strong>
        </div>
        <div class="field wide">
          <span>Beneficiário</span>
          <strong><?= $esc($beneficiary); ?></strong>
        </div>
        <div class="field">
          <span>Agência / Conta</span>
          <strong><?= $esc($agencyAccountDisplay); ?></strong>
        </div>
        <div class="field">
          <span>Data do documento</span>
          <strong><?= $esc($date($bankSlip['issue_date'] ?? '')); ?></strong>
        </div>
        <div class="field">
          <span>Nº documento</span>
          <strong><?= $esc($documentNumber); ?></strong>
        </div>
        <div class="field">
          <span>Carteira / Nosso número</span>
          <strong><?= $esc($ourNumberDisplay); ?></strong>
        </div>
        <div class="field">
          <span>Valor do documento</span>
          <strong><?= $esc($money($bankSlip['amount'] ?? 0)); ?></strong>
        </div>
        <div class="field wide">
          <span>Pagador</span>
          <strong><?= $esc(($bankSlip['payer_name'] ?? '') . ($payerDocument !== '' ? ' - ' . $payerDocument : '')); ?></strong>
        </div>
        <div class="field">
          <span>Código de barras</span>
          <strong><?= $esc($barcode); ?></strong>
        </div>
      </div>
    </section>

    <div class="cut-line">Corte na linha pontilhada</div>

    <section class="boleto-section compensation-section">
      <div class="section-title">Ficha de compensação</div>
      <header class="boleto-header">
        <div class="bank-logo <?= $bankNumber === '341' ? 'itau-logo' : 'generic-logo'; ?>">
          <?= $bankNumber === '341' ? 'Itaú' : $esc($bankName); ?>
        </div>
        <div class="bank-code"><?= $esc($bankCodeDisplay); ?></div>
        <div class="digitable-line"><?= $esc($digitableLine); ?></div>
      </header>

      <div class="field-grid compensation-grid">
        <div class="field wide">
          <span>Local de pagamento</span>
          <strong><?= $esc($placeOfPayment); ?></strong>
        </div>
        <div class="field">
          <span>Vencimento</span>
          <strong><?= $esc($date($bankSlip['due_date'] ?? '')); ?></strong>
        </div>
        <div class="field wide">
          <span>Beneficiário</span>
          <strong><?= $esc($beneficiary); ?></strong>
        </div>
        <div class="field">
          <span>Agência / Conta</span>
          <strong><?= $esc($agencyAccountDisplay); ?></strong>
        </div>
        <div class="field">
          <span>Data do documento</span>
          <strong><?= $esc($date($bankSlip['issue_date'] ?? '')); ?></strong>
        </div>
        <div class="field">
          <span>Nº documento</span>
          <strong><?= $esc($documentNumber); ?></strong>
        </div>
        <div class="field">
          <span>Espécie doc.</span>
          <strong>DM</strong>
        </div>
        <div class="field">
          <span>Aceite</span>
          <strong>N</strong>
        </div>
        <div class="field">
          <span>Data processamento</span>
          <strong><?= date('d/m/Y'); ?></strong>
        </div>
        <div class="field">
          <span>Carteira / Nosso número</span>
          <strong><?= $esc($ourNumberDisplay); ?></strong>
        </div>
        <div class="field wide instructions">
          <span>Instruções</span>
          <strong>Não receber após 30 dias do vencimento.</strong>
          <?php if ($fineAmount > 0): ?>
            <strong>Após o vencimento, cobrar multa de <?= $esc($money($fineAmount)); ?>.</strong>
          <?php endif; ?>
          <?php if ($interestAmount > 0): ?>
            <strong>Após o vencimento, cobrar juros de <?= $esc($money($interestAmount)); ?> por dia de atraso.</strong>
          <?php endif; ?>
        </div>
        <div class="field amount-stack">
          <span>Valor do documento</span>
          <strong><?= $esc($money($bankSlip['amount'] ?? 0)); ?></strong>
          <span>(-) Desconto</span>
          <strong><?= $esc($money($bankSlip['discount_amount'] ?? 0)); ?></strong>
          <span>(+) Mora / Multa</span>
          <strong></strong>
          <span>(=) Valor cobrado</span>
          <strong></strong>
        </div>
        <div class="field wide payer-field">
          <span>Pagador</span>
          <strong><?= $esc(($bankSlip['payer_name'] ?? '') . ($payerDocument !== '' ? ' - ' . $payerDocument : '')); ?></strong>
          <strong><?= $esc($payerAddress); ?></strong>
          <strong><?= $esc($payerLocation); ?><?= $payerZipCode !== '' ? ' - CEP ' . $esc($payerZipCode) : ''; ?></strong>
        </div>
      </div>

      <div class="barcode-area">
        <?= $barcodeSvg; ?>
        <div class="barcode-number"><?= $esc($barcode); ?></div>
      </div>
      <div class="mechanical-auth">Autenticação mecânica - Ficha de compensação</div>
    </section>
  </main>

  <script src="<?= url('assets/js/bank_slips/boleto.js?v=1'); ?>"></script>
</body>
</html>
