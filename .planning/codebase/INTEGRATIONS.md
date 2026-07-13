# External Integrations

**Analysis Date:** 2026-07-13

## APIs & External Services

**AI providers (outbound REST via Laravel `Http` facade):**
- **OpenAI-compatible Chat Completions** — default run generation with JSON Schema `response_format`
  - Implementation: `backend/app/Services/OpenAIProvider.php`
  - Endpoints: `{AI_BASE_URL}/chat/completions`, `{AI_BASE_URL}/models` (default base `https://api.openai.com/v1` in `backend/config/services.php`)
  - SDK/Client: none (Illuminate HTTP)
  - Auth: `OPENAI_API_KEY` or `OPENROUTER_API_KEY` → `config('services.openai.key')`
  - Model: `AI_MODEL` overrides `OPENAI_MODEL` (code default `gpt-4o-mini`; `.env.example` sets `gpt-5`)
  - Timeout: `OPENAI_TIMEOUT` (config default 30; `.env.example` 60)
  - OpenRouter: set `AI_BASE_URL=https://openrouter.ai/api/v1`; sends `HTTP-Referer` / `X-OpenRouter-Title` and `provider.require_parameters` when base URL contains `openrouter.ai`
- **Anthropic Messages API** — alternate provider for user-supplied credentials
  - Implementation: `backend/app/Services/AnthropicProvider.php`
  - Endpoints: `https://api.anthropic.com/v1/messages`, `/v1/models`
  - Auth: `ANTHROPIC_API_KEY` / per-user encrypted credential; header `x-api-key`, `anthropic-version: 2023-06-01`
  - Model: `ANTHROPIC_MODEL` (default `claude-sonnet-4-20250514`)
- **Google Gemini** — alternate provider
  - Implementation: `backend/app/Services/GeminiProvider.php`
  - Endpoints: `https://generativelanguage.googleapis.com/v1beta/models` and `:generateContent`
  - Auth: `GEMINI_API_KEY` / per-user credential as query `key=`
  - Model: `GEMINI_MODEL` (default `gemini-2.0-flash`)
- Provider catalog API: `GET /api/providers` (`backend/app/Http/Controllers/ProviderController.php`) lists openai / anthropic / gemini / openrouter metadata
- Default container binding: `AIProviderInterface` → `OpenAIProvider` (`backend/app/Providers/AppServiceProvider.php`)

**GitHub REST API:**
- Service: public HTTPS `github.com` repository / PR / issue context (no git clone)
- Implementation: `backend/app/Services/GitHubService.php`, `GitHubContextFetcher.php`, `GitHubContextAssembler.php`
- Base URL: `https://api.github.com` (repos, languages, readme, git trees, pulls, issues, comments)
- SDK/Client: none (Illuminate HTTP, UA `ai-flow`, retry 2, timeout 15s)
- Auth: optional `GITHUB_TOKEN` → `config('services.github.token')` Bearer token
- Caching: `Cache::remember` 10 minutes key `github:{sha1(url)}` (`GitHubService::context`)

**Fonts (frontend CDN):**
- Google Fonts CSS import in `backend/resources/css/app.css` (Manrope, DM Mono, Playfair Display)

**Mail (magic-link auth):**
- `MagicLinkMail` queued via Laravel Mail (`backend/app/Mail/MagicLinkMail.php`, `MagicLinkController`)
- Default mailer: `MAIL_MAILER=log` (`.env.example` pattern / `backend/config/mail.php`)
- Supported transports in config: smtp, resend (`RESEND_API_KEY`), postmark (`POSTMARK_API_KEY`), ses (`AWS_*`), log, array
- From: `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`

## Data Storage

**Databases:**
- **SQLite** — local/CI default (`DB_CONNECTION=sqlite`, `database/database.sqlite`; tests use `:memory:` in `backend/phpunit.xml`)
- **PostgreSQL** — production (Dokku Postgres plugin or **Neon** serverless Postgres); Laravel `pgsql` driver, `DB_SSLMODE=require` for external hosts
- Connection: `DB_CONNECTION`, `DB_URL` **or** `DB_HOST`/`DB_PORT`/`DB_DATABASE`/`DB_USERNAME`/`DB_PASSWORD`; Dokku’s `DATABASE_URL` must be copied to `DB_URL` (app does not read `DATABASE_URL` natively) — `backend/DOKKU_DEPLOY.md`
- Migrations prefer Neon **direct** host via `DB_DIRECT_HOST` in release script (`backend/docker/bin/release-migrate.sh`)
- Client/ORM: **Eloquent** / Laravel query builder (`backend/app/Models/*`)
- Key tables (migrations under `backend/database/migrations/`): users, magic_login_tokens, provider_credentials, launchers, runs, jobs/cache/sessions standard Laravel tables

**File Storage:**
- Local filesystem only by default (`FILESYSTEM_DISK=local` → `storage/app/private`)
- S3 disk configured in `backend/config/filesystems.php` but not required by app flows
- Built frontend assets: `backend/public/build` (gitignored; produced at build time)

**Caching:**
- Default: **database** cache store (`CACHE_STORE=database`, table `cache`) — `backend/config/cache.php`
- Used for GitHub context (10 min) and run progress versioning (`CacheRunProgressedVersion` listener)
- Testing: `array` store (`phpunit.xml`)

**Queue:**
- Default: **database** queue (`QUEUE_CONNECTION=database`, table `jobs`)
- Job: `ExecuteLauncherJob` (`backend/app/Jobs/ExecuteLauncherJob.php`)
- Production worker process in `backend/Procfile`; local `queue:listen` via `composer run dev`

## Authentication & Identity

