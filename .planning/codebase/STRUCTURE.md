# Codebase Structure

**Analysis Date:** 2026-07-13

## Directory Layout

```
2026-07-13-ai-flow-pr-25/
├── Agents.md                 # Project agent instructions (commands, architecture map)
├── README.md                 # Repo overview
├── LICENSE
├── justfile                  # Task runner (prek hooks, etc.)
├── konsistent.json           # Structural TS conventions (components/hooks export rules)
├── renovate.json             # Dependency update config
├── .planning/
│   └── codebase/             # Architecture/structure analysis docs (this folder)
├── .github/
│   └── workflows/            # CI (PHP 8.4 + Node 24)
├── doc/
│   └── adr/                  # Architecture Decision Records (0001–0014)
├── scripts/
│   └── hooks/                # Pre-commit helper scripts (composer, pint, npm)
└── backend/                  # Deploy root: Laravel 13 + React/Vite SPA
    ├── app/
    │   ├── Console/Commands/ # Artisan commands (reap stuck runs)
    │   ├── Contracts/        # AIProvider, Launcher, RunExecutor interfaces
    │   ├── Data/             # GitHubReference DTO
    │   ├── Events/           # RunProgressed
    │   ├── Http/
    │   │   ├── Controllers/  # API + MagicLink auth
    │   │   ├── Requests/     # Form requests
    │   │   └── Resources/    # JSON API resources
    │   ├── Jobs/             # ExecuteLauncherJob
    │   ├── Launchers/        # Workflow metadata classes
    │   ├── Listeners/        # CacheRunProgressedVersion
    │   ├── Mail/             # MagicLinkMail
    │   ├── Models/           # Eloquent models
    │   ├── Policies/         # Run, ProviderCredential
    │   ├── Providers/        # AppServiceProvider
    │   ├── Security/         # CredentialCipher
    │   └── Services/         # Domain orchestration + integrations
    ├── bootstrap/            # app.php routing/middleware; providers.php
    ├── config/               # Laravel + services (OpenAI/GitHub/etc.)
    ├── database/
    │   ├── factories/
    │   ├── migrations/
    │   └── seeders/          # Seeds launchers from PHP classes
    ├── docker/               # nginx, supervisor, release migrate (Dokku)
    ├── public/               # Web root (index.php, Vite build output)
    ├── resources/
    │   ├── css/app.css
    │   ├── ts/               # React 19 + TypeScript SPA
    │   └── views/            # app.blade.php SPA shell; mail templates
    ├── routes/               # api, web, auth, console
    ├── storage/              # logs, cache, sessions (runtime)
    ├── tests/
    │   ├── Feature/          # HTTP + job feature tests
    │   └── Unit/             # Service unit tests
    ├── vendor/               # Composer deps (not edited)
    ├── node_modules/         # npm deps (not edited)
    ├── artisan
    ├── composer.json
    ├── package.json
    ├── Dockerfile
    ├── Procfile
    ├── phpunit.xml
    ├── vite.config.ts
    ├── vitest.config.ts
    ├── tsconfig.json
    ├── README.md
    ├── DOKKU_DEPLOY.md
    └── CLOUD_DEPLOY.md
```

## Directory Purposes

**`backend/app/`:**
- Purpose: All application PHP code (domain + HTTP + jobs).
- Contains: Controllers, services, models, launchers, contracts, policies.
- Key files: `Jobs/ExecuteLauncherJob.php`, `Services/RunExecutor.php`, `Http/Controllers/RunController.php`, `Providers/AppServiceProvider.php`

**`backend/app/Launchers/`:**
- Purpose: One class per AI workflow; `metadata()` returns slug, name, inputType, prompt, shared outputSchema.
- Contains: `BaseLauncher`, `ReviewPullRequestLauncher`, `PlanIssueLauncher`, `ExplainRepositoryLauncher`, `LaravelDoctorLauncher`
- Key files: `BaseLauncher.php` (shared JSON schema)

**`backend/app/Services/`:**
- Purpose: Business logic outside controllers: GitHub, AI, encoding, validation, streaming.
- Contains: GitHub pipeline, AI providers, `RunExecutor`, `RunStreamer`, `JsonSchemaValidator`, `ContextEncoder`
- Key files: `RunExecutor.php`, `GitHubService.php`, `OpenAIProvider.php`, `RunStreamer.php`

