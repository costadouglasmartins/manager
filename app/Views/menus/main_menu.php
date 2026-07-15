<nav class="navbar navbar-expand-lg fixed-top app-navbar">
  <div class="container-fluid app-navbar-container">
    <a class="navbar-brand app-brand d-flex align-items-center" href="<?= url('/'); ?>">
      <!--<i class="bi bi-boxes me-2"></i>-->
      <span class="app-brand-mark"><img src="/assets/bootstrap/images/favicon-32x32.png" alt=""></span>
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
        <?php if ($isAuthenticated): ?>
        <!-- Menu cadastros essenciais -->
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" id="menuExemplo" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="bi bi-layers me-1"></i> Cadastros essenciais
          </a>
          <ul class="dropdown-menu" aria-labelledby="menuExemplo">

            <li class="dropdown-submenu">
              <a class="dropdown-item submenu-toggle" href="#"><i class="bi bi-people-fill me-2"></i>Pessoas</a>
              <ul class="dropdown-menu">
                <?php if (authCan('person.create')): ?>
                  <li><a class="dropdown-item" href="<?= url('person/create'); ?>"><i class="bi bi-person-fill-add me-2"></i>Cadastrar</a></li>
                <?php endif; ?>
                <li><hr class="dropdown-divider" /></li>
                <?php if (authCan('person.index')): ?>
                  <li><a class="dropdown-item" href="<?= url('person'); ?>"><i class="bi bi-person-lines-fill me-2"></i>Listar</a></li>
                <?php endif; ?>  
                <li><hr class="dropdown-divider" /></li>

                <li class="dropdown-submenu">
                  <a class="dropdown-item submenu-toggle" href="#"><i class="bi bi-asterisk me-2"></i>Associações</a>
                  <ul class="dropdown-menu">
                    <?php if (authCan('email.index')): ?>
                    <li><a class="dropdown-item" href="<?= url('email'); ?>"><i class="bi bi-envelope-paper-fill me-2"></i>E-mails</a></li>
                    <?php endif; ?>
                    <li><hr class="dropdown-divider" /></li>
                    <?php if (authCan('address.index')): ?>
                      <li><a class="dropdown-item" href="<?= url('address'); ?>"><i class="bi bi-signpost-split-fill me-2"></i>Endereços</a></li>
                    <?php endif; ?>
                    <li><hr class="dropdown-divider" /></li>
                    <?php if (authCan('phone_number.index')): ?>
                      <li><a class="dropdown-item" href="<?= url('phone_number'); ?>"><i class="bi bi-telephone-fill me-2"></i>Telefones</a></li>
                    <?php endif; ?>  
                  </ul>
                </li>

                <li><hr class="dropdown-divider" /></li>

                <li class="dropdown-submenu">
                  <a class="dropdown-item submenu-toggle" href="#"><i class="bi bi-stack me-2"></i>Relatórios</a>
                  <ul class="dropdown-menu">

                    <li><a class="dropdown-item" href="#"><i class="bi bi-file-earmark-text me-2"></i>A2 — Item 1</a></li>
                    <li><hr class="dropdown-divider" /></li>
                    <li><a class="dropdown-item" href="#"><i class="bi bi-file-earmark-text me-2"></i>A2 — Item 2</a></li>

                  </ul>
                </li>

              </ul>
            </li>

          </ul>
        </li>
        <!-- /Menu cadastros essenciais -->
        <!-- Menu financeiro -->
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" id="financeMenu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="bi bi-bank me-1"></i> Financeiro
          </a>
          <ul class="dropdown-menu" aria-labelledby="financeMenu">
            <?php if (authCan('bank.index')): ?>
              <li><a class="dropdown-item" href="<?= url('bank'); ?>"><i class="bi bi-bank me-2"></i>Bancos</a></li>
              <li><hr class="dropdown-divider" /></li>
            <?php endif; ?>
            <?php if (authCan('bank_account.index')): ?>
              <li><a class="dropdown-item" href="<?= url('bank_account'); ?>"><i class="bi bi-wallet2 me-2"></i>Contas Bancárias</a></li>
            <?php endif; ?>
            
            <li><hr class="dropdown-divider" /></li>
            <?php if (authCan('financial_operation.spreadsheet') || authCan('financial_operation_category.index') || authCan('financial_operation_subcategory.index') || authCan('financial_operation_payment.index')): ?>
              <li class="dropdown-submenu">
                <a class="dropdown-item submenu-toggle" href="#"><i class="bi bi-receipt me-2"></i>Operações financeiras</a>
                <ul class="dropdown-menu">
                  <?php if (authCan('financial_operation.spreadsheet')): ?>
                    <li><a class="dropdown-item" href="<?= url('financial_operation'); ?>"><i class="bi bi-receipt me-2"></i>Operações</a></li>
                    <li><hr class="dropdown-divider" /></li>
                    <li><a class="dropdown-item" href="<?= url('financial_operation/spreadsheet'); ?>"><i class="bi bi-table me-2"></i>Planilha anual</a></li>
                    <?php if (authCan('financial_operation_category.index') || authCan('financial_operation_subcategory.index') || authCan('financial_operation_payment.index')): ?>
                      <li><hr class="dropdown-divider" /></li>
                    <?php endif; ?>
                  <?php endif; ?>
                  <?php if (authCan('financial_operation_category.index')): ?>
                    <li><a class="dropdown-item" href="<?= url('financial_operation_category'); ?>"><i class="bi bi-tags me-2"></i>Categorias</a></li>
                    <?php if (authCan('financial_operation_subcategory.index') || authCan('financial_operation_payment.index')): ?>
                      <li><hr class="dropdown-divider" /></li>
                    <?php endif; ?>
                  <?php endif; ?>
                  <?php if (authCan('financial_operation_subcategory.index')): ?>
                    <li><a class="dropdown-item" href="<?= url('financial_operation_subcategory'); ?>"><i class="bi bi-diagram-3 me-2"></i>Subcategorias</a></li>
                    <?php if (authCan('financial_operation_payment.index')): ?>
                      <li><hr class="dropdown-divider" /></li>
                    <?php endif; ?>
                  <?php endif; ?>
                  <?php if (authCan('financial_operation_payment.index')): ?>
                    <li><a class="dropdown-item" href="<?= url('financial_operation_payment'); ?>"><i class="bi bi-cash-stack me-2"></i>Pagamentos</a></li>
                  <?php endif; ?>
                </ul>
              </li>
              <li><hr class="dropdown-divider" /></li>
            <?php endif; ?>
            <?php if (authCan('bank_slip.index')): ?>
              <li><a class="dropdown-item" href="<?= url('bank_slip'); ?>"><i class="bi bi-upc-scan me-2"></i>Boletos</a></li>
            <?php endif; ?>
            <li><hr class="dropdown-divider" /></li>
            <?php if (authCan('bank_file.index')): ?>
              <li><a class="dropdown-item" href="<?= url('bank_file'); ?>"><i class="bi bi-file-earmark-text me-2"></i>Remessas</a></li>
            <?php endif; ?>
          </ul>
        </li>
        <!-- /Menu financeiro -->
        <?php if (authCan('task.index') || authCan('task.create') || authCan('task_category.index') || authCan('task_category.create')): ?>
          <!-- Menu tarefas -->
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" id="tasksMenu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="bi bi-list-check me-1"></i> Tarefas
            </a>
            <ul class="dropdown-menu" aria-labelledby="tasksMenu">
              <?php if (authCan('task.create')): ?>
                <li><a class="dropdown-item" href="<?= url('task/create'); ?>"><i class="bi bi-plus-square me-2"></i>Cadastrar</a></li>
                <li><hr class="dropdown-divider" /></li>
              <?php endif; ?>
              <?php if (authCan('task.index')): ?>
                <li><a class="dropdown-item" href="<?= url('task'); ?>"><i class="bi bi-list-ul me-2"></i>Listar</a></li>
                <li><hr class="dropdown-divider" /></li>
              <?php endif; ?>
              <li class="dropdown-submenu">
                <a class="dropdown-item submenu-toggle" href="#"><i class="bi bi-tags me-2"></i>Categorias</a>
                <ul class="dropdown-menu">
                  <?php if (authCan('task_category.create')): ?>
                    <li><a class="dropdown-item" href="<?= url('task_category/create'); ?>"><i class="bi bi-tag me-2"></i>Cadastrar</a></li>
                  <?php endif; ?>
                  <li><hr class="dropdown-divider" /></li>
                  <?php if (authCan('task_category.index')): ?>
                    <li><a class="dropdown-item" href="<?= url('task_category'); ?>"><i class="bi bi-list me-2"></i>Listar</a></li>
                  <?php endif; ?>
                </ul>
              </li>
            </ul>
          </li>
          <!-- /Menu tarefas -->
        <?php endif; ?>
        <?php if (authCan('schedule.index') || authCan('schedule.create') || authCan('schedule.calendar') || authCan('schedule_category.index') || authCan('schedule_category.create')): ?>
          <!-- Menu agenda -->
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" id="scheduleMenu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="bi bi-calendar3 me-1"></i> Agenda
            </a>
            <ul class="dropdown-menu" aria-labelledby="scheduleMenu">
              <?php if (authCan('schedule.create')): ?>
                <li><a class="dropdown-item" href="<?= url('schedule/create'); ?>"><i class="bi bi-calendar-plus me-2"></i>Cadastrar</a></li>
                <li><hr class="dropdown-divider" /></li>
              <?php endif; ?>
              <?php if (authCan('schedule.index')): ?>
                <li><a class="dropdown-item" href="<?= url('schedule'); ?>"><i class="bi bi-list-ul me-2"></i>Listar</a></li>
                <li><hr class="dropdown-divider" /></li>
              <?php endif; ?>
              <?php if (authCan('schedule.calendar')): ?>
                <li><a class="dropdown-item" href="<?= url('schedule/calendar'); ?>"><i class="bi bi-calendar3 me-2"></i>Calendário</a></li>
                <?php if (authCan('schedule_category.index') || authCan('schedule_category.create')): ?>
                  <li><hr class="dropdown-divider" /></li>
                <?php endif; ?>
              <?php endif; ?>
              <?php if (authCan('schedule_category.index') || authCan('schedule_category.create')): ?>
                <li class="dropdown-submenu">
                  <a class="dropdown-item submenu-toggle" href="#"><i class="bi bi-tags me-2"></i>Categorias</a>
                  <ul class="dropdown-menu">
                    <?php if (authCan('schedule_category.create')): ?>
                      <li><a class="dropdown-item" href="<?= url('schedule_category/create'); ?>"><i class="bi bi-tag me-2"></i>Cadastrar</a></li>
                    <?php endif; ?>
                    <li><hr class="dropdown-divider" /></li>
                    <?php if (authCan('schedule_category.index')): ?>
                      <li><a class="dropdown-item" href="<?= url('schedule_category'); ?>"><i class="bi bi-list me-2"></i>Listar</a></li>
                    <?php endif; ?>
                  </ul>
                </li>
              <?php endif; ?>
            </ul>
          </li>
          <!-- /Menu agenda -->
        <?php endif; ?>
        <!-- Menu loja -->
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" id="storeMenu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="bi bi-shop me-1"></i> Loja
          </a>
          <ul class="dropdown-menu" aria-labelledby="storeMenu">
            <?php if (authCan('product_category.index')): ?>
              <li><a class="dropdown-item" href="<?= url('product_category'); ?>"><i class="bi bi-tags me-2"></i>Categorias de produtos</a></li>
            <?php endif; ?>
            <li><hr class="dropdown-divider" /></li>
            <?php if (authCan('product.index')): ?>
              <li><a class="dropdown-item" href="<?= url('product'); ?>"><i class="bi bi-box-seam me-2"></i>Produtos</a></li>
            <?php endif; ?>
            <li><hr class="dropdown-divider" /></li>
            <?php if (authCan('product_stock_movement.index')): ?>
              <li><a class="dropdown-item" href="<?= url('product_stock_movement'); ?>"><i class="bi bi-arrow-left-right me-2"></i>Movimentações de estoque</a></li>
            <?php endif; ?>
          </ul>
        </li>
        <!-- /Menu loja -->
        <?php endif; ?>
      </ul>
      <!-- Sistema -->
      <ul class="navbar-nav app-navbar-nav mb-2 mb-lg-0">
      <?php if ($isAuthenticated): ?>
        <li class="nav-item dropdown me-lg-3">
          <a class="nav-link dropdown-toggle app-nav-link d-flex align-items-center" href="#" id="systemMenu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="bi bi-gear-fill"></i>&nbsp;
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
            <i class="bi bi-person-circle me-2"></i>
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
