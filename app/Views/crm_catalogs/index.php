<?php
$catalogMeta = (isset($catalogMeta) && is_array($catalogMeta)) ? $catalogMeta : [];
$catalogRows = (isset($catalogRows) && is_array($catalogRows)) ? $catalogRows : [];
$resource = (string) ($catalogMeta['resource'] ?? '');
$isPipeline = !empty($catalogMeta['is_pipeline']);
$filters = $filters ?? [
	'id' => '',
	'name' => '',
	'slug' => '',
	'bootstrap_class' => '',
	'active' => '',
	'is_won' => '',
	'is_lost' => '',
];
$page = max(1, (int) ($page ?? 1));
$totalPages = max(1, (int) ($totalPages ?? 1));
$total = (int) ($total ?? count($catalogRows));
$queryData = [
	'id' => $filters['id'] ?? '',
	'name' => $filters['name'] ?? '',
	'slug' => $filters['slug'] ?? '',
	'bootstrap_class' => $filters['bootstrap_class'] ?? '',
	'active' => $filters['active'] ?? '',
];
if ($isPipeline)
	{
	$queryData['is_won'] = $filters['is_won'] ?? '';
	$queryData['is_lost'] = $filters['is_lost'] ?? '';
	}
$queryFilters = http_build_query($queryData);
$columnCount = $isPipeline ? 10 : 8;
?>

