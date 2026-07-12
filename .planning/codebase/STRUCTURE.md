# Codebase Structure

**Analysis Date:** 2026-07-12

## Directory Layout

```text
ai-flow/
├── .amp/portals/              # Amp preview metadata
├── .planning/codebase/        # Generated codebase maps
├── backend/                   # Independently deployable Laravel 13 API root
│   ├── app/
│   │   ├── Contracts/         # Swappable application/integration interfaces
│   │   ├── Data/              # Small typed data carriers
│   │   ├── Events/            # Run lifecycle domain events
│   │   ├── Http/              # Controllers, form requests, API resources
│   │   ├── Jobs/              # Queued workflow entry points
│   │   ├── Launchers/         # Workflow metadata and shared report schema
│   │   ├── Models/            # Eloquent persistence models
│   │   ├── Providers/         # Container bindings, rate limits, boot guards
│   │   └── Services/          # GitHub, AI, validation, execution orchestration
│   ├── bootstrap/             # Laravel application bootstrap and cache
│   ├── config/                # Laravel and integration configuration
│   ├── database/              # Migrations, factories, seeders, local SQLite
│   ├── public/                # Laravel HTTP document root
│   ├── resources/             # Default Laravel web assets/views
│   ├── routes/                # API, web, and console route declarations
│   ├── storage/               # Runtime cache, framework files, logs
│   ├── tests/                 # PHPUnit feature and unit tests
│   ├── artisan                # Laravel CLI entry point
│   ├── composer.json          # Backend PHP dependencies/scripts
│   └── README.md              # API setup, contract, and Cloud deployment
├── doc/adr/                   # Numbered architecture decision records
├── public/                    # Static assets for the root Vite app
├── src/                       # Root React launcher SPA
│   ├── components/            # Reusable React components
│   ├── data/                  # Workflow catalog and demo fixtures
│   ├── lib/                   # API and browser utility modules
│   ├── main.jsx               # SPA entry point and primary views/state
│   └── styles.css             # Plain global/BEM-like styles
├── index.html                 # Vite HTML shell
├── package.json               # Root frontend dependencies/scripts
├── vite.config.js             # Root Vite development/build configuration
├── README.md                  # Product and monorepo overview
└── AGENTS.md                  # Repository-specific agent guidance
```

## Directory Purposes

**`src/`:**
- Purpose: Root Vite + React launcher and report experience.
- Contains: The main app, extracted error boundary, declarative workflow/demo data, transport helpers, scroll helper, and styles.
- Key files: `src/main.jsx`, `src/lib/api.js`, `src/data/workflows.js`, `src/styles.css`.

**`src/components/`:**
- Purpose: Reusable presentation components that warrant extraction from the mostly single-file MVP UI.
- Contains: Currently `src/components/ErrorBoundary.jsx`.
- Key files: `src/components/ErrorBoundary.jsx`.

**`src/data/`:**
- Purpose: Frontend declarative metadata and demo-only report/progress fixtures.
- Contains: Workflow IDs/slugs/presentation metadata, recent demo runs, execution steps, and sample findings.
- Key files: `src/data/workflows.js`.

**`src/lib/`:**
- Purpose: Browser-facing infrastructure kept out of presentation code.
- Contains: API Fetch/EventSource wrappers, URL helpers, and scrolling behavior.
- Key files: `src/lib/api.js`, `src/lib/scroll.js`.

**`backend/`:**
- Purpose: Complete Laravel application and Laravel Cloud deployment root, separate from the root Vite build.
- Contains: Application code, dependencies, config, persistence, routes, tests, and runtime directories.
- Key files: `backend/artisan`, `backend/composer.json`, `backend/bootstrap/app.php`, `backend/README.md`.

**`backend/app/Contracts/`:**
- Purpose: Dependency-inversion seams for AI generation, launcher metadata, and run execution.
- Contains: PHP interfaces named after their role with an `Interface` suffix.
- Key files: `backend/app/Contracts/AIProviderInterface.php`, `backend/app/Contracts/RunExecutorInterface.php`, `backend/app/Contracts/LauncherInterface.php`.

**`backend/app/Data/`:**
- Purpose: Structured values crossing service boundaries.
- Contains: `GitHubReference`, which carries parsed GitHub owner/repo/type/number data.
- Key files: `backend/app/Data/GitHubReference.php`.

