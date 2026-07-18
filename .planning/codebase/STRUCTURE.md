# Directory Structure

## Project Layout

```
ai-flow/
├── README.md                  # Project README (polished open-source)
├── AGENTS.md                  # AI coding assistant guide (authoritative)
├── DESIGN.md                  # Visual identity (colors, typography, components)
├── LICENSE                    # MIT
├── justfile                   # Task runner (just ci, just test, just dev, etc.)
├── konsistent.json            # Structural TS conventions (components/hooks rules)
├── .oxlintrc.json             # oxlint config (typescript, unicorn, oxc; correctness: error; no-console)
├── .oxfmtrc.json              # oxfmt config (ignores node_modules, public, vendor)
├── .pre-commit-config.yaml    # Pre-commit hooks (prek) — pint, typecheck, oxlint, oxfmt, konsistent
├── .gitguardian.yml           # GitGuardian secret scanning
├── .editorconfig              # Editor config
├── renovate.json              # Renovate dependency bot config
├── plan.md                    # Project plan (historical)
├── .agents/                   # Agent config (setup file)
├── .amp/                      # Amp portal config
│   └── portals/ai-launcher.json
├── .github/                   # CI workflows
│   └── workflows/
│       ├── ci.yml             # Main CI (backend PHP 8.4 + frontend Node 24)
│       ├── deploy-staging.yml # Dokku deploy
│       └── react-doctor.yml   # React Doctor check
├── doc/                       # Documentation
│   ├── adr/                   # Architecture Decision Records (22 ADRs, see README.md)
│   └── PRIVACY.md             # Privacy policy
├── .planning/                 # Codebase documentation
│   └── codebase/              # Codemap output (this directory, 7 files)
└── backend/                   # Application root (DEPLOY ROOT — not repo root)
    ├── README.md              # Backend setup and API guide
    ├── composer.json          # PHP dependencies
    ├── composer.lock          # Locked PHP deps
    ├── package.json           # Node.js dependencies (frontend build)
    ├── package-lock.json      # Locked Node deps
    ├── vite.config.ts         # Vite config (port 5173, laravel-vite-plugin, react plugin)
    ├── tsconfig.json          # TS config (strict, ES2022, react-jsx, noEmit)
    ├── vitest.config.ts       # Vitest config (jsdom, setup file, **/*.test.{ts,tsx})
    ├── playwright.config.ts   # Playwright E2E config
    ├── phpunit.xml            # PHPUnit config (Unit + Feature suites, :memory: sqlite)
    ├── Dockerfile             # Docker build (nginx + PHP-FPM) — Dokku staging
    ├── Procfile               # Laravel Cloud process file
    ├── app.json               # Laravel Cloud config
    ├── artisan                # Laravel CLI
    ├── DOKKU_DEPLOY.md        # Dokku deployment guide
    ├── CLOUD_DEPLOY.md        # Laravel Cloud deployment guide
    ├── .env.example           # Environment template
    ├── .dockerignore
    ├── .gitignore
    ├── .gitattributes
    │
    ├── app/                   # PHP application code
    │   ├── Console/
    │   │   └── Commands/
    │   │       ├── ReapStuckRuns.php              # Scheduled: reap orphaned running runs (TTL 180s)
    │   │       └── PromoteSuperAdminCommand.php   # Promote user to super admin
    │   ├── Contracts/                             # Interfaces
    │   │   ├── AIProviderInterface.php            # id, models, defaultModel, verifyCredential, generate
    │   │   └── LauncherInterface.php              # static metadata()
    │   ├── Data/                                  # DTOs (readonly)
    │   │   └── GitHubReference.php                # owner, repo, type, number
    │   ├── Events/
    │   │   └── RunProgressed.php                  # Dispatchable, carries Run
    │   ├── Exceptions/
    │   │   └── UserFacingRunException.php         # Expected user/input failures (not Sentry-reported)
    │   ├── Filament/                              # Admin panel (v5)
    │   │   └── Resources/
    │   │       ├── Launchers/                     # Launcher admin resource
    │   │       └── Users/                         # User admin resource
    │   ├── Http/
    │   │   ├── Controllers/
    │   │   │   ├── Controller.php                 # Base controller
    │   │   │   ├── RunController.php              # store, show, stream, recent
    │   │   │   ├── RunHistoryController.php       # User run history
    │   │   │   ├── ProviderController.php         # List AI providers
    │   │   │   ├── ProviderCredentialController.php # CRUD + verify + makeDefault
    │   │   │   ├── AccountController.php          # destroy (account deletion)
    │   │   │   ├── LauncherPromptController.php   # index, update (per-user overrides)
    │   │   │   ├── TrendingRepositoryController.php # Trending GitHub repos
    │   │   │   └── Auth/
    │   │   │       ├── MagicLinkController.php    # request, verify, logout
    │   │   │       └── PasswordAuthController.php # register, login, logout
    │   │   ├── Requests/                          # Form requests (validation)
    │   │   │   ├── StoreRunRequest.php            # + withValidator (LaunchParameters checks)
    │   │   │   ├── StoreProviderCredentialRequest.php
    │   │   │   ├── UpdateProviderCredentialRequest.php
    │   │   │   └── UpsertLauncherPromptRequest.php
    │   │   └── Resources/                         # API resources (JSON)
    │   │       ├── RunResource.php                # + provider_label via registry
    │   │       ├── UserResource.php
    │   │       └── ProviderCredentialResource.php # + masked_key
    │   ├── Jobs/
    │   │   └── ExecuteLauncherJob.php             # ShouldBeEncrypted, ShouldQueue (tries=2, timeout=120)
    │   ├── Launchers/                             # Workflow definitions (Strategy)
    │   │   ├── BaseLauncher.php                   # abstract; outputSchema(), make()
    │   │   ├── ReviewPullRequestLauncher.php      # slug: review-pr
    │   │   ├── PlanIssueLauncher.php              # slug: plan-issue
    │   │   ├── ExplainRepositoryLauncher.php      # slug: explain-repository
    │   │   └── LaravelDoctorLauncher.php          # slug: laravel-doctor
    │   ├── Listeners/
    │   │   └── CacheRunProgressedVersion.php      # Writes run:version:{id} cache on RunProgressed
    │   ├── Mail/
    │   │   ├── MagicLinkMail.php                  # Magic link email (queued)
    │   │   └── SuperAdminBootstrapMail.php        # Super admin bootstrap email
    │   ├── Models/                                # Eloquent models
    │   │   ├── Run.php                            # HasUuids; STATUSES, TERMINAL_STATUSES; markFailed()
    │   │   ├── User.php                           # Authenticatable + FilamentUser; is_super_admin
    │   │   ├── Launcher.php                       # prompt_template, output_schema (cast array)
    │   │   ├── LauncherPromptOverride.php         # Per-user prompt overrides
    │   │   └── ProviderCredential.php             # HasUuids; encrypted_api_key, encrypted_base_url; decryptApiKey()
    │   ├── Policies/
    │   │   ├── RunPolicy.php                      # view (public/owner), retry, delete (owner)
    │   │   └── ProviderCredentialPolicy.php       # manage (owner)
    │   ├── Providers/
    │   │   ├── AppServiceProvider.php             # Singleton AiProviderRegistry; rate limiters; production guards
    │   │   └── Filament/
    │   │       └── AdminPanelProvider.php         # Filament admin panel at /admin
    │   ├── Rules/
    │   │   └── PublicHttpUrl.php                  # Custom validation rule
    │   ├── Security/
    │   │   └── CredentialCipher.php               # AES-256-CBC; encrypt/decrypt/mask
    │   ├── Services/                              # Business logic (12 classes)
    │   │   ├── BaseAIProvider.php                 # abstract; HTTP lifecycle + subclass hooks (259 lines)
    │   │   ├── OpenAIProvider.php
    │   │   ├── OpenRouterProvider.php
    │   │   ├── AnthropicProvider.php
    │   │   ├── GeminiProvider.php
    │   │   ├── RunExecutor.php                    # Orchestrates fetch → generate → validate
    │   │   ├── RunStreamer.php                    # SSE generator with cache-versioned polling
    │   │   ├── GitHubService.php                  # Parse + cached fetch + assemble (199 lines)
    │   │   ├── ContextEncoder.php                 # Sanitize + truncate to ContextBudget
    │   │   ├── ContextBudget.php                  # Fetch/budget-tier limit constants
    │   │   ├── JsonSchemaValidator.php            # Recursive schema validation
    │   │   ├── LaunchParameters.php               # Provider/model/key resolution (141 lines)
    │   │   ├── RecentRunSummary.php               # Lightweight summary for home page
    │   │   ├── LauncherPromptResolver.php         # effectivePrompt (override or default)
    │   │   └── GitHubTrendingService.php          # Trending repos scrape
    │   └── Support/
    │       └── AiProviderRegistry.php             # Provider→class map; resolveApiKey; resolveModel (189 lines)
    │
    ├── bootstrap/
    │   ├── app.php                                # Laravel bootstrap
    │   └── providers.php                          # Provider list
    ├── config/                                    # Laravel config (12 files)
    │   ├── app.php, auth.php, cache.php, cors.php,
    │   ├── credentials.php                        # CREDENTIAL_ENCRYPTION_KEY
    │   ├── database.php, filesystems.php, logging.php,
    │   ├── mail.php, queue.php, sentry.php,
    │   ├── services.php                           # AI providers, GitHub, mail
    │   ├── session.php, super_admin.php
    ├── database/
    │   ├── .gitignore
    │   ├── migrations/                            # 14 migrations (listed below)
    │   │   ├── 0001_01_01_000000_create_users_table.php
    │   │   ├── 0001_01_01_000001_create_cache_table.php
    │   │   ├── 0001_01_01_000002_create_jobs_table.php
    │   │   ├── 2026_01_01_000000_create_launchers_and_runs.php
    │   │   ├── 2026_03_22_100000_add_is_super_admin_to_users_table.php
    │   │   ├── 2026_07_12_000001_add_runs_created_at_index.php
    │   │   ├── 2026_07_12_000002_drop_class_name_from_launchers.php
    │   │   ├── 2026_07_13_000001_adapt_users_for_magic_link_auth.php
    │   │   ├── 2026_07_13_000002_create_magic_login_tokens_table.php
    │   │   ├── 2026_07_13_000003_create_provider_credentials_table.php
    │   │   ├── 2026_07_13_000004_add_ownership_to_runs_table.php
    │   │   ├── 2026_07_15_000001_add_recent_runs_index_to_runs_table.php
    │   │   ├── 2026_07_15_100000_add_launcher_prompt_overrides_and_run_snapshot.php
    │   │   └── 2026_07_16_000001_add_repo_metadata_to_runs_table.php
    │   ├── seeders/
    │   │   ├── DatabaseSeeder.php                 # Seeds 4 launchers + calls SuperAdminBootstrapSeeder
    │   │   └── SuperAdminBootstrapSeeder.php      # Seeds/promotes super admin from config
    │   └── factories/
    │       └── UserFactory.php                    # User factory (with unverified state)
    ├── public/                                    # Public assets (docroot)
    │   ├── index.php                              # Laravel entry point
    │   ├── robots.txt, .htaccess
    │   ├── favicon.svg, favicon.ico, apple-touch-icon.png
    │   ├── logo.svg, logo-dark.svg, demo.png
    │   └── build/                                 # Vite build output (gitignored)
    ├── resources/
    │   ├── views/
    │   │   └── app.blade.php                      # SPA shell (mounts #root)
    │   ├── css/
    │   │   └── app.css                            # Global styles (vanilla CSS, no Tailwind)
    │   └── ts/                                    # React TypeScript app
    │       ├── app.tsx                            # Entry: Sentry.init + ErrorBoundary + App
    │       ├── components/                        # React components (PascalCase)
    │       │   ├── App.tsx                        # Root state: user, view, currentRunId (442 lines)
    │       │   ├── AppViews.tsx                   # View routing (190 lines)
    │       │   ├── Home.tsx                       # Landing page (289 lines)
    │       │   ├── LaunchArea.tsx                 # Launcher + URL + provider input (226 lines)
    │       │   ├── Report.tsx                     # Run result display (219 lines)
    │       │   ├── SignIn.tsx                     # Auth UI (447 lines)
    │       │   ├── Dashboard.tsx                  # User dashboard (147 lines)
    │       │   ├── RunHistory.tsx                 # User run history (154 lines)
    │       │   ├── ProviderSettings.tsx           # BYOK credential management (125 lines)
    │       │   ├── CredentialForm.tsx, CredentialList.tsx
    │       │   ├── WorkflowPromptsSection.tsx     # Per-user prompt overrides (162 lines)
    │       │   ├── Header.tsx, Footer.tsx, Logo.tsx
    │       │   ├── LauncherSelector.tsx, LauncherIcon.tsx, UrlInput.tsx
    │       │   ├── Running.tsx                    # Progress display
    │       │   ├── MarkdownBody.tsx               # react-markdown wrapper
    │       │   ├── PrivacyNote.tsx                # Privacy/key-handling notice
    │       │   ├── RecentRunsSection.tsx, TrendingCard.tsx
    │       │   ├── ErrorBoundary.tsx              # Class component (konsistent exception)
    │       │   ├── appUiState.ts                  # UI state helpers
    │       │   └── __tests__/                     # Component tests (5 files)
    │       ├── hooks/                             # Custom hooks (use*)
    │       │   ├── useRunSubscription.ts          # SSE + polling fallback (153 lines)
    │       │   └── useRunFromPath.ts              # Run ID from URL path (95 lines)
    │       ├── services/                          # API service layer
    │       │   ├── run.ts                         # Runs, launchers, recent, trending (295 lines)
    │       │   └── auth.ts                        # Auth, credentials, prompts (218 lines)
    │       ├── lib/                               # Utilities
    │       │   ├── http.ts                        # get/post + CSRF + timeout (134 lines)
    │       │   ├── logger.ts, navigate.ts, scroll.ts
    │       │   ├── appPaths.ts, runModels.ts
    │       │   └── __tests__/
    │       ├── types/
    │       │   └── api.ts                         # TypeScript API types
    │       └── data/
    │           └── launcherMeta.ts                # Static launcher metadata + icons
    ├── routes/
    │   ├── api.php                                # API routes (launchers, runs, providers, user/* )
    │   ├── auth.php                               # Auth routes (register, login, magic-link, logout)
    │   ├── web.php                                # Web routes (catch-all SPA, excludes /api, /admin)
    │   └── console.php                            # Console routes (inspire, schedule ReapStuckRuns)
    ├── scripts/
    │   └── e2e/
    │       └── serve-real.sh                      # E2E real-backend server script
    ├── storage/                                   # Logs, cache, compiled views (gitignored)
    │   ├── app/.gitignore
    │   ├── framework/.gitignore
    │   └── logs/.gitignore
    └── tests/                                     # Test suites
        ├── TestCase.php                           # Base test case
        ├── Unit/                                  # 11 unit tests
        ├── Feature/                               # 18 feature tests
        └── E2E/                                   # Playwright E2E (flows/, helpers/)
```

