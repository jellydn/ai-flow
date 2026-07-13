# Architecture

**Analysis Date:** 2026-07-13

## Pattern Overview

**Overall:** Laravel API backend with a queued job worker, exposing a thin HTTP layer to a React/TypeScript single-page app bundled by Vite. The defining pattern is **dispatch-and-poll**: the HTTP request only creates a `Run` row and dispatches a queue job (returning `202 Accepted`); all slow work (GitHub fetch + AI generation) happens asynchronously on the queue, and the client learns about progress through server-sent events that poll the same database row.

**Key Characteristics:**

- Dispatcher pattern: controllers return `202` and enqueue work; no synchronous GitHub/OpenAI calls in the request cycle (`backend/app/Http/Controllers/RunController.php`).
- Service-oriented backend: orchestration lives in `backend/app/Services/`, not controllers.
- Strategy/Factory for AI providers: `AIProviderInterface` with a factory in `backend/app/Support/AiProviders.php`.
- Declarative launchers: each workflow is a small class returning static `metadata()` seeded into the `launchers` table (`backend/app/Launchers/`, `backend/database/seeders/DatabaseSeeder.php`).
- Database-as-state: a `Run` row is the single source of truth for status/progress/result; both the job and the SSE stream read/write that same row.
- SSE streaming via DB polling: `RunStreamer` yields `StreamedEvent`s every second for up to ~55s (`backend/app/Services/RunStreamer.php`).
- Monorepo deploy: only `backend/` is the application root on Laravel Cloud; the React UI is bundled inside it.

## Layers

**HTTP / Routing layer:**

- Purpose: receive requests, validate input, return JSON or SSE; never performs I/O-heavy work.
- Location: `backend/app/Http/`, `backend/routes/api.php`, `backend/routes/web.php`
- Contains: `RunController`, `RunHistoryController`, `ProviderController`, `ProviderCredentialController`, `MagicLinkController`, `StoreRunRequest` (form request validation), `RunResource` / `UserResource` / `ProviderCredentialResource` (JSON shape)
- Depends on: `App\Jobs\ExecuteLauncherJob`, `App\Services\RunStreamer`, `App\Models\Launcher`, `App\Models\Run`
- Used by: external API clients and the bundled SPA

**Service layer:**

- Purpose: all domain logic — orchestration, GitHub context building, AI generation, schema validation, SSE streaming.
- Location: `backend/app/Services/`
- Contains:
  - `RunExecutor.php` — orchestrates a single run end-to-end
  - `GitHubService.php` — URL parse + cached context fetch (composition of fetcher + assembler)
  - `GitHubContextFetcher.php` — raw GitHub REST calls
  - `GitHubContextAssembler.php` — shapes raw data into a context array
  - `ContextEncoder.php` — serializes/bounds context to a byte budget
  - `JsonSchemaValidator.php` — validates AI JSON output against a schema
  - `OpenAIProvider.php` — concrete `AIProviderInterface` talking to OpenAI-compatible API
  - `AnthropicProvider.php` — concrete `AIProviderInterface` for Claude models
  - `GeminiProvider.php` — concrete `AIProviderInterface` for Gemini models
  - `RunStreamer.php` — SSE generator polling the `runs` table
- Depends on: `App\Contracts\*`, `App\Models\*`, `App\Data\GitHubReference`, `App\Events\RunProgressed`, Laravel `Http`/`Cache`/`Log` facades
- Used by: `RunController` (stream) and `ExecuteLauncherJob` (executor)

**Job / Queue layer:**

- Purpose: run a single run off the queue, resilient to failure.
- Location: `backend/app/Jobs/ExecuteLauncherJob.php`
- Contains: a `ShouldQueue` + `ShouldBeEncrypted` job (`$tries = 2`, `$timeout = 120`)
- Depends on: `App\Contracts\RunExecutorInterface`, `App\Support\AiProviders`, `App\Events\RunProgressed`
- Used by: `RunController::store` (dispatch)

**Launcher layer (workflow definitions):**

- Purpose: declare each workflow's slug, name, prompt template, accepted input type, and shared output schema.
- Location: `backend/app/Launchers/`
- Contains: `BaseLauncher.php` (abstract, shared `outputSchema()` + `make()`) and four concrete launchers: `ReviewPullRequestLauncher.php`, `PlanIssueLauncher.php`, `ExplainRepositoryLauncher.php`, `LaravelDoctorLauncher.php`
- Depends on: `App\Contracts\LauncherInterface`
- Used by: `backend/database/seeders/DatabaseSeeder.php` (seeds `launchers` rows)