**`backend/app/Http/`:**
- Purpose: Thin HTTP boundary.
- Contains: Controllers, Form Requests, API Resources
- Key files: `Controllers/RunController.php`, `Requests/StoreRunRequest.php`, `Resources/RunResource.php`

**`backend/routes/`:**
- Purpose: Route registration only (thin).
- Contains: `api.php`, `web.php`, `auth.php`, `console.php`
- Key files: `api.php` (runs, launchers, user routes + aliases)

**`backend/resources/ts/`:**
- Purpose: Frontend SPA source (Vite entry).
- Contains: `app.tsx`, `components/`, `hooks/`, `services/`, `lib/`, `types/`, `data/`
- Key files: `components/App.tsx`, `services/run.ts`, `hooks/useRunSubscription.ts`, `types/api.ts`

**`backend/database/`:**
- Purpose: Schema and seed data.
- Contains: Migrations for users, jobs/cache, launchers/runs, magic tokens, credentials, ownership
- Key files: `seeders/DatabaseSeeder.php`, `migrations/2026_01_01_000000_create_launchers_and_runs.php`

**`backend/tests/`:**
- Purpose: PHPUnit Feature + Unit tests.
- Contains: Run API, jobs, auth, credentials, GitHub/AI units
- Key files: `Feature/RunApiTest.php`, `Feature/ExecuteLauncherJobTest.php`

**`doc/adr/`:**
- Purpose: Recorded architecture decisions (prototype → Laravel API patterns).
- Contains: ADR 0001–0014 + README index
- Key files: `0008-queue-backed-execute-launcher-job.md`, `0013-sse-run-stream-via-database-polling.md`

**`scripts/hooks/`:**
- Purpose: Pre-commit tooling used by `just prek` / prek config.
- Contains: Shell wrappers for composer validate, pint, npm-in-backend

**`.planning/codebase/`:**
- Purpose: Generated/maintained analysis docs for agents and planning.
- Contains: `ARCHITECTURE.md`, `STRUCTURE.md`

## Key File Locations

**Entry Points:**
- `backend/public/index.php`: PHP front controller
- `backend/bootstrap/app.php`: Laravel app configure (web/api/commands/health)
- `backend/resources/ts/app.tsx`: React mount + ErrorBoundary
- `backend/resources/views/app.blade.php`: SPA HTML shell + `@vite('resources/ts/app.tsx')`
- `backend/routes/api.php`: JSON API routes
- `backend/routes/web.php`: SPA catch-all + requires `auth.php`
- `backend/app/Jobs/ExecuteLauncherJob.php`: Queue entry for workflow execution
- `backend/artisan`: CLI entry

**Configuration:**
- `backend/config/services.php`: OpenAI/OpenRouter, Anthropic, Gemini, GitHub token, provider allow-list
- `backend/config/queue.php`, `database.php`, `auth.php`, `cache.php`, `cors.php`, `logging.php`
- `backend/.env.example` (via project docs): `OPENAI_API_KEY`, `GITHUB_TOKEN`, `VITE_DEMO_MODE`, `AI_MODEL`, etc.
- `backend/vite.config.ts`, `backend/tsconfig.json`, `backend/package.json`, `backend/composer.json`
- Root `konsistent.json`, `.oxlintrc.json` / `.oxfmtrc.json` (lint/format for TS)

**Core Logic:**
- `backend/app/Services/RunExecutor.php`: GitHub → AI → validate → complete/fail
- `backend/app/Services/GitHubService.php`: Parse + cached context
- `backend/app/Services/GitHubContextFetcher.php` / `GitHubContextAssembler.php`: REST fetch + shape
- `backend/app/Services/OpenAIProvider.php` (+ Anthropic/Gemini): AI `generate` / verify
- `backend/app/Services/JsonSchemaValidator.php`: Result schema enforcement
- `backend/app/Services/RunStreamer.php`: SSE progress loop
- `backend/app/Launchers/*.php`: Workflow prompts and input types
- `backend/app/Models/Run.php` / `Launcher.php`: Persistence model

**Testing:**
- `backend/tests/Feature/`: HTTP/API/job integration tests (`RefreshDatabase`, fakes)
- `backend/tests/Unit/`: Isolated service tests
- `backend/resources/ts/components/__tests__/`: Vitest + Testing Library (e.g. `RunHistory.test.tsx`)
- `backend/phpunit.xml`, `backend/vitest.config.ts`

