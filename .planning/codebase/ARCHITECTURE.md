# Architecture

**Analysis Date:** 2026-07-13

## Pattern Overview

**Overall:** Monolithic Laravel 13 application (deploy root `backend/`) serving a React 19 SPA (Vite) plus a queue-backed JSON API. Domain work is asynchronous: HTTP accepts runs, workers execute GitHub + AI pipelines, clients observe progress via SSE (with poll fallback).

**Key Characteristics:**
- **Async job pipeline** — `POST /api/runs` creates a UUID `Run` and dispatches `ExecuteLauncherJob`; no OpenAI/GitHub I/O on the request thread in production (`QUEUE_CONNECTION` must not be `sync`).
- **Contract-driven services** — `AIProviderInterface`, `LauncherInterface`, `RunExecutorInterface` bound in `AppServiceProvider`; launchers are PHP metadata classes seeded into `launchers`.
- **Structured-report UX** — Shared JSON schema (`summary`, `risk`, `findings`, `verification_steps`) validated server-side; React renders reports, not chat.
- **Public + owned runs** — Unauthenticated runs allowed (rate-limited); magic-link auth for history and provider credentials; `RunPolicy` gates private run access.
- **SPA shell** — Catch-all web route serves `resources/views/app.blade.php`; Vite mounts React at `#root`.

## Layers

**Presentation (React SPA):**
- Purpose: Capture GitHub URL + launcher choice, show run progress, render structured reports, manage sign-in and credentials UI.
- Location: `backend/resources/ts/`, shell view `backend/resources/views/app.blade.php`
- Contains: Components (`components/`), hooks (`hooks/`), API clients (`services/`), types (`types/`), UI state reducer (`components/appUiState.ts`)
- Depends on: Same-origin `/api/*` and `/auth/*` endpoints; optional `VITE_DEMO_MODE` for client-only simulation
- Used by: Browser users via `routes/web.php` SPA catch-all

**HTTP / API (Laravel controllers):**
- Purpose: Validate input, authorize, create/query domain records, return JSON or SSE streams.
- Location: `backend/app/Http/Controllers/`, `backend/app/Http/Requests/`, `backend/app/Http/Resources/`, `backend/routes/api.php`, `backend/routes/auth.php`
- Contains: `RunController`, `RunHistoryController`, `ProviderController`, `ProviderCredentialController`, `Auth/MagicLinkController`; form requests; API resources
- Depends on: Eloquent models, policies, jobs, `RunStreamer`
- Used by: SPA services (`resources/ts/services/run.ts`, `auth.ts`) and external API clients

**Domain / Application services:**
- Purpose: Orchestrate a single run: parse GitHub URL, fetch/cache context, call AI with schema, validate result, update progress.
- Location: `backend/app/Services/`, `backend/app/Jobs/`, `backend/app/Launchers/`, `backend/app/Contracts/`, `backend/app/Data/`
- Contains: `RunExecutor`, `GitHubService` (+ fetcher/assembler), AI providers, `JsonSchemaValidator`, `ContextEncoder`, `ExecuteLauncherJob`, launcher classes
- Depends on: Models, Cache, HTTP client, config (`config/services.php`)
- Used by: Queue workers via `ExecuteLauncherJob`; streamer reads DB state updated by executor

**Persistence:**
- Purpose: Store launchers (catalog), runs (execution records), users, magic-login tokens, provider credentials, queue/cache tables.
- Location: `backend/app/Models/`, `backend/database/migrations/`, `backend/database/seeders/`
- Contains: `Run` (UUID), `Launcher`, `User`, `ProviderCredential`; seeder maps launcher classes → DB rows
- Depends on: Laravel DB (local SQLite; production Postgres/MySQL), `CredentialCipher` for encrypted keys
- Used by: Controllers, jobs, policies, SSE poller

**Infrastructure / cross-cutting:**
- Purpose: DI bindings, rate limits, production safety checks, scheduling, deploy packaging.
- Location: `backend/app/Providers/AppServiceProvider.php`, `backend/bootstrap/app.php`, `backend/config/`, `backend/routes/console.php`, `backend/docker/`, `backend/Dockerfile`
- Contains: Interface bindings, rate limiters (`runs`, `runs-stream`, `magic-link`), `ReapStuckRuns` schedule, nginx/supervisor for Dokku
- Depends on: Laravel framework, env config
- Used by: Entire app bootstrap and deploy

## Data Flow