**`backend/app/Http/`:**
- Purpose: HTTP boundary organized by Laravel role.
- Contains: `Controllers/` for endpoint behavior, `Requests/` for validation/authorization, and `Resources/` for public JSON shape.
- Key files: `backend/app/Http/Controllers/RunController.php`, `backend/app/Http/Requests/StoreRunRequest.php`, `backend/app/Http/Resources/RunResource.php`.

**`backend/app/Jobs/`:**
- Purpose: Queue entry points for slow or external-I/O work.
- Contains: `ExecuteLauncherJob`, which reloads a run and delegates to the executor contract.
- Key files: `backend/app/Jobs/ExecuteLauncherJob.php`.

**`backend/app/Launchers/`:**
- Purpose: Backend source definitions for each supported workflow and their common structured-report schema.
- Contains: Abstract `BaseLauncher` plus one `*Launcher` class per workflow.
- Key files: `backend/app/Launchers/BaseLauncher.php`, `backend/app/Launchers/ReviewPullRequestLauncher.php`, `backend/app/Launchers/PlanIssueLauncher.php`, `backend/app/Launchers/ExplainRepositoryLauncher.php`, `backend/app/Launchers/LaravelDoctorLauncher.php`.

**`backend/app/Services/`:**
- Purpose: Core orchestration and infrastructure adapters.
- Contains: `RunExecutor`, `GitHubService`, `OpenAIProvider`, and `JsonSchemaValidator`.
- Key files: `backend/app/Services/RunExecutor.php`, `backend/app/Services/GitHubService.php`, `backend/app/Services/OpenAIProvider.php`.

**`backend/app/Models/`:**
- Purpose: Eloquent records and relationships.
- Contains: Runtime `Launcher` and UUID `Run` models plus Laravel's default `User` model (not used for MVP API auth).
- Key files: `backend/app/Models/Launcher.php`, `backend/app/Models/Run.php`.

**`backend/database/`:**
- Purpose: Database schema, seed defaults, test factories, and local development storage.
- Contains: Timestamped migrations, `DatabaseSeeder`, factories, and optional `database.sqlite`.
- Key files: `backend/database/seeders/DatabaseSeeder.php`, `backend/database/migrations/2026_01_01_000000_create_launchers_and_runs.php`.

**`backend/routes/`:**
- Purpose: Thin Laravel route declarations by transport.
- Contains: Public API routes and compatibility aliases in `api.php`, framework web routes in `web.php`, and command routes in `console.php`.
- Key files: `backend/routes/api.php`.

**`backend/tests/`:**
- Purpose: Backend behavior verification under PHPUnit/Laravel testing utilities.
- Contains: Endpoint/job feature tests and focused service unit tests under `Feature/` and `Unit/`.
- Key files: `backend/tests/Feature/RunApiTest.php`, `backend/tests/Feature/ExecuteLauncherJobTest.php`, `backend/tests/Unit/GitHubServiceTest.php`.

**`doc/adr/`:**
- Purpose: Preserve architectural rationale and consequences separately from setup documentation.
- Contains: Zero-padded numbered Markdown ADRs and an index.
- Key files: `doc/adr/README.md`, `doc/adr/0007-laravel-api-in-backend-subdirectory.md`, `doc/adr/0008-queue-backed-execute-launcher-job.md`.

## Key File Locations

**Entry Points:**
- `index.html`: Root SPA HTML shell and React mount element.
- `src/main.jsx`: Browser application composition, state, live/demo execution flows, and render mount.
- `backend/public/index.php`: Laravel HTTP front controller.
- `backend/bootstrap/app.php`: Framework bootstrap and API/web/console route registration.
- `backend/routes/api.php`: Public API endpoint map.
- `backend/app/Jobs/ExecuteLauncherJob.php`: Queue worker entry point for each run.
- `backend/artisan`: Backend CLI entry point for migrations, seeding, serving, queues, and tests.

**Configuration:**
- `vite.config.js`: Root SPA build/dev-server configuration.
- `package.json`: Root React/Vite dependency and script definitions.
- `backend/composer.json`: Laravel/PHP dependency and Composer script definitions.
- `backend/.env.example`: Backend environment contract for app, database, queue, cache, GitHub, AI, and CORS settings.
- `backend/config/services.php`: GitHub and OpenAI-compatible provider configuration.
- `backend/config/cors.php`: Cross-origin policy for separately hosted frontend/API.
- `backend/config/database.php`, `backend/config/queue.php`, `backend/config/cache.php`: Persistence and asynchronous runtime selection.
- `backend/app/Providers/AppServiceProvider.php`: Interface bindings, rate limiters, and production safeguards.

