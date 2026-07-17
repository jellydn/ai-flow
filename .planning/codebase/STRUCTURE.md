# Directory Structure

## Project Layout

```
ai-flow/
├── README.md                  # Project README (polished open-source)
├── AGENTS.md                  # AI coding assistant guide
├── DESIGN.md                  # Visual identity (colors, typography, components)
├── LICENSE                    # MIT
├── justfile                   # Task runner (prek hooks)
├── konsistent.json            # Structural TS conventions
├── .oxlintrc.json             # oxlint configuration
├── .oxfmtrc.json              # oxfmt configuration
├── .pre-commit-config.yaml    # Pre-commit hooks
├── renovate.json              # Renovate dependency bot config
├── .github/                   # CI workflows
│   └── workflows/
│       ├── ci.yml             # Main CI (backend + frontend)
│       ├── deploy-staging.yml # Dokku deploy
│       └── react-doctor.yml   # React Doctor check
├── doc/                       # Documentation
│   └── adr/                   # Architecture Decision Records (22 ADRs)
├── .planning/                 # Codebase documentation
│   └── codebase/              # Codemap output (7 files)
└── backend/                   # Application root (deploy root)
    ├── README.md              # Backend setup and API guide
    ├── composer.json          # PHP dependencies
    ├── package.json           # Node.js dependencies
    ├── vite.config.ts         # Vite configuration
    ├── tsconfig.json          # TypeScript configuration
    ├── vitest.config.ts       # Vitest configuration
    ├── playwright.config.ts   # Playwright E2E configuration
    ├── phpunit.xml            # PHPUnit configuration
    ├── Dockerfile             # Docker build (nginx + PHP-FPM)
    ├── Procfile               # Laravel Cloud process file
    ├── app.json               # Laravel Cloud config
    ├── artisan                # Laravel CLI
    ├── DOKKU_DEPLOY.md        # Dokku deployment guide
    ├── CLOUD_DEPLOY.md        # Laravel Cloud deployment guide
    ├── .env.example           # Environment template
    │
    ├── app/                   # PHP application code (70 files, 4079 lines)
    │   ├── Console/           # Artisan commands
    │   │   └── Commands/
    │   │       ├── ReapStuckRuns.php
    │   │       └── PromoteSuperAdminCommand.php
    │   ├── Contracts/         # Interfaces
    │   │   ├── AIProviderInterface.php
    │   │   └── LauncherInterface.php
    │   ├── Filament/          # Admin panel resources
    │   │   └── Resources/
    │   │       ├── Launchers/
    │   │       └── Users/
    │   ├── Http/              # HTTP layer
    │   │   ├── Controllers/
    │   │   │   ├── RunController.php
    │   │   │   ├── RunHistoryController.php
    │   │   │   ├── ProviderController.php
    │   │   │   ├── ProviderCredentialController.php
    │   │   │   ├── AccountController.php
    │   │   │   ├── LauncherPromptController.php
    │   │   │   ├── TrendingRepositoryController.php
    │   │   │   └── Auth/
    │   │   │       ├── MagicLinkController.php
    │   │   │       └── PasswordAuthController.php
    │   │   ├── Requests/      # Form requests
    │   │   │   ├── StoreRunRequest.php
    │   │   │   ├── StoreProviderCredentialRequest.php
    │   │   │   ├── UpdateProviderCredentialRequest.php
    │   │   │   └── UpsertLauncherPromptRequest.php
    │   │   └── Resources/     # API resources
    │   │       ├── RunResource.php
    │   │       ├── UserResource.php
    │   │       └── ProviderCredentialResource.php
    │   ├── Jobs/              # Queue jobs
    │   │   └── ExecuteLauncherJob.php
    │   ├── Launchers/         # Workflow definitions
    │   │   ├── BaseLauncher.php
    │   │   ├── ReviewPullRequestLauncher.php
    │   │   ├── PlanIssueLauncher.php
    │   │   ├── ExplainRepositoryLauncher.php
    │   │   └── LaravelDoctorLauncher.php
    │   ├── Mail/              # Mailables
    │   │   ├── MagicLinkMail.php
    │   │   └── SuperAdminBootstrapMail.php
    │   ├── Models/            # Eloquent models
    │   │   ├── Run.php
    │   │   ├── User.php
    │   │   ├── Launcher.php
    │   │   ├── LauncherPromptOverride.php
    │   │   └── ProviderCredential.php
    │   ├── Providers/         # Service providers
    │   │   ├── AppServiceProvider.php
    │   │   └── Filament/AdminPanelProvider.php
    │   ├── Rules/             # Custom validation
    │   │   └── PublicHttpUrl.php
    │   ├── Security/          # Encryption
    │   │   └── CredentialCipher.php
    │   ├── Services/          # Business logic (8 classes)
    │   │   ├── BaseAIProvider.php
    │   │   ├── OpenAIProvider.php
    │   │   ├── OpenRouterProvider.php
    │   │   ├── AnthropicProvider.php
    │   │   ├── GeminiProvider.php
    │   │   ├── RunExecutor.php
    │   │   ├── RunStreamer.php
    │   │   ├── GitHubService.php
    │   │   ├── ContextEncoder.php
    │   │   ├── JsonSchemaValidator.php
    │   │   ├── LaunchParameters.php
    │   │   ├── RecentRunSummary.php
    │   │   └── LauncherPromptResolver.php
    │   └── Support/           # Utility classes
    │       └── AiProviderRegistry.php
    │
    ├── config/                # Laravel config (12 files)
    ├── database/              # Migrations, seeders, factories
    ├── routes/                # Route definitions
    │   ├── api.php            # API routes (launchers, runs, providers, user endpoints)
    │   ├── auth.php           # Auth routes (register, login, magic-link, logout)
    │   ├── web.php            # Web routes (SPA catch-all)
    │   └── console.php        # Console routes
    ├── public/                # Public assets
    │   ├── index.php          # Laravel entry point
    │   ├── favicon.svg        # SVG favicon (64x64)
    │   ├── favicon.ico        # Multi-res ICO (16/32/48px)
    │   ├── apple-touch-icon.png # 180x180 Apple touch icon
    │   ├── logo.svg           # Light mode logo
    │   ├── logo-dark.svg      # Dark mode logo
    │   ├── demo.png           # Demo screenshot (1280x800)
    │   └── build/             # Vite build output (gitignored)
    ├── resources/             # Frontend + views
    │   ├── views/
    │   │   └── app.blade.php  # SPA shell
    │   ├── css/
    │   │   └── app.css        # Global styles (no Tailwind)
    │   └── ts/                # React TypeScript app (47 files, 4847 lines)
    │       ├── app.tsx        # Entry point
    │       ├── components/    # React components
    │       ├── hooks/         # Custom hooks (use*)
    │       ├── services/      # API service layer
    │       ├── lib/           # Utility functions
    │       ├── types/         # TypeScript types
    │       └── data/          # Static data
    ├── tests/                 # Test suites
    │   ├── TestCase.php       # Base test case
    │   ├── Unit/              # Unit tests (11 files)
    │   ├── Feature/           # Feature tests (18 files)
    │   └── E2E/               # Playwright E2E (4 files)
    └── storage/               # Logs, cache, compiled views
```

