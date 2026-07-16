Manager System

- Apenas para usuários admin e user;
- Este é o cliente tenant para todos os bancos de dados.

## Instalação em servidor web

As configurações Docker usadas no ambiente local ficam no diretório acima da aplicação e servem como referência para produção: PHP-FPM 8.4, Nginx, MySQL 8.0 e Redis 7. No Docker, a pasta `src` é montada em `/var/www/html`, então o servidor web deve enxergar `manager_main` e `manager` lado a lado:

```text
/var/www/html/
  assets/
  manager_main/
  manager/
```

### Requisitos

- PHP 8.4 com as extensões `intl`, `pdo`, `pdo_mysql`, `zip` e `redis`.
- Composer instalado no servidor.
- MySQL 8.0 acessível pela aplicação.
- Redis 7 acessível pela aplicação.
- Nginx com PHP-FPM ou Apache com suporte a `.htaccess`.

Em produção, evite habilitar Xdebug. Ele aparece no Docker para desenvolvimento, mas não é necessário para executar a aplicação no servidor.

### Publicação dos arquivos

1. Envie os diretórios `assets`, `manager_main` e `manager` para `/var/www/html`.
2. Instale uma única vez as dependências PHP compartilhadas:

```sh
cd /var/www/html/assets
composer install --no-dev --optimize-autoloader
```

Os dois sistemas procuram esse diretório no mesmo nível por padrão. Se a implantação utilizar outro caminho, configure `SHARED_ASSETS_PATH` com o caminho absoluto da pasta `assets`.

3. Garanta permissão de escrita para o usuário do PHP-FPM nas pastas públicas que recebem arquivos por tenant:

```sh
mkdir -p /var/www/html/manager/public/tenant_files
chown -R www-data:www-data /var/www/html/manager/public/tenant_files
chmod -R 775 /var/www/html/manager/public/tenant_files
```

### Variáveis de ambiente

Configure as variáveis no PHP-FPM, no serviço da aplicação ou no gerenciador de processos usado pelo servidor. Os valores abaixo seguem os nomes esperados pelo código e pelos serviços definidos no Docker:

```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE_MANAGER=manager_main
DB_USERNAME=usuario_do_banco
DB_PASSWORD=senha_do_banco
DB_CHARSET=utf8mb4

SUPPORT_CHAT_SECRET=troque_por_um_segredo_forte
SUPPORT_CHAT_WS_URL=wss://seu-dominio.com/ws
SHARED_ASSETS_PATH=/var/www/html/assets
```

No Docker local, o host do banco é `mysql`, a porta é `3306` e o Redis fica disponível no host `redis`. Em um servidor tradicional, ajuste `DB_HOST` e a conexão do Redis conforme a infraestrutura instalada.

### Banco de dados

Crie ou restaure o banco principal `manager_main` e os bancos de tenants no formato `manager_XXXX`, por exemplo `manager_0001`. O Docker cria um banco inicial chamado `generic_system`, mas esta aplicação usa `manager_main` como banco principal por padrão.

Para o módulo de loja, execute `app/Database/store_schema.sql` em cada banco tenant que deve possuir produtos, imagens e movimentações de estoque.

Para o módulo de tarefas, execute `app/Database/tasks_schema.sql` em cada banco tenant que deve possuir tarefas, categorias, responsáveis, execuções e comentários.

### Nginx

O Nginx deve apontar a raiz para `/var/www/html`, mantendo as URLs públicas usadas no Docker:

- `https://seu-dominio.com/manager_main/public/`
- `https://seu-dominio.com/manager/public/`

Exemplo equivalente ao `nginx.conf` local:

```nginx
server {
    listen 80;
    server_name seu-dominio.com;

    root /var/www/html;
    index index.php index.html;

    location = / {
        return 302 /manager_main/public/;
    }

    location = /manager_main/public {
        return 301 /manager_main/public/;
    }

    location = /manager/public {
        return 301 /manager/public/;
    }

    location /manager_main/public/ {
        try_files $uri $uri/ @manager_main;
    }

    location @manager_main {
        rewrite ^/manager_main/public/(.*)$ /manager_main/public/index.php?url=$1&$args last;
    }

    location /manager/public/ {
        try_files $uri $uri/ @manager;
    }

    location @manager {
        rewrite ^/manager/public/(.*)$ /manager/public/index.php?url=$1&$args last;
    }

    location ^~ /assets/bootstrap/ {
        location ~* \.(?:css|js|map|svg|png|ico|woff|woff2)$ {
            try_files $uri =404;
        }

        return 404;
    }

    location ^~ /assets/fullcalendar/ {
        location ~* \.js$ {
            try_files $uri =404;
        }

        return 404;
    }

    location ^~ /assets/ {
        deny all;
    }

    location ~ \.php$ {
        try_files $uri =404;
        include fastcgi_params;
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    location ~ /\. {
        deny all;
    }
}
```

