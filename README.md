# 🖥️ OSale — Sistema de Gestão para Assistência Técnica

Sistema web desenvolvido como projeto final para gerenciamento de ordens de serviço, estoque de peças e cadastro de equipamentos voltado a assistências técnicas de informática e consoles de videogame.

---

## 📋 Sobre o Projeto

O **OSale** nasceu da necessidade real de organizar o fluxo de trabalho de uma assistência técnica que atende notebooks, desktops e consoles (PlayStation 4/5, Xbox One/Series S). O sistema centraliza o controle de:

- **Ordens de Serviço (OS)** — abertura, acompanhamento e encerramento de chamados
- **Estoque de Peças** — entradas, saídas e alertas de estoque mínimo
- **Cadastro de Equipamentos** — histórico de manutenções por dispositivo
- **Cadastro de Clientes** — dados e histórico de atendimentos
- **Serviços Realizados** — formatação, instalação de SSD, troca de tela/teclado, reparo de placa-mãe, troca de conectores, limpeza e troca de pasta térmica, reparo em controles de videogame, entre outros

---

## 🛠️ Stack Tecnológica

### Backend

| Tecnologia | Versão | Uso |
|---|---|---|
| PHP | 8.x | Linguagem principal do backend |
| Slim Framework | ^4.15 | Roteamento e estrutura da aplicação |
| Slim PSR-7 | ^1.8 | Abstração de requisições/respostas HTTP |
| Slim Twig View | ^3.4 | Renderização de templates no servidor |
| Doctrine DBAL | ^4.4 | Camada de abstração do banco de dados |
| Doctrine Migrations | ^3.9 | Versionamento e migração do schema |
| PHP DotEnv | ^5.6 | Gerenciamento de variáveis de ambiente |
| Firebase PHP-JWT | ^7.0 | Autenticação via JSON Web Tokens |
| League OAuth2 Client | ^2.9 | Autenticação OAuth2 |
| League OAuth2 Google | ^5.0 | Login social com Google |

### Frontend

| Tecnologia | Versão | Uso |
|---|---|---|
| JavaScript / HTML | — | Linguagens base do frontend |
| Bootstrap | 5.3.8 | Framework CSS responsivo |
| Bootstrap Icons | ^1.13.1 | Ícones do sistema |
| Font Awesome | 7.2.0 | Ícones complementares |
| jQuery | 3.7.1 | Manipulação do DOM e requisições Ajax |
| DataTables (BS5) | 2.3.8 | Tabelas com paginação, busca e ordenação |
| DataTables Responsive | 3.0.8 | Responsividade nas tabelas |
| DataTables StateRestore | 1.4.3 | Persistência de estado nas tabelas |
| Select2 | 4.1.0-rc.0 | Campos de seleção avançados |
| Select2 Bootstrap 5 | 1.3.0 | Tema Bootstrap para o Select2 |
| Flatpickr | 4.6.13 | Seletor de datas |
| Inputmask | 5.0.9 | Máscaras de entrada (CPF, telefone, etc.) |
| jQuery Validation | 1.21.0 | Validação de formulários no cliente |
| SweetAlert2 | 11.26.24 | Modais e alertas interativos |
| Vite | — | Bundler e servidor de desenvolvimento |

### Banco de Dados

- **PostgreSQL 18.3** — banco de dados relacional principal
- Acesso via **Doctrine DBAL** (sem ORM completo, SQL direto com abstração)
- Versionamento de schema via **Doctrine Migrations**

### Infraestrutura

- **Docker** + **Docker Compose** — ambiente containerizado e reproduzível
- **Nginx 1.30** — servidor web e proxy reverso
- **PHP-FPM** — gerenciador de processos PHP
- **Redis 8.6** — sessões e cache de aplicação

---

## 🏗️ Arquitetura

```
                    ┌──────────────┐
        HTTP :80    │    Nginx     │   Proxy reverso + arquivos estáticos
  ──────────────────►  (Alpine)    │
                    └──────┬───────┘
                           │ FastCGI :9000
                    ┌──────▼───────┐
                    │   PHP-FPM    │   Slim 4 · Twig · Doctrine
                    │  (app_php)   │
                    └──┬───────┬───┘
                       │       │
          ┌────────────▼──┐  ┌─▼────────────┐
          │  PostgreSQL   │  │    Redis      │
          │  (app_postgres│  │  (app_redis)  │
          │    port 5432) │  │   port 6379)  │
          └───────────────┘  └───────────────┘
```

As páginas HTML são renderizadas **no servidor** pelo Slim + Twig View e entregues prontas ao browser. O Vite gerencia o pipeline de assets (JS/CSS), gerando os bundles otimizados que o Nginx serve como arquivos estáticos.

---

## 📁 Estrutura de Diretórios

