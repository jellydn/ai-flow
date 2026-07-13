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
- Contains: `RunController`, `StoreRunRequest` (form request validation), `RunResource` (JSON shape)
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
- Contains: `Run.php` (UUID key, `status`/`progress`/`input`/`source_context`/`result`/`error` casts), `Launcher.php`, `User.php`, and the readonly DTO `GitHubReference.php`
- Used by: every layer above

**Frontend / SPA layer:**
- Purpose: present the launcher picker, run progress, and final report; subscribe to run updates.
- Location: `backend/resources/ts/`
- Contains: `app.tsx` (entry), `components/` (App, Home, Running, Report, etc.), `services/run.ts` (HTTP + decoders), `hooks/useRunSubscription.ts` (SSE + polling fallback), `hooks/useRunFromPath.ts` (deep-link), `types/api.ts` (contracts), `lib/http.ts`
- Depends on: same-origin `/api/*` endpoints
- Used by: the browser via `backend/resources/views/app.blade.php`

## Data Flow

**Create-and-run (primary flow):**
1. `POST /api/runs` hits `routes/api.php` → `RunController::store` (`backend/app/Http/Controllers/RunController.php:22`).
2. `StoreRunRequest` validates `launcher` (exists in `launchers`), `source_url` (https github.com regex), and optional `provider.id`/`provider.api_key` (`backend/app/Http/Requests/StoreRunRequest.php`).
3. Controller looks up the active `Launcher`, creates a `Run` with `status='queued'` and empty `progress`, then resolves `provider.id` (default `openai` via `App\Support\AiProviders`) (`RunController.php:24-31`).
4. `ExecuteLauncherJob::dispatch($run->id, $providerId, $apiKey)` is called; controller returns `202` JSON `{id, status, message}` (`RunController.php:34-40`).
5. Queue worker runs `ExecuteLauncherJob::handle` → builds the provider through `AiProviders::createProvider` (throws → `failRun`) → calls `RunExecutorInterface::execute` (`backend/app/Jobs/ExecuteLauncherJob.php:30-47`).
6. `RunExecutor::execute` (`backend/app/Services/RunExecutor.php:21`):
   - `GitHubService::parse($url)` → `GitHubReference` (`backend/app/Services/GitHubService.php:16`); rejects mismatched `input_type`.
   - `GitHubService::context($url)` → cached (10 min) fetch+assemble from `GitHubContextFetcher` + `GitHubContextAssembler` (`GitHubService.php:44`).
   - `ContextEncoder::encode($context)` bounds the payload (`backend/app/Services/ContextEncoder.php:9`).
   - `AIProviderInterface::generate($prompt, $outputSchema)` → `OpenAIProvider` calls `/chat/completions` with `json_schema` response format (`backend/app/Services/OpenAIProvider.php:13`).
   - `JsonSchemaValidator::validate($result, $outputSchema)` enforces shape/types (`backend/app/Services/JsonSchemaValidator.php:9`).
   - Updates `Run` to `status='completed'` with `result`, clears `source_context` (`RunExecutor.php:41`).
   - Each step emits `RunProgressed` event and appends a progress message (`RunExecutor.php:56`).
7. On any `Throwable`, the run is marked `failed` with a user-safe `error`, server-logged, and `RunProgressed` is dispatched (`RunExecutor.php:48-53`).

**Streaming / progress delivery (SSE):**
1. `GET /api/runs/{run}/stream` → `RunController::stream` → `response()->eventStream(...)` (`RunController.php:48`).
2. `RunStreamer::stream` polls the `Run` row every second for up to `55s`, yielding a `StreamedEvent` `progress` on change and a terminal `completed`/`failed` event, then breaks (`backend/app/Services/RunStreamer.php:20`).
3. SSE headers disable buffering (`X-Accel-Buffering: no`, `Cache-Control: no-cache`) for proxy compatibility (`RunController.php:52`).

