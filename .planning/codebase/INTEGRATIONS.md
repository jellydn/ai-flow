# Integrations

> External services, APIs, databases, and auth providers consumed by ai-flow.

## AI providers (multi-provider registry)

All providers implement `App\Contracts\AIProviderInterface` (`backend/app/Contracts/AIProviderInterface.php`):
- `id(): string` — provider identifier
- `models(): array` — available models
- `defaultModel(): string` — config-resolved default
- `verifyCredential(string $key): bool` — minimal API call to validate key
- `generate(...): array` — structured JSON generation (model override supported)

Provider IDs are sourced from `App\Support\AiProviderRegistry::ids()` (not a config array). The registry is bound in the container (`AppServiceProvider`) and resolves provider instances + models.

| Provider | Class | Config keys | Default model |
|----------|-------|-------------|---------------|
| OpenAI | `App\Services\OpenAIProvider` | `OPENAI_API_KEY`, `AI_BASE_URL`, `OPENAI_MODEL` | `gpt-4o-mini` (`.env.example` bumps to `gpt-5`) |
| OpenRouter | `App\Services\OpenRouterProvider` | `OPENROUTER_API_KEY`, `OPENROUTER_BASE_URL`, `OPENROUTER_MODEL` | `openrouter/free` |
| Anthropic | `App\Services\AnthropicProvider` | `ANTHROPIC_API_KEY`, `ANTHROPIC_MODEL` | `claude-sonnet-4-20250514` |
| Gemini | `App\Services\GeminiProvider` | `GEMINI_API_KEY`, `GEMINI_MODEL` | `gemini-2.0-flash` |

`AI_MODEL` overrides `OPENAI_MODEL`. Per-adapter model overrides take precedence. Requests may carry `provider.id`; users manage keys via `provider-credentials` (never stored on runs, never logged).

Base class `App\Services\BaseAIProvider` provides shared JSON extraction (handles code fences, prose-wrapped JSON, deep nesting) and error mapping (401/403 → safe message).

## GitHub

### GitHub REST API (`App\Services\GitHubService`)
- **Purpose:** Parse GitHub URLs, fetch repo/PR/issue context (cached 10 min via `Cache::remember`), assemble structured context for AI runs.
- **Auth:** `GITHUB_TOKEN` (PAT) via `services.github.token`; bot path uses GitHub App installation tokens (see below).
- **Endpoints:** `/repos/{owner}/{repo}` (repo, languages, readme, tree), `/pulls/{n}`, `/pulls/{n}/files`, `/issues/{n}`, `/issues/{n}/comments`.
- **Caching:** `github:{sha1(url)}` key, 10-min TTL.

### GitHub App / Bot (`App\Services\GitHubBotService` + `ProcessGitHubBotCommandJob`)
- **Purpose:** Listen for `issue_comment.created` webhooks, parse `@ai-flow {review|plan|explain|doctor}` commands, post/update progress + result comments.
- **Auth:** GitHub App JWT (RS256, base64url segments per RFC 7515) → installation token. `botClient(?int $installationId)` prefers the installation ID from the webhook payload; falls back to PAT; finally unauthenticated.
- **Webhook verification:** `App\Http\Middleware\VerifyGitHubWebhook` — HMAC-SHA256 signature check against `GITHUB_WEBHOOK_SECRET`.
- **Per-repo config:** Reads `.github/ai-flow.yml` from the repo (cached 5 min, installation-scoped cache key). Missing file ⇒ all launchers enabled.
- **Config:** `backend/config/github-bot.php` — `app_id`, `app_private_key`, `webhook_secret`, `poll_max_seconds` (150), `poll_interval_seconds` (5), `job_timeout` (60).

### GitHub Trending (`App\Services\GitHubTrendingService`)
- Scrapes `https://github.com/trending`, parses repos, caches daily + stale-then-fresh fallback.

## Databases

| Store | Driver | Use |
|-------|--------|-----|
| Primary DB | SQLite (local: `database/database.sqlite`), Postgres/MySQL (prod) | App data, sessions, cache, queue |
| Cache | `database` (default), Redis/Memcached supported | GitHub context, repo config, trending, run-progress version |
| Queue | `database` (default), Redis/SQS supported | `ExecuteLauncherJob`, `ProcessGitHubBotCommandJob` |

**Production guardrails** (`AppServiceProvider::boot()`):
- SQLite in production → `RuntimeException`
- Postgres without TLS (`DB_SSLMODE` not `require`/`verify-ca`/`verify-full`) → `RuntimeException`
- `QUEUE_CONNECTION=sync` in production → `RuntimeException`

**Laravel 13 note:** `turso/libsql-laravel` doesn't support Laravel 13 yet; production uses managed Postgres/MySQL, not SQLite.

## Auth

### Session-based (`web` guard)
- **Magic link:** `App\Http\Controllers\Auth\MagicLinkController` — email-based passwordless. Tokens in `magic_login_tokens` table. Mail via Resend (`resend/resend-php`). Rate limited (`magic-link`: 3/min).
- **Password:** `App\Http\Controllers\Auth\PasswordAuthController` — register/login/logout. Rate limited (`auth-login`: 10/min, `auth-register`: 5/min).

### Super-admin (Filament)
- `App\Filament\AdminPanelProvider` — admin panel at `/admin`.
- Bootstrap via `SuperAdminBootstrapSeeder` (`SUPER_ADMIN_BOOTSTRAP_EMAIL`, `SUPER_ADMIN_BOOTSTRAP_NAME`).
- `is_super_admin` flag on `users` table; `PromoteSuperAdminCommand` console command.

## Email

- **Provider:** Resend (`resend/resend-php`, `RESEND_API_KEY`)
- **Use:** Magic link emails (`App\Mail\MagicLinkMail`), super-admin bootstrap (`App\Mail\SuperAdminBootstrapMail`)
- **Default mailer:** `log` (dev); Resend in prod

## Monitoring

- **Backend:** Sentry (`sentry/sentry-laravel`, `SENTRY_LARAVEL_DSN`) — config at `backend/config/sentry.php`. Sample rates, breadcrumbs, queue/SQL tracing configurable.
- **Frontend:** `@sentry/react` v10.
- **Guard:** `AppServiceProvider::boot()` logs a warning if `LOG_LEVEL=debug` in production.

## CI/CD

| Workflow | File | Purpose |
|----------|------|---------|
| CI | `.github/workflows/ci.yml` | Backend (PHP 8.4: `composer validate`, `php artisan test`, `pint --test`) + Frontend (Node 24: `typecheck`, `lint`, `konsistent`, `build`, `test`, `npm audit --production`) |
| Staging deploy | `.github/workflows/deploy-staging.yml` | PRs from `jellydn` → Dokku (`docklight-staging.itman.fyi:ai-flow`, URL `https://ai-flow-staging.itman.fyi`) |
| Release | `.github/workflows/release.yml` | `googleapis/release-please-action@v4` on push to `main` (permissions: `contents: write`, `pull-requests: write`) |

**Production:** Laravel Cloud auto-deploys on push to `main` (stable `APP_KEY`, durable Neon Postgres `DB_SSLMODE=require`, `QUEUE_CONNECTION=database`).
