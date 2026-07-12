# Codebase Structure

**Analysis Date:** 2026-07-13

## Directory Layout

```
ai-flow-pr-25/                         # Monorepo root
├── backend/                           # Laravel 13 app = deployable application root
│   ├── app/                           # PHP application code (PSR-12, Laravel conventions)
│   │   ├── Console/                   # (reserved) console commands
│   │   ├── Contracts/                 # Interfaces: AIProvider, Launcher, RunExecutor
│   │   ├── Data/                      # Readonly DTOs (GitHubReference)
│   │   ├── Events/                    # RunProgressed event
│   │   ├── Http/                      # Controllers, Requests, Resources
│   │   │   ├── Controllers/           # RunController
│   │   │   ├── Requests/              # StoreRunRequest (form validation)
│   │   │   └── Resources/             # RunResource (JSON shape)
│   │   ├── Jobs/                      # ExecuteLauncherJob (queue worker unit)
│   │   ├── Launchers/                 # BaseLauncher + 4 workflow launchers
│   │   ├── Models/                    # Run, Launcher, User (Eloquent)
│   │   ├── Providers/                 # AppServiceProvider (bindings, rate limits)
│   │   ├── Services/                  # RunExecutor, GitHub*, OpenAIProvider, etc.
│   │   └── Support/                   # AiProviders factory
│   ├── bootstrap/                     # App bootstrap (Laravel framework)
│   ├── config/                        # Laravel config (app, database, queue, services...)
│   ├── database/                      # Migrations, factories, seeders
│   │   ├── migrations/                # Schema (launchers, runs, users, jobs, cache)
│   │   ├── factories/                 # Model factories
│   │   └── seeders/                   # DatabaseSeeder (seeds launchers)
│   ├── public/                        # Web root (Vite build output -> build/)
│   ├── resources/                     # Frontend source + Blade shell
│   │   ├── css/app.css                # Plain BEM-like CSS
│   │   ├── ts/                        # React/TypeScript SPA source
│   │   │   ├── app.tsx                # Entry point
│   │   │   ├── components/            # UI components
│   │   │   ├── data/                  # Static launcher metadata
│   │   │   ├── hooks/                 # useRunSubscription, useRunFromPath
│   │   │   ├── lib/                   # http.ts, scroll.ts
│   │   │   ├── services/              # run.ts (API client + decoders)
│   │   │   └── types/                 # api.ts (Typed contracts)
│   │   └── views/app.blade.php        # Blade shell mounting the SPA
│   ├── routes/                        # api.php, web.php, console.php
│   ├── storage/                       # Logs, framework cache, compiled views
│   ├── tests/                         # PHPUnit feature/unit tests
│   ├── artisan                        # Laravel CLI entry
│   ├── composer.json                  # PHP deps (Laravel 13, PHP 8.4+)
│   ├── package.json                   # Node deps (React, Vite, oxlint, oxfmt)
│   ├── phpunit.xml                    # PHP test config
│   ├── tsconfig.json                  # TS strict config
│   └── vite.config.ts                 # Vite + laravel-vite-plugin + react
├── doc/                               # Architecture Decision Records (doc/adr)
├── scripts/                           # Repo-level helper scripts
├── .github/                           # CI workflows (ci.yml)
├── AGENTS.md                          # Project instructions / conventions
├── README.md                          # Product/marketing docs
├── justfile                           # Task runner shortcuts
├── konsistent.json                    # TS structural convention config
├── renovate.json                      # Dependency bot config
├── .oxlintrc.json / .oxfmtrc.json     # Frontend lint/format config
└── .pre-commit-config.yaml            # prek pre-commit hooks
```

## Directory Purposes

**backend/app/Http/:**
- Purpose: HTTP-facing code only.
- Contains: `Controllers/RunController.php`, `Requests/StoreRunRequest.php`, `Resources/RunResource.php`.
- Key files: `backend/app/Http/Controllers/RunController.php` (store/show/stream), `backend/app/Http/Requests/StoreRunRequest.php` (validation + `flow_id`/`input.url` aliases).

