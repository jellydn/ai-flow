# Integrations

## AI Providers

### OpenAI
- **Class**: `app/Services/OpenAIProvider.php` extends `BaseAIProvider`
- **SDK**: `openai-php/client` (via Composer)
- **Config**: `OPENAI_API_KEY`, `OPENAI_MODEL` (default: `gpt-4o-mini`, `.env.example` bumps to `gpt-5`)
- **Auth**: Server key or BYOK (per-run, never stored)

### OpenRouter
- **Class**: `app/Services/OpenRouterProvider.php` extends `BaseAIProvider`
- **API**: `https://openrouter.ai/api/v1/chat/completions`
- **Config**: `OPENROUTER_API_KEY`
- **Guest runs**: Forced to `openrouter/free` model router
- **Model list**: Fetched from `/api/v1/models` at boot, cached

### Anthropic
- **Class**: `app/Services/AnthropicProvider.php` extends `BaseAIProvider`
- **API**: `https://api.anthropic.com/v1/messages`
- **Config**: `ANTHROPIC_API_KEY`, `ANTHROPIC_MODEL` (default: `claude-sonnet-4-20250514`)
- **Note**: Uses Messages API, not Completions

### Gemini
- **Class**: `app/Services/GeminiProvider.php` extends `BaseAIProvider`
- **Config**: `GEMINI_API_KEY`, `GEMINI_MODEL` (default: `gemini-2.0-flash`)

### Provider Registry
- **Class**: `app/Support/AiProviderRegistry.php`
- **Role**: Canonical source for provider→config mapping, API key resolution, usability checks
- **Key resolution**: `resolveApiKey()` — checks run-level BYOK → user-level saved credential → server env key
- **Provider IDs**: Sourced from `AiProviderRegistry::ids()`, not a config array

## GitHub REST API

- **Service**: `app/Services/GitHubService.php`
- **Purpose**: Fetch repository context (file tree, README, directory structure) for AI report generation
- **Auth**: `GITHUB_TOKEN` (optional, recommended for rate limits)
- **Caching**: Context is cached to reduce API calls
- **Parsing**: Validates and parses GitHub URLs (repo, PR, issue), strips `.git` suffix
- **Input constraint**: Only `https://github.com/...` URLs accepted

## Email (Resend)

- **SDK**: `resend/resend-php`
- **Config**: `RESEND_API_KEY`
- **Use cases**:
  - Magic link authentication emails
  - Super admin bootstrap password emails
- **Rate limiting**: Magic link endpoint: 3 requests/min/IP

## Error Monitoring (Sentry)

- **SDK**: `sentry/sentry-laravel` (backend), `@sentry/react` (frontend)
- **Config**: `SENTRY_LARAVEL_DSN`, `VITE_SENTRY_DSN`
- **Behavior**: No-op when DSNs are unset (optional integration)

## Database

| Environment | Driver | Config |
|---|---|---|
| Development | SQLite | `database/database.sqlite` |
| Production | PostgreSQL (Neon) | `DB_CONNECTION=pgsql`, `DB_SSLMODE=require` |
| Production (alt) | MySQL | Standard Laravel MySQL config |

- **Migrations**: Standard Laravel migrations in `database/migrations/`
- **Queue**: Database driver (`QUEUE_CONNECTION=database`)
- **Cache**: File (dev), configurable for production

## Authentication

### Session-based (Laravel Sanctum cookie)
- **Views**: `routes/auth.php` — register, login, magic-link, logout
- **Methods**: Password (`POST /auth/login`, `POST /auth/register`) + Magic link (`POST /auth/magic-link`, `GET /auth/magic-link/{token}`)
- **SPA**: Same-origin cookie (`credentials: include`)
- **Model**: `app/Models/User.php` — standard Laravel user with `is_super_admin` flag

### Super Admin (Filament)
- **Route**: `/admin` (excluded from SPA catch-all)
- **Access**: Requires `users.is_super_admin = true`
- **Bootstrap**: `php artisan user:promote-super-admin` or auto-seeded via `SUPER_ADMIN_BOOTSTRAP_EMAIL`
- **Panel**: `app/Providers/Filament/AdminPanelProvider.php`

## Admin Panel (Filament)

- **Package**: `filament/filament: ^5.0`
- **Resources**: Users (`Filament/Resources/Users/`), Launchers (`Filament/Resources/Launchers/`)
- **Pages**: List, Create, Edit for both resources
- **Assets**: `php artisan filament:assets` (not committed)
