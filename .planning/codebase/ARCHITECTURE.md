# Architecture

System patterns, layers, data flow, and abstractions for ai-flow.

## High-level pattern

**Monolithic Laravel app** serving a React SPA + queue-backed JSON API. No microservices. The single Laravel 13 app handles HTTP, queue jobs, SSE streaming, and serves the compiled React assets.

```
Browser (React SPA)
   │  HTTPS
   ▼
Laravel HTTP (routes/api.php, routes/web.php)
   │
   ├── POST /api/runs → RunController::store → Run (queued) → ExecuteLauncherJob
   │                                                    │
   │                                                    ▼
   │                                              RunExecutor::execute
   │                                                    │
   │                                    ┌───────────────┼───────────────┐
   │                                    ▼               ▼               ▼
   │                              GitHubService   AIProviderInterface   JsonSchemaValidator
   │                              (parse + cache)  (generate JSON)      (validate result)
   │                                    │               │
   │                                    ▼               ▼
   │                              runs.source_context  runs.result
   │
   ├── GET /api/runs/{uuid} → RunResource (JSON snapshot)
   │
   └── GET /api/runs/{uuid}/stream → SSE (DB-polled, ~55s window)
                                        │
                                        ▼
                                  RunStreamer::stream
                                  (cache-version-gated DB poll)
```

## Layers

### 1. HTTP layer (`app/Http/`)

- **Controllers** — thin: validate input, dispatch jobs, return resources. `RunController`, `LauncherController`, `UserLauncherController`, `ProviderCredentialController`, `LauncherPromptController`, `RunHistoryController`, `AccountController`, `TrendingRepositoryController`, `Auth/PasswordAuthController`, `Auth/MagicLinkController`.
- **Form Requests** — validation: `StoreUserLauncherRequest`, `UpdateUserLauncherRequest`, etc.
- **API Resources** — JSON serialization: `RunResource`, `LauncherResource`, `UserLauncherResource`, `UserResource`.

### 2. Domain layer (`app/Services/`, `app/Launchers/`)

- **`RunExecutor`** — orchestrates a run: GitHub fetch → context encoding → AI generate → schema validation → persist result. Single entry point for `ExecuteLauncherJob`.
- **`GitHubService`** — parse GitHub URL, fetch + cache context, assemble structured context. No git clone — pure REST API + cache.
- **`ContextEncoder`** — encodes GitHub context into a prompt-friendly string.
- **`ContextBudget`** — limits on README/file-tree/diff/comment lengths.
- **`BaseAIProvider`** — deep base module owning the HTTP request lifecycle for all AI providers. Subclasses declare shape via hooks (`configureRequest`, `endpoint`, `buildPayload`, `extractContent`, `verifyEndpoint`, `configKey`, `defaultModel`).
- **`JsonSchemaValidator`** — recursive JSON Schema validation (type, enum, required, additionalProperties, nested objects/arrays).
- **`RunStreamer`** — SSE generator with cache-version-gated DB polling.
- **`AiProviderRegistry`** (`app/Support/`) — singleton. Resolves provider by ID, resolves API key (injected → BYOK credential → server config).
- **`LauncherMetaService`** — singleton. Launcher icon/tone metadata.
- **`LauncherResolutionService`** — resolves a launcher slug to a built-in `Launcher` or user `UserLauncher`.

### 3. Launcher layer (`app/Launchers/`)

- **`LauncherInterface`** (`app/Contracts/`) — `getSlug()`, `getName()`, `getPromptTemplate()`, `getInputType()`, `getOutputSchema()`, `isBuiltIn()`.
- **`LauncherSource`** (`app/Contracts/`) — implemented by both `Launcher` and `UserLauncher` models so `Run::launcherSource()` can return either.
- **`BaseLauncher`** — abstract base with shared `outputSchema()` and `make()` metadata helper.
- **Built-in launchers**: `ReviewPullRequestLauncher`, `PlanIssueLauncher`, `ExplainRepositoryLauncher`, `LaravelDoctorLauncher`.
- **Custom launchers**: created by authenticated users via API, stored in `user_launchers` table (separate from built-in `launchers`).

### 4. Job layer (`app/Jobs/`)

- **`ExecuteLauncherJob`** — `ShouldBeEncrypted`, `ShouldQueue`. `tries=2`, `timeout=120`. Resolves provider + API key via registry, delegates to `RunExecutor::execute`.
- **`ReapStuckRuns`** — scheduled command (every minute in production). Transitions orphaned "running" runs to "failed" after TTL (180s default).

