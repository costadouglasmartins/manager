<?php
/**
 * Navbar menu definition filtered by App\Services\MainMenuService before rendering.
 * Top-level entries are groups with an `id`; nested entries are groups (`children`), links or dividers.
 * Every link must declare the same ability its controller action requires: `ability` + `url`, or an ordered
 * `targets` list whose first granted ability decides the URL. Groups and dividers are shown only when
 * a visible link remains next to them.
 * @todo Evolve this array to a db table.
 **/
return [
  'main' => [
    ['id' => 'essentialData', 'label' => 'Cadastros essenciais', 'icon' => 'bi-layers', 'children' => [
      ['label' => 'Pessoas', 'icon' => 'bi-people-fill', 'children' => [
        ['label' => 'Cadastrar', 'icon' => 'bi-person-fill-add', 'url' => 'person/create', 'ability' => 'person.create'],
        ['divider' => true],
        ['label' => 'Listar', 'icon' => 'bi-person-lines-fill', 'url' => 'person', 'ability' => 'person.index'],
        ['divider' => true],
        ['label' => 'Associações', 'icon' => 'bi-asterisk', 'children' => [
          ['label' => 'E-mails', 'icon' => 'bi-envelope-paper-fill', 'url' => 'email', 'ability' => 'email.index'],
          ['divider' => true],
          ['label' => 'Endereços', 'icon' => 'bi-signpost-split-fill', 'url' => 'address', 'ability' => 'address.index'],
          ['divider' => true],
          ['label' => 'Telefones', 'icon' => 'bi-telephone-fill', 'url' => 'phone_number', 'ability' => 'phone_number.index'],
        ]],
      ]],
    ]],
    ['id' => 'financeMenu', 'label' => 'Financeiro', 'icon' => 'bi-bank', 'children' => [
      ['label' => 'Bancos', 'icon' => 'bi-bank', 'url' => 'bank', 'ability' => 'bank.index'],
      ['divider' => true],
      ['label' => 'Contas Bancárias', 'icon' => 'bi-wallet2', 'url' => 'bank_account', 'ability' => 'bank_account.index'],
      ['divider' => true],
      ['label' => 'Operações financeiras', 'icon' => 'bi-receipt', 'children' => [
        ['label' => 'Operações', 'icon' => 'bi-receipt', 'url' => 'financial_operation', 'ability' => 'financial_operation.index'],
        ['divider' => true],
        ['label' => 'Planilha anual', 'icon' => 'bi-table', 'url' => 'financial_operation/spreadsheet', 'ability' => 'financial_operation.index'],
        ['divider' => true],
        ['label' => 'Categorias', 'icon' => 'bi-tags', 'url' => 'financial_operation_category', 'ability' => 'financial_operation_category.index'],
        ['divider' => true],
        ['label' => 'Subcategorias', 'icon' => 'bi-diagram-3', 'url' => 'financial_operation_subcategory', 'ability' => 'financial_operation_subcategory.index'],
        ['divider' => true],
        ['label' => 'Pagamentos', 'icon' => 'bi-cash-stack', 'url' => 'financial_operation_payment', 'ability' => 'financial_operation_payment.index'],
      ]],
      ['divider' => true],
      ['label' => 'Boletos', 'icon' => 'bi-upc-scan', 'url' => 'bank_slip', 'ability' => 'bank_slip.index'],
      ['divider' => true],
      ['label' => 'Remessas', 'icon' => 'bi-file-earmark-text', 'url' => 'bank_file', 'ability' => 'bank_file.index'],
    ]],
    ['id' => 'crmMenu', 'label' => 'CRM', 'icon' => 'bi-briefcase', 'children' => [
      ['label' => 'Painel', 'icon' => 'bi-speedometer2', 'url' => 'crm/dashboard', 'ability' => 'crm.dashboard'],
      ['label' => 'Cadastrar', 'icon' => 'bi-person-plus', 'url' => 'crm/create', 'ability' => 'crm.create'],
      ['divider' => true],
      ['label' => 'Listar', 'icon' => 'bi-list-ul', 'url' => 'crm', 'ability' => 'crm.index'],
      ['label' => 'Atividades', 'icon' => 'bi-clock-history', 'targets' => [
        ['ability' => 'crm_activity.index', 'url' => 'crm_activity'],
        ['ability' => 'crm_activity.create', 'url' => 'crm_activity/create'],
      ]],
      ['label' => 'Oportunidades', 'icon' => 'bi-currency-dollar', 'targets' => [
        ['ability' => 'crm_opportunity.index', 'url' => 'crm_opportunity'],
        ['ability' => 'crm_opportunity.create', 'url' => 'crm_opportunity/create'],
      ]],
      ['label' => 'Funil Kanban', 'icon' => 'bi-kanban', 'url' => 'crm_opportunity/kanban', 'ability' => 'crm_opportunity.kanban'],
      ['label' => 'Relatório comercial', 'icon' => 'bi-graph-up-arrow', 'url' => 'crm_opportunity/report', 'ability' => 'crm_opportunity.report'],
      ['divider' => true],
      ['label' => 'Configurações', 'icon' => 'bi-gear', 'children' => [
        ['label' => 'Status do CRM', 'icon' => 'bi-tags', 'targets' => [
          ['ability' => 'crm_status.index', 'url' => 'crm_status'],
          ['ability' => 'crm_status.create', 'url' => 'crm_status/create'],
        ]],
        ['label' => 'Tipos de atividade', 'icon' => 'bi-ui-checks', 'targets' => [
          ['ability' => 'crm_activity_type.index', 'url' => 'crm_activity_type'],
          ['ability' => 'crm_activity_type.create', 'url' => 'crm_activity_type/create'],
        ]],
        ['label' => 'Status de atividade', 'icon' => 'bi-check2-circle', 'targets' => [
          ['ability' => 'crm_activity_status.index', 'url' => 'crm_activity_status'],
          ['ability' => 'crm_activity_status.create', 'url' => 'crm_activity_status/create'],
        ]],
        ['label' => 'Origens', 'icon' => 'bi-signpost-split', 'targets' => [
          ['ability' => 'crm_source.index', 'url' => 'crm_source'],
          ['ability' => 'crm_source.create', 'url' => 'crm_source/create'],
        ]],
        ['label' => 'Motivos de inatividade', 'icon' => 'bi-pause-circle', 'targets' => [
          ['ability' => 'crm_inactivity_reason.index', 'url' => 'crm_inactivity_reason'],
          ['ability' => 'crm_inactivity_reason.create', 'url' => 'crm_inactivity_reason/create'],
        ]],
        ['label' => 'Motivos de perda', 'icon' => 'bi-x-octagon', 'targets' => [
          ['ability' => 'crm_loss_reason.index', 'url' => 'crm_loss_reason'],
          ['ability' => 'crm_loss_reason.create', 'url' => 'crm_loss_reason/create'],
        ]],
        ['label' => 'Tipos de oportunidade', 'icon' => 'bi-diagram-2', 'targets' => [
          ['ability' => 'crm_opportunity_type.index', 'url' => 'crm_opportunity_type'],
          ['ability' => 'crm_opportunity_type.create', 'url' => 'crm_opportunity_type/create'],
        ]],
        ['label' => 'Etapas do funil', 'icon' => 'bi-funnel', 'targets' => [
          ['ability' => 'crm_pipeline_stage.index', 'url' => 'crm_pipeline_stage'],
          ['ability' => 'crm_pipeline_stage.create', 'url' => 'crm_pipeline_stage/create'],
        ]],
      ]],
    ]],
    ['id' => 'tasksMenu', 'label' => 'Tarefas', 'icon' => 'bi-list-check', 'children' => [
      ['label' => 'Cadastrar', 'icon' => 'bi-plus-square', 'url' => 'task/create', 'ability' => 'task.create'],
      ['divider' => true],
      ['label' => 'Listar', 'icon' => 'bi-list-ul', 'url' => 'task', 'ability' => 'task.index'],
      ['divider' => true],
      ['label' => 'Categorias', 'icon' => 'bi-tags', 'children' => [
        ['label' => 'Cadastrar', 'icon' => 'bi-tag', 'url' => 'task_category/create', 'ability' => 'task_category.create'],
        ['divider' => true],
        ['label' => 'Listar', 'icon' => 'bi-list', 'url' => 'task_category', 'ability' => 'task_category.index'],
      ]],
    ]],
    ['id' => 'hrMenu', 'label' => 'RH', 'icon' => 'bi-people', 'children' => [
      ['label' => 'Funcionários', 'icon' => 'bi-person-vcard', 'url' => 'hr_employee', 'ability' => 'hr_employee.index'],
      ['divider' => true],
      ['label' => 'Departamentos', 'icon' => 'bi-diagram-3', 'url' => 'hr_department', 'ability' => 'hr_department.index'],
      ['divider' => true],
      ['label' => 'Cargos', 'icon' => 'bi-person-badge', 'url' => 'hr_position', 'ability' => 'hr_position.index'],
    ]],
    ['id' => 'scheduleMenu', 'label' => 'Agenda', 'icon' => 'bi-calendar3', 'children' => [
      ['label' => 'Cadastrar', 'icon' => 'bi-calendar-plus', 'url' => 'schedule/create', 'ability' => 'schedule.create'],
      ['divider' => true],
      ['label' => 'Listar', 'icon' => 'bi-list-ul', 'url' => 'schedule', 'ability' => 'schedule.index'],
      ['divider' => true],
      ['label' => 'Calendário', 'icon' => 'bi-calendar3', 'url' => 'schedule/calendar', 'ability' => 'schedule.calendar'],
      ['divider' => true],
      ['label' => 'Categorias', 'icon' => 'bi-tags', 'children' => [
        ['label' => 'Cadastrar', 'icon' => 'bi-tag', 'url' => 'schedule_category/create', 'ability' => 'schedule_category.create'],
        ['divider' => true],
        ['label' => 'Listar', 'icon' => 'bi-list', 'url' => 'schedule_category', 'ability' => 'schedule_category.index'],
      ]],
    ]],
    ['id' => 'storeMenu', 'label' => 'Loja', 'icon' => 'bi-shop', 'children' => [
      ['label' => 'Categorias de produtos', 'icon' => 'bi-tags', 'children' => [
        ['label' => 'Cadastrar', 'icon' => 'bi-tag', 'url' => 'product_category/create', 'ability' => 'product_category.create'],
        ['divider' => true],
        ['label' => 'Listar', 'icon' => 'bi-list', 'url' => 'product_category', 'ability' => 'product_category.index'],
      ]],
      ['divider' => true],
      ['label' => 'Produtos', 'icon' => 'bi-box-seam', 'children' => [
        ['label' => 'Cadastrar', 'icon' => 'bi-plus-square', 'url' => 'product/create', 'ability' => 'product.create'],
        ['divider' => true],
        ['label' => 'Importar', 'icon' => 'bi-file-earmark-spreadsheet', 'url' => 'product/import-spreadsheet', 'ability' => 'product.create'],
        ['divider' => true],
        ['label' => 'Listar', 'icon' => 'bi-list-ul', 'url' => 'product', 'ability' => 'product.index'],
      ]],
      ['divider' => true],
      ['label' => 'Movimentações de estoque', 'icon' => 'bi-arrow-left-right', 'url' => 'product_stock_movement', 'ability' => 'product_stock_movement.index'],
    ]],
  ],
  'system' => [
    ['label' => 'Permissões', 'icon' => 'bi-gear', 'url' => 'permission', 'ability' => 'permission.manage'],
  ],
];
?>
