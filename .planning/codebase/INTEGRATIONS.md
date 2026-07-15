# External Integrations

**Analysis Date:** 2026-07-15

## APIs & External Services

**AI / LLM (workflow output):**
- OpenAI Chat Completions — default provider (`backend/app/Services/OpenAIProvider.php`, `backend/config/services.php`)
- OpenRouter — OpenAI-compatible routing (`backend/app/Services/OpenRouterProvider.php`, `backend/app/Support/AiProviderRegistry.php`)
- Anthropic Messages API (`backend/app/Services/AnthropicProvider.php`)
- Google Gemini (`backend/app/Services/GeminiProvider.php`)
- SDK/Client: Laravel `Http` facade (no vendor AI SDK)
- Auth: server `OPENAI_API_KEY` / `OPENROUTER_API_KEY` / `ANTHROPIC_API_KEY` / `GEMINI_API_KEY` in `backend/config/services.php`; per-user keys via `provider-credentials` API (`backend/routes/api.php`, stored encrypted — never on run records)

**GitHub (context for launchers):**
- GitHub REST API `https://api.github.com` — repos, README, trees, PRs, issues (`backend/app/Services/GitHubContextFetcher.php`, `backend/app/Services/GitHubService.php`)
- Auth: optional `GITHUB_TOKEN` (`backend/config/services.php` → `services.github.token`)

**Email:**
- Resend — magic-link sign-in (`backend/.env.example` `MAIL_MAILER=resend`, `backend/app/Http/Controllers/Auth/MagicLinkController.php`, `resend/resend-php` in `backend/composer.json`)
- Auth: `RESEND_API_KEY`

## Data Storage

**Databases:**
- SQLite — local default (`backend/config/database.php`, `backend/.env.example` `DB_CONNECTION=sqlite`)
- PostgreSQL — staging/production (Dokku plugin or Neon; `backend/DOKKU_DEPLOY.md`, `backend/CLOUD_DEPLOY.md`)
- Connection: `DB_CONNECTION`, `DB_URL` or `DB_HOST`/`DB_*`, `DB_SSLMODE` (`backend/.env.example`)
- Client: Eloquent ORM (Laravel); migrations in `backend/database/migrations/`

**File Storage:**
- Local filesystem — `FILESYSTEM_DISK=local` (`backend/.env.example`); Laravel `storage/` and `public/`

**Caching:**
- Database cache store — `CACHE_STORE=database` (`backend/.env.example`, `backend/config/cache.php` pattern)

**Sessions:**
- Database-backed sessions — `SESSION_DRIVER=database` (`backend/.env.example`)

## Authentication & Identity

**Auth Provider:**
- Custom Laravel session auth — password register/login (`backend/routes/auth.php`, `backend/app/Http/Controllers/Auth/PasswordAuthController.php`) and email magic links (`backend/app/Http/Controllers/Auth/MagicLinkController.php`, table `magic_login_tokens` in `backend/database/migrations/2026_07_13_000002_create_magic_login_tokens_table.php`)
- SPA uses cookie session + CSRF on mutating API routes (`backend/routes/api.php` `web` middleware on `POST /api/runs`)
- Post-verify redirect: `FRONTEND_URL` (`backend/.env.example`, `backend/config/app.php`)

## Monitoring & Observability

**Error Tracking:**
- Sentry — optional when DSN set (`SENTRY_LARAVEL_DSN`, `VITE_SENTRY_DSN` in `backend/.env.example`; `backend/config/sentry.php`; `backend/resources/ts/lib/logger.ts` forwards errors to Sentry)

**Logs:**
- Laravel logging stack (`LOG_CHANNEL=stack` in `backend/.env.example`); `laravel/pail` in local `composer run dev`; job failures captured in `backend/app/Services/RunExecutor.php` via `\Sentry\captureException`

## CI/CD & Deployment

**Hosting:**
- Dokku on VPS — primary staging path (`backend/DOKKU_DEPLOY.md`, git remote `dokku`)
- Laravel Cloud — documented production alternative (`backend/CLOUD_DEPLOY.md`)

**CI Pipeline:**
- GitHub Actions — `.github/workflows/ci.yml` (PHP 8.4 tests + Pint, Node 24 typecheck/lint/konsistent/build/vitest, Playwright E2E demo + real-backend auth spec)

**Container:**
- Multi-stage `backend/Dockerfile` (Node build → PHP-FPM + nginx)
- Process types: `backend/Procfile` (`web`, `worker`, `release` migrate/seed)

## Environment Configuration

**Required env vars (workflow runs):**
- `OPENAI_API_KEY` (or user credential / OpenRouter key) — `backend/.env.example`
- `APP_KEY` — Laravel encryption

**Strongly recommended:**
- `GITHUB_TOKEN` — rate limits and private repo access
- `QUEUE_CONNECTION=database` (never `sync` in production — enforced in `backend/app/Providers/AppServiceProvider.php`)

**Production database:**
- `DB_CONNECTION=pgsql`, `DB_SSLMODE=require`, durable `DB_URL` or discrete vars (`backend/DOKKU_DEPLOY.md`)

**Optional:**
- `AI_MODEL`, `AI_BASE_URL`, `OPENAI_TIMEOUT`, provider-specific model env vars (`backend/config/services.php`)
- `RESEND_API_KEY`, `MAIL_FROM_*` for magic links
- `CORS_ALLOWED_ORIGINS` for SPA origins
- `VITE_DEMO_MODE` — browser-only simulated runs without queue (`backend/.env.example`)

**Secrets location:**
- Server/Dokku/Laravel Cloud environment config (not committed); user AI keys in DB via provider credentials API

## Webhooks & Callbacks

**Incoming:**
- None — no third-party webhook endpoints; user-facing auth callbacks are GET `/auth/magic-link/{token}` (`backend/routes/auth.php`)

**Outgoing:**
- HTTPS only to OpenAI/OpenRouter, Anthropic, Gemini, GitHub API, Resend API (queued mail)
- No outbound webhooks to customer systems

**Real-time to clients:**
- Server-Sent Events — `GET /api/runs/{run}/stream` (`backend/routes/api.php`); Dokku nginx `proxy-buffering off` and extended read timeout (`backend/DOKKU_DEPLOY.md`)

---

*Integration audit: 2026-07-15*
