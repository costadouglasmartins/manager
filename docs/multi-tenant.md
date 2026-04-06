# Arquitetura multi-tenant

## Visao geral

O projeto foi preparado para operar com dois contextos de banco:

- `manager_main`: autenticacao, usuarios, tenants e permissoes centrais.
- `main_0001`, `main_0002`, ...: dados de negocio de cada tenant.

O login continua centralizado em `manager_main`. Depois do login, a aplicacao resolve a lista de tenants disponiveis para o usuario pela tabela `tenant_users` e grava o tenant selecionado em `$_SESSION['manager_main']['tenant']`.

## Estrutura recomendada

- `users`, `people`, `emails`, `permissions`, `user_permissions`:
  ficam em `manager_main` e continuam sendo a fonte oficial de identidade.
- `tenants`:
  guarda o cadastro do tenant e o nome do banco fisico (`database_name`).
- `tenant_users`:
  define quais usuarios do banco principal acessam cada tenant.
- `tenant_permissions` e `tenant_user_permissions`:
  controlam as permissoes especificas do sistema `manager`.

## Fluxo de acesso

1. O usuario autentica no `manager_main`.
2. A aplicacao consulta os tenants disponiveis para esse usuario.
3. O endpoint `/tenant/switch/{id}` seleciona o tenant e salva o contexto na sessao.
4. Modelos do sistema de tenant devem herdar de `App\Core\TenantModel`.
5. A conexao passa a usar o banco definido em `tenants.database_name`.

## Convencao de bancos

Use uma mascara previsivel para o nome fisico do banco:

- `main_0001`
- `main_0002`
- `main_0003`

O `code` do tenant pode seguir o mesmo numero logico:

- `0001`
- `0002`
- `0003`

## Provisionamento sugerido

1. Criar o registro em `manager_main.tenants`.
2. Criar o vinculo do usuario em `manager_main.tenant_users`.
3. Executar o script `public/database/tenant_template.sql`, substituindo `%s` pelo nome do banco do tenant.
4. Popular permissoes iniciais em `tenant_permissions`.
5. Criar os modelos/controladores do novo sistema herdando de `TenantModel`.

## Observacoes de implementacao

- `App\Core\Database` agora aceita conexoes por banco e reutiliza instancias por `database_name`.
- `App\Core\TenantContext` concentra o tenant ativo da sessao.
- `App\Models\Tenant` gerencia consulta e vinculo usuario-tenant.
- `App\Models\TenantPermission` centraliza as permissoes por tenant no `manager_main`.
