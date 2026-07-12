# External Integrations

**Analysis Date:** 2026-07-12

## Third-Party Services

### Frontend (root)

**Google Fonts** (production usage, loaded from CDN)
- Served from: `https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Manrope:wght@400;500;600;700;800&family=Playfair+Display:ital,wght@1,600&display=swap`
- Usage: Imported via `@import` at the top of `src/styles.css` (line 1)
- Fonts loaded: DM Mono (400, 500), Manrope (400–800), Playfair Display (italic 600)
- No self-hosted fallback; relies on Google Fonts CDN availability

**Lucide React** (npm dependency)
- Source: npm package `lucide-react` (latest, unpinned)
- Usage: ~24 SVG icon components imported individually in `src/main.jsx`:
  `ArrowRight`, `BookOpen`, `Bot`, `Check`, `CheckCircle2`, `CircleDot`, `Clock3`, `Code2`, `Copy`, `GitFork`, `GitPullRequest`, `ListTodo`, `LoaderCircle`, `Menu`, `Newspaper`, `ShieldCheck`, `Sparkles`, `Stethoscope`, `X`, `Zap`
- Runtime: Rendered as inline SVG elements within React components

### Backend (Laravel)

**OpenAI API** (production integration via `OpenAIProvider`)
- Endpoint: `POST https://api.openai.com/v1/chat/completions`
- SDK: Laravel's `Illuminate\Support\Facades\Http` (no official OpenAI PHP SDK; raw HTTP calls)
- Auth: Bearer token from `config('services.openai.key')` (env: `OPENAI_API_KEY`)
- Default model: `gpt-4o-mini` (configurable via `OPENAI_MODEL` env)
- Timeout: 60 seconds (configurable via `OPENAI_TIMEOUT` env)
- Response format: JSON Schema with `response_format` set to `json_schema` with `strict: true`
- Usage: All AI-powered workflow analysis (code review, issue planning, repo explanation, Laravel audit)
- Error handling: Throws `RuntimeException` on HTTP failure or invalid JSON response

**GitHub REST API** (production integration via `GitHubService`)
- Endpoint: `https://api.github.com` (v3 REST API)
- SDK: Laravel's `Illuminate\Support\Facades\Http` (no Octokit/GitHub SDK; raw HTTP calls)
- Auth: Optional bearer token from `config('services.github.token')` (env: `GITHUB_TOKEN`)
  - Without token: subject to unauthenticated rate limits (60 req/hour)
  - With token: authenticated rate limits (5,000 req/hour)
- Endpoints consumed:
  - `GET /repos/{owner}/{repo}` — Repository metadata
  - `GET /repos/{owner}/{repo}/languages` — Language breakdown
  - `GET /repos/{owner}/{repo}/readme` — Repository README (base64 decoded, truncated to 20KB)
  - `GET /repos/{owner}/{repo}/git/trees/{branch}?recursive=1` — Full file tree (first 500 paths)
  - `GET /repos/{owner}/{repo}/pulls/{number}` — PR details
  - `GET /repos/{owner}/{repo}/pulls/{number}/files?per_page=50` — Changed files in PR (diff truncated to 4KB each)
  - `GET /repos/{owner}/{repo}/issues/{number}` — Issue details
  - `GET /repos/{owner}/{repo}/issues/{number}/comments?per_page=30` — PR/issue comments (truncated to 3KB each)
- Caching: Results cached via `Cache::remember()` with 10-minute TTL using SHA-1 URL hash as key
- Retry: HTTP client configured with `retry(2, 200)` — 2 retries, 200ms delay
- User-agent: `ai-launcher`
- Error handling: Throws `Illuminate\Http\Client\RequestException` on HTTP failure; `InvalidArgumentException` for malformed URLs
- URL parsing: Validates HTTPS scheme, github.com host, 2-4 path segments; supports repository, pull request, and issue URLs

## APIs & External Services

**No additional external APIs beyond OpenAI and GitHub.**
- No payment processor integration
- No webhook receivers/emitters
- No analytics service integration (Segment, GA, etc.)
- No error monitoring service (Sentry, etc.)
- No CI/CD pipeline integration (GitHub Actions, etc.)

## Data Storage

### Databases

**Default (development): SQLite**
- File-based: `backend/database/database.sqlite`
- SQLite is the default connection in `backend/config/database.php` (`'default' => env('DB_CONNECTION', 'sqlite')`)
- Create command: `touch database/database.sqlite && php artisan migrate`

**Supported production drivers:**
- MySQL (PDO driver: `pdo_mysql`)
- MariaDB
- PostgreSQL
- SQL Server

**Redis** (configured for cache/queue/session — not used by default)
- Client: `phpredis` (default)
- Connections: `default` (db 0), `cache` (db 1)
- Supports clustering, persistence