**Core Logic:**
- `src/lib/api.js`: Frontend API contract and SSE subscription.
- `src/data/workflows.js`: Frontend catalog-to-backend slug mapping and demo fixtures.
- `backend/app/Http/Controllers/RunController.php`: Run creation, retrieval, and streaming.
- `backend/app/Services/RunExecutor.php`: End-to-end backend execution pipeline and state transitions.
- `backend/app/Services/GitHubService.php`: GitHub URL parsing and cached REST context assembly.
- `backend/app/Services/OpenAIProvider.php`: Structured-output AI adapter.
- `backend/app/Services/JsonSchemaValidator.php`: Result contract enforcement.
- `backend/app/Launchers/BaseLauncher.php`: Shared output schema and launcher metadata factory.
- `backend/database/seeders/DatabaseSeeder.php`: Launcher source definitions to runtime catalog synchronization.

**Persistence:**
- `backend/app/Models/Launcher.php`: Active launcher catalog and run relationship.
- `backend/app/Models/Run.php`: UUID execution record and JSON/datetime casts.
- `backend/database/migrations/`: Schema history for launcher/run tables and indexes.

**Testing:**
- `backend/phpunit.xml`: PHPUnit environment and suite configuration.
- `backend/tests/Feature/`: HTTP and queue/application integration behavior.
- `backend/tests/Unit/`: Focused parser and schema validation behavior.
- The root frontend currently has no automated test suite or lint configuration; use `npm run build` as its configured static verification.

**Documentation:**
- `README.md`: Product vision, monorepo summary, and frontend setup.
- `backend/README.md`: Backend API, local setup, test, and Laravel Cloud operations.
- `AGENTS.md`: Repository architecture, commands, standards, and operational gotchas.
- `doc/adr/README.md`: Architecture decision index.

## Naming Conventions

**Files:**
- React components use PascalCase `.jsx`, for example `src/components/ErrorBoundary.jsx`; the main Vite entry remains conventional lowercase `src/main.jsx`.
- Frontend data/helper modules use lowercase descriptive `.js`, for example `src/data/workflows.js` and `src/lib/api.js`.
- Laravel classes use one PascalCase class per `.php` file and role suffixes: `RunController`, `StoreRunRequest`, `RunResource`, `ExecuteLauncherJob`, `GitHubService`, `OpenAIProvider`.
- Contracts use the `Interface` suffix and live in `backend/app/Contracts/`, for example `AIProviderInterface.php`.
- Launcher implementations use a descriptive workflow name plus `Launcher`, for example `ReviewPullRequestLauncher.php`.
- Tests mirror the subject/behavior and end in `Test.php`, grouped into `backend/tests/Feature/` or `backend/tests/Unit/`.
- Migrations use Laravel timestamp prefixes and snake_case action names under `backend/database/migrations/`.
- ADRs use four-digit sequence numbers plus kebab-case titles, for example `doc/adr/0013-sse-run-stream-via-database-polling.md`.

