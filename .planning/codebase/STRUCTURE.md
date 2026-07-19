# Directory Structure

## Project Root

```
.
├── backend/                  # Laravel 13 app (deploy root)
│   ├── app/
│   │   ├── Console/          # Artisan commands (ReapStuckRuns)
│   │   ├── Contracts/        # Interfaces (AIProviderInterface, LauncherSource)
│   │   ├── Data/             # DTOs (ResolvedLauncher, GitHubReference, LaunchParameters)
│   │   ├── Events/           # RunProgressed
│   │   ├── Exceptions/       # UserFacingRunException
│   │   ├── Filament/         # Super-admin panel resources
│   │   ├── Http/
│   │   │   ├── Controllers/  # RunController, LauncherController, UserLauncherController, auth...
│   │   │   ├── Requests/     # StoreRunRequest, StoreUserLauncherRequest, UpdateUserLauncherRequest
│   │   │   └── Resources/    # RunResource, LauncherResource, UserLauncherResource, UserResource
│   │   ├── Jobs/             # ExecuteLauncherJob
│   │   ├── Launchers/        # One class per built-in workflow (extends BaseLauncher)
│   │   ├── Listeners/        # CacheRunProgressedVersion
│   │   ├── Mail/             # Magic-link emails
│   │   ├── Models/           # Run, User, Launcher, UserLauncher, ProviderCredential, ...
│   │   ├── Policies/         # RunPolicy, UserLauncherPolicy, ProviderCredentialPolicy
│   │   ├── Providers/        # AppServiceProvider (rate limiters, container bindings, production guards)
│   │   ├── Rules/            # PublicHttpUrl
│   │   ├── Services/         # RunExecutor, GitHubService, *Provider, LauncherMetaService, ...
│   │   └── Support/          # AiProviderRegistry
│   ├── config/               # Laravel config (services, auth, database, credentials, mail, ...)
│   ├── database/
│   │   ├── factories/        # RunFactory, UserLauncherFactory
│   │   ├── migrations/       # Schema migrations
│   │   └── seeders/          # DatabaseSeeder (seeds built-in launchers + super admin)
│   ├── public/               # index.php (web entry point)
│   ├── resources/
│   │   ├── ts/               # React TypeScript source
│   │   │   ├── app.tsx       # Entry point (Sentry init + ErrorBoundary)
│   │   │   ├── components/   # React components (App, Home, Dashboard, LaunchArea, Report, ...)
│   │   │   ├── hooks/        # useRunSubscription, useRunFromPath
│   │   │   ├── lib/          # http.ts (CSRF, fetch wrappers), decode.ts (type guards), appPaths.ts
│   │   │   ├── services/     # API clients (run.ts, auth.ts, userLaunchers.ts)
│   │   │   └── types/        # TypeScript type definitions (api.ts)
│   │   └── views/
│   │       └── app.blade.php # SPA shell (vite assets, CSRF meta, root div)
│   ├── routes/
│   │   ├── api.php           # REST API routes
│   │   ├── auth.php          # Auth routes (register, login, magic-link, logout)
│   │   ├── web.php           # SPA catch-all (excludes /api, /admin)
│   │   └── console.php       # Console routes (ReapStuckRuns schedule)
│   ├── tests/
│   │   ├── Feature/          # PHP feature tests
│   │   ├── Unit/             # PHP unit tests
│   │   └── E2E/              # Playwright E2E specs
│   ├── composer.json
│   ├── package.json
│   ├── Dockerfile
│   └── vite.config.ts
├── .planning/
│   └── codebase/             # Architecture docs (this directory)
├── doc/
│   └── adr/                  # Architecture Decision Records (24 ADRs)
├── scripts/
│   └── hooks/                # Pre-commit hook scripts
├── .github/workflows/        # CI (ci.yml, deploy-staging.yml)
├── justfile                  # Task runner (just ci, just test, etc.)
├── konsistent.json           # Structural TS conventions
├── .oxlintrc.json            # TypeScript linting rules
├── .oxfmtrc.json             # TypeScript formatting config
├── .pre-commit-config.yaml   # Pre-commit hooks (prek)
└── AGENTS.md                 # AI coding assistant instructions
```

## Key Files and Their Roles

### Entry Points
| File | Role |
|------|------|
| `backend/public/index.php` | Web entry point (Laravel front controller) |
| `backend/resources/ts/app.tsx` | React entry point (Sentry init + ErrorBoundary) |
| `backend/resources/views/app.blade.php` | SPA shell (vite assets, root `<div>`) |

### Core Execution Path
| File | Role |
|------|------|
| `app/Http/Controllers/RunController.php` | Run creation, status, streaming |
| `app/Services/RunExecutor.php` | Orchestrates GitHub fetch + AI generate + validation |
| `app/Services/LauncherResolutionService.php` | Resolves built-in vs custom launcher by slug |
| `app/Jobs/ExecuteLauncherJob.php` | Queue job (ShouldBeEncrypted, tries=2, timeout=120) |

### API Routes Summary
| Endpoint | Controller | Auth |
|----------|------------|------|
| `GET /api/launchers` | `LauncherController::index` | Session (web) |
| `POST /api/runs` | `RunController::store` | Session (web) |
| `GET /api/runs/{run}` | `RunController::show` | Session (web) |
| `GET /api/runs/{run}/stream` | `RunController::stream` | Session (web) |
| `POST /api/user/launchers` | `UserLauncherController::store` | Auth |
| `GET /api/user/launchers` | `UserLauncherController::index` | Auth |
| `GET /api/user/hidden-launchers` | `UserLauncherController::hidden` | Auth |
| `POST /api/user/hidden-launchers/{launcher:slug}` | `UserLauncherController::hide` | Auth |

## Naming Conventions

| Layer | Convention | Example |
|-------|-----------|---------|
| Controllers | PascalCase, `Controller` suffix | `RunController`, `UserLauncherController` |
| Form Requests | PascalCase, `Store*Request` / `Update*Request` | `StoreRunRequest` |
| API Resources | PascalCase, `Resource` suffix, extends `JsonResource` | `RunResource`, `LauncherResource` |
| Services | PascalCase, descriptive | `RunExecutor`, `GitHubService`, `LauncherMetaService` |
| Models | PascalCase, singular | `Run`, `User`, `UserLauncher` |
| Contracts | PascalCase, `Interface` suffix (when multiple impls) | `AIProviderInterface`, `LauncherSource` |
| Policies | PascalCase, `Policy` suffix | `RunPolicy`, `UserLauncherPolicy` |
| Jobs | PascalCase, `Job` suffix | `ExecuteLauncherJob` |
| DTOs | PascalCase, in `app/Data/` | `ResolvedLauncher`, `GitHubReference` |
| Frontend components | PascalCase, filename = component name | `LaunchArea.tsx` → `LaunchArea` |
| Frontend hooks | `use*` prefix, filename matches | `useRunSubscription.ts` → `useRunSubscription` |