**Create and execute a run:**
1. Client (`createRun` in `resources/ts/services/run.ts`) `POST /api/runs` with `launcher`, `source_url`, optional `provider.id` / `provider.api_key` (aliases: `flow_id`, `input.url`; also `/api/executions`).
2. `StoreRunRequest` validates HTTPS `github.com` URL, launcher slug existence, provider allow-list from `config('services.openai.providers')`.
3. `RunController::store` loads active `Launcher` by slug, creates `Run` (`status: queued`, optional `user_id` if session auth present), dispatches `ExecuteLauncherJob` with run id + optional provider/key, returns **202** `{ id, status, message }`.
4. Worker runs `ExecuteLauncherJob::handle`: resolves `AIProviderInterface` (default `OpenAIProvider` via container; optional per-request apiKey), calls `RunExecutorInterface::execute`.
5. `RunExecutor::execute`: progress “Fetching repository” → `GitHubService::parse` / `context` (Cache 10 min) → store `source_context` → “Running AI analysis” → prompt = `prompt_template` + `ContextEncoder::encode(context)` → `AIProviderInterface::generate($prompt, $output_schema)` → `JsonSchemaValidator::validate` → status `completed` with `result` (clears `source_context`) or `failed` with safe error message.
6. Each progress/terminal update dispatches `RunProgressed` → `CacheRunProgressedVersion` writes cache key `run:version:{uuid}`.

**Observe run progress (SSE):**
1. Client `useRunSubscription` opens `EventSource` on `GET /api/runs/{uuid}/stream` (throttle `runs-stream`); falls back to polling `GET /api/runs/{uuid}` every 1.5s if SSE fails.
2. `RunController::stream` authorizes via `RunPolicy::view`, then `RunStreamer::stream` (~55s window): watches cache version, refreshes run from DB on change, yields SSE `progress` / terminal `completed`|`failed` events with `RunResource` JSON.
3. SPA reducer (`uiStateFromRun`) switches views: live-running → report/failed; deep links via `/runs/{id}` and `useRunFromPath`.

**List launchers / providers:**
1. `GET /api/launchers` (alias `/api/flows`) returns active launcher metadata from DB (seeded from launcher classes).
2. `GET /api/providers` returns configured provider ids/names/models for UI.

**Magic-link auth:**
1. `POST /auth/magic-link` (throttle `magic-link`) creates/finds user, stores hashed token, queues `MagicLinkMail`.
2. `GET /auth/magic-link/{token}` verifies token, logs in, redirects to SPA.
3. Authenticated routes under `/api/user/*` for session user, run history, provider credentials.

**Stuck-run reaping:**
1. Scheduler (`routes/console.php`) runs `app:reap-stuck-runs` every minute in production.
2. Marks long-running `running` rows as `failed` (“Run timed out.”) and dispatches `RunProgressed`.

**State Management:**
- **Server of truth:** `runs` table (`status`, `progress[]`, `result`, `error`, timestamps); statuses: `queued` | `running` | `completed` | `failed`.
- **SSE optimization:** Cache version keys avoid DB hits when unchanged; array cache in tests falls back to always-refresh.
- **Client UI:** `useReducer` in `App.tsx` (`AppUiState` / view machine); live run mirrored by `useRunSubscription`.
- **Demo mode:** `VITE_DEMO_MODE=true` simulates steps client-side without backend worker.

## Key Abstractions

**Run:**
- Purpose: One workflow execution against a GitHub URL; UUID primary key; JSON columns for input/progress/result.
- Examples: `backend/app/Models/Run.php`, migration `backend/database/migrations/2026_01_01_000000_create_launchers_and_runs.php`
- Pattern: Aggregate root for execution lifecycle; optional ownership (`user_id`)

**Launcher (catalog + class metadata):**
- Purpose: Workflow definition: slug, input type (repository | issue | pull_request), prompt template, output JSON schema.
- Examples: `backend/app/Launchers/*`, `backend/app/Models/Launcher.php`, `backend/database/seeders/DatabaseSeeder.php`
- Pattern: Strategy-like metadata classes implementing `LauncherInterface::metadata()`; runtime uses DB row (class name not stored after migration)

**RunExecutor / ExecuteLauncherJob:**
- Purpose: Separate queue boundary (job) from domain steps (executor).
- Examples: `backend/app/Jobs/ExecuteLauncherJob.php`, `backend/app/Services/RunExecutor.php`
- Pattern: Job implements `ShouldQueue` + `ShouldBeEncrypted` (protects optional apiKey); executor is unit-testable service

**AIProviderInterface:**
- Purpose: Swappable AI backends with `generate` (JSON schema), `verifyCredential`, `id`, `models`.
- Examples: `backend/app/Contracts/AIProviderInterface.php`, `OpenAIProvider.php`, `AnthropicProvider.php`, `GeminiProvider.php`
- Pattern: Strategy; default bind `OpenAIProvider` in `AppServiceProvider`; config under `config/services.php` (`openai`, `anthropic`, `gemini`)

**GitHubService pipeline:**
- Purpose: Parse public HTTPS github.com URLs only; fetch REST context without cloning; assemble prompt-friendly structure; cache.
- Examples: `GitHubService.php`, `GitHubContextFetcher.php`, `GitHubContextAssembler.php`, `Data/GitHubReference.php`
- Pattern: Facade + collaborators; 10-minute Cache::remember per URL hash

