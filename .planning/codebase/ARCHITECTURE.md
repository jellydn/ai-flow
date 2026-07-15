# Architecture

**Analysis Date:** 2026-07-15

## Pattern Overview

**Overall:** Monolithic Laravel 13 application with a React SPA shell, asynchronous queue workers, and contract-based AI/GitHub integrations.

**Key Characteristics:**
- HTTP layer stays thin: create runs return **202** immediately; heavy work runs in `ExecuteLauncherJob`.
- Workflow behavior is data-driven: launcher metadata (prompt, JSON schema, input type) lives in the DB, seeded from PHP launcher classes.
- Progress is observable via DB-backed state, cache-bumped SSE, and optional client polling fallback.

## Layers

**Presentation (React SPA):**
- Purpose: Launcher selection, GitHub URL input, run progress/report UI, auth and provider-credential management.
- Location: `backend/resources/ts/`
- Contains: `components/`, `hooks/`, `services/`, `lib/`, `types/`, `data/`
- Depends on: Laravel JSON API under `/api`, session cookies + CSRF for mutating requests.
- Used by: `backend/resources/views/app.blade.php` via Vite entry `backend/resources/ts/app.tsx`.

**HTTP / API:**
- Purpose: REST-style JSON endpoints, authorization, validation, SSE streaming.
- Location: `backend/routes/api.php`, `backend/routes/auth.php` (included from `backend/routes/web.php`), `backend/app/Http/`
- Contains: Controllers, `StoreRunRequest` and other form requests, `RunResource` / `UserResource`.
- Depends on: Models, jobs, services, policies.
- Used by: SPA `services/run.ts`, `services/auth.ts`.

**Domain / orchestration:**
- Purpose: Run lifecycle, launcher execution pipeline, provider resolution.
- Location: `backend/app/Services/RunExecutor.php`, `backend/app/Jobs/ExecuteLauncherJob.php`, `backend/app/Launchers/`
- Contains: GitHub fetch/encode, AI generation, JSON schema validation, progress updates.
- Depends on: `AIProviderInterface`, `GitHubService`, `JsonSchemaValidator`, `AiProviderRegistry`.
- Used by: Queue worker invoking `ExecuteLauncherJob`.

**Infrastructure:**
- Purpose: External APIs, encryption, caching for SSE, rate limits.
- Location: `backend/app/Services/*Provider.php`, `backend/app/Services/GitHubService.php`, `backend/app/Security/CredentialCipher.php`, `backend/app/Listeners/CacheRunProgressedVersion.php`
- Contains: OpenAI/OpenRouter/Anthropic/Gemini adapters, GitHub context assembly, credential crypto.
- Depends on: `config/services.php`, env keys, HTTP clients.
- Used by: Jobs and credential CRUD controllers.

**Persistence:**
- Purpose: Users, launchers, runs, jobs queue, provider credentials, magic-link tokens.
- Location: `backend/app/Models/`, `backend/database/migrations/`
- Contains: Eloquent models and migrations (SQLite local/CI; Postgres/MySQL production).

## Data Flow

**Create and execute a run:**
1. Client `POST /api/runs` with `launcher`, `source_url`, optional `provider` / `provider_credential_id` (`backend/app/Http/Controllers/RunController.php`, `backend/app/Http/Requests/StoreRunRequest.php`).
2. Controller resolves active `Launcher` by slug, creates `Run` with `status: queued`, attaches `user_id` when session-authenticated.
3. `ExecuteLauncherJob::dispatch(...)` enqueues work (`ShouldQueue`, `ShouldBeEncrypted` for transient API key material).
4. Worker `handle()` resolves provider via `AiProviderRegistry`, decrypts saved credential if needed, calls `RunExecutorInterface::execute()`.
5. `RunExecutor` validates URL type vs `launcher.input_type`, loads GitHub context via `GitHubService`, builds prompt from `prompt_template` + `ContextEncoder`, calls `AIProviderInterface::generate()` with `output_schema`, validates with `JsonSchemaValidator`, persists `result` and `status: completed` (or `failed`).
6. Each progress step updates `runs` and dispatches `RunProgressed`; listener bumps cache version for SSE.

**Observe run status (poll):**
1. Client `GET /api/runs/{run}` with optional session for private runs.
2. `RunPolicy::view` allows public runs (`user_id` null) to anyone; owned runs require matching user.
3. `RunResource` serializes run + launcher snapshot.

