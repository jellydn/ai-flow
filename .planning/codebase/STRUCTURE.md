# Structure

> Directory layout, key locations, and naming conventions for ai-flow.

## Top-level layout

```
ai-flow/
├── .agents/                 # Agent setup
├── .github/                 # CI workflows (ci.yml, deploy-staging.yml, release.yml) + ai-flow.example.yml
├── .planning/codebase/      # This codebase map (7 docs)
├── .amp/                    # AMP portal config
├── doc/                     # ADRs (doc/adr/), GitHub App setup guide
├── scripts/                 # setup-github-app.sh, hooks/, e2e/
├── AGENTS.md                # AI agent instructions (commands, conventions, gotchas)
├── DESIGN.md                # Design system reference
├── konsistent.json          # Structural TS conventions (root-level)
├── .oxlintrc.json           # oxlint config (root-level)
├── .oxfmtrc.json            # oxfmt config (root-level)
├── .pre-commit-config.yaml  # Pre-commit hooks (prek)
├── justfile                 # just targets (lint-js, test-js, e2e, ci, etc.)
└── backend/                 # ← deploy root (NOT repo root)
```

> **Important:** The deploy root is `backend/`, not the repo root. Dokku and Laravel Cloud deploy from `backend/`.

## Backend (`backend/`)

```
backend/
├── app/
│   ├── Console/Commands/        # ReapStuckRuns.php, PromoteSuperAdminCommand.php
│   ├── Contracts/               # AIProviderInterface, LauncherInterface, LauncherSource
│   ├── Controllers/             # RunController, GitHubWebhookController, Auth/, ...
│   ├── Data/                    # GitHubReference, ResolvedLauncher (DTOs)
│   ├── Events/                  # RunProgressed
│   ├── Exceptions/              # UserFacingRunException
│   ├── Filament/Resources/      # Launchers/, Users/ (super-admin panel)
│   ├── Http/
│   │   ├── Middleware/          # VerifyGitHubWebhook
│   │   ├── Requests/            # Store*Request (form request validation)
│   │   ├── Resources/           # *Resource (API JSON resources)
│   ├── Jobs/                    # ExecuteLauncherJob, ProcessGitHubBotCommandJob
│   ├── Launchers/               # BaseLauncher, ReviewPullRequestLauncher, PlanIssueLauncher, ExplainRepositoryLauncher, LaravelDoctorLauncher
│   ├── Listeners/               # CacheRunProgressedVersion
│   ├── Models/                  # Run, Launcher, User, ProviderCredential, UserLauncher, UserHiddenLauncher, LauncherPromptOverride
│   ├── Policies/                # RunPolicy, UserLauncherPolicy, ProviderCredentialPolicy
│   ├── Providers/               # AppServiceProvider, Filament/AdminPanelProvider
│   ├── Rules/                   # PublicHttpUrl, JsonObjectSchemaRule
│   ├── Security/                # CredentialCipher
│   ├── Services/                # GitHubService, GitHubBotService, RunExecutor, RunStreamer, LaunchParameters, LauncherResolutionService, ContextEncoder, ContextBudget, JsonSchemaValidator, LauncherMetaService, LauncherPromptResolver, RecentRunSummary, GitHubTrendingService, OpenAIProvider, OpenRouterProvider, AnthropicProvider, GeminiProvider, BaseAIProvider
│   └── Support/                 # AiProviderRegistry
├── bootstrap/                   # app.php, providers.php
├── config/                      # 15 config files (app, auth, cache, github-bot, services, ...)
├── database/
│   ├── migrations/              # users, cache, jobs, launchers, runs, magic_login_tokens, provider_credentials, user_launchers, user_hidden_launchers
│   ├── seeders/                 # DatabaseSeeder (syncs built-in launchers), SuperAdminBootstrapSeeder
│   └── factories/               # ProviderCredentialFactory
├── public/                      # index.php, .htaccess, robots.txt
├── resources/
│   ├── css/app.css              # Single consolidated CSS file (plain CSS, ~2400 lines)
│   ├── views/app.blade.php      # SPA entry point
│   └── ts/                      # ← Frontend (see below)
├── routes/                      # api.php, web.php, auth.php, console.php
├── tests/                       # Unit/, Feature/, E2E/, TestCase.php
├── composer.json
├── package.json
├── phpunit.xml
├── tsconfig.json
├── vite.config.ts
├── vitest.config.ts
├── playwright.config.ts
├── Dockerfile                   # Dokku build (React assets + nginx/PHP-FPM)
├── Procfile
└── artisan
```