```
/
├── docker/
│   ├── nginx/
│   │   └── default.conf          # Configuração do virtual host
│   ├── php/
│   │   └── Dockerfile            # Imagem PHP customizada
│   ├── postgres/
│   │   ├── init.sql              # Script de inicialização do banco
│   │   └── storage/              # Dados persistidos do PostgreSQL
│   └── redis/                    # Dados persistidos do Redis
├── public/                       # Document root (index.php + assets compilados)
├── src/
│   ├── Controllers/              # Controllers das rotas
│   ├── Repositories/             # Acesso ao banco via DBAL
│   ├── Services/                 # Regras de negócio
│   ├── Middleware/               # Autenticação, CSRF, etc.
│   └── Routes/                   # Definição de rotas Slim
├── templates/                    # Templates Twig
├── migrations/                   # Migrations do Doctrine
├── resources/
│   ├── js/                       # Fontes JavaScript (Vite)
│   └── css/                      # Fontes CSS/SCSS (Vite)
├── .env.example                  # Exemplo de variáveis de ambiente
├── composer.json
├── package.json
├── vite.config.js
└── docker-compose.yml
```

---

## ⚙️ Configuração do Ambiente

### Pré-requisitos

- [Docker](https://docs.docker.com/get-docker/) 24+
- [Docker Compose](https://docs.docker.com/compose/) v2+
- [Node.js](https://nodejs.org/) 20+ e npm (para o Vite em desenvolvimento)

### Passo a passo

**1. Clone o repositório**

```bash
git clone https://github.com/seu-usuario/techstock.git
cd techstock
```

**2. Copie e configure as variáveis de ambiente**

```bash
cp .env.example .env
```

Edite o `.env` com suas configurações:

```dotenv
TZ=America/Rio_Branco
APP_ENV=development
APP_DEBUG=true

DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_NAME=techstock
DB_USER=techstock_user
DB_PASSWORD=sua_senha_segura

REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=sua_senha_redis
REDIS_DATABASE=1
REDIS_PREFIX=SESS:
REDIS_TIMEOUT=2.5
REDIS_READ_TIMEOUT=2.5
REDIS_PERSISTENT=1

XDEBUG_MODE=off
OPCACHE_VALIDATE_TIMESTAMPS=1
```

**3. Instale as dependências Node e compile os assets**

```bash
npm install
npm run build
```

> Em desenvolvimento use `npm run dev` para o servidor Vite com HMR.

**4. Suba os containers**

```bash
docker compose up -d --build
```

O container PHP já executa `composer install` automaticamente na inicialização.

**5. Execute as migrations**

```bash
docker compose exec php vendor/bin/doctrine-migrations migrate --no-interaction
```

**6. Acesse a aplicação**

Abra [http://localhost](http://localhost) no navegador.

---

## 🗄️ Banco de Dados

As migrations ficam em `migrations/` e são gerenciadas pelo Doctrine Migrations. Para criar uma nova migration:

```bash
docker compose exec php vendor/bin/doctrine-migrations generate
```

Para visualizar o status das migrations:

```bash
docker compose exec php vendor/bin/doctrine-migrations status
```

---

## 🔧 Comandos Úteis

```bash
# Logs em tempo real de todos os serviços
docker compose logs -f

# Logs de um serviço específico
docker compose logs -f php

# Acessar o shell do container PHP
docker compose exec php sh

# Acessar o PostgreSQL via psql
docker compose exec postgres psql -U techstock_user -d techstock

# Acessar o Redis CLI
docker compose exec redis redis-cli -a $REDIS_PASSWORD

# Reconstruir apenas o container PHP
docker compose up -d --build php

# Parar e remover containers (mantém volumes)
docker compose down

# Parar e remover tudo, incluindo volumes
docker compose down -v
```

---

## 🩺 Healthchecks

Todos os serviços possuem healthcheck configurado:

| Serviço | Verificação | Intervalo |
|---|---|---|
| Nginx | `wget` na raiz (`/`) | 30s |
| PHP-FPM | `nc -z localhost 9000` | 30s |
| PostgreSQL | `pg_isready` | 30s |
| Redis | `redis-cli ping` | 5s |

O PHP-FPM só sobe após o PostgreSQL estar saudável (`condition: service_healthy`).

---

## 🔒 Autenticação

O sistema suporta dois métodos de autenticação:

- **JWT** — tokens gerados localmente via `firebase/php-jwt`
- **OAuth2 Google** — login social via `league/oauth2-google`

---

## 📦 Recursos de Hardware Atendidos

O sistema foi projetado para cobrir os serviços prestados pela assistência técnica:

**Informática**
- Notebooks e Desktops em geral
- Instalação de SSD e upgrades de memória
- Formatação e instalação de sistema operacional
- Troca de tela e teclado em notebooks
- Reparo eletrônico em placa-mãe
- Troca de conectores (USB, power, DC jack)
- Limpeza interna e troca de pasta térmica

**Consoles**
- PlayStation 4 e PlayStation 5
- Xbox One e Xbox Series S
- Reparo em controles (joysticks, botões, conectores)
- Limpeza interna e troca de pasta térmica

---

## 🤝 Contribuição

Este é um projeto final acadêmico com aplicação prática real. Sugestões e melhorias são bem-vindas via Issues e Pull Requests.

---

## 📄 Licença

Este projeto está sob a licença MIT. Consulte o arquivo [LICENSE](LICENSE) para mais detalhes.
