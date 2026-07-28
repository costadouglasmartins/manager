<?php
$catalogMeta = (isset($catalogMeta) && is_array($catalogMeta)) ? $catalogMeta : [];
$catalog = (isset($catalog) && is_array($catalog)) ? $catalog : [];
$resource = (string) ($catalogMeta['resource'] ?? '');
$isPipeline = !empty($catalogMeta['is_pipeline']);
$formatDateTime = static function ($value): string {
	$value = trim((string) ($value ?? ''));
	if ($value === '' || $value === '0000-00-00 00:00:00')
		{
		return '';
		}

	try
		{
		return (new \DateTimeImmutable($value))->format('d/m/Y H:i');
		}
	catch (\Throwable $e)
		{
		return $value;
		}
};
$pipelineResult = 'Em andamento';
$pipelineResultClass = 'text-bg-secondary';
if ((int) ($catalog['is_won'] ?? 0) === 1)
	{
	$pipelineResult = 'Ganha';
	$pipelineResultClass = 'text-bg-success';
	}
elseif ((int) ($catalog['is_lost'] ?? 0) === 1)
	{
	$pipelineResult = 'Perdida';
	$pipelineResultClass = 'text-bg-danger';
	}
?>

<div class="app-page">
  <div class="app-page-header">
    <div>
      <span class="app-page-kicker"><i class="bi <?= htmlspecialchars((string) ($catalogMeta['icon'] ?? 'bi-gear'), ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i> CRM</span>
      <h5><?= htmlspecialchars((string) ($catalogMeta['information_title'] ?? 'Cadastro CRM'), ENT_QUOTES, 'UTF-8'); ?></h5>
    </div>
    <span class="badge <?= htmlspecialchars((string) ($catalog['bootstrap_class'] ?? 'text-bg-secondary'), ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars((string) ($catalog['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
  </div>

  <section class="border rounded p-3 mb-3">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <tbody>
          <tr>
            <th class="text-muted">Código</th>
            <td><?= (int) ($catalog['id'] ?? 0); ?></td>
          </tr>
          <tr>
            <th class="text-muted">Nome</th>
            <td><?= htmlspecialchars((string) ($catalog['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
          </tr>
          <tr>
            <th class="text-muted">Chave</th>
            <td><code><?= htmlspecialchars((string) ($catalog['slug'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code></td>
          </tr>
          <tr>
            <th class="text-muted">Descrição</th>
            <td><?= nl2br(htmlspecialchars((string) ($catalog['description'] ?? ''), ENT_QUOTES, 'UTF-8')); ?></td>
          </tr>
          <tr>
            <th class="text-muted">Classe Bootstrap</th>
            <td><code><?= htmlspecialchars((string) ($catalog['bootstrap_class'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code></td>
          </tr>
          <?php if ($isPipeline): ?>
            <tr>
              <th class="text-muted">Probabilidade padrão</th>
              <td><?= (int) ($catalog['default_probability'] ?? 0); ?>%</td>
            </tr>
            <tr>
              <th class="text-muted">Resultado</th>
              <td><span class="badge <?= $pipelineResultClass; ?>"><?= $pipelineResult; ?></span></td>
            </tr>
          <?php endif; ?>
          <tr>
            <th class="text-muted">Ordem</th>
            <td><?= (int) ($catalog['sort_order'] ?? 0); ?></td>
          </tr>
          <tr>
            <th class="text-muted">Status</th>
            <td><span class="badge <?= (int) ($catalog['active'] ?? 0) === 1 ? 'text-bg-success' : 'text-bg-secondary'; ?>"><?= (int) ($catalog['active'] ?? 0) === 1 ? 'Ativo' : 'Inativo'; ?></span></td>
          </tr>
          <tr>
            <th class="text-muted">Criado em</th>
            <td><?= htmlspecialchars($formatDateTime($catalog['created'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
          </tr>
          <tr>
            <th class="text-muted">Atualizado em</th>
            <td><?= htmlspecialchars($formatDateTime($catalog['modified'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>

  <div class="form-actions">
    <a class="btn btn-secondary" href="<?= url($resource); ?>"><i class="bi bi-arrow-left" aria-hidden="true"></i> Voltar</a>
    <?php if (authCan($resource . '.edit')): ?>
      <a class="btn btn-primary" href="<?= url($resource . '/edit/' . (int) ($catalog['id'] ?? 0)); ?>"><i class="bi bi-pencil-square" aria-hidden="true"></i> Editar</a>
    <?php endif; ?>
  </div>
</div>
