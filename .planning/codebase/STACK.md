# Technology Stack

**Analysis Date:** 2026-07-15

## Languages

**Primary:**
- PHP ^8.4 (runtime constraint in `backend/composer.json`; production image uses PHP 8.5 FPM in `backend/Dockerfile`) — Laravel API, jobs, launchers under `backend/app/`
- TypeScript 5.9.3 — React SPA under `backend/resources/ts/` (`backend/tsconfig.json`, strict mode)

**Secondary:**
- CSS — `backend/resources/css/app.css`, loaded from `backend/resources/ts/app.tsx`
- Shell — deploy/release scripts (`backend/docker/bin/`, repo `scripts/hooks/`)

## Runtime

**Environment:**
- PHP 8.4+ locally/CI (`.github/workflows/ci.yml`); PHP 8.5-FPM in Docker (`backend/Dockerfile`)
- Node.js 24 — CI (`.github/workflows/ci.yml`), frontend build stage (`backend/Dockerfile`)

**Package Manager:**
- Composer 2 — PHP (`backend/composer.json`, `backend/composer.lock` present)
- npm — JavaScript (`backend/package.json`, `backend/package-lock.json` present)

## Frameworks

**Core:**
- Laravel ^13.0 — HTTP API, auth, queue, Eloquent (`backend/composer.json`, `backend/bootstrap/app.php`, `backend/routes/api.php`)
- React 19.2.7 — UI (`backend/package.json`, entry `backend/resources/ts/app.tsx`)

**Testing:**
- PHPUnit ^13.0 — backend (`backend/composer.json`, `backend/tests/`)
- Vitest ^4.1.10 + Testing Library — unit/component tests (`backend/package.json`, `backend/vitest.config.ts`)
- Playwright ^1.61.1 — E2E (`backend/package.json`, `backend/tests/E2E/`, `backend/playwright.config.ts`)

**Build/Dev:**
- Vite 8.1.4 + `laravel-vite-plugin` 3.1.3 — asset pipeline (`backend/vite.config.ts`, `backend/package.json`)
- `@vitejs/plugin-react` 6.0.3 — JSX/TSX transform
- Laravel Pint ^1.24 — PHP style (`backend/composer.json`, CI in `.github/workflows/ci.yml`)
- oxlint / oxfmt — TS lint/format (repo root `.oxlintrc.json`, `.oxfmtrc.json`; invoked via `backend/package.json` scripts)
- konsistent ^1.0.0-beta.3 — structural TS conventions (`konsistent.json`)
- concurrently 10.0.3 — local dev orchestration (`backend/composer.json` `dev` script)
- laravel/pail ^1.2.2 — log tailing in dev (`backend/composer.json`)

## Key Dependencies

**Critical:**
- `laravel/framework` ^13.0 — application core
- `react` / `react-dom` 19.2.7 — SPA
- `lucide-react` 1.24.0 — icons (pinned in `backend/package.json`)
- `sentry/sentry-laravel` ^4.26 — server error reporting (`backend/composer.json`, `backend/config/sentry.php`)
- `@sentry/react` ^10.65.0 — client error reporting (`backend/resources/ts/app.tsx`)
- `resend/resend-php` ^1.5 — transactional email for magic links (`backend/.env.example` `MAIL_MAILER=resend`)

**Infrastructure (app code, not separate packages):**
- Laravel HTTP client (`Illuminate\Support\Facades\Http`) — OpenAI/OpenRouter, Anthropic, Gemini, GitHub REST (`backend/app/Services/OpenAIProvider.php`, `backend/app/Services/GitHubContextFetcher.php`)
- Database queue driver — async runs (`backend/.env.example` `QUEUE_CONNECTION=database`, `backend/app/Jobs/ExecuteLauncherJob.php`)

## Configuration

**Environment:**
- Laravel `.env` / `.env.example` in `backend/` — DB, AI keys, mail, Sentry, CORS (`backend/.env.example`)
- Vite env: `VITE_DEMO_MODE`, `VITE_SENTRY_DSN` (`backend/.env.example`, `backend/resources/ts/`)
- Service credentials centralized in `backend/config/services.php` (GitHub, OpenAI, Anthropic, Gemini, Resend)

**Build:**
- `backend/vite.config.ts` — Laravel plugin, React, dev server port 5173
- `backend/tsconfig.json` — ES2022, strict, `noEmit` (typecheck only)
- `backend/phpunit.xml` — PHP test runner
- `konsistent.json` — component/hook naming rules at repo root

**Quality gates:**
- `.github/workflows/ci.yml` — backend + frontend + E2E jobs
- `.pre-commit-config.yaml` — Pint, typecheck, oxlint, composer validate

## Platform Requirements

**Development:**
- PHP 8.4+ with extensions used in CI: mbstring, xml, zip, sqlite3, pgsql (`.github/workflows/ci.yml`)
- Composer, Node 24, SQLite file `backend/database/database.sqlite` for local default (`backend/.env.example`)
- Queue worker for real runs: `php artisan queue:work` (documented in `backend/README.md`, `AGENTS.md`)

**Production:**
- Deploy root **`backend/`** only (`backend/CLOUD_DEPLOY.md`, `backend/DOKKU_DEPLOY.md`)
- **Dokku (staging):** Dockerfile + nginx/PHP-FPM + separate worker (`backend/Dockerfile`, `backend/Procfile`, `backend/DOKKU_DEPLOY.md`) — e.g. `https://ai-flow-staging.itman.fyi`
- **Laravel Cloud (alternative):** `composer install --no-dev && npm ci && npm run build`, Postgres, queue worker (`backend/CLOUD_DEPLOY.md`)
- Postgres/MySQL in production; SQLite rejected when `APP_ENV=production` (`backend/app/Providers/AppServiceProvider.php`)

---

*Stack analysis: 2026-07-15*
