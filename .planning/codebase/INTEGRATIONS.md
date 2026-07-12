# External Integrations

**Analysis Date:** 2026-07-12

## APIs & External Services
- **GitHub REST API:** `GitHubService` accepts only public HTTPS `github.com` repository, issue, and pull-request URLs, then calls `https://api.github.com` for repository metadata, languages, README, recursive tree, PR files, issues, and comments (`backend/app/Services/GitHubService.php`). `GITHUB_TOKEN` is optional but adds bearer authentication to improve rate limits (`backend/config/services.php`, `backend/.env.example`). Responses are bounded before AI submission and cached for 10 minutes (`backend/app/Services/GitHubService.php`, `backend/app/Services/RunExecutor.php`).
- **OpenAI-compatible chat completions:** `OpenAIProvider` posts bearer-authenticated requests to `{AI_BASE_URL}/chat/completions`, defaulting to OpenAI, and requires strict JSON Schema output (`backend/app/Services/OpenAIProvider.php`, `backend/config/services.php`). The default model is `gpt-4o-mini`; timeout and model are configurable (`backend/.env.example`, `backend/config/services.php`).
- **OpenRouter:** The same provider can use `OPENROUTER_API_KEY`, `https://openrouter.ai/api/v1`, and an OpenRouter model; it sends `HTTP-Referer`, `X-OpenRouter-Title`, and `provider.require_parameters` when that base URL is selected (`backend/app/Services/OpenAIProvider.php`, `backend/.env.example`). No vendor SDK is installed; both integrations use Laravel's HTTP client (`backend/composer.json`).
- **Frontend-to-backend API:** The root SPA uses browser `fetch` for run creation/status and `EventSource` for progress (`src/lib/api.js`). Its API origin comes from `VITE_API_BASE_URL`, while backend CORS permits configured comma-separated origins and does not allow credentials (`.env.example`, `backend/config/cors.php`).

## Data Storage
- **Relational database:** Local defaults use SQLite (`backend/.env.example`, `backend/config/database.php`); production explicitly rejects SQLite in HTTP execution and documents MySQL/PostgreSQL or Laravel Cloud's managed database (`backend/app/Providers/AppServiceProvider.php`, `backend/README.md`). Laravel also ships configured SQL Server support, though it is not an indicated deployment target (`backend/config/database.php`).
- **Application records:** Eloquent persists launcher definitions and UUID-keyed workflow runs, including status, input, transient source context, structured result, errors, and timestamps (`backend/database/migrations/2026_01_01_000000_create_launchers_and_runs.php`, `backend/app/Models/Run.php`). GitHub source context is cleared after either completion or failure (`backend/app/Services/RunExecutor.php`).
- **Queue and cache:** Database-backed queue and cache are defaults, with jobs/cache/locks tables supplied by Laravel migrations (`backend/.env.example`, `backend/config/queue.php`, `backend/config/cache.php`, `backend/database/migrations/0001_01_01_000001_create_cache_table.php`, `backend/database/migrations/0001_01_01_000002_create_jobs_table.php`). Redis, Beanstalkd, DynamoDB cache, and other Laravel drivers are configurable but not active defaults (`backend/config/queue.php`, `backend/config/cache.php`, `backend/config/database.php`).
- **Filesystem/session:** Local filesystem and database session drivers are configured by default (`backend/.env.example`, `backend/config/filesystems.php`, `backend/config/session.php`); no external object-storage integration is used by application workflow code (`backend/app/`).

## Authentication & Identity
- Public API routes have no user-authentication middleware; access is controlled by URL validation and IP rate limits—five run creations per hour and 30 stream connections per minute (`backend/routes/api.php`, `backend/app/Providers/AppServiceProvider.php`, `backend/app/Http/Requests/StoreRunRequest.php`).
- The Laravel scaffold retains session-based `web` authentication backed by the Eloquent `User` model and password-reset tables, but no login routes or third-party identity provider are integrated into the workflow API (`backend/config/auth.php`, `backend/routes/web.php`, `backend/database/migrations/0001_01_01_000000_create_users_table.php`). Sanctum, OAuth, SSO, and social-login packages are absent (`backend/composer.json`).
- Outbound identity is service-token based: optional GitHub bearer token and required OpenAI/OpenRouter API key (`backend/config/services.php`). Browser CORS has `supports_credentials` disabled (`backend/config/cors.php`).

