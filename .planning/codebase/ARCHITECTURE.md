# Architecture

**Analysis Date:** 2026-07-14

## Pattern Overview

**Overall:** Laravel API backend with a queued job worker, exposing a thin HTTP layer to a React/TypeScript single-page app bundled by Vite. The defining pattern is **dispatch-and-poll**: the HTTP request only creates a `Run` row and dispatches a queue job (returning `202 Accepted`); all slow work (GitHub fetch + AI generation) happens asynchronously on the queue, and the client learns about progress through server-sent events that poll the same database row.

**Key Characteristics:**
- Dispatcher pattern: controllers return `202` and enqueue work; no synchronous GitHub/OpenAI calls in the request cycle.
- Service-oriented backend: orchestration lives in `app/Services/`, not controllers.
- Strategy/Factory for AI providers: `AIProviderInterface` with a registry in `app/Support/AiProviderRegistry.php`.
- Declarative launchers: each workflow is a small class returning static `metadata()` seeded into the `launchers` table.
- Database-as-state: a `Run` row is the single source of truth for status/progress/result; both the job and the SSE stream read/write that same row.
- SSE streaming via DB polling: `RunStreamer` yields `StreamedEvent`s every second for up to ~55s.
- Monorepo deploy: only `backend/` is the application root; the React UI is bundled inside it.
- Magic-link auth: passwordless email sign-in with hashed tokens, no passwords stored.
- BYOK credentials: encrypted at rest via `CredentialCipher` (AES-256), decrypted only inside the queue job.

## Layers

**HTTP / Routing layer:**
- Purpose: receive requests, validate input, return JSON or SSE; never performs I/O-heavy work.
- Location: `backend/app/Http/`, `backend/routes/api.php`, `backend/routes/web.php`
- Contains: `RunController`, `StoreRunRequest` (form request validation), `RunResource` (JSON shape), `MagicLinkController`, `AccountController`, `ProviderCredentialController`, `RunHistoryController`, `ProviderController`
- Depends on: `App\Jobs\ExecuteLauncherJob`, `App\Services\RunStreamer`, `App\Models\*`
- Used by: external API clients and the bundled SPA

**Service layer:**
- Purpose: all domain logic — orchestration, GitHub context building, AI generation, schema validation, SSE streaming, credential encryption.
- Location: `backend/app/Services/`, `backend/app/Security/`
- Contains:
  - `RunExecutor.php` — orchestrates a single run end-to-end
  - `GitHubService.php` — URL parse + cached context fetch (composition of fetcher + assembler)
  - `GitHubContextFetcher.php` — raw GitHub REST calls
  - `GitHubContextAssembler.php` — shapes raw data into a context array
  - `ContextEncoder.php` — serializes/bounds context to a byte budget
  - `JsonSchemaValidator.php` — validates AI JSON output against a schema
  - `OpenAIProvider.php`, `OpenRouterProvider.php`, `AnthropicProvider.php`, `GeminiProvider.php` — concrete `AIProviderInterface` adapters
  - `RunStreamer.php` — SSE generator polling the `runs` table
  - `CredentialCipher.php` — AES-256 encrypt/decrypt/mask for BYOK keys
- Depends on: `App\Contracts\*`, `App\Models\*`, `App\Data\GitHubReference`, `App\Events\RunProgressed`, Laravel `Http`/`Cache`/`Crypt`/`Log` facades
- Used by: `RunController` (stream) and `ExecuteLauncherJob` (executor)

**Job / Queue layer:**
- Purpose: run a single run off the queue, resilient to failure.
- Location: `backend/app/Jobs/ExecuteLauncherJob.php`
- Contains: a `ShouldQueue` + `ShouldBeEncrypted` job (`$tries = 2`, `$timeout = 120`)
- Key logic: resolves provider via `AiProviderRegistry`, decrypts saved credentials via `CredentialCipher` (decrypt-on-use only), delegates to `RunExecutorInterface`
- Depends on: `App\Contracts\RunExecutorInterface`, `App\Support\AiProviderRegistry`, `App\Events\RunProgressed`, `App\Models\ProviderCredential`, `App\Security\CredentialCipher`
- Used by: `RunController::store` (dispatch)