**Auth Provider:**
- **Custom magic-link email auth** (not OAuth/SSO)
- Implementation: `backend/app/Http/Controllers/Auth/MagicLinkController.php`, routes in `backend/routes/auth.php` (included from `backend/routes/web.php`)
- Flow: `POST /auth/magic-link` (throttle `magic-link`: 3/min/IP+email) → email token (SHA-256 hashed in `magic_login_tokens`, 15 min TTL) → `GET /auth/magic-link/{token}` logs in via session (`Auth::login`, remember true) → `POST /auth/logout`
- Guard: Laravel **session** guard `web` + Eloquent `User` model (`backend/config/auth.php`)
- Session: database driver, 120 min lifetime (defaults)
- Authenticated API under `auth` middleware: `/api/user/*` run history + provider credentials (`backend/routes/api.php`)
- Public unauthenticated runs still allowed (`POST /api/runs` with IP throttle)

**Credential storage:**
- User AI API keys: `provider_credentials` table, encrypted with Laravel `Crypt` (AES-256-CBC via `APP_KEY`) through `backend/app/Security/CredentialCipher.php`
- Keys never stored on runs; masked for display

## Monitoring & Observability

**Error Tracking:**
- **None declared** in `backend/composer.json` / `composer.lock`
- Local `.env` may set `SENTRY_LARAVEL_DSN` and leftover `vendor/sentry` can exist, but Sentry is not a project dependency or bootstrapped config in-repo
- Production guards log a warning if `LOG_LEVEL=debug` (`AppServiceProvider`)

**Logs:**
- Laravel **Monolog** stack (`backend/config/logging.php`)
- Local default: `LOG_CHANNEL=stack` → `single` file `storage/logs/laravel.log`
- Docker/Dokku production: `LOG_CHANNEL=stderr` (`backend/Dockerfile`, `DOKKU_DEPLOY.md`)
- Dev: `php artisan pail` in `composer run dev`
- Run failures: `Log::error('Launcher run failed', …)` in `RunExecutor`

**Health:**
- `GET /up` (Laravel framework health in `bootstrap/app.php`)
- `GET /api/health` → `{ "status": "ok" }` (`routes/api.php`)
- Dokku healthchecks: `backend/app.json` path `/up` port 80

## CI/CD & Deployment

**Hosting:**
- **Dokku** staging: `dokku@docklight-staging.itman.fyi:ai-flow` → `https://ai-flow-staging.itman.fyi` (Dockerfile build-dir `backend`)
- **Laravel Cloud** optional production path (`backend/CLOUD_DEPLOY.md`) with Neon Postgres
- Process types: web (nginx + php-fpm via supervisord), worker (`queue:work`), release migrate/seed

**CI Pipeline:**
- GitHub Actions: `.github/workflows/ci.yml`
  - Backend job: PHP 8.4, `composer validate`, migrate sqlite, `php artisan test`, Pint `--test`
  - Frontend job: Node 24, `npm ci`, typecheck, oxlint+oxfmt, konsistent, `npm run build`, vitest
- Staging deploy: `.github/workflows/deploy-staging.yml` — force-push to Dokku on PR events when author is `jellydn`; secret `DOKKU_SSH_PRIVATE_KEY`
- Dependency updates: Renovate (`renovate.json`)
- Pre-commit: prek hooks for composer validate, pint, typecheck, oxlint, oxfmt, konsistent

## Environment Configuration

**Required env vars:**
- `APP_KEY` — encryption, sessions, credential cipher (stable across web/worker)
- `APP_ENV` / `APP_DEBUG` / `APP_URL` (and often `ASSET_URL` behind TLS)
- `OPENAI_API_KEY` — real AI workflow runs (or OpenRouter key + base URL)
- Production DB: `DB_CONNECTION=pgsql` + `DB_URL` or discrete `DB_*` + `DB_SSLMODE=require` for external hosts
- `QUEUE_CONNECTION=database` (not `sync`)
- `CACHE_STORE=database` (typical production)

**Recommended / optional:**
- `GITHUB_TOKEN` — higher GitHub API rate limits
- `OPENROUTER_API_KEY`, `AI_BASE_URL`, `AI_MODEL`, `OPENAI_MODEL`, `OPENAI_TIMEOUT`, `AI_SITE_URL`
- `ANTHROPIC_API_KEY`, `ANTHROPIC_MODEL`, `GEMINI_API_KEY`, `GEMINI_MODEL`
- `CORS_ALLOWED_ORIGINS`
- `VITE_DEMO_MODE` — browser-only demo without worker
- Mail: `MAIL_MAILER`, `MAIL_FROM_*`, provider keys if not using `log`
- `DB_DIRECT_HOST` — Neon direct host for migrations

**Secrets location:**
- Local: `backend/.env` (not committed; template `backend/.env.example`)
- Staging Dokku: `dokku config:set` (see `backend/DOKKU_DEPLOY.md`)
- Deploy SSH key: GitHub Actions secret `DOKKU_SSH_PRIVATE_KEY`
- Laravel Cloud env UI for production secrets
- User-supplied provider keys: encrypted in DB (`provider_credentials`), not env

## Webhooks & Callbacks

**Incoming:**
- None (no GitHub webhooks, Stripe, etc.)
- Auth callback is email magic-link HTTP GET, not a third-party webhook: `/auth/magic-link/{token}`

**Outgoing:**
- GitHub REST API requests during run execution
- OpenAI / OpenRouter / Anthropic / Gemini API requests during run execution
- Transactional email for magic links (mail transport)
- No outbound webhooks to customer systems

**Real-time progress (internal, not external):**
- SSE `GET /api/runs/{uuid}/stream` and alias `/api/executions/{uuid}/stream` — DB-polled via `backend/app/Services/RunStreamer.php` (~55s window); requires proxy buffering disabled and ~75s read timeout on Dokku

---

*Integration audit: 2026-07-13*