<div class="app-page">
  <div class="app-page-header">
    <div>
      <span class="app-page-kicker"><i class="bi <?= htmlspecialchars((string) ($catalogMeta['icon'] ?? 'bi-gear'), ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i> CRM</span>
      <h5><?= htmlspecialchars((string) ($catalogMeta['list_title'] ?? 'Cadastros do CRM'), ENT_QUOTES, 'UTF-8'); ?></h5>
    </div>
  </div>

  <?php if (!empty($formError)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars((string) $formError, ENT_QUOTES, 'UTF-8'); ?></div>
  <?php endif; ?>

  <form action="<?= url($resource); ?>" class="row g-3 mb-3" method="get">
    <div class="col-12 col-lg-9">
      <div class="row g-3">
        <div class="col-12 col-md-2">
          <label class="visually-hidden" for="catalogId">Código</label>
          <input class="form-control" id="catalogId" name="id" placeholder="Código" type="text" value="<?= htmlspecialchars((string) ($filters['id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="col-12 col-md-4">
          <label class="visually-hidden" for="catalogName">Nome</label>
          <input class="form-control" id="catalogName" maxlength="120" name="name" placeholder="Nome" type="text" value="<?= htmlspecialchars((string) ($filters['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="col-12 col-md-3">
          <label class="visually-hidden" for="catalogSlug">Chave</label>
          <input class="form-control" id="catalogSlug" maxlength="140" name="slug" placeholder="Chave" type="text" value="<?= htmlspecialchars((string) ($filters['slug'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="col-12 col-md-3">
          <label class="visually-hidden" for="catalogActive">Status</label>
          <select class="form-select" id="catalogActive" name="active">
            <option value="" <?= (string) ($filters['active'] ?? '') === '' ? 'selected' : ''; ?>>Ativos e inativos</option>
            <option value="1" <?= (string) ($filters['active'] ?? '') === '1' ? 'selected' : ''; ?>>Ativos</option>
            <option value="0" <?= (string) ($filters['active'] ?? '') === '0' ? 'selected' : ''; ?>>Inativos</option>
          </select>
        </div>

        <div class="col-12 col-md-4">
          <label class="visually-hidden" for="catalogBootstrapClass">Classe Bootstrap</label>
          <input class="form-control" id="catalogBootstrapClass" maxlength="120" name="bootstrap_class" placeholder="Classe Bootstrap" type="text" value="<?= htmlspecialchars((string) ($filters['bootstrap_class'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <?php if ($isPipeline): ?>
          <div class="col-12 col-md-4">
            <label class="visually-hidden" for="catalogIsWon">Etapa ganha</label>
            <select class="form-select" id="catalogIsWon" name="is_won">
              <option value="" <?= (string) ($filters['is_won'] ?? '') === '' ? 'selected' : ''; ?>>Etapas ganhas e não ganhas</option>
              <option value="1" <?= (string) ($filters['is_won'] ?? '') === '1' ? 'selected' : ''; ?>>Somente ganhas</option>
              <option value="0" <?= (string) ($filters['is_won'] ?? '') === '0' ? 'selected' : ''; ?>>Não ganhas</option>
            </select>
          </div>

          <div class="col-12 col-md-4">
            <label class="visually-hidden" for="catalogIsLost">Etapa perdida</label>
            <select class="form-select" id="catalogIsLost" name="is_lost">
              <option value="" <?= (string) ($filters['is_lost'] ?? '') === '' ? 'selected' : ''; ?>>Etapas perdidas e não perdidas</option>
              <option value="1" <?= (string) ($filters['is_lost'] ?? '') === '1' ? 'selected' : ''; ?>>Somente perdidas</option>
              <option value="0" <?= (string) ($filters['is_lost'] ?? '') === '0' ? 'selected' : ''; ?>>Não perdidas</option>
            </select>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="col-12 col-lg-3">
      <div class="d-flex flex-column gap-2 align-items-center align-items-lg-end">
        <button class="btn btn-primary w-100" type="submit">Pesquisar</button>
        <a class="btn btn-secondary w-100" href="<?= url($resource); ?>">Limpar</a>
        <?php if (authCan($resource . '.create')): ?>
          <a class="btn btn-success w-100" href="<?= url($resource . '/create'); ?>">Novo(a)</a>
        <?php endif; ?>
      </div>
    </div>
  </form>

  <hr>

  <div class="table-responsive">
    <table class="table table-striped table-hover align-middle">
      <thead>
        <tr>
          <th>#</th>
          <th>Nome</th>
          <th>Chave</th>
          <th>Descrição</th>
          <th>Estilo</th>
          <?php if ($isPipeline): ?>
            <th>Probabilidade</th>
            <th>Resultado</th>
          <?php endif; ?>
          <th>Ordem</th>
          <th>Status</th>
          <th class="text-end">Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($catalogRows !== []): ?>
          <?php foreach ($catalogRows as $row): ?>
            <?php
            $resultLabel = 'Em andamento';
            $resultClass = 'text-bg-secondary';
            if ((int) ($row['is_won'] ?? 0) === 1)
              {
              $resultLabel = 'Ganha';
              $resultClass = 'text-bg-success';
              }
            elseif ((int) ($row['is_lost'] ?? 0) === 1)
              {
              $resultLabel = 'Perdida';
              $resultClass = 'text-bg-danger';
              }
            ?>
            <tr>
              <td><?= (int) ($row['id'] ?? 0); ?></td>
              <td><span class="badge <?= htmlspecialchars((string) ($row['bootstrap_class'] ?? 'text-bg-secondary'), ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars((string) ($row['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span></td>
              <td><code><?= htmlspecialchars((string) ($row['slug'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code></td>
              <td><?= nl2br(htmlspecialchars((string) ($row['description'] ?? ''), ENT_QUOTES, 'UTF-8')); ?></td>
              <td><code><?= htmlspecialchars((string) ($row['bootstrap_class'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code></td>
              <?php if ($isPipeline): ?>
                <td><?= (int) ($row['default_probability'] ?? 0); ?>%</td>
                <td><span class="badge <?= $resultClass; ?>"><?= $resultLabel; ?></span></td>
              <?php endif; ?>
              <td><?= (int) ($row['sort_order'] ?? 0); ?></td>
              <td><span class="badge <?= (int) ($row['active'] ?? 0) === 1 ? 'text-bg-success' : 'text-bg-secondary'; ?>"><?= (int) ($row['active'] ?? 0) === 1 ? 'Ativo' : 'Inativo'; ?></span></td>
              <td class="text-nowrap text-end">
                <?php if (authCan($resource . '.delete') && (int) ($row['active'] ?? 0) === 1): ?>
                  <a aria-label="Desativar <?= htmlspecialchars((string) ($catalogMeta['label'] ?? 'cadastro'), ENT_QUOTES, 'UTF-8'); ?>"
                     class="js-record-delete-link text-danger"
                     data-bs-content="Desativar"
                     data-bs-target="#recordDeleteModal"
                     data-bs-toggle="modal"
                     data-bs-trigger="hover focus"
                     data-record-delete-description="<?= htmlspecialchars((string) ($catalogMeta['delete_description'] ?? 'o cadastro'), ENT_QUOTES, 'UTF-8'); ?>"
                     data-record-delete-url="<?= url($resource . '/delete/' . (int) ($row['id'] ?? 0)); ?>"
                     data-record-id="<?= (int) ($row['id'] ?? 0); ?>"
                     data-record-name="<?= htmlspecialchars((string) ($row['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                     href="#"
                     role="button">
                    <i class="bi bi-trash" aria-hidden="true"></i>
                  </a>
                <?php endif; ?>
                <?php if (authCan($resource . '.edit')): ?>
                  <a aria-label="Editar <?= htmlspecialchars((string) ($catalogMeta['label'] ?? 'cadastro'), ENT_QUOTES, 'UTF-8'); ?>" data-bs-content="Editar" data-bs-toggle="popover" data-bs-trigger="hover focus" href="<?= url($resource . '/edit/' . (int) ($row['id'] ?? 0)); ?>"><i class="bi bi-pencil-square" aria-hidden="true"></i></a>
                <?php endif; ?>
                <?php if (authCan($resource . '.informations')): ?>
                  <a aria-label="Ver <?= htmlspecialchars((string) ($catalogMeta['label'] ?? 'cadastro'), ENT_QUOTES, 'UTF-8'); ?>" data-bs-content="Saiba mais" data-bs-toggle="popover" data-bs-trigger="hover focus" href="<?= url($resource . '/informations/' . (int) ($row['id'] ?? 0)); ?>"><i class="bi bi-search" aria-hidden="true"></i></a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td class="text-center" colspan="<?= $columnCount; ?>"><?= htmlspecialchars((string) ($catalogMeta['empty_message'] ?? 'Nenhum cadastro encontrado.'), ENT_QUOTES, 'UTF-8'); ?></td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <p class="text-muted"><?= $total; ?> registro(s) encontrado(s)</p>

  <?php if ($totalPages > 1): ?>
    <?= paginationLinks($resource, $page, $totalPages, $queryFilters, 2, 'Anterior', 'Próxima'); ?>
  <?php endif; ?>
</div>

<?php if (authCan($resource . '.delete')): ?>
  <?php require __DIR__ . '/../shared/delete_modal.php'; ?>
  <script src="<?= url('assets/js/shared/delete_modal.js?v=1'); ?>"></script>
<?php endif; ?>
