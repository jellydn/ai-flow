# Architecture

## Pattern: Queue-Backed Workflow with SSE Streaming

The core architecture is a **fire-and-forget queue pattern with server-sent events for progress**. No synchronous AI/GitHub calls happen in the HTTP cycle.

```
Browser
  │
  ├─ POST /api/runs ──▶ RunController::store ──▶ Database Queue
  │                         │                         │
  ├─ SSE /api/runs/{id}/stream                    ExecuteLauncherJob
  │                                                    │
  └─ GET /api/runs/{id}              ┌─────────────────┤
                                      │                 │
                                 GitHubService    AIProviderInterface
                                 (parse + cache)   (generate report)
                                      │                 │
                                      └─────┬───────────┘
                                            │
                                     JsonSchemaValidator
                                            │
                                     runs.result ✅
```

## Key Abstractions

### AI Provider Adapter Pattern (ADR-0017, ADR-0022)

```
AIProviderInterface (app/Contracts/)
    │
    └── BaseAIProvider (app/Services/)  [abstract — owns HTTP lifecycle]
            │
            ├── OpenAIProvider      (json_schema response format)
            ├── OpenRouterProvider  (OpenAI-compatible + HTTP-Referer/X-Title)
            ├── AnthropicProvider   (x-api-key + anthropic-version; prompt-only JSON)
            └── GeminiProvider      (?key= in URL; system instructions in payload)
```

- **Interface** (`AIProviderInterface`): `id()`, `models()`, `defaultModel()`, `verifyCredential($apiKey)`, `generate($prompt, $schema, $model)`
- **Base class** (`BaseAIProvider`): shared HTTP lifecycle — key resolution (injected → server config fallback), timeout, retry (`RETRY_ATTEMPTS=2`, `RETRY_DELAY_MS=500`), 401/403 → "Invalid API key.", non-success → "AI provider request failed (HTTP {status}).", `ConnectionException` → "Unable to reach the AI provider...", `json_decode` + "invalid JSON" guard with `MAX_ERROR_PREVIEW_LENGTH=200` preview.
- **Subclass hooks**: `configureRequest()`, `endpoint($model)`, `buildPayload()`, `extractContent()`, `verifyEndpoint()`, `configKey()`, `defaultModel()`, `systemMessage()` (overridable; `jsonOnlySystemMessage()` for Anthropic/Gemini)
- **Registry**: `AiProviderRegistry` — singleton; provider IDs from `PROVIDERS` const (not config); `resolveApiKey()` priority: one-time key > saved credential > server config; `resolveModel()` validates against allowed list (custom models allowed for authenticated users matching `^[A-Za-z0-9][A-Za-z0-9._:/-]*$`)

### Launcher Pattern (Strategy)

```
LauncherInterface (app/Contracts/)
    │
    └── BaseLauncher (app/Launchers/)  [abstract]
            │
            ├── ReviewPullRequestLauncher     (slug: review-pr, input: pull_request)
            ├── PlanIssueLauncher             (slug: plan-issue, input: issue)
            ├── ExplainRepositoryLauncher     (slug: explain-repository, input: repository)
            └── LaravelDoctorLauncher         (slug: laravel-doctor, input: repository)
```

- Each launcher defines: slug, name, description, `input_type`, `output_schema` (shared structure via `BaseLauncher::outputSchema()`), `prompt` (system prompt)
- Seeded in `DatabaseSeeder` via `BaseLauncher::make()` + `Launcher::updateOrCreate(['slug' => ...])`
- **User overrides**: `LauncherPromptOverride` model — per-user custom prompt templates; resolved by `LauncherPromptResolver::effectivePrompt($launcher, $user)`
- **Shared output schema** (`BaseLauncher::outputSchema()`): `{summary: string, risk: enum[low,medium,high,critical], findings: [{severity, title, description, recommendation}], verification_steps: [string]}`, `additionalProperties: false`

### Launch Parameters Resolution

`App\Services\LaunchParameters::resolve()` centralizes provider/model/key resolution that was previously smeared across `StoreRunRequest::withValidator`, `RunController::store`, and `ExecuteLauncherJob::handle`.

- **Inputs**: `providerId`, `oneTimeApiKey`, `providerCredentialId`, `requestedModel`, `registry`, `allowCustom`
- **Outputs** (readonly props): `effectiveProvider`, `resolvedModel`, `rawProviderId` (preserves nullable behavior for job dispatch)
- **Validation methods**: `hasUsableKey()`, `hasCredentialKeyConflict()`, `isModelAllowed($registry, $isAuthenticated)`, `isGuestViolationFor($isAuthenticated)`
- **Credential precedence**: if `providerCredentialId` set, its provider takes precedence over raw `providerId`

### Run Lifecycle

