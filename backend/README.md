# AI Launcher API

Laravel 12 API that queues GitHub workflow analyses and returns schema-constrained OpenAI results.

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

Set `OPENAI_API_KEY`; `GITHUB_TOKEN` is optional but strongly recommended for GitHub rate limits. Configure `AI_MODEL` and `OPENAI_TIMEOUT` as needed. The provider accepts any OpenAI-compatible endpoint via `AI_BASE_URL`. For an OpenRouter free-model demo, set `OPENROUTER_API_KEY`, `AI_BASE_URL=https://openrouter.ai/api/v1`, and `AI_MODEL=openrouter/free` instead of `OPENAI_API_KEY`. The free router selects a currently available model that supports the request's structured-output parameters; free capacity and model selection are not guaranteed. Use a durable database/cache/queue in production.

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

Deploy `backend` as the application root. Provision a database, cache, and queue; set `APP_KEY`, `APP_ENV=production`, `APP_DEBUG=false`, provider settings (`OPENAI_API_KEY` or `OPENROUTER_API_KEY`, `AI_BASE_URL`, `AI_MODEL`), optional `GITHUB_TOKEN`, `CORS_ALLOWED_ORIGINS`, and the relevant `DB_*`, `CACHE_STORE`, and `QUEUE_CONNECTION` variables. Laravel Cloud may expose a managed database through `DATABASE_URL`; otherwise use the individual `DB_*` values supported by Laravel. Run `php artisan migrate --force` during deployment and configure a worker with `php artisan queue:work --sleep=1 --tries=2 --timeout=120`. Ensure the HTTP proxy disables buffering for `/api/runs/*/stream` and allows responses of at least 60 seconds. Never run AI work on the web process or with `QUEUE_CONNECTION=sync` in production.

Laravel Cloud deploys only the API because the application root is `backend/`. Host the root Vite build separately with SPA fallback enabled so `/runs/{uuid}` serves `index.html`, then set `VITE_API_BASE_URL` to the Cloud API URL, `VITE_PUBLIC_APP_URL` to the frontend origin, and allow that origin through `CORS_ALLOWED_ORIGINS`. A share URL opened on the API host is not a frontend report URL.

For CLI deployment, install the official Cloud CLI with `composer global require laravel/cloud-cli`, authenticate with `cloud auth -n`, and inspect current command options with `cloud <command> -h` rather than relying on fixed signatures. Use `cloud ship -n` for initial setup or `cloud deploy ... -n` for an existing application, then always verify the deployment with `cloud deploy:monitor -n`. Creating or deleting shared Cloud resources should be confirmed separately.

## Tests

`php artisan test` covers endpoint validation/queueing/rate limiting, URL parsing, and job execution with mocked GitHub and AI providers.

## Architecture

Backend decisions are recorded in the repo root: [`doc/adr/`](../doc/adr/README.md) (ADRs 0007–0014).