### 5. Model layer (`app/Models/`)

- **`Run`** — UUID primary key, JSON columns (`progress`, `input`, `source_context`, `result`). `markFailed()` is the single owner of the run-failure lifecycle. `launcherSource()` returns built-in or user launcher.
- **`Launcher`** — built-in launchers, seeded from `DatabaseSeeder`. Implements `LauncherSource`.
- **`UserLauncher`** — user-created launchers, UUID PK. Cascade-deletes associated runs. Implements `LauncherSource`.
- **`User`** — auth + ownership.
- **`ProviderCredential`** — encrypted BYOK credentials. `CREDENTIAL_ENCRYPTION_KEY` encryption.

### 6. Frontend layer (`resources/ts/`)

- **`app.tsx`** — React entry, mounts `App.tsx`.
- **`components/`** — UI components (functional + hooks). `App.tsx` (root), `Home.tsx`, `Dashboard.tsx`, `LaunchArea.tsx`, `Report.tsx`, `Running.tsx`, `CustomLaunchersSection.tsx`, `LauncherVisibilitySection.tsx`, `WorkflowPromptsSection.tsx`, `ProviderSettings.tsx`, `RunHistory.tsx`, `SignIn.tsx`, etc.
- **`services/`** — API clients: `run.ts`, `auth.ts`, `userLaunchers.ts`.
- **`hooks/`** — `useRunFromPath.ts`, `useRunSubscription.ts` (SSE).
- **`lib/`** — utilities: `http.ts`, `logger.ts`, `decode.ts`, `runModels.ts`, `appPaths.ts`, `navigate.ts`, `scroll.ts`.
- **`data/`** — `launcherMeta.ts` (static launcher metadata).
- **`types/`** — `api.ts` (shared API types, `RunStatus` enum synced with `Run::STATUSES`).

## Key data flow

### Run creation → completion

1. `POST /api/runs` (202) → `RunController::store` → validates, creates `Run` (status: `queued`), dispatches `ExecuteLauncherJob`.
2. `ExecuteLauncherJob::handle` → resolves provider + API key via `AiProviderRegistry`, delegates to `RunExecutor::execute`.
3. `RunExecutor::execute` → GitHub fetch (cached) → context encode → AI generate (JSON schema) → `JsonSchemaValidator::validate` → persist `result`, status `completed`.
4. Progress dispatched via `RunProgressed` event → `CacheRunProgressedVersion` listener bumps cache version → `RunStreamer` SSE yields snapshot.
5. Frontend `useRunSubscription` hook consumes SSE, updates UI.

### Failure handling

- `UserFacingRunException` — expected user/input errors (malformed URL, wrong launcher type, repo not found). Logged at `warning`, Sentry ignores.
- `ConnectionException` — network failures to GitHub. Logged at `warning`.
- AI operational errors ("API key not configured", "Invalid API key", "Unable to reach AI provider") — logged at `warning`.
- Unexpected `Throwable` — `markFailed()` + `Sentry\captureException()`.

## Key abstractions

| Abstraction | Location | Purpose |
|-------------|----------|---------|
| `AIProviderInterface` | `app/Contracts/` | Swappable AI providers (generate, verifyCredential, defaultModel) |
| `LauncherInterface` | `app/Contracts/` | Launcher contract (built-in + custom) |
| `LauncherSource` | `app/Contracts/` | Unified interface for `Launcher` + `UserLauncher` models |
| `BaseAIProvider` | `app/Services/` | Shared HTTP lifecycle for all AI providers (hooks pattern) |
| `BaseLauncher` | `app/Launchers/` | Shared output schema + metadata for built-in launchers |
| `AiProviderRegistry` | `app/Support/` | Singleton provider registry + API key resolution |
| `Run::markFailed()` | `app/Models/Run.php` | Single owner of run-failure lifecycle |

## Entry points

| Entry point | Path | Purpose |
|-------------|------|---------|
| HTTP | `public/index.php` | Laravel HTTP kernel |
| Artisan | `artisan` | CLI commands |
| React | `resources/ts/app.tsx` | Frontend SPA mount |
| Queue worker | `php artisan queue:work` | Processes `ExecuteLauncherJob` |
| Scheduled | `console.php` | `ReapStuckRuns` every minute (production) |
