# Integrations

## AI Providers

Four providers implement `AIProviderInterface` via `BaseAIProvider` (abstract base):

| Provider | Adapter | Auth | Key Features |
|----------|---------|------|-------------|
| OpenAI | `OpenAIProvider` | `Bearer` token | JSON schema response format |
| OpenRouter | `OpenRouterProvider` | `Bearer` token + `HTTP-Referer`/`X-Title` | OpenAI-compatible, free tier |
| Anthropic | `AnthropicProvider` | `x-api-key` + `anthropic-version` | Prompt-only JSON (no schema) |
| Gemini | `GeminiProvider` | `?key=` in URL | System instructions in payload |

**Key resolution priority** (`AiProviderRegistry::resolveApiKey`):
1. One-time key (in-memory, transient)
2. Saved credential (`ProviderCredential`, encrypted at rest)
3. Server config (`OPENAI_API_KEY`, etc.)

**Provider IDs** sourced from `AiProviderRegistry::ids()`, not a config array.

## GitHub REST API

- **Purpose**: Parse GitHub URLs, fetch repository/PR/issue context
- **Auth**: `GITHUB_TOKEN` (optional, avoids rate limiting)
- **Caching**: 10-minute cache for context fetches
- **URL validation**: HTTPS-only enforced; malformed URLs → `UserFacingRunException`
- **Error mapping**: `GitHubService::mapRequestException()` maps HTTP status → typed exception
  - 404 → `UserFacingRunException` (PR/Issue/Repo not found)
  - 403 → `UserFacingRunException` (rate limit, suggests `GITHUB_TOKEN`)
  - 401 → `UserFacingRunException` (auth failed)
  - Other → `RuntimeException`

## Email (Magic Links)

- **Provider**: Resend (`resend/resend-php` ^1.5)
- **Config**: `RESEND_API_KEY`
- **Flow**: `POST /auth/magic-link` → email with signed token → `GET /auth/magic-link/{token}` → session cookie

## Error Monitoring

- **Provider**: Sentry (`sentry/sentry-laravel` ^4.26)
- **Config**: `SENTRY_LARAVEL_DSN`, `SENTRY_TRACES_SAMPLE_RATE`
- **Frontend**: `@sentry/react` ^10.65.0, 0.1 sample rate prod, 0 dev
- **Excluded from Sentry**: `UserFacingRunException` (expected user errors)
- **Reported**: `RuntimeException`, `ConnectionException`, `Throwable`

## Admin Panel

- **Provider**: Filament (`filament/filament` ^5.0)
- **Access**: `User::is_super_admin === true` only
- **Panel**: `/admin` route, `admin` guard

## BYOK Credential Storage

- **Encryption**: `CredentialCipher` (AES-256-CBC)
- **Key hierarchy**: `CREDENTIAL_ENCRYPTION_KEY` → `APP_KEY` fallback
- **Masked display**: `sk-abcd...9X2A` (prefix 4 + suffix 4)
- **Never exposed**: `encrypted_api_key`, `encrypted_base_url` in `$hidden`

## Rate Limiters

Defined in `AppServiceProvider::boot()`:

| Limiter | Limit | Key |
|---------|-------|-----|
| `runs` | 5/hr (configurable) | Per IP |
| `runs-stream` | 30/min (configurable) | Per IP |
| `magic-link` | 3/min (configurable) | Per IP + email |
| `auth-login` | 10/min (configurable) | Per IP + email |
| `auth-register` | 5/min (configurable) | Per IP |
| `credentials` | 10/min (configurable) | Per user ID |
