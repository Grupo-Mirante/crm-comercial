# Manual de instalação — CRM Comercial · Grupo Mirante

Este manual leva o CRM do arquivo `.zip` até a primeira tela de login, em **9 etapas**. Foi escrito para quem **não é desenvolvedor**: cada etapa diz o que fazer, onde clicar ou o que digitar, e como saber se deu certo.

> **Tempo estimado:** 40 minutos a 1 hora e meia, dependendo da hospedagem.
> **Quem deve fazer:** a pessoa responsável pelo servidor (TI interna ou empresa de hospedagem), acompanhada por alguém do comercial na etapa 6.

---

## Antes de começar: palavras que aparecem neste manual

| Palavra | O que significa |
|---|---|
| **Servidor** | O computador (na empresa ou numa hospedagem) onde o CRM vai rodar. |
| **Painel da hospedagem** | O site de administração do servidor (cPanel, Plesk, DirectAdmin, CyberPanel…). |
| **Terminal (SSH)** | Uma janela onde se digitam comandos no servidor. Nem toda hospedagem oferece; quando não houver, use o caminho "pelo painel". |
| **Pasta pública** | A pasta `public` do CRM. É a **única** que pode ficar visível na internet. |
| **Banco de dados** | Onde ficam guardadas as PIs, os clientes e os usuários (MySQL). |
| **`.env`** | Arquivo de configuração com o endereço do sistema e a senha do banco. Nunca deve ser enviado por e-mail ou publicado. |
| **Cron** | O "despertador" do servidor: roda as rotinas automáticas do CRM a cada 5 minutos. |

Nos comandos deste manual, troque o que estiver em `MAIÚSCULAS` pelos seus dados. Exemplo: `crm.SEUDOMINIO.com.br` vira `crm.grupomirante.com.br`.

---

## Etapa 1 · O que você vai precisar

- [ ] Um **servidor Linux** com Apache ou Nginx (hospedagem com cPanel ou um servidor próprio/VPS com Ubuntu 22.04 ou 24.04).
- [ ] **PHP 8.5** (veja a etapa 2).
- [ ] **MySQL 8.0 ou mais novo**.
- [ ] Um **endereço** para o sistema, por exemplo `crm.grupomirante.com.br`, já apontado para o servidor (registro DNS do tipo A).
- [ ] **Certificado HTTPS** (cadeado no navegador). Em cPanel costuma ser automático ("AutoSSL"); em VPS, use o Certbot (etapa 3).
- [ ] O arquivo **`crm-grupo-mirante-1.0.0.zip`**.
- [ ] Acesso ao **painel da hospedagem** ou ao **terminal (SSH)** do servidor.

---

## Etapa 2 · Conferir o servidor

O CRM precisa do **PHP 8.5** com estas extensões ativas:

`pdo_mysql · mbstring · json · zip · gd · dom · xml · xmlreader · xmlwriter · simplexml · iconv · ctype · fileinfo · openssl · zlib` e suporte a senhas **Argon2id**.

Não precisa decorar: o instalador (etapa 5) confere tudo e mostra em vermelho o que faltar.

### Pelo painel (cPanel)
1. Abra **Selecionar versão do PHP** (ou **MultiPHP Manager**).
2. Escolha **PHP 8.5** para o domínio do CRM.
3. Na lista de extensões, marque as que aparecem acima e salve.

### Pelo terminal (VPS com Ubuntu)
Confira a versão:
```bash
php -v
```
A primeira linha deve começar com `PHP 8.5`. Se não começar, instale (Ubuntu):
```bash
sudo add-apt-repository ppa:ondrej/php -y
```
```bash
sudo apt update && sudo apt install -y php8.5-fpm php8.5-cli php8.5-mysql php8.5-mbstring php8.5-xml php8.5-zip php8.5-gd
```
Confira o MySQL:
```bash
mysql --version
```
Deve mostrar `8.0` ou maior. Para instalar no Ubuntu: `sudo apt install -y mysql-server`.

**Deu certo se:** `php -v` mostra 8.5 e `mysql --version` mostra 8.x.

---

## Etapa 3 · Enviar os arquivos e apontar o site para a pasta `public`

> **Regra de ouro de segurança:** o endereço do CRM deve abrir **somente a pasta `public`**. As outras pastas (`app`, `storage`, `.env`…) ficam fora do alcance do navegador.