**Contracts / Abstractions layer:**

- Purpose: define swappable boundaries (AI provider, launcher shape, executor).
- Location: `backend/app/Contracts/`
- Contains: `AIProviderInterface.php`, `LauncherInterface.php`, `RunExecutorInterface.php`
- Used by: services, jobs, and `backend/app/Providers/AppServiceProvider.php` (binding)

**Data / Model layer:**

- Purpose: persistence and value objects.
- Location: `backend/app/Models/`, `backend/app/Data/`
- Contains: `Run.php` (UUID key, `status`/`progress`/`input`/`source_context`/`result`/`error` casts), `Launcher.php`, `User.php`, `ProviderCredential.php`, `MagicLoginToken.php`, and the readonly DTO `GitHubReference.php`
- Used by: every layer above

**Frontend / SPA layer:**

- Purpose: present the launcher picker, run progress, and final report; subscribe to run updates.
- Location: `backend/resources/ts/`
- Contains:
  - `app.tsx` (entry), `components/` (App, Home, Running, Report, SignIn, Dashboard, RunHistory, ProviderSettings, Header, Footer, Logo, ErrorBoundary, LauncherIcon)
  - `services/run.ts` (HTTP + decoders), `services/auth.ts` (auth API calls)
  - `hooks/useRunSubscription.ts` (SSE + polling fallback), `hooks/useRunFromPath.ts` (deep-link)
  - `types/api.ts` (contracts), `lib/http.ts`, `data/launcherMeta.ts`
- Depends on: same-origin `/api/*` endpoints
- Used by: the browser via `backend/resources/views/app.blade.php`

## Data Flow

**Create-and-run (primary flow):**

1. `POST /api/runs` hits `routes/api.php` → `RunController::store` (`backend/app/Http/Controllers/RunController.php`).
2. `StoreRunRequest` validates `launcher` (exists in `launchers`), `source_url` (https github.com regex), and optional `provider.id`/`provider.api_key`.
3. Controller looks up the active `Launcher`, creates a `Run` with `status='queued'` and empty `progress`, then resolves `provider.id` (default `openai` via `App\Support\AiProviders`).
4. `ExecuteLauncherJob::dispatch($run->id, $providerId, $apiKey)` is called; controller returns `202` JSON `{id, status, message}`.
5. Queue worker runs `ExecuteLauncherJob::handle` → builds the provider through `AiProviders::createProvider` (throws → `failRun`) → calls `RunExecutorInterface::execute`.
6. `RunExecutor::execute`:
   - `GitHubService::parse($url)` → `GitHubReference`; rejects mismatched `input_type`.
   - `GitHubService::context($url)` → cached (10 min) fetch+assemble.
   - `ContextEncoder::encode($context)` bounds the payload.
   - `AIProviderInterface::generate($prompt, $outputSchema)` → calls provider.
   - `JsonSchemaValidator::validate($result, $outputSchema)` enforces shape/types.
   - Updates `Run` to `status='completed'` with `result`, clears `source_context`.
   - Each step emits `RunProgressed` event and appends a progress message.
7. On any `Throwable`, the run is marked `failed` with a user-safe `error`, server-logged, and `RunProgressed` is dispatched.

**Streaming / progress delivery (SSE):**

1. `GET /api/runs/{run}/stream` → `RunController::stream` → `response()->eventStream(...)`.
2. `RunStreamer::stream` polls the `Run` row every second for up to `55s`, yielding a `StreamedEvent` `progress` on change and a terminal `completed`/`failed` event, then breaks.
3. SSE headers disable buffering (`X-Accel-Buffering: no`, `Cache-Control: no-cache`) for proxy compatibility.

**Frontend consumption:**

1. `services/run.ts` posts to `/api/runs` (`createRun`) and parses the run on `/api/runs/{id}` (`fetchRun` + `decodeRun` with strict runtime validation).
2. `hooks/useRunSubscription.ts` opens an `EventSource` to `/api/runs/{id}/stream`, handling `progress`/`completed`/`failed` events; on `EventSource` absence or stream error it falls back to polling `fetchRun` every 1500ms.
3. `components/App.tsx` drives view state (`home` → `live-running` → `report`/`failed`) via a `useReducer`.

**Authenticated flows:**

1. Magic link: `POST /api/magic-link/request` → email sent with token → user clicks link → `GET /magic-link/verify?token=...` → session established.
2. Dashboard: authenticated user sees `Dashboard` component with Run History and Provider Settings tabs.
3. Run history: `GET /api/user/runs` with pagination and filtering (status, launcher, provider, date, search).
4. Provider credentials: CRUD via `ProviderCredentialController` with encryption at rest.