**backend/app/Services/:**
- Purpose: domain logic and I/O.
- Contains: `RunExecutor.php`, `GitHubService.php`, `GitHubContextFetcher.php`, `GitHubContextAssembler.php`, `ContextEncoder.php`, `JsonSchemaValidator.php`, `OpenAIProvider.php`, `RunStreamer.php`.
- Key files: `backend/app/Services/RunExecutor.php` (pipeline orchestrator), `backend/app/Services/OpenAIProvider.php` (AI call), `backend/app/Services/RunStreamer.php` (SSE).

**backend/app/Launchers/:**
- Purpose: one class per workflow; metadata seeded into `launchers`.
- Contains: `BaseLauncher.php` + `ReviewPullRequestLauncher.php`, `PlanIssueLauncher.php`, `ExplainRepositoryLauncher.php`, `LaravelDoctorLauncher.php`.
- Key files: `backend/app/Launchers/BaseLauncher.php` (shared `outputSchema()`/`make()`).

**backend/app/Jobs/:**
- Purpose: queue unit executed by `queue:work`.
- Contains: `backend/app/Jobs/ExecuteLauncherJob.php` (builds provider, calls `RunExecutor`, handles failures).

**backend/app/Contracts/:**
- Purpose: swappable boundaries.
- Contains: `AIProviderInterface.php`, `LauncherInterface.php`, `RunExecutorInterface.php`.

**backend/app/Models/ & backend/app/Data/:**
- Purpose: persistence + value objects.
- Key files: `backend/app/Models/Run.php` (UUID, casts), `backend/app/Models/Launcher.php`, `backend/app/Data/GitHubReference.php` (readonly DTO).

**backend/routes/:**
- Purpose: route definitions.
- Key files: `backend/routes/api.php` (the API surface + aliases + throttles), `backend/routes/web.php` (SPA catch-all), `backend/routes/console.php` (artisan stubs).

**backend/resources/ts/:**
- Purpose: React/TypeScript SPA source.
- Key files: `backend/resources/ts/app.tsx` (entry), `backend/resources/ts/components/App.tsx` (root component/reducer), `backend/resources/ts/services/run.ts` (API client + decoders), `backend/resources/ts/hooks/useRunSubscription.ts` (SSE + polling), `backend/resources/ts/types/api.ts` (typed contracts).

**backend/database/:**
- Purpose: persistence schema + seed data.
- Key files: `backend/database/migrations/2026_01_01_000000_create_launchers_and_runs.php` (core schema), `backend/database/seeders/DatabaseSeeder.php` (seeds 4 launchers).

## Key File Locations

**Entry Points:**
- `backend/routes/api.php`: JSON API surface (runs, launchers, stream, health).
- `backend/routes/web.php`: SPA catch-all → `backend/resources/views/app.blade.php`.
- `backend/resources/ts/app.tsx`: React entry mounted via `backend/resources/views/app.blade.php`.
- `backend/artisan`: Laravel CLI (migrate, seed, queue:work, test).

**Configuration:**
- `backend/config/services.php`: GitHub token + OpenAI/OpenRouter keys, base URL, model, timeout.
- `backend/config/queue.php`: queue connection (must be non-`sync` in prod).
- `backend/config/database.php`: sqlite (local/CI) vs pgsql/mysql (prod).
- `backend/vite.config.ts`: Vite input + React plugin + allowed hosts.
- `backend/.env.example`: required env (`OPENAI_API_KEY`, optional `GITHUB_TOKEN`, `AI_MODEL`, `AI_BASE_URL`, `QUEUE_CONNECTION`).

**Core Logic:**
- `backend/app/Http/Controllers/RunController.php`: request handling + SSE.
- `backend/app/Jobs/ExecuteLauncherJob.php`: queue entry.
- `backend/app/Services/RunExecutor.php`: end-to-end run pipeline.
- `backend/app/Services/GitHubService.php`: GitHub URL parsing + cached context.
- `backend/app/Services/OpenAIProvider.php`: AI generation.
- `backend/app/Launchers/BaseLauncher.php`: shared output schema.

