<?php
// Variables expected from the edit action: $catalogMeta (resource, icon, edit_title), $catalog (record), $formData (values after an error).
$catalogMeta = (isset($catalogMeta) && is_array($catalogMeta)) ? $catalogMeta : [];
$catalog = (isset($catalog) && is_array($catalog)) ? $catalog : [];
// Submitted values win over the stored record when the form is shown again after an error.
$formValues = array_merge($catalog, (isset($formData) && is_array($formData)) ? $formData : []);
$formAction = url((string) ($catalogMeta['resource'] ?? '') . '/update/' . (int) ($catalog['id'] ?? 0));
$catalogHeader = (string) ($catalogMeta['edit_title'] ?? 'Editar cadastro de RH');
require __DIR__ . '/_form.php';