```
StoreRunRequest (validation + LaunchParameters::resolve)
    │
RunController::store
    │  ├─ Launcher::where('slug', ...)->where('active', true)->firstOrFail()
    │  ├─ LauncherPromptResolver::effectivePrompt($launcher, $user) → prompt_snapshot
    │  ├─ GitHubService::parse($source_url) → repo_slug, repo_type (null on invalid)
    │  └─ Run::create() [status: queued, UUID assigned, prompt_snapshot stored]
    │
ExecuteLauncherJob::dispatch($runId, $rawProviderId, $oneTimeApiKey, $providerCredentialId, $model)
    │
[Worker picks up — ShouldBeEncrypted, tries=2, timeout=120]
    │
ExecuteLauncherJob::handle(RunExecutor $executor)
    │  ├─ Run::with('launcher')->findOrFail($runId)
    │  ├─ AiProviderRegistry::has($providerId) check
    │  ├─ AiProviderRegistry::resolveApiKey(...) — transient, never persisted
    │  ├─ AiProviderRegistry::get($providerId, $apiKey) → adapter
    │  ├─ Update run.model if changed
    │  └─ ProviderCredential::update(['last_used_at' => now()]) if credential used
    │
RunExecutor::execute(Run $run, AIProviderInterface $ai)
    │  ├─ progress('Fetching repository', start=true) → Run::update + RunProgressed::dispatch
    │  ├─ GitHubService::parse($source_url) → type check vs launcher->input_type
    │  ├─ GitHubService::context($source_url) → cached context (10 min)
    │  ├─ Run::update(['source_context' => $context])
    │  ├─ progress('Running AI analysis')
    │  ├─ Prompt = prompt_snapshot + "\nGitHub context:\n" + ContextEncoder::encode($context)
    │  ├─ Provider::generate($prompt, $launcher->output_schema, $run->model)
    │  ├─ JsonSchemaValidator::validate($result, $launcher->output_schema)
    │  ├─ progress('Preparing report')
    │  └─ Run::update([status: completed, result, source_context: null, completed_at])
    │
RunProgressed::dispatch($run->fresh())
```

### Error Handling in RunExecutor

| Exception Type | Handling | Sentry? |
|---|---|---|
| `UserFacingRunException` | `markFailed($message)` — expected user/input errors (malformed URL, wrong launcher URL, missing repo, rate limit) | No |
| `ConnectionException` | `markFailed('Unable to reach GitHub...')` | Yes |
| `RuntimeException` | `markFailed($e->getMessage())` — operational (AI provider, schema validation) | Yes |
| `Throwable` | `markFailed("Run failed unexpectedly ({class_basename}).")` | Yes |

`Run::markFailed()` is the single owner of the run-failure lifecycle: sets status, error, clears `source_context`, sets `completed_at`, logs, dispatches `RunProgressed`.

### Progress Streaming (SSE)

```
GET /api/runs/{uuid}/stream
    │
RunController::stream
    │  ├─ authorize('view', $run) — RunPolicy
    │  ├─ session()->save() — release session lock before long-lived loop
    │  └─ response()->eventStream(...) with X-Accel-Buffering: no, Cache-Control: no-cache
    │
RunStreamer::stream($run, deadlineSeconds=55, pollIntervalMicroseconds=1_000_000)
    │  ├─ Cache version check (CacheRunProgressedVersion::versionKey($run->id))
    │  │    - null version (cache unavailable, e.g. array driver in tests) → always refresh
    │  │    - version unchanged → skip DB query, sleep only
    │  │    - version changed → fetch snapshot
    │  ├─ fetchSnapshot(): Run::refresh() → RunResource::resolve() → json_encode
    │  ├─ Yield StreamedEvent('progress', $encoded) when snapshot changes
    │  └─ Yield StreamedEvent($run->status, $encoded) on terminal (completed/failed) → break
```

**Cache version listener**: `App\Listeners\CacheRunProgressedVersion` listens to `RunProgressed` event, writes micro-timestamp to `run:version:{id}` cache key. This lets the SSE loop skip DB queries when no progress has been made.

## Layer Boundaries

| Layer | Responsibility | Examples |
|---|---|---|
| **HTTP** | Request validation, response formatting, auth | `StoreRunRequest`, `RunResource`, `RunController`, `RunPolicy` |
| **Queue** | Async execution, retry logic, encryption-at-rest | `ExecuteLauncherJob` (`ShouldBeEncrypted`, `ShouldQueue`) |
| **Service** | Business logic, external API calls | `RunExecutor`, `GitHubService`, `*Provider`, `LaunchParameters`, `RunStreamer`, `ContextEncoder`, `JsonSchemaValidator`, `LauncherPromptResolver`, `RecentRunSummary` |
| **Domain** | Data, schema, encryption | `Run`, `User`, `Launcher`, `ProviderCredential`, `LauncherPromptOverride` models; `CredentialCipher`; `ContextBudget`; `GitHubReference` DTO |
| **Support** | Cross-cutting utilities | `AiProviderRegistry` |
| **Contracts** | Interfaces | `AIProviderInterface`, `LauncherInterface` |
| **Console** | Maintenance commands | `ReapStuckRuns` (scheduled every minute in production) |
| **Events/Listeners** | Decoupled progress signaling | `RunProgressed` event, `CacheRunProgressedVersion` listener |

## Data Flow

### Run Creation (Synchronous)