**RunStreamer + RunProgressed:**
- Purpose: Near-real-time progress without long-lived worker→HTTP coupling.
- Examples: `RunStreamer.php`, `Events/RunProgressed.php`, `Listeners/CacheRunProgressedVersion.php`
- Pattern: Event → cache version bump; SSE polls version then DB snapshot

**ProviderCredential + CredentialCipher:**
- Purpose: Store user AI keys encrypted (Laravel Crypt / APP_KEY); never return plaintext via API.
- Examples: `Models/ProviderCredential.php`, `Security/CredentialCipher.php`, `ProviderCredentialController.php`
- Pattern: Cipher helper + Eloquent hidden columns + policy

## Entry Points

**HTTP SPA:**
- Location: `backend/routes/web.php` → `Route::view('/{path?}', 'app')` (excludes api/up/build/…)
- Triggers: Browser navigation
- Responsibilities: Serve Blade shell + Vite React entry `resources/ts/app.tsx`

**API runs:**
- Location: `backend/routes/api.php` → `RunController`
- Triggers: SPA `createRun` / `fetchRun` / EventSource
- Responsibilities: Queue runs, authorize show/stream, expose launchers/providers/aliases

**Queue worker:**
- Location: `php artisan queue:work` (job `ExecuteLauncherJob`)
- Triggers: Job payload after `dispatch` from store/retry
- Responsibilities: Resolve AI provider, execute full pipeline, persist terminal state

**Auth:**
- Location: `backend/routes/auth.php` → `MagicLinkController`
- Triggers: Sign-in UI / email link
- Responsibilities: Passwordless session auth

**Console / schedule:**
- Location: `backend/routes/console.php`, `app/Console/Commands/ReapStuckRuns.php`
- Triggers: Scheduler in production (every minute)
- Responsibilities: Fail orphaned running runs

**Health:**
- Location: `GET /api/health`, Laravel `health: /up` in `bootstrap/app.php`
- Triggers: Deploy probes
- Responsibilities: Liveness

## Error Handling

**Strategy:** Fail closed on unexpected exceptions with generic client messages; preserve `RuntimeException` messages for expected domain errors (URL type mismatch, schema, provider config). Always clear `source_context` on terminal failure. Log exception class + run id, not secrets.

**Patterns:**
- **Job/executor try/catch** — `RunExecutor` and `ExecuteLauncherJob::failRun` set `status=failed`, `error`, `completed_at`, dispatch `RunProgressed`.
- **Form requests** — `StoreRunRequest` / credential requests for validation; 422 from Laravel.
- **GitHub mapping** — `GitHubContextFetcher` maps HTTP failures to user-facing `RuntimeException`s.
- **Schema validation** — `JsonSchemaValidator` throws on type/required/enum/additionalProperties violations.
- **Policies** — Unauthorized private runs denied via `authorize('view', $run)`.
- **Production guards** — Boot-time `RuntimeException` if production uses sqlite, non-TLS remote pgsql, or `queue.sync`.
- **Stuck reaper** — Operational safety net for abandoned workers.
- **SPA** — `ErrorBoundary`, decode assertions in `run.ts`, SSE error → poll fallback.

## Cross-Cutting Concerns

**Logging:** Laravel Log facade; errors on failed runs (`Launcher run failed`, provider setup failures); warning on reaped runs and debug log level in production. Avoid logging API keys (encrypted job payload via `ShouldBeEncrypted`).

**Validation:** HTTP via Form Requests (`StoreRunRequest` merges legacy `flow_id`/`input.url`); AI output via recursive `JsonSchemaValidator` against launcher `output_schema`; client-side URL checks (`isValidGithubUrl`) and response decoders for type safety.

**Authentication:** Session-based magic link (no password required for primary flow); optional `user_id` on public runs; `auth` middleware group for `/api/user/*`. Named JSON `login` route avoids auth middleware crashes for non-Accept-JSON clients. CSRF token in Blade meta for cookie sessions.

**Authorization:** `RunPolicy` — public runs (`user_id` null) viewable by all; owned runs only by owner; retry/delete owner-only. `ProviderCredentialPolicy` for credential CRUD.

**Rate limiting:** Defined in `AppServiceProvider`: `runs` 5/hour/IP, `runs-stream` 30/min/IP, `magic-link` 3/min per IP+email.

**Caching:** GitHub context 10 minutes; run progress version keys 2 minutes.

**Security notes:** HTTPS-only GitHub hosts; user-supplied provider keys passed only on encrypted queue jobs; credentials encrypted at rest; trust all proxies for TLS termination (Dokku); production HTTPS URL force-scheme.

---

*Architecture analysis: 2026-07-13*