**Frontend consumption:**
1. `services/run.ts` posts to `/api/runs` (`createRun`) and parses the run on `/api/runs/{id}` (`fetchRun` + `decodeRun` with strict runtime validation) (`backend/resources/ts/services/run.ts:160`).
2. `hooks/useRunSubscription.ts` opens an `EventSource` to `/api/runs/{id}/stream`, handling `progress`/`completed`/`failed` events; on `EventSource` absence or stream error it falls back to polling `fetchRun` every 1500ms (`backend/resources/ts/hooks/useRunSubscription.ts:99`).
3. `components/App.tsx` drives view state (`home` → `live-running` → `report`/`failed`) via a `useReducer` (`backend/resources/ts/components/App.tsx:47`).

**State Management:**
- Backend state is the `runs` database row (`status`, `progress`, `result`, `error`); the SSE stream and job both mutate/read it.
- Frontend state is a `useReducer` `AppUiState` (`backend/resources/ts/components/appUiState.ts`) plus the live `Run` from `useRunSubscription`. Deep links (`/runs/{id}`) are resolved by `hooks/useRunFromPath.ts`.

## Key Abstractions

**AIProviderInterface:**
- Purpose: hide the concrete AI backend; callers depend on `generate(string $prompt, array $schema): array`.
- Examples: `backend/app/Contracts/AIProviderInterface.php`, `backend/app/Services/OpenAIProvider.php`
- Pattern: Strategy + Factory. `App\Support\AiProviders::createProvider()` maps `openai` → `OpenAIProvider` via the container (`backend/app/Support/AiProviders.php:19`). The base URL/model/timeout come from `config/services.php` (`openai` key), so OpenRouter or any OpenAI-compatible endpoint is supported via `AI_BASE_URL`.

**LauncherInterface / BaseLauncher:**
- Purpose: declare a workflow's metadata in one place; the DB `launchers` row is the runtime instance.
- Examples: `backend/app/Contracts/LauncherInterface.php`, `backend/app/Launchers/BaseLauncher.php`, the four concrete launchers
- Pattern: Template Method — `BaseLauncher` supplies shared `outputSchema()` (summary/risk/findings/verification_steps) and a `make()` helper; each subclass supplies slug/name/prompt/input_type. Seeded by `DatabaseSeeder`.

**RunExecutorInterface:**
- Purpose: encapsulate the full run pipeline so the job stays thin and the executor is testable/mockable.
- Examples: `backend/app/Contracts/RunExecutorInterface.php`, `backend/app/Services/RunExecutor.php`
- Pattern: bounded context service injected into the job.

**GitHubReference (Data DTO):**
- Purpose: typed, readonly parsed URL (owner/repo/type/number).
- Examples: `backend/app/Data/GitHubReference.php`
- Pattern: immutable value object passed between `GitHubService`, fetcher, and assembler.

## Entry Points

**Web SPA:**
- Location: `backend/routes/web.php` (`Route::view('/{path?}', 'app')`) → `backend/resources/views/app.blade.php` → Vite entry `backend/resources/ts/app.tsx`
- Triggers: direct browser navigation; SPA routing is client-side with deep links to `/runs/{id}`.
- Responsibilities: render the React app; Vite serves `resources/ts/app.tsx` (`backend/vite.config.ts:8`).

**JSON API:**
- Location: `backend/routes/api.php`
- Triggers: `POST /api/runs` (+ alias `/api/executions`), `GET /api/runs/{run}` (alias `/api/executions/{run}`), `GET /api/runs/{run}/stream` (alias `/api/executions/{run}/stream`), `GET /api/launchers` (+ alias `/api/flows`), `GET /api/health`.
- Responsibilities: validate and enqueue runs, expose launcher catalog, stream progress, report health.

