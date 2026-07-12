# Architecture

**Analysis Date:** 2026-07-12

## Pattern Overview

**Overall:** Two-application monorepo with a React single-page client and a layered, queue-backed Laravel API.

**Key Characteristics:**

- The root Vite SPA and deployable Laravel application in `backend/` are independently built and hosted; the frontend reaches the API through `VITE_API_BASE_URL`.
- HTTP requests create durable work records and return `202`; GitHub and AI network I/O runs asynchronously in a queue worker.
- Launchers are declarative workflows: PHP classes provide seed metadata, while active database rows supply prompts, input types, and output schemas at runtime.
- Runs are UUID-addressed state machines (`queued`, `running`, `completed`, `failed`) exposed through REST and database-polled server-sent events.
- Output is a strict structured report rather than a chat transcript. The same launcher schema constrains the provider and validates its decoded response.

## Layers

**Frontend Presentation:**

- Purpose: Render launcher selection, execution progress, structured reports, failures, and shareable run URLs.
- Location: `src/main.jsx`, `src/components/`, `src/styles.css`
- Contains: Functional React views, local UI state, an error boundary, and plain CSS.
- Depends on: Frontend workflow metadata in `src/data/workflows.js` and transport helpers in `src/lib/api.js`.
- Used by: Browser entry point mounted from `src/main.jsx` into `index.html`.

**Frontend Data and Transport:**

- Purpose: Map UI workflow IDs to API slugs, validate/display GitHub URLs, create/fetch runs, and subscribe to progress.
- Location: `src/data/workflows.js`, `src/lib/api.js`, `src/lib/scroll.js`
- Contains: Declarative catalog and demo fixtures, Fetch API wrappers, EventSource handling, and small browser helpers.
- Depends on: Browser APIs and Vite environment variables.
- Used by: `App` and report/running views in `src/main.jsx`.

**HTTP/API:**

- Purpose: Define public endpoints, validate run creation, serialize state, and stream snapshots.
- Location: `backend/routes/api.php`, `backend/app/Http/Controllers/`, `backend/app/Http/Requests/`, `backend/app/Http/Resources/`
- Contains: Thin routes, `RunController`, `StoreRunRequest`, and `RunResource`.
- Depends on: Eloquent models and `ExecuteLauncherJob`; the SSE action polls persisted run state.
- Used by: The root SPA and curl/API consumers.

**Application/Orchestration:**

- Purpose: Coordinate asynchronous execution and lifecycle transitions without placing slow work in the web process.
- Location: `backend/app/Jobs/ExecuteLauncherJob.php`, `backend/app/Services/RunExecutor.php`, `backend/app/Events/RunProgressed.php`
- Contains: Queue boundary, execution pipeline, progress persistence, context bounding, completion/failure handling, and domain progress events.
- Depends on: `RunExecutorInterface`, `GitHubService`, `AIProviderInterface`, `JsonSchemaValidator`, and Eloquent models.
- Used by: Queue workers after `RunController::store` dispatches a job.

**Domain/Workflow Catalog:**

- Purpose: Define supported workflows and the common report contract.
- Location: `backend/app/Launchers/`, `backend/app/Contracts/LauncherInterface.php`, `backend/database/seeders/DatabaseSeeder.php`
- Contains: `BaseLauncher` shared JSON schema and one metadata class per workflow.
- Depends on: No infrastructure at definition time; the seeder persists metadata into `launchers`.
- Used by: Database seeding and runtime `Launcher` records.

**Integration Services:**

- Purpose: Convert GitHub URLs into bounded context, invoke an OpenAI-compatible provider, and validate structured output.
- Location: `backend/app/Services/GitHubService.php`, `backend/app/Services/OpenAIProvider.php`, `backend/app/Services/JsonSchemaValidator.php`
- Contains: GitHub REST access with caching and truncation, chat-completions JSON-schema requests, and recursive result validation.
- Depends on: Laravel HTTP/cache/config facilities and external GitHub/OpenAI-compatible APIs.
- Used by: `RunExecutor` through concrete services and `AIProviderInterface`.

**Persistence:**

- Purpose: Store launcher configuration and run lifecycle/result data.
- Location: `backend/app/Models/Launcher.php`, `backend/app/Models/Run.php`, `backend/database/migrations/`
- Contains: Eloquent relationships, UUID run IDs, JSON casts, statuses, timestamps, context, results, and errors.
- Depends on: The configured Laravel database.
- Used by: Routes, controllers, queue execution, SSE polling, and seeders.

**Composition and Infrastructure:**

