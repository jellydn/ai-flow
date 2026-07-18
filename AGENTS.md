# AGENTS.md

AI-assisted work on **ai-flow**: a single Laravel 13 app that serves a React UI (Vite) and a queue-backed API turning GitHub URLs into structured AI workflow reports.

| Area        | Path                                                              | Stack                                        |
| ----------- | ----------------------------------------------------------------- | -------------------------------------------- |
| UI          | `backend/resources/ts/` + `backend/resources/views/app.blade.php` | React 19 + TS, Vite (served by Laravel)      |
| API         | `backend/`                                                        | PHP 8.4, queue jobs, OpenAI/Anthropic/Gemini |
| Deploy root | `backend/` (not repo root)                                        | Dokku (staging) or Laravel Cloud             |

ADRs: `doc/adr/README.md`. Backend details: `backend/README.md`. Deploy: `backend/DOKKU_DEPLOY.md` (Dokku) and `backend/CLOUD_DEPLOY.md` (Laravel Cloud).

## Commands (all run inside `backend/`)

```bash
cp .env.example .env && composer install && npm install
php artisan key:generate
touch database/database.sqlite && php artisan migrate --seed
composer run dev          # serve + queue:listen + pail + vite, concurrently
# or separately:
php artisan serve
php artisan queue:work --tries=2 --timeout=120   # production/standalone worker
php artisan test
php artisan test --filter=SomeTest               # focused test
./vendor/bin/pint --test && ./vendor/bin/pint    # CI fails on --test violations
npm run typecheck     # tsc --noEmit (strict)
npm run lint          # oxlint + oxfmt --check (NOT typecheck)
npm run format        # oxfmt --write
npm run build         # tsc --noEmit && vite build -> public/build
npm run konsistent    # structural TS conventions (root konsistent.json)
npm run doctor        # npx react-doctor
npm run test          # vitest run (frontend unit tests)
npm run test:e2e      # Playwright e2e suite (--project=e2e)
npm run test:e2e:real # Playwright vs real API (--project=real-backend)
```

Frontend JS commands also exposed as `just` targets (`just lint-js`, `just test-js`, `just e2e-real`, etc.). `just ci` runs the full backend+frontend gate locally.

CI (`.github/workflows/ci.yml`): backend on **PHP 8.4** (`sqlite3`,`pgsql` ext) runs `composer validate`, `php artisan test`, `pint --test`; frontend on **Node 24** runs `typecheck`, `lint`, `konsistent`, `build`, `test` (`vitest run`). Pre-commit hooks via prek (`.pre-commit-config.yaml`): `just prek` runs them all.

## Environment & AI providers

- Required: `OPENAI_API_KEY`. Recommended: `GITHUB_TOKEN` (rate limits). Optional: `OPENAI_TIMEOUT`.
- Model resolution (`config/services.php`): `AI_MODEL` overrides `OPENAI_MODEL` (code default `gpt-4o-mini`; `.env.example` bumps to `gpt-5`). Per-adapter: `ANTHROPIC_MODEL` (`claude-sonnet-4-20250514`), `GEMINI_MODEL` (`gemini-2.0-flash`).
- Multiple providers implement `AIProviderInterface` (`OpenAI`/`OpenRouter`/`Anthropic`/`Gemini`). Provider IDs are sourced from `AiProviderRegistry::ids()`, not a config array. Requests may carry `provider.id`; users manage keys via `provider-credentials` (never stored on runs, never logged). User-supplied HTTPS GitHub URLs only.
- `QUEUE_CONNECTION=database` by default; never `sync` in production. Local DB: SQLite (`database/database.sqlite`). Production: durable Postgres/MySQL.

## API

- Slugs: `review-pr`, `plan-issue`, `explain-repository`, `laravel-doctor`.
- Aliases: `/api/flows`=`/api/launchers`, `/api/executions`=`/api/runs` (backward compat).
- Rate limiters in `AppServiceProvider::boot()`: `runs` (5/hr/IP), `runs-stream` (30/min/IP), `magic-link` (3/min/IP), `auth-login` (10/min/IP), `auth-register` (5/min/IP), `credentials` (10/min/user).
- `POST /api/runs` returns **202** + UUID; status at `GET /api/runs/{uuid}`; progress via SSE `GET /api/runs/{uuid}/stream` (DB-polled, ~55s window). Do not add synchronous OpenAI/GitHub calls to the HTTP cycle.
- Authenticated user routes under `auth` middleware: run history (`/api/user/runs`), provider credentials (`/api/user/provider-credentials`).

## Architecture map

```
POST /api/runs → RunController::store → Run (queued) → ExecuteLauncherJob
  → GitHubService (parse + cached context)
  → AIProviderInterface::generate (JSON schema)  → JsonSchemaValidator → runs.result
GET /api/runs/{uuid} → RunResource
GET /api/runs/{uuid}/stream → SSE (DB poll, ~55s)
```

## Deploy

- **Dokku (staging, what CI actually ships):** `dokku` git remote → `docklight-staging.itman.fyi:ai-flow`, URL `https://ai-flow-staging.itman.fyi`. Dockerfile builds React assets + nginx/PHP-FPM; release phase migrates + seeds. Disable nginx `proxy-buffering` and set `proxy-read-timeout 75s` for SSE. DB uses `DB_URL` (not Dokku's `DATABASE_URL`).
- **Laravel Cloud (alternative):** deploy `backend/` as app root, build `npm ci && npm run build`; stable shared `APP_KEY`, durable Neon Postgres (`DB_SSLMODE=require`), `QUEUE_CONNECTION=database`. See `CLOUD_DEPLOY.md`.
- Worker (both): `php artisan queue:work --sleep=1 --tries=2 --timeout=120`. Note `composer run dev` uses `php artisan queue:listen --tries=1 --timeout=0` (differs from standalone worker flags — don't copy the dev flags to prod).

## Coding standards

**PHP/Laravel:** PSR-12 via Pint. Explicit return types; form requests (`Store*Request`) for validation; API resources (`RunResource`) for JSON; contracts + container binding for swappable services; jobs for slow/IO work; thin `routes/api.php`. Launchers: one class per workflow under `app/Launchers/`, metadata via `BaseLauncher::make()`, seeded in `DatabaseSeeder`. No nested ternaries (prefer `match`/early returns). After PHP changes run `php artisan test`; feature tests use `RefreshDatabase`+seed, `Queue::fake()` for dispatch, mock GitHub/AI.

**React/TS:** functional components + hooks, strict mode, avoid broad `any`. Entry `resources/ts/app.tsx`; folders `components/ data/ lib/ services/ types/ hooks/`. Lint/format with **oxlint + oxfmt** (config at repo root `.oxlintrc.json`/`.oxfmtrc.json`) — no Prettier. `konsistent` requires `components/*.tsx` to export a PascalCase component matching the filename and `hooks/*.ts` to export `use*` functions. Pin React/Vite/`lucide-react` versions.

## Gotchas

- Git remotes: `origin` = `github.com/jellydn/ai-flow`, `dokku` = staging deploy target.
- After rebasing a feature branch onto `main`, use `git push --force-with-lease` (never `--force`) to update the remote. The PR will automatically track the new commits.
- New launcher = PHP class + `DatabaseSeeder` entry + feature test; shared `outputSchema` in `BaseLauncher`.
- Laravel 13 + DB: `turso/libsql-laravel` doesn't support Laravel 13 yet; production uses managed Postgres/MySQL, not SQLite.
