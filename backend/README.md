# AI Launcher

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

Set `OPENAI_API_KEY` (or `OPENROUTER_API_KEY` when using OpenRouter). `GITHUB_TOKEN` is optional but strongly recommended for GitHub rate limits. Model and timeout: `config/services.php` uses `AI_MODEL` if set, otherwise `OPENAI_MODEL` (default `gpt-4o-mini`). OpenAI-compatible endpoints: `AI_BASE_URL` (OpenRouter example in `.env.example`). Use a durable database/cache/queue in production.

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

## Frontend

The UI is in `resources/ts/` and built with Vite. `resources/views/app.blade.php` is the SPA shell, and `routes/web.php` provides a fallback so `/runs/{uuid}` and other client-side routes resolve correctly. Laravel Vite loads the built assets from `public/build`.

The same-origin API client lives in `resources/ts/services/api.ts`. It calls `/api/health`, `/api/flows`, `/api/executions`, and `/api/executions/{uuid}/stream`.

Set `VITE_DEMO_MODE=true` in `.env` to run simulated workflow executions without a backend.

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

The existing `flow_id` and `input` fields remain supported. User keys override `OPENAI_API_KEY`, are never added to run records or responses, and are never logged. Because execution is asynchronous, Laravel encrypts the complete queued job with the shared `APP_KEY`; only the worker decrypts the key in memory for the current execution. Authentication failures are exposed only as `Invalid API key.`

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

## Laravel Cloud

Deploy `backend` as the application root. Provision a cache and database queue; set a stable shared `APP_KEY`, `APP_ENV=production`, `APP_DEBUG=false`, `OPENAI_API_KEY`, `OPENAI_MODEL=gpt-5`, optional `GITHUB_TOKEN`, `CORS_ALLOWED_ORIGINS`, Neon `DB_*` values, `DB_SSLMODE=require`, `CACHE_STORE`, and `QUEUE_CONNECTION=database`.

**Database (Laravel 13):** use a Neon direct PostgreSQL connection with SSL required for deployment migrations. The pooled endpoint is suitable for web and worker runtime traffic after migration. Do not use file SQLite in production. Run migrations against Neon before starting the worker.

Run `php artisan migrate --force` during deployment and configure a worker with `php artisan queue:work --sleep=1 --tries=2 --timeout=120`. Ensure the HTTP proxy disables buffering for `/api/executions/*/stream` and `/api/runs/*/stream` and allows responses of at least 60 seconds. Never run AI work on the web process or with `QUEUE_CONNECTION=sync` in production.

Laravel Cloud deploys the whole `backend/` directory; the React UI is built during deployment and served by Laravel. The SPA fallback route ensures `/runs/{uuid}` resolves correctly.

For CLI deployment, install the official Cloud CLI with `composer global require laravel/cloud-cli`, authenticate with `cloud auth -n`, and inspect current command options with `cloud <command> -h` rather than relying on fixed signatures. Use `cloud ship -n` for initial setup or `cloud deploy ... -n` for an existing application, then always verify the deployment with `cloud deploy:monitor -n`. Creating or deleting shared Cloud resources should be confirmed separately.

## Tests

`php artisan test` covers endpoint validation/queueing/rate limiting, URL parsing, and job execution with mocked GitHub and AI providers.

## Architecture

Backend decisions are recorded in the repo root: [`doc/adr/`](../doc/adr/README.md) (ADRs 0007–0014).