**Launcher layer (workflow definitions):**
- Purpose: declare each workflow's slug, name, prompt template, accepted input type, and shared output schema.
- Location: `backend/app/Launchers/`
- Contains: `BaseLauncher.php` (abstract, shared `outputSchema()` + `make()`) and four concrete launchers: `ReviewPullRequestLauncher`, `PlanIssueLauncher`, `ExplainRepositoryLauncher`, `LaravelDoctorLauncher`
- Depends on: `App\Contracts\LauncherInterface`
- Used by: `backend/database/seeders/DatabaseSeeder.php` (seeds `launchers` rows)

**Contracts / Abstractions layer:**
- Purpose: define swappable boundaries (AI provider, launcher shape, executor).
- Location: `backend/app/Contracts/`
- Contains: `AIProviderInterface.php` (`generate`, `verifyCredential`, `id`, `models`), `LauncherInterface.php` (`metadata`), `RunExecutorInterface.php` (`execute`)
- Used by: services, jobs, and `AppServiceProvider` (binding)

**Domain / Model layer:**
- Purpose: persistence, value objects, and domain events.
- Location: `backend/app/Models/`, `backend/app/Data/`, `backend/app/Events/`
- Contains: `Run.php` (UUID key, `status`/`progress`/`input`/`source_context`/`result`/`error`/`user_id`/`provider`/`model` casts), `Launcher.php`, `User.php` (passwordless, `last_login_at`), `ProviderCredential.php` (UUID, encrypted key, `is_default` auto-deselect), `GitHubReference.php` (readonly DTO), `RunProgressed.php` (event)
- Used by: every layer above

**Auth layer:**
- Purpose: passwordless magic-link authentication, session management, account lifecycle.
- Location: `backend/app/Http/Controllers/Auth/MagicLinkController.php`, `backend/app/Mail/MagicLinkMail.php`, `backend/app/Policies/`
- Contains: `request` (email → token → mail), `verify` (token → session), `logout`, `RunPolicy` (ownership), `ProviderCredentialPolicy` (ownership)
- Depends on: `App\Models\User`, `App\Mail\MagicLinkMail`, Laravel `Auth`, `DB`, `Mail` facades
- Used by: `routes/api.php` (auth middleware group), `routes/web.php` (auth routes)

**Frontend / SPA layer:**
- Purpose: present the launcher picker, run progress, and final report; manage auth UI, dashboard, credential management.
- Location: `backend/resources/ts/`
- Contains: `app.tsx` (entry + Sentry init), `components/` (App, AppViews, Home, Running, Report, Dashboard, Header, Footer, SignIn, LaunchArea, LauncherSelector, UrlInput, CredentialForm, CredentialList, ProviderSettings, RunHistory, PrivacyNote, ErrorBoundary, Logo, LauncherIcon), `services/run.ts` (HTTP + decoders), `services/auth.ts` (auth + credential API), `hooks/useRunSubscription.ts` (SSE + polling fallback), `hooks/useRunFromPath.ts` (deep-link), `types/api.ts` (contracts), `lib/http.ts`, `lib/navigate.ts`, `data/launcherMeta.ts` (demo data + metadata), `components/appUiState.ts` (view state machine)
- Depends on: same-origin `/api/*` endpoints
- Used by: the browser via `backend/resources/views/app.blade.php`

## Data Flow

