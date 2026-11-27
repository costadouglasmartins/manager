Manager System
# Instalação do `manager` e do `manager_main` em uma VPS

Este guia instala os dois sistemas numa VPS Linux sem Docker, do zero até o
deploy automático pelo GitHub Actions funcionar de novo:

- **`manager_main`**: painel central, só para usuários `root`. Também roda o
  servidor WebSocket do chat (`support-chat`).
- **`manager`**: sistema dos tenants, para usuários `admin` e `user`.
- **`manager_assets`**: dependências compartilhadas pelos dois (`vendor/` do
  Composer, Bootstrap, FullCalendar e as bibliotecas JS). Sem ele, nenhum dos
  dois sistemas abre.

```text
Navegador ──HTTPS──► Nginx ──FastCGI──► PHP-FPM ──► MariaDB (manager_main + manager_NNNN)
             │                                         ▲
             └──WSS /ws──► support-chat (PHP CLI, 127.0.0.1:8081) ─┘
```

## Convenções deste guia

- Os comandos rodam como `root` na VPS, salvo quando o bloco diz "no Mac".
- Os exemplos usam o domínio e a pasta da produção atual
  (`izzidoro.com.br` e `/var/www/izzidoro.com.br/public`), porque os scripts de
  deploy do GitHub têm esse caminho fixo. Se a nova VPS atender outro domínio,
  troque `izzidoro.com.br` em todos os arquivos deste guia e veja o
  [passo 11](#11-deploy-automático-github-actions).
- Gere todas as senhas e segredos com `openssl rand -hex 32`. Valores
  hexadecimais não precisam de aspas nem de escape em nenhum dos arquivos de
  configuração abaixo.

Estrutura final no servidor:

```text
/var/www/izzidoro.com.br/public/
  manager_assets/   (vendor/, bootstrap/, fullcalendar/, autocomplete/, ...)
  manager_main/     (painel central + app/SupportChat/server.php)
  manager/          (sistema dos tenants)
```

Os três diretórios precisam ficar lado a lado. Cada sistema procura
`../manager_assets` por padrão; para outro caminho, use `SHARED_ASSETS_PATH`.

---

## 1. Preparar o servidor

Use um Ubuntu Server LTS recente. Aponte o registro DNS `A` do domínio para o
IP da VPS antes do passo 9 (HTTPS).

```sh
apt update && apt -y full-upgrade
timedatectl set-timezone America/Sao_Paulo

apt -y install nginx git curl unzip rsync python3 util-linux \
               certbot python3-certbot-nginx ufw unattended-upgrades

ufw allow OpenSSH
ufw allow 'Nginx Full'
ufw enable
```

Não abra a porta `8081`: o chat fica atrás do Nginx, em `/ws`.

`python3`, `rsync`, `curl` e `flock` (pacote `util-linux`) são exigidos pelos
scripts de deploy do GitHub. Mesmo que você não use o deploy automático agora,
deixe-os instalados.

## 2. PHP

Os sistemas precisam de PHP 8.1 ou mais novo. O ambiente Docker de
desenvolvimento usa o 8.5; use 8.4 ou 8.5 na VPS. Confira a versão que o Ubuntu
oferece com `apt-cache policy php-fpm`. Se ela for menor que 8.4, adicione o
PPA `ppa:ondrej/php` e instale os pacotes com a versão no nome (`php8.4-fpm`
etc.).

```sh
apt -y install php-fpm php-cli php-mysql php-intl php-zip php-gd \
               php-mbstring php-xml php-curl

PHPV=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
echo "PHP $PHPV"
```

Essas extensões cobrem o que o código e o `composer.lock` pedem: `pdo_mysql`,
`intl`, `zip`, `gd`, `mbstring`, `dom`, `xml`, `xmlreader`, `xmlwriter`,
`simplexml`, `fileinfo`, `iconv`, `ctype` e `zlib`. Confira:

```sh
php -m | grep -Ei '^(pdo_mysql|intl|zip|gd|mbstring|dom|xml|xmlreader|xmlwriter|simplexml|fileinfo|iconv|ctype|zlib)$'
```

A lista deve ter 14 linhas. Não instale o Xdebug em produção.

Ajustes de `php.ini`, os mesmos limites do Docker, com os erros fora da tela:

```sh
cat > /etc/php/$PHPV/mods-available/manager.ini <<'EOF'
; priority=99
memory_limit = 512M
upload_max_filesize = 100M
post_max_size = 100M
date.timezone = America/Sao_Paulo
display_errors = Off
display_startup_errors = Off
log_errors = On
expose_php = Off
EOF
phpenmod -v $PHPV manager
systemctl restart php$PHPV-fpm
```

Com `display_errors = Off`, um erro de PHP aparece no navegador só como
"HTTP ERROR 500". O motivo fica no log de erro do Nginx (passo 7).

## 3. MariaDB

Use **MariaDB**, não MySQL. Os dumps e o `tenant_template.sql` usam a colação
`utf8mb3_uca1400_ai_ci`, que só existe no MariaDB 10.10 ou mais novo. O
desenvolvimento usa o 11.8.

```sh
apt -y install mariadb-server mariadb-client
mariadb --version
```

Se a versão for menor que 10.10, instale o 11.8 pelo repositório oficial do
MariaDB antes de continuar:

```sh
curl -LsS https://r.mariadb.com/downloads/mariadb_repo_setup | bash -s -- --mariadb-server-version=11.8
apt update && apt -y install mariadb-server mariadb-client
```

Proteja a instalação. Mantenha o `root` com autenticação por socket, que é o
padrão do Ubuntu:

```sh
mariadb-secure-installation
```

Crie o usuário da aplicação. Gere a senha com `openssl rand -hex 32` e anote-a
para o passo 4.

```sh
mariadb <<'EOF'
CREATE USER 'manager_app'@'localhost' IDENTIFIED BY 'TROQUE_PELA_SENHA_GERADA';
CREATE USER 'manager_app'@'127.0.0.1' IDENTIFIED BY 'TROQUE_PELA_SENHA_GERADA';
GRANT ALL PRIVILEGES ON `manager\_%`.* TO 'manager_app'@'localhost';
GRANT ALL PRIVILEGES ON `manager\_%`.* TO 'manager_app'@'127.0.0.1';
EOF
```

O padrão `manager\_%` cobre o `manager_main` e todos os bancos de tenant
(`manager_0001`, `manager_0002`, ...). O usuário precisa de `CREATE` e `DROP`
nesses bancos porque o painel cria a base de cada tenant novo
(`TenantProvisioner`) e a apaga quando o tenant é excluído.

O MariaDB do Ubuntu já escuta apenas em `127.0.0.1`. Mantenha assim.

## 4. Variáveis de ambiente (credenciais e chat)

Os dois `app/Core/Config.php` leem todas as configurações por `getenv()` e só
usam os valores escritos no arquivo quando a variável não existe. Defina tudo
por variável de ambiente. Os padrões que estão no `Config.php` versionado não
devem ser usados em uma VPS nova.

Crie um único arquivo com os valores:

```sh
mkdir -p /etc/manager
cat > /etc/manager/manager.env <<'EOF'
APP_ENV=production
APP_TIMEZONE=America/Sao_Paulo
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE_MANAGER=manager_main
DB_USERNAME=manager_app
DB_PASSWORD=TROQUE_PELA_SENHA_DO_PASSO_3
DB_CHARSET=utf8mb4
SUPPORT_CHAT_SECRET=TROQUE_POR_OUTRO_openssl_rand_hex_32
SUPPORT_CHAT_WS_HOST=127.0.0.1
SUPPORT_CHAT_WS_PORT=8081
SUPPORT_CHAT_WS_URL=wss://izzidoro.com.br/ws
SHARED_ASSETS_PATH=/var/www/izzidoro.com.br/public/manager_assets
EOF
chmod 600 /etc/manager/manager.env
```

- O `SUPPORT_CHAT_SECRET` assina os tokens do chat. O PHP-FPM, que emite os
  tokens, e o `support-chat`, que os valida, precisam do mesmo valor.
- O `SUPPORT_CHAT_WS_URL` é a URL que o navegador usa para abrir o chat. Sem
  ela, o sistema monta `wss://<domínio>:8081`, porta que fica fechada no
  firewall.

O PHP-FPM não lê esse arquivo sozinho. Gere a partir dele um complemento do
pool `www`:

```sh
{ echo '[www]'; sed -E 's/^([A-Z_]+)=(.*)$/env[\1] = "\2"/' /etc/manager/manager.env; } \
  > /etc/php/$PHPV/fpm/pool.d/zz-manager-env.conf
chmod 640 /etc/php/$PHPV/fpm/pool.d/zz-manager-env.conf
php-fpm$PHPV -t && systemctl restart php$PHPV-fpm
```

Sempre que mudar `/etc/manager/manager.env`, gere o `zz-manager-env.conf` de
novo e reinicie o PHP-FPM e o `support-chat` (passo 8).

> Alternativa: editar o bloco `production` dos dois `Config.php` direto no
> servidor. O deploy nunca sobrescreve esse arquivo. Nesse caso, mantenha os
> dois arquivos iguais e não use as variáveis acima.

## 5. Código e pastas

Crie a raiz:

```sh
mkdir -p /var/www/izzidoro.com.br/public
```

**No Mac**, envie os três repositórios usando as mesmas exclusões do deploy
automático. Assim, a cópia inicial fica igual ao que o GitHub Actions vai
publicar depois.

```sh
cd /Users/douglascosta/Documents/Docker/src
VPS=root@IP_DA_NOVA_VPS
DEST=/var/www/izzidoro.com.br/public

for app in manager_assets manager_main manager; do
  rsync -az --exclude-from="$app/.github/deploy/rsync-excludes" "$app/" "$VPS:$DEST/$app/"
done

# O Config.php fica fora do deploy, então vai uma única vez.
# Com as variáveis do passo 4, os padrões escritos nele não são usados.
scp manager_main/app/Core/Config.php "$VPS:$DEST/manager_main/app/Core/Config.php"
scp manager/app/Core/Config.php      "$VPS:$DEST/manager/app/Core/Config.php"
```

Não é preciso rodar o Composer na VPS. O `manager_assets/vendor/` já vem
versionado no repositório. Só rode `composer install --no-dev
--optimize-autoloader` em `manager_assets` se mudar as dependências.

**Na VPS**, ajuste dono e permissões. O código fica com o `root` e só pode ser
lido pelo PHP. As pastas abaixo recebem escrita do `www-data`:

| Pasta | Uso |
| --- | --- |
| `manager/public/tenant_files/` | Arquivos enviados, separados por tenant e módulo |
| `manager/tmp/chunk_uploads/` | Uploads em partes, enquanto não terminam |
| `manager_main/storage/support_chats/` | Estado de "digitando" do chat |

```sh
cd /var/www/izzidoro.com.br/public
chown -R root:root .
find . -type d -exec chmod 755 {} +
find . -type f -exec chmod 644 {} +

mkdir -p manager/public/tenant_files manager/tmp/chunk_uploads manager_main/storage/support_chats
chown -R www-data:www-data manager/public/tenant_files manager/tmp manager_main/storage
chmod -R 775 manager/public/tenant_files manager/tmp manager_main/storage

test -f manager_assets/vendor/autoload.php && echo "vendor OK"
test -f manager_main/public/database/tenant_template.sql && echo "template OK"
```

O crawler de CEP do `manager_main` grava arquivos `zip_code_crawler_*` dentro
de `manager_main/public/`. Para usá-lo na VPS, o `www-data` precisa escrever
nessa pasta. Só libere isso se for usar o crawler, porque deixa a pasta
pública gravável pelo PHP.

## 6. Banco de dados

Escolha **uma** das opções.

### Opção A: migrar da VPS atual (recomendado)

Traz usuários, tenants, permissões e arquivos como estão hoje. Para não perder
gravações, faça em janela de manutenção, com o chat parado na VPS antiga.

**Na VPS antiga:**

```sh
systemctl stop support-chat

DBS=$(mariadb -N -e "SHOW DATABASES LIKE 'manager\_%'")
echo $DBS   # confira: manager_main e todos os manager_NNNN
mariadb-dump --single-transaction --routines --triggers --events \
  --databases $DBS | gzip > /root/manager-db-$(date +%F-%H%M).sql.gz

cd /var/www/izzidoro.com.br/public
tar czf /root/manager-files-$(date +%F-%H%M).tar.gz \
  manager/public/tenant_files manager_main/storage manager_main/public/zip_code_crawler_*
```

Copie os dois arquivos para a nova VPS (por exemplo, `scp -3` a partir do Mac).

**Na nova VPS:**

```sh
gunzip -c /root/manager-db-AAAA-MM-DD-HHMM.sql.gz | mariadb

cd /var/www/izzidoro.com.br/public
tar xzf /root/manager-files-AAAA-MM-DD-HHMM.tar.gz
chown -R www-data:www-data manager/public/tenant_files manager_main/storage
```

O dump não leva usuários do MariaDB. O `manager_app` do passo 3 já tem acesso
aos bancos importados.

### Opção B: instalação a partir dos dumps do repositório

Use para um ambiente novo, de teste ou homologação. Os dumps em
`manager_main/public/database/` são retratos do desenvolvimento e **contêm
dados**, inclusive usuários, pessoas e conversas. O `manager_main.sql` começa
com `DROP DATABASE IF EXISTS manager_main`, então nunca rode esse arquivo num
servidor que já tenha dados.

Essa pasta não vai pelo deploy. Envie **do Mac**:

```sh
scp manager_main/public/database/manager_main.sql \
    manager_main/public/database/manager_0001.sql \
    "$VPS:/root/"
```

Envie também os demais `manager_NNNN.sql` que aparecerem na tabela `tenants`.

**Na VPS:**

```sh
mariadb < /root/manager_main.sql
mariadb -e "SELECT id, database_number, status FROM manager_main.tenants"

# Um arquivo para cada database_number listado acima
for n in 0001; do
  mariadb < /root/manager_$n.sql
done
```

Cada dump de tenant também começa com `DROP DATABASE IF EXISTS manager_NNNN`
e cria o próprio banco. Os tenants novos são criados pelo próprio painel, que
usa o `tenant_template.sql`.

#### Acessos que vêm nesses dumps

Os dois sistemas fazem login por e-mail:

| Sistema | Endereço | E-mail | Senha | Perfil |
| --- | --- | --- | --- | --- |
| `manager_main` | `https://izzidoro.com.br/manager_main/public/` | `admin@admin.com` | `123456` | `root` |
| `manager` (tenant) | `https://izzidoro.com.br/manager/public/` | `emma@watson.com` | `12345678` | `admin` do tenant `0001` |

Para o login da Emma funcionar, o banco `manager_0001` precisa ter sido
importado.

> Essas senhas são de desenvolvimento. Se a VPS ficar acessível pela internet,
> troque as duas no primeiro acesso, principalmente a do usuário `root`.

Na Opção A valem os usuários e as senhas que já existem na produção atual.

### Nas duas opções: migrations

Ninguém aplica as migrations automaticamente: nem o deploy, nem o sistema.
Elas ficam em `manager_main/public/database/migrations/`:

- As migrations de tenant (`0.0.N_*.sql`) rodam em cada `manager_NNNN` e são
  registradas em `schema_versions`. Compare
  `SELECT * FROM manager_NNNN.schema_versions` com os arquivos da pasta antes
  de aplicar.
- Os scripts `manager_main_*.sql` rodam só no `manager_main`. O cabeçalho de
  cada um diz se pode ser reaplicado.

Antes de qualquer migration, faça um `mariadb-dump` dos bancos envolvidos.

## 7. Nginx

```sh
cat > /etc/nginx/sites-available/izzidoro.com.br <<'EOF'
map $http_upgrade $connection_upgrade {
    default upgrade;
    ''      close;
}

server {
    listen 80;
    listen [::]:80;
    server_name izzidoro.com.br;

    root /var/www/izzidoro.com.br/public;
    index index.php index.html;

    client_max_body_size 100M;

    access_log /var/log/nginx/izzidoro.com.br.access.log;
    error_log  /var/log/nginx/izzidoro.com.br.error.log;

    # Chat: o navegador abre wss://izzidoro.com.br/ws e o Nginx repassa ao support-chat.
    location = /ws {
        proxy_pass http://127.0.0.1:8081;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection $connection_upgrade;
        proxy_set_header Host $host;
        proxy_read_timeout 1h;
        proxy_send_timeout 1h;
    }

    # Pastas internas dos sistemas: nunca servir.
    location ^~ /manager/app/                  { return 404; }
    location ^~ /manager/tmp/                  { return 404; }
    location ^~ /manager/tests/                { return 404; }
    location ^~ /manager/tools/                { return 404; }
    location ^~ /manager/deploy/               { return 404; }
    location ^~ /manager/storage/              { return 404; }
    location ^~ /manager/docs/                 { return 404; }
    location ^~ /manager_main/app/             { return 404; }
    location ^~ /manager_main/storage/         { return 404; }
    location ^~ /manager_main/tests/           { return 404; }
    location ^~ /manager_main/docs/            { return 404; }
    location ^~ /manager_main/public/database/ { return 404; }

    # manager_assets: libera só os arquivos estáticos; vendor e composer.* ficam bloqueados.
    location ^~ /manager_assets/bootstrap/ {
        location ~* \.(?:css|js|map|svg|png|ico|woff|woff2)$ { try_files $uri =404; }
        return 404;
    }
    location ^~ /manager_assets/fullcalendar/   { location ~* \.js$ { try_files $uri =404; } return 404; }
    location ^~ /manager_assets/autocomplete/   { location ~* \.js$ { try_files $uri =404; } return 404; }
    location ^~ /manager_assets/helpers/        { location ~* \.js$ { try_files $uri =404; } return 404; }
    location ^~ /manager_assets/masks/          { location ~* \.js$ { try_files $uri =404; } return 404; }
    location ^~ /manager_assets/richtexteditor/ { location ~* \.js$ { try_files $uri =404; } return 404; }
    location ^~ /manager_assets/viacep/         { location ~* \.js$ { try_files $uri =404; } return 404; }
    location ^~ /manager_assets/                { return 404; }

    # Remova esta linha se a raiz do domínio já servir outro site.
    location = / { return 302 /manager_main/public/; }

    location = /manager_main/public { return 301 /manager_main/public/; }
    location = /manager/public      { return 301 /manager/public/; }

    location /manager_main/public/ { try_files $uri $uri/ @manager_main; }
    location @manager_main {
        rewrite ^/manager_main/public/(.*)$ /manager_main/public/index.php?url=$1&$args last;
    }

    location /manager/public/ { try_files $uri $uri/ @manager; }
    location @manager {
        rewrite ^/manager/public/(.*)$ /manager/public/index.php?url=$1&$args last;
    }

    location ~ \.php$ {
        try_files $uri =404;
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;   # ajuste para a versão do passo 2
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_read_timeout 300s;
    }

    location ~ /\. { deny all; }
}
EOF

ln -s /etc/nginx/sites-available/izzidoro.com.br /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx
```

Mantenha cada pasta de `manager_assets` como um bloco `^~` próprio, com o
`location` de extensão aninhado dentro dele. Um `^~ /manager_assets/` genérico
impede o Nginx de avaliar expressões regulares depois. Já uma regex solta para
`.js` antes do bloco de PHP deixaria `vendor/*.php` executável.

Confira o socket do PHP-FPM com `ls /run/php/`.

## 8. Chat de suporte (systemd)

O WebSocket é um processo PHP de linha de comando, de longa duração. Ele lê o
código só ao iniciar e encerra quando o banco derruba a conexão. Por isso,
precisa de um supervisor que o reinicie.

```sh
cat > /etc/systemd/system/support-chat.service <<'EOF'
[Unit]
Description=Manager support chat (WebSocket)
After=network.target mariadb.service
Wants=mariadb.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/izzidoro.com.br/public
EnvironmentFile=/etc/manager/manager.env
ExecStart=/usr/bin/php /var/www/izzidoro.com.br/public/manager_main/app/SupportChat/server.php
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable --now support-chat
systemctl status support-chat --no-pager
ss -ltnp | grep 8081        # deve mostrar 127.0.0.1:8081
```

Logs do chat: `journalctl -u support-chat -f`.

## 9. HTTPS

Com o DNS já apontando para a VPS:

```sh
certbot --nginx -d izzidoro.com.br --redirect
certbot renew --dry-run
```

O Certbot acrescenta o bloco `listen 443 ssl` e o redirecionamento de HTTP
para HTTPS no arquivo do passo 7. O `wss://` do chat passa a funcionar junto
com o certificado.

## 10. Verificação

```sh
# Telas de login: devem responder 200
curl -s -o /dev/null -w '%{http_code}\n' 'https://izzidoro.com.br/manager_main/public/index.php?url=user/login'
curl -s -o /dev/null -w '%{http_code}\n' 'https://izzidoro.com.br/manager/public/index.php?url=user/login'

# Estático liberado: 200
curl -s -o /dev/null -w '%{http_code}\n' 'https://izzidoro.com.br/manager_assets/autocomplete/autocomplete.js'

# Arquivos privados: 404
curl -s -o /dev/null -w '%{http_code}\n' 'https://izzidoro.com.br/manager_assets/vendor/autoload.php'
curl -s -o /dev/null -w '%{http_code}\n' 'https://izzidoro.com.br/manager_assets/composer.lock'
curl -s -o /dev/null -w '%{http_code}\n' 'https://izzidoro.com.br/manager/app/Core/Config.php'
curl -s -o /dev/null -w '%{http_code}\n' 'https://izzidoro.com.br/manager_main/public/database/tenant_template.sql'
```

No navegador:

1. Entre no `manager_main` com um usuário `root` e no `manager` com um usuário
   de tenant. Na Opção B, use `admin@admin.com` / `123456` no `manager_main` e
   `emma@watson.com` / `12345678` no `manager` (tabela no
   [passo 6](#acessos-que-vêm-nesses-dumps)).
2. Abra o chat. Em DevTools › Network › WS, a conexão com `/ws` deve
   aparecer com status `101`. Mande uma mensagem entre duas sessões.
3. Envie um arquivo num módulo que aceite anexos. Isso testa
   `client_max_body_size`, `upload_max_filesize` e a escrita em
   `tenant_files`.
4. Opcional: crie um tenant de teste pelo painel. Isso confirma que o
   `manager_app` consegue criar bancos. Exclua-o em seguida.

Se algo der 500, leia o log:

```sh
tail -n 50 /var/log/nginx/izzidoro.com.br.error.log
```

## 11. Deploy automático (GitHub Actions)

Os três repositórios (`manager`, `manager_main`, `manager_assets`) têm um
workflow que publica cada push na `main` por SSH e `rsync`. Para apontá-los
para a nova VPS:

1. Autorize a chave pública de deploy (`~/.ssh/github_deploy.pub`, do Mac) em
   `/root/.ssh/authorized_keys` na VPS.
2. Em cada repositório, em **Settings › Secrets and variables › Actions**,
   atualize `SSH_HOST` com o IP novo. Revise também `SSH_USER`, `SSH_PORT` e
   `SSH_PRIVATE_KEY`.
3. Atualize `.github/deploy/known_hosts` nos três repositórios com a chave de
   host da VPS nova:

   ```sh
   # no Mac
   ssh-keyscan -t ed25519 IP_DA_NOVA_VPS
   # na VPS, confira se a impressão digital bate:
   ssh-keygen -lf /etc/ssh/ssh_host_ed25519_key.pub
   ```

4. Se o domínio ou a pasta mudarem, edite nos três repositórios os caminhos e
   a URL fixos em `.github/scripts/deploy.sh`, `.github/scripts/apply-deploy.sh`
   (destino, URL do health check e `--resolve`) e
   `.github/scripts/validate-deploy.py` (`PUBLIC_ROOT`).
5. Rode o workflow manualmente uma vez (**Actions › Run workflow**) e confira o
   health check.

O que o deploy **não** faz, e precisa ser feito à mão quando a versão pedir:

- aplicar migrations de banco (passo 6);
- reiniciar o chat. Depois de qualquer mudança em
  `manager_main/app/SupportChat/`, rode `systemctl restart support-chat`;
- sincronizar as permissões de telas novas pelo `manager_main` (tela de
  permissões, que chama `PermissionController::sync()`);
- reiniciar o Nginx ou o PHP-FPM;
- publicar `manager_assets` junto com os sistemas (cada repositório tem o seu
  workflow).

## 12. Backup e manutenção

Backup diário dos bancos, com 14 dias de retenção:

```sh
cat > /usr/local/sbin/manager-db-backup <<'EOF'
#!/bin/bash
set -euo pipefail
dir=/var/backups/manager-db
mkdir -p "$dir" && chmod 700 "$dir"
dbs=$(mariadb -N -e "SHOW DATABASES LIKE 'manager\_%'")
mariadb-dump --single-transaction --routines --triggers --events --databases $dbs \
  | gzip > "$dir/manager-$(date +%F-%H%M).sql.gz"
find "$dir" -name 'manager-*.sql.gz' -mtime +14 -delete
EOF
chmod 700 /usr/local/sbin/manager-db-backup
echo '30 3 * * * root /usr/local/sbin/manager-db-backup' > /etc/cron.d/manager-db-backup
/usr/local/sbin/manager-db-backup && ls -lh /var/backups/manager-db
```

Esse backup fica no mesmo disco da VPS. Copie-o regularmente para fora dela,
junto com `manager/public/tenant_files`.

Onde olhar quando algo falhar:

| O quê | Onde |
| --- | --- |
| Erros de PHP e 500 | `/var/log/nginx/izzidoro.com.br.error.log` |
| PHP-FPM | `journalctl -u php*-fpm` e `/var/log/php*-fpm.log` |
| Chat | `journalctl -u support-chat` |
| Backups do deploy | `/var/backups/manager`, `/var/backups/manager_main`, `/var/backups/manager_assets` |

---

## Diferenças em relação ao `manager/README.md`

Este guia segue o código atual, não o README, nestes pontos:

- **Banco:** o README fala em MySQL 8.0, mas os schemas usam colações
  `uca1400`, que só existem no MariaDB ≥ 10.10.
- **Redis:** o README lista Redis e a extensão `redis` como requisitos. O
  código atual não usa Redis; ele só aparece no Docker local.
- **Nginx:** o exemplo do README libera só `bootstrap` e `fullcalendar`. As
  views também carregam `autocomplete`, `masks`, `helpers`, `richtexteditor`
  e `viacep` de `/manager_assets/`, que ficariam bloqueados.
- **Chat:** o README sugere expor a porta `8081`. Aqui o chat fica em
  `127.0.0.1` atrás do Nginx (`wss://<domínio>/ws`), e só as portas 22, 80 e
  443 ficam abertas.


## Deployment

For automatic publication to the existing VPS, see [deployment instructions](docs/deployment.md).

- Apenas para usuários admin e user;
- Este é o cliente tenant para todos os bancos de dados.

## Instalação em servidor web

As configurações Docker usadas no ambiente local ficam no diretório acima da aplicação e servem como referência para produção: PHP-FPM 8.4, Nginx, MySQL 8.0 e Redis 7. No Docker, a pasta `src` é montada em `/var/www/html`, então o servidor web deve enxergar `manager_main` e `manager` lado a lado:

```text
/var/www/html/
  manager_assets/
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

1. Envie os diretórios `manager_assets`, `manager_main` e `manager` para `/var/www/html`.
2. Instale uma única vez as dependências PHP compartilhadas:

```sh
cd /var/www/html/manager_assets
composer install --no-dev --optimize-autoloader
```

Os dois sistemas procuram esse diretório no mesmo nível por padrão. Se a implantação utilizar outro caminho, configure `SHARED_ASSETS_PATH` com o caminho absoluto da pasta `manager_assets`.

3. Garanta permissão de escrita para o usuário do PHP-FPM nas pastas públicas que recebem arquivos por tenant:

```sh
mkdir -p /var/www/html/manager/public/tenant_files
chown -R www-data:www-data /var/www/html/manager/public/tenant_files
chmod -R 775 /var/www/html/manager/public/tenant_files
```

### Configuração do banco de dados e do chat

As credenciais do banco de dados e a chave compartilhada do chat ficam centralizadas no arquivo:

```text
app/Core/Config.php
```

Os dois arquivos `Config.php` mantêm os mesmos blocos de configuração, separados em `production` e `docker`. A variável de ambiente `APP_ENV` seleciona o bloco e tem prioridade sobre a detecção automática, inclusive para executar produção em Docker. Quando ela não está definida, a presença de `/.dockerenv` seleciona `docker`; fora do contêiner, o padrão é `production`. O valor `development` continua aceito como alias de `docker`, para compatibilidade com o Compose atual.

No bloco `docker`, o banco usa o host `mariadb`, o usuário `manager_app` e as credenciais locais existentes. Em `production`, mantém o host `127.0.0.1`, o usuário `root` e as credenciais já configuradas. O chat escuta em `0.0.0.0` no Docker e em `127.0.0.1` na produção. Todas as constantes `DB_HOST`, `DB_PORT`, `DB_DATABASE_MANAGER`, `DB_USERNAME`, `DB_PASSWORD`, `DB_CHARSET`, `SUPPORT_CHAT_SECRET`, `SUPPORT_CHAT_WS_HOST` e `SUPPORT_CHAT_WS_PORT` podem ser substituídas por variáveis de ambiente com os mesmos nomes, lidas por `getenv()`. Mantenha os valores consistentes entre `manager`, `manager_main` e o processo do chat.

Defina as variáveis no ambiente do PHP-FPM e dos processos CLI; este arquivo não carrega `.env` automaticamente. Se preferir editar os padrões no próprio `Config.php`, mantenha os dois arquivos sincronizados. O deploy atual preserva os `Config.php` da VPS, portanto esta atualização precisa ser copiada para eles separadamente.

As demais configurações continuam disponíveis por variáveis de ambiente:

```env
SUPPORT_CHAT_WS_URL=wss://seu-dominio.com/ws
SHARED_ASSETS_PATH=/var/www/html/manager_assets
```

No Docker local, o host do banco é `mariadb`, a porta é `3306` e o Redis fica disponível no host `redis`. Em um servidor tradicional, ajuste as variáveis de ambiente ou os padrões das constantes e a conexão do Redis conforme a infraestrutura instalada.

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

    location ^~ /manager_assets/bootstrap/ {
        location ~* \.(?:css|js|map|svg|png|ico|woff|woff2)$ {
            try_files $uri =404;
        }

        return 404;
    }

    location ^~ /manager_assets/fullcalendar/ {
        location ~* \.js$ {
            try_files $uri =404;
        }

        return 404;
    }

    location ^~ /manager_assets/ {
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

    <Directory /var/www/html/manager_assets>
        Require all denied
    </Directory>

    <Directory /var/www/html/manager_assets/bootstrap>
        Options -Indexes
        AllowOverride None
        Require all granted

        <FilesMatch "(?i)^(?!.*\.(css|js|map|svg|png|ico|woff|woff2)$).*$">
            Require all denied
        </FilesMatch>
    </Directory>

    <Directory /var/www/html/manager_assets/fullcalendar>
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

As abas Suporte e Interno usam o mesmo layout: a conversa à esquerda e a lista
de pessoas à direita, colorida pela presença. O topo da janela mostra a conversa
atual da aba aberta (nome, presença e situação). Abaixo de 768px a lista ocupa a
aba e tocar em alguém abre a conversa, com um botão para voltar no topo.

- Interno: a lista Equipe mostra todos os usuários do tenant, com o total de
  mensagens não lidas. Clicar abre a conversa com a pessoa (ou cria uma, se
  ainda não existir). Conversas internas não são encerradas: o histórico fica
  sempre disponível.
- Suporte: a lista Atendimentos mostra os atendimentos do último mês em ordem de
  data, inclusive os finalizados. O atendimento em andamento usa a cor da
  presença do atendente `root` (`user_presences` em `manager_main`); os
  finalizados ficam em cinza neutro. Clicar no atendimento em andamento abre a
  conversa; clicar num finalizado mostra as mensagens com o campo de texto e o
  botão Enviar desabilitados, sem reabrir o atendimento. Para escrever de novo,
  use o "+" da lista (aparece quando não há atendimento aberto) ou a linha em
  andamento; a próxima mensagem cria um atendimento novo,
  e o servidor escolhe o atendente. Só usuários `root` encerram atendimentos (o
  servidor recusa `close_chat` de outros papéis). Atendimentos encerrados há mais
  de um mês saem da lista e da contagem de não lidas, mas continuam gravados no
  banco. `support_chat/bootstrap` ainda devolve os roots online em `attendants`,
  usado no topo quando não há atendimento aberto.

O botão Chat mostra um badge vermelho com o total de mensagens não lidas do suporte
e das conversas internas no tenant atual. Cada aba mostra sua própria contagem.
Carregar conversas em segundo plano não marca mensagens como lidas: a confirmação
ocorre quando a conversa está aberta e a página está visível. Mensagens de suporte
continuam na contagem quando o atendimento é encerrado, até abri-lo na lista (no máximo por um mês).

Os endpoints POST `internal_chat/seen` e `support_chat/seen` recebem `chat_id` e
`last_message_id`, validam o usuário e o tenant e avançam a leitura até a data da
mensagem exibida. Os campos de leitura existentes têm precisão de segundos.
As consultas retornam as 200 mensagens mais recentes em ordem cronológica.
Para verificar os totais e as confirmações sem usar o banco da aplicação:

```sh
php tests/chat_unread_test.php
```

## Presença no login e logoff

O login só cria a sessão autenticada depois de gravar `online` em `user_presences`.
Quando o limite `max_online_users` do tenant é atingido, o login é recusado e a
tela informa que é necessário aguardar outro usuário sair. Uma falha ao gravar a
presença também impede o login, com uma mensagem genérica para tentar novamente.

Quem ainda precisa escolher um tenant entra como `online` com `tenant_id` nulo.
A seleção e a troca de tenant verificam o limite antes de mudar o contexto;
quando não há vaga, preservam a presença e o tenant anteriores. O limite mantém
o critério existente de contar apenas usuários com status `online`.

O logoff grava `offline` e limpa o `tenant_id` da presença antes de destruir a
sessão. Para verificar esses fluxos sem acessar o banco da aplicação, execute:

```sh
php tests/user_presence_authentication_test.php
```

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
- Antes de criar uma nova biblioteca, verifique se já existe uma biblioteca em `manager_assets/` que possa ser estendida.
- Quando for necessária uma nova biblioteca JavaScript reutilizável, crie-a no diretório compartilhado seguindo o padrão `manager_assets/<nome_da_lib>/<nome_da_lib>.js`.
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
