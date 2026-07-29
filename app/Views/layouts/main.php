<?php
$pageTitle = trim((string) ($title ?? 'Início'));
$managerUser = $_SESSION['manager']['user'] ?? null;
$isAuthenticated = !empty($managerUser);
$userName = $isAuthenticated ? (string) ($managerUser['name'] ?? 'Usuário(a)') : 'Usuário(a)';
$hasTenantContext = class_exists('App\\Core\\TenantContext') && \App\Core\TenantContext::hasTenant();
$userPresence = null;
if ($isAuthenticated && class_exists('App\\Services\\UserPresenceService'))
  {
  $userPresence = (new \App\Services\UserPresenceService())->current((int) ($managerUser['id'] ?? 0));
  }
$userPresence = is_array($userPresence) ? $userPresence : \App\Services\UserPresenceService::statusMeta('offline') + ['status' => 'offline'];
?>
<!DOCTYPE html>
<html lang="pt-BR" id="htmlRoot" data-bs-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script src="/assets/helpers/helper.js?v=3"></script>
  <script>
    AppTheme.initialize({ storageKey: "theme-manager" });
  </script>
  <link rel="stylesheet" href="/assets/bootstrap/css/bootstrap.min.css">
  <link rel="stylesheet" href="/assets/bootstrap/images/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= url('assets/css/main.css?v=13'); ?>">
  <link rel="stylesheet" href="<?= url('assets/css/menu.css?v=4'); ?>">
  <link rel="apple-touch-icon" sizes="180x180" href="/assets/bootstrap/images/apple-touch-icon.png">
  <link rel="icon" type="image/png" sizes="32x32" href="/assets/bootstrap/images/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="/assets/bootstrap/images/favicon-16x16.png">
  <link rel="manifest" href="<?= url('/site.webmanifest'); ?>">
  <title>Manager - <?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
</head>
<body class="app-shell">
  
  <!-- Menu -->
  <?php require_once __DIR__ . '/../menus/main_menu.php'; ?>
  <!-- /Menu -->

  <main class="app-main">
    <div class="container-fluid app-container mt-3">
      <div class="app-content">
        <?= renderFlashMessages(); ?>
        <?= $content ?>
      </div>
    </div>
  </main>
  
  <script src="/assets/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="<?= url('assets/js/menu.js?v=4'); ?>"></script>
  <script src="<?= url('assets/js/main.js?v=5'); ?>"></script>
  <?php if ($isAuthenticated): ?>
    <script src="<?= url('assets/js/user_presences/user_presence.js?v=2'); ?>"></script>
  <?php endif; ?>
  <?php if ($isAuthenticated && $hasTenantContext): ?>
    <?php require __DIR__ . '/../support_chats/widget.php'; ?>
  <?php endif; ?>

</body>
</html>
