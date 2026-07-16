# Architecture

## Pattern Overview

The application follows a **queue-backed MVC + service layer** architecture:

```
Browser → Laravel (routes/api.php) → Controller → validation (FormRequest)
                                                    → dispatch job (ExecuteLauncherJob)
                                                    → 202 Accepted + run UUID

Queue Worker → ExecuteLauncherJob → GitHubService (parse + cached context)
                                  → AIProviderInterface::generate (JSON schema)
                                  → JsonSchemaValidator → run.result (DB)

Browser → GET /api/runs/{uuid}/stream → SSE (DB poll, ~55s window)
```

Frontend is a **React SPA** served by Laravel via Vite: `backend/resources/views/app.blade.php` loads `backend/resources/ts/app.tsx`.

## Layer Map

| Layer | Path | Role |
|-------|------|------|
| **HTTP** | `app/Http/Controllers/`, `app/Http/Requests/`, `app/Http/Resources/` | Request validation, JSON serialization, SSE streaming |
| **Jobs** | `app/Jobs/ExecuteLauncherJob.php` | Single queued job: orchestrate GitHub → AI → result |
| **Services** | `app/Services/` | Domain logic: AI providers, GitHub parsing, context assembly, run streaming |
| **Contracts** | `app/Contracts/` | Interfaces: `AIProviderInterface`, `LauncherInterface`, `RunExecutorInterface` |
| **Models** | `app/Models/` | Eloquent models: `Run`, `Launcher`, `User`, `ProviderCredential`, `LauncherPromptOverride` |
| **Support** | `app/Support/AiProviderRegistry.php` | Provider lookup registry (service locator pattern) |
| **Launchers** | `app/Launchers/` | Workflow definitions: `BaseLauncher` + 4 concrete launchers |
| **Frontend** | `resources/ts/` | React 19 SPA: components, hooks, services, types, data |

## Key Architectural Decisions

### ADR Summary (22 ADRs in `doc/adr/`)

| ADR | Decision |
|-----|----------|
| 0001–0003 | Prototype progression: Vite+React → single-file → client-side simulation |
| 0004 | Structured report UX, not chat interface |
| 0005 | Workflow catalog as declarative metadata (seeded launchers) |
| 0006 | Amp portal for preview hosting |
| 0007 | Laravel API in `backend/` subdirectory (monorepo root) |
| 0008 | Queue-backed `ExecuteLauncherJob` (async AI + GitHub calls) |
| 0009 | Launcher classes seeded to database via `BaseLauncher::make()` |
| 0010 | GitHub REST context with cache, no git clone |
| 0011 | `AIProviderInterface` + OpenAI JSON schema enforcement |
| 0012 | Runs as UUID records with JSON columns (`input`, `result`, `progress`) |
| 0013 | SSE run stream via database polling (not WebSockets) |
| 0014 | API throttling and public unauthenticated runs |
| 0015 | Magic-link authentication |
| 0016 | Stored encrypted BYOK credentials (`CredentialCipher`) |
| 0017 | Multi-provider registry (`AiProviderRegistry`) |
| 0018 | Run ownership and visibility (user_id, policies) |
| 0019 | Email/password authentication alongside magic link |
| 0020 | Per-user launcher prompt overrides with run snapshot |
| 0021 | Super admin control panel with Filament |
| 0022 | `BaseAIProvider` deepening — shared HTTP lifecycle behind template-method seam |

### BaseAIProvider Deepening (ADR-0022)

All 4 AI provider adapters extend `BaseAIProvider` which owns the shared HTTP lifecycle:
- **Key resolution**: injected key → server config fallback
- **Timeout**: shared `services.ai.timeout` with backward-compat `OPENAI_TIMEOUT`
- **Retry**: 2 attempts, 500ms delay
- **Status mapping**: 401/403 → "Invalid API key", non-success → "AI provider request failed (HTTP {status})"
- **Connection errors**: `ConnectionException` → "Unable to reach the AI provider..."
- **JSON decode**: `json_decode` + array validation

Each adapter declares only its provider-specific **shape** via 8 template-method hooks:
`configureRequest()`, `endpoint()`, `buildPayload()`, `extractContent()`, `verifyEndpoint()`, `configKey()`, `defaultModel()`, `systemMessage()`.

### LaunchParameters Value Object

`backend/app/Services/LaunchParameters.php` centralizes provider/model/key resolution that was previously smeared across 3 call sites (`StoreRunRequest`, `RunController`, `ExecuteLauncherJob`). Resolved once at request time:

```
StoreRunRequest::withValidator() → LaunchParameters::resolve()
  → hasCredentialKeyConflict()   (mutual exclusion)
  → hasUsableKey()               (key availability)
  → isGuestProviderViolation()   (unauthenticated → openrouter only)
  → isModelAllowed()             (guest model or custom format)

RunController::store() → LaunchParameters::resolve(allowCustom: auth)
  → effectiveProvider, resolvedModel, dispatchProvider
```