## Frontend (`backend/resources/ts/`)

```
ts/
├── app.tsx                      # Entry point (mounts React app)
├── types/api.ts                 # Shared API types
├── components/                  # React components (PascalCase, one per file)
│   ├── App.tsx                  # Root component
│   ├── AppViews.tsx             # View routing
│   ├── Home.tsx, Dashboard.tsx, LaunchArea.tsx, Report.tsx, ...
│   ├── CustomLaunchersSection.tsx, ProviderSettings.tsx, ...
│   └── __tests__/               # Component tests (*.test.tsx)
├── hooks/                       # useRunFromPath.ts, useRunSubscription.ts (use* naming)
├── lib/                         # http.ts, appPaths.ts, navigate.ts, runModels.ts, decode.ts, logger.ts, scroll.ts
│   └── __tests__/               # lib tests
├── data/launcherMeta.ts         # Static launcher metadata
├── services/                    # run.ts, auth.ts, userLaunchers.ts (API clients)
└── test/setup.ts                # Vitest setup
```

## Key locations quick reference

| What | Where |
|------|-------|
| API routes | `backend/routes/api.php` |
| Run creation flow | `backend/app/Http/Controllers/RunController.php` |
| AI run execution | `backend/app/Jobs/ExecuteLauncherJob.php` → `backend/app/Services/RunExecutor.php` |
| AI provider interface | `backend/app/Contracts/AIProviderInterface.php` |
| Provider registry | `backend/app/Support/AiProviderRegistry.php` |
| GitHub context fetch | `backend/app/Services/GitHubService.php` |
| GitHub bot | `backend/app/Services/GitHubBotService.php` + `backend/app/Jobs/ProcessGitHubBotCommandJob.php` |
| Webhook handler | `backend/app/Http/Controllers/GitHubWebhookController.php` |
| SSE streaming | `backend/app/Services/RunStreamer.php` |
| Credential encryption | `backend/app/Security/CredentialCipher.php` |
| Rate limiters | `backend/app/Providers/AppServiceProvider.php` (boot method) |
| Built-in launchers | `backend/app/Launchers/` (4 classes) |
| Seeder | `backend/database/seeders/DatabaseSeeder.php` |
| Production guards | `backend/app/Providers/AppServiceProvider.php` (boot method) |
| Frontend entry | `backend/resources/ts/app.tsx` |
| Frontend root component | `backend/resources/ts/components/App.tsx` |
| CI workflow | `.github/workflows/ci.yml` |

## Naming conventions

- **PHP:** PSR-12 (Pint). One class per file. Controllers `*Controller`. Form requests `Store*Request`/`Update*Request`. API resources `*Resource`. Jobs `*Job`. Policies `*Policy`.
- **React/TS:** Functional components + hooks. `components/*.tsx` export a PascalCase component matching the filename. `hooks/*.ts` export `use*` functions. Strict mode, avoid broad `any`.
- **Launchers:** One class per workflow under `app/Launchers/`, metadata via `BaseLauncher::make()`, seeded in `DatabaseSeeder`.
- **Built-in slugs:** `review-pr`, `plan-issue`, `explain-repository`, `laravel-doctor`.
- **Route aliases:** `/api/flows`=`/api/launchers`, `/api/executions`=`/api/runs` (backward compat).
