# Technology Stack

**Analysis Date:** 2026-07-13

## Languages & Runtimes

| Layer | Language | Version | Runtime |
|-------|----------|---------|---------|
| Backend | PHP | ^8.4 | CLI / PHP-FPM 8.5 (Docker) |
| Frontend | TypeScript | 5.9 | Node 24 (dev), Vite 8 (bundler) |
| Styles | CSS | — | Plain CSS (BEM-like), no preprocessor |

## Backend Framework

**Laravel 13.0** (`backend/composer.json`)

| Area | Package | Notes |
|------|---------|-------|
| HTTP | `laravel/framework` ^13.0 | Controllers, middleware, routing, queue, cache, validation |
| Queue | `database` driver | Jobs consume from `jobs` table; `sync` forbidden in production |
| Cache | Laravel Cache | `GitHubService` caches context for 10 min via `sha1(url)` key |
| Database | SQLite (dev) / Postgres or MySQL (prod) | `DB_CONNECTION` env-driven; Turso/libsql unsupported on L13 |
| Logging | Monolog via Laravel | `LOG_LEVEL=debug` warned against in production |
| Testing | PHPUnit 13 | `RefreshDatabase` + seed; `Queue::fake()` for job assertions |
| Linting | Laravel Pint | PSR-12 enforced; CI runs `--test` (fail on violations) |

## Frontend Framework

**React 19 + TypeScript** (`backend/package.json`)

| Area | Package | Notes |
|------|---------|-------|
| UI | React 19.3, `react-dom` 19.2 | Functional components + hooks only |
| Icons | `lucide-react` 0.561 | SVG icon library |
| Bundler | Vite 8 | `laravel-vite-plugin` v3 + `@vitejs/plugin-react` |
| Type checking | `tsc --noEmit` | Strict mode (`tsconfig.json`) |
| Linting | `oxlint` (Rust) | TypeScript + Unicorn + Oxc plugins |
| Formatting | `oxfmt` (Rust) | Ignores `node_modules`, `public`, `vendor` |
| Conventions | `konsistent` | Enforces filename-export conventions (PascalCase components, `use*` hooks) |
| React analysis | `react-doctor` (`npm run doctor`) | Codebase quality analysis |

## Build & Development Tools

| Tool | Purpose | Config |
|------|---------|--------|
| `concurrently` | Runs server + queue + vite + pail in dev | `composer run dev` |
| `vite` | HMR dev server + production build | `vite.config.ts`, port 5173 |
| `prek` (pre-commit) | Git hooks via `.pre-commit-config.yaml` | Runs pint, typecheck, oxlint, oxfmt, konsistent |
| Docker | Production container | Multi-stage: Node 24 build → PHP 8.5 FPM runtime |
| Dokku | VPS deployment | Dockerfile-based; Nginx + PHP-FPM + supervisor |
| Laravel Cloud | Managed hosting | Deploys `backend/` as app root |

## Configuration Files

| File | Purpose |
|------|---------|
| `backend/composer.json` | PHP dependencies, scripts, autoload PSR-4 |
| `backend/package.json` | Node dependencies, build/lint scripts |
| `backend/vite.config.ts` | Vite + Laravel plugin + React plugin |
| `backend/tsconfig.json` | TypeScript strict config (ES2022, bundler module) |
| `.oxlintrc.json` | oxlint plugin rules |
| `.oxfmtrc.json` | oxfmt ignore patterns |
| `.pre-commit-config.yaml` | Pre-commit hook orchestration |
| `backend/phpunit.xml` | PHPUnit test configuration |
| `backend/.env.example` | Environment variable template |

---

*Stack analysis: 2026-07-13*