**Create-and-run (primary flow):**
1. `POST /api/runs` → `RunController::store` validates via `StoreRunRequest` (launcher exists, GitHub URL, optional `provider.id`, `provider.api_key`, `provider_credential_id`).
2. Controller creates `Run` with `status='queued'`, resolves provider ID, dispatches `ExecuteLauncherJob::dispatch($run->id, $providerId, $apiKey, $providerCredentialId)`, returns `202`.
3. Queue worker runs `ExecuteLauncherJob::handle` → resolves provider via `AiProviderRegistry::get($providerId, $resolveApiKey($providerId))` (decrypts saved credential if present) → calls `RunExecutorInterface::execute`.
4. `RunExecutor::execute`: parses URL via `GitHubService::parse`, fetches cached context via `GitHubService::context`, encodes via `ContextEncoder`, calls `AIProviderInterface::generate($prompt, $schema)`, validates via `JsonSchemaValidator`, updates `Run` to `completed` with `result`, clears `source_context`. Each step emits `RunProgressed`.
5. On failure: `Run` marked `failed` with user-safe `error`, `RunProgressed` dispatched.

**Streaming / progress delivery (SSE):**
1. `GET /api/runs/{run}/stream` → `RunController::stream` → `RunStreamer::stream` polls `Run` row every second for up to 55s.
2. Yields `progress` on change, terminal `completed`/`failed` event, then breaks.
3. SSE headers: `X-Accel-Buffering: no`, `Cache-Control: no-cache`.

**Auth flow (magic link):**
1. `POST /api/auth/magic-link` with email → `MagicLinkController::request` → creates/finds user, generates token, queues `MagicLinkMail`.
2. User clicks link → `GET /auth/magic-link/{token}` → `MagicLinkController::verify` → validates token (not used, not expired) → `Auth::login($user, true)` → `session()->regenerate()` → redirect to app.
3. `POST /api/auth/logout` → `MagicLinkController::logout` → session invalidate.

**Credential management:**
1. `GET /api/user/provider-credentials` → `ProviderCredentialController::index` → returns masked credentials via `ProviderCredentialResource`.
2. `POST /api/user/provider-credentials` → `ProviderCredentialController::store` → encrypts key via `CredentialCipher`, saves `ProviderCredential`.
3. `POST /api/user/provider-credentials/{id}/verify` → calls `$provider->verifyCredential($decryptedKey)`.
4. `DELETE /api/user/account` → `AccountController::destroy` → logout first, then cascade-delete runs + credentials + user.

**Frontend consumption:**
1. `services/run.ts` posts to `/api/runs` and parses runs with strict runtime decoders.
2. `hooks/useRunSubscription.ts` opens `EventSource` to `/api/runs/{id}/stream`, falls back to polling on error.
3. `components/App.tsx` drives view state (`home` → `demo-running`/`live-running` → `report`/`failed`) via `useReducer`.
4. Auth state: `fetchUser()` on mount → if authenticated, shows `Dashboard`; if not, shows `Home` with `SignIn` option.

**Stuck run reaper:**
1. `app:reap-stuck-runs` scheduled every minute in production via `routes/console.php`.
2. Finds runs with `status='running'` older than TTL (180s default), marks them `failed`, dispatches `RunProgressed`.

## State Management

- Backend state: the `runs` database row (`status`, `progress`, `result`, `error`, `user_id`, `provider`, `model`); the SSE stream and job both mutate/read it.
- Frontend state: a `useReducer` `AppUiState` (`backend/resources/ts/components/appUiState.ts`) plus the live `Run` from `useRunSubscription`. Deep links (`/runs/{id}`) resolved by `hooks/useRunFromPath.ts`.
- Auth state: `User | null` in `App.tsx` `useState`, credentials in `useState<ProviderCredential[]>`.

## Key Abstractions

**AIProviderInterface:**
- Purpose: hide the concrete AI backend; callers depend on `generate(string $prompt, array $schema): array` and `verifyCredential(string $apiKey): array`.
- Pattern: Strategy + Factory. `AiProviderRegistry` is the **single source of truth** for provider IDs (`ids()`, `has()`) and adapter instantiation (`get()`). It replaces the former `config/services.php` provider array — config is now only for per-provider credentials/base URLs/models, not for which providers exist. Config-driven base URLs/models/timeouts.

