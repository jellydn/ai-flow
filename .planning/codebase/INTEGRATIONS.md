# External Integrations

## AI Providers (BYOK + Server-Config)

All providers implement `App\Contracts\AIProviderInterface` and extend `App\Services\BaseAIProvider` (ADR-0017, ADR-0022). The base owns the HTTP lifecycle (key resolution, timeout, retry, 401/403 → "Invalid API key.", non-success → "AI provider request failed (HTTP {status}).", `ConnectionException` → "Unable to reach the AI provider..."). Each subclass declares its shape via protected hooks: `configureRequest()`, `endpoint()`, `buildPayload()`, `extractContent()`, `verifyEndpoint()`, `configKey()`, `defaultModel()`, `systemMessage()`.

| Provider ID | Class | Config Key | Default Model | Auth Mechanism |
|---|---|---|---|---|
| `openai` | `App\Services\OpenAIProvider` | `services.openai.key` | `gpt-4o-mini` (override `OPENAI_MODEL`/`AI_MODEL`) | `Authorization: Bearer {key}` header; uses `json_schema` response format |
| `openrouter` | `App\Services\OpenRouterProvider` | `services.openai.openrouter_key` | `openrouter/free` (guest) | `Authorization: Bearer {key}` + `HTTP-Referer` + `X-Title` headers; OpenAI-compatible endpoint |
| `anthropic` | `App\Services\AnthropicProvider` | `services.anthropic.key` | `claude-sonnet-4-20250514` | `x-api-key: {key}` header + `anthropic-version` header; prompt-only JSON instruction (no native schema) |
| `gemini` | `App\Services\GeminiProvider` | `services.gemini.key` | `gemini-2.0-flash` | `?key={key}` query param baked into endpoint URL; system instructions in payload |

### Key Resolution Priority (`AiProviderRegistry::resolveApiKey`)

1. One-time key (`provider.api_key` in request) — transient, never stored on run
2. Saved credential (`provider_credential_id` → decrypt via `CredentialCipher`)
3. Server config fallback (`config('services.{provider}.key')`)

### Provider Registry

`App\Support\AiProviderRegistry` — singleton bound in `AppServiceProvider::register()`.
- `ids()`: `['openai', 'openrouter', 'anthropic', 'gemini']` (hardcoded `PROVIDERS` const, not config)
- `get($id, $apiKey)`: instantiates adapter with optional injected key
- `list()`: cached metadata `{id, name, models}` for UI provider picker
- `defaultModel($id)`: delegates to adapter (single source of truth — ADR-0022)
- `resolveModel(...)`: validates requested model against allowed list; authenticated users may pass custom model names matching `^[A-Za-z0-9][A-Za-z0-9._:/-]*$`
- `hasUsableKey(...)`: gate for run launch validation

### Guest vs Authenticated

- **Guests** (`StoreRunRequest::prepareForValidation` when `! $this->user()`): forced to `openrouter` provider + `openrouter/free` model. `LaunchParameters::isGuestViolationFor()` blocks other providers.
- **Authenticated users**: free choice of provider/model; may use saved credentials or one-time keys (mutually exclusive — `hasCredentialKeyConflict()`).

## GitHub REST API

**Service**: `App\Services\GitHubService` (single class, replaces former `GitHubContextFetcher` + `GitHubContextAssembler`)

- **Base URL**: `https://api.github.com`
- **Auth**: optional `GITHUB_TOKEN` bearer (rate-limit relief); unauthenticated calls work but are heavily rate-limited
- **HTTP client**: `Http::baseUrl(...)->acceptJson()->withUserAgent('ai-flow')->timeout(15)->retry(2, 200, null, false)`
- **Cache**: `Cache::remember('github:'.sha1($url), 10 minutes, ...)` — keyed by source URL
- **Timeout**: 15s per request; 2 retries with 200ms backoff

### URL Parsing (`GitHubService::parse`)

Accepts only `https://github.com` / `https://www.github.com` URLs. Returns `App\Data\GitHubReference` DTO (readonly: `owner`, `repo`, `type`, `number`).