## Key Locations

| What | Where |
|---|---|
| API entry points | `backend/routes/api.php` |
| Run lifecycle | `backend/app/Http/Controllers/RunController.php` → `backend/app/Jobs/ExecuteLauncherJob.php` → `backend/app/Services/RunExecutor.php` |
| AI providers | `backend/app/Services/*Provider.php` → `BaseAIProvider` → `AIProviderInterface` |
| Provider registry | `backend/app/Support/AiProviderRegistry.php` (singleton) |
| Workflow definitions | `backend/app/Launchers/*Launcher.php` → `BaseLauncher` → `LauncherInterface` |
| Validation | `backend/app/Http/Requests/Store*Request.php` |
| JSON responses | `backend/app/Http/Resources/*Resource.php` |
| React entry | `backend/resources/ts/app.tsx` |
| API client | `backend/resources/ts/services/run.ts`, `backend/resources/ts/services/auth.ts` |
| HTTP utilities | `backend/resources/ts/lib/http.ts` |
| SPA shell | `backend/resources/views/app.blade.php` |
| Rate limiters | `backend/app/Providers/AppServiceProvider.php` (`boot()`) |
| Production guards | `backend/app/Providers/AppServiceProvider.php` (`boot()`) — sqlite/sync/TLS checks |
| Credential encryption | `backend/app/Security/CredentialCipher.php` |
| SSE streaming | `backend/app/Services/RunStreamer.php` + `backend/app/Listeners/CacheRunProgressedVersion.php` |

