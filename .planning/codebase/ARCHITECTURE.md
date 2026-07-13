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
- Monorepo deploy: only `backend/` is the application root on Laravel Cloud and Dokku; the React UI is bundled inside it.

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
1. `POST /api/runs` hits `routes/api.php` → `RunController::store`.
2. `StoreRunRequest` validates `launcher` (exists in `launchers`), `source_url` (https github.com regex), and optional `provider.id`/`provider.api_key`.
3. Controller looks up the active `Launcher`, creates a `Run` with `status='queued'`, resolves provider, dispatches `ExecuteLauncherJob`, returns `202`.
4. Queue worker runs `ExecuteLauncherJob::handle` → builds provider → calls `RunExecutorInterface::execute`.
5. `RunExecutor::execute`: parse URL → fetch/cache context → encode context → AI generate → validate schema → update run as `completed`/`failed`. Each step emits `RunProgressed` and appends progress message.
6. On any `Throwable`, run is marked `failed` with user-safe error, server-logged, `RunProgressed` dispatched.

**Streaming / progress delivery (SSE):**
1. `GET /api/runs/{run}/stream` → `RunController::stream` → `response()->eventStream(...)`.
2. `RunStreamer::stream` polls the `Run` row every second for up to `55s`, yielding `progress` on change and terminal `completed`/`failed` event.
3. SSE headers disable buffering (`X-Accel-Buffering: no`, `Cache-Control: no-cache`).

**Frontend consumption:**
1. `services/run.ts` posts to `/api/runs` (`createRun`) and parses runs (`fetchRun` + `decodeRun`).
2. `hooks/useRunSubscription.ts` opens `EventSource` to `/api/runs/{id}/stream`; on error or absence, falls back to polling `fetchRun` every 1500ms.
3. `components/App.tsx` drives view state (`home` → `live-running` → `report`/`failed`) via `useReducer`.

## Key Abstractions

| Abstraction | Purpose | Location |
|-------------|---------|----------|
| **AIProviderInterface** | Hide concrete AI backend behind `generate(string $prompt, array $schema): array` | `backend/app/Contracts/AIProviderInterface.php` |
| **LauncherInterface / BaseLauncher** | Declare workflow metadata; DB `launchers` row is runtime instance | `backend/app/Contracts/LauncherInterface.php`, `backend/app/Launchers/BaseLauncher.php` |
| **RunExecutorInterface** | Encapsulate full run pipeline for testability | `backend/app/Contracts/RunExecutorInterface.php`, `backend/app/Services/RunExecutor.php` |
| **GitHubReference (Data DTO)** | Typed, readonly parsed URL (owner/repo/type/number) | `backend/app/Data/GitHubReference.php` |

## Error Handling

**Strategy:** Failures are captured into the `runs` row (`status='failed'`, `error` set), never thrown to the client as raw exceptions.

- Job-level guard: `ExecuteLauncherJob::handle` wraps provider creation in try/catch → `failRun()`.
- Executor-level guard: `RunExecutor::execute` wraps pipeline; `RuntimeException` keeps its message, other `Throwable` becomes generic.
- Provider errors: `OpenAIProvider` maps 401/403 to "Invalid API key", other non-2xx to generic failure.
- GitHub errors: `GitHubContextFetcher` translates 404/403/401 into friendly messages.
- Frontend: `lib/http.ts` builds messages from `message`/`error` keys; `services/run.ts` decoders throw on malformed payloads.

---

*Architecture analysis: 2026-07-13*
