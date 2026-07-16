# Directory Structure

## Repository Root

```
.
├── .agents/                     # Agent skills & setup
├── .amp/                        # Amp portal config (preview hosting)
│   └── portals/
│       └── ai-launcher.json
├── .github/
│   └── workflows/
│       ├── ci.yml               # CI: PHP 8.4 + Node 24
│       └── deploy-staging.yml   # Dokku staging deploy
├── .planning/
│   ├── codebase/                # ← THIS CODASEMAP
│   │   ├── STACK.md
│   │   ├── INTEGRATIONS.md
│   │   ├── ARCHITECTURE.md
│   │   ├── STRUCTURE.md
│   │   ├── CONVENTIONS.md
│   │   ├── TESTING.md
│   │   └── CONCERNS.md
│   └── handoffs/                # Session handoff files
├── backend/                     # ⭐ DEPLOY ROOT — Laravel app
├── doc/
│   └── adr/                     # Architecture Decision Records (22 ADRs)
│       └── README.md
├── scripts/
│   ├── e2e/
│   │   └── serve-real.sh
│   └── hooks/                   # Pre-commit hook scripts
│       └── *.sh
├── .editorconfig
├── .gitattributes
├── .gitguardian.yml
├── .gitignore
├── .oxfmtrc.json                # oxfmt config (repo root)
├── .oxlintrc.json               # oxlint config (repo root)
├── .pre-commit-config.yaml
├── AGENTS.md                    # AI agent instructions
├── CLOUD_DEPLOY.md
├── DESIGN.md
├── LICENSE
├── PRIVACY.md
├── README.md
├── justfile
├── konsistent.json              # TS structural convention rules
└── renovate.json
```

## Backend (`backend/`)