**Code Symbols and Data:**
- PHP classes/methods use PascalCase/camelCase under the `App\` namespace; database columns and serialized JSON fields use snake_case (`source_url`, `output_schema`, `completed_at`).
- Frontend functions and state use camelCase (`createRun`, `runSnapshot`); React components use PascalCase (`App`, `Running`, `Report`).
- Backend launcher slugs are kebab-case (`review-pr`, `plan-issue`); frontend presentation IDs are shorter lowercase keys (`review`, `plan`) mapped through each workflow's `slug`.
- CSS is centralized in `src/styles.css` and uses descriptive lowercase kebab-case/BEM-like class names such as `launcher-card`, `report-layout`, and `run-risk`.

**Directories:**
- Laravel directories use conventional PascalCase role names under `backend/app/` (`Http`, `Jobs`, `Services`).
- Frontend directories use lowercase role names (`src/components`, `src/data`, `src/lib`).
- Framework-generated/runtime directories retain Laravel conventions (`bootstrap/cache`, `storage/framework`).

## Where to Add New Code

**New Backend Launcher:**
- Definition: add `backend/app/Launchers/<WorkflowName>Launcher.php` extending `BaseLauncher`.
- Registration: add the class to `backend/database/seeders/DatabaseSeeder.php` so metadata is upserted.
- Frontend catalog: add API slug/presentation metadata to `src/data/workflows.js` if the root SPA should expose it.
- Tests: add feature coverage in `backend/tests/Feature/` for creation/type matching and focused launcher/schema coverage as needed.
- Keep the common report shape in `backend/app/Launchers/BaseLauncher.php`; only override the schema when the API/frontend contract is intentionally expanded.

**New API Endpoint:**
- Route: declare it in `backend/routes/api.php`.
- Validation: create a form request in `backend/app/Http/Requests/`.
- HTTP behavior: add a focused controller in `backend/app/Http/Controllers/`.
- Serialization: add an API resource in `backend/app/Http/Resources/` rather than exposing models ad hoc.
- Tests: add endpoint behavior to `backend/tests/Feature/`.

**New Slow Workflow Step:**
- Queue boundary: add or extend a job in `backend/app/Jobs/`; do not perform GitHub/AI calls in controllers.
- Orchestration: place lifecycle coordination in `backend/app/Services/RunExecutor.php` or a focused service called from it.
- External provider behavior: create an adapter in `backend/app/Services/`, introduce a contract in `backend/app/Contracts/` when swapability is useful, and bind it in `backend/app/Providers/AppServiceProvider.php`.

**New Persistence:**
- Schema: add a timestamped migration to `backend/database/migrations/`.
- Model: add/update Eloquent classes in `backend/app/Models/` with fillable fields, casts, and relationships.
- Seed defaults: add to `backend/database/seeders/`.
- Tests: use `backend/tests/Feature/` for persistence across application boundaries and `backend/tests/Unit/` for isolated logic.

**New Frontend UI:**
- Primary view/application wiring: keep it in `src/main.jsx` while following the MVP's main-file pattern.
- Reusable component: add PascalCase `.jsx` files under `src/components/` when shared or independently meaningful.
- Workflow/static metadata: add to `src/data/` rather than branching presentation logic repeatedly.
- API/browser utility: add to `src/lib/`; keep network calls out of presentation components where practical.
- Styles: add matching plain CSS selectors to `src/styles.css`; no Tailwind setup exists at the root.

**New Architecture Decision or Documentation:**
- Product/marketing changes: update `README.md`.
- Backend setup/API/Cloud changes: update `backend/README.md`.
- Durable architectural choice: add the next numbered file under `doc/adr/` and index it in `doc/adr/README.md`.

## Special Directories

**`.planning/codebase/`:**
- Purpose: Generated architectural/codebase reference documents for planning.
- Generated: Yes, by mapping work.
- Committed: Repository policy determines this; these files were created directly and no commit was made.

**`.amp/portals/`:**
- Purpose: Amp-hosted preview metadata for the launcher.
- Generated: Tool-managed configuration.
- Committed: Yes, according to ADR 0006.

**`backend/bootstrap/cache/`:**
- Purpose: Laravel-generated bootstrap/package/config caches.
- Generated: Yes.
- Committed: Directory placeholder may be committed; generated cache contents should not be treated as source.

**`backend/storage/`:**
- Purpose: Laravel runtime logs, cache, sessions, compiled views, and application files.
- Generated: Mostly runtime-generated.
- Committed: Directory structure/placeholders only; runtime contents are not source code.

**`backend/vendor/`:**
- Purpose: Composer-installed PHP dependencies.
- Generated: Yes, by `composer install`.
- Committed: No.

**`node_modules/` and `backend/node_modules/`:**
- Purpose: Root SPA and Laravel asset-tooling Node dependencies respectively.
- Generated: Yes, by package installation.
- Committed: No.

**`dist/`:**
- Purpose: Production output from the root Vite build.
- Generated: Yes, by `npm run build`.
- Committed: No.

**`backend/public/build/`:**
- Purpose: Compiled Laravel-side Vite assets if that default asset pipeline is built.
- Generated: Yes.
- Committed: No.

**`doc/adr/`:**
- Purpose: Human-authored architecture history and rationale, including superseded/extended decisions.
- Generated: No.
- Committed: Yes.

---

*Structure analysis: 2026-07-12*
