<link rel="stylesheet" href="<?= url('assets/css/bank_slips/create.css?v=1'); ?>">

<div class="app-page">
  <div class="app-page-header">
    <div>
      <span class="app-page-kicker"><i class="bi bi-upc-scan"></i> Boletos</span>
      <h5>Novo boleto</h5>
    </div>
  </div>

  <form action="<?= url('bank_slip/store'); ?>" method="post" class="row g-3">
    <div class="col-md-6">
      <label class="form-label">Conta bancária</label>
      <select name="bank_account_id" class="form-select" required>
        <option value="">Selecione...</option>
        <?php foreach ($bankAccounts as $account): ?>
          <option value="<?= (int) $account['id']; ?>"><?= htmlspecialchars(($account['bank_name'] ?? '') . ' - ' . ($account['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3"><label class="form-label">Carteira</label><input type="text" name="portfolio" class="form-control" value="109" required></div>
    <div class="col-md-3"><label class="form-label">Status</label><select name="status" class="form-select"><option value="READY">READY</option><option value="DRAFT">DRAFT</option></select></div>
    <div class="col-md-4"><label class="form-label">Número do documento</label><input type="text" name="document_number" class="form-control" required></div>
    <div class="col-md-4"><label class="form-label">Nosso número</label><input type="text" name="our_number" class="form-control"></div>
    <div class="col-md-4"><label class="form-label">ID pagamento financeiro (opcional)</label><input type="number" name="financial_operation_payment_id" class="form-control"></div>
    <div class="col-md-4"><label class="form-label">Valor</label><input type="text" name="amount" class="form-control" required></div>
    <div class="col-md-4"><label class="form-label">Emissão</label><input type="date" name="issue_date" class="form-control" value="<?= date('Y-m-d'); ?>" required></div>
    <div class="col-md-4"><label class="form-label">Vencimento</label><input type="date" name="due_date" class="form-control" required></div>
    <div class="col-md-6"><label class="form-label">Nome do sacado</label><input type="text" name="payer_name" class="form-control" required></div>
    <div class="col-md-2"><label class="form-label">Tipo doc.</label><select name="payer_document_type" class="form-select"><option value="CPF">CPF</option><option value="CNPJ">CNPJ</option></select></div>
    <div class="col-md-4"><label class="form-label">Documento</label><input type="text" name="payer_document" class="form-control" required></div>
    <div class="col-md-6"><label class="form-label">Endereço</label><input type="text" name="payer_street" class="form-control" required></div>
    <div class="col-md-3"><label class="form-label">Bairro</label><input type="text" name="payer_neighborhood" class="form-control"></div>
    <div class="col-md-3"><label class="form-label">Cidade</label><input type="text" name="payer_city" class="form-control" required></div>
    <div class="col-md-2"><label class="form-label">UF</label><input type="text" name="payer_state" maxlength="2" class="form-control" required></div>
    <div class="col-md-3"><label class="form-label">CEP</label><input type="text" name="payer_zip_code" class="form-control" required></div>
    <div class="col-12 form-actions">
      <a href="<?= url('bank_slip'); ?>" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Cancelar</a>
      <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> Salvar</button>
    </div>
  </form>
</div>

<script src="<?= url('assets/js/bank_slips/create.js?v=1'); ?>"></script>
