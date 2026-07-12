# AGENTS.md

Instructions for AI-assisted work on **ai-flow** (AI Launcher). Align with [Laravel agent-skills](https://github.com/laravel/agent-skills) where noted below.

## Project overview

**AI Launcher** turns public GitHub URLs into structured AI workflow reports (review PR, plan issue, explain repo, Laravel doctor).

| Area            | Path                                                                         | Stack                                                  |
| --------------- | ---------------------------------------------------------------------------- | ------------------------------------------------------ |
| **Launcher UI** | `backend/resources/ts/` + `backend/resources/views/app.blade.php`            | React + TypeScript, Vite (served by Laravel)           |
| **API**         | `backend/`                                                                   | Laravel 13, PHP 8.4+, queue jobs, OpenAI + GitHub REST |
| **Durable DB**  | Laravel Cloud Postgres/MySQL                                                 | Local dev uses SQLite; production needs a managed DB   |
| **Production**  | [Laravel Cloud](https://cloud.laravel.com/dung-huynh-duc/ai-flow/production) | Deploy **`backend/`** as application root              |

Architecture decisions: [`doc/adr/README.md`](doc/adr/README.md).

## Commands

All development happens inside `backend/`.

```bash
cd backend
composer install
npm install
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
npm run typecheck                             # tsc --noEmit (strict)
npm run lint                                  # oxlint + oxfmt --check (NOT typecheck)
npm run format                                # oxfmt --write (in place)
npm run build                                 # tsc --noEmit && vite build → public/build
npm run konsistent                            # structural TS convention checks (konsistent)
npm run doctor                                # npx react-doctor (React codebase analysis)
```

CI (`.github/workflows/ci.yml`): backend (PHP 8.4, `sqlite3`+`pgsql` ext) runs `composer validate`, `composer install`, `migrate --force`, `php artisan test`, `./vendor/bin/pint --test`. Frontend (Node 20) runs `npm ci`, `npm run typecheck` (`tsc --noEmit`), `npm run lint` (oxlint + oxfmt --check), `npm run konsistent`, `npm run build` (`tsc --noEmit && vite build`); `npm run test` is a no-op placeholder.

**Local hooks:** pre-commit runs via [prek](https://prek.j178.dev) (`.pre-commit-config.yaml`); `just prek` runs them all on every file (requires backend deps: `cd backend && npm ci`). Hooks: `composer-validate`, `pint`, `frontend-typecheck`, `oxlint`, `oxfmt`, `konsistent`.

**Required env (local & Cloud):** `OPENAI_API_KEY`. **Recommended:** `GITHUB_TOKEN` (rate limits). Optional: `AI_MODEL` (default `gpt-4o-mini`), `AI_BASE_URL` (OpenAI-compatible; set `https://openrouter.ai/api/v1` + `OPENROUTER_API_KEY` for the free-router demo), `OPENAI_TIMEOUT`, `VITE_DEMO_MODE=true` (frontend simulated runs without backend). Default `QUEUE_CONNECTION=database` (never `sync` in production). **Production DB (Laravel 13):** attach Laravel Cloud Serverless Postgres or MySQL (`DB_CONNECTION=pgsql` or `mysql`); do not use file SQLite on Cloud. Local dev: `DB_CONNECTION=sqlite` + `database/database.sqlite`.

**Production:** never run AI on the web process; use a real queue (`QUEUE_CONNECTION` ≠ `sync`). Worker: `php artisan queue:work --sleep=1 --tries=2 --timeout=120`.

### API smoke test

```bash
curl http://localhost:8000/api/health
curl http://localhost:8000/api/launchers
curl -X POST http://localhost:8000/api/runs -H 'Content-Type: application/json' \
  -d '{"launcher":"laravel-doctor","source_url":"https://github.com/laravel/framework"}'
curl http://localhost:8000/api/runs/RUN_UUID
curl -N -H 'Accept: text/event-stream' http://localhost:8000/api/runs/RUN_UUID/stream
```

Launcher slugs: `review-pr`, `plan-issue`, `explain-repository`, `laravel-doctor`. `POST /api/runs` is throttled (5/hour/IP); `/api/executions` is an alias.

## Laravel Cloud (production)

- **Console:** https://cloud.laravel.com/dung-huynh-duc/ai-flow/production
- **App root in repo:** `backend/` (not monorepo root).
- **Deploy:** `composer global require laravel/cloud-cli`, `cloud auth -n`, then `cloud ship -n` (initial setup) or `cloud deploy ai-flow production -n` (existing app; discover flags via `cloud deploy -h`). Always `cloud deploy:monitor -n` after deploy.
- **Env on Cloud:** `APP_KEY`, `APP_ENV=production`, `APP_DEBUG=false`, `OPENAI_API_KEY`, optional `GITHUB_TOKEN`, durable `DB_*`, `CACHE_STORE`, `QUEUE_CONNECTION`. Run `php artisan migrate --force` on deploy.
- **SSE:** Proxy must disable buffering for `/api/executions/*/stream` and `/api/runs/*/stream` (`X-Accel-Buffering: no`); allow long-lived responses (≥60s).
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

### React / frontend (`backend/resources/ts/`)

- Main UI is split into `components/`, `data/`, `lib/`, `services/`, and `types/`. Entry is `resources/ts/app.tsx`; the Blade shell is `resources/views/app.blade.php`. Plain CSS remains in `resources/css/app.css` (BEM-like classes).
- Functional components + hooks; TypeScript with strict mode. Avoid broad `any`; use `unknown` with explicit narrowing.
- **API:** `resources/ts/services/run.ts` (HTTP helpers in `resources/ts/lib/http.ts`, streaming in `resources/ts/hooks/`) — same-origin `/api/*` requests, typed `Run`/`Launcher` contracts, SSE with polling fallback.
- Vite configuration is `vite.config.ts` and uses `laravel-vite-plugin` + `@vitejs/plugin-react`.
- Frontend is linted/formatted by **oxlint + oxfmt** (Rust-based; config at repo root `.oxlintrc.json` / `.oxfmtrc.json`), not ESLint/Prettier — there is no `.prettierrc`. Fix formatting with `npm run format`, not `prettier --write`.
- **konsistent** enforces structural TS conventions: `components/*.tsx` must export a PascalCase component matching the filename, `hooks/*.ts` must export `use*` functions.
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

- **Monorepo:** Cloud deploys `backend/` only; the UI is now bundled inside `backend/` and served by Laravel.
- **Amp sync:** `origin` may point at Amp git; `github` remote is `jellydn/ai-flow`.
- **API aliases:** `/api/flows` and `/api/executions` are compatibility aliases for `/api/launchers` and `/api/runs` (same contracts).
- **Rate limit:** changing run creation limits → `AppServiceProvider` `RateLimiter::for('runs', ...)`.
- **New launcher:** PHP class + `DatabaseSeeder` entry + feature test coverage; shared `outputSchema` in `BaseLauncher`.
- **Laravel 13 + DB:** `turso/libsql-laravel` does not support Laravel 13 yet; production on this line uses **Laravel Cloud managed Postgres/MySQL**. Re-add Turso when the package supports `illuminate/database ^13`. `libsql` connection in `config/database.php` may remain for a future Turso return.

## When editing docs

- Product/marketing: root `README.md`
- API/setup/Cloud: `backend/README.md`
- Decisions: new ADR under `doc/adr/` and index in `doc/adr/README.md`
