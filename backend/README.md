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

Set `OPENAI_API_KEY`; `GITHUB_TOKEN` is optional but strongly recommended for GitHub rate limits. Configure `OPENAI_MODEL` and `OPENAI_TIMEOUT` as needed. Use a durable database/cache/queue in production.

## API

```bash
curl http://localhost:8000/api/health
curl http://localhost:8000/api/launchers
curl -X POST http://localhost:8000/api/runs -H 'Content-Type: application/json' \
  -d '{"launcher":"laravel-doctor","source_url":"https://github.com/laravel/framework"}'
curl http://localhost:8000/api/runs/RUN_UUID
curl -N -H 'Accept: text/event-stream' http://localhost:8000/api/runs/RUN_UUID/stream
```

`POST /api/runs` returns HTTP 202 with a UUID, `queued` status, and `Workflow started`; it is limited to 5 requests per IP per hour. Supported slugs are `review-pr`, `plan-issue`, `explain-repository`, and `laravel-doctor`; HTTPS GitHub URLs must match the launcher's repository/PR/issue input type. The status endpoint exposes queued/running/completed/failed state, a progress message array, and a structured result. SSE emits changed progress snapshots, then a completed or failed event.

## Laravel Cloud

Deploy `backend` as the application root. Provision a database, cache, and queue; set `APP_KEY`, `APP_ENV=production`, `APP_DEBUG=false`, `OPENAI_API_KEY`, optional `GITHUB_TOKEN`, and the relevant `DB_*`, `CACHE_STORE`, and `QUEUE_CONNECTION` variables. Run `php artisan migrate --force` during deployment and configure a worker with `php artisan queue:work --sleep=1 --tries=2 --timeout=120`. Ensure the HTTP proxy disables buffering for `/api/runs/*/stream` and allows responses of at least 60 seconds. Never run AI work on the web process or with `QUEUE_CONNECTION=sync` in production.

For CLI deployment, install the official Cloud CLI with `composer global require laravel/cloud-cli`, authenticate with `cloud auth -n`, and inspect current command options with `cloud <command> -h` rather than relying on fixed signatures. Use `cloud ship -n` for initial setup or `cloud deploy ... -n` for an existing application, then always verify the deployment with `cloud deploy:monitor -n`. Creating or deleting shared Cloud resources should be confirmed separately.

## Tests

`php artisan test` covers endpoint validation/queueing/rate limiting, URL parsing, and job execution with mocked GitHub and AI providers.

## Architecture

Backend decisions are recorded in the repo root: [`doc/adr/`](../doc/adr/README.md) (ADRs 0007–0014).
