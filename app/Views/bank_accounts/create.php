<div class="app-page">
  <div class="app-page-header">
    <div>
      <span class="app-page-kicker"><i class="bi bi-wallet2"></i> Contas Bancárias</span>
      <h5>Criar conta bancária</h5>
    </div>
  </div>

  <form action="<?= url('bank_account/store'); ?>" method="POST" class="needs-validation" novalidate>
    <section class="app-section">
      <div class="row g-3">
        <div class="col-12">
          <h6 class="mb-0">Dados da conta</h6>
          <p class="text-muted small mb-0 mt-1">Preencha os dados principais da conta e habilite a emissão de boletos apenas quando necessário.</p>
        </div>

        <div class="col-12">
          <label class="form-label" for="name">Nome</label>
          <input autocomplete="off" class="form-control" id="name" maxlength="50" name="name" required type="text">
          <div class="invalid-feedback">Informe o nome.</div>
        </div>

        <div class="col-12 col-lg-6">
          <label class="form-label" for="agency">Agência</label>
          <input autocomplete="off" class="form-control" id="agency" maxlength="6" name="agency" required type="text">
          <div class="invalid-feedback">Informe a agência.</div>
        </div>

        <div class="col-12 col-lg-6">
          <label class="form-label" for="account">Conta</label>
          <input autocomplete="off" class="form-control" id="account" maxlength="50" name="account" required type="text">
          <div class="invalid-feedback">Informe a conta.</div>
        </div>

        <div class="col-12">
          <h6 class="mb-0 mt-2">Pix</h6>
          <p class="text-muted small mb-0 mt-1">Os dados Pix são opcionais e independentes da emissão de boletos.</p>
        </div>

        <div class="col-12 col-lg-6">
          <label class="form-label" for="pix_key_type">Tipo da chave Pix</label>
          <select class="form-select" id="pix_key_type" name="pix_key_type">
            <option value="" selected>Não informado</option>
            <option value="CPF">CPF</option>
            <option value="CNPJ">CNPJ</option>
            <option value="EMAIL">E-mail</option>
            <option value="PHONE">Telefone</option>
            <option value="RANDOM">Aleatória</option>
          </select>
        </div>

        <div class="col-12 col-lg-6">
          <label class="form-label" for="pix_key">Chave Pix</label>
          <input autocomplete="off" class="form-control" id="pix_key" maxlength="255" name="pix_key" type="text">
        </div>

        <div class="col-12 mt-2">
          <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-lg-between gap-3 rounded-3 border p-3 bg-body-tertiary">
            <div>
              <h6 class="mb-1">Emissão de boletos</h6>
              <p class="text-muted small mb-0">Ative para selecionar o banco emissor e informar os parâmetros usados na geração de boletos deste tenant.</p>
            </div>
            <div class="form-check form-switch m-0">
              <input class="form-check-input js-bank-slip-toggle" id="enable_bank_slip" role="switch" type="checkbox">
              <label class="form-check-label fw-semibold" for="enable_bank_slip">Habilitar emissão de boletos</label>
            </div>
          </div>
        </div>

        <div class="col-12 js-bank-slip-field d-none">
          <label class="form-label" for="bank_id">Banco</label>
          <select class="form-select" id="bank_id" name="bank_id">
            <option value="" selected disabled>Selecione...</option>
            <?php foreach ($banks as $bank): ?>
              <?php
              $bankNumber = trim((string) ($bank['number'] ?? ''));
              $bankLabelParts = [$bankNumber, trim((string) ($bank['name'] ?? ''))];
              $bankCnab = trim((string) ($bank['cnab'] ?? ''));
              $bankLabelParts[] = 'CNAB ' . $bankCnab;
              if ($bankNumber > 0 || $bankNumber !== '000')
                { ?>
                <option value="<?= (int) $bank['id']; ?>" data-bank-cnab="<?= htmlspecialchars($bankCnab, ENT_QUOTES, 'UTF-8'); ?>" data-bank-number="<?= htmlspecialchars($bankNumber, ENT_QUOTES, 'UTF-8'); ?>">
                  <?= htmlspecialchars(implode(' - ', array_filter($bankLabelParts, static fn ($part): bool => $part !== '')), ENT_QUOTES, 'UTF-8'); ?>
                </option>
                <?php
                } ?>
            <?php endforeach; ?>
          </select>
          <div class="invalid-feedback">Selecione o banco.</div>
        </div>

        <div class="col-12 js-bank-slip-field d-none">
          <h6 class="mb-0">Configurações do boleto</h6>
          <p class="text-muted small mb-0 mt-1">Esses campos só serão considerados quando a emissão de boletos estiver habilitada.</p>
        </div>

        <div class="col-12 col-lg-6 js-bank-slip-field d-none">
          <label class="form-label" for="bank_slip_wallet">Carteira</label>
          <input autocomplete="off" class="form-control" disabled id="bank_slip_wallet" maxlength="50" name="bank_slip_wallet" type="text">
        </div>

        <div class="col-12 col-lg-6 js-bank-slip-field js-bank-slip-cnab240-field d-none">
          <label class="form-label" for="bank_slip_species_title">Espécie do título</label>
          <input autocomplete="off" class="form-control" disabled id="bank_slip_species_title" maxlength="50" name="bank_slip_species_title" type="text">
        </div>

        <div class="col-12 col-lg-6 js-bank-slip-field js-bank-slip-cnab240-field d-none">
          <label class="form-label" for="bank_slip_protest">Protestar</label>
          <select class="form-select" disabled id="bank_slip_protest" name="bank_slip_protest">
            <option value="0">Não</option>
            <option value="1">Sim</option>
          </select>
        </div>

        <div class="col-12 col-lg-6 js-bank-slip-field js-bank-slip-cnab240-field d-none">
          <label class="form-label" for="bank_slip_protest_number_of_days">Dias para protesto</label>
          <input autocomplete="off" class="form-control only-numbers" disabled id="bank_slip_protest_number_of_days" inputmode="numeric" maxlength="2" name="bank_slip_protest_number_of_days" type="text">
          <div class="invalid-feedback">Informe no mínimo 5 dias para protesto.</div>
        </div>

        <div class="col-12 col-lg-3 js-bank-slip-field js-bank-slip-cnab240-field d-none">
          <label class="form-label" for="bank_slip_interest_type">Tipo de juros</label>
          <select class="form-select" disabled id="bank_slip_interest_type" name="bank_slip_interest_type">
            <option value="" selected>Não informado</option>
            <option value="DAILY">Diário</option>
            <option value="MONTHLY">Mensal</option>
          </select>
        </div>

        <div class="col-12 col-lg-3 js-bank-slip-field js-bank-slip-cnab240-field d-none">
          <label class="form-label" for="bank_slip_interest_amount_type">Tipo do valor dos juros</label>
          <select class="form-select" disabled id="bank_slip_interest_amount_type" name="bank_slip_interest_amount_type">
            <option value="" selected>Não informado</option>
            <option value="PERCENTAGE">Percentual</option>
            <option value="FIXED_AMOUNT">Valor fixo</option>
          </select>
        </div>

        <div class="col-12 col-lg-3 js-bank-slip-field js-bank-slip-cnab240-field d-none">
          <label class="form-label" for="bank_slip_interest">Juros</label>
          <input autocomplete="off" class="form-control js-money" disabled id="bank_slip_interest" inputmode="decimal" name="bank_slip_interest" placeholder="0,00" type="text">
        </div>

        <div class="col-12 col-lg-3 js-bank-slip-field js-bank-slip-cnab240-field d-none">
          <label class="form-label" for="bank_slip_late_fee_amount">Multa(R$)</label>
          <input autocomplete="off" class="form-control js-money" disabled id="bank_slip_late_fee_amount" inputmode="decimal" name="bank_slip_late_fee_amount" placeholder="0,00" type="text">
        </div>

        <div class="col-12 col-lg-6 js-bank-slip-field d-none">
          <label class="form-label" for="bank_slip_instructions_line_1">Instrução 1</label>
          <input autocomplete="off" class="form-control" disabled id="bank_slip_instructions_line_1" maxlength="255" name="bank_slip_instructions_line_1" type="text">
        </div>

        <div class="col-12 col-lg-6 js-bank-slip-field d-none">
          <label class="form-label" for="bank_slip_instructions_line_2">Instrução 2</label>
          <input autocomplete="off" class="form-control" disabled id="bank_slip_instructions_line_2" maxlength="255" name="bank_slip_instructions_line_2" type="text">
        </div>

        <div class="col-12 col-lg-6 js-bank-slip-field d-none">
          <label class="form-label" for="bank_slip_instructions_line_3">Instrução 3</label>
          <input autocomplete="off" class="form-control" disabled id="bank_slip_instructions_line_3" maxlength="255" name="bank_slip_instructions_line_3" type="text">
        </div>

        <div class="col-12 col-lg-6 js-bank-slip-field d-none">
          <label class="form-label" for="bank_slip_instructions_line_4">Instrução 4</label>
          <input autocomplete="off" class="form-control" disabled id="bank_slip_instructions_line_4" maxlength="255" name="bank_slip_instructions_line_4" type="text">
        </div>

        <div class="col-12">
          <label class="form-label" for="active">Status</label>
          <select class="form-select" id="active" name="active">
            <option value="1" selected>Ativa</option>
            <option value="0">Inativa</option>
          </select>
        </div>
      </div>
    </section>
    <div class="form-actions">
      <a href="<?= url('bank_account'); ?>" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Cancelar</a>
      <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> Salvar</button>
    </div>
  </form>
</div>

<script src="<?= url('assets/js/bank_accounts/create.js?v=6'); ?>"></script>
