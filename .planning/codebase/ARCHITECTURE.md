# Architecture

## Pattern: Queue-Backed Workflow with SSE Streaming

The core architecture follows a **fire-and-forget queue pattern with server-sent events for progress**:

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

### AI Provider Adapter Pattern

```
AIProviderInterface (app/Contracts/)
    │
    └── BaseAIProvider (app/Services/)  [abstract]
            │
            ├── OpenAIProvider
            ├── OpenRouterProvider
            ├── AnthropicProvider
            └── GeminiProvider
```

- **Interface**: `AIProviderInterface` — `generate(array $messages, string $model, array $jsonSchema, ?string $apiKey): array`
- **Base class**: `BaseAIProvider` — shared HTTP client, error handling, response parsing
- **Registry**: `AiProviderRegistry` — provider→config mapping, key resolution (`resolveApiKey()`), usability checks
- **Config**: `LaunchParameters::resolve()` for provider/model resolution; `AiProviderRegistry::resolveApiKey()` for key resolution

### Launcher Pattern (Strategy)

```
LauncherInterface (app/Contracts/)
    │
    └── BaseLauncher (app/Launchers/)  [abstract]
            │
            ├── ReviewPullRequestLauncher
            ├── PlanIssueLauncher
            ├── ExplainRepositoryLauncher
            └── LaravelDoctorLauncher
```

- Each launcher defines: slug, label, description, GitHub URL type, output JSON schema, system prompt
- Seeded in `DatabaseSeeder` via `BaseLauncher::make()`
- User overrides: `LauncherPromptOverride` model — per-user custom system prompts

### Run Lifecycle

```
StoreRunRequest (validation)
    │
RunController::store
    │
Run::create()  [status: queued, UUID assigned]
    │
ExecuteLauncherJob::dispatch()
    │
[Worker picks up]
    │
ExecuteLauncherJob::handle()
    │
RunExecutor::execute(Run $run)
    │
    ├── GitHubService::fetch()     → GitHub context (cached)
    ├── LaunchParameters::resolve() → provider + model + key
    └── Provider::generate()       → AI report (JSON schema)
    │
JsonSchemaValidator::validate()
    │
Run::update()  [status: completed, result: JSON]
    │
RunStreamer::broadcast()  [for SSE listeners]
```

## Layer Boundaries

| Layer | Responsibility | Examples |
|---|---|---|
| **HTTP** | Request validation, response formatting | `StoreRunRequest`, `RunResource`, `RunController` |
| **Queue** | Async execution, retry logic | `ExecuteLauncherJob` (`ShouldQueue`, `ShouldBeEncrypted`) |
| **Service** | Business logic, external API calls | `RunExecutor`, `GitHubService`, `*Provider` |
| **Domain** | Data, schema validation | `Run` model, `JsonSchemaValidator`, `LaunchParameters` |
| **Support** | Cross-cutting utilities | `AiProviderRegistry`, `CredentialCipher` |

## Data Flow

### Run Creation
1. `POST /api/runs` → `StoreRunRequest` validates (`launcher`, `source_url`, optional `provider`)
2. `RunController::store` → `Run::create()` with `source_url`, `repo_slug`, `repo_type`, `launcher`, `provider`
3. Job dispatched to database queue (`QUEUE_CONNECTION=database`)
4. Returns HTTP 202 + UUID

### Run Execution (Async)
1. `ExecuteLauncherJob::handle()` → `RunExecutor::execute($run)`
2. `GitHubService::fetch($sourceUrl)` → context cached by URL
3. `LaunchParameters::resolve()` → determines provider, model, API key
4. Provider generates structured report via JSON schema
5. `JsonSchemaValidator::validate()` → ensures output matches expected schema
6. `Run::update()` → status `completed` with validated result

### Progress Streaming (SSE)
1. `GET /api/runs/{uuid}/stream` → `RunStreamer` polls database for progress changes
2. Emits SSE events: `status` change, `progress` message array
3. Final event: `completed` or `failed` with full result
4. Window: ~55 seconds before SSE connection times out

## Key Design Decisions

- **No synchronous AI calls in HTTP cycle**: All AI work is queued; API returns 202 immediately
- **Provider keys never stored on runs**: BYOK keys processed in-memory only; saved credentials encrypted at rest via `CredentialCipher`
- **Single-implementation interfaces removed**: `RunExecutorInterface` was deleted; type-hint concrete class directly (speculative generality)
- **Thin helpers merged**: `GitHubContextFetcher` + `GitHubContextAssembler` → `GitHubService`; `LaunchAiKeyResolver` → `AiProviderRegistry`
- **JSON Schema enforced**: Every launcher defines `outputSchema`; AI response validated before persisting
- **Repo metadata at creation time**: `repo_slug` and `repo_type` parsed and stored on `Run` model, removing `GitHubService` dependency from `RecentRunSummary`