**Database Tables (migrations):**
- `users` — Standard Laravel user accounts
- `cache` + `cache_locks` — Database cache store
- `jobs` + `job_batches` + `failed_jobs` — Database queue store
- `launchers` — Workflow definitions (slug, name, description, prompt_template, input_type, output_schema JSON, class_name)
- `runs` — Run instances (UUID primary key, status, progress JSON, input JSON, source_context JSON, result JSON, error text)

### File Storage

- **Local filesystem** (default): `storage/app/private` and `storage/app/public`
- **S3** (configured, not default): `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_BUCKET`, `AWS_REGION`
- No CDN integration

### Caching

- Default driver: **database** (cache table)
- Configured drivers: array, file, memcached, redis, dynamodb, octane
- Cache prefix: `{APP_NAME}-cache-`
- Used by: GitHub API responses (10-min TTL via `Cache::remember`), Laravel framework internals

## Authentication & Identity

**Frontend:**
- No authentication
- No login/signup UI
- No API keys stored client-side

**Backend (Laravel):**
- Auth guard: `session` driver with Eloquent `User` model
- Password resets: Database token table
- Session driver: database (default), configurable to file/cookie/memcached/redis/dynamodb/array
- No OAuth, no socialite, no JWT, no API tokens
- No registration/login endpoints exposed in current routes

## Monitoring & Observability

**Error Tracking:**
- None — no Sentry, no Flare, no Bugsnag integration
- Exceptions: Laravel default logging to `storage/logs/laravel.log`

**Logging (via Monolog):**
- Default: `stack` channel → `single` file (`storage/logs/laravel.log`)
- Configured channels: single, daily, slack, papertrail, stderr, syslog, errorlog, null
- Log level: debug (configurable via `LOG_LEVEL` env)

**Health Check:**
- `GET /api/health` returns `{"status": "ok"}` — no deep dependency checks

## CI/CD & Deployment

**Hosting Target:**
- [Laravel Cloud](https://cloud.laravel.com) (production)
- Deployed from `backend/` directory as application root

**CI Pipeline:**
- None — no GitHub Actions, no Laravel Forge, no Envoyer configuration

**Deployment Commands:**
- `composer global require laravel/cloud-cli` → `cloud deploy ai-flow production -n`

**Frontend Hosting:**
- Not yet deployed; Vite dev server only
- Amp portal config: `.amp/portals/ai-launcher.json`

## Environment Configuration

### Required env vars (backend)

| Variable | Purpose |
|----------|---------|
| `APP_KEY` | Laravel encryption key (AES-256-CBC) |
| `OPENAI_API_KEY` | OpenAI API access |
| `DB_CONNECTION` | Database driver (default: sqlite) |
| `QUEUE_CONNECTION` | Queue driver (default: database; production: not `sync`) |

### Recommended env vars

| Variable | Purpose |
|----------|---------|
| `GITHUB_TOKEN` | Authenticated GitHub API access (rate limits) |

### Optional env vars

| Variable | Default | Purpose |
|----------|---------|---------|
| `OPENAI_MODEL` | `gpt-4o-mini` | OpenAI model selection |
| `OPENAI_TIMEOUT` | `60` | OpenAI request timeout (seconds) |
| `APP_ENV` | `production` | Environment name |
| `APP_DEBUG` | `false` | Debug mode |
| `APP_URL` | `http://localhost` | Application base URL |
| `DB_DATABASE` | `database/database.sqlite` | Database path/name |
| `CACHE_STORE` | `database` | Cache driver |
| `SESSION_DRIVER` | `database` | Session driver |
| `LOG_CHANNEL` | `stack` | Log channel |
| `MAIL_MAILER` | `log` | Mail driver |
| `AWS_*` | — | AWS services (S3, SES) |
| `REDIS_*` | — | Redis connections |

### Frontend

- No env vars; no `.env` files

## Rate Limiting

- `POST /api/runs` is throttled via named limiter `throttle:runs` (configured in `AppServiceProvider`)
- Default: 5 runs per hour per IP (defined in AGENTS.md)

## Webhooks & Callbacks

**Incoming:**
- None — no webhook endpoints registered in routes

**Outgoing:**
- None — no callback/notification webhooks sent

## Dependency Summary

| Integration | Type | Status | Auth |
|-------------|------|--------|------|
| Google Fonts CDN | External asset | Active (frontend) | None |
| Lucide React | npm package | Active (frontend) | None |
| OpenAI API | REST API | Active (backend) | Bearer token |
| GitHub REST API | REST API | Active (backend) | Optional bearer token |
| SQLite | Database | Active (dev default) | None |
| MySQL/MariaDB/PgSQL/SQL Server | Database | Configured (production) | Username/password |
| Redis | Cache/Queue/Session | Configured (not active by default) | Optional password |
| S3 | File storage | Configured (not active by default) | AWS IAM keys |
| Postmark / SES / Resend / SMTP | Mail | Configured (not active by default) | API keys |
| Slack | Logging / Notifications | Configured (not active by default) | Webhook URL / OAuth token |
| Laravel Cloud | Hosting | Target (not yet deployed) | Cloud CLI auth |
