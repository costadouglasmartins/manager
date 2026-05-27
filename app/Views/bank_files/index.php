<?php
$filters = $filters ?? ['id' => '', 'file_type' => 'REMITTANCE', 'file_name' => '', 'bank_account_id' => ''];
$page = $page ?? 1;
$totalPages = $totalPages ?? 1;
$total = $total ?? (is_array($bankFiles) ? count($bankFiles) : 0);

$queryFilters = http_build_query([
	'id' => $filters['id'],
	'file_type' => $filters['file_type'],
	'file_name' => $filters['file_name'],
	'bank_account_id' => $filters['bank_account_id'],
]);

$accountLabel = static function (array $row): string {
	$bank = trim((string) ($row['bank_name'] ?? ''));
	$account = trim((string) ($row['bank_account_name'] ?? ''));
	$number = preg_replace('/\D+/', '', (string) ($row['bank_number'] ?? '')) ?? '';

	$parts = [];
	if ($number !== '')
		{
		$parts[] = $number;
		}
	if ($bank !== '')
		{
		$parts[] = $bank;
		}
	if ($account !== '')
		{
		$parts[] = $account;
		}

	return implode(' - ', $parts);
};
?>
<link rel="stylesheet" href="<?= url('assets/css/bank_files/index.css?v=1'); ?>">

<h5>Remessas</h5>

<?php if (!empty($formError)): ?>
  <div class="alert alert-danger"><?= htmlspecialchars((string) $formError, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>

<form action="<?= url('bank_file'); ?>" class="row g-3 mb-3" method="get">
  <div class="col-12 col-lg-9">
    <div class="row g-3">
      <div class="col-12 col-md-2">
        <input class="form-control" id="id" name="id" type="text" placeholder="Código" value="<?= htmlspecialchars($filters['id'], ENT_QUOTES, 'UTF-8'); ?>">
      </div>
      <div class="col-12 col-md-3">
        <input class="form-control" id="file_name" name="file_name" type="text" placeholder="Nome do arquivo" value="<?= htmlspecialchars($filters['file_name'], ENT_QUOTES, 'UTF-8'); ?>">
      </div>
      <div class="col-12 col-md-3">
        <input class="form-control" id="bank_account_id" name="bank_account_id" type="text" placeholder="ID conta bancária" value="<?= htmlspecialchars($filters['bank_account_id'], ENT_QUOTES, 'UTF-8'); ?>">
      </div>
      <div class="col-12 col-md-4">
        <select class="form-select" id="file_type" name="file_type">
          <option value="" <?= $filters['file_type'] === '' ? 'selected' : ''; ?>>Todos os tipos</option>
          <option value="REMITTANCE" <?= $filters['file_type'] === 'REMITTANCE' ? 'selected' : ''; ?>>REMITTANCE</option>
          <option value="RETURN" <?= $filters['file_type'] === 'RETURN' ? 'selected' : ''; ?>>RETURN</option>
        </select>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-3">
    <div class="d-flex flex-column gap-2 align-items-center align-items-lg-end">
      <button type="submit" class="btn btn-primary w-100">Pesquisar</button>
      <a href="<?= url('bank_file'); ?>" class="btn btn-secondary w-100">Limpar</a>
    </div>
  </div>
</form>

<hr>

<div class="table-responsive">
  <table class="table table-striped table-hover align-middle">
    <thead>
      <tr>
        <th>#</th>
        <th>Tipo</th>
        <th>Arquivo</th>
        <th>Conta bancária</th>
        <th>Boletos</th>
        <th>Criado em</th>
        <th class="text-end">Ações</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!empty($bankFiles)): ?>
        <?php foreach ($bankFiles as $row): ?>
          <tr>
            <td style="width:1%;"><?= (int) $row['id']; ?></td>
            <td><?= htmlspecialchars((string) ($row['file_type'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?= htmlspecialchars((string) ($row['file_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?= htmlspecialchars($accountLabel($row), ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?= (int) ($row['bank_slips_count'] ?? 0); ?></td>
            <td><?= htmlspecialchars((string) ($row['created'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
            <td style="width:1%;" class="text-nowrap text-end">
              <?php if (authCan('bank_file.delete') && strtoupper((string) ($row['file_type'] ?? '')) === 'REMITTANCE'): ?>
                <a href="#"
                   class="js-bank-file-delete-link text-danger"
                   data-bs-toggle="modal"
                   data-bs-target="#bankFileDeleteModal"
                   data-bank-file-id="<?= (int) $row['id']; ?>"
                   data-bank-file-name="<?= htmlspecialchars((string) ($row['file_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                   data-bank-file-delete-url="<?= url('bank_file/delete/' . (int) $row['id']); ?>"
                   role="button"
                   aria-label="Excluir remessa"
                   title="Excluir">
                  <i class="bi bi-trash"></i>
                </a>
              <?php endif; ?>
              <?php if (authCan('bank_file.informations')): ?>
                <a href="<?= url('bank_file/informations/' . (int) $row['id']); ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Saiba mais"><i class="bi bi-search"></i></a>
              <?php endif; ?>
              <?php if (authCan('bank_slip.generateRemittance') && strtoupper((string) ($row['file_type'] ?? '')) === 'REMITTANCE'): ?>
                <a href="<?= url('bank_slip/downloadRemittance/' . (int) $row['id']); ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Baixar"><i class="bi bi-file-arrow-down"></i></a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr>
          <td colspan="7" class="text-center">Nenhum registro encontrado.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<p class="text-muted"><?= (int) $total; ?> registro(s) encontrado(s)</p>

<?php if ($totalPages > 1): ?>
  <?= paginationLinks('bank_file', $page, $totalPages, $queryFilters, 2, 'Anterior', 'Próxima'); ?>
<?php endif; ?>

<?php if (authCan('bank_file.delete')): ?>
  <div class="modal fade" id="bankFileDeleteModal" tabindex="-1" aria-labelledby="bankFileDeleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <form id="bankFileDeleteForm" method="POST" action="">
          <div class="modal-header">
            <h5 class="modal-title" id="bankFileDeleteModalLabel">Confirmar exclusão</h5>
            <a href="#" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></a>
          </div>
          <div class="modal-body">
            <p class="mb-0">Deseja realmente excluir a remessa <strong id="bankFileDeleteName">#</strong> e os boletos vinculados?</p>
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

<script src="<?= url('assets/js/bank_files/index.js?v=1'); ?>"></script>