- Purpose: Assemble interfaces, routing, rate limits, environment safeguards, and framework runtime.
- Location: `backend/bootstrap/app.php`, `backend/app/Providers/AppServiceProvider.php`, `backend/config/`
- Contains: API route registration, container bindings, named rate limiters, and production database/logging checks.
- Depends on: Laravel service container and environment configuration.
- Used by: Both HTTP and queue processes.

## Data Flow

**Create and Execute a Live Run:**

1. `App.launch()` in `src/main.jsx` checks the selected workflow and performs lightweight client URL validation through `src/lib/api.js`.
2. `createRun()` sends `POST /api/runs` with a launcher slug and `source_url`.
3. `StoreRunRequest` validates an active-looking GitHub URL and an existing launcher slug; `RunController::store` additionally selects an active `Launcher` row.
4. The controller creates a related UUID `Run` with `queued` status, dispatches `ExecuteLauncherJob`, and returns HTTP `202`.
5. A queue worker resolves `RunExecutorInterface` to `RunExecutor`, loads the run/launcher, marks it `running`, and appends progress messages.
6. `GitHubService::parse` identifies repository, issue, or pull-request input; `RunExecutor` rejects a type that does not match the launcher.
7. `GitHubService::context` loads cached or fresh bounded GitHub REST data; the run temporarily stores `source_context`.
8. `RunExecutor` combines the persisted prompt template and size-bounded JSON context, then calls `AIProviderInterface::generate` with the persisted output schema.
9. `OpenAIProvider` requests strict JSON-schema output; `JsonSchemaValidator` verifies types, required fields, enums, nested arrays/objects, and additional-property policy.
10. Success stores `result`, clears source context, timestamps completion, and sets `completed`; failure stores a safe error, clears context, and sets `failed`. Both paths dispatch `RunProgressed`.

**Progress and Terminal Delivery:**

1. After creation, the SPA pushes `/runs/{uuid}`, enters its running view, and opens `GET /api/runs/{uuid}/stream` via `EventSource`.
2. `RunController::stream` refreshes the model approximately once per second for up to 55 seconds and serializes it through `RunResource`.
3. Changed snapshots emit `progress`; terminal state emits `completed` or `failed` and closes the stream.
4. The SPA updates the timeline and switches to report/failure view. On disconnect, it falls back to `fetchRun()` polling every 1.5 seconds.
5. Opening a share URL directly triggers `fetchRun()` in `src/main.jsx`; frontend hosting must provide SPA fallback for `/runs/{uuid}`.

**Demo Execution:**

1. With `VITE_DEMO_MODE=true`, `App.launch()` skips the API and advances through `demoExecutionSteps` using timers.
2. The report renders static `demoFindings` from `src/data/workflows.js`; this path does not persist or analyze a repository.

**State Management:**

- Browser state is local React hook state in `src/main.jsx`; no router or global state library is used.
- Durable backend state is the `runs` row, making REST, SSE, queue workers, and share URLs converge on one source of truth.
- `RunProgressed` is dispatched but is not the SSE transport; current SSE delivery polls the database.

## Key Abstractions

**AI Provider Contract:**

- Purpose: Decouple orchestration from the configured structured-output vendor.
- Examples: `backend/app/Contracts/AIProviderInterface.php`, `backend/app/Services/OpenAIProvider.php`
- Pattern: Strategy/adapter selected by a Laravel container binding in `backend/app/Providers/AppServiceProvider.php`.

**Run Executor Contract:**

- Purpose: Keep the queue job focused on queue mechanics and make execution orchestration replaceable/testable.
- Examples: `backend/app/Contracts/RunExecutorInterface.php`, `backend/app/Services/RunExecutor.php`
- Pattern: Application service behind dependency inversion.

**Launcher Metadata:**

- Purpose: Package slug, display metadata, accepted input type, prompt, and result schema per workflow.
- Examples: `backend/app/Launchers/BaseLauncher.php`, `backend/app/Launchers/ReviewPullRequestLauncher.php`
- Pattern: Template base class plus one declarative class per workflow, materialized into database rows by `backend/database/seeders/DatabaseSeeder.php`.

**Run Resource:**

- Purpose: Provide one stable public snapshot shape for status reads and SSE events.
- Examples: `backend/app/Http/Resources/RunResource.php`
- Pattern: Laravel API Resource DTO/serializer.

**GitHub Reference:**

- Purpose: Represent parsed owner, repository, resource type, and optional issue/PR number.
- Examples: `backend/app/Data/GitHubReference.php`, `backend/app/Services/GitHubService.php`
- Pattern: Immutable-style data transfer object produced at the integration boundary.

