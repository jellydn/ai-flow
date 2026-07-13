# Integrations

**Analysis Date:** 2026-07-14

## AI Providers

### OpenAI (`backend/app/Services/OpenAIProvider.php`)

- **API:** OpenAI Chat Completions (`/chat/completions`)
- **Auth:** Bearer token (`OPENAI_API_KEY` env)
- **Config:** `config/services.php` → `openai.key`, `openai.base_url`, `openai.model`
- **JSON Schema:** Uses `response_format: { type: "json_schema" }` for structured output
- **Compatibility:** Supports any OpenAI-compatible endpoint via `AI_BASE_URL` (e.g. OpenRouter free tier)

### OpenRouter (`backend/app/Services/OpenRouterProvider.php`)

- **API:** OpenRouter Chat Completions (`/chat/completions`) — OpenAI-compatible
- **Auth:** Bearer token (`OPENROUTER_API_KEY` env)
- **Config:** `config/services.php` → `openai.openrouter_key`, `openai.openrouter_base_url`, `openai.openrouter_model`, `openai.referer`
- **Headers:** Sends `HTTP-Referer` and `X-Title` for ranking/identification
- **Base URL + Referer:** Configurable via constructor, falls back to config values

### Anthropic (`backend/app/Services/AnthropicProvider.php`)

- **API:** Anthropic Messages API (`/v1/messages`)
- **Auth:** `x-api-key` header (`ANTHROPIC_API_KEY` env)
- **Config:** `config/services.php` → `anthropic.key`, `anthropic.model` (default: `claude-sonnet-4-20250514`)
- **Headers:** `anthropic-version: 2023-06-01`
- **Output:** Parses `content[0].text` as JSON (no native JSON schema mode)

### Gemini (`backend/app/Services/GeminiProvider.php`)

- **API:** Google Generative Language API (`/v1beta/models/{model}:generateContent`)
- **Auth:** API key as URL query param (`GEMINI_API_KEY` env)
- **Config:** `config/services.php` → `gemini.key`, `gemini.model` (default: `gemini-2.0-flash`)
- **Output:** Uses `generationConfig.responseMimeType: "application/json"` for structured output

### Provider Registry (`backend/app/Support/AiProviderRegistry.php`)

- **Pattern:** Central registry mapping provider IDs → adapter classes
- **Providers:** `openai`, `openrouter`, `anthropic`, `gemini`
- **Resolution:** `AiProviderRegistry::get($id, $apiKey)` instantiates the adapter with an optional API key
- **Singleton:** Registered as a singleton in `AppServiceProvider`
- **Metadata:** `list()` returns `{ id, name, models }` for each provider

## GitHub

### GitHub REST API (`backend/app/Services/GitHubContextFetcher.php`)

- **Endpoints:** `/repos/{owner}/{repo}`, `/pulls/{number}`, `/issues/{number}`, `/contents/{path}`
- **Auth:** `GITHUB_TOKEN` env (optional but recommended for rate limits)
- **Caching:** 10-minute cache keyed by `sha1(url)` via Laravel `Cache` facade
- **Error mapping:** 404 → "Repository not found", 403 → "Rate limit exceeded", 401 → "Authentication failed"

### GitHub Service (`backend/app/Services/GitHubService.php`)

- **URL parsing:** `parse($url)` → `GitHubReference` DTO (owner, repo, type: repo/issue/pr, number)
- **Context assembly:** `context($url)` → fetches + assembles via `GitHubContextFetcher` + `GitHubContextAssembler`
- **Bounding:** `ContextEncoder::encode($context)` limits total payload size

### GitHub Reference DTO (`backend/app/Data/GitHubReference.php`)

- Immutable value object: `owner`, `repo`, `type` (`repository`|`issue`|`pull-request`), `number` (nullable)
- Produced by `GitHubService::parse()`, consumed by `RunExecutor`

## Email (Magic Link Auth)

### Resend (`backend/app/Mail/MagicLinkMail.php`)

- **Provider:** Resend (`resend/resend-php`)
- **Config:** `config/services.php` → `resend.key` from `RESEND_API_KEY`
- **Template:** Markdown mail `mail.magic-link`
- **Content:** Signed URL with token, expiry notice (15 minutes)
- **Queue:** Mail is queued via `Mail::to($email)->queue(...)`

## Database

### PostgreSQL (Production)

- **Driver:** `pgsql` via `config/database.php`
- **TLS:** Enforced in production — `AppServiceProvider` checks `DB_SSLMODE` is `require`/`verify-ca`/`verify-full`
- **External providers:** Dokku Postgres plugin (staging), Laravel Cloud Serverless Postgres (prod), Neon (alternative)

### SQLite (Development)

- **Driver:** `sqlite` — `database/database.sqlite` file
- **Test:** `:memory:` (phpunit.xml)
- **Production guard:** `AppServiceProvider` throws if `sqlite` detected in production

## Authentication

### Magic Link (`backend/app/Http/Controllers/Auth/MagicLinkController.php`)

- **Flow:** Email → `POST /api/auth/magic-link` → Resend email with signed URL → `GET /auth/magic-link/{token}` → session regenerate → redirect to app
- **Tokens:** 32-byte random, SHA-256 hashed, stored in `magic_login_tokens` table, 15-min expiry, single-use
- **Rate limit:** 3/min per `IP|email` (named limiter `magic-link` in `AppServiceProvider`)
- **User creation:** `firstOrCreate` — new emails auto-create accounts
- **Guard:** Laravel `web` guard (session driver, Eloquent user provider)

### Session (`config/session.php`)

- **Driver:** `database` (sessions table)
- **Lifetime:** 120 minutes (configurable)
- **Encryption:** Session data encrypted via `APP_KEY`

## Credential Encryption

### CredentialCipher (`backend/app/Security/CredentialCipher.php`)

- **Encryption:** Laravel `Crypt::encryptString()` — AES-256-CBC authenticated encryption via `APP_KEY`
- **API:** `encrypt($plaintext)`, `decrypt($ciphertext)`, `mask($plaintext)` → `sk-abcd...9X2A`
- **Model:** `ProviderCredential` — stores `encrypted_api_key` (never plaintext), `masked_key` for display
- **Decrypt-on-use:** `ProviderCredential::decryptApiKey(CredentialCipher)` — only called inside `ExecuteLauncherJob::resolveApiKey()`

## Sentry

- **Backend:** `sentry/sentry-laravel` — captures unhandled exceptions, disabled in testing
- **Frontend:** `@sentry/react` — initialized in `resources/ts/app.tsx` with `import.meta.env.VITE_SENTRY_DSN`

## Dokku (Staging Deployment)

- **SSH host:** `docklight-staging.itman.fyi`
- **App name:** `ai-flow`
- **URL:** `https://ai-flow-staging.itman.fyi`
- **Builder:** Dockerfile (`backend/Dockerfile`)
- **Git remote:** `dokku@docklight-staging.itman.fyi:ai-flow`
- **Deploy:** `git push dokku main:main` (or CI workflow)
- **Nginx:** `proxy-buffering off`, `proxy-read-timeout 75s` for SSE

## Laravel Cloud (Production)

- **Console:** `https://cloud.laravel.com/dung-huynh-duc/ai-flow/production`
- **Deploy:** `cloud deploy ai-flow production -n`
- **App root:** `backend/` (not monorepo root)
- **Worker:** Separate queue worker process (`php artisan queue:work`)
- **SSE:** Proxy must disable buffering for `/api/runs/*/stream`