Se o PHP-FPM do servidor usa socket Unix, troque `fastcgi_pass 127.0.0.1:9000` pelo socket configurado, por exemplo `fastcgi_pass unix:/run/php/php8.4-fpm.sock;`.

### Apache

Se usar Apache, mantenha a mesma raiz em `/var/www/html` e habilite `AllowOverride All`, conforme o `vhost.conf` usado como referência:

```apache
<VirtualHost *:80>
    ServerName seu-dominio.com
    DocumentRoot /var/www/html

    <Directory /var/www/html>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    <Directory /var/www/html/assets>
        Require all denied
    </Directory>

    <Directory /var/www/html/assets/bootstrap>
        Options -Indexes
        AllowOverride None
        Require all granted

        <FilesMatch "(?i)^(?!.*\.(css|js|map|svg|png|ico|woff|woff2)$).*$">
            Require all denied
        </FilesMatch>
    </Directory>

    <Directory /var/www/html/assets/fullcalendar>
        Options -Indexes
        AllowOverride None
        Require all granted

        <FilesMatch "(?i)^(?!.*\.js$).*$">
            Require all denied
        </FilesMatch>
    </Directory>
</VirtualHost>
```

### Chat de suporte

O Docker executa o WebSocket do chat com o comando abaixo, a partir de `/var/www/html`:

```sh
SUPPORT_CHAT_SECRET=troque_por_um_segredo_forte \
SUPPORT_CHAT_WS_HOST=0.0.0.0 \
SUPPORT_CHAT_WS_PORT=8081 \
php manager_main/app/SupportChat/server.php
```

Em produção, execute esse processo com `systemd`, Supervisor ou outro gerenciador de processos. Se expuser o WebSocket direto, libere a porta `8081`; se usar proxy reverso, encaminhe a rota pública configurada em `SUPPORT_CHAT_WS_URL` para esse serviço.

# Regras de programação do projeto

Este documento registra as convenções de desenvolvimento que devem ser seguidas neste projeto.

## Idioma e nomenclatura

- Mantenha comentários de métodos e documentação em inglês.
- Escreva nomes de métodos em inglês.
- Use `camelCase` em nomes de métodos quando a convenção do PHP/framework ao redor pedir esse padrão.
- Prefira nomes claros para models, controllers e services, sempre alinhados com a entidade de domínio.
- Mantenha rótulos e textos visíveis ao usuário em português quando a view atual já estiver em português.
- Em novos códigos, documente métodos com comentários de bloco iniciados por `/**`, mantenha cada linha intermediária iniciada por `*` e finalize o bloco com `**/`.

Exemplo recomendado para comentários de métodos:

```php
/**
 * Describe what the method does.
 * @param int $id Identifier used by the method.
 * @return bool True when the operation succeeds.
 **/
```

## Estrutura PHP

- Mantenha actions de controllers enxutas: valide permissões, leia entradas, chame models/services e escolha a resposta.
- Ao criar novos módulos com listagem, implemente também no controller os métodos de exportação para PDF e Excel, seguindo o padrão `exportPdf()` e `exportExcel()` já suportado pelo projeto.
- Coloque escritas no banco e fluxos transacionais em models ou services.
- Reutilize helpers existentes em `app/Helpers` antes de criar novas lógicas de formatação ou parsing.
- Use `requirePermission()` ou `requireAnyPermission()` em actions que expõem dados protegidos.
- Retorne JSON com `$this->json()` em endpoints AJAX.
- Evite refatorações sem relação direta com a alteração solicitada.

## Código reutilizável

- Sempre que um código puder ser reutilizado por mais de uma aplicação não o mantenha duplicado ou embutido em uma view.
- Antes de criar uma nova biblioteca, verifique se já existe uma biblioteca em `assets/` que possa ser estendida.
- Quando for necessária uma nova biblioteca JavaScript reutilizável, crie-a no diretório compartilhado seguindo o padrão `assets/<nome_da_lib>/<nome_da_lib>.js`.
- Mantenha a biblioteca independente dos detalhes de uma tela específica e exponha somente as opções necessárias para que cada tela configure seu comportamento.
- Nas views de `manager` e `manager_main`, apenas carregue e inicialize a biblioteca compartilhada; mantenha nelas somente o código que for realmente exclusivo daquela tela.

## Views e assets

- Para arquivos de view PHP, coloque JavaScript específico da tela em:
  `public/assets/js/{nome_plural_da_view}/{basename_da_view}.js`
- Para arquivos de view PHP, coloque CSS específico da tela em:
  `public/assets/css/{nome_plural_da_view}/{basename_da_view}.css`
- Exemplo: `app/Views/people/index.php` deve usar:
  `public/assets/js/people/index.js` e `public/assets/css/people/index.css`
