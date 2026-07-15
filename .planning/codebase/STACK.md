# Stack

**Analysis Date:** 2026-07-14

## Languages & Runtimes

| Area | Language | Runtime | Version |
|------|----------|---------|---------|
| Backend | PHP | PHP-FPM / CLI | 8.4+ |
| Frontend | TypeScript | Browser (Vite bundling) | Node 24 for builds |
| E2E | TypeScript | Playwright (Chromium) | Node 24 |

## Backend Framework

- **Laravel** ^13.0 (`laravel/framework`) — the API server, queue worker, and static-asset host
- **Laravel Tinker** ^3.0 — REPL for debugging
- **Resend** ^1.5 (`resend/resend-php`) — transactional email for magic-link auth
- **Sentry** ^4.26 (`sentry/sentry-laravel`) — error monitoring (optional, env-gated)

## Frontend Stack

- **React** 19.2.7 — functional components + hooks only
- **Vite** — bundler via `laravel-vite-plugin` + `@vitejs/plugin-react`
- **TypeScript** strict mode — `tsc --noEmit` for typechecking
- **lucide-react** 1.24.0 — icon library (pinned)
- **Sentry React** ^10.65.0 — frontend error monitoring

## Frontend Tooling

- **oxlint** + **oxfmt** — Rust-based linter/formatter (config: `.oxlintrc.json`, `.oxfmtrc.json`); no ESLint/Prettier
- **konsistent** — structural TS convention checks (`components/*.tsx` must export PascalCase matching filename, `hooks/*.ts` must export `use*`)
- **Vitest** + `@testing-library/react` + `@testing-library/user-event` — unit/component tests
- **Playwright** ^1.61.1 — E2E tests (demo + real-backend projects)

## Backend Dev Dependencies

- **PHPUnit** ^13.0 — test framework
- **Mockery** ^1.6 — test doubles
- **Laravel Pint** ^1.24 — PSR-12 / Laravel style enforcer
- **Laravel Pail** ^1.2.2 — log tailing in dev
- **Laravel Sail** ^1.41 — Docker dev environment (optional)
- **Faker** ^1.23 — test data generation

## Database

| Environment | Driver | Config |
|-------------|--------|--------|
| Local dev | SQLite | `database/database.sqlite` (file) |
| Test | SQLite | `:memory:` (phpunit.xml) |
| Production | PostgreSQL or MySQL | Laravel Cloud managed DB; `DB_CONNECTION=pgsql` or `mysql` |
| Staging (Dokku) | PostgreSQL | Dokku Postgres plugin or external (e.g. Neon) |

**Note:** `turso/libsql-laravel` does not support Laravel 13 yet; a `libsql` connection remains in `config/database.php` for future Turso return.

## Queue

- **Default:** `database` (`QUEUE_CONNECTION=database`) — jobs stored in `jobs` table
- **Never `sync` in production** — `AppServiceProvider` throws if `sync` detected
- Worker: `php artisan queue:work --sleep=1 --tries=2 --timeout=120`

## Cache

- **Default:** `database` cache store (via `CACHE_STORE`)
- Used by: `GitHubService` (10-min context cache), `CacheRunProgressedVersion` listener

## Session

- **Default:** `database` session driver
- Used by: magic-link auth (`SessionGuard`), `Auth::login()`, `session()->regenerate()`

## Mail

- **Resend** (`resend/resend-php`) — sends magic-link emails
- Config: `config/services.php` → `resend.key` from `RESEND_API_KEY`
- Fallback: Postmark, AWS SES configured but not used by default

## Deployment Targets

| Target | Method | App Root |
|--------|--------|----------|
| Laravel Cloud | `cloud deploy` CLI | `backend/` |
| Dokku VPS (staging) | `git push dokku main:main` | `backend/` (Dockerfile builder) |
| Local dev | `php artisan serve` + `npm run dev` | `backend/` |

## Key Environment Variables

| Variable | Purpose | Required |
|----------|---------|----------|
| `APP_KEY` | Encryption (credentials, sessions) | Yes |
| `OPENAI_API_KEY` | OpenAI provider (or fallback for OpenRouter) | Yes |
| `GITHUB_TOKEN` | GitHub API rate-limit bypass | Recommended |
| `RESEND_API_KEY` | Magic-link email sending | Yes (auth) |
| `ANTHROPIC_API_KEY` | Anthropic provider | Optional |
| `GEMINI_API_KEY` | Gemini provider | Optional |
| `OPENROUTER_API_KEY` | OpenRouter provider | Optional |
| `AI_MODEL` | Default model override | Optional |
| `AI_BASE_URL` | OpenAI-compatible base URL | Optional |
| `VITE_DEMO_MODE` | Frontend simulated runs | Optional |
| `QUEUE_CONNECTION` | Must not be `sync` in prod | Yes (prod) |
| `DB_CONNECTION` | `sqlite` (dev), `pgsql`/`mysql` (prod) | Yes (prod) |

## CI Pipeline

**GitHub Actions** (`.github/workflows/ci.yml`):
- **backend** job: `composer validate` → `composer install` → `migrate` → `php artisan test` → `pint --test`
- **frontend** job: `npm ci` → `typecheck` → `lint` (oxlint + oxfmt) → `konsistent` → `build` → `test`
- **e2e** job: `composer install` + `npm ci` + Playwright Chromium → `npm run test:e2e:demo`
- **deploy** job (`.github/workflows/deploy-staging.yml`): pushes to Dokku on `main`

## Pre-commit Hooks

Via [prek](https://prek.j178.dev) (`.pre-commit-config.yaml`):
- `composer-validate`, `pint`, `frontend-typecheck`, `oxlint`, `oxfmt`, `konsistent`
- Run all: `just prek` (requires `cd backend && npm ci`)

## Sentry Integration

- Backend: `sentry/sentry-laravel` — captures unhandled exceptions
- Frontend: `@sentry/react` — initialized in `resources/ts/app.tsx`
- Both disabled in test/CI unless `SENTRY_DSN` is set
