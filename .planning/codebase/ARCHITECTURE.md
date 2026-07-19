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
- **Base class** (`BaseAIProvider`): shared HTTP lifecycle — key resolution, timeout, retry (`RETRY_ATTEMPTS=2`, `RETRY_DELAY_MS=500`), error mapping
- **Subclass hooks**: `configureRequest()`, `endpoint($model)`, `buildPayload()`, `extractContent()`, `verifyEndpoint()`, `configKey()`, `defaultModel()`, `systemMessage()`
- **Registry**: `AiProviderRegistry` — singleton; `resolveApiKey()` priority: one-time > saved credential > server config

### Launcher Architecture

Two launcher tables supporting both built-in and user-created workflows:

```
LauncherSource (app/Contracts/)
    │
    ├── Launcher (built-in, app/Models/)
    │   ├── ReviewPullRequestLauncher     (slug: review-pr)
    │   ├── PlanIssueLauncher             (slug: plan-issue)
    │   ├── ExplainRepositoryLauncher     (slug: explain-repository)
    │   └── LaravelDoctorLauncher         (slug: laravel-doctor)
    │
    └── UserLauncher (custom, app/Models/)
        └── Created by authenticated users via API
```

- **Built-in**: Seeded in `DatabaseSeeder` via `BaseLauncher::make()`, stored in `launchers` table
- **Custom**: Created via `POST /api/user/launchers`, stored in `user_launchers` table with UUID PK
- **Unified API**: `GET /api/launchers` returns both types mixed, with `is_custom` flag
- **Visibility**: `user_hidden_launchers` table for per-user built-in launcher toggles
- **Icon assignment**: `LauncherMetaService` — deterministic hash-based for custom, hardcoded map for built-in
- **Resolution**: `LauncherResolutionService::resolve($slug, $user)` checks built-in first, then custom

### Launch Parameters Resolution

`LaunchParameters::resolve()` centralizes provider/model/key resolution:
- **Inputs**: `providerId`, `oneTimeApiKey`, `providerCredentialId`, `requestedModel`, `registry`, `allowCustom`
- **Outputs** (readonly): `effectiveProvider`, `resolvedModel`, `rawProviderId`
- **Validation methods**: `hasUsableKey()`, `hasCredentialKeyConflict()`, `isModelAllowed()`, `isGuestViolationFor()`

### Run Lifecycle

```
StoreRunRequest (validation + LaunchParameters::resolve)
    │
RunController::store
    │  ├─ LauncherResolutionService::resolve($slug, $user)
    │  ├─ LauncherPromptResolver::effectivePrompt($launcher, $user) → prompt_snapshot
    │  ├─ GitHubService::parse($source_url) → repo_slug, repo_type
    │  └─ Run::create() [status: queued, UUID assigned]
    │
ExecuteLauncherJob::dispatch($runId, $rawProviderId, ...)
    │
[Worker picks up — ShouldBeEncrypted, tries=2, timeout=120]
    │
RunExecutor::execute($run, $ai)
    │  ├─ progress('Fetching repository', start=true)
    │  ├─ GitHubService::parse($source_url) → type check vs launcher->getInputType()
    │  ├─ GitHubService::context($source_url) → cached context (10 min)
    │  ├─ progress('Running AI analysis')
    │  ├─ Prompt = prompt_snapshot + "GitHub context:" + encoded context
    │  ├─ Provider::generate($prompt, $outputSchema, $model)
    │  ├─ JsonSchemaValidator::validate($result, $outputSchema)
    │  └─ Run::update([status: completed, result, completed_at])
```

### Error Handling

| Exception Type | Handling | Sentry? |
|---|---|---|
| `UserFacingRunException` | `markFailed($message)` | No |
| `ConnectionException` | `markFailed('Unable to reach GitHub...')` | Yes |
| `RuntimeException` | `markFailed($e->getMessage())` | Yes |
| `Throwable` | `markFailed("Run failed unexpectedly ({class}).")` | Yes |

`Run::markFailed()` is the single owner of the failure lifecycle.

### Progress Streaming (SSE)

```
GET /api/runs/{uuid}/stream
    │
RunController::stream
    │  ├─ authorize('view', $run) — RunPolicy
    │  └─ response()->eventStream(...) with X-Accel-Buffering: no, Cache-Control: no-cache
    │
RunStreamer::stream($run, deadlineSeconds=55, pollIntervalMicroseconds=1_000_000)
    │  ├─ Cache version check (CacheRunProgressedVersion::versionKey)
    │  ├─ fetchSnapshot(): Run::refresh() → RunResource::resolve() → json_encode
    │  └─ Yield on progress change; break on terminal status
```

**Frontend**: `useRunSubscription` hook — EventSource with polling fallback (1.5s interval).

## Layer Boundaries

| Layer | Responsibility | Examples |
|---|---|---|
| **HTTP** | Validation, response formatting, auth | `StoreRunRequest`, `RunResource`, `RunController`, `RunPolicy` |
| **Queue** | Async execution, retry, encryption | `ExecuteLauncherJob` (`ShouldBeEncrypted`, `ShouldQueue`) |
| **Service** | Business logic, external APIs | `RunExecutor`, `GitHubService`, `*Provider`, `LaunchParameters`, `RunStreamer`, `ContextEncoder`, `JsonSchemaValidator`, `LauncherPromptResolver`, `LauncherResolutionService`, `LauncherMetaService` |
| **Domain** | Data, schema, encryption | `Run`, `User`, `Launcher`, `UserLauncher`, `ProviderCredential`, `LauncherPromptOverride`, `UserHiddenLauncher` |
| **Contracts** | Interfaces | `AIProviderInterface`, `LauncherSource`, `LauncherInterface` |
| **Data** | DTOs | `ResolvedLauncher`, `GitHubReference`, `LaunchParameters` |

## Key Design Decisions

- **No synchronous AI calls in HTTP cycle**: API returns 202 immediately; `sync` queue forbidden in production
- **Provider keys never stored on runs**: BYOK keys transient (in-memory + encrypted in queue via `ShouldBeEncrypted`)
- **Saved credentials encrypted at rest**: `CredentialCipher` (AES-256-CBC, dedicated key)
- **Single-implementation interfaces removed**: `RunExecutorInterface`, `LauncherMetaInterface` deleted
- **JSON Schema enforced**: Every launcher defines `output_schema`; validated by `JsonSchemaValidator`
- **Prompt snapshot**: Captured at run creation, decouples from later prompt edits
- **Cache-versioned SSE**: `CacheRunProgressedVersion` listener skips DB queries when version unchanged
- **Custom launchers**: Separate `user_launchers` table from built-in `launchers`; `LauncherSource` contract unifies them
- **Dual FK on runs**: `launcher_id` (placeholder for custom runs) + `user_launcher_id` (null for built-in)
- **`is_public` on runs**: Authenticated users default private; anonymous runs default public
