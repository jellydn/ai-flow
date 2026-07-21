# Architecture

> System patterns, layers, data flow, and abstractions for ai-flow.

## Pattern

**Monolith with queue-offloaded AI work.** A single Laravel 13 app serves a React 19 SPA (Vite) and a JSON API. Slow/IO work (AI generation, GitHub fetching, bot polling) runs on the queue — never in the HTTP request cycle.

```
┌─────────────┐     ┌──────────────────────────────────────────────┐
│  React SPA  │────▶│  Laravel API (routes/api.php)                │
│ (Vite/TS)   │     │  ┌────────────┐  ┌───────────────────────┐  │
└─────────────┘     │  │ Controllers│─▶│ Services (sync part)  │  │
                    │  └─────┬──────┘  └───────────────────────┘  │
                    │        │ dispatch                              │
                    │  ┌─────▼──────┐  ┌───────────────────────┐  │
                    │  │   Jobs     │─▶│ RunExecutor / AI / GH │  │
                    │  │ (queue)    │  └───────────────────────┘  │
                    │  └────────────┘                              │
                    └──────────────────────────────────────────────┘
                                    │
                              ┌─────▼─────┐
                              │ Database  │ (Postgres/MySQL prod)
                              └───────────┘
```

## Core data flow

### 1. Run creation (HTTP → queue)

```
POST /api/runs → RunController::store → StoreRunRequest (validates: hasUsableKey, isGuestViolationFor, isModelAllowed)
  → Run::create (status=queued, uuid) → ExecuteLauncherJob::dispatch
  → returns 202 + {uuid}
```

### 2. Run execution (queue worker)

```
ExecuteLauncherJob::handle (ShouldBeEncrypted — byok key encrypted in payload)
  → RunExecutor::execute
    → GitHubService::context(url)        (cached 10 min)
    → ContextEncoder::encode             (budget tiers: small/medium/large)
    → AIProviderInterface::generate      (JSON schema → JsonSchemaValidator)
    → Run::update (status=completed, result=json)
  → RunProgressed event → CacheRunProgressedVersion listener (cache version key)
```

### 3. Run polling (SSE, DB-backed)

```
GET /api/runs/{uuid}/stream → RunStreamer::stream (generator)
  → polls DB (~55s window), yields SSE events keyed by cached progress version
  → terminal status (completed/failed) ends stream
```

### 4. GitHub bot (webhook → two-phase job)

```
POST /api/github/webhooks → VerifyGitHubWebhook (HMAC) → GitHubWebhookController
  → GitHubBotService::parseCommand + isLauncherEnabled (cached .github/ai-flow.yml)
  → ProcessGitHubBotCommandJob::dispatch (returns 202)

Phase 1 (initialize): postComment(queued) → createAndExecuteRun (hasUsableKey guard)
  → ExecuteLauncherJob::dispatch → dispatchContinuation (delayed self)
Phase 2 (continue):   check Run status
  → terminal → postResultComment (error sanitized for public runs)
  → deadline exceeded → timeout comment
  → else → dispatchContinuation (delayed self)
```

## Layers

| Layer | Location | Responsibility |
|-------|----------|----------------|
| HTTP | `app/Http/Controllers/`, `app/Http/Requests/`, `app/Http/Middleware/` | Thin controllers, form request validation, rate limiting, webhook verification |
| API Resources | `app/Http/Resources/` | JSON serialization (`RunResource`, `UserResource`, etc.) |
| Services | `app/Services/` | Business logic (AI providers, GitHub, launcher resolution, run execution, streaming) |
| Contracts | `app/Contracts/` | `AIProviderInterface`, `LauncherInterface`, `LauncherSource` |
| Jobs | `app/Jobs/` | Queue work (`ExecuteLauncherJob`, `ProcessGitHubBotCommandJob`) |
| Launchers | `app/Launchers/` | One class per workflow (`BaseLauncher` + 4 built-ins) |
| Models | `app/Models/` | Eloquent ORM (`Run`, `Launcher`, `User`, `ProviderCredential`, `UserLauncher`, `UserHiddenLauncher`, `LauncherPromptOverride`) |
| Support | `app/Support/` | `AiProviderRegistry` (provider resolution, credential decryption) |
| Data | `app/Data/` | DTOs (`GitHubReference`, `ResolvedLauncher`) |
| Security | `app/Security/` | `CredentialCipher` (AES-256 encrypt/decrypt for BYOK keys) |
| Policies | `app/Policies/` | Authorization (`RunPolicy`, `UserLauncherPolicy`, `ProviderCredentialPolicy`) |
| Events/Listeners | `app/Events/`, `app/Listeners/` | `RunProgressed` → `CacheRunProgressedVersion` |
| Console | `app/Console/Commands/` | `ReapStuckRuns` (marks stuck running runs as failed), `PromoteSuperAdminCommand` |
| Filament | `app/Filament/` | Super-admin panel (Launchers + Users resources) |

## Key abstractions

### `AIProviderInterface` (`app/Contracts/AIProviderInterface.php`)
Swappable AI providers. Registry (`AiProviderRegistry`) resolves by ID; supports credential-backed keys (decrypts `ProviderCredential` via `CredentialCipher`), server config keys, and one-time keys. `BaseAIProvider` handles JSON extraction + error mapping.

### `LauncherInterface` + `BaseLauncher` (`app/Contracts/LauncherInterface.php`, `app/Launchers/BaseLauncher.php`)
Declarative workflow metadata via `BaseLauncher::make()`. Each built-in launcher (review-pr, plan-issue, explain-repository, laravel-doctor) defines prompt template + output schema. Seeded by `DatabaseSeeder`. Custom launchers created by users via API (`user_launchers` table).

### `LaunchParameters` (`app/Services/LaunchParameters.php`)
Resolves effective provider, model, and API key for a run — combining one-time keys, saved credentials, server config, and guest restrictions. `hasUsableKey()` guards the bot path (fail-fast).

### `RunStreamer` (`app/Services/RunStreamer.php`)
Generator-based SSE streaming. Polls the DB within a ~55s window, deduplicates via a cached progress version (incremented by `CacheRunProgressedVersion` listener on each `RunProgressed` event).

## Rate limiting (`AppServiceProvider::boot()`)

| Limiter | Scope | Default |
|---------|-------|---------|
| `runs` | IP | 5/hr |
| `runs-stream` | IP | 30/min |
| `magic-link` | IP\|email | 3/min |
| `auth-login` | IP\|email | 10/min |
| `auth-register` | IP | 5/min |
| `credentials` | user ID | 10/min |
| `github-webhook` | IP | 60/min |

All configurable via env (`RUNS_RATE_LIMIT_PER_HOUR`, etc. in `config/app.php`).

## Security architecture

- **BYOK credentials:** Encrypted at rest via `CredentialCipher` (AES-256, dedicated `CREDENTIAL_ENCRYPTION_KEY` falling back to `APP_KEY`). Never stored on runs, never logged. API resources exclude `encrypted_*` fields.
- **Webhook verification:** HMAC-SHA256 signature validation (`VerifyGitHubWebhook`).
- **Public run error sanitization:** `ProcessGitHubBotCommandJob::postResultComment` replaces raw `$run->error` with a generic message for public runs.
- **Production guards:** SQLite/sync-queue/no-TLS-Postgres all throw at boot in production.
