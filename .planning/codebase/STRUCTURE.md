# Directory Structure

**Analysis Date:** 2026-07-13

> All application code lives under `backend/`. The project root contains docs, CI, and repo config.
>
> Deployment root: `backend/` (both Laravel Cloud and Dokku build from here).

## Top-Level Layout

```
backend/
├── app/                  # Application code (PSR-4: App\)
├── bootstrap/            # Framework bootstrap (app.php, providers.php, cache/)
├── config/               # Laravel config files
├── database/             # Migrations, seeders, factories
├── docker/               # Docker configs (nginx, supervisor, bin/)
├── public/               # Web root (index.php, .htaccess, robots.txt)
├── resources/            # Views (Blade), CSS, TypeScript (React SPA)
├── routes/               # Route definitions (web, api, console)
├── storage/              # Framework storage (cache, logs, sessions, views)
├── tests/                # PHPUnit tests (Unit/, Feature/)
├── composer.json         # PHP dependencies
├── package.json          # Node dependencies
├── Dockerfile            # Multi-stage production image
├── Procfile              # Dokku process definitions
├── app.json              # Dokku healthcheck configuration
└── .dockerignore         # Docker build exclusions
```

## `app/` Directory

```
app/
├── Console/
│   └── Commands/
│       └── ReapStuckRuns.php      # Stuck-run reaper (scheduled command)
├── Contracts/
│   ├── AIProviderInterface.php    # AI provider contract
│   ├── LauncherInterface.php      # Launcher metadata contract
│   └── RunExecutorInterface.php   # Run execution contract
├── Data/
│   └── GitHubReference.php        # Parsed GitHub URL DTO
├── Events/
│   └── RunProgressed.php          # Dispatched on every progress step
├── Http/
│   ├── Controllers/
│   │   ├── Controller.php         # Base controller
│   │   └── RunController.php      # CRUD + stream endpoints
│   ├── Requests/
│   │   └── StoreRunRequest.php    # POST validation + field aliases
│   └── Resources/
│       └── RunResource.php        # JSON shape for runs
├── Jobs/
│   └── ExecuteLauncherJob.php     # Queue job (ShouldBeEncrypted, tries=2, timeout=120)
├── Launchers/
│   ├── BaseLauncher.php           # Abstract base (shared outputSchema + make)
│   ├── ExplainRepositoryLauncher.php
│   ├── LaravelDoctorLauncher.php
│   ├── PlanIssueLauncher.php
│   └── ReviewPullRequestLauncher.php
├── Models/
│   ├── Launcher.php               # Launcher config (seeded from Launcher classes)
│   ├── Run.php                    # Run record (UUID PK, JSON casts)
│   └── User.php                   # User stub (unused in MVP)
├── Providers/
│   └── AppServiceProvider.php     # Container bindings, rate limiters, production guards
├── Services/
│   ├── ContextEncoder.php         # Bounds context to budget (120KB)
│   ├── GitHubContextAssembler.php # Shapes raw GitHub data into context array
│   ├── GitHubContextFetcher.php   # Raw GitHub REST API calls
│   ├── GitHubService.php          # URL parse + cached context (composes fetcher + assembler)
│   ├── JsonSchemaValidator.php    # Validates AI JSON output against schema
│   ├── OpenAIProvider.php         # OpenAI-compatible provider (implements AIProviderInterface)
│   ├── RunExecutor.php            # Orchestrates a run end-to-end
│   └── RunStreamer.php            # SSE generator (DB poll, ~55s)
└── Support/
    └── AiProviders.php            # Provider factory (match expression, single 'openai' arm)
```

## `resources/ts/` (React SPA)

```
resources/ts/
├── app.tsx                 # Entry point (mounts App)
├── components/
│   ├── App.tsx             # Main orchestrator (useReducer, launch, views)
│   ├── appUiState.ts       # UI state machine + view transitions
│   ├── Home.tsx            # Launcher picker + URL input + launch button
│   ├── Running.tsx         # Live progress timeline
│   ├── Report.tsx          # Structured report display
│   ├── Header.tsx          # Top bar
│   ├── Footer.tsx          # Bottom bar
│   ├── LauncherIcon.tsx    # Workflow icons
│   └── Logo.tsx            # App logo
├── hooks/
│   ├── useRunFromPath.ts   # Deep-link resolver (reads /runs/{id} from URL)
│   └── useRunSubscription.ts # SSE + polling fallback
├── services/
│   └── run.ts              # HTTP client (createRun, fetchRun, getLaunchers, decodeRun)
├── data/
│   └── launcherMeta.ts     # Static launcher metadata + demo steps
├── lib/
│   ├── http.ts             # HTTP error handling helpers
│   └── scroll.ts           # Scroll utilities
└── types/
    └── api.ts              # TypeScript types (Run, Launcher)
```

## Naming Conventions

| Concern | Convention |
|---------|-----------|
| PHP classes | `PascalCase`, one per file, PSR-4 namespace `App\` |
| Contracts | Suffixed `Interface` (e.g., `AIProviderInterface`) |
| Services | `PascalCase` under `app/Services/` |
| Launchers | One class per workflow, `PascalCase` under `app/Launchers/` |
| TS components | `PascalCase` matching filename (`App.tsx` → `export function App`) |
| TS hooks | `use*` function matching filename (`useRunSubscription.ts` → `useRunSubscription`) |
| Database columns | `snake_case` (e.g., `source_url`, `started_at`) |

---

*Structure analysis: 2026-07-13*
