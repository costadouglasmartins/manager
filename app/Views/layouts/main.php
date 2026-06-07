<?php
$pageTitle = trim((string) ($title ?? 'Início'));
$managerUser = $_SESSION['manager']['user'] ?? null;
$isAuthenticated = !empty($managerUser);
$userName = $isAuthenticated ? (string) ($managerUser['name'] ?? 'Usuário(a)') : 'Usuário(a)';
$hasTenantContext = class_exists('App\\Core\\TenantContext') && \App\Core\TenantContext::hasTenant();
?>
<!DOCTYPE html>
<html lang="pt-BR" id="htmlRoot" data-bs-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script>
    (function() {
      const savedTheme = localStorage.getItem("theme") || "light";
      document.documentElement.setAttribute("data-bs-theme", savedTheme);
    })();
  </script>
  <link rel="stylesheet" href="<?= url('assets/css/bootstrap.min.css'); ?>">
  <link rel="stylesheet" href="<?= url('assets/images/bootstrap-icons.min.css'); ?>">
  <link rel="stylesheet" href="<?= url('assets/css/main.css?v=5'); ?>">
  <link rel="stylesheet" href="<?= url('assets/css/menu.css?v=3'); ?>">
  <link rel="apple-touch-icon" sizes="180x180" href="<?= url('assets/images/apple-touch-icon.png'); ?>">
  <link rel="icon" type="image/png" sizes="32x32" href="<?= url('assets/images/favicon-32x32.png'); ?>">
  <link rel="icon" type="image/png" sizes="16x16" href="<?= url('assets/images/favicon-16x16.png'); ?>">
  <link rel="manifest" href="<?= url('/site.webmanifest'); ?>">
  <title>Manager - <?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
</head>
<body class="app-shell">
  
  <!-- Menu -->
  <?php require_once __DIR__ . '/../menus/main_menu.php'; ?>
  <!-- /Menu -->

  <main class="app-main">
    <div class="container-fluid app-container">
      <div class="app-content">
        <?= $content ?>
      </div>
    </div>
  </main>
  
  <script src="<?= url('assets/js/bootstrap.bundle.min.js'); ?>"></script>
  <script src="<?= url('assets/js/menu.js?v=3'); ?>"></script>
  <script src="<?= url('assets/js/main.js?v=4'); ?>"></script>
  <script src="<?= url('assets/js/helper.js?v=1'); ?>"></script>
  <?php if ($isAuthenticated && $hasTenantContext): ?>
    <?php require __DIR__ . '/../support_chats/widget.php'; ?>
  <?php endif; ?>

</body>
</html>
