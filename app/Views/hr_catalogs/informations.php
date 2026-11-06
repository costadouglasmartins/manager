<?php
// Variables expected from the informations action: $catalogMeta (resource, icon, information_title), $catalog (id, name, description, active, created, modified).
$esc = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$catalogMeta = (isset($catalogMeta) && is_array($catalogMeta)) ? $catalogMeta : [];
$catalog = (isset($catalog) && is_array($catalog)) ? $catalog : [];
$resource = (string) ($catalogMeta['resource'] ?? '');
$isActive = (int) ($catalog['active'] ?? 0) === 1;
$dateTimeBr = static function ($value): string {
	$value = trim((string) $value);
	return preg_match('/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}:\d{2})/', $value, $m) === 1 ? $m[3] . '/' . $m[2] . '/' . $m[1] . ' ' . $m[4] : '—';
};
?>
<div class="app-page">
  <div class="app-page-header">
    <div>
      <span class="app-page-kicker"><i class="bi <?= $esc($catalogMeta['icon'] ?? 'bi-diagram-3'); ?>" aria-hidden="true"></i> RH</span>
      <h5><?= $esc($catalogMeta['information_title'] ?? 'Cadastro de RH'); ?></h5>
    </div>
    <span class="badge <?= $isActive ? 'text-bg-success' : 'text-bg-secondary'; ?>"><?= $isActive ? 'Ativo' : 'Inativo'; ?></span>
  </div>

  <section class="border rounded p-3 mb-3">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <tbody>
          <tr>
            <th class="text-muted" scope="row" style="width: 220px;">Código</th>
            <td><?= (int) ($catalog['id'] ?? 0); ?></td>
          </tr>
          <tr>
            <th class="text-muted" scope="row">Nome</th>
            <td><?= $esc($catalog['name'] ?? ''); ?></td>
          </tr>
          <tr>
            <th class="text-muted" scope="row">Descrição</th>
            <td><?= trim((string) ($catalog['description'] ?? '')) !== '' ? nl2br($esc($catalog['description'])) : '—'; ?></td>
          </tr>
          <tr>
            <th class="text-muted" scope="row">Criado em</th>
            <td><?= $esc($dateTimeBr($catalog['created'] ?? '')); ?></td>
          </tr>
          <tr>
            <th class="text-muted" scope="row">Atualizado em</th>
            <td><?= $esc($dateTimeBr($catalog['modified'] ?? '')); ?></td>
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