Supported types:
- `repository`: `/{owner}/{repo}`
- `pull_request`: `/{owner}/{repo}/pull/{number}`
- `issue`: `/{owner}/{repo}/issues/{number}`

Throws `InvalidArgumentException` for malformed/unsupported URLs (caught in `RunExecutor` → `UserFacingRunException`).

### Fetch Endpoints (`GitHubService::fetchRaw`)

| Type | Endpoints |
|---|---|
| All | `GET /repos/{owner}/{repo}`, `GET /repos/{owner}/{repo}/languages`, `GET /repos/{owner}/{repo}/readme` (404 tolerated), `GET /repos/{owner}/{repo}/git/trees/{default_branch}?recursive=1` |
| PR | `GET /repos/{owner}/{repo}/pulls/{number}`, `GET .../pulls/{number}/files?per_page=50`, `GET .../issues/{number}/comments?per_page=30` |
| Issue | `GET /repos/{owner}/{repo}/issues/{number}`, `GET .../issues/{number}/comments?per_page=30` |

### Error Mapping (`GitHubService::mapRequestException`)

| HTTP Status | Mapped To |
|---|---|
| 404 | `UserFacingRunException` with context-specific message (PR #X not found / Issue #X not found / Repository private/not found) |
| 403 | `UserFacingRunException` — rate limit / access denied; suggests `GITHUB_TOKEN` |
| 401 | `UserFacingRunException` — auth failed; check `GITHUB_TOKEN` |
| Other | `RuntimeException` — `GitHub API request failed (HTTP {status}).` |

### Context Assembly (`GitHubService::assemble`)

Uses `App\Services\ContextBudget` constants for truncation:
- `FETCH_README_LIMIT`, `FETCH_FILE_TREE_LIMIT`, `FETCH_DIFF_LIMIT`, `FETCH_CHANGED_FILES_LIMIT`, `FETCH_PR_COMMENT_BODY_LIMIT`, `FETCH_PR_COMMENTS_LIMIT`, `FETCH_ISSUE_COMMENT_BODY_LIMIT`, `FETCH_ISSUE_COMMENTS_LIMIT`

## GitHub Trending (External Scrape)

- **Service**: `App\Services\GitHubTrendingService` (referenced by `TrendingRepositoryController`)
- **Purpose**: daily top-trending GitHub repos for home page
- **Endpoint**: `GET /api/trending-repositories`

## Authentication

### Session-Based (Primary)

- **Guard**: `web` (config `config/auth.php`), Eloquent user provider
- **Driver**: session (file in dev, database/redis in prod)
- **CSRF**: `XSRF-TOKEN` cookie → `X-XSRF-TOKEN` header (frontend `lib/http.ts`); fallback `X-CSRF-TOKEN` from `<meta name="csrf-token">`
- **Session lock release**: `RunController::stream` calls `session()->save()` before SSE loop to avoid blocking same-user requests

### Magic Link (`App\Http\Controllers\Auth\MagicLinkController`)

- **Request**: `POST /auth/magic-link` (rate-limited `magic-link`: 3/min/IP+email)
- **Token**: 32 random bytes → `bin2hex` → SHA-256 hash stored in `magic_login_tokens` table
- **Expiry**: 15 minutes (`TOKEN_EXPIRY_MINUTES`)
- **Single use**: `used_at` timestamp
- **Delivery**: `App\Mail\MagicLinkMail` queued via Resend
- **Verify**: `GET /auth/magic-link/{token}` → redirects to `{FRONTEND_URL}/user` on success; `?auth_error={invalid|used|expired}` on failure
- **User creation**: `User::firstOrCreate(['email' => ...])` — auto-registers on first magic-link request

### Password (`App\Http\Controllers\Auth\PasswordAuthController`)

- **Register**: `POST /auth/register` (rate-limited `auth-register`: 5/min/IP)
- **Login**: `POST /auth/login` (rate-limited `auth-login`: 10/min/IP+email)
- **Logout**: `POST /auth/logout` — `Auth::guard('web')->logout()` + session invalidate + regenerate token

### Super Admin Bootstrap (`config/super_admin.php`)

- `SUPER_ADMIN_BOOTSTRAP_EMAIL` (default `dung@productsway.com`) seeded/promoted by `SuperAdminBootstrapSeeder` on migrate
- `User::canAccessPanel(Panel $panel)`: `is_super_admin === true` for `admin` panel
- Bootstrap email sent via `App\Mail\SuperAdminBootstrapMail`

## Database

### SQLite (Dev / CI / Tests)

- `DB_CONNECTION=sqlite`, `database/database.sqlite` (gitignored)
- Tests: in-memory `:memory:` (per `phpunit.xml`)
- **Not allowed in production**: `AppServiceProvider::boot()` throws if `database.default === 'sqlite'` in production HTTP context

### PostgreSQL (Production)

- Neon Postgres on Laravel Cloud; `DB_URL` on Dokku (not Dokku's `DATABASE_URL`)
- **TLS required**: `AppServiceProvider::boot()` throws if `DB_SSLMODE` not in `['require', 'verify-ca', 'verify-full']` for external hosts (those containing `.`). Internal Docker hosts (e.g., `dokku-postgres-ai-flow`) exempt.
- `turso/libsql-laravel` doesn't support Laravel 13 — explicitly avoided

### Queue

- `QUEUE_CONNECTION=database` (default); `sync` forbidden in production (guard in `AppServiceProvider`)
- Tables: `jobs`, `job_batches`, `failed_jobs` (migration `0001_01_01_000002_create_jobs_table.php`)
- `ExecuteLauncherJob` implements `ShouldBeEncrypted` + `ShouldQueue` (`tries=2`, `timeout=120`)

## Email — Resend

- **Mailer**: `resend` (config `config/mail.php`)
- **Env**: `RESEND_API_KEY`, `MAIL_FROM_ADDRESS`
- **Mailables**: `App\Mail\MagicLinkMail`, `App\Mail\SuperAdminBootstrapMail`
- **Dispatch**: `Mail::to(...)->queue(...)` (queued, not synchronous)

## Error Monitoring — Sentry

- **Backend**: `sentry/sentry-laravel` — captures `RuntimeException` and `Throwable` from `RunExecutor` via `\Sentry\captureException($e)` (skipped for `UserFacingRunException`)
- **Frontend**: `@sentry/react` — `Sentry.init()` in `backend/resources/ts/app.tsx`; sample rate 0.1 prod / 0 dev
- **Env**: `SENTRY_LARAVEL_DSN`, `VITE_SENTRY_DSN`
- **No-op**: when DSN unset, SDK is inert

## Admin Panel — Filament v5

- **Provider**: `App\Providers\Filament\AdminPanelProvider`
- **Route**: `/admin`
- **Access**: `User::canAccessPanel()` → `is_super_admin === true`
- **Resources**: `app/Filament/Resources/Launchers/`, `app/Filament/Resources/Users/`

## Rate Limiters (`AppServiceProvider::boot`)

| Limiter | Limit | Keyed By |
|---|---|---|
| `runs` | 5/hour | IP |
| `runs-stream` | 30/minute | IP |
| `magic-link` | 3/minute | IP + email |
| `auth-login` | 10/minute | IP + email |
| `auth-register` | 5/minute | IP |
| `credentials` | 10/minute | user id (`CREDENTIAL_VERIFY_PER_MINUTE`) |

## Credential Encryption — `App\Security\CredentialCipher`

- **Key**: `CREDENTIAL_ENCRYPTION_KEY` (dedicated) → falls back to `APP_KEY` if empty
- **Cipher**: `AES-256-CBC` (config `app.cipher`); `base64:` prefix stripped (same format as `APP_KEY`)
- **Operations**: `encrypt(string) -> string`, `decrypt(string) -> string`, `mask(string) -> string` (e.g., `sk-abcd...9X2A`)
- **Contract**: plaintext must not be stored, logged, serialized, or returned in API responses
- **Empty-input guard**: `encrypt('')` throws `RuntimeException`

## Webhooks / Incoming

- None. All integrations are outbound (GitHub fetch, AI generate, email send).