## Key Locations

| What | Where |
|---|---|
| API entry points | `routes/api.php` |
| Run lifecycle | `RunController` → `ExecuteLauncherJob` → `RunExecutor` |
| AI providers | `app/Services/*Provider.php` → `BaseAIProvider` → `AIProviderInterface` |
| Workflow definitions | `app/Launchers/*Launcher.php` → `BaseLauncher` → `LauncherInterface` |
| Validation | `app/Http/Requests/Store*Request.php` |
| JSON responses | `app/Http/Resources/*Resource.php` |
| React entry | `resources/ts/app.tsx` |
| API client | `resources/ts/services/run.ts` |
| SPA shell | `resources/views/app.blade.php` |

## Naming Conventions

| Context | Convention |
|---|---|
| PHP classes | PSR-4: `App\Services\OpenAIProvider` |
| Controllers | `*Controller`, thin, delegate to services/jobs |
| Services | `*Provider`, `*Service`, `*Executor`, `*Registry` |
| Jobs | `Execute*Job`, implements `ShouldQueue` |
| Launchers | `*Launcher` extends `BaseLauncher` |
| Form requests | `Store*Request`, `Update*Request` |
| API resources | `*Resource` extends `JsonResource` |
| React components | PascalCase, filename matches default export |
| Custom hooks | `use*`, in `hooks/` |
| Service files | `*.ts` in `services/` |
| Type definitions | `*.ts` in `types/` |
