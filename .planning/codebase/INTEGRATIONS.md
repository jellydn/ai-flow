# Integrations

**Analysis Date:** 2026-07-13

## AI Providers

The app supports multiple AI backends behind a common `AIProviderInterface` (`backend/app/Contracts/AIProviderInterface.php`).

| Provider | Class | API | Auth | Config Path |
|----------|-------|-----|------|-------------|
| OpenAI | `OpenAIProvider` | `/v1/chat/completions` | `Bearer {key}` header | `config/services.php` → `services.openai` |
| Anthropic | `AnthropicProvider` | `/v1/messages` | `x-api-key` header + `anthropic-version` | `config/services.php` → `services.anthropic` |
| Gemini | `GeminiProvider` | `/v1beta/models/{model}:generateContent` | API key in URL query string | `config/services.php` → `services.gemini` |
| OpenRouter | Via `OpenAIProvider` with `AI_BASE_URL` | OpenAI-compatible endpoint | `Bearer {key}` header | `config/services.php` → `services.openai` fallback |

**Key env vars:** `OPENAI_API_KEY`, `AI_MODEL` (default `gpt-4o-mini`), `AI_BASE_URL`, `OPENROUTER_API_KEY`, `ANTHROPIC_API_KEY`, `GEMINI_API_KEY`

**Model routing:** `App\Support\AiProviders::createProvider()` maps provider IDs to concrete classes via the Laravel container.

**Provider credentials:** Users can store their own API keys (`backend/app/Models/ProviderCredential.php`) managed via `ProviderCredentialController`. Keys are encrypted at rest (job implements `ShouldBeEncrypted`).

## GitHub Integration

**Service:** `GitHubService` (`backend/app/Services/GitHubService.php`)

| Layer | Class | Purpose |
|-------|-------|---------|
| Parser | `GitHubService::parse()` | Extracts owner, repo, type (repo/issue/PR), number → `GitHubReference` DTO |
| Fetcher | `GitHubContextFetcher` | Raw GitHub REST API calls (repo info, README, issues, PRs, files) |
| Assembler | `GitHubContextAssembler` | Shapes raw data into structured context arrays |
| Encoder | `ContextEncoder` | Bounds context to byte budget before sending to AI |
| Caching | `GitHubService::context()` | 10-minute cache keyed by `sha1(url)` |

**Auth:** Optional `GITHUB_TOKEN` env var for higher rate limits. Public repos work without token.

## Authentication

**Method:** Magic-link authentication (session-based, not token-based).

| Component | File | Purpose |
|-----------|------|---------|
| Controller | `MagicLinkController` | Request/verify magic link tokens |
| Mail | `MagicLinkMail` | Sends sign-in link email |
| Model | `User` | Eloquent model with `Authenticatable` |
| Migration | `magic_login_tokens` | Token storage table |
| Guard | `web` (session driver) | Default auth guard in `config/auth.php` |

## Mail / Notifications

| Provider | Config | Notes |
|----------|--------|-------|
| Postmark | `config/services.php` | Mail driver option |
| Resend | `config/services.php` | Mail driver option |
| AWS SES | `config/services.php` | Mail driver option |
| Slack | `config/services.php` | Notification webhook URL |

## Deployment Platforms

| Platform | Config File | Architecture |
|----------|-------------|--------------|
| **Dokku (VPS)** | `backend/DOKKU_DEPLOY.md`, `Dockerfile`, `Procfile` | Docker build → Nginx + PHP-FPM + supervisor; `web` + `worker` process types |
| **Laravel Cloud** | `backend/CLOUD_DEPLOY.md` | Deploys `backend/` as app root; requires `npm run build` during deploy |

## Database

| Environment | Driver | Notes |
|-------------|--------|-------|
| Local dev | SQLite (`database/database.sqlite`) | Auto-created; no setup needed |
| Production | Postgres or MySQL | Laravel Cloud Serverless Postgres or managed MySQL; SQLite forbidden via `AppServiceProvider` guard |
| Future | Turso/libsql | Connection config present but package doesn't support L13 yet |

---

*Integrations analysis: 2026-07-13*
