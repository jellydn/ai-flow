# External Integrations

**Analysis Date:** 2026-07-13

> Application root is `backend/`. All integrations are wired through `backend/config/services.php`
> and consumed via `Illuminate\Support\Facades\Http` (Laravel HTTP client). No third-party SDK
> packages are installed for AI or GitHub — both are custom adapters over plain REST.

## APIs & External Services

**AI Provider (LLM):**
- OpenAI-compatible Chat Completions API.
- SDK/Client: Custom `App\Services\OpenAIProvider` (`backend/app/Services/OpenAIProvider.php`), implementing `backend/app/Contracts/AIProviderInterface.php`; selected via `backend/app/Support/AiProviders.php` (`const OPENAI = 'openai'`).
- Endpoint: `POST {AI_BASE_URL}/chat/completions` (`backend/app/Services/OpenAIProvider.php:44`). Default `AI_BASE_URL=https://api.openai.com/v1` (`backend/config/services.php:41`).
- Request shape: `model`, `messages` (system + user), `response_format.type=json_schema` (strict) — `backend/app/Services/OpenAIProvider.php:21-31`.
- OpenRouter compatibility: if `AI_BASE_URL` contains `openrouter.ai`, adds `provider.require_parameters` and `X-OpenRouter-Title` header (`backend/app/Services/OpenAIProvider.php:32-41`); uses `OPENROUTER_API_KEY` as alt key (`backend/config/services.php:40`).
- Auth: Bearer token from `OPENAI_API_KEY` (or `OPENROUTER_API_KEY`) — `backend/.env.example:39,49`.
- Config: `services.openai` — `key`, `base_url`, `model` (default `gpt-4o-mini` in config, `OPENAI_MODEL=gpt-5` in `.env.example`), `timeout` (default 60), `referer` (`backend/config/services.php:39-45`).
- Retry: 2 attempts at 500ms (`backend/app/Services/OpenAIProvider.php:43`).

**GitHub REST API:**
- GitHub public REST API (`https://api.github.com`).
- SDK/Client: Custom `App\Services\GitHubService` + `App\Services\GitHubContextFetcher` (`backend/app/Services/GitHubService.php`, `backend/app/Services/GitHubContextFetcher.php`); parser `App\Data\GitHubReference` (`backend/app/Data/GitHubReference.php`).
- Endpoint builder: `GET /repos/{owner}/{repo}` plus sub-resources — `repo`, `languages`, `readme`, `git/trees/{branch}?recursive=1`; for PRs: `pulls/{n}`, `pulls/{n}/files`, `issues/{n}/comments`; for issues: `issues/{n}`, `issues/{n}/comments` (`backend/app/Services/GitHubContextFetcher.php:30-60`).
- Client config: base URL `https://api.github.com`, `Accept: application/json`, User-Agent `ai-launcher`, timeout 15s, retry 2 (`backend/app/Services/GitHubContextFetcher.php:88-94`).
- Auth: optional Bearer `GITHUB_TOKEN` via `services.github.token` (`backend/config/services.php:38`, `backend/app/Services/GitHubContextFetcher.php:96-98`).
- Errors mapped by HTTP status: 404 -> resource not found, 403 -> rate limit/access denied (suggests `GITHUB_TOKEN`), 401 -> auth failed (`backend/app/Services/GitHubContextFetcher.php:63-86`).

## Data Storage

