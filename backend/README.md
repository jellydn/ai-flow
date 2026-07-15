# AI Flow

Laravel 13 application that serves the React UI and queues GitHub workflow analyses, returning schema-constrained OpenAI results.

## Setup

```bash
cd backend
cp .env.example .env
composer install
npm install
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
```

Start the local dev stack:

```bash
composer run dev
```

This runs the PHP dev server, queue listener, logs, and Vite dev server concurrently. The app is served at `http://localhost:8000`.

Or run them separately:

```bash
php artisan serve
php artisan queue:work --tries=2 --timeout=120
npm run dev
```

Set `OPENAI_API_KEY` (or `OPENROUTER_API_KEY` when using OpenRouter). `GITHUB_TOKEN` is optional but strongly recommended for GitHub rate limits. `RESEND_API_KEY` is required for **Email link** sign-in (magic links); password sign-in does not use email delivery. Model and timeout: `config/services.php` uses `AI_MODEL` if set, otherwise `OPENAI_MODEL` (default `gpt-4o-mini`). OpenAI-compatible endpoints: `AI_BASE_URL` (OpenRouter example in `.env.example`). Use a durable database/cache/queue in production.

Optional error monitoring: set `SENTRY_LARAVEL_DSN` and `VITE_SENTRY_DSN` to enable Sentry error tracking on both backend and frontend (no-op when unset).

Frontend checks:

```bash
npm run typecheck
npm run lint
npm run build
```

Backend checks:

```bash
php artisan test
./vendor/bin/pint --test
```

## Super admin (`/admin`)

Internal operators use **Filament** at `/admin` (ADR 0021) to manage **users** and **workflow templates** (`launchers`). Access requires `users.is_super_admin = true`.

```bash
# After migrate, promote an existing account (same email/password as SPA sign-in)
php artisan user:promote-super-admin you@example.com
```

Optional bootstrap on `migrate --seed`: by default promotes or creates **`dung@productsway.com`** as super admin (override with `SUPER_ADMIN_BOOTSTRAP_EMAIL`; set empty to disable). If no user exists for that address, the seeder creates a **super admin**, generates a password, and **emails it** (requires `RESEND_API_KEY` or another mail driver). If the user already exists, only `is_super_admin` is set — no password email.

Sign in at `http://localhost:8000/admin`. The React customer app is unchanged; `/admin` is excluded from the SPA catch-all route.

Filament frontend assets are not committed; after `composer install` run:

```bash
php artisan filament:assets
```

## Frontend

The UI is in `resources/ts/` and built with Vite. `resources/views/app.blade.php` is the SPA shell, and `routes/web.php` provides a fallback so `/runs/{uuid}` and other client-side routes resolve correctly. Laravel Vite loads the built assets from `public/build`.

The same-origin API client lives in `resources/ts/services/run.ts` (with HTTP helpers in `resources/ts/lib/http.ts` and streaming hooks in `resources/ts/hooks/`). It calls `/api/health`, `/api/launchers`, `/api/runs`, and `/api/runs/{uuid}/stream` (`/api/flows` and `/api/executions` are aliases for backward compatibility).

Set `VITE_DEMO_MODE=true` in `.env` (and restart Vite if it is already running) to run **simulated** workflow progress and demo reports in the browser. Demo mode does not call `POST /api/runs` or require a queue worker; it uses the static launcher catalog in `resources/ts/data/launcherMeta.ts`. Share URLs such as `/runs/{uuid}` still load real runs from `GET /api/runs/{uuid}` when the API is available.

## Database

Development defaults to SQLite. Production uses Neon PostgreSQL via Laravel's standard `pgsql` driver:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=
DB_PORT=5432
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=
DB_SSLMODE=require
```

Copy values from the Neon connection details; never commit credentials. Use Neon's **direct hostname** (without `-pooler`) when running migrations, because Laravel wraps PostgreSQL schema changes in transactions and Neon transaction pooling can abort multi-statement DDL migrations. From the `backend/` application root, verify connectivity with `php artisan migrate --force`. After migration, the web process and queue worker may use Neon's pooled hostname for normal application queries.

## Bring Your Own API Key

The server key remains the default. A caller can optionally supply an OpenAI-compatible key for one execution:

```json
{
  "launcher": "laravel-doctor",
  "source_url": "https://github.com/laravel/framework",
  "provider": { "id": "openai", "api_key": "sk-..." }
}
```

The launch form's optional AI provider selector and API key field send the same `provider` object. Supported `provider.id` values are `openai` (default), `openrouter`, `anthropic`, and `gemini`; the authoritative list is `AiProviderRegistry::ids()`. The existing `flow_id` and `input` fields remain supported. User keys override `OPENAI_API_KEY`, are never added to run records or responses, and are never logged. Only **HTTPS** `https://github.com/...` URLs are accepted for `source_url`. Because execution is asynchronous, Laravel encrypts the complete queued job with the shared `APP_KEY`; only the worker decrypts the key in memory for the current execution. Authentication failures are exposed only as `Invalid API key.`

## API

```bash
curl http://localhost:8000/api/health
curl http://localhost:8000/api/flows
curl -X POST http://localhost:8000/api/executions -H 'Content-Type: application/json' \
  -d '{"launcher":"laravel-doctor","source_url":"https://github.com/laravel/framework"}'
curl http://localhost:8000/api/executions/RUN_UUID
curl -N -H 'Accept: text/event-stream' http://localhost:8000/api/executions/RUN_UUID/stream
```

