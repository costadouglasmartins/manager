<?php
$filters = $filters ?? ['id' => '', 'name' => '', 'number' => '', 'active' => ''];
$page = $page ?? 1;
$totalPages = $totalPages ?? 1;
$total = $total ?? (is_array($banks) ? count($banks) : 0);

$queryFilters = http_build_query([
	'id'     => $filters['id'],
	'name'   => $filters['name'],
	'number' => $filters['number'],
	'active' => $filters['active'],
]);

$activeLabel = static function ($value): string {
	return ((int) $value === 1) ? 'Ativo' : 'Inativo';
};
?>
<h5>Lista de Bancos</h5>

<form action="<?= url('bank'); ?>" class="row g-3 mb-3" method="get">

  <div class="col-12 col-lg-9">
    <div class="row g-3">

      <div class="col-12 col-md-6 col-lg-3">
        <input class="form-control" id="id" name="id" type="text" placeholder="Código" value="<?= htmlspecialchars($filters['id'], ENT_QUOTES, 'UTF-8'); ?>">
      </div>

      <div class="col-12 col-md-6 col-lg-3">
        <input class="form-control" id="number" maxlength="4" name="number" type="text" placeholder="Número" value="<?= htmlspecialchars($filters['number'], ENT_QUOTES, 'UTF-8'); ?>">
      </div>

      <div class="col-12 col-md-6 col-lg-3">
        <input class="form-control" id="name" maxlength="50" name="name" type="text" placeholder="Nome" value="<?= htmlspecialchars($filters['name'], ENT_QUOTES, 'UTF-8'); ?>">
      </div>

      <div class="col-12 col-md-6 col-lg-3">
        <select class="form-select" id="active" name="active">
          <option value="" <?= $filters['active'] === '' ? 'selected' : ''; ?>>Todos</option>
          <option value="1" <?= $filters['active'] === '1' ? 'selected' : ''; ?>>Ativos</option>
          <option value="0" <?= $filters['active'] === '0' ? 'selected' : ''; ?>>Inativos</option>
        </select>
      </div>

    </div>
  </div>

  <div class="col-12 col-lg-3">
    <div class="d-flex flex-column gap-2 align-items-center align-items-lg-end view-actions">
      <button type="submit" class="btn btn-primary w-100">Pesquisar</button>
      <a href="<?= url('bank'); ?>" class="btn btn-secondary w-100">Limpar</a>
      <?php if (authCan('bank.create')): ?>
        <a href="<?= url('bank/create'); ?>" class="btn btn-success w-100">Novo(a)</a>
      <?php endif; ?>
    </div>
  </div>

</form>

<hr>

<div class="table-responsive">
  <table class="table table-striped table-hover">
    <thead>
      <tr>
        <th>#</th>
        <th>Número</th>
        <th>Nome</th>
        <th>Status</th>
        <th class="text-end">Ações</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!empty($banks)): ?>
        <?php foreach ($banks as $row): ?>
          <tr>
            <td style="width:1%;"><?= (int) $row['id']; ?></td>
            <td><?= htmlspecialchars($row['number'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td>
              <span class="badge <?= ((int) $row['active'] === 1) ? 'text-bg-success' : 'text-bg-secondary'; ?>">
                <?= $activeLabel($row['active']); ?>
              </span>
            </td>
            <td style="width:1%;" class="text-nowrap text-end">
              <?php if (authCan('bank.edit')): ?>
                <a href="<?= url('bank/edit/' . $row['id']); ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Editar"><i class="bi bi-pencil-square"></i></a>
              <?php endif; ?>
              <?php if (authCan('bank.informations')): ?>
                <a href="<?= url('bank/informations/' . $row['id']); ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Saiba mais"><i class="bi bi-search"></i></a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr>
          <td colspan="5" class="text-center">Nenhum registro encontrado.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<p class="text-muted"><?= (int) $total; ?> registro(s) encontrado(s)</p>

<?php if ($totalPages > 1): ?>
  <?= paginationLinks('bank', $page, $totalPages, $queryFilters, 2, 'Anterior', 'Próxima'); ?>
<?php endif; ?>