- Inclua JS e CSS específicos da view com query string de cache:
  `?v={numero}`
- Todas as views devem manter os botões de ação principais em uma barra fixa no rodapé da página usando a classe `view-actions`.
- Aplique a mesma regra em views `informations.php` e em views antigas que ainda não seguem um padrão próprio de área de ações.
- Não inclua botões de ação de linha de tabela, botões de modal ou controles internos de blocos repetíveis na barra fixa da view.
- Crie elementos HTML com os atributos separados por espaço e tudo na mesma linha sempre.
- Escape saídas em views com `htmlspecialchars()` ou com o padrão local de helper `$e()`.
- Mantenha nomes de campos agrupados por entidade, como `person[name]`, `user[type]`, `emails[0][address]`.

## Regras para SQL

- Use nomes de tabelas em inglês e no plural. Quando o nome tiver uma única palavra, essa palavra deve ficar no plural. Quando o nome for composto, separe cada palavra com underline (`_`) e mantenha no plural somente as palavras subsequentes à primeira(que deve ser no singular), como `product_categories`, `product_images` e `product_stocks_movements`.
- Sempre use prepared statements e placeholders nomeados para valores dinâmicos.
- Não concatene entradas do usuário diretamente em strings SQL.
- Use listas explícitas de colunas; evite `SELECT *` em queries usadas por telas ou APIs.
- Use aliases legíveis em PascalCase representando a tabela/entidade, como `Address`, `Neighborhood`, `City`, `State`.
- Coloque cada coluna selecionada em sua própria linha.
- Alinhe SQL multi-linha para que `FROM`, `JOIN`, `WHERE` e `ORDER BY` fiquem fáceis de escanear.
- Use tipos de join explícitos. Prefira `LEFT JOIN` apenas quando registros relacionados ausentes forem aceitáveis.
- Use `ORDER BY` explícito para garantir saída determinística em telas.

Exemplo recomendado de formatação SQL:

```php
$addressesStmt = $this->db->prepare("SELECT Address.id,
                                            Address.zip_code,
                                            Address.street,
                                            Address.number,
                                            Address.observations,
                                            Address.neighborhood_id,
                                            Neighborhood.name AS neighborhood_name,
                                            City.name AS city_name,
                                            State.name AS state_name
                                     FROM addresses AS Address
                                     LEFT JOIN neighborhoods AS Neighborhood ON Neighborhood.id = Address.neighborhood_id
                                     LEFT JOIN cities AS City ON City.id = Neighborhood.city_id
                                     LEFT JOIN states AS State ON State.id = City.state_id
                                     WHERE Address.person_id = :id
                                     ORDER BY Address.id ASC");
$addressesStmt->execute(['id' => $id]);
```

## Banco de dados e transações

- Para acesso a banco de dados em tarefas operacionais, debugging e consultas fora da aplicação web, use sempre o MCP do projeto.
- Não use acesso direto via clientes nativos (`mysql`, `psql`, etc.) quando a operação puder ser feita via MCP.
- Ao executar o MCP localmente, use sempre PHP na linha de comando (ex.: `php .mcp/server.php` ou `./.mcp/run.sh`).
- Envolva fluxos de create/update/delete em múltiplas tabelas com transações.
- Faça rollback da transação quando qualquer escrita falhar.
- Mantenha relacionamentos de tenants explícitos por meio de `tenant_users`.
- Ao atualizar registros filhos repetíveis, valide tanto o `id` quanto a chave estrangeira do dono quando possível, por exemplo `id` + `person_id`.
- Normalize documentos, datas e telefones com helpers existentes antes de persistir.

## Comportamento frontend

- Siga os padrões existentes de UI baseados em Bootstrap.
- Use Bootstrap Icons já utilizados pelo projeto em botões de ação.
- Mantenha blocos repetíveis visualmente consistentes com o padrão `people-repeat-item`.
- Para modais, envie formulários AJAX com `fetch()` e inclua:
  `Accept: application/json` e `X-Requested-With: XMLHttpRequest`.
- Mantenha controles desabilitados ou somente leitura quando uma tela exibir dados relacionados que não devem ser alterados pelo formulário atual.

## Validação e verificação

- Após editar arquivos PHP, execute `php -l` nos arquivos PHP alterados.
- Após editar ou criar arquivos `.js`, execute `node --check caminho/do/arquivo.js` em cada arquivo JavaScript alterado para validar a sintaxe pela linha de comando.
- Para mudanças em JavaScript, valide o comportamento da tela afetada no navegador quando possível.
- Atualize versões de cache (`?v={numero}`) ao alterar JS ou CSS específico de view.
- Prefira verificações manuais focadas no fluxo alterado em vez de testes amplos sem relação com a mudança.
