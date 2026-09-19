# Transparência Jardim Maceió

Portal Financeiro Condominial em **PHP 7.4 + MySQL** — desenvolvido com PHP puro estruturado, sem frameworks. Cobre dashboard, contas a pagar/receber, notas fiscais, balancete, fluxo de caixa, relatórios, gráficos, documentos, moradores e configurações.

---

## ✅ Requisitos

- PHP **7.4+** (compatível com 8.0/8.1/8.2/8.3)
- MySQL **5.7+** ou MariaDB **10.3+**
- Apache (mod_rewrite) ou Nginx
- Extensões PHP: `pdo_mysql`, `mbstring`, `fileinfo`

---

## 🚀 Instalação

### 1. Copie a pasta `jardim_maceio` para a raiz do servidor web

Exemplo:
- XAMPP: `C:\xampp\htdocs\jardim_maceio`
- Linux (Apache): `/var/www/html/jardim_maceio`

### 2. Crie o banco de dados MySQL

Conecte-se ao MySQL e execute:

```sql
CREATE DATABASE jardim_maceio CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'jardim_maceio'@'localhost' IDENTIFIED BY 'jardim_maceio';
GRANT ALL PRIVILEGES ON jardim_maceio.* TO 'jardim_maceio'@'localhost';
FLUSH PRIVILEGES;
```

### 3. Importe a estrutura

```bash
mysql -u jardim_maceio -p jardim_maceio < sql/jardim_maceio.sql
```

Ou via phpMyAdmin: importe `sql/jardim_maceio.sql`.

### 4. Permissões da pasta uploads

```bash
chmod -R 775 uploads
chown -R www-data:www-data uploads   # Linux
```

### 5. Acesse no navegador

```
http://localhost/jardim_maceio/
```

Você será redirecionado para o login.

---

## 🔐 Credenciais padrão

- **E-mail:** `admin@jardimmaceio.com.br`
- **Senha:** `admin123`

> ⚠️ Altere essas credenciais em **Configurações → Usuários** imediatamente após o primeiro acesso.

---

## ⚙️ Configurações

Edite `config/database.php` para mudar credenciais de banco:

```php
private const DB_HOST = 'localhost';
private const DB_NAME = 'jardim_maceio';
private const DB_USER = 'jardim_maceio';
private const DB_PASS = 'jardim_maceio';
```

Em `config/config.php`, em produção, desative os erros:

```php
ini_set('display_errors', 0);
```

---

## 📂 Estrutura

```
jardim_maceio/
├── config/         # config.php, database.php
├── api/            # endpoints REST (auth, dashboard, receitas, despesas...)
├── views/          # telas (dashboard, contas_pagar, login...)
├── includes/       # auth.php, helpers.php, header.php, footer.php
├── assets/         # css, js, img
├── sql/            # jardim_maceio.sql (estrutura completa)
├── uploads/        # arquivos enviados (NF e documentos)
└── index.php       # entrypoint
```

---

## 🎨 Identidade visual

- Verde institucional: `#0F7B3E`
- Verde escuro: `#0a4d27`
- Fonte: **Inter** (Google Fonts)
- Charts: **Chart.js 4.4.0** (via CDN)

---

## 📋 Funcionalidades

| Módulo | Descrição |
|---|---|
| **Dashboard** | 4 KPIs principais, fluxo de caixa, despesas por categoria, movimentações do dia, contas a pagar/receber, resumo financeiro |
| **Contas a Pagar** | CRUD completo de despesas, filtros, status (pendente/pago/vencido) |
| **Contas a Receber** | CRUD de receitas, vinculação a moradores, controle de inadimplência |
| **Notas Fiscais** | Upload de PDF/PNG/JPG/XML, vinculação a despesas |
| **Fluxo de Caixa** | Extrato unificado entradas/saídas com filtro por período |
| **Balancete** | Demonstrativo consolidado mensal (saldo anterior + receitas - despesas) |
| **Gráficos** | 4 gráficos (evolução anual, saldo, distribuição por categoria) |
| **Documentos** | Repositório de balancetes, atas, convenções |
| **Moradores** | Cadastro por bloco/apartamento, proprietário/inquilino |
| **Configurações** | Categorias, fornecedores, usuários (admin/síndico/morador) |
| **Assembleia** | Convocação, pautas, votação online e atas |
| **Visibilidade** | O administrador escolhe, módulo a módulo, o que os condôminos veem; o menu de quem está conectado se ajusta sozinho (AJAX) |
| **Notificações** | Contas vencidas, recebimentos em atraso e avisos no sino; cada usuário limpa as suas (item a item ou tudo) |

---

## 📱 Aplicativo (PWA)

- Instalável no celular e no computador (`manifest.webmanifest`, `sw.js`, ícones em `assets/icons/`)
- Tutorial ilustrado para instalar pelo Safari no iPhone/iPad (iOS 26 e anteriores) — `assets/js/pwa.js`
- **Offline:** telas e dados já vistos continuam disponíveis; cadastros, edições e exclusões feitos sem conexão ficam numa fila no aparelho e são enviados sozinhos quando a conexão volta (com chave de idempotência `X-JM-Idem` — nunca grava em dobro)
- Status real no topo: *Ao vivo*, *Sem internet*, *Servidor fora*, *Entre de novo* (sessão expirada) e quantas alterações estão pendentes
- **Atualização forçada:** ao publicar qualquer arquivo novo, todas as abas abertas recarregam sozinhas (esperam se houver um formulário sendo preenchido)
- Visual premium minimalista, com tabelas que viram cartões e barra de atalhos no celular; zoom desativado

---

## 🔒 Segurança

- Senhas armazenadas com `password_hash` (bcrypt)
- Sessões HTTPOnly, regenerated_id no login
- Prepared statements em **todas** as queries (PDO)
- Escape de saída via `e()` helper
- `.htaccess` bloqueia acesso direto a `/config` e `/includes`
- CSRF token disponível em `helpers.php`

---

## 👤 Perfis de usuário

- **admin** — acesso total + gerenciamento de usuários
- **sindico** — acesso a todas as telas exceto usuários
- **morador** — visualização apenas

---

Desenvolvido para a gestão transparente do Condomínio Jardim Maceió.

---

## 👨‍💻 Desenvolvedor

Projetado e desenvolvido **100% por Alex Junior (alequizao)** — da ideia ao deploy:
levantamento, modelagem do banco, backend, interface e publicação em produção.
Analista e Desenvolvedor de Sistemas em **Maceió, Alagoas**, Brasil. Programador na
**Publish Digital**.

- **E-mail:** alequizao.dev@gmail.com
- **WhatsApp:** [(82) 98871-7072](https://wa.me/5582988717072)
- **Instagram:** [@alequizao](https://instagram.com/alequizao)
- **GitHub:** [@alequizao](https://github.com/alequizao) · [perfil completo](https://github.com/alequizao/alequizao)
- **Site:** [alequizao.com](https://alequizao.com)

---

© Código proprietário, desenvolvido sob encomenda.

---

## 📸 Tela

[![jardim_maceio — sistema desenvolvido por Alex Junior (alequizao)](screenshots/tela-principal.png)](https://publishdev.com.br/jardim_maceio/)
