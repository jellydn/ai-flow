# External Integrations

## AI Providers

All providers implement `AIProviderInterface` (`backend/app/Contracts/AIProviderInterface.php`) and extend `BaseAIProvider` (`backend/app/Services/BaseAIProvider.php`). Registered in `AiProviderRegistry` (`backend/app/Support/AiProviderRegistry.php`).

### OpenAI
- **Adapter**: `backend/app/Services/OpenAIProvider.php`
- **Endpoint**: `https://api.openai.com/v1/chat/completions`
- **Auth**: Bearer token via `Authorization` header
- **Default model**: `gpt-4o-mini` (configurable via `OPENAI_MODEL` or `AI_MODEL`)
- **Config keys**: `services.openai.key`, `services.openai.model`, `services.openai.timeout`
- **Features**: JSON schema enforcement via `response_format`, OpenRouter URL detection removed (ADR-0017)

### OpenRouter
- **Adapter**: `backend/app/Services/OpenRouterProvider.php`
- **Endpoint**: `https://openrouter.ai/api/v1/chat/completions`
- **Auth**: Bearer token + `HTTP-Referer` + `X-Title` headers
- **Guest provider**: Unauthenticated users default to OpenRouter with `openrouter/free` model
- **Config keys**: `services.openai.openrouter_key`, `services.openai.openrouter_model`, `services.openai.openrouter_base_url`
- **Verify endpoint**: `https://openrouter.ai/api/v1/key`

### Anthropic
- **Adapter**: `backend/app/Services/AnthropicProvider.php`
- **Endpoint**: `https://api.anthropic.com/v1/messages`
- **Auth**: `x-api-key` header + `anthropic-version: 2023-06-01`
- **Default model**: `claude-sonnet-4-20250514`
- **Config keys**: `services.anthropic.key`, `services.anthropic.model`
- **Verify endpoint**: `https://api.anthropic.com/v1/models?limit=1`
- **Note**: Relies on system prompt for JSON output (no native JSON schema enforcement)

### Google Gemini
- **Adapter**: `backend/app/Services/GeminiProvider.php`
- **Endpoint**: `https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent`
- **Auth**: API key baked into URL as `?key=` query parameter
- **Default model**: `gemini-2.0-flash`
- **Config keys**: `services.gemini.key`, `services.gemini.model`
- **Note**: Relies on system prompt for JSON output (no native JSON schema enforcement)

## GitHub REST API

- **Service**: `backend/app/Services/GitHubService.php`
- **Context fetcher**: `backend/app/Services/GitHubContextFetcher.php`
- **Context assembler**: `backend/app/Services/GitHubContextAssembler.php`
- **Trending**: `backend/app/Services/GitHubTrendingService.php`
- **Endpoint**: `https://api.github.com`
- **Auth**: Optional `GITHUB_TOKEN` for higher rate limits
- **Features**:
  - Parses GitHub URLs into owner/repo references (`GitHubReference` DTO)
  - Fetches repository context (tree, README, recent commits) with cache
  - No git clone — REST-only context gathering (ADR-0010)
  - Context budget shared via `ContextBudget` constants (`backend/app/Services/ContextBudget.php`)
  - Truncation via `ContextEncoder` to stay within AI token limits
- **Rate limiting**: Respects GitHub API limits; `GITHUB_TOKEN` recommended in production

## Email (Resend)

- **Package**: `resend/resend-php` ^1.5
- **Usage**: Magic link emails (`backend/app/Mail/MagicLinkMail.php`), super admin bootstrap (`backend/app/Mail/SuperAdminBootstrapMail.php`)
- **Config**: `services.resend.key`

## Error Tracking (Sentry)

- **Backend**: `sentry/sentry-laravel` ^4.26 (`backend/config/sentry.php`)
- **Frontend**: `@sentry/react` ^10.65.0
- **Usage**: Exception capture in `ExecuteLauncherJob`, `RunExecutor`

## Admin Panel (Filament)

- **Package**: `filament/filament` ^5.0
- **Provider**: `backend/app/Providers/Filament/AdminPanelProvider.php`
- **Resources**:
  - `LauncherResource` — manage workflow templates (slug, prompt, active status)
  - `UserResource` — manage users (create, edit, promote super admin)
- **Access**: Super admin only (guarded by `config/super_admin.php` emails list)
- **ADR**: 0021

## Caching

- **Driver**: File (dev) or Redis (production)
- **GitHub context cache**: `GitHubContextFetcher` caches repository tree/readme/commits
- **Run progressed version**: `CacheRunProgressedVersion` listener tracks SSE polling version

## Queue

- **Driver**: Database (never `sync` in production)
- **Job**: `ExecuteLauncherJob` (`backend/app/Jobs/ExecuteLauncherJob.php`)
- **Worker config**: `--sleep=1 --tries=2 --timeout=120`
- **Failed jobs**: Stored in `failed_jobs` table (database-uuids)
- **Reaper**: `ReapStuckRuns` console command cleans up stalled runs

## No External Auth Provider

Authentication is entirely self-hosted:
- **Magic link** (ADR-0015): `MagicLinkController` → `MagicLinkMail` via Resend
- **Email/password** (ADR-0019): `PasswordAuthController` with Laravel session guard
- **Session-based**: `web` guard with `session` driver
- No OAuth, no social login, no JWT