## Naming Conventions

| Context | Convention | Example |
|---|---|---|
| PHP classes | PSR-4 autoload, `App\` namespace | `App\Services\OpenAIProvider` |
| Controllers | `*Controller`, thin, delegate to services/jobs | `RunController` |
| Services | `*Provider`, `*Service`, `*Executor`, `*Registry`, `*Resolver`, `*Streamer`, `*Encoder`, `*Validator` | `RunExecutor`, `GitHubService` |
| Jobs | `Execute*Job`, implements `ShouldQueue` | `ExecuteLauncherJob` |
| Launchers | `*Launcher` extends `BaseLauncher` | `ReviewPullRequestLauncher` |
| Form requests | `Store*Request`, `Update*Request`, `Upsert*Request` | `StoreRunRequest` |
| API resources | `*Resource` extends `JsonResource` | `RunResource` |
| Models | Singular, Eloquent | `Run`, `ProviderCredential` |
| Policies | `*Policy` | `RunPolicy` |
| Events | `*ed` (past tense) | `RunProgressed` |
| Mail | `*Mail` | `MagicLinkMail` |
| Console commands | `app:*-{verb}` | `app:reap-stuck-runs` |
| React components | PascalCase, filename matches default export (`konsistent.json` enforced) | `LaunchArea.tsx` → `LaunchArea` |
| Custom hooks | `use*`, filename matches named export (`konsistent.json` enforced) | `useRunSubscription.ts` → `useRunSubscription` |
| Service files | `*.ts` in `services/` | `run.ts` |
| Type definitions | `*.ts` in `types/` | `api.ts` |
| Test files | `*.test.{ts,tsx}` (frontend), `*Test.php` (backend) | `OpenAIProviderTest.php` |
| Route slugs | kebab-case | `review-pr`, `plan-issue` |
| Config keys | snake_case | `services.openai.key` |

## Deploy Root Note

The **deploy root is `backend/`, not the repo root**. Dokku and Laravel Cloud both deploy `backend/` as the app root. The repo root holds cross-cutting config (`.oxlintrc.json`, `konsistent.json`, `justfile`, `.pre-commit-config.yaml`, `renovate.json`, `doc/adr/`).