```
backend/
├── app/
│   ├── Console/
│   │   └── Commands/
│   │       ├── PromoteSuperAdminCommand.php
│   │       └── ReapStuckRuns.php        # Cleans stalled runs (>10min processing)
│   ├── Contracts/
│   │   ├── AIProviderInterface.php       # generate(), verifyCredential(), defaultModel()
│   │   ├── LauncherInterface.php
│   │   └── RunExecutorInterface.php
│   ├── Data/
│   │   └── GitHubReference.php           # owner/repo value object
│   ├── Events/
│   │   └── RunProgressed.php             # Fired when run status changes
│   ├── Filament/                         # Super admin panel (ADR-0021)
│   │   └── Resources/
│   │       ├── Launchers/                # Workflow template CRUD
│   │       │   ├── LauncherResource.php
│   │       │   ├── Pages/
│   │       │   │   ├── EditLauncher.php
│   │       │   │   └── ListLaunchers.php
│   │       │   ├── Schemas/
│   │       │   │   └── LauncherForm.php
│   │       │   └── Tables/
│   │       │       └── LaunchersTable.php
│   │       └── Users/                    # User CRUD
│   │           ├── UserResource.php
│   │           ├── Pages/
│   │           │   ├── CreateUser.php
│   │           │   ├── EditUser.php
│   │           │   └── ListUsers.php
│   │           ├── Schemas/
│   │           │   └── UserForm.php
│   │           └── Tables/
│   │               └── UsersTable.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── AccountController.php
│   │   │   ├── Auth/
│   │   │   │   ├── MagicLinkController.php
│   │   │   │   └── PasswordAuthController.php
│   │   │   ├── Controller.php             # Base controller
│   │   │   ├── LauncherPromptController.php
│   │   │   ├── ProviderController.php
│   │   │   ├── ProviderCredentialController.php
│   │   │   ├── RunController.php          # store, show, stream, recent
│   │   │   ├── RunHistoryController.php
│   │   │   └── TrendingRepositoryController.php
│   │   ├── Requests/
│   │   │   ├── StoreProviderCredentialRequest.php
│   │   │   ├── StoreRunRequest.php        # Launch validation + LaunchParameters
│   │   │   ├── UpdateProviderCredentialRequest.php
│   │   │   └── UpsertLauncherPromptRequest.php
│   │   └── Resources/
│   │       ├── ProviderCredentialResource.php
│   │       ├── RunResource.php
│   │       └── UserResource.php
│   ├── Jobs/
│   │   └── ExecuteLauncherJob.php         # Single queued job orchestrating runs
│   ├── Launchers/
│   │   ├── BaseLauncher.php               # Abstract base: slug, make(), outputSchema
│   │   ├── ExplainRepositoryLauncher.php
│   │   ├── LaravelDoctorLauncher.php
│   │   ├── PlanIssueLauncher.php
│   │   └── ReviewPullRequestLauncher.php
│   ├── Listeners/
│   │   └── CacheRunProgressedVersion.php  # DB version tracking for SSE skip
│   ├── Mail/
│   │   ├── MagicLinkMail.php
│   │   └── SuperAdminBootstrapMail.php
│   ├── Models/
│   │   ├── Launcher.php
│   │   ├── LauncherPromptOverride.php
│   │   ├── ProviderCredential.php
│   │   ├── Run.php                        # UUID PK, JSON columns, markFailed()
│   │   └── User.php
│   ├── Policies/
│   │   ├── ProviderCredentialPolicy.php
│   │   └── RunPolicy.php
│   ├── Providers/
│   │   ├── AppServiceProvider.php         # Rate limiters, binding
│   │   └── Filament/
│   │       └── AdminPanelProvider.php
│   ├── Rules/
│   │   └── PublicHttpUrl.php
│   ├── Security/
│   │   └── CredentialCipher.php           # AES-256-CBC BYOK encryption
│   ├── Services/
│   │   ├── AnthropicProvider.php          # → BaseAIProvider
│   │   ├── BaseAIProvider.php             # ★ Abstract base owning HTTP lifecycle (ADR-0022)
│   │   ├── ContextBudget.php              # Shared truncation constants
│   │   ├── ContextEncoder.php             # Truncation for AI token limits
│   │   ├── GeminiProvider.php             # → BaseAIProvider
│   │   ├── GitHubContextAssembler.php     # Structured context from GitHub API
│   │   ├── GitHubContextFetcher.php       # Cached GitHub API calls
│   │   ├── GitHubService.php              # URL parsing → GitHubReference
│   │   ├── GitHubTrendingService.php      # Trending repos (cached)
│   │   ├── JsonSchemaValidator.php        # Validates AI output against schema
│   │   ├── LaunchAiKeyResolver.php        # Key resolution: injected → credential → config
│   │   ├── LauncherPromptResolver.php     # Prompt resolution: default → user override
│   │   ├── LaunchParameters.php           # ★ Value object: provider/model/key resolution
│   │   ├── OpenAIProvider.php             # → BaseAIProvider
│   │   ├── OpenRouterProvider.php         # → BaseAIProvider
│   │   ├── RecentRunSummary.php           # Run → home-card transformer
│   │   ├── RunExecutor.php                # execute() orchestration (service)
│   │   └── RunStreamer.php               # SSE streaming via DB polling
│   └── Support/
│       └── AiProviderRegistry.php         # Provider lookup + resolveModel()
├── bootstrap/
│   ├── app.php
│   └── providers.php
├── config/
│   ├── app.php
│   ├── auth.php
│   ├── cache.php
│   ├── cors.php
│   ├── database.php
│   ├── filesystems.php
│   ├── logging.php
│   ├── mail.php
│   ├── queue.php
│   ├── sentry.php
│   ├── services.php                       # AI keys, models, timeout, GitHub, Resend
│   ├── session.php
│   └── super_admin.php                    # Super admin email allowlist
├── database/
│   └── migrations/                        # Standard Laravel migrations
├── public/
│   ├── index.php                          # Laravel front controller
│   ├── .htaccess
│   └── robots.txt
├── resources/
│   ├── css/
│   │   └── app.css
│   ├── ts/
│   │   ├── app.tsx                        # React entry point
│   │   ├── components/
│   │   │   ├── App.tsx                    # Root component with routing
│   │   │   ├── AppViews.tsx               # View state machine
│   │   │   ├── Dashboard.tsx              # Authenticated user dashboard
│   │   │   ├── Header.tsx                 # Navigation header
│   │   │   ├── Home.tsx                   # Landing page
│   │   │   ├── LaunchArea.tsx             # URL input + provider selector
│   │   │   ├── Report.tsx                 # AI report display
│   │   │   ├── RunHistory.tsx             # Past runs list
│   │   │   ├── SignIn.tsx                 # Auth forms
│   │   │   ├── TrendingRepos.tsx          # Top 3 trending repos
│   │   │   ├── appUiState.ts              # UI state management
│   │   │   └── __tests__/                 # Component tests
│   │   ├── data/
│   │   │   └── launcherMeta.ts            # Workflow metadata (slugs, labels, icons)
│   │   ├── hooks/
│   │   │   ├── useRunFromPath.ts          # Load run from URL path
│   │   │   └── useRunSubscription.ts      # SSE subscription hook
│   │   ├── lib/
│   │   │   ├── appPaths.ts                # Route path constants
│   │   │   ├── http.ts                    # Axios/fetch wrapper
│   │   │   ├── logger.ts                  # consola logger config
│   │   │   ├── navigate.ts                # Client-side navigation
│   │   │   ├── runModels.ts               # Model selection logic
│   │   │   ├── scroll.ts                  # Scroll utilities
│   │   │   └── __tests__/
│   │   ├── services/
│   │   │   ├── auth.ts                    # Auth API client
│   │   │   └── run.ts                     # Run API client
│   │   └── types/
│   │       └── api.ts                     # API response types
│   └── views/
│       └── app.blade.php                  # Single Blade view → loads React
├── routes/
│   ├── api.php                            # All REST + SSE endpoints
│   ├── auth.php                           # Auth routes
│   ├── console.php                        # Console routes (ReapStuckRuns scheduling)
│   └── web.php                            # Web routes (Filament, SPA fallback)
├── storage/
│   ├── app/
│   ├── framework/
│   └── logs/
├── tests/
│   ├── TestCase.php                       # Base test case
│   ├── Feature/                           # 18 feature test files
│   │   ├── AccountDeletionTest.php
│   │   ├── ExecuteLauncherJobTest.php
│   │   ├── FilamentPanelAccessTest.php
│   │   ├── LauncherPromptApiTest.php
│   │   ├── MagicLinkAuthTest.php
│   │   ├── PasswordAuthTest.php
│   │   ├── ProviderCredentialApiTest.php
│   │   ├── ProviderCredentialBaseUrlValidationTest.php
│   │   ├── ReapStuckRunsTest.php
│   │   ├── RunApiTest.php
│   │   ├── RunHistoryTest.php
│   │   ├── RunOwnershipTest.php
│   │   ├── RunPromptSnapshotTest.php
│   │   ├── RunRequiresProviderKeyTest.php
│   │   ├── SavedCredentialLaunchTest.php
│   │   ├── SessionRunCsrfTest.php
│   │   ├── SuperAdminBootstrapSeederTest.php
│   │   └── TrendingRepositoriesApiTest.php
│   ├── Unit/                              # 12 unit test files
│   │   ├── AiProviderRegistryTest.php
│   │   ├── AnthropicProviderTest.php
│   │   ├── CacheRunProgressedVersionTest.php
│   │   ├── ContextEncoderTest.php
│   │   ├── CredentialCipherTest.php
│   │   ├── GeminiProviderTest.php
│   │   ├── GitHubContextAssemblerTest.php
│   │   ├── GitHubContextFetcherTest.php
│   │   ├── GitHubServiceTest.php
│   │   ├── OpenAIProviderTest.php
│   │   ├── OpenRouterProviderTest.php
│   │   └── RunStreamerTest.php
│   └── E2E/                               # Playwright E2E tests
│       └── flows/
│           └── demo-full-flow.spec.ts
├── .dockerignore
├── .env.example
├── .gitignore
├── Dockerfile                             # nginx + PHP-FPM + React build
├── Procfile
├── README.md
├── artisan
├── composer.json
├── composer.lock
├── package.json
├── phpunit.xml
├── playwright.config.ts
├── tsconfig.json
├── vite.config.ts
└── vitest.config.ts
```

## Key Naming Conventions

| Pattern | Convention |
|---------|-----------|
| PHP Controllers | `{Resource}Controller` (e.g., `RunController`, `ProviderCredentialController`) |
| PHP Requests | `Store{Resource}Request`, `Update{Resource}Request`, `Upsert{Resource}Request` |
| PHP Resources | `{Resource}Resource` (API JSON serialization) |
| PHP Models | PascalCase singular: `Run`, `Launcher`, `User`, `ProviderCredential` |
| PHP Services | Descriptive: `GitHubService`, `RunStreamer`, `LaunchParameters`, `ContextBudget` |
| PHP Contracts | `{Domain}Interface` (e.g., `AIProviderInterface`, `LauncherInterface`) |
| TS Components | PascalCase matching filename: `App.tsx` → `App`, `LaunchArea.tsx` → `LaunchArea` |
| TS Hooks | `use{CamelCase}`: `useRunFromPath`, `useRunSubscription` |
| TS Types | `backend/resources/ts/types/api.ts` — centralized API types |
| Routes | `/api/{resource}` RESTful, `/api/runs/{uuid}/stream` SSE |
| ADRs | `doc/adr/NNNN-kebab-case-title.md` |