## Monitoring & Observability
- Laravel logging uses a configurable Monolog stack, defaulting locally to a single file; daily, stderr, syslog, Slack webhook, and Papertrail channels are available configuration options (`backend/config/logging.php`, `backend/.env.example`). Application failures log the run ID and exception class while storing a safe error message on the run (`backend/app/Services/RunExecutor.php`).
- Laravel Pail is installed and included in the concurrent development command for live log viewing (`backend/composer.json`). Production warns if `LOG_LEVEL=debug` (`backend/app/Providers/AppServiceProvider.php`).
- No active Sentry, Telescope, Pulse, Nightwatch, APM, or metrics integration is installed; test configuration explicitly disables Pulse, Telescope, and Nightwatch flags (`backend/composer.json`, `backend/phpunit.xml`). Slack/Papertrail entries are framework configuration capabilities, not evidence that either service is provisioned (`backend/config/logging.php`).
- `GET /api/health` provides a minimal JSON liveness endpoint (`backend/routes/api.php`). Progress is observable through run status and SSE, not a monitoring vendor (`backend/app/Http/Controllers/RunController.php`).

## CI/CD & Deployment
- GitHub Actions runs on pushes and pull requests to `main`, building the Node 22 frontend and validating the PHP 8.4 backend via migration/seed, Pint, and PHPUnit (`.github/workflows/ci.yml`). It performs CI only; no deployment job or webhook is defined (`.github/workflows/ci.yml`).
- Production targets Laravel Cloud with `backend/` as application root; deployment requires migrations and a dedicated `php artisan queue:work --sleep=1 --tries=2 --timeout=120` worker (`backend/README.md`, `AGENTS.md`). The root Vite SPA is a separate deployment and requires SPA fallback plus frontend/API origin configuration (`backend/README.md`).
- Laravel Cloud CLI deployment and post-deploy monitoring are documented, but no checked-in Cloud manifest or automated release workflow was found (`backend/README.md`, `.github/workflows/ci.yml`).

## Environment Configuration
- **Required backend:** `APP_KEY` and an AI credential (`OPENAI_API_KEY`, or `OPENROUTER_API_KEY` for the alternate endpoint) (`backend/.env.example`, `backend/config/services.php`). Production additionally needs `APP_ENV=production`, `APP_DEBUG=false`, durable `DB_*`/`DATABASE_URL`, `CACHE_STORE`, and non-`sync` `QUEUE_CONNECTION` (`backend/README.md`).
- **Optional backend:** `GITHUB_TOKEN`, `AI_BASE_URL`, `AI_MODEL`/`OPENAI_MODEL`, `AI_SITE_URL`, `OPENAI_TIMEOUT`, `CORS_ALLOWED_ORIGINS`, and logging settings (`backend/.env.example`, `backend/config/services.php`, `backend/config/cors.php`, `backend/config/logging.php`).
- **Frontend:** `VITE_API_BASE_URL` selects the Laravel API, `VITE_PUBLIC_APP_URL` generates report share links, and `VITE_DEMO_MODE=true` enables simulation (`.env.example`, `src/lib/api.js`, `src/main.jsx`). Vite exposes these at build time, so deployment environments must build with the correct values (`package.json`).
- The stock Laravel service config includes Postmark, Resend, SES, and Slack-notification variables, but no application code invokes those services and no matching packages are declared (`backend/config/services.php`, `backend/composer.json`).

## Webhooks & Callbacks
- No inbound webhook or OAuth callback routes are defined; API routes consist of health, launcher/flow listings, run/execution creation, status, and streams (`backend/routes/api.php`, `backend/routes/web.php`).
- Outbound webhooks are not part of workflow execution. Laravel's optional Slack logging channel can send to `LOG_SLACK_WEBHOOK_URL` if configured, but it is not enabled by default (`backend/config/logging.php`, `backend/.env.example`).
- Run updates dispatch an internal Laravel `RunProgressed` event, but current SSE does not consume an event bus; it polls the database once per second for up to about 55 seconds (`backend/app/Events/RunProgressed.php`, `backend/app/Http/Controllers/RunController.php`, `doc/adr/0013-sse-run-stream-via-database-polling.md`). The browser receives `progress`, `completed`, and `failed` SSE events through `EventSource` (`src/lib/api.js`).

---
*Integration audit: 2026-07-12*
