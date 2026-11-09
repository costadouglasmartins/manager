<?php
// Variables expected from the create action: $catalogMeta (resource, icon, create_title), $formData (name, description, active) after an error.
$catalogMeta = (isset($catalogMeta) && is_array($catalogMeta)) ? $catalogMeta : [];
$formValues = (isset($formData) && is_array($formData)) ? $formData : [];
$formAction = url((string) ($catalogMeta['resource'] ?? '') . '/store');
$catalogHeader = (string) ($catalogMeta['create_title'] ?? 'Novo cadastro de RH');
require __DIR__ . '/_form.php';