**Observe run status (SSE):**
1. Client `GET /api/runs/{run}/stream` (`throttle:runs-stream`).
2. Session lock released before long-lived loop so parallel API calls are not blocked.
3. `RunStreamer` polls until ~55s deadline: reads cache version key from `CacheRunProgressedVersion`, refreshes DB when version changes, yields `progress` events and terminal `completed` / `failed` events with `RunResource` JSON.
4. SPA `useRunSubscription` uses `EventSource` with polling fallback (`backend/resources/ts/hooks/useRunSubscription.ts`).

**State Management:**
- Server: run row is source of truth (`status`, `progress[]`, `result`, `error`, timestamps).
- Client: `App.tsx` uses `useReducer` + `appUiState.ts`; active run synced via `useRunSubscription` / `useRunFromPath`.
- No global client store beyond React local state and URL-driven views.

## Key Abstractions

**Launcher (workflow definition):**
- Purpose: Maps slug to prompt, input type (`pull_request` | `issue` | `repository`), and shared JSON output schema.
- Examples: `backend/app/Launchers/ReviewPullRequestLauncher.php`, `PlanIssueLauncher.php`, `ExplainRepositoryLauncher.php`, `LaravelDoctorLauncher.php`; metadata factory `backend/app/Launchers/BaseLauncher.php`.
- Pattern: Static `metadata()` on each class; `DatabaseSeeder` upserts into `launchers` table; runtime reads from `Launcher` model only.

**AI provider:**
- Purpose: Pluggable LLM backends with structured JSON output.
- Examples: `backend/app/Services/OpenAIProvider.php`, `AnthropicProvider.php`, `GeminiProvider.php`, `OpenRouterProvider.php`; registry `backend/app/Support/AiProviderRegistry.php`.
- Pattern: `AIProviderInterface`; IDs from registry (not a static config list); user keys via one-time request field or `ProviderCredential` (never stored on `runs`).

**Run executor:**
- Purpose: Single pipeline for all launchers after job dequeue.
- Examples: `backend/app/Services/RunExecutor.php`, contract `backend/app/Contracts/RunExecutorInterface.php`.
- Pattern: Bound in `backend/app/Providers/AppServiceProvider.php`; job depends on interface, not concrete class.

## Entry Points

**Web UI:**
- Location: `backend/routes/web.php` → `app` view; `backend/resources/ts/app.tsx` mounts `App`.
- Triggers: Browser navigation to any non-API path.
- Responsibilities: Client-side routing by path hooks; calls `/api/*`.

**API bootstrap:**
- Location: `backend/bootstrap/app.php` registers `web`, `api` (`backend/routes/api.php`), `console`, health `/up`.
- Triggers: HTTP to `/api/...` (Laravel default API prefix).

**Queue worker:**
- Location: `php artisan queue:work` processing `ExecuteLauncherJob`.
- Triggers: Job dispatch from `RunController::store` or `RunHistoryController::retry`.
- Responsibilities: AI + GitHub IO outside request cycle.

**Auth routes:**
- Location: `backend/routes/auth.php` — register/login password, magic link request/verify, logout.
- Triggers: SPA sign-in flows via `backend/resources/ts/services/auth.ts`.

## Error Handling

**Strategy:** Fail runs with user-safe messages; log and Sentry for unexpected errors; validation at HTTP boundary.

**Patterns:**
- `RunExecutor` catches `RuntimeException` for expected failures (URL/type mismatch) vs generic message for other throwables; sets `status: failed`, clears `source_context`, dispatches `RunProgressed`.
- `ExecuteLauncherJob` fails early on unsupported provider; uses `failRun()` helper for consistent persistence.
- API validation via form requests; JSON 401/403 via policies and `auth` middleware on `/api/user/*`.

## Cross-Cutting Concerns

**Logging:** Laravel `Log`; production guard against `LOG_LEVEL=debug` in `AppServiceProvider`.

**Validation:** `StoreRunRequest` for runs; `PublicHttpUrl` rule for GitHub URLs; `JsonSchemaValidator` for AI output against launcher schema.

**Authentication:** Session-based (`web` middleware on run create/show/stream and user routes); magic link + password registration/login; rate limiters `magic-link`, `auth-login`, `auth-register` in `AppServiceProvider`. Provider credentials scoped per user with `ProviderCredentialPolicy`.

**Rate limiting:** `runs` (5/hr/IP), `runs-stream` (30/min/IP), `credentials` (10/min/user).

---

*Architecture analysis: 2026-07-15*
