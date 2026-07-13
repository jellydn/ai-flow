# Technology Stack

**Analysis Date:** 2026-07-13

> Repo root is a monorepo; the actual application lives in `backend/`. The Laravel
> app also bundles and serves the React/TypeScript frontend (Vite + `laravel-vite-plugin`).

## Languages

**Primary:**
- PHP 8.4+ — `backend/composer.json` (`"php": "^8.4"`); all backend application logic (`backend/app/`).
- TypeScript 5.7.3 — `backend/package.json`; frontend UI in `backend/resources/ts/` (strict mode, `backend/tsconfig.json`).

**Secondary:**
- Blade / HTML — `backend/resources/views/app.blade.php` (shell that mounts the React app).
- YAML — CI workflow (`.github/workflows/ci.yml`), pre-commit config (`.pre-commit-config.yaml`), `renovate.json`.
- JSON — config conventions (`konsistent.json`), lint config (`.oxlintrc.json`, `.oxfmtrc.json`).

## Runtime

**Environment:**
- PHP 8.4 (CI installs via `shivammathur/setup-php@v2`, extensions `mbstring, xml, zip, sqlite3, pgsql` — `.github/workflows/ci.yml`).
- Node.js 20 (CI uses `actions/setup-node@v4` with `node-version: "20"` — `.github/workflows/ci.yml`).
- Vite dev server (`0.0.0.0:5173`) and `php artisan serve` in dev (`backend/composer.json` `scripts.dev`).

**Package Manager:**
- Composer 2 — `backend/composer.json` (lockfile `backend/composer.lock` expected; validated in CI with `composer validate`).
- npm — `backend/package.json` (lockfile `backend/package-lock.json`; CI uses `npm ci` — `.github/workflows/ci.yml:61`).
- Lockfile: `backend/composer.lock` (Composer), `backend/package-lock.json` (npm).

## Frameworks

**Core:**
- Laravel 13 (`laravel/framework`: `^13.0`) — `backend/composer.json:13`. Full-stack web framework; routing (`backend/routes/api.php`), Eloquent ORM, queue, cache, HTTP client.
- React 19.2.7 (`react`, `react-dom`) — `backend/package.json:18-20`. Frontend SPA mounted by Blade.
- Vite 8.1.3 — `backend/package.json:33`. Module bundler / dev server via `laravel-vite-plugin` 3.1.0 (`backend/vite.config.ts`).
- `@vitejs/plugin-react` 6.0.3 — `backend/package.json:26`. React fast-refresh + JSX transform.

**Testing:**
- PHPUnit 13 (`phpunit/phpunit`: `^13.0`) — `backend/composer.json:23`. Runs via `php artisan test` (`backend/composer.json` `scripts.test`).
- Mockery 1.6 (`mockery/mockery`) — `backend/composer.json:21`. Mocking for jobs/services.
- `nunomaduro/collision` 8.8 — `backend/composer.json:22`. Pretty error reporting in tests.
- `fakerphp/faker` 1.23 — `backend/composer.json:17`. Test/seeder data.
- Frontend tests: none configured — `npm run test` is a no-op placeholder (`backend/package.json:14`).

**Build/Dev:**
- `laravel-vite-plugin` 3.1.0 — bundles `backend/resources/ts/app.tsx` (`backend/vite.config.ts:7`).
- `concurrently` 9.0.0 — parallel dev processes (`backend/composer.json` `scripts.dev`).
- `oxlint` 1.73.0 + `oxfmt` 0.58.0 — Rust-based lint/format (config `.oxlintrc.json` / `.oxfmtrc.json`); `npm run lint` / `npm run format` (`backend/package.json:9-12`).
- `konsistent` ^1.0.0-beta.3 — structural TS convention checks (`konsistent.json`); `npm run konsistent` (`backend/package.json:13`).
- `laravel/pint` 1.24 — PHP style fixer (`backend/composer.json:19`); CI runs `./vendor/bin/pint --test`.
- `laravel/pail` 1.2.2 — structured log tailing (`backend/composer.json:18`).
- `react-doctor` (latest) — `npm run doctor` (`backend/package.json:15`).
- `lucide-react` 1.23.0 — icon library (`backend/package.json:18`).

## Key Dependencies

