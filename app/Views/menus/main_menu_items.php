<?php
/**
 * Renders entries already filtered by App\Services\MainMenuService inside a dropdown list.
 * Expects $menuItems and the $renderMenuItems closure from main_menu.php, which gives each nested level its own scope.
 **/
?>
<?php foreach ($menuItems as $menuItem): ?>
  <?php if (!empty($menuItem['divider'])): ?>
    <li><hr class="dropdown-divider" /></li>
  <?php elseif (isset($menuItem['children'])): ?>
    <li class="dropdown-submenu">
      <a class="dropdown-item submenu-toggle" href="#"><i class="bi <?= htmlspecialchars((string) ($menuItem['icon'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> me-2"></i><?= htmlspecialchars((string) ($menuItem['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></a>
      <ul class="dropdown-menu">
        <?php $renderMenuItems($menuItem['children']); ?>
      </ul>
    </li>
  <?php else: ?>
    <li><a class="dropdown-item" href="<?= htmlspecialchars(url((string) ($menuItem['url'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>"><i class="bi <?= htmlspecialchars((string) ($menuItem['icon'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> me-2"></i><?= htmlspecialchars((string) ($menuItem['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></a></li>
  <?php endif; ?>
<?php endforeach; ?>