## Naming Conventions

**Files:**
- PHP classes: PascalCase matching class name — `RunController.php`, `ExecuteLauncherJob.php`
- Launchers: `{Purpose}Launcher.php` with kebab-case slugs in metadata (`review-pr`, `plan-issue`, `explain-repository`, `laravel-doctor`)
- Form requests: `Store*Request` / `Update*Request`
- API resources: `*Resource.php`
- React components: PascalCase filename = exported component — `Report.tsx` exports `Report` (enforced by konsistent)
- Hooks: `use*.ts` — `useRunSubscription.ts`, `useRunFromPath.ts`
- TS services/types: camelCase modules — `run.ts`, `api.ts`
- Migrations: `YYYY_MM_DD_HHMMSS_description.php`
- ADRs: `NNNN-kebab-title.md`

**Directories:**
- Laravel standard PSR-4 under `App\` → `backend/app/`
- Frontend feature folders by role: `components/`, `hooks/`, `services/`, `lib/`, `types/`, `data/`
- Tests mirror concern: `Feature/` vs `Unit/`

## Where to Add New Code

**New Feature (new AI workflow / launcher):**
- Primary code: `backend/app/Launchers/NewWorkflowLauncher.php` extending `BaseLauncher`, implementing `metadata()`
- Seed: register class in `backend/database/seeders/DatabaseSeeder.php`
- Tests: `backend/tests/Feature/` (API + job) and/or unit coverage for unique behavior
- Frontend catalog chrome (icons/copy): `backend/resources/ts/data/launcherMeta.ts` + any UI in `components/`

**New API endpoint:**
- Route: `backend/routes/api.php` (keep thin)
- Controller: `backend/app/Http/Controllers/`
- Validation: `backend/app/Http/Requests/`
- Response shape: `backend/app/Http/Resources/`
- Policy if resource-owned: `backend/app/Policies/`
- Test: `backend/tests/Feature/`

**New Component/Module (UI):**
- Implementation: `backend/resources/ts/components/PascalName.tsx` (export matching name)
- Hooks: `backend/resources/ts/hooks/useThing.ts`
- API client: `backend/resources/ts/services/`
- Shared types: `backend/resources/ts/types/`

**New AI provider:**
- Implementation: `backend/app/Services/{Provider}Provider.php` implementing `AIProviderInterface`
- Config: `backend/config/services.php`
- Bind/select: `AppServiceProvider` and/or job resolution; keep `providers` allow-list in sync with `StoreRunRequest`

**Utilities:**
- Shared PHP helpers/services: prefer `backend/app/Services/` or `Data/` DTOs over static utils
- Shared TS helpers: `backend/resources/ts/lib/` (`http.ts`, `navigate.ts`, `scroll.ts`)
- Encryption: `backend/app/Security/CredentialCipher.php`

**Architecture decisions:**
- New ADR under `doc/adr/` and index row in `doc/adr/README.md`

## Special Directories

**`backend/vendor/`:**
- Purpose: Composer dependencies
- Generated: Yes (`composer install`)
- Committed: No

**`backend/node_modules/`:**
- Purpose: npm dependencies for Vite/React tooling
- Generated: Yes (`npm install`)
- Committed: No

**`backend/public/build/`:**
- Purpose: Vite production assets (`manifest.json`, hashed JS/CSS)
- Generated: Yes (`npm run build`)
- Committed: Often present for deploy simplicity; treat as build output

**`backend/storage/`:**
- Purpose: Logs, framework cache/sessions/views, app files
- Generated: Runtime
- Committed: Skeleton only (not log content)

**`backend/database/database.sqlite`:**
- Purpose: Local/dev SQLite DB
- Generated: Local setup
- Committed: May exist for convenience; production must use Postgres/MySQL

**`backend/test-results/`:**
- Purpose: Playwright/e2e failure artifacts (screenshots, error context)
- Generated: Yes (test runs)
- Committed: Should not be treated as source

**`doc/adr/`:**
- Purpose: Durable decision log
- Generated: No (hand-written)
- Committed: Yes

**`.planning/codebase/`:**
- Purpose: Codebase maps for planning/agents
- Generated: Written by analysis tasks
- Committed: Per team preference

---

*Structure analysis: 2026-07-13*