### Run Lifecycle

```
queued → running → completed
                    → failed
```

- **Created**: `POST /api/runs` → `Run` model with `status = 'queued'`, 202 response
- **Dispatched**: `ExecuteLauncherJob` pushed to database queue
- **Processing**: Job resolves AI key, fetches GitHub context, calls AI provider
- **Progress**: SSE stream via `RunStreamer` (DB polling on `RunProgressed` event)
- **Completed**: `result` JSON column populated, `completed_at` timestamp set
- **Failed**: `Run::markFailed()` — single owner for failure transition (ADR-0022 follow-up). Sets `status = 'failed'`, `error` message, `completed_at`, dispatches `RunProgressed`
- **Stuck runs**: `ReapStuckRuns` console command reaps runs stuck in `processing` for >10 minutes

### Request Flow Details

1. `POST /api/runs` → `RunController::store()`
   - `StoreRunRequest` validates: launcher exists, URL is GitHub HTTPS, provider/model constraints
   - `LaunchParameters::resolve()` computes effective provider, model, key source
   - `LauncherPromptResolver::effectivePrompt()` snapshots the prompt (server default or user override)
   - Run record created with UUID, status `queued`
   - `ExecuteLauncherJob::dispatch(runId, dispatchProvider, oneTimeKey, credentialId, model)`

2. `ExecuteLauncherJob::handle()`
   - `Run::findOrFail(runId)`, set status to `processing`
   - Resolve provider from registry, resolve live API key (transient, never stored)
   - `GitHubService::parse(url)` → `GitHubReference` DTO
   - `GitHubContextAssembler::assemble(reference)` → structured context
   - `AIProviderInterface::generate(prompt, schema)` → JSON result
   - `JsonSchemaValidator::validate(result)` → validated array
   - `Run::update(['status' => 'completed', 'result' => ..., 'completed_at' => ...])`
   - On failure: `Run::markFailed(message, exception)`

3. `GET /api/runs/{uuid}/stream` → SSE
   - `RunStreamer::stream()` polls database every ~500ms
   - Emits `progress` events with encoded snapshot
   - Emits terminal event (`completed` or `failed`) then closes
   - Uses `CacheRunProgressedVersion` to skip DB queries when nothing changed
   - `ContextBudget` constants shared with assembler/encoder for truncation

## API Routes

| Method | Path | Auth | Purpose |
|--------|------|------|---------|
| `POST` | `/api/runs` | Optional | Create run (guest or authenticated) |
| `GET` | `/api/runs/{uuid}` | Policy | Get run status/result |
| `GET` | `/api/runs/{uuid}/stream` | Policy | SSE progress stream |
| `GET` | `/api/runs/recent` | None | Recent public runs for home page |
| `GET` | `/api/launchers` | None | List active launchers (alias: `/api/flows`) |
| `GET` | `/api/providers` | None | List AI providers + models |
| `POST` | `/api/auth/magic-link` | None | Request magic link |
| `GET` | `/api/auth/callback` | None | Magic link callback |
| `POST` | `/api/auth/register` | None | Email/password registration |
| `POST` | `/api/auth/login` | None | Email/password login |
| `POST` | `/api/auth/logout` | Auth | Logout |
| `GET` | `/api/user` | Auth | Current user info |
| `DELETE` | `/api/user` | Auth | Account deletion |
| `GET` | `/api/user/runs` | Auth | Run history |
| `CRUD` | `/api/user/provider-credentials` | Auth | Manage saved API keys |
| `GET` | `/api/user/launcher-prompts` | Auth | Get prompt overrides |
| `PUT` | `/api/user/launcher-prompts/{slug}` | Auth | Upsert prompt override |
| `GET` | `/api/trending-repositories` | None | Top 3 GitHub trending repos |

## Rate Limiting

Defined in `AppServiceProvider`:
- `runs`: 5 requests/hour/IP (public)
- `runs-stream`: 30 requests/minute/IP
- `magic-link`: 3 requests/minute/IP
- `credentials`: 10 requests/minute/user (authenticated)

## Security

- **BYOK credentials**: Encrypted via `CredentialCipher` (AES-256-CBC), never logged or stored on runs
- **API keys**: Transient — resolved in job, passed through memory, never persisted to run records
- **CSRF**: Session-based via `SessionRunCsrfTest`
- **CORS**: Restricted to `localhost:5173` origins (dev), configurable via `CORS_ALLOWED_ORIGINS`
- **Public runs**: Guest users restricted to `openrouter/free` model (ADR-0014 + PR #72)