1. `POST /api/runs` → `StoreRunRequest` validates (`launcher`, `source_url`, optional `provider`)
2. `prepareForValidation`: guests forced to `openrouter` + `openrouter/free`
3. `withValidator` → `LaunchParameters::resolve()` → `hasCredentialKeyConflict()`, `hasUsableKey()`, `isGuestViolationFor()`, `isModelAllowed()` checks
4. `RunController::store` → `Launcher::where('slug', ...)->firstOrFail()`
5. `LauncherPromptResolver::effectivePrompt()` → `prompt_snapshot`
6. `GitHubService::parse()` → `repo_slug`, `repo_type` (null on invalid, silently ignored)
7. `Run::create()` with `user_id`, `provider`, `model`, `source_url`, `repo_slug`, `repo_type`, `input`, `prompt_snapshot`, `status: queued`
8. `ExecuteLauncherJob::dispatch()` with transient `oneTimeApiKey`
9. Returns HTTP 202 + `{id, status: queued, message}`

### Run Execution (Async, Worker)

1. `ExecuteLauncherJob::handle()` → `Run::with('launcher')->findOrFail()`
2. Registry validation: `has($providerId)`, `resolveApiKey()`, `get($providerId, $apiKey)`
3. `ProviderCredential::update(['last_used_at'])` if credential used
4. `RunExecutor::execute($run, $ai)` — see lifecycle above
5. `RunProgressed::dispatch()` at each progress step + on terminal

### Progress Streaming (SSE, ~55s window)

1. `GET /api/runs/{uuid}/stream` → `RunController::stream`
2. Auth via `RunPolicy::view` (public runs viewable by anyone; private runs owner-only)
3. Session lock released before loop
4. `RunStreamer::stream()` polls cache version → DB snapshot on version change → SSE events
5. Frontend `useRunSubscription` hook: EventSource with polling fallback (1.5s interval)

## Key Design Decisions

- **No synchronous AI calls in HTTP cycle**: All AI work queued; API returns 202 immediately (enforced by `AppServiceProvider` guard against `sync` queue in production)
- **Provider keys never stored on runs**: BYOK keys processed in-memory only; saved credentials encrypted at rest via `CredentialCipher` (`CREDENTIAL_ENCRYPTION_KEY`)
- **Single-implementation interfaces removed**: `RunExecutorInterface` was deleted; type-hint concrete `RunExecutor` directly (avoids speculative generality)
- **Thin helpers merged**: `GitHubContextFetcher` + `GitHubContextAssembler` → `GitHubService`; `LaunchAiKeyResolver` → `AiProviderRegistry::resolveApiKey()`
- **JSON Schema enforced**: Every launcher defines `output_schema`; AI response validated by `JsonSchemaValidator` before persisting (`additionalProperties: false` enforced)
- **Repo metadata at creation time**: `repo_slug` and `repo_type` parsed and stored on `Run` model at creation, removing `GitHubService` dependency from `RecentRunSummary`
- **Prompt snapshot**: `prompt_snapshot` captured at run creation (honoring user overrides) and used at execution — decouples run from later prompt edits
- **Encrypted queue payload**: `ExecuteLauncherJob` implements `ShouldBeEncrypted` — transient API key encrypted in queue payload
- **Cache-versioned SSE**: `CacheRunProgressedVersion` listener writes micro-timestamp cache key; SSE loop skips DB queries when version unchanged
- **Stuck-run reaping**: `ReapStuckRuns` command (scheduled every minute in production) transitions orphaned `running` runs older than TTL (default 180s) to `failed`

## Frontend Architecture

### SPA Structure

- **Entry**: `backend/resources/ts/app.tsx` — `Sentry.init()` + `<ErrorBoundary><App /></ErrorBoundary>`
- **Shell**: `backend/resources/views/app.blade.php` + catch-all `web.php` route (excludes `/api`, `/admin`)
- **Routing**: client-side path-based (no router lib); `useRunFromPath` hook delegates to `getRunIdFromPath` in `lib/appPaths.ts` to extract the run ID from the current path

### Frontend Data Flow

```
App.tsx (state: user, view, currentRunId)
    │
    ├─ services/auth.ts    — fetchUser, login, register, magic-link, credentials, launcher prompts
    ├─ services/run.ts     — getLaunchers, createRun, fetchRun, fetchRecentRuns, fetchTrendingRepositories
    │     └─ decode* functions — runtime type assertions on API responses (assertObject, assertString, etc.)
    ├─ lib/http.ts         — get/post with CSRF (XSRF-TOKEN cookie → X-XSRF-TOKEN header), 10s timeout, AbortController
    ├─ hooks/useRunSubscription.ts — EventSource SSE + polling fallback (1.5s)
    └─ hooks/useRunFromPath.ts — run ID from URL path
```

### Type Safety Boundary

- `services/run.ts` and `services/auth.ts` export `decode*` functions (`decodeRun`, `decodeUser`, `decodeCredential`, etc.) that assert runtime types on API JSON
- `assertObject`, `assertString`, `assertArray`, `assertIntegerId` helpers — throw on mismatch
- Avoids broad `any`; strict-mode TS
