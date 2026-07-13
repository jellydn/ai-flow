# External Integrations

**Analysis Date:** 2026-07-13

> Scope: `backend/` — all external APIs, services, and platforms.

## AI Provider

| Detail | Value |
|--------|-------|
| **Provider** | OpenAI-compatible API |
| **Interface** | `App\Contracts\AIProviderInterface` |
| **Concrete** | `App\Services\OpenAIProvider` |
| **Config** | `config/services.php` (key: `openai`) |
| **Model** | `AI_MODEL` env (default: `gpt-4o-mini`) |
| **Base URL** | `AI_BASE_URL` env (supports OpenRouter: `https://openrouter.ai/api/v1`) |
| **Timeout** | `OPENAI_TIMEOUT` env (default: 60s) |
| **Auth** | `OPENAI_API_KEY` server key, or per-request `provider.api_key` (BYOK) |
| **Headers** | `HTTP-Referer`, `X-OpenRouter-Title` sent to all providers |
| **Special case** | OpenRouter gets extra `provider: {require_parameters: true}` body |
| **Test mocking** | `OpenAIProviderTest.php` |

## GitHub REST API

| Detail | Value |
|--------|-------|
| **Service** | `App\Services\GitHubService` (parse + context) |
| **Fetcher** | `App\Services\GitHubContextFetcher` (raw REST calls) |
| **Assembler** | `App\Services\GitHubContextAssembler` (shapes raw → structured context) |
| **Auth** | `GITHUB_TOKEN` env (optional, strongly recommended for rate limits) |
| **Endpoints** | Repository info, languages, README, file tree, PRs, issues, comments |
| **Caching** | 10-minute cache by `sha1(url)` via Laravel Cache |
| **Rate limits** | GitHub public API (60/hour unauthenticated, 5000/hour with token) |
| **Test mocking** | `GitHubServiceTest.php`, `GitHubContextFetcherTest.php`, `GitHubContextAssemblerTest.php` |

## Database Platforms

| Platform | Connection | Use Case |
|----------|-----------|----------|
| **SQLite** | `sqlite` | Local development, CI |
| **Neon PostgreSQL** | `pgsql` + TLS | Laravel Cloud production (`DB_SSLMODE=require`) |
| **Dokku Postgres** | `pgsql` (no TLS) | Dokku staging (internal Docker network) |
| **Turso/libsql** | `libsql` (config present, unused) | Not supported on Laravel 13; config retained for future |

## Deployment Platforms

| Platform | Details |
|----------|---------|
| **Dokku** | VPS at `docklight-staging.itman.fyi`, app `ai-flow`, Dockerfile + Procfile + app.json |
| **Laravel Cloud** | `backend/` as app root, `CLOUD_DEPLOY.md` instructions |
| **GitHub Actions** | CI: `ci.yml` (push/PR on main), Deploy: `deploy-staging.yml` (PR, author-gated) |

## SSE / Streaming

| Detail | Value |
|--------|-------|
| **Transport** | Server-Sent Events (`EventSource` in browser) |
| **Backend** | `RunStreamer` polls DB every second for up to 55s |
| **Frontend** | `useRunSubscription.ts` SSE + polling fallback (1500ms) |
| **Proxy config** | `X-Accel-Buffering: no`, `proxy-read-timeout: 75s`, `proxy-buffering: off` |

## CORS

| Setting | Value |
|---------|-------|
| **Origins** | `CORS_ALLOWED_ORIGINS` env |
| **Methods** | `*` (permissive) |
| **Headers** | `*` (permissive) |
| **Credentials** | `false` |

## Security / Secret Management

| Concern | Implementation |
|---------|---------------|
| **BYOK API keys** | Encrypted in queue via `ShouldBeEncrypted` on `ExecuteLauncherJob` |
| **Production guards** | SQLite rejected, pgsql TLS enforced (external hosts), `QUEUE_CONNECTION≠sync` enforced |
| **Rate limiting** | 5 runs/hour/IP, 30 streams/min/IP |
| **No auth** | Public API (intentional MVP design) |

---

*Integration analysis: 2026-07-13*