## State Management

- Backend state is the `runs` database row (`status`, `progress`, `result`, `error`); the SSE stream and job both mutate/read it.
- Frontend state is a `useReducer` `AppUiState` (`backend/resources/ts/components/appUiState.ts`) plus the live `Run` from `useRunSubscription`. Deep links (`/runs/{id}`) are resolved by `hooks/useRunFromPath.ts`.
- No global state library (no Redux, Zustand, Context) — purely local React state.

## Key Abstractions

**AIProviderInterface:**
- Purpose: hide the concrete AI backend; callers depend on `generate(string $prompt, array $schema): array` and `verifyCredential(string $model): bool`.
- Pattern: Strategy + Factory. `App\Support\AiProviders::createProvider()` maps provider IDs to concrete classes via the container. The base URL/model/timeout come from `config/services.php`.

**LauncherInterface / BaseLauncher:**
- Purpose: declare a workflow's metadata in one place; the DB `launchers` row is the runtime instance.
- Pattern: Template Method — `BaseLauncher` supplies shared `outputSchema()` and a `make()` helper; each subclass supplies slug/name/prompt/input_type. Seeded by `DatabaseSeeder`.

**RunExecutorInterface:**
- Purpose: encapsulate the full run pipeline so the job stays thin and the executor is testable/mockable.
- Pattern: bounded context service injected into the job.

**GitHubReference (Data DTO):**
- Purpose: typed, readonly parsed URL (owner/repo/type/number).
- Pattern: immutable value object passed between `GitHubService`, fetcher, and assembler.

## Entry Points

**Web SPA:**
- `backend/routes/web.php` (`Route::view('/{path?}', 'app')`) → `backend/resources/views/app.blade.php` → Vite entry `backend/resources/ts/app.tsx`
- Triggers: direct browser navigation; SPA routing is client-side with deep links to `/runs/{id}`.

**JSON API:**
- `backend/routes/api.php` with both public and `auth`-protected routes.
- Public: health, launchers, providers, runs (create, show, stream).
- Authenticated: user profile, run history (CRUD + retry), provider credentials (CRUD + verify + make-default).

**Queue worker:**
- `backend/app/Jobs/ExecuteLauncherJob.php` consumed by `php artisan queue:work` (config in `backend/config/queue.php`; `QUEUE_CONNECTION` must not be `sync` in production).

**Console commands:**
- `ReapStuckRuns` — cleans up runs stuck in `running` state.
- `artisan migrate`, `db:seed`, `queue:work`, `test`.

**Container / DI:**
- `backend/app/Providers/AppServiceProvider.php` binds `RunExecutorInterface` → `RunExecutor`; registers rate limiters, production DB/TLS guards.

## Error Handling

**Strategy:** Failures are captured into the `runs` row (`status='failed'`, `error` set), never thrown to the client as raw exceptions. The client reads `error` only when status is `failed`.

- Job-level guard: `ExecuteLauncherJob::handle` wraps provider creation in try/catch → `failRun()`.
- Executor-level guard: `RunExecutor::execute` wraps the whole pipeline; `RuntimeException` keeps its message, other `Throwable` becomes a generic message; both logged via `Log::error`.
- Provider errors: `OpenAIProvider` maps 401/403 to "Invalid API key"; `AnthropicProvider` and `GeminiProvider` wrap HTTP calls in try/catch for `ConnectionException`.
- GitHub errors: `GitHubContextFetcher::mapRequestException` translates 404/403/401 into friendly messages.
- Validation: `StoreRunRequest` returns 422; `JsonSchemaValidator` throws `RuntimeException` on bad AI output.
- Frontend: `lib/http.ts` builds messages from `message`/`error` keys; decoders throw on malformed payloads, caught in hooks/components.

## Cross-Cutting Concerns

- **Logging:** Laravel `Log` facade in executor and job; `AppServiceProvider` warns when `LOG_LEVEL=debug` in production.
- **Authentication:** Magic-link session auth for `/api/user/*` routes; public routes use IP-based rate limiting.
- **Rate limiting:** 5 runs/hour/IP, 30 SSE connections/min/IP.
- **Caching:** GitHub context cached 10 min; `CacheRunProgressedVersion` listener for SSE versioning.
- **Production hardening:** Guards against SQLite and non-TLS Postgres in production.
- **Streaming compatibility:** `X-Accel-Buffering: no` header for proxy compatibility.

---

*Architecture analysis: 2026-07-13*
