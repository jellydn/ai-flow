# Technology Stack

**Analysis Date:** 2026-07-13

> Scope: `backend/` monorepo — Laravel 13 API + jobs + React/TypeScript SPA served by Laravel via Vite.

## Languages & Runtime

| Layer | Language | Version |
|-------|----------|---------|
| Backend | PHP | 8.4+ |
| Frontend | TypeScript | Strict mode (`tsconfig.json`) |
| Runtime | Node.js | 20 (bookworm-slim for Docker) |

## Frameworks

| Framework | Purpose |
|-----------|---------|
| **Laravel** 13 | API, queue, caching, routing, Blade SPA shell |
| **React** 19 | SPA UI (`backend/resources/ts/`) |
| **Vite** | Frontend build tool (`laravel-vite-plugin` + `@vitejs/plugin-react`) |
| **PHPUnit** 13 | Backend testing |

## Key Dependencies

### PHP (`composer.json`, `backend/composer.lock`)

| Package | Purpose |
|---------|---------|
| `laravel/framework` 13.x | Core framework |
| `laravel/pint` ^1.24 | Code style (PSR-12 + Laravel preset) |
| `phpunit/phpunit` 13 | Test framework |
| `guzzlehttp/guzzle` | HTTP client (GitHub API, OpenAI API) |

### Node (`package.json`, `backend/package-lock.json`)

| Package | Purpose |
|---------|---------|
| `react` / `react-dom` 19 | SPA UI |
| `lucide-react` | Icons |
| `vite` | Build tool |
| `@vitejs/plugin-react` | React Fast Refresh in Vite |
| `laravel-vite-plugin` | Laravel Vite integration |
| `typescript` | Type checker (`tsc --noEmit`) |
| `oxlint` / `oxfmt` | Linting and formatting (Rust-based, no ESLint/Prettier) |
| `konsistent` | Structural TypeScript convention checks |

## Configuration

| File | Purpose |
|------|---------|
| `backend/config/app.php` | App name, env, debug, URL |
| `backend/config/database.php` | DB connections (sqlite, pgsql, libsql stub) |
| `backend/config/queue.php` | Queue driver (default: database) |
| `backend/config/services.php` | OpenAI config (key, model, base URL, timeout) |
| `backend/config/cors.php` | CORS origins, methods, headers |
| `backend/config/cache.php` | Cache store |
| `backend/config/logging.php` | Log channels (default: stderr in production) |
| `backend/.env.example` | Environment template |
| `backend/tsconfig.json` | TypeScript strict config |
| `backend/vite.config.ts` | Vite build config |

## Build & Deploy

| Tool | Purpose |
|------|---------|
| **Vite** | Builds React/TS → `public/build/` |
| **Docker** | Multi-stage build: Node 20 frontend → PHP 8.4-fpm + nginx + supervisor |
| **Dokku** | PaaS deployment (`backend/Dockerfile`, `backend/Procfile`, `backend/app.json`) |
| **Laravel Cloud** | Alternative PaaS deployment (`backend/CLOUD_DEPLOY.md`) |
| **GitHub Actions** | CI (`.github/workflows/ci.yml`) + staging deploy (`.github/workflows/deploy-staging.yml`) |

## Databases

| Environment | Database |
|-------------|----------|
| Local dev | SQLite (`database/database.sqlite`) |
| Production (Cloud) | Neon PostgreSQL (`pgsql`, TLS required) |
| Production (Dokku) | Dokku Postgres plugin (internal Docker network) |

## Caching & Queues

| Service | Default | Production |
|---------|---------|------------|
| Cache | File | Database |
| Queue | Database | Database (never `sync`) |
| Session | File | File/cookie |

---

*Stack analysis: 2026-07-13*