**LauncherInterface / BaseLauncher:**
- Purpose: declare a workflow's metadata in one place; the DB `launchers` row is the runtime instance.
- Pattern: Template Method — `BaseLauncher` supplies shared `outputSchema()` and a `make()` helper; each subclass supplies slug/name/prompt/input_type.

**RunExecutorInterface:**
- Purpose: encapsulate the full run pipeline so the job stays thin and the executor is testable/mockable.
- Pattern: bounded context service injected into the job.

**CredentialCipher:**
- Purpose: encrypt/decrypt BYOK API keys at the boundary, never expose plaintext outside the job.
- Pattern: stateless service using Laravel `Crypt` facade.

**GitHubReference (Data DTO):**
- Purpose: typed, readonly parsed URL (owner/repo/type/number).
- Pattern: immutable value object passed between `GitHubService`, fetcher, and assembler.

## Entry Points

**Web SPA:** `backend/routes/web.php` → `resources/views/app.blade.php` → Vite entry `resources/ts/app.tsx`

**JSON API:** `backend/routes/api.php` — `POST /api/runs`, `GET /api/runs/{run}`, `GET /api/runs/{run}/stream`, `GET /api/launchers`, `GET /api/providers`, auth group (`/api/user/*`), auth routes (`/api/auth/*`)

**Queue worker:** `backend/app/Jobs/ExecuteLauncherJob.php` consumed by `php artisan queue:work`

**Artisan console:** `backend/routes/console.php` — schedules `app:reap-stuck-runs` every minute in production

**Container / DI:** `backend/app/Providers/AppServiceProvider.php` — binds `RunExecutorInterface` → `RunExecutor`, registers `AiProviderRegistry` as singleton, defines rate limiters (`runs`, `runs-stream`, `magic-link`, `credentials`), production DB/TLS/queue guards

## Error Handling

**Strategy:** Failures are captured into the `runs` row (`status='failed'`, `error` set), never thrown to the client as raw exceptions.

- Job-level: `ExecuteLauncherJob::handle` wraps provider creation in try/catch → `failRun()`.
- Executor-level: `RunExecutor::execute` wraps the whole pipeline; `RuntimeException` keeps its message, other `Throwable` becomes generic.
- Provider errors: 401/403 → "Invalid API key", other non-2xx → generic failure, `ConnectionException` → network error.
- GitHub errors: 404/403/401 mapped to friendly messages in `GitHubContextFetcher`.
- Validation: `StoreRunRequest` returns 422; `JsonSchemaValidator` throws on bad AI output.
- Frontend: `lib/http.ts` builds messages from `message`/`error` keys; `services/run.ts` decoders throw on malformed payloads.

## Cross-Cutting Concerns

**Logging:** Laravel `Log` facade in executor, job, and reaper; `AppServiceProvider` warns when `LOG_LEVEL=debug` in production.

**Validation:** Two layers — HTTP form requests (input) and `JsonSchemaValidator` (AI output). Frontend uses runtime assertion decoders.

**Authentication / Authorization:** No API auth for public endpoints (launchers, runs). Auth group via `auth` middleware. Policies: `RunPolicy`, `ProviderCredentialPolicy` enforce ownership. Per-request AI key via `provider.api_key` or saved credential.

**Rate limiting:** `runs` (5/hour/IP), `runs-stream` (30/min/IP), `magic-link` (3/min/IP+email), `credentials` (`CREDENTIAL_VERIFY_PER_MINUTE` = 10/min/user).

**Caching:** `GitHubService::context` caches for 10 minutes keyed by `sha1(url)`. `CacheRunProgressedVersion` listener caches run version for SSE change detection.

**Production hardening:** `AppServiceProvider` throws if production uses `sqlite`, `pgsql` without TLS, or `sync` queue.

**SSE compatibility:** `X-Accel-Buffering: no` header for proxy compatibility.