### Pelo painel (cPanel)
1. Abra o **Gerenciador de arquivos**.
2. Vá até a sua pasta pessoal (ex.: `/home/USUARIO`) — **não** use `public_html`.
3. Clique em **Carregar** e envie `crm-grupo-mirante-1.0.0.zip`.
4. Clique com o botão direito no arquivo → **Extrair**. Vai surgir a pasta `crm-grupo-mirante`.
5. Abra **Domínios** (ou **Subdomínios**), crie `crm.SEUDOMINIO.com.br` e, em **Raiz do documento (Document Root)**, informe:
   `/home/USUARIO/crm-grupo-mirante/public`
6. Em **SSL/TLS Status**, confirme que o domínio tem certificado (AutoSSL).

### Pelo terminal (VPS)
Envie o zip para o servidor (por exemplo com o FileZilla para `/tmp`) e rode:
```bash
sudo mkdir -p /var/www && cd /var/www && sudo unzip /tmp/crm-grupo-mirante-1.0.0.zip
```
```bash
sudo chown -R www-data:www-data /var/www/crm-grupo-mirante/storage /var/www/crm-grupo-mirante
```
```bash
sudo find /var/www/crm-grupo-mirante -type d -exec chmod 750 {} \; && sudo find /var/www/crm-grupo-mirante -type f -exec chmod 640 {} \;
```

**Apache** — crie `/etc/apache2/sites-available/crm.conf`:
```apache
<VirtualHost *:80>
    ServerName crm.SEUDOMINIO.com.br
    DocumentRoot /var/www/crm-grupo-mirante/public
    <Directory /var/www/crm-grupo-mirante/public>
        AllowOverride All
        Require all granted
    </Directory>
    ErrorLog ${APACHE_LOG_DIR}/crm-erro.log
    CustomLog ${APACHE_LOG_DIR}/crm-acesso.log combined
</VirtualHost>
```
Ative e reinicie:
```bash
sudo a2enmod rewrite headers proxy_fcgi setenvif && sudo a2enconf php8.5-fpm && sudo a2ensite crm && sudo systemctl reload apache2
```

**Nginx** — crie `/etc/nginx/sites-available/crm`:
```nginx
server {
    listen 80;
    server_name crm.SEUDOMINIO.com.br;
    root /var/www/crm-grupo-mirante/public;
    index index.php;
    client_max_body_size 12M;

    location ~ /\.  { deny all; }
    location / { try_files $uri /index.php?$query_string; }
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.5-fpm.sock;
    }
}
```
Ative e reinicie:
```bash
sudo ln -s /etc/nginx/sites-available/crm /etc/nginx/sites-enabled/ && sudo nginx -t && sudo systemctl reload nginx
```

**HTTPS (cadeado) no VPS** — com o Certbot:
```bash
sudo apt install -y certbot python3-certbot-apache && sudo certbot --apache -d crm.SEUDOMINIO.com.br
```
(no Nginx, troque `python3-certbot-apache` por `python3-certbot-nginx` e `--apache` por `--nginx`).

**Deu certo se:** ao abrir `https://crm.SEUDOMINIO.com.br` aparece a tela **"Instalação pendente"** com o botão **Abrir o instalador**.

---

## Etapa 4 · Criar o banco de dados e o usuário do MySQL

O CRM precisa de um banco **vazio** e de um usuário **só dele** (não use o usuário `root`).

### Pelo painel (cPanel)
1. Abra **Bancos de dados MySQL** (ou **Assistente de banco de dados MySQL**).
2. Crie o banco `crm_grupo_mirante` (o cPanel pode acrescentar um prefixo, ex.: `usuario_crm_grupo_mirante` — anote o nome completo).
3. Crie um usuário (ex.: `crm_mirante`) com uma **senha forte** (use o gerador de senha do painel) e anote.
4. Adicione o usuário ao banco marcando **TODOS OS PRIVILÉGIOS**.

