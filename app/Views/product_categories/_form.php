<?php
$formData = $formData ?? [];
$category = $category ?? [];
$attributes = $attributes ?? [];
$templates = $templates ?? [];
$value = static function (string $field, string $default = '') use ($formData, $category): string {
	return (string) ($formData[$field] ?? $category[$field] ?? $default);
};
$description = $value('description');
$editorHtml = trim(preg_replace('/<([a-z][a-z0-9]*)\b[^>]*>/i', '<$1>', strip_tags($description, '<b><strong><i><em><u><br><p><div><ul><ol><li>')) ?? '');
$action = $isEdit ? url('product_category/update/' . (int) ($category['id'] ?? 0)) : url('product_category/store');
$typeLabels = ['TEXT' => 'Texto', 'INTEGER' => 'Número inteiro', 'DECIMAL' => 'Número decimal', 'BOOLEAN' => 'Sim/Não', 'DATE' => 'Data', 'OPTION' => 'Lista de opções'];
?>
<link rel="stylesheet" href="<?= url('assets/css/rich_text_editor.css?v=1'); ?>">
<link rel="stylesheet" href="<?= url('assets/css/product_categories/create.css?v=2'); ?>">

<div class="store-page app-page">
  <div class="store-page-header app-page-header">
    <div><span class="store-page-kicker app-page-kicker"><i class="bi bi-tags" aria-hidden="true"></i> Produtos</span><h5><?= $isEdit ? 'Editar categoria' : 'Criar categoria'; ?></h5></div>
  </div>
  <?php if (!empty($formError)): ?><div class="alert alert-danger" role="alert"><?= htmlspecialchars((string) $formError, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>

  <form action="<?= $action; ?>" method="POST" class="needs-validation store-form js-product-category-form" novalidate>
    <section class="store-section">
      <div class="store-subheader"><h6>Dados da categoria</h6></div>
      <div class="row g-3">
        <div class="col-12 col-md-8"><label class="form-label" for="name">Nome</label><input autocomplete="off" class="form-control" id="name" maxlength="120" name="name" required type="text" value="<?= htmlspecialchars($value('name'), ENT_QUOTES, 'UTF-8'); ?>"><div class="invalid-feedback">Informe o nome da categoria.</div></div>
        <div class="col-12 col-md-4"><label class="form-label" for="active">Status</label><select class="form-select" id="active" name="active"><option value="1" <?= $value('active', '1') === '1' ? 'selected' : ''; ?>>Ativa</option><option value="0" <?= $value('active', '1') === '0' ? 'selected' : ''; ?>>Inativa</option></select></div>
        <div class="col-12">
          <label class="form-label" for="descriptionEditor">Descrição</label>
          <div class="rich-editor" data-rich-editor><div class="rich-editor-toolbar" role="toolbar" aria-label="Formatação da descrição"><button class="btn btn-outline-secondary btn-sm" data-editor-command="bold" type="button" aria-label="Negrito"><i class="bi bi-type-bold" aria-hidden="true"></i></button><button class="btn btn-outline-secondary btn-sm" data-editor-command="italic" type="button" aria-label="Itálico"><i class="bi bi-type-italic" aria-hidden="true"></i></button><button class="btn btn-outline-secondary btn-sm" data-editor-command="underline" type="button" aria-label="Sublinhado"><i class="bi bi-type-underline" aria-hidden="true"></i></button></div><div class="form-control rich-editor-surface" contenteditable="true" data-rich-editor-surface id="descriptionEditor"><?= $editorHtml; ?></div><textarea class="d-none" data-rich-editor-source name="description"><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?></textarea></div>
        </div>
        <?php if (!$isEdit): ?>
          <div class="col-12 col-lg-6">
            <label class="form-label" for="product_category_template_id">Começar por um modelo</label>
            <select class="form-select" id="product_category_template_id" name="product_category_template_id">
              <option value="">Categoria em branco</option>
              <?php foreach ($templates as $template): ?>
                <option value="<?= (int) $template['id']; ?>" <?= $value('product_category_template_id') === (string) $template['id'] ? 'selected' : ''; ?>><?= htmlspecialchars((string) $template['name'], ENT_QUOTES, 'UTF-8'); ?></option>
              <?php endforeach; ?>
            </select>
            <div class="form-text">O modelo copia características, opções e regras condicionais.</div>
          </div>
        <?php endif; ?>
      </div>
    </section>

    <?php if ($isEdit && !empty($attributes)): ?>
      <section class="store-section">
        <div class="store-subheader"><div><h6>Características configuradas</h6><small class="text-muted">Desmarque “Usar” para retirar a característica desta categoria.</small></div></div>
        <div class="table-responsive">
          <table class="table table-hover align-middle"><thead><tr><th>Característica</th><th>Tipo</th><th>Usar</th><th>Obrigatória</th><th>Filtro</th><th>Na lista</th><th>Gera variação</th><th>Por variação</th><th>Ordem</th></tr></thead><tbody>
            <?php foreach ($attributes as $attribute): $attributeId = (int) $attribute['product_attribute_id']; ?>
              <tr>
                <td><strong><?= htmlspecialchars((string) $attribute['name'], ENT_QUOTES, 'UTF-8'); ?></strong><?php if (!empty($attribute['options_text'])): ?><small class="d-block text-muted"><?= htmlspecialchars(str_replace("\n", ', ', (string) $attribute['options_text']), ENT_QUOTES, 'UTF-8'); ?></small><?php endif; ?></td>
                <td><?= htmlspecialchars($typeLabels[strtoupper((string) $attribute['data_type'])] ?? (string) $attribute['data_type'], ENT_QUOTES, 'UTF-8'); ?><?= !empty($attribute['measurement_type']) ? ' · ' . htmlspecialchars((string) $attribute['measurement_type'], ENT_QUOTES, 'UTF-8') : ''; ?></td>
                <?php foreach (['enabled' => 1, 'required' => $attribute['required'], 'filterable' => $attribute['filterable'], 'show_on_list' => $attribute['show_on_list'], 'variation_axis' => $attribute['variation_axis'] ?? 0, 'variation_specific' => $attribute['variation_specific'] ?? 0] as $field => $state): ?><td><input name="attributes[<?= $attributeId; ?>][<?= $field; ?>]" type="hidden" value="0"><input class="form-check-input <?= $field === 'variation_axis' ? 'js-product-variation-axis' : ($field === 'variation_specific' ? 'js-product-variation-specific' : ''); ?>" aria-label="<?= htmlspecialchars($field . ' ' . $attribute['name'], ENT_QUOTES, 'UTF-8'); ?>" name="attributes[<?= $attributeId; ?>][<?= $field; ?>]" type="checkbox" value="1" data-attribute-type="<?= htmlspecialchars(strtoupper((string) $attribute['data_type']), ENT_QUOTES, 'UTF-8'); ?>" <?= (int) $state === 1 ? 'checked' : ''; ?> <?= ($field === 'variation_axis' && strtoupper((string) $attribute['data_type']) !== 'OPTION') ? 'disabled' : ''; ?>></td><?php endforeach; ?>
                <td><input class="form-control form-control-sm" min="0" name="attributes[<?= $attributeId; ?>][sort_order]" type="number" value="<?= (int) $attribute['sort_order']; ?>"></td>
              </tr>
              <tr>
                <td colspan="9">
                  <div class="row g-2 align-items-end">
                    <div class="col-12 col-lg-3">
                      <label class="form-label small text-muted" for="attribute_<?= $attributeId; ?>_condition_source">Mostrar quando</label>
                      <select class="form-select form-select-sm js-product-condition-source" id="attribute_<?= $attributeId; ?>_condition_source" name="attributes[<?= $attributeId; ?>][condition_source_attribute_id]">
                        <option value="">Sempre mostrar</option>
                        <?php foreach ($attributes as $sourceAttribute): $sourceId = (int) $sourceAttribute['product_attribute_id']; if ($sourceId === $attributeId) continue; ?>
                          <option value="<?= $sourceId; ?>" <?= ((string) ($attribute['condition_source_attribute_id'] ?? '') === (string) $sourceId) ? 'selected' : ''; ?>><?= htmlspecialchars((string) $sourceAttribute['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-12 col-md-4 col-lg-2">
                      <label class="form-label small text-muted" for="attribute_<?= $attributeId; ?>_condition_operator">Operador</label>
                      <select class="form-select form-select-sm js-product-condition-operator" id="attribute_<?= $attributeId; ?>_condition_operator" name="attributes[<?= $attributeId; ?>][condition_operator]">
                        <?php foreach (['EQUALS' => 'igual a', 'NOT_EQUALS' => 'diferente de', 'FILLED' => 'preenchida', 'EMPTY' => 'vazia'] as $operator => $label): ?>
                          <option value="<?= $operator; ?>" <?= ((string) ($attribute['condition_operator'] ?? 'EQUALS') === $operator) ? 'selected' : ''; ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-12 col-md-4 col-lg-3">
                      <label class="form-label small text-muted" for="attribute_<?= $attributeId; ?>_condition_value">Valor</label>
                      <input class="form-control form-control-sm js-product-condition-value" id="attribute_<?= $attributeId; ?>_condition_value" maxlength="190" name="attributes[<?= $attributeId; ?>][condition_value]" type="text" value="<?= htmlspecialchars((string) ($attribute['condition_value'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                      <div class="form-text">Para listas, informe o rótulo da opção.</div>
                    </div>
                    <div class="col-12 col-md-4 col-lg-2">
                      <input name="attributes[<?= $attributeId; ?>][required_when_matched]" type="hidden" value="0">
                      <label class="form-check mb-1">
                        <input class="form-check-input" name="attributes[<?= $attributeId; ?>][required_when_matched]" type="checkbox" value="1" <?= (int) ($attribute['required_when_matched'] ?? 0) === 1 ? 'checked' : ''; ?>>
                        <span class="form-check-label">Obrigatória quando atender</span>
                      </label>
                    </div>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody></table>
        </div>
      </section>
    <?php endif; ?>

    <section class="store-section">
      <div class="store-subheader"><div><h6>Novas características</h6><small class="text-muted">Ex.: cor, material, voltagem, aro ou validade.</small></div><button class="btn btn-outline-primary btn-sm js-add-product-attribute" type="button"><i class="bi bi-plus-lg" aria-hidden="true"></i> Adicionar</button></div>
      <div class="vstack gap-3 js-new-product-attributes"></div>
      <p class="text-muted mb-0 js-new-product-attributes-empty">Nenhuma nova característica adicionada.</p>
    </section>

    <?php if ($isEdit): ?>
      <section class="store-section">
        <div class="store-subheader"><div><h6>Modelo reutilizável</h6><small class="text-muted">Salve esta configuração para iniciar futuras categorias com as mesmas características e regras.</small></div></div>
        <div class="row g-3 align-items-end">
          <div class="col-12 col-lg-5">
            <input name="save_as_template" type="hidden" value="0">
            <label class="form-check mb-2"><input class="form-check-input js-save-category-template" name="save_as_template" type="checkbox" value="1" <?= $value('save_as_template') === '1' ? 'checked' : ''; ?>><span class="form-check-label">Salvar ou atualizar como modelo</span></label>
            <label class="form-label" for="template_name">Nome do modelo</label>
            <input class="form-control js-category-template-field" id="template_name" maxlength="120" name="template_name" type="text" value="<?= htmlspecialchars($value('template_name'), ENT_QUOTES, 'UTF-8'); ?>">
          </div>
          <div class="col-12 col-lg-7"><label class="form-label" for="template_description">Descrição do modelo</label><input class="form-control js-category-template-field" id="template_description" maxlength="255" name="template_description" type="text" value="<?= htmlspecialchars($value('template_description'), ENT_QUOTES, 'UTF-8'); ?>"></div>
        </div>
      </section>
    <?php endif; ?>

    <div class="form-actions"><a href="<?= url('product_category'); ?>" class="btn btn-secondary"><i class="bi bi-arrow-left" aria-hidden="true"></i> Cancelar</a><button class="btn btn-primary" type="submit"><i class="bi bi-check-lg" aria-hidden="true"></i> Salvar</button></div>
  </form>
</div>

<template id="productAttributeTemplate">
  <div class="border rounded-3 p-3 js-new-product-attribute">
    <div class="d-flex justify-content-between align-items-center mb-3"><strong>Nova característica</strong><button class="btn btn-outline-danger btn-sm js-remove-product-attribute" type="button" aria-label="Remover característica"><i class="bi bi-trash" aria-hidden="true"></i></button></div>
    <div class="row g-3">
      <div class="col-12 col-lg-4"><label class="form-label">Nome</label><input class="form-control" data-field="name" maxlength="120" type="text"></div>
      <div class="col-12 col-md-6 col-lg-3"><label class="form-label">Tipo</label><select class="form-select js-product-attribute-type" data-field="data_type"><?php foreach ($typeLabels as $type => $label): ?><option value="<?= $type; ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?></select></div>
      <div class="col-12 col-md-6 col-lg-3"><label class="form-label">Medição</label><select class="form-select js-product-attribute-measurement" data-field="measurement_type"><option value="">Sem unidade</option><option value="LENGTH">Comprimento</option><option value="WEIGHT">Peso</option></select></div>
      <div class="col-12 col-lg-2 d-flex align-items-end"><div class="d-flex flex-column gap-1 pb-2"><label class="form-check"><input class="form-check-input" data-field="required" type="checkbox" value="1"><span class="form-check-label">Obrigatória</span></label><label class="form-check"><input class="form-check-input" data-field="filterable" type="checkbox" value="1"><span class="form-check-label">Usar como filtro</span></label></div></div>
      <div class="col-12 d-flex flex-wrap gap-4"><label class="form-check"><input class="form-check-input js-new-variation-axis" data-field="variation_axis" type="checkbox" value="1"><span class="form-check-label">Gerar combinações de variações</span></label><label class="form-check"><input class="form-check-input js-new-variation-specific" data-field="variation_specific" type="checkbox" value="1"><span class="form-check-label">Valor próprio em cada variação</span></label></div>
      <div class="col-12 d-none js-product-attribute-options"><label class="form-label">Opções</label><textarea class="form-control" data-field="options" rows="2" placeholder="Uma opção por linha: Vermelho, Azul, Verde"></textarea></div>
    </div>
  </div>
</template>
<script src="/assets/richtexteditor/rich_text_editor.js?v=2"></script>
<script src="<?= url('assets/js/product_categories/form.js?v=1'); ?>"></script>