**Queue worker:**
- Location: `backend/app/Jobs/ExecuteLauncherJob.php` consumed by `php artisan queue:work` (config in `backend/config/queue.php`; `QUEUE_CONNECTION` must not be `sync` in production).
- Triggers: `ExecuteLauncherJob::dispatch` from `RunController::store`.
- Responsibilities: build the provider, run `RunExecutor`, persist results/failures. Job implements `ShouldBeEncrypted` (`backend/app/Jobs/ExecuteLauncherJob.php:16`).

**Artisan console:**
- Location: `backend/routes/console.php` (only `inspire` stub); real commands include `migrate`, `db:seed`, `queue:work`, `test`.
- Responsibilities: operational tasks; seeding launchers via `DatabaseSeeder`.

**Container / DI:**
- Location: `backend/app/Providers/AppServiceProvider.php:21` binds `RunExecutorInterface` → `RunExecutor`; also registers rate limiters and production DB/TLS guards.

## Error Handling

**Strategy:** Failures are captured into the `runs` row (`status='failed'`, `error` set), never thrown to the client as raw exceptions. The client reads `error` only when status is `failed` (`backend/app/Http/Resources/RunResource.php:19`).

**Patterns:**
- Job-level guard: `ExecuteLauncherJob::handle` wraps provider creation in try/catch → `failRun()` (`backend/app/Jobs/ExecuteLauncherJob.php:36-44`).
- Executor-level guard: `RunExecutor::execute` wraps the whole pipeline; `RuntimeException` keeps its message, other `Throwable` becomes a generic message; both log via `Log::error` (`backend/app/Services/RunExecutor.php:48-53`).
- Provider errors: `OpenAIProvider` maps 401/403 to "Invalid API key" and other non-2xx to a generic failure; missing key → `RuntimeException` (`backend/app/Services/OpenAIProvider.php:46-55`).
- GitHub errors: `GitHubContextFetcher::mapRequestException` translates 404/403/401 into friendly messages (`backend/app/Services/GitHubContextFetcher.php:63`).
- Validation errors: `StoreRunRequest` returns 422 with field errors; `JsonSchemaValidator` throws `RuntimeException` on bad AI output (`backend/app/Services/JsonSchemaValidator.php:23`).
- Frontend: `lib/http.ts` builds messages from `message`/`error` keys; `services/run.ts` decoders throw on malformed payloads, caught and surfaced in `useRunSubscription` / `App`.

## Cross-Cutting Concerns

**Logging:** Laravel `Log` facade in executor and job; `AppServiceProvider` warns when `LOG_LEVEL=debug` in production (`backend/app/Providers/AppServiceProvider.php:57`).

**Validation:** two layers — HTTP `StoreRunRequest` (input) and `JsonSchemaValidator` (AI output vs `launchers.output_schema`). Frontend uses runtime assertion decoders in `services/run.ts`.

**Authentication / Authorization:** no API auth; the API is IP-throttled. Per-request AI key may be supplied via `provider.api_key`, otherwise falls back to server `config('services.openai.key')`. `StoreRunRequest::authorize()` returns `true` (`backend/app/Http/Requests/StoreRunRequest.php:19`).

**Rate limiting:** `AppServiceProvider` defines `runs` (5/hour/IP) and `runs-stream` (30/min/IP) limiters applied in `routes/api.php` (`backend/app/Providers/AppServiceProvider.php:29-30`).

**Caching:** `GitHubService::context` caches assembled context for 10 minutes keyed by `sha1(url)` (`backend/app/Services/GitHubService.php:47`).

**Production hardening:** `AppServiceProvider` throws if production uses `sqlite` or `pgsql` without TLS (`sslmode` require/verify-ca/verify-full) (`backend/app/Providers/AppServiceProvider.php:34-55`).

**Streaming compatibility:** SSE responses send `X-Accel-Buffering: no` so upstream proxies (Laravel Cloud) don't buffer (`backend/app/Http/Controllers/RunController.php:52`).

---

*Architecture analysis: 2026-07-13*
