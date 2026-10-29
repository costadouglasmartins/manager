<?php
// Keeps only the menu entries the current user may open; guests get no module menus.
$mainMenuItems = [];
$systemMenuItems = [];
if ($isAuthenticated)
  {
  $menuDefinition = require __DIR__ . '/main_menu_definition.php';
  $mainMenuService = new \App\Services\MainMenuService();
  $mainMenuItems = $mainMenuService->visibleItems($menuDefinition['main']);
  $systemMenuItems = $mainMenuService->visibleItems($menuDefinition['system']);
  }

// Each call renders one menu level in its own scope, so nested levels keep their loop variables.
$renderMenuItems = static function (array $menuItems) use (&$renderMenuItems): void
  {
  require __DIR__ . '/main_menu_items.php';
  };
?>
<nav class="navbar navbar-expand-lg fixed-top app-navbar">
  <div class="container-fluid app-navbar-container">
    <a class="navbar-brand app-brand d-flex align-items-center" href="<?= url('/'); ?>">
      <!--<i class="bi bi-boxes me-2"></i>-->
      <span class="app-brand-mark"><img src="/manager_assets/bootstrap/images/favicon-32x32.png" alt=""></span>
      <!--<span class="app-brand-text">Manager</span>-->
    </a>

    <!-- Botão toggle mobile -->
    <button class="navbar-toggler app-navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
      aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <!-- Itens -->
    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav app-navbar-nav me-auto mb-2 mb-lg-0">
        <?php foreach ($mainMenuItems as $menuItem): ?>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" id="<?= htmlspecialchars((string) $menuItem['id'], ENT_QUOTES, 'UTF-8'); ?>" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="bi <?= htmlspecialchars((string) ($menuItem['icon'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> me-1"></i> <?= htmlspecialchars((string) ($menuItem['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
            </a>
            <ul class="dropdown-menu" aria-labelledby="<?= htmlspecialchars((string) $menuItem['id'], ENT_QUOTES, 'UTF-8'); ?>">
              <?php $renderMenuItems($menuItem['children']); ?>
            </ul>
          </li>
        <?php endforeach; ?>
      </ul>
      <!-- Sistema -->
      <ul class="navbar-nav app-navbar-nav mb-2 mb-lg-0">
      <?php if ($systemMenuItems !== []): ?>
        <li class="nav-item dropdown me-lg-3">
          <a class="nav-link dropdown-toggle app-nav-link d-flex align-items-center" href="#" id="systemMenu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="bi bi-gear-fill"></i>&nbsp;
          </a>
          <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="systemMenu">
            <?php $renderMenuItems($systemMenuItems); ?>
          </ul>
        </li>
      <?php endif; ?>
      </ul>
      <!-- Usuário -->
      <ul class="navbar-nav app-navbar-nav mb-2 mb-lg-0">
        <li class="nav-item dropdown me-lg-3">
          <a class="nav-link dropdown-toggle app-nav-link d-flex align-items-center" href="#" id="accountMenu" role="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
            <i class="bi bi-person-circle me-2"></i>
          </a>
          <ul class="dropdown-menu dropdown-menu-end app-account-menu" aria-labelledby="accountMenu">
            <!--
            <li><a class="dropdown-item" href="#"><i class="bi bi-gear me-2"></i>Perfil</a></li>
            <li><hr class="dropdown-divider" /></li>
            <li><a class="dropdown-item text-danger" href="<?= ('permission'); ?>"><i class="bi bi-box-arrow-right me-2"></i>Permissões</a></li>
            -->
            <?php if ($isAuthenticated): ?>
              <?php
              $currentPresenceStatus = (string) ($userPresence['status'] ?? 'offline');
              $presenceLabels = [
                'online' => 'Online',
                'away' => 'Ausente',
                'busy' => 'Ocupado',
                'offline' => 'Offline',
              ];
              $currentPresenceLabel = $presenceLabels[$currentPresenceStatus] ?? $presenceLabels['offline'];
              ?>
              <li class="app-account-summary">
                <span class="app-account-avatar" aria-hidden="true"><i class="bi bi-person-fill"></i></span>
                <span class="app-account-identity">
                  <span class="app-account-eyebrow">Minha conta</span>
                  <strong title="<?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8'); ?></strong>
                </span>
              </li>
              <li><hr class="dropdown-divider app-account-divider" /></li>
              <li class="app-presence-panel" data-user-presence data-status-url="<?= url('user_presence/status'); ?>" aria-busy="false">
                <div class="app-presence-header">
                  <div>
                    <span class="app-presence-title"><i class="bi bi-broadcast-pin" aria-hidden="true"></i> Presença</span>
                    <span class="app-presence-description">Como sua equipe vê você</span>
                  </div>
                  <span class="badge app-presence-current <?= htmlspecialchars((string) ($userPresence['class'] ?? 'text-bg-secondary'), ENT_QUOTES, 'UTF-8'); ?>" data-user-presence-badge>
                    <i class="bi <?= htmlspecialchars((string) ($userPresence['icon'] ?? 'bi-circle'), ENT_QUOTES, 'UTF-8'); ?> app-presence-current-icon" data-user-presence-icon aria-hidden="true"></i>
                    <span data-user-presence-label><?= htmlspecialchars($currentPresenceLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                  </span>
                </div>
                <div class="app-presence-options" role="group" aria-label="Alterar status de presença">
                  <button class="app-presence-option app-presence-option-online<?= $currentPresenceStatus === 'online' ? ' is-active' : ''; ?>" type="button" aria-pressed="<?= $currentPresenceStatus === 'online' ? 'true' : 'false'; ?>" data-user-presence-status="online" data-user-presence-name="Online">
                    <span class="app-presence-option-icon"><i class="bi bi-circle-fill" aria-hidden="true"></i></span>
                    <span class="app-presence-option-copy"><strong>Online</strong><small>Disponível</small></span>
                    <i class="bi bi-check-lg app-presence-option-check" aria-hidden="true"></i>
                  </button>
                  <button class="app-presence-option app-presence-option-away<?= $currentPresenceStatus === 'away' ? ' is-active' : ''; ?>" type="button" aria-pressed="<?= $currentPresenceStatus === 'away' ? 'true' : 'false'; ?>" data-user-presence-status="away" data-user-presence-name="Ausente">
                    <span class="app-presence-option-icon"><i class="bi bi-clock-fill" aria-hidden="true"></i></span>
                    <span class="app-presence-option-copy"><strong>Ausente</strong><small>Volto em breve</small></span>
                    <i class="bi bi-check-lg app-presence-option-check" aria-hidden="true"></i>
                  </button>
                  <button class="app-presence-option app-presence-option-busy<?= $currentPresenceStatus === 'busy' ? ' is-active' : ''; ?>" type="button" aria-pressed="<?= $currentPresenceStatus === 'busy' ? 'true' : 'false'; ?>" data-user-presence-status="busy" data-user-presence-name="Ocupado">
                    <span class="app-presence-option-icon"><i class="bi bi-dash-circle-fill" aria-hidden="true"></i></span>
                    <span class="app-presence-option-copy"><strong>Ocupado</strong><small>Evitar interrupções</small></span>
                    <i class="bi bi-check-lg app-presence-option-check" aria-hidden="true"></i>
                  </button>
                  <button class="app-presence-option app-presence-option-offline<?= $currentPresenceStatus === 'offline' ? ' is-active' : ''; ?>" type="button" aria-pressed="<?= $currentPresenceStatus === 'offline' ? 'true' : 'false'; ?>" data-user-presence-status="offline" data-user-presence-name="Offline">
                    <span class="app-presence-option-icon"><i class="bi bi-circle" aria-hidden="true"></i></span>
                    <span class="app-presence-option-copy"><strong>Offline</strong><small>Desconectado</small></span>
                    <i class="bi bi-check-lg app-presence-option-check" aria-hidden="true"></i>
                  </button>
                </div>
                <span class="app-presence-feedback" data-user-presence-feedback role="status" aria-live="polite"></span>
              </li>
              <li><hr class="dropdown-divider app-account-divider" /></li>
              <li><a class="dropdown-item app-account-action" href="<?= url('user/edit/' . (int) ($managerUser['id'] ?? 0)); ?>"><i class="bi bi-person-lines-fill"></i><span>Meus dados</span></a></li>
              <li><a class="dropdown-item app-account-action app-account-action-danger" href="<?= url('user/logout'); ?>" onclick="return confirm('Tem certeza que deseja sair?');"><i class="bi bi-box-arrow-right"></i><span>Encerrar sessão</span></a></li>
            <?php else: ?>
              <li><a class="dropdown-item" href="<?= url('user/login'); ?>">Entrar</a></li>
            <?php endif; ?>
          </ul>
        </li>

        <!-- Botão de tema -->
        <li class="nav-item">
          <button id="themeToggle" class="btn-theme" type="button" title="Alternar tema" aria-label="Alternar tema claro ou escuro">
            <i class="bi bi-moon-fill"></i>
          </button>
        </li>
      </ul>
    </div>
  </div>
</nav>