**Testing:**
- `backend/tests/`: PHPUnit feature/unit tests (uses `RefreshDatabase` + seed, `Queue::fake()`).
- `backend/phpunit.xml`: test config.

## Naming Conventions

**Files (PHP):**
- PSR-12 / Laravel PascalCase class names matching filenames: `RunController.php`, `StoreRunRequest.php`, `RunResource.php`, `ExecuteLauncherJob.php`, `OpenAIProvider.php`, `GitHubContextFetcher.php`.
- Interfaces in `app/Contracts/` are `*Interface` (`AIProviderInterface`, `LauncherInterface`, `RunExecutorInterface`).
- Launchers are `{Workflow}Launcher.php`; all extend `BaseLauncher` and implement `metadata(): array`.
- Services are concrete nouns (`RunExecutor`, `JsonSchemaValidator`, `RunStreamer`).

**Files (TypeScript/React):**
- `components/*.tsx` export a PascalCase component matching the filename (`App.tsx` → `App`, `Home.tsx` → `Home`) enforced by `konsistent` (see `konsistent.json`).
- `hooks/*.ts` export `use*` functions (`useRunSubscription`, `useRunFromPath`).
- `services/run.ts` holds API client + runtime decoders; `types/api.ts` holds shared interfaces; `lib/*.ts` holds utilities (`http`, `scroll`).
- Strict mode enabled (`tsconfig.json`); prefer `unknown` + narrowing over `any`.
- Frontend lint/format via `oxlint` + `oxfmt` (`.oxlintrc.json`, `.oxfmtrc.json`), NOT ESLint/Prettier.

## Where to Add New Code

**New Launcher (workflow):**
- Primary code: `backend/app/Launchers/{Slug}Launcher.php` extending `BaseLauncher` implementing `metadata()`.
- Add to seed list: `backend/database/seeders/DatabaseSeeder.php` (array in `run()`).
- Tests: `backend/tests/` feature test covering store + dispatch + result shape.
- (Optional) frontend metadata: `backend/resources/ts/data/launcherMeta.ts`.

**New AI Provider:**
- Primary code: implement `backend/app/Contracts/AIProviderInterface.php` (e.g. `backend/app/Services/OpenAIProvider.php`); register in `backend/app/Support/AiProviders.php` (`ids()` + `createProvider()` match) and add id to `StoreRunRequest` `Rule::in(...)`.

**New API Endpoint / Validation:**
- Routes: `backend/routes/api.php`.
- Validation: new `backend/app/Http/Requests/*.php` form request.
- JSON shape: new `backend/app/Http/Resources/*.php` or extend `RunResource`.

**New Frontend Feature / Component:**
- Implementation: `backend/resources/ts/components/*.tsx` (PascalCase filename) and/or `backend/resources/ts/hooks/*.ts` (`use*`).
- API call: extend `backend/resources/ts/services/run.ts`; update `backend/resources/ts/types/api.ts` if contracts change.

**Utilities:**
- Shared PHP helpers: add to a `backend/app/Services/` class or `backend/app/Support/`.
- Shared TS helpers: `backend/resources/ts/lib/`.

## Special Directories

**backend/public/build/:**
- Purpose: compiled Vite assets (JS/CSS).
- Generated: Yes (by `npm run build` → `tsc --noEmit && vite build`).
- Committed: No (build artifact; served by Laravel).

**backend/storage/:**
- Purpose: logs, framework cache, compiled views, session.
- Generated: Yes (runtime).
- Committed: No (see `backend/.gitignore`).

**backend/database/database.sqlite:**
- Purpose: local/CI SQLite database file.
- Generated: Yes (created via `touch`; migrations populate it).
- Committed: No (local only; production uses managed Postgres/MySQL).

**doc/adr/:**
- Purpose: Architecture Decision Records.
- Generated: No (authored). See `doc/adr/README.md` for index.

**.planning/:**
- Purpose: planning/analysis artifacts (this document lives in `.planning/codebase/`).
- Generated: Yes (analysis output).
- Committed: per-repo policy.

---

*Structure analysis: 2026-07-13*
