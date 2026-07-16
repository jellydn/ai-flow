# Technology Stack

## Languages & Runtime

| Layer | Technology | Version |
|-------|-----------|---------|
| Backend | **PHP** | 8.4+ |
| Frontend | **TypeScript** | 5.9.3 |
| Frontend UI | **React** | 19.2.7 |
| Runtime | **Node.js** | 24 (CI) |

## Backend Framework

| Package | Version | Purpose |
|---------|---------|---------|
| `laravel/framework` | ^13.0 | HTTP, queue, Eloquent ORM, auth, validation |
| `filament/filament` | ^5.0 | Admin panel (super admin for users & launchers) |
| `laravel/tinker` | ^3.0 | REPL for debugging |
| `resend/resend-php` | ^1.5 | Transactional email (magic links, password auth) |
| `sentry/sentry-laravel` | ^4.26 | Error tracking & monitoring |

## Frontend Tooling

| Package | Version | Purpose |
|---------|---------|---------|
| `vite` | 8.1.4 | Build tool & dev server |
| `@vitejs/plugin-react` | 6.0.3 | React Fast Refresh |
| `laravel-vite-plugin` | 3.1.3 | Laravel → Vite manifest integration |
| `typescript` | 5.9.3 | Type checking (`strict` mode) |
| `oxlint` | ^1.73.0 | Linting (replaces ESLint) |
| `oxfmt` | ^0.59.0 | Formatting (replaces Prettier) |
| `konsistent` | ^1.0.0-beta.3 | Structural TS conventions |

## Frontend Libraries

| Package | Version | Purpose |
|---------|---------|---------|
| `react` / `react-dom` | 19.2.7 | UI framework |
| `lucide-react` | 1.24.0 (pinned) | Icon library |
| `react-markdown` | ^10.1.0 | Markdown rendering in run reports |
| `remark-gfm` | ^4.0.1 | GitHub Flavored Markdown support |
| `@sentry/react` | ^10.65.0 | Client-side error tracking |
| `consola` | ^3.4.2 | Structured logging |

## Development & Quality

| Tool | Purpose |
|------|---------|
| `laravel/pint` (^1.24) | PHP code style (PSR-12) |
| `phpunit/phpunit` (^13.0) | Backend test framework |
| `vitest` (^4.1.10) | Frontend test framework |
| `@playwright/test` (^1.61.1) | E2E browser tests |
| `@testing-library/react` (^16.3.2) | React component testing |
| `jsdom` (^29.1.1) | DOM environment for Vitest |
| `concurrently` (10.0.3) | Run dev servers in parallel |
| `prek` / `.pre-commit-config.yaml` | Git hooks |

## Database

| Environment | Driver | Notes |
|-------------|--------|-------|
| Development | **SQLite** (`database/database.sqlite`) | Zero-config local dev |
| Production | **PostgreSQL** or **MySQL** | Managed (Neon, Dokku Postgres) |
| Queue | **Database** driver | Jobs table, never `sync` in production |
| Cache | **File** or **Redis** (`phpredis` client) | Configurable per env |

## AI Providers

| Provider | Adapter Class | Config Key |
|----------|--------------|------------|
| OpenAI | `OpenAIProvider` | `services.openai.key` |
| OpenRouter | `OpenRouterProvider` | `services.openai.openrouter_key` |
| Anthropic | `AnthropicProvider` | `services.anthropic.key` |
| Google Gemini | `GeminiProvider` | `services.gemini.key` |

Default model: `gpt-4o-mini` (overridable via `AI_MODEL` or `OPENAI_MODEL` env vars).

## Deployment

| Target | Method | URL |
|--------|--------|-----|
| Staging | **Dokku** via `dokku` git remote | `https://ai-flow-staging.itman.fyi` |
| Alternative | **Laravel Cloud** | N/A |

Deploy root is `backend/` (not repo root). Dockerfile builds React assets + nginx/PHP-FPM.
Release phase runs migrations + seeds.

## Key Configuration Files

| File | Purpose |
|------|---------|
| `backend/composer.json` | PHP dependencies |
| `backend/package.json` | JS/TS dependencies |
| `backend/tsconfig.json` | TypeScript config (strict) |
| `backend/vite.config.ts` | Vite build config |
| `backend/vitest.config.ts` | Vitest test config |
| `backend/playwright.config.ts` | Playwright E2E config |
| `backend/phpunit.xml` | PHPUnit config |
| `backend/.env.example` | Environment template |
| `backend/Dockerfile` | Container build |
| `backend/config/services.php` | AI provider keys, email, GitHub |
| `backend/config/database.php` | DB connections |
| `backend/config/queue.php` | Queue connections |
| `.github/workflows/ci.yml` | CI (PHP 8.4 + Node 24) |
| `.github/workflows/deploy-staging.yml` | Staging deploy |
| `.pre-commit-config.yaml` | Pre-commit hooks |
