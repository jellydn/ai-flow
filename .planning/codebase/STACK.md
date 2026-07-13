# Technology Stack

**Analysis Date:** 2026-07-13

## Languages

**Primary:**
- PHP 8.4+ (`^8.4` in `backend/composer.json`) — Laravel API, jobs, launchers, Eloquent models under `backend/app/`
- TypeScript 5.9.3 (strict, `ES2022` target in `backend/tsconfig.json`) — React SPA under `backend/resources/ts/`

**Secondary:**
- Blade — SPA shell `backend/resources/views/app.blade.php` and mail template `backend/resources/views/mail/magic-link.blade.php`
- CSS (plain, no Tailwind) — `backend/resources/css/app.css` with Google Fonts (Manrope, DM Mono, Playfair Display)
- Bash — deploy/release helpers (`backend/docker/bin/release-migrate.sh`, `scripts/hooks/*`)
- YAML — CI (`.github/workflows/ci.yml`, `deploy-staging.yml`), pre-commit (`.pre-commit-config.yaml`)
- Dockerfile / nginx / supervisord configs — production image in `backend/Dockerfile`, `backend/docker/`

## Runtime

**Environment:**
- PHP **8.4** for local/CI (`.github/workflows/ci.yml` uses `shivammathur/setup-php` with 8.4; extensions: `mbstring`, `xml`, `zip`, `sqlite3`, `pgsql`)
- Production Docker image uses **PHP 8.5-FPM** Bookworm (`php:8.5-fpm-bookworm` in `backend/Dockerfile`) with nginx + supervisor
- Node.js **24** for frontend tooling/CI (`actions/setup-node` node-version `24`; Docker frontend stage `node:24-bookworm-slim`)
- Browser: React 19 SPA served same-origin by Laravel

**Package Manager:**
- **Composer 2** — PHP deps; lockfile present: `backend/composer.lock` (Laravel framework locked at **v13.19.0**)
- **npm** — frontend deps; lockfile present: `backend/package-lock.json`
- Root has no workspace `package.json`; all Node work runs from `backend/`

## Frameworks

**Core:**
- **Laravel Framework ^13.0** (`laravel/framework` v13.19.0) — HTTP API, queue jobs, sessions, mail, Eloquent (`backend/composer.json`)
- **React 19.2.7** + **react-dom 19.2.7** — UI (`backend/package.json`)
- **Vite 8.1.4** + **laravel-vite-plugin 3.1.0** + **@vitejs/plugin-react 6.0.3** — asset build/dev (`backend/vite.config.ts`)
- **lucide-react 1.24.0** — icons

**Testing:**
- **PHPUnit ^13.0** (locked 13.2.4) via `php artisan test` — `backend/tests/Unit`, `backend/tests/Feature`, config `backend/phpunit.xml`
- **Mockery ^1.6**, **Faker** — PHP test doubles/data
- **Vitest ^4.1.10** + **jsdom** + **@testing-library/react** / **jest-dom** / **user-event** — frontend unit tests (`backend/vitest.config.ts`, `resources/ts/**/*.test.{ts,tsx}`)

**Build/Dev:**
- **concurrently 10.0.3** — `composer run dev` runs serve + queue:listen + pail + vite
- **laravel/pail**, **laravel/pint ^1.24**, **laravel/sail**, **laravel/tinker** — logging/dev tooling
- **oxlint ^1.73**, **oxfmt ^0.58** — lint/format (configs: `.oxlintrc.json`, `.oxfmtrc.json` at repo root)
- **konsistent ^1.0.0-beta.3** — structural TS conventions (`konsistent.json`)
- **TypeScript 5.9.3** — `tsc --noEmit` typecheck
- **prek / pre-commit** — `.pre-commit-config.yaml` + `just prek`
- **Renovate** — `renovate.json` (`config:recommended`)

## Key Dependencies

**Critical:**
- `laravel/framework` ^13 — application core (routing, queue, cache, session, HTTP client, Crypt)
- `guzzlehttp/*` (via Laravel) — outbound HTTP used by `Http` facade for OpenAI/Anthropic/Gemini/GitHub
- No first-party OpenAI/Anthropic/Gemini SDKs — custom providers in `backend/app/Services/*Provider.php` call REST APIs with `Illuminate\Support\Facades\Http`
- React 19 + Vite 8 — SPA build pipeline producing `public/build`

