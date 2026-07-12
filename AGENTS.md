# AGENTS.md

Instructions for AI-assisted work on **ai-flow** (AI Launcher). Align with [Laravel agent-skills](https://github.com/laravel/agent-skills) where noted below.

## Project overview

**AI Launcher** turns public GitHub URLs into structured AI workflow reports (review PR, plan issue, explain repo, Laravel doctor).

| Area            | Path                                                                         | Stack                                                        |
| --------------- | ---------------------------------------------------------------------------- | ------------------------------------------------------------ |
| **Launcher UI** | repo root (`src/`, `index.html`)                                             | Vite + React; calls real API unless `VITE_DEMO_MODE=true`    |
| **API**         | `backend/`                                                                   | Laravel 13, PHP 8.4+, queue jobs, OpenAI + GitHub REST       |
| **Durable DB**  | Laravel Cloud Postgres/MySQL (or Turso on `main` until L13+libsql)           | Local dev uses SQLite; production needs a managed DB           |
| **Production**  | [Laravel Cloud](https://cloud.laravel.com/dung-huynh-duc/ai-flow/production) | Deploy **`backend/`** as application root                    |

Architecture decisions: [`doc/adr/README.md`](doc/adr/README.md).

## Commands

### Frontend (repo root)

```bash
npm install
npm run dev        # Vite dev binds 0.0.0.0
npm run build      # → dist/
npm run preview
```

No ESLint/Prettier at root—match existing style in `src/main.jsx` and `src/styles.css`.

- `vite.config.js` `server.allowedHosts` is an explicit list (`localhost`, `127.0.0.1`, `.onamp.dev`, `.amp.dev`), **not** `true`. Add your remote-preview host there, not `true`.
- Frontend calls the real API by default (`VITE_API_BASE_URL`, default `http://localhost:8000`). Set `VITE_DEMO_MODE=true` (root `.env.local`) to run simulated runs without a backend.

### Backend (`backend/`)

```bash
cd backend
composer install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite && php artisan migrate --seed
composer run dev   # server + queue:listen + pail + vite (concurrently; watches for code changes)
# Or separately:
php artisan serve
php artisan queue:work --sleep=1 --tries=2 --timeout=120   # production/standalone worker (not queue:listen)
php artisan test
php artisan test --filter=SomeTest            # run a focused test
./vendor/bin/pint --test                      # CI checks style with --test (fails on violations)
./vendor/bin/pint                             # fix style locally before pushing
```

CI (`.github/workflows/ci.yml`): frontend runs `npm ci` + `npm run build`; backend runs `composer install`, `migrate --force --seed`, `./vendor/bin/pint --test`, then `php artisan test` on PHP 8.4.

Vercel (`.github/workflows/vercel.yml`): same frontend build on PR/push; optional deploy on push when `VERCEL_TOKEN`, `VERCEL_ORG_ID`, and `VERCEL_PROJECT_ID` GitHub secrets are set. See root `README.md` (Frontend deployment).

**Required env (local & Cloud):** `OPENAI_API_KEY`. **Recommended:** `GITHUB_TOKEN` (rate limits). Optional: `AI_MODEL` (default `gpt-4o-mini`), `AI_BASE_URL` (OpenAI-compatible; set `https://openrouter.ai/api/v1` + `OPENROUTER_API_KEY` for the free-router demo), `OPENAI_TIMEOUT`, `CORS_ALLOWED_ORIGINS` (browser SPA origins, e.g. `http://localhost:5173`). Default `QUEUE_CONNECTION=database` (never `sync` in production). **Production DB (Laravel 13):** attach Laravel Cloud Serverless Postgres or MySQL (`DB_CONNECTION=pgsql` or `mysql`); do not use file SQLite on Cloud. Local dev: `DB_CONNECTION=sqlite` + `database/database.sqlite`.

**Production:** never run AI on the web process; use a real queue (`QUEUE_CONNECTION` ≠ `sync`). Worker: `php artisan queue:work --sleep=1 --tries=2 --timeout=120`.

### API smoke test

```bash
curl http://localhost:8000/health
curl http://localhost:8000/api/launchers
curl -X POST http://localhost:8000/api/runs -H 'Content-Type: application/json' \
  -d '{"launcher":"laravel-doctor","source_url":"https://github.com/laravel/framework"}'
curl http://localhost:8000/api/runs/RUN_UUID
curl -N -H 'Accept: text/event-stream' http://localhost:8000/api/runs/RUN_UUID/stream
```

Launcher slugs: `review-pr`, `plan-issue`, `explain-repository`, `laravel-doctor`. `POST /api/runs` is throttled (5/hour/IP).

## Laravel Cloud (production)

- **Console:** https://cloud.laravel.com/dung-huynh-duc/ai-flow/production
- **App root in repo:** `backend/` (not monorepo root).
- **Deploy:** `composer global require laravel/cloud-cli`, `cloud auth -n`, then `cloud ship -n` (initial setup) or `cloud deploy ai-flow production -n` (existing app; discover flags via `cloud deploy -h`). Always `cloud deploy:monitor -n` after deploy.
- **Env on Cloud:** `APP_KEY`, `APP_ENV=production`, `APP_DEBUG=false`, `OPENAI_API_KEY`, optional `GITHUB_TOKEN`, durable `DB_*`, `CACHE_STORE`, `QUEUE_CONNECTION`. Run `php artisan migrate --force` on deploy.
- **SSE:** Proxy must disable buffering for `/api/runs/*/stream` (`X-Accel-Buffering: no`); allow long-lived responses (≥60s).
- **Docs / CLI:** https://cloud.laravel.com/docs/llms.txt — use `cloud <command> -h` for signatures; prefer `-n` on all commands (never `-q`). Destructive Cloud ops need explicit user approval.

Official skill reference: [deploying-laravel-cloud](https://github.com/laravel/agent-skills/tree/main/laravel-cloud/skills/deploying-laravel-cloud).

## Coding standards

### PHP / Laravel (`backend/`)

Follow **Laravel conventions** and **PSR-12**, enforced with **Laravel Pint** (`./vendor/bin/pint`). Mirrors [laravel-simplifier](https://github.com/laravel/agent-skills/blob/main/laravel/agents/laravel-simplifier.md):

- **Explicit return types** on methods where practical.
- **Form requests** for HTTP validation (`StoreRunRequest`), not fat controllers.
- **API resources** for JSON shape (`RunResource`).
- **Contracts + container binding** for swappable services (`AIProviderInterface` → `OpenAIProvider`).
- **Jobs** for slow/IO work (`ExecuteLauncherJob`); controllers return **202** and dispatch.
- **Thin routes** in `routes/api.php`; logic in controllers, services, jobs, launchers.
- **Launchers:** one class per workflow under `app/Launchers/`, metadata via `BaseLauncher::make()`; seed in `DatabaseSeeder`.
- **Exceptions:** domain failures as `RuntimeException` / `InvalidArgumentException` with safe user-facing messages in `runs.error`; log details server-side.
- **No nested ternaries**—prefer `match`, early returns, or clear `if/else`.
- **Preserve behavior** when refactoring; run `php artisan test` after PHP changes.
- **PHPUnit:** feature tests with `RefreshDatabase` + seed; `Queue::fake()` when asserting dispatch; mock GitHub/AI in job tests.

### React / frontend (repo root)

- Main UI remains in `src/main.jsx`; supporting code lives in `src/components/`, `src/data/`, and `src/lib/`. Plain CSS remains in `src/styles.css` (BEM-like classes).
- Functional components + hooks; no TypeScript unless the project adds it.
- **API:** `src/lib/api.js` — `VITE_API_BASE_URL` (default `http://localhost:8000`), SSE progress, `VITE_DEMO_MODE=true` for simulated runs only.
- Keep React, Vite, and `lucide-react` versions pinned for reproducible builds.

## Architecture map (backend)

```
POST /api/runs → RunController::store → Run (queued) → ExecuteLauncherJob
  → GitHubService (parse + cached context)
  → AIProviderInterface::generate (JSON schema)
  → JsonSchemaValidator → runs.result
GET /api/runs/{uuid} → RunResource
GET /api/runs/{uuid}/stream → SSE (DB poll, ~55s)
```

Do not add synchronous OpenAI/GitHub calls to the HTTP request cycle.

## Optional Laravel official agent skills

Install when working on Cloud deploys or PHP polish ([agent-skills README](https://github.com/laravel/agent-skills)):

```sh
npx skills add https://github.com/laravel/agent-skills/tree/main/laravel-cloud/skills/deploying-laravel-cloud
npx skills add https://github.com/laravel/agent-skills/tree/main/laravel/skills/starter-kit-upgrade  # only if upgrading from a Laravel starter kit
```

**Laravel simplifier:** after substantive PHP edits, review for clarity/conventions without changing behavior (same rules as above).

**Nightwatch:** not required for this MVP; add [configure-nightwatch](https://github.com/laravel/agent-skills/tree/main/laravel-nightwatch/skills/configure-nightwatch) if observability is introduced.

## Repo-specific gotchas

- **Monorepo:** Cloud deploys `backend/` only; root React deploys to Vercel (`vercel.json`, `.github/workflows/vercel.yml`). Production API: `https://ai-flow-production-q41p7t.laravel.cloud` via `VITE_API_BASE_URL` at build time. Amp portal: `.amp/portals/ai-launcher.json`.
- **Amp sync:** `origin` may point at Amp git; `github` remote is `jellydn/ai-flow`.
- **API aliases:** `/api/flows` and `/api/executions` are compatibility aliases for `/api/launchers` and `/api/runs` (same contracts).
- **Rate limit:** changing run creation limits → `AppServiceProvider` `RateLimiter::for('runs', ...)`.
- **New launcher:** PHP class + `DatabaseSeeder` entry + feature test coverage; shared `outputSchema` in `BaseLauncher`.
- **Laravel 13 + DB:** `turso/libsql-laravel` does not support Laravel 13 yet; production on this line uses **Laravel Cloud managed Postgres/MySQL**. Re-add Turso when the package supports `illuminate/database ^13`. `libsql` connection in `config/database.php` may remain for a future Turso return.

## When editing docs

- Product/marketing: root `README.md`
- API/setup/Cloud: `backend/README.md`
- Decisions: new ADR under `doc/adr/` and index in `doc/adr/README.md`