`/api/flows` and `/api/executions` are compatibility aliases for `/api/launchers` and `/api/runs`; they use the same request and response contracts.

`POST /api/executions` returns HTTP 202 with a UUID, `queued` status, and `Workflow started`; it is limited to 5 requests per IP per hour. Supported slugs are `review-pr`, `plan-issue`, `explain-repository`, and `laravel-doctor`; HTTPS GitHub URLs must match the launcher's repository/PR/issue input type. The status endpoint exposes queued/running/completed/failed state, a progress message array, and a structured result. SSE emits changed progress snapshots, then a completed or failed event.

### Sign-in

The UI supports **password** sign-in, **sign-up** (email + password), and **email link** (magic link). All methods use the same Laravel session cookie on the app origin.

| Method | HTTP | Notes |
|--------|------|--------|
| Register | `POST /auth/register` | `email`, `password`, `password_confirmation`, optional `name`. Rejects emails that already have an account (including magic-link-only); use magic link first, then set a password from Settings when that flow exists. |
| Login | `POST /auth/login` | `email`, `password`. |
| Magic link request | `POST /auth/magic-link` | `email`; queues email (needs worker + `RESEND_API_KEY`). |
| Magic link verify | `GET /auth/magic-link/{token}` | Browser redirect; sets session. |
| Logout | `POST /auth/logout` | Clears session. |

Authenticated JSON endpoints expect the session cookie (`credentials: include` from the SPA).

### Authenticated user endpoints

When signed in (password or magic link), users can manage their own runs:

```bash
# List runs (with optional filters)
curl http://localhost:8000/api/user/runs
curl "http://localhost:8000/api/user/runs?status=completed&launcher=review-pr&date_from=2026-01-01"

# Show a single run
curl http://localhost:8000/api/user/runs/RUN_UUID

# Retry a completed or failed run
curl -X POST http://localhost:8000/api/user/runs/RUN_UUID/retry

# Delete a run
curl -X DELETE http://localhost:8000/api/user/runs/RUN_UUID
```

**Run history filters:** `status` (`queued`/`running`/`completed`/`failed`), `launcher` (slug), `provider` (id), `date_from`/`date_to` (Y-m-d), `search` (source URL substring), and `per_page` (1–100, default 20). A cross-field validation ensures `date_to >= date_from`.

**Provider credentials:** users can save, update, verify, and delete encrypted API keys for any supported provider. The `POST /api/user/provider-credentials/{id}/verify` endpoint is rate-limited to 10 requests per minute per user.

## Laravel Cloud

Deploy `backend` as the application root. See **[CLOUD_DEPLOY.md](CLOUD_DEPLOY.md)** for monorepo root, build/deploy commands, and troubleshooting (`Vite manifest not found`, SQLite in production).

Provision a cache and database queue; set a stable shared `APP_KEY`, `APP_ENV=production`, `APP_DEBUG=false`, `OPENAI_API_KEY`, `OPENAI_MODEL=gpt-5`, optional `GITHUB_TOKEN`, `CORS_ALLOWED_ORIGINS`, Neon `DB_*` values, `DB_SSLMODE=require`, `CACHE_STORE`, and `QUEUE_CONNECTION=database`.

**Database (Laravel 13):** use a Neon direct PostgreSQL connection with SSL required for deployment migrations. The pooled endpoint is suitable for web and worker runtime traffic after migration. Do not use file SQLite in production. Run migrations against Neon before starting the worker.

Run `php artisan migrate --force` during deployment and configure a worker with `php artisan queue:work --sleep=1 --tries=2 --timeout=120`. Ensure the HTTP proxy disables buffering for `/api/executions/*/stream` and `/api/runs/*/stream` and allows responses of at least 60 seconds. Never run AI work on the web process or with `QUEUE_CONNECTION=sync` in production.

Laravel Cloud deploys the whole `backend/` directory; the React UI is built during deployment and served by Laravel. The SPA fallback route ensures `/runs/{uuid}` resolves correctly.

**Build step:** `public/build` is gitignored, so the Cloud deploy must install Node dependencies and build the Vite assets from the `backend/` root. Set the build command to `npm ci && npm run build` (or run `npm install && npm run build` before `composer install` if the image requires it). `composer.json`’s `setup` script also runs `npm run build` for local/offline installs, but the Cloud build step is the authoritative source for production assets.

For CLI deployment, install the official Cloud CLI with `composer global require laravel/cloud-cli`, authenticate with `cloud auth -n`, and inspect current command options with `cloud <command> -h` rather than relying on fixed signatures. Use `cloud ship -n` for initial setup or `cloud deploy ... -n` for an existing application, then always verify the deployment with `cloud deploy:monitor -n`. Creating or deleting shared Cloud resources should be confirmed separately.

## Dokku

The app can also be deployed to the Dokku VPS at `docklight-staging.itman.fyi` (staging URL `https://ai-flow-staging.itman.fyi`). See **[DOKKU_DEPLOY.md](DOKKU_DEPLOY.md)** for DNS/TLS, Dokku Postgres (`DATABASE_URL` → `DB_URL`), environment variables, Git remote, queue-worker scaling, and verification.

## Tests

`php artisan test` covers endpoint validation/queueing/rate limiting, URL parsing, and job execution with mocked GitHub and AI providers.

Frontend: `npm run test` runs Vitest + React Testing Library component tests. E2E: `npm run test:e2e:demo` runs Playwright against the demo-mode Vite dev server (no backend required).

## Architecture

Backend decisions are recorded in the repo root: [`doc/adr/`](../doc/adr/README.md) (ADRs 0007–0018).
