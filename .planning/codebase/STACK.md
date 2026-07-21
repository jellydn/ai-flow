# Stack

> Single Laravel 13 app serving a React 19 SPA (Vite) + queue-backed API that turns GitHub URLs into structured AI workflow reports.
> Deploy root is `backend/`, not the repo root.

## Backend — PHP / Laravel

| Concern | Choice | Notes |
|---------|--------|-------|
| Language | **PHP 8.4+** | `composer.json` requires `^8.4`; Docker image runs PHP 8.5-fpm |
| Framework | **Laravel 13** | `laravel/framework ^13.0` |
| Admin panel | **Filament 5** | `filament/filament ^5.0` — super-admin panel at `/admin` |
| Mail | **Resend** | `resend/resend-php ^1.5` — magic-link auth emails |
| Error monitoring | **Sentry** | `sentry/sentry-laravel ^4.26` — errors reported only when DSN set |
| Tinker | **Laravel Tinker 3** | dev-only |
| Testing | **PHPUnit 13** | `phpunit/phpunit ^13.0` |
| Linting/formatting | **Laravel Pint 1.24** | PSR-12; CI fails on `pint --test` violations |
| Dev tooling | **Laravel Pail 1.2**, **Laravel Sail 1.41**, **Faker 1.23**, **Mockery 1.6**, **Collision 8.8** | |

### Backend config highlights (`backend/config/`)

- `services.php` — AI provider settings (OpenAI/OpenRouter/Anthropic/Gemini), GitHub token, Resend, mail. Model resolution: `AI_MODEL` overrides `OPENAI_MODEL` (code default `gpt-4o-mini`; `.env.example` bumps to `gpt-5`). Per-adapter: `ANTHROPIC_MODEL` (`claude-sonnet-4-20250514`), `GEMINI_MODEL` (`gemini-2.0-flash`).
- `credentials.php` — dedicated `CREDENTIAL_ENCRYPTION_KEY` for BYOK credential encryption (falls back to `APP_KEY`). AppServiceProvider warns in production when the dedicated key is unset.
- `super_admin.php` — bootstrap super admin on `migrate --seed` (defaults to `dung@productsway.com`).
- `database.php` — SQLite local/CI, Postgres/MySQL production. AppServiceProvider throws in production if `DB_CONNECTION=sqlite` or if pgsql lacks TLS.
- `queue.php` — `database` driver default. AppServiceProvider throws in production if `QUEUE_CONNECTION=sync`.

### Database

| Env | Driver | Notes |
|-----|--------|-------|
| Local/CI | **SQLite** | `database/database.sqlite` or `:memory:` in tests |
| Production (Dokku) | **PostgreSQL** | `DB_URL` (not Dokku's `DATABASE_URL`); `DB_SSLMODE=require` for Neon |
| Production (Laravel Cloud) | **Neon PostgreSQL** | durable, `DB_SSLMODE=require` |

> `turso/libsql-laravel` doesn't support Laravel 13 yet — production uses managed Postgres/MySQL, never SQLite.

## Frontend — React / TypeScript

| Concern | Choice | Notes |
|---------|--------|-------|
| Language | **TypeScript 5.9** | strict mode (`tsconfig.json` `strict: true`, `allowJs: false`) |
| UI framework | **React 19.2** | `react`/`react-dom` `19.2.7` — functional components + hooks |
| Bundler | **Vite 8.1** | `@vitejs/plugin-react 6.0`, `laravel-vite-plugin 3.1` |
| Icons | **lucide-react 1.25** | pinned version |
| Markdown | **react-markdown 10** + **remark-gfm 4** | for report rendering |
| Error monitoring | **@sentry/react 10** | frontend errors |
| Logger | **consola 3.4** | structured logging |
| Linting | **oxlint 1.73** | config at repo root `.oxlintrc.json`; `correctness: error`, `no-console: error` |
| Formatting | **oxfmt 0.59** | config at repo root `.oxfmtrc.json`; no Prettier |
| Structural lint | **konsistent 1.0-beta** | `konsistent.json` — enforces component/hook naming conventions |
| Unit testing | **Vitest 4.1** + **Testing Library** | jsdom env, `globals: true` |
| E2E testing | **Playwright 1.61** | `--project=real-backend`; `*.real.spec.ts` |
| Dev server | **concurrently 10** | `composer run dev` runs serve + queue:listen + pail + vite |

### Frontend entry point

`backend/resources/ts/app.tsx` → mounted in `backend/resources/views/app.blade.php` → served by Laravel.

## Deployment

| Target | Method | URL |
|--------|--------|-----|
| **Dokku (staging)** | Dockerfile + git push `dokku` remote | `https://ai-flow-staging.itman.fyi` |
| **Laravel Cloud (alt)** | deploy `backend/` as app root | — |

### Dockerfile (multi-stage)

1. `node:24-bookworm-slim` — `npm ci`, `npm run build` → `public/build/`
2. `php:8.5-fpm-bookworm` — composer install (no-dev), nginx + supervisor, PHP-FPM

### Procfile

- `release:` `docker/bin/release-migrate.sh` (migrate + seed)
- `web:` supervisord (nginx + PHP-FPM)
- `worker:` `php artisan queue:work --sleep=1 --tries=2 --timeout=120`

> SSE: disable nginx `proxy-buffering`, set `proxy-read-timeout 75s`.

## CI (`.github/workflows/ci.yml`)

| Job | Runs | Key steps |
|-----|------|-----------|
| Backend | PHP 8.4 (`sqlite3`,`pgsql` ext) | `composer validate`, `php artisan test`, `pint --test` |
| Frontend | Node 24 | `typecheck`, `lint`, `konsistent`, `build`, `test` (vitest) |

Pre-commit hooks via prek (`.pre-commit-config.yaml`): trailing-whitespace, end-of-file-fixer, check-yaml, check-added-large-files, composer-validate, pint, frontend-typecheck, oxlint, oxfmt, konsistent. `just prek` runs all.

## Justfile targets

`just ci` runs the full backend+frontend gate: `pint-check test typecheck lint-js konsistent build`.