**Databases:**
- **SQLite** (local dev / CI) — `DB_CONNECTION=sqlite`, file `backend/database/database.sqlite` (`backend/config/database.php:44-54`, `backend/.env.example:17`).
- **PostgreSQL** (production / Laravel Cloud + Neon) — `DB_CONNECTION=pgsql`; env `DB_HOST`, `DB_PORT=5432`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_SSLMODE=require` (`backend/config/database.php:96-112`, `backend/.env.example:20-27`).
- **MySQL / MariaDB** — supported (`backend/config/database.php:56-94`); production alternative to Postgres.
- **Turso / libsql** (optional, future) — `libsql` connection pre-wired with `TURSO_DATABASE_URL`, `TURSO_AUTH_TOKEN`, `TURSO_LOCAL_DATABASE`, `TURSO_SYNC_INTERVAL` (`backend/config/database.php:35-42`); package `turso/libsql-laravel` not yet in `composer.json` (Laravel 13 unsupported per `AGENTS.md`).
- **Redis** — configured in `backend/config/database.php:158-194` but NOT used by default (cache/queue use `database` driver).
- ORM/client: Eloquent ORM (`backend/app/Models/Run.php`, `Launcher.php`, `User.php`).
- Production guard: `AppServiceProvider` throws if `sqlite` is used in production, and requires `DB_SSLMODE` in `{require, verify-ca, verify-full}` for pgsql (`backend/app/Providers/AppServiceProvider.php:34-55`).

**File Storage:**
- Local filesystem only — `FILESYSTEM_DISK=local` (`backend/.env.example:33`, `backend/config/filesystems.php`). No object storage (S3/etc.) configured. GitHub README content is `base64_decode`d in-memory (`backend/app/Services/GitHubContextFetcher.php:41`).

**Caching:**
- Default `CACHE_STORE=database` — DB-backed cache table (`backend/config/cache.php:18,42-48`, `backend/.env.example:36`).
- GitHub context cached 10 minutes via `Cache::remember('github:'.sha1($url), ...)` (`backend/app/Services/GitHubService.php:47-53`).
- Other supported stores available (array, file, memcached, redis, dynamodb, octane, failover) but inactive.

## Authentication & Identity

**Auth Provider:**
- Custom / framework-default — Laravel session auth (`web` guard, Eloquent `User` provider — `backend/config/auth.php:40-68`).
- No external OAuth/idP (Auth0, Laravel Jetstream, Sanctum, Passport) is installed. No API token auth on the public endpoints; the `/api/runs` and `/api/executions` routes are open but throttled (`backend/routes/api.php:10-14`).
- `App\Models\User` exists but there is no registration/login flow wired into the launcher API.

## Monitoring & Observability

**Error Tracking:**
- None (no Sentry/Flare/Telemetry). GitHub/AI failures become `RuntimeException` logged server-side; user-facing messages stored in `runs.error` (per `AGENTS.md`).

**Logs:**
- `LOG_CHANNEL=stack`, `LOG_STACK=single`, `LOG_LEVEL=debug` (local) (`backend/.env.example:13-15`, `backend/config/logging.php`).
- `laravel/pail` for structured dev log tailing (`backend/composer.json:18`).
- Production warning if `LOG_LEVEL=debug` (`backend/app/Providers/AppServiceProvider.php:57-59`).

## CI/CD & Deployment

**Hosting:**
- Laravel Cloud — production app `ai-flow` (per `AGENTS.md`); deploy app root `backend/`. CLI: `cloud deploy ai-flow production`. Worker runs `php artisan queue:work --sleep=1 --tries=2 --timeout=120`.

**CI Pipeline:**
- GitHub Actions `.github/workflows/ci.yml`:
  - `backend` job: PHP 8.4 (extensions `mbstring, xml, zip, sqlite3, pgsql`), `composer validate` -> `composer install` -> `key:generate` + sqlite -> `migrate --force` -> `php artisan test` -> `./vendor/bin/pint --test`.
  - `frontend` job: Node 20, `npm ci` -> `npm run typecheck` -> `npm run lint` (oxlint+oxfmt) -> `npm run konsistent` -> `npm run build`.
- Local pre-commit: prek hooks (`.pre-commit-config.yaml`) — composer-validate, pint, frontend-typecheck, oxlint, oxfmt, konsistent.
- Dependency updates: `renovate.json` (repo root).

## Environment Configuration

**Required env vars:**
- `OPENAI_API_KEY` — LLM auth (`backend/.env.example:39`).
- `APP_KEY` — Laravel app key (`backend/.env.example:3`).

**Recommended / optional env vars:**
- `GITHUB_TOKEN` — higher GitHub rate limits (`backend/.env.example:46`).
- `OPENAI_MODEL` (default `gpt-5`), `AI_BASE_URL` (default `https://api.openai.com/v1`), `AI_SITE_URL`, `OPENAI_TIMEOUT` (60).
- `OPENROUTER_API_KEY` + `AI_BASE_URL=https://openrouter.ai/api/v1` + `AI_MODEL=openrouter/free` — alternative provider (`backend/.env.example:48-51`).
- `DB_CONNECTION` / `DB_*` — database (sqlite local, pgsql/mysql cloud).
- `QUEUE_CONNECTION` (default `database`; never `sync` in prod).
- `CACHE_STORE` (default `database`), `SESSION_DRIVER=database`.
- `CORS_ALLOWED_ORIGINS`, `VITE_DEMO_MODE`.

**Secrets location:**
- `.env` (git-ignored) on local/Cloud; not committed. CI/Cloud injects via environment. No `.env` committed (only `backend/.env.example`).

## Webhooks & Callbacks

**Incoming:**
- None. No webhook receivers or signature-verified endpoints. All inbound traffic is the public REST API (`/api/health`, `/api/launchers`, `/api/runs`, `/api/executions`, plus `/flows` alias) and the SSE stream endpoints (`backend/routes/api.php`).

**Outgoing:**
- `POST {AI_BASE_URL}/chat/completions` — to OpenAI/OpenRouter (`backend/app/Services/OpenAIProvider.php:44`).
- `GET https://api.github.com/repos/...` — GitHub REST calls (`backend/app/Services/GitHubContextFetcher.php:90`).
- React frontend: same-origin `/api/*` requests (no third-party analytics/telemetry). `AI_SITE_URL`/`HTTP-Referer` and `X-OpenRouter-Title` are the only outbound custom headers, sent to the AI provider only.

---

*Integration audit: 2026-07-13*