### Pelo terminal
```bash
sudo mysql
```
Dentro do MySQL, digite (troque a senha):
```sql
CREATE DATABASE crm_grupo_mirante CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
CREATE USER 'crm_mirante'@'localhost' IDENTIFIED BY 'TROQUE-POR-UMA-SENHA-FORTE';
GRANT ALL PRIVILEGES ON crm_grupo_mirante.* TO 'crm_mirante'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

**Anote:** servidor do MySQL (quase sempre `localhost` ou `127.0.0.1`), porta (`3306`), nome do banco, usuário e senha. Você vai digitar isso na próxima etapa.

---

## Etapa 5 · Executar o instalador

Escolha **um** dos dois caminhos. Os dois fazem exatamente o mesmo:
1. conferem o servidor;
2. conectam ao banco;
3. gravam o `.env`;
4. criam as 49 tabelas com a tabela de preços 2026, os perfis, o checklist da Conferência e as cláusulas;
5. criam o primeiro administrador;
6. travam o instalador.

> O CRM **não tem usuário ou senha padrão** e a instalação de produção **começa sem nenhum dado fictício**.

### Caminho A · Pelo navegador (recomendado)
1. Abra `https://crm.SEUDOMINIO.com.br/instalar`.
2. **Etapa 1 de 3 — conferência do servidor.** Tudo deve estar com ✓ verde. Itens em vermelho impedem a instalação: volte à etapa 2. Itens em amarelo são recomendações (por exemplo, `mysqldump`, usado no backup).
3. **Código de instalação.** Por segurança, o instalador pede um código que só existe dentro do servidor. Abra o arquivo
   `crm-grupo-mirante/storage/codigo-instalacao.txt` pelo **Gerenciador de arquivos** (ou pelo terminal: `cat storage/codigo-instalacao.txt`), copie o código (ex.: `ABCDE-FGHJK-LMNPQ`) e cole na tela.
4. **Etapa 2 de 3 — dados.**
   - **Banco de dados:** preencha com o que você anotou na etapa 4 e clique em **Testar conexão**. Deve aparecer "Conexão funcionando".
   - **Endereço do sistema:** confira o endereço (ex.: `https://crm.grupomirante.com.br`, sem barra no final) e deixe **Produção**.
   - **Primeiro administrador:** nome, e-mail, um usuário **pessoal** (ex.: `maria.silva` — "admin" não é aceito) e uma **senha provisória** com no mínimo 12 caracteres, letras e números.
5. Clique em **Instalar**. Em alguns segundos aparece **"CRM instalado"** com a lista do que foi feito.

### Caminho B · Pelo terminal
Na pasta do CRM:
```bash
cd /var/www/crm-grupo-mirante && sudo -u www-data php instalar.php
```
Responda às perguntas (Enter aceita o valor entre colchetes). A senha não aparece enquanto você digita — isso é normal.

> Se você rodar o instalador com outro usuário que não o do servidor web, ajuste o dono das pastas depois:
> `sudo chown -R www-data:www-data /var/www/crm-grupo-mirante/storage /var/www/crm-grupo-mirante/.env`

**Deu certo se:** `https://crm.SEUDOMINIO.com.br` mostra a tela de **login** e `https://crm.SEUDOMINIO.com.br/instalar` mostra **"Página não encontrada"** (o instalador se trancou).

> **Se algo der errado no meio**, nada é perdido: corrija o que a mensagem indicar e clique em **Instalar** de novo. O instalador continua de onde parou e **nunca apaga** um banco que já tenha dados.

---

## Etapa 6 · Primeiro acesso e cadastros iniciais

1. Entre com o usuário e a senha provisória do administrador. O sistema pede uma **senha pessoal** (mínimo de 12 caracteres para administradores). Assim, quem instalou não conhece a senha definitiva.
2. **Gestão → Usuários:** cadastre primeiro os **gerentes** e o **diretor**, depois a **Conferência/OPEC**, o **Financeiro** e os **executivos** (cada executivo com o seu gestor e a sua matrícula). Ao salvar, o sistema mostra **uma única vez** a senha provisória da pessoa: entregue-a pessoalmente.
3. **Gestão → Configurações:** confira os prazos (SLA), o tamanho mínimo de senha e, quando a diretoria definir, as **alçadas** de desconto e bonificação (vazias = tudo vai ao gestor).
4. **Gestão → Tabelas e veículos:** confira os dados das **empresas emissoras** (CNPJ e endereço que saem na PI) e os feriados.
5. **E-mail (opcional):** para o CRM enviar e-mails (recuperação de senha, avisos), preencha no `.env` as linhas `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD` e `MAIL_FROM_ADDRESS` com os dados do e-mail da empresa. Sem isso, os avisos ficam dentro do CRM (sino no topo) e a senha esquecida é redefinida pelo administrador.

---

## Etapa 7 · Agendar as rotinas automáticas (cron)

As rotinas expiram reservas de 24 h, enviam os avisos de prazo (SLA), montam a fila diária da Conferência e fazem o **backup diário**. Devem rodar **a cada 5 minutos**.

Descubra o caminho do PHP:
```bash
which php
```
(ex.: `/usr/bin/php`; em cPanel costuma ser `/usr/local/bin/php` ou `/opt/cpanel/ea-php85/root/usr/bin/php`).

### Pelo painel (cPanel)
**Cron Jobs** → configuração comum **"A cada cinco minutos"** → comando:
```bash
cd /home/USUARIO/crm-grupo-mirante && /CAMINHO/DO/php bin/console.php rotinas >> storage/logs/cron.log 2>&1
```

