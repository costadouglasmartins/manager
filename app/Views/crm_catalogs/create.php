<?php
$catalogMeta = (isset($catalogMeta) && is_array($catalogMeta)) ? $catalogMeta : [];
$formData = (isset($formData) && is_array($formData)) ? $formData : [];
$resource = (string) ($catalogMeta['resource'] ?? '');
$isPipeline = !empty($catalogMeta['is_pipeline']);
$bootstrapClass = (string) ($formData['bootstrap_class'] ?? 'text-bg-secondary');
$activeValue = (string) ($formData['active'] ?? '1');
$isWon = (string) ($formData['is_won'] ?? '0') === '1';
$isLost = (string) ($formData['is_lost'] ?? '0') === '1';
?>

<div class="app-page">
  <div class="app-page-header">
    <div>
      <span class="app-page-kicker"><i class="bi <?= htmlspecialchars((string) ($catalogMeta['icon'] ?? 'bi-gear'), ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i> CRM</span>
      <h5><?= htmlspecialchars((string) ($catalogMeta['create_title'] ?? 'Novo cadastro CRM'), ENT_QUOTES, 'UTF-8'); ?></h5>
    </div>
    <span class="badge <?= htmlspecialchars($bootstrapClass, ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars((string) ($formData['name'] ?? 'Preview'), ENT_QUOTES, 'UTF-8'); ?></span>
  </div>

  <?php if (!empty($formError)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars((string) $formError, ENT_QUOTES, 'UTF-8'); ?></div>
  <?php endif; ?>

  <form action="<?= url($resource . '/store'); ?>" class="needs-validation" method="POST" novalidate>
    <section class="border rounded p-3 mb-3">
      <div class="row g-3">
        <div class="col-12 col-lg-6">
          <label class="form-label" for="catalogName">Nome *</label>
          <input autocomplete="off" class="form-control" id="catalogName" maxlength="120" name="name" required type="text" value="<?= htmlspecialchars((string) ($formData['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
          <div class="invalid-feedback">Informe o nome do cadastro.</div>
        </div>

        <div class="col-12 col-lg-6">
          <label class="form-label" for="catalogSlug">Chave</label>
          <input autocomplete="off" class="form-control" id="catalogSlug" maxlength="140" name="slug" pattern="[a-z0-9]+(?:[_-][a-z0-9]+)*" placeholder="Gerada automaticamente quando vazia" type="text" value="<?= htmlspecialchars((string) ($formData['slug'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
          <div class="form-text">Use letras minúsculas, números, hífens ou sublinhados. Evite alterar a chave depois que estiver em uso.</div>
          <div class="invalid-feedback">Use somente letras minúsculas, números, hífens ou sublinhados.</div>
        </div>

        <div class="col-12 col-md-5">
          <label class="form-label" for="catalogBootstrapClass">Classe Bootstrap *</label>
          <input autocomplete="off" class="form-control" id="catalogBootstrapClass" maxlength="120" name="bootstrap_class" required type="text" value="<?= htmlspecialchars($bootstrapClass, ENT_QUOTES, 'UTF-8'); ?>">
          <div class="form-text">Ex.: text-bg-primary, text-bg-success ou text-bg-warning.</div>
          <div class="invalid-feedback">Informe a classe Bootstrap.</div>
        </div>

        <div class="col-12 col-md-3">
          <label class="form-label" for="catalogSortOrder">Ordem</label>
          <input class="form-control" id="catalogSortOrder" min="0" name="sort_order" step="1" type="number" value="<?= htmlspecialchars((string) ($formData['sort_order'] ?? '0'), ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="col-12 col-md-4">
          <label class="form-label" for="catalogActive">Status</label>
          <select class="form-select" id="catalogActive" name="active">
            <option value="1" <?= $activeValue === '1' ? 'selected' : ''; ?>>Ativo</option>
            <option value="0" <?= $activeValue === '0' ? 'selected' : ''; ?>>Inativo</option>
          </select>
        </div>

        <?php if ($isPipeline): ?>
          <div class="col-12 col-md-4">
            <label class="form-label" for="catalogDefaultProbability">Probabilidade padrão (%) *</label>
            <input class="form-control" id="catalogDefaultProbability" max="100" min="0" name="default_probability" required step="1" type="number" value="<?= htmlspecialchars((string) ($formData['default_probability'] ?? '0'), ENT_QUOTES, 'UTF-8'); ?>">
            <div class="invalid-feedback">Informe uma probabilidade entre 0 e 100.</div>
          </div>

          <div class="col-12 col-md-4 d-flex align-items-end">
            <div class="form-check mb-2">
              <input name="is_won" type="hidden" value="0">
              <input class="form-check-input" id="catalogIsWon" name="is_won" type="checkbox" value="1" <?= $isWon ? 'checked' : ''; ?>>
              <label class="form-check-label" for="catalogIsWon">Representa oportunidade ganha</label>
            </div>
          </div>

          <div class="col-12 col-md-4 d-flex align-items-end">
            <div class="form-check mb-2">
              <input name="is_lost" type="hidden" value="0">
              <input class="form-check-input" id="catalogIsLost" name="is_lost" type="checkbox" value="1" <?= $isLost ? 'checked' : ''; ?>>
              <label class="form-check-label" for="catalogIsLost">Representa oportunidade perdida</label>
            </div>
          </div>
        <?php endif; ?>

        <div class="col-12">
          <label class="form-label" for="catalogDescription">Descrição</label>
          <textarea class="form-control" id="catalogDescription" maxlength="1000" name="description" rows="5"><?= htmlspecialchars((string) ($formData['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>
      </div>
    </section>

    <div class="form-actions">
      <a class="btn btn-secondary" href="<?= url($resource); ?>"><i class="bi bi-arrow-left" aria-hidden="true"></i> Cancelar</a>
      <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg" aria-hidden="true"></i> Salvar</button>
    </div>
  </form>
</div>
