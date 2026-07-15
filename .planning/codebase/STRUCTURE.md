# Codebase Structure

**Analysis Date:** 2026-07-15

## Directory Layout

```
2026-07-13-ai-flow-pr-25/          # Git repo root (docs, CI, tooling)
├── AGENTS.md                      # Agent/human dev guide
├── DESIGN.md                      # Product/design notes
├── doc/adr/                       # Architecture decision records
├── .github/workflows/             # CI (PHP 8.4 + Node 24)
├── justfile                       # Task shortcuts (e.g. prek)
├── konsistent.json                # TS structural conventions (repo root)
├── .oxlintrc.json / .oxfmtrc.json # Frontend lint/format
└── backend/                       # Deployable Laravel app (Dokku/Cloud root)
    ├── app/                       # Application code (PHP)
    ├── bootstrap/app.php          # Routing, middleware, exceptions
    ├── config/                    # Laravel + services (AI models, etc.)
    ├── database/migrations/       # Schema
    ├── database/seeders/          # Launcher seed data
    ├── public/                    # Web root, Vite build output
    ├── resources/
    │   ├── views/app.blade.php    # SPA shell
    │   ├── css/app.css
    │   └── ts/                    # React 19 + TypeScript UI
    ├── routes/
    │   ├── api.php                # JSON API (/api prefix)
    │   ├── web.php                # SPA catch-all + auth include
    │   └── auth.php               # /api/auth/*
    ├── tests/                     # PHPUnit Feature + Unit (+ E2E dir)
    ├── composer.json / package.json
    ├── vite.config.ts
    ├── DOKKU_DEPLOY.md / CLOUD_DEPLOY.md
    └── artisan
```

## Directory Purposes

**`backend/app/Http/`:**
- Purpose: HTTP adapters (controllers, requests, API resources).
- Contains: `RunController`, `RunHistoryController`, `ProviderCredentialController`, `Auth/*`, `RunResource`.
- Key files: `backend/app/Http/Controllers/RunController.php`, `backend/app/Http/Resources/RunResource.php`.

**`backend/app/Launchers/`:**
- Purpose: One PHP class per AI workflow; defines slug, prompts, schemas for seeding.
- Contains: `BaseLauncher`, four concrete launchers (`review-pr`, `plan-issue`, `explain-repository`, `laravel-doctor`).
- Key files: `backend/app/Launchers/BaseLauncher.php`, `backend/database/seeders/DatabaseSeeder.php`.

**`backend/app/Services/`:**
- Purpose: GitHub integration, AI providers, run execution, SSE streaming helpers.
- Contains: `RunExecutor.php`, `RunStreamer.php`, `GitHubService.php`, `*Provider.php`, `JsonSchemaValidator.php`.
- Key files: `backend/app/Services/RunExecutor.php`, `backend/app/Services/RunStreamer.php`.

**`backend/app/Jobs/`:**
- Purpose: Async queue work.
- Contains: `ExecuteLauncherJob.php` only.

**`backend/resources/ts/`:**
- Purpose: Frontend application source.
- Contains: `components/` (UI), `hooks/` (`useRunSubscription`, `useRunFromPath`), `services/` (API clients), `lib/` (`http.ts`, navigation), `types/api.ts`.
- Key files: `backend/resources/ts/app.tsx`, `backend/resources/ts/components/App.tsx`.

**`backend/tests/`:**
- Purpose: Automated tests; Feature tests use DB seed + `Queue::fake()` for run dispatch.
- Contains: `Feature/RunApiTest.php`, `ExecuteLauncherJobTest.php`, `Unit/RunStreamerTest.php`.

## Key File Locations

**Entry Points:**
- `backend/resources/ts/app.tsx`: Vite/React bootstrap, Sentry, mounts `App`.
- `backend/routes/web.php`: Serves SPA for all non-reserved paths.
- `backend/bootstrap/app.php`: Registers `api.php`, `web.php`, `/up` health.
- `backend/artisan`: CLI (migrate, `queue:work`, test).

**Configuration:**
- `backend/.env.example`: App keys, `QUEUE_CONNECTION`, AI/GitHub env vars, `VITE_*`.
- `backend/config/services.php`: Model defaults and provider-related config.
- `backend/config/database.php`, `backend/config/queue.php`: Runtime persistence and workers.

**Core Logic:**
- `backend/routes/api.php`: `/runs`, `/launchers`, `/user/*`, aliases `/flows`, `/executions`.
- `backend/app/Jobs/ExecuteLauncherJob.php`: Queue entry for execution.
- `backend/app/Services/RunExecutor.php`: GitHub + AI + validation pipeline.
- `backend/app/Policies/RunPolicy.php`: Public vs owned run access.

**Testing:**
- `backend/tests/Feature/RunApiTest.php`: POST 202, queue push, SSE behavior.
- `backend/tests/Feature/ExecuteLauncherJobTest.php`: Executor integration with mocks.
- `backend/resources/ts/components/__tests__/`: Frontend component tests.

## Naming Conventions

**Files:**
- PHP classes: PascalCase matching class name (`RunController.php`, `ExecuteLauncherJob.php`).
- Launchers: `*Launcher.php` under `app/Launchers/`.
- React components: PascalCase filename exporting same-named component (`App.tsx` → `App`).
- Hooks: `use*.ts` under `resources/ts/hooks/`.
- Services (TS): camelCase module names (`run.ts`, `auth.ts`).

**Directories:**
- Laravel standard: `Http/Controllers`, `Http/Requests`, `Http/Resources`.
- Frontend by concern: `components/`, `hooks/`, `services/`, `lib/`, `types/`.

**API / DB:**
- Launcher slugs: kebab-case (`review-pr`, `plan-issue`).
- Run statuses: `queued`, `running`, `completed`, `failed`.

## Where to Add New Code

**New launcher workflow:**
- Primary code: new class in `backend/app/Launchers/` extending `BaseLauncher` with `metadata()`.
- Seed: register class in `backend/database/seeders/DatabaseSeeder.php`.
- Tests: `backend/tests/Feature/` (dispatch job, schema/URL type as needed).

**New API endpoint:**
- Route: `backend/routes/api.php`.
- Handler: `backend/app/Http/Controllers/` + optional `Requests/` + `Resources/`.

**New UI screen or flow:**
- Implementation: `backend/resources/ts/components/`; wire state in `App.tsx` / `AppViews.tsx`.
- API access: extend `backend/resources/ts/services/` and `types/api.ts`.

**New AI provider:**
- Implementation: `backend/app/Services/<Name>Provider.php` implementing `AIProviderInterface`.
- Registration: `backend/app/Support/AiProviderRegistry.php`.
- Tests: unit/feature mocks in `backend/tests/`.

**Utilities:**
- Shared PHP helpers: `backend/app/Support/` or dedicated service under `Services/`.
- Shared TS helpers: `backend/resources/ts/lib/`.

## Special Directories

**`backend/vendor/`, `backend/node_modules/`:**
- Purpose: Composer/npm dependencies.
- Generated: Yes (install).
- Committed: No.

**`backend/public/build/`:**
- Purpose: Vite production assets.
- Generated: Yes (`npm run build`).
- Committed: Typically no (built in deploy).

**`backend/database/database.sqlite`:**
- Purpose: Local/CI database file.
- Generated: Often created locally.
- Committed: No (per `.gitignore`).

**`.planning/codebase/`:**
- Purpose: Codebase map artifacts for planning agents.
- Generated: By analysis tasks.
- Committed: Project-dependent.

---

*Structure analysis: 2026-07-15*