### Pelo terminal
```bash
sudo crontab -u www-data -e
```
Acrescente a linha e salve:
```bash
*/5 * * * * cd /var/www/crm-grupo-mirante && /usr/bin/php bin/console.php rotinas >> storage/logs/cron.log 2>&1
```

**Deu certo se:** depois de 5 minutos o arquivo `storage/logs/cron.log` mostra linhas como `reservas_expiradas: 0 · …`, e a tela **Gestão → Backup** mostra a data do último backup (o backup diário sai na primeira execução depois das 2h da manhã; se você instalou depois das 2h, ele já sai na primeira rodada do cron).

---

## Etapa 8 · Backup e restauração

**O que é guardado:** o banco (todas as PIs, clientes, usuários, histórico) e, à parte, os anexos.

- **Automático:** um backup do banco por dia em `storage/backups/crm_AAAAMMDD_HHMMSS.sql.gz`, mantidos por 30 dias (`BACKUP_RETENCAO_DIAS` no `.env`).
- **Na hora:** **Gestão → Backup → Backup do banco agora** (e **Backup dos anexos**). Os arquivos podem ser baixados pela mesma tela.
- **Fora do servidor (importante):** pelo menos uma vez por semana, **baixe** o backup mais recente e guarde em outro lugar (nuvem da empresa ou disco externo). Um backup que só existe no servidor não protege contra a perda do servidor.

Pelo terminal:
```bash
php bin/console.php backup --anexos
```

### Restaurar (voltar o banco para um backup)
> Isso **substitui** todo o conteúdo atual do banco. Faça só com orientação do responsável.

```bash
cd /var/www/crm-grupo-mirante && php bin/console.php restaurar
```
O comando lista os backups. Rode de novo com o nome escolhido:
```bash
php bin/console.php restaurar crm_AAAAMMDD_HHMMSS.sql.gz
```
Digite `RESTAURAR` para confirmar. Antes de restaurar, o sistema faz automaticamente um backup do estado atual.

Para restaurar os **anexos**: extraia o `crm_anexos_….tar.gz` dentro da pasta `storage` (`tar -xzf storage/backups/crm_anexos_AAAAMMDD_HHMMSS.tar.gz -C storage`).

---

## Etapa 9 · Atualizações e problemas comuns

### Atualizar para uma versão nova
1. Faça um backup (etapa 8) e baixe-o.
2. Envie a nova versão e substitua **todos os arquivos**, **exceto** o `.env` e a pasta `storage`.
3. Rode:
```bash
php bin/console.php migrar
```
4. Abra o CRM e confira o login.

### Problemas comuns

| O que aparece | O que fazer |
|---|---|
| "Instalação pendente" depois de instalar | O arquivo `storage/instalado.lock` não existe ou não pode ser lido. Confira as permissões da pasta `storage` (etapa 3). |
| Página em branco ou "Ocorreu um erro inesperado. Informe o código XXXXXXXX" | Abra `storage/logs/app-AAAA-MM-DD.log` e procure o código. Erros de permissão: rode o `chown` da etapa 3. |
| Erro 404 em todas as páginas, menos na inicial | No Apache, falta `AllowOverride All` ou o módulo `rewrite`. No Nginx, falta a linha `try_files` (etapa 3). |
| O instalador mostra item vermelho | Ative a extensão indicada (etapa 2) e recarregue a página. |
| "Usuário ou senha do MySQL incorretos" | Confira usuário e senha da etapa 4 (no cPanel, o nome do usuário costuma ter prefixo). |
| Backup "mysqldump não encontrado" | Informe o caminho no `.env`: `MYSQLDUMP_PATH=/usr/bin/mysqldump` e `MYSQL_PATH=/usr/bin/mysql`. |
| Ninguém consegue entrar (administrador esqueceu a senha) | No terminal, crie outro administrador: `php bin/console.php criar-admin`. Depois, pela tela, redefina a senha do antigo. |
| Usuário bloqueado por senhas erradas | Aguarde alguns segundos e tente de novo; o administrador pode redefinir a senha em **Gestão → Usuários**. |
| Anexo maior que o permitido | Ajuste `UPLOAD_MAX_MB` no `.env` e os limites `upload_max_filesize` e `post_max_size` do PHP (e `client_max_body_size` no Nginx). |

---

**Pronto.** Use o **Checklist de implantação** (`docs/CHECKLIST-DE-IMPLANTACAO.md`) para conferir tudo antes de liberar o CRM para a equipe.
