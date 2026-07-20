# Integrations

External services, APIs, and third-party providers used by ai-flow.

## AI Providers (multi-provider registry)

All implement `App\Contracts\AIProviderInterface` and extend `App\Services\BaseAIProvider`. Provider IDs sourced from `App\Support\AiProviderRegistry::ids()`, not a config array.

| Provider | Class | Config block | Default model | Auth |
|----------|-------|-------------|---------------|------|
| OpenAI | `OpenAIProvider` | `services.openai` | `gpt-4o-mini` (`.env.example`: `gpt-5`) | Bearer token |
| OpenRouter | `OpenRouterProvider` | `services.openai.openrouter_*` | `openrouter/free` | Bearer token + `HTTP-Referer`/`X-Title` headers |
| Anthropic | `AnthropicProvider` | `services.anthropic` | `claude-sonnet-4-20250514` | `x-api-key` + `anthropic-version` header |
| Gemini | `GeminiProvider` | `services.gemini` | `gemini-2.0-flash` | API key in URL query param |

### Key resolution (`AiProviderRegistry::resolveApiKey`)

1. Injected key (from request `provider.api_key`)
2. Saved BYOK credential (`provider_credential_id` → `ProviderCredential` model, decrypted with `CREDENTIAL_ENCRYPTION_KEY`)
3. Server config fallback (`config('services.openai.key')`, etc.)

> Provider keys are never stored on runs, never logged. Stored credentials encrypted with dedicated `CREDENTIAL_ENCRYPTION_KEY` (falls back to `APP_KEY`).

### Shared lifecycle (`BaseAIProvider`)

- `generate()`: key resolution → request building (auth + timeout + retry) → status mapping (401/403 → "Invalid API key.") → JSON extraction → json_decode
- `verifyCredential()`: lightweight GET to `verifyEndpoint()` — key resolution, status mapping, result structure
- `extractJson()`: tolerates markdown fences (```json...```) and leading/trailing prose

## GitHub REST API

| Concern | Detail |
|---------|--------|
| Service | `App\Services\GitHubService` |
| Base URL | `https://api.github.com` |
| Auth | `GITHUB_TOKEN` env (optional but recommended for rate limits) |
| Caching | 10 min via `Cache::remember('github:'.sha1($url), ...)` |
| Timeout | 15s, retry 2x with 200ms delay |
| URL parsing | HTTPS-only, `github.com`/`www.github.com`, repo/PR/issue types |

### Fetches per reference type

- **Repository**: repo metadata, languages, README (base64-decoded), file tree (recursive)
- **Pull request**: PR metadata, changed files (patches, 50/page), PR comments (30/page)
- **Issue**: issue metadata, issue comments (30/page)

### Error mapping (`mapRequestException`)

- 404 → `UserFacingRunException` (repo/PR/issue not found)
- 403 → rate limit / access denied
- 401 → auth failed

### Context budget (`App\Services\ContextBudget`)

Limits on README length, file tree size, diff length, comment bodies — applied in `GitHubService::assemble()`.

## Email — Resend

| Concern | Detail |
|---------|--------|
| Mailer | `resend` (config `services.resend.key`) |
| From | `noreply@itman.fyi` |
| Use | Magic-link auth emails (queued; needs worker) |
| Env | `MAIL_MAILER=resend`, `RESEND_API_KEY`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` |

## Error Monitoring — Sentry

| Concern | Detail |
|---------|--------|
| Backend | `sentry/sentry-laravel ^4.26` — `SENTRY_LARAVEL_DSN` |
| Frontend | `@sentry/react ^10` — `VITE_SENTRY_DSN` |
| Filtering | Expected user/input failures and AI operational errors logged at `warning` (Sentry ignores). Unexpected `Throwable` → `Sentry\captureException()`. |

## Authentication

| Method | Detail |
|--------|--------|
| Password | `PasswordAuthController` — register/login, `throttle:auth-login` (10/min), `throttle:auth-register` (5/min) |
| Magic link | `MagicLinkController` — email link, `throttle:magic-link` (3/min), queued mail |
| Session | `database` driver, 120 min lifetime |

## Rate Limiters (`AppServiceProvider::boot`)

| Limiter | Scope | Default |
|---------|-------|---------|
| `runs` | per IP / hour | 5 (`RUNS_RATE_LIMIT_PER_HOUR`) |
| `runs-stream` | per IP / min | 30 (`RUNS_STREAM_RATE_LIMIT_PER_MIN`) |
| `magic-link` | per IP+email / min | 3 (`MAGIC_LINK_RATE_LIMIT_PER_MIN`) |
| `auth-login` | per IP+email / min | 10 (`AUTH_LOGIN_RATE_LIMIT_PER_MIN`) |
| `auth-register` | per IP / min | 5 (`AUTH_REGISTER_RATE_LIMIT_PER_MIN`) |
| `credentials` | per user / min | 10 (`CREDENTIALS_RATE_LIMIT_PER_MIN`) |
