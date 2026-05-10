<?php
$pageTitle = trim((string) ($title ?? 'Início'));
$managerUser = $_SESSION['manager']['user'] ?? null;
$isAuthenticated = !empty($managerUser);
$userName = $isAuthenticated ? (string) ($managerUser['name'] ?? 'Usuário(a)') : 'Usuário(a)';
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
  <link rel="stylesheet" href="<?= url('assets/css/main.css?v=3'); ?>">
  <link rel="stylesheet" href="<?= url('assets/css/menu.css?v=2'); ?>">
  <link rel="apple-touch-icon" sizes="180x180" href="<?= url('assets/images/apple-touch-icon.png'); ?>">
  <link rel="icon" type="image/png" sizes="32x32" href="<?= url('assets/images/favicon-32x32.png'); ?>">
  <link rel="icon" type="image/png" sizes="16x16" href="<?= url('assets/images/favicon-16x16.png'); ?>">
  <link rel="manifest" href="<?= url('/site.webmanifest'); ?>">
  <title>Manager - <?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
</head>
<body class="app-shell">
  
  <nav class="navbar navbar-expand-lg fixed-top app-navbar">
    <div class="container-fluid app-navbar-container">
      <a class="navbar-brand app-brand d-flex align-items-center" href="<?= url('/'); ?>">
        <!--<i class="bi bi-boxes me-2"></i>-->
        <span class="app-brand-mark"><img src="<?= url('/assets/images/favicon-32x32.png'); ?>" alt=""></span>
        <span class="app-brand-text">Manager</span>
      </a>

      <!-- Botão toggle mobile -->
      <button class="navbar-toggler app-navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
        aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>

      <!-- Itens -->
      <div class="collapse navbar-collapse" id="mainNav">
        <ul class="navbar-nav app-navbar-nav me-auto mb-2 mb-lg-0">
          <?php if ($isAuthenticated): ?>
          <!-- Menu Cadastros (2 níveis) -->
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle app-nav-link" href="#" id="menuCadastros" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="bi bi-folder-fill me-1"></i> Cadastros
            </a>
            <ul class="dropdown-menu" aria-labelledby="menuCadastros">
              <li class="dropdown-submenu">
                <a class="dropdown-item submenu-toggle" href="#">Bairros</a>
                <ul class="dropdown-menu">
                  <?php if (authCan('neighborhood.index')): ?>
                    <li><a class="dropdown-item" href="<?= url('neighborhood'); ?>"><i class="bi bi-list-ul me-2"></i> Listar</a></li>
                    <li><hr class="dropdown-divider" /></li>
                  <?php endif; ?>
                  <?php if (authCan('neighborhood.create')): ?>
                    <li><a class="dropdown-item" href="<?= url('neighborhood/create'); ?>"><i class="bi bi-plus-circle me-2"></i> Cadastrar</a></li>
                  <?php endif; ?>
                </ul>
              </li>
              <li><hr class="dropdown-divider" /></li>
              <li class="dropdown-submenu">
                <a class="dropdown-item submenu-toggle" href="#">Cidades</a>
                <ul class="dropdown-menu">
                  <?php if (authCan('city.index')): ?>
                    <li><a class="dropdown-item" href="<?= url('city'); ?>"><i class="bi bi-list-ul me-2"></i> Listar</a></li>
                    <li><hr class="dropdown-divider" /></li>
                  <?php endif; ?>
                  <?php if (authCan('city.create')): ?>
                    <li><a class="dropdown-item" href="<?= url('city/create'); ?>"><i class="bi bi-plus-circle me-2"></i> Cadastrar</a></li>
                  <?php endif; ?>
                </ul>
              </li>
              <li><hr class="dropdown-divider" /></li>
              <li class="dropdown-submenu">
                <a class="dropdown-item submenu-toggle" href="#">E-mails</a>
                <ul class="dropdown-menu">
                  <?php if (authCan('email.index')): ?>
                    <li><a class="dropdown-item" href="<?= url('email'); ?>"><i class="bi bi-list-ul me-2"></i> Listar</a></li>
                    <li><hr class="dropdown-divider" /></li>
                  <?php endif; ?>
                  <?php if (authCan('email.create')): ?>
                    <li><a class="dropdown-item" href="<?= url('email/create'); ?>"><i class="bi bi-plus-circle me-2"></i> Cadastrar</a></li>
                  <?php endif; ?>
                </ul>
              </li>
              <li><hr class="dropdown-divider" /></li>
              <li class="dropdown-submenu">
                <a class="dropdown-item submenu-toggle" href="#">Endereços</a>
                <ul class="dropdown-menu">
                  <?php if (authCan('address.index')): ?>
                    <li><a class="dropdown-item" href="<?= url('address'); ?>"><i class="bi bi-list-ul me-2"></i> Listar</a></li>
                    <li><hr class="dropdown-divider" /></li>
                  <?php endif; ?>
                  <?php if (authCan('address.create')): ?>
                    <li><a class="dropdown-item" href="<?= url('address/create'); ?>"><i class="bi bi-plus-circle me-2"></i> Cadastrar</a></li>
                  <?php endif; ?>
                </ul>
              </li>
              <li><hr class="dropdown-divider" /></li>
              <li class="dropdown-submenu">
                <a class="dropdown-item submenu-toggle" href="#">Estados</a>
                <ul class="dropdown-menu">
                  <?php if (authCan('state.index')): ?>
                    <li><a class="dropdown-item" href="<?= url('state'); ?>"><i class="bi bi-list-ul me-2"></i> Listar</a></li>
                    <li><hr class="dropdown-divider" /></li>
                  <?php endif; ?>
                  <?php if (authCan('state.create')): ?>
                    <li><a class="dropdown-item" href="<?= url('state/create'); ?>"><i class="bi bi-plus-circle me-2"></i> Cadastrar</a></li>
                  <?php endif; ?>
                </ul>
              </li>
              <li><hr class="dropdown-divider" /></li>
              <li class="dropdown-submenu">
                <a class="dropdown-item submenu-toggle" href="#">Países</a>
                <ul class="dropdown-menu">
                  <?php if (authCan('country.index')): ?>
                    <li><a class="dropdown-item" href="<?= url('country'); ?>"><i class="bi bi-list-ul me-2"></i> Listar</a></li>
                    <li><hr class="dropdown-divider" /></li>
                  <?php endif; ?>
                  <?php if (authCan('country.create')): ?>
                    <li><a class="dropdown-item" href="<?= url('country/create'); ?>"><i class="bi bi-plus-circle me-2"></i> Cadastrar</a></li>
                  <?php endif; ?>
                </ul>
              </li>
              <li><hr class="dropdown-divider" /></li>
              <li class="dropdown-submenu">
                <a class="dropdown-item submenu-toggle" href="#">Pessoas</a>
                <ul class="dropdown-menu">
                  <?php if (authCan('person.index')): ?>
                    <li><a class="dropdown-item" href="<?= url('person'); ?>"><i class="bi bi-list-ul me-2"></i> Listar</a></li>
                    <li><hr class="dropdown-divider" /></li>
                  <?php endif; ?>
                  <?php if (authCan('person.create')): ?>
                    <li><a class="dropdown-item" href="<?= url('person/create'); ?>"><i class="bi bi-plus-circle me-2"></i> Cadastrar</a></li>
                  <?php endif; ?>
                </ul>
              </li>
              <li><hr class="dropdown-divider" /></li>
              <li class="dropdown-submenu">
                <a class="dropdown-item submenu-toggle" href="#">Telefones</a>
                <ul class="dropdown-menu">
                  <?php if (authCan('phone_number.index')): ?>
                    <li><a class="dropdown-item" href="<?= url('phone_number'); ?>"><i class="bi bi-list-ul me-2"></i> Listar</a></li>
                    <li><hr class="dropdown-divider" /></li>
                  <?php endif; ?>
                  <?php if (authCan('phone_number.create')): ?>
                    <li><a class="dropdown-item" href="<?= url('phone_number/create'); ?>"><i class="bi bi-plus-circle me-2"></i> Cadastrar</a></li>
                  <?php endif; ?>
                </ul>
              </li>
              <li><hr class="dropdown-divider" /></li>
              <li class="dropdown-submenu">
                <a class="dropdown-item submenu-toggle" href="#">Usuários</a>
                <ul class="dropdown-menu">
                  <?php if (authCan('user.index')): ?>
                    <li><a class="dropdown-item" href="<?= url('user'); ?>"><i class="bi bi-person-lines-fill me-2"></i> Listar</a></li>
                  <?php endif; ?>
                </ul>
              </li>
            </ul>
          </li>
          <!-- Menu Exemplo (4 níveis) 
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" id="menuExemplo" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="bi bi-layers me-1"></i> Cadastros essenciais
            </a>
            <ul class="dropdown-menu" aria-labelledby="menuExemplo">

              <li><a class="dropdown-item" href="#"><i class="bi bi-house me-2"></i>Início</a></li>

              <li><hr class="dropdown-divider" /></li>

              <li class="dropdown-submenu">
                <a class="dropdown-item submenu-toggle" href="#"><i class="bi bi-folder me-2"></i>Grupo A</a>
                <ul class="dropdown-menu">

                  <li><a class="dropdown-item" href="#"><i class="bi bi-file-earmark me-2"></i>A — Item direto</a></li>

                  <li><hr class="dropdown-divider" /></li>

                  <li class="dropdown-submenu">
                    <a class="dropdown-item submenu-toggle" href="#"><i class="bi bi-folder2 me-2"></i>Sub-grupo A1</a>
                    <ul class="dropdown-menu">

                      <li><a class="dropdown-item" href="#"><i class="bi bi-file-earmark-text me-2"></i>A1 — Item 1</a></li>
                      <li><hr class="dropdown-divider" /></li>
                      <li><a class="dropdown-item" href="#"><i class="bi bi-file-earmark-text me-2"></i>A1 — Item 2</a></li>
                      <li><hr class="dropdown-divider" /></li>
                      <li><a class="dropdown-item" href="#"><i class="bi bi-file-earmark-text me-2"></i>A1 — Item 3</a></li>

                    </ul>
                  </li>

                  <li><hr class="dropdown-divider" /></li>

                  <li class="dropdown-submenu">
                    <a class="dropdown-item submenu-toggle" href="#"><i class="bi bi-folder2 me-2"></i>Sub-grupo A2</a>
                    <ul class="dropdown-menu">

                      <li><a class="dropdown-item" href="#"><i class="bi bi-file-earmark-text me-2"></i>A2 — Item 1</a></li>
                      <li><hr class="dropdown-divider" /></li>
                      <li><a class="dropdown-item" href="#"><i class="bi bi-file-earmark-text me-2"></i>A2 — Item 2</a></li>

                    </ul>
                  </li>

                </ul>
              </li>

              <li><hr class="dropdown-divider" /></li>

              <li class="dropdown-submenu">
                <a class="dropdown-item submenu-toggle" href="#"><i class="bi bi-folder me-2"></i>Grupo B</a>
                <ul class="dropdown-menu">
                  <li><a class="dropdown-item" href="#"><i class="bi bi-file-earmark me-2"></i>B — Item 1</a></li>
                  <li><hr class="dropdown-divider" /></li>
                  <li><a class="dropdown-item" href="#"><i class="bi bi-file-earmark me-2"></i>B — Item 2</a></li>
                </ul>
              </li>

              <li><hr class="dropdown-divider" /></li>

              <li><a class="dropdown-item" href="#"><i class="bi bi-info-circle me-2"></i>Sobre</a></li>

            </ul>
          </li>
          /Menu Exemplo -->
          <?php endif; ?>
        </ul>
        <!-- Sistema -->
        <ul class="navbar-nav app-navbar-nav mb-2 mb-lg-0">
        <?php if ($isAuthenticated): ?>
          <li class="nav-item dropdown me-lg-3">
            <a class="nav-link dropdown-toggle app-nav-link d-flex align-items-center" href="#" id="systemMenu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="bi bi-gear-fill"></i>&nbsp;Sistema
            </a>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="systemMenu">
              <?php if (authCan('home.index')): ?>
                <li><a class="dropdown-item" href="<?= url('permission'); ?>"><i class="bi bi-gear me-2"></i>Permissões</a></li>
              <?php endif; ?>
            </ul>
          </li>
        <?php endif; ?>
        </ul>
        <!-- Usuário -->
        <ul class="navbar-nav app-navbar-nav mb-2 mb-lg-0">
          <li class="nav-item dropdown me-lg-3">
            <a class="nav-link dropdown-toggle app-nav-link d-flex align-items-center" href="#" id="accountMenu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="bi bi-person-circle me-2"></i> <?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8'); ?>
            </a>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="accountMenu">
              <!--
              <li><a class="dropdown-item" href="#"><i class="bi bi-gear me-2"></i>Perfil</a></li>
              <li><hr class="dropdown-divider" /></li>
              <li><a class="dropdown-item text-danger" href="<?= ('permission'); ?>"><i class="bi bi-box-arrow-right me-2"></i>Permissões</a></li>
              -->
              <?php if ($isAuthenticated): ?>
                <li><a class="dropdown-item" href="<?= url('user/edit/' . (int) ($managerUser['id'] ?? 0)); ?>"><i class="bi bi-person-lines-fill me-2"></i>Meus dados</a></li>
                <li><hr class="dropdown-divider" /></li>
                <li><a class="dropdown-item" href="<?= url('user/logout'); ?>" onclick="return confirm('Tem certeza que deseja sair?');"><i class="bi bi-box-arrow-right me-2"></i>Olá, <?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8'); ?>, encerrar sessão?</a></li>
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

  <main class="app-main">
    <div class="container-fluid app-container">
      <div class="app-content">
        <?= $content ?>
      </div>
    </div>
  </main>
  
  <script src="<?= url('assets/js/bootstrap.bundle.min.js'); ?>"></script>
  <script src="<?= url('assets/js/menu.js?v=2'); ?>"></script>
  <script src="<?= url('assets/js/main.js?v=2'); ?>"></script>
</body>
</html>
