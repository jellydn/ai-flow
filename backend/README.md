# AI Launcher API

Laravel 13 API that queues GitHub workflow analyses and returns schema-constrained OpenAI results.

## Setup

```bash
cp .env.example .env
composer install
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan serve
php artisan queue:work --tries=2 --timeout=120
```

Set `OPENAI_API_KEY`; `GITHUB_TOKEN` is optional but strongly recommended for GitHub rate limits. Configure `OPENAI_MODEL` and `OPENAI_TIMEOUT` as needed. The provider accepts any OpenAI-compatible endpoint via `AI_BASE_URL`. Use a durable database/cache/queue in production.

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
  "flow_id": "laravel-doctor",
  "input": { "url": "https://github.com/laravel/laravel" },
  "provider": { "id": "openai", "api_key": "sk-..." }
}
```

The existing `launcher` and `source_url` fields remain supported. User keys override `OPENAI_API_KEY`, are never added to run records or responses, and are never logged. Because execution is asynchronous, Laravel encrypts the complete queued job with the shared `APP_KEY`; only the worker decrypts the key in memory for the current execution. Authentication failures are exposed only as `Invalid API key.`

## API

```bash
curl http://localhost:8000/api/health
curl http://localhost:8000/api/launchers
curl -X POST http://localhost:8000/api/runs -H 'Content-Type: application/json' \
  -d '{"launcher":"laravel-doctor","source_url":"https://github.com/laravel/framework"}'
curl http://localhost:8000/api/runs/RUN_UUID
curl -N -H 'Accept: text/event-stream' http://localhost:8000/api/runs/RUN_UUID/stream
```

`/api/flows` and `/api/executions` are compatibility aliases for `/api/launchers` and `/api/runs`; they use the same request and response contracts.

`POST /api/runs` returns HTTP 202 with a UUID, `queued` status, and `Workflow started`; it is limited to 5 requests per IP per hour. Supported slugs are `review-pr`, `plan-issue`, `explain-repository`, and `laravel-doctor`; HTTPS GitHub URLs must match the launcher's repository/PR/issue input type. The status endpoint exposes queued/running/completed/failed state, a progress message array, and a structured result. SSE emits changed progress snapshots, then a completed or failed event.

## Laravel Cloud

Deploy `backend` as the application root. Provision a cache and database queue; set a stable shared `APP_KEY`, `APP_ENV=production`, `APP_DEBUG=false`, `OPENAI_API_KEY`, `OPENAI_MODEL=gpt-5`, optional `GITHUB_TOKEN`, `CORS_ALLOWED_ORIGINS`, Neon `DB_*` values, `DB_SSLMODE=require`, `CACHE_STORE`, and `QUEUE_CONNECTION=database`.

**Database (Laravel 13):** use a Neon direct PostgreSQL connection with SSL required for deployment migrations. The pooled endpoint is suitable for web and worker runtime traffic after migration. Do not use file SQLite in production. Run migrations against Neon before starting the worker.

Run `php artisan migrate --force` during deployment and configure a worker with `php artisan queue:work --sleep=1 --tries=2 --timeout=120`. Ensure the HTTP proxy disables buffering for `/api/runs/*/stream` and allows responses of at least 60 seconds. Never run AI work on the web process or with `QUEUE_CONNECTION=sync` in production.

Laravel Cloud deploys only the API because the application root is `backend/`. Host the root Vite build separately with SPA fallback enabled so `/runs/{uuid}` serves `index.html`, then set `VITE_API_BASE_URL` to the Cloud API URL, `VITE_PUBLIC_APP_URL` to the frontend origin, and allow that origin through `CORS_ALLOWED_ORIGINS`. A share URL opened on the API host is not a frontend report URL.

For CLI deployment, install the official Cloud CLI with `composer global require laravel/cloud-cli`, authenticate with `cloud auth -n`, and inspect current command options with `cloud <command> -h` rather than relying on fixed signatures. Use `cloud ship -n` for initial setup or `cloud deploy ... -n` for an existing application, then always verify the deployment with `cloud deploy:monitor -n`. Creating or deleting shared Cloud resources should be confirmed separately.

## Tests

`php artisan test` covers endpoint validation/queueing/rate limiting, URL parsing, and job execution with mocked GitHub and AI providers.

## Architecture

Backend decisions are recorded in the repo root: [`doc/adr/`](../doc/adr/README.md) (ADRs 0007–0014).
