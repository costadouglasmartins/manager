<!-- Breadcrumbs -->
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item active" aria-current="page">Início</li>
  </ol>
</nav>
<!-- /Breadcrumbs -->

<h5>Início</h5>

<?php if (!empty($tenant)): ?>
  <div class="alert alert-info">
    Tenant ativo: <strong><?= htmlspecialchars($tenant['name']) ?></strong>
    (<?= htmlspecialchars('manager_' . str_pad($tenant['database_number'], 4, '0', STR_PAD_LEFT)) ?>)
  </div>

  <?php if (!empty($schemaVersions)): ?>
    <div class="card mt-3">
      <div class="card-header">
        <strong>Schema Versions</strong>
      </div>
      <div class="card-body p-0">
        <table class="table table-striped table-hover mb-0">
          <thead class="table-dark">
            <tr>
              <th>ID</th>
              <th>Version</th>
              <th>Description</th>
              <th>Executed At</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($schemaVersions as $row): ?>
              <tr>
                <td><?= htmlspecialchars($row['id'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['version'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['description'] ?? $row['name'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['executed_at'] ?? $row['created'] ?? '') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php else: ?>
    <div class="alert alert-warning mt-3">Nenhuma versão de schema encontrada.</div>
  <?php endif; ?>

<?php else: ?>
  <div class="alert alert-warning">
    Nenhum tenant selecionado. <a href="<?= url('tenant/select') ?>">Selecione um tenant</a> para visualizar os dados.
  </div>
<?php endif; ?>