**Run and Launcher Models:**

- Purpose: Represent durable execution state and workflow configuration with a one-to-many relationship.
- Examples: `backend/app/Models/Run.php`, `backend/app/Models/Launcher.php`
- Pattern: Eloquent active records; JSON casts retain schema flexibility.

## Entry Points

**Browser Application:**

- Location: `index.html`, `src/main.jsx`
- Triggers: Vite serves the SPA or a deployed frontend receives a browser request.
- Responsibilities: Mount React, restore shared runs from the URL, submit launches, subscribe to progress, and render finite home/running/report/failed views.

**Laravel HTTP Kernel and API Routes:**

- Location: `backend/public/index.php`, `backend/bootstrap/app.php`, `backend/routes/api.php`
- Triggers: HTTP requests to `/api/*` (plus framework health `/up`).
- Responsibilities: Bootstrap Laravel, apply route middleware, list active launchers, create/show runs, and expose SSE. `/api/flows` and `/api/executions` are compatibility aliases.

**Queue Worker:**

- Location: `backend/app/Jobs/ExecuteLauncherJob.php`
- Triggers: `php artisan queue:work` consumes a job dispatched by the create endpoint.
- Responsibilities: Reload the durable run and delegate execution to `RunExecutorInterface`; enforces two tries and a 120-second timeout.

**Database Seeder:**

- Location: `backend/database/seeders/DatabaseSeeder.php`
- Triggers: `php artisan migrate --seed` or `php artisan db:seed`.
- Responsibilities: Upsert the four launcher class definitions into the runtime catalog.

## Error Handling

**Strategy:** Reject malformed requests at the HTTP boundary, convert execution/integration failures into terminal run state, and keep the asynchronous failure visible through the same REST/SSE resource.

**Patterns:**

- `StoreRunRequest` provides framework validation for launcher existence and HTTPS GitHub URL shape; inactive launchers fail the controller's active-row lookup.
- `GitHubService::parse` throws `InvalidArgumentException` for malformed or unsupported paths, while Laravel HTTP responses use `throw()` for external API failures.
- `OpenAIProvider` throws `RuntimeException` for missing configuration, non-success responses, and invalid JSON; `JsonSchemaValidator` throws path-specific `RuntimeException` messages.
- `RunExecutor` catches all `Throwable`: domain/runtime messages become `runs.error`; unexpected exceptions become `Run failed unexpectedly.` Details are logged as class plus run ID, not returned to the client.
- The SPA surfaces create/load failures in UI state, renders terminal backend failures, ignores malformed SSE payloads, and switches from SSE to polling after disconnect.
- `ErrorBoundary` in `src/components/ErrorBoundary.jsx` catches uncaught React rendering errors and offers a reload.

## Cross-Cutting Concerns

**Logging:** Execution failures use Laravel `Log::error` in `backend/app/Services/RunExecutor.php`. `AppServiceProvider` warns if production logging is configured at debug level.

**Validation:** Validation occurs at several boundaries: frontend convenience checks, Laravel form-request rules, strict GitHub parsing/input-type matching, provider-side JSON schema, and post-response `JsonSchemaValidator` checks.

**Authentication:** MVP API endpoints are intentionally public and unauthenticated. Only public GitHub URLs are supported; optional `GITHUB_TOKEN` authenticates server-to-GitHub requests but not users.

**Rate Limiting:** Named limiters in `backend/app/Providers/AppServiceProvider.php` cap run creation at five per IP/hour and SSE connections at thirty per IP/minute.

**Caching:** `GitHubService` caches context by URL SHA for ten minutes through Laravel Cache. Production should use a durable/shared cache.

**Security and Privacy:** Only HTTPS `github.com` hosts are accepted. Context is bounded before prompting and `source_context` is cleared on either completion or failure. Provider keys remain server-side. Production boot rejects SQLite for HTTP workloads.

**Configuration:** Frontend API/public origins and demo behavior use `VITE_*` values. Backend integrations, database, queue, cache, CORS, and provider selection use Laravel config/environment files under `backend/config/` and `backend/.env.example`.

**Deployment:** Laravel Cloud deploys `backend/` as its application root with a non-sync queue worker. The root Vite build is hosted separately with cross-origin configuration and SPA fallback; SSE proxies must disable buffering.

**Architecture Decisions:** Historical and current rationale is indexed in `doc/adr/README.md`; frontend decisions are ADRs 0001–0006 and backend decisions are ADRs 0007–0014.

---

_Architecture analysis: 2026-07-12_