**Critical:**
- `laravel/tinker` 3.0 — `backend/composer.json:14`; REPL for Laravel.
- `OpenAIProvider` (custom service, `backend/app/Services/OpenAIProvider.php`) — HTTP client wrapper over OpenAI-compatible `/chat/completions` (uses `Illuminate\Support\Facades\Http`). No official SDK; adapter pattern via `backend/app/Contracts/AIProviderInterface.php` and `backend/app/Support/AiProviders.php`.
- `GitHubService` / `GitHubContextFetcher` (custom; `backend/app/Services/GitHubService.php`, `backend/app/Services/GitHubContextFetcher.php`) — raw GitHub REST calls via `Illuminate\Support\Facades\Http` to `https://api.github.com`.
- `JsonSchemaValidator` (`backend/app/Services/JsonSchemaValidator.php`) — validates AI JSON responses against launcher schemas.

**Infrastructure:**
- Queue worker: `php artisan queue:work --sleep=1 --tries=2 --timeout=120` (per `AGENTS.md`); `ExecuteLauncherJob` (`backend/app/Jobs/ExecuteLauncherJob.php`) runs AI + GitHub work off the HTTP cycle.
- `laravel/sail` 1.41 — `backend/composer.json:20`; optional Docker dev environment.
- `turso/libsql-laravel` — NOT present in `composer.json` requires, but a `libsql` connection is pre-wired in `backend/config/database.php:35-42` (env `TURSO_DATABASE_URL` / `TURSO_AUTH_TOKEN`); intended future Turso return (see `AGENTS.md` gotchas).

## Configuration

**Environment:**
- `.env.example` (`backend/.env.example`) is the canonical template.
- Required: `OPENAI_API_KEY` (production). Recommended: `GITHUB_TOKEN` (rate limits).
- Optional AI: `OPENAI_MODEL` (default `gpt-5`), `AI_BASE_URL` (default `https://api.openai.com/v1`), `AI_SITE_URL`, `OPENAI_TIMEOUT` (default 60), `OPENROUTER_API_KEY` (alt provider).
- App: `APP_NAME="AI Launcher"`, `APP_KEY`, `APP_URL`, `APP_DEBUG`, `APP_ENV`.
- CORS: `CORS_ALLOWED_ORIGINS` (e.g. `http://localhost:8000`) — `backend/.env.example:54`.
- Frontend: `VITE_DEMO_MODE=true` enables simulated UI without backend — `backend/.env.example:57`.

**Build:**
- `backend/vite.config.ts` — Vite + `laravel-vite-plugin` (input `resources/ts/app.tsx`) + React plugin; dev server `host 0.0.0.0`, port `5173`, allowed hosts include `localhost`, `.localhost`, `.onamp.dev`, `.amp.dev`.
- `backend/tsconfig.json` — target ES2022, strict, `jsx: react-jsx`, `moduleResolution: Bundler`, `noEmit: true`, types `vite/client` + `node`.
- `konsistent.json` (repo root) — enforces PascalCase components in `components/` and `use*` hooks in `hooks/`.
- `.oxlintrc.json` (repo root) — plugins `typescript, unicorn, oxc`, `correctness: error`.
- `backend/config/*` — `app.php`, `auth.php`, `cache.php`, `database.php`, `queue.php`, `services.php`, `session.php`, `cors.php`, `filesystems.php`, `logging.php`, `mail.php`.

## Platform Requirements

**Development:**
- PHP 8.4 with `sqlite3`, `pgsql` extensions; `composer`, `npm`/`node 20`.
- Local DB: SQLite file `backend/database/database.sqlite` (`DB_CONNECTION=sqlite`).
- Dev command: `composer run dev` (server + `queue:listen` + `pail` + Vite concurrently — `backend/composer.json:46`).
- Pre-commit hooks via prek (`just prek`) — `.pre-commit-config.yaml`: composer-validate, pint, frontend-typecheck, oxlint, oxfmt, konsistent.

**Production:**
- Deployment targets: **Laravel Cloud** (app root `backend/`, `cloud deploy ai-flow production`) and optional **Dokku VPS** (`backend/DOKKU_DEPLOY.md`, Dockerfile + Procfile).
- Managed DB only: Laravel Cloud Postgres/MySQL (`DB_CONNECTION=pgsql` or `mysql`); file SQLite forbidden in production (`backend/app/Providers/AppServiceProvider.php:34-43` throws if `sqlite` in production).
- Real queue required: `QUEUE_CONNECTION` must NOT be `sync` in production (`AGENTS.md`).
- SSE proxy must disable buffering for `/api/runs/*/stream` and `/api/executions/*/stream` (`X-Accel-Buffering: no`), allow >=60s responses.
- CI: GitHub Actions `.github/workflows/ci.yml` (backend job: PHP 8.4 + SQLite, `composer validate`/`install`/`migrate`/`test`/`pint`; frontend job: Node 20, `typecheck`/`lint`/`konsistent`/`build`).

---

*Stack analysis: 2026-07-13*