**Infrastructure:**
- Database drivers: **SQLite** (local/CI), **PostgreSQL** (`pdo_pgsql` in Docker; Neon or Dokku Postgres in prod)
- Queue: **database** driver (`jobs` table) — `QUEUE_CONNECTION=database` default in `backend/.env.example` and `backend/config/queue.php`
- Cache/session: **database** store/driver by default (`CACHE_STORE=database`, `SESSION_DRIVER=database`)
- Mail transports available: log (default), smtp, resend, postmark, ses (`backend/config/mail.php`)
- Optional local vendor artifacts: `resend/resend-php`, `sentry/*` may exist under `vendor/` but are **not** declared in `backend/composer.json` / lock require list

## Configuration

**Environment:**
- Primary: `backend/.env` from `backend/.env.example`
- App: `APP_NAME`, `APP_ENV`, `APP_KEY`, `APP_DEBUG`, `APP_URL`
- DB: `DB_CONNECTION` (sqlite local; pgsql production), optional `DB_URL` / discrete `DB_*`, `DB_SSLMODE`
- Queue/cache/session: `QUEUE_CONNECTION`, `CACHE_STORE`, `SESSION_DRIVER`, `SESSION_LIFETIME`
- AI: `OPENAI_API_KEY` (required for runs), optional `OPENROUTER_API_KEY`, `AI_BASE_URL`, `AI_MODEL` / `OPENAI_MODEL`, `OPENAI_TIMEOUT`, `AI_SITE_URL`
- Anthropic/Gemini (user credentials / optional server keys): `ANTHROPIC_API_KEY`, `ANTHROPIC_MODEL`, `GEMINI_API_KEY`, `GEMINI_MODEL` via `backend/config/services.php`
- GitHub: `GITHUB_TOKEN` (recommended for rate limits)
- CORS: `CORS_ALLOWED_ORIGINS`
- Frontend demo: `VITE_DEMO_MODE=true` (client-only simulated runs)
- Config sources: `backend/config/*.php` (`app`, `auth`, `cache`, `cors`, `database`, `filesystems`, `logging`, `mail`, `queue`, `services`, `session`)

**Build:**
- Frontend: `backend/vite.config.ts`, `backend/tsconfig.json`, `backend/package.json` scripts (`build` = `tsc --noEmit && vite build`)
- PHP: Composer autoload PSR-4 `App\` → `backend/app/`
- Docker multi-stage: Node builds assets → PHP-FPM image copies `public/build` (`backend/Dockerfile`)
- Prod process model: `backend/Procfile` (release migrate, web supervisord, worker `queue:work`)
- Lint/format: root `.oxlintrc.json`, `.oxfmtrc.json`, `konsistent.json`

## Platform Requirements

**Development:**
- PHP 8.4+, Composer, extensions for sqlite (and pgsql if testing against Postgres)
- Node 24+, npm
- SQLite file `backend/database/database.sqlite`
- Commands from `backend/`: `composer install`, `npm install`, `php artisan key:generate`, `migrate --seed`, `composer run dev`
- Optional: `just` + `prek` for hooks

**Production:**
- Deploy root: **`backend/`** (not monorepo root)
- **Dokku staging** (what CI ships): host `docklight-staging.itman.fyi`, app `ai-flow`, URL `https://ai-flow-staging.itman.fyi` — Dockerfile builder, nginx proxy buffering off for SSE (`backend/DOKKU_DEPLOY.md`, `.github/workflows/deploy-staging.yml`)
- **Laravel Cloud** alternative: Neon Postgres, `npm ci && npm run build`, durable `APP_KEY` (`backend/CLOUD_DEPLOY.md`)
- Worker: `php artisan queue:work --sleep=1 --tries=2 --timeout=120`
- Must not use `DB_CONNECTION=sqlite` or `QUEUE_CONNECTION=sync` in production HTTP (enforced in `backend/app/Providers/AppServiceProvider.php`)
- Health: Laravel `/up`, API `/api/health` (`backend/app.json` healthcheck path `/up`)

---

*Stack analysis: 2026-07-13*
