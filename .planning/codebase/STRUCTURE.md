# Directory Structure

**Analysis Date:** 2026-07-13

## Layout

```
ai-flow/
├── .agents/                    # Agent skills setup
├── .amp/                       # Amp portal config
│   └── portals/
│       └── ai-launcher.json
├── .github/
│   └── workflows/
│       ├── ci.yml              # CI: PHP + frontend checks
│       └── react-doctor.yml    # React codebase analysis
├── .planning/
│   └── codebase/               # Codebase documentation (these files)
├── backend/                    # Laravel application (monorepo root for deploy)
│   ├── app/
│   │   ├── Console/
│   │   │   └── Commands/
│   │   │       └── ReapStuckRuns.php
│   │   ├── Contracts/          # Interfaces (3)
│   │   │   ├── AIProviderInterface.php
│   │   │   ├── LauncherInterface.php
│   │   │   └── RunExecutorInterface.php
│   │   ├── Data/               # DTOs (1)
│   │   │   └── GitHubReference.php
│   │   ├── Events/             # Events (1)
│   │   │   └── RunProgressed.php
│   │   ├── Http/
│   │   │   ├── Controllers/    # 6 controllers
│   │   │   │   ├── Auth/
│   │   │   │   │   └── MagicLinkController.php
│   │   │   │   ├── Controller.php
│   │   │   │   ├── ProviderController.php
│   │   │   │   ├── ProviderCredentialController.php
│   │   │   │   ├── RunController.php
│   │   │   │   └── RunHistoryController.php
│   │   │   ├── Requests/       # Form requests (3)
│   │   │   │   ├── StoreProviderCredentialRequest.php
│   │   │   │   ├── StoreRunRequest.php
│   │   │   │   └── UpdateProviderCredentialRequest.php
│   │   │   └── Resources/      # API resources (3)
│   │   │       ├── ProviderCredentialResource.php
│   │   │       ├── RunResource.php
│   │   │       └── UserResource.php
│   │   ├── Jobs/               # Queue jobs (1)
│   │   │   └── ExecuteLauncherJob.php
│   │   ├── Launchers/          # Workflow definitions (5)
│   │   │   ├── BaseLauncher.php
│   │   │   ├── ExplainRepositoryLauncher.php
│   │   │   ├── LaravelDoctorLauncher.php
│   │   │   ├── PlanIssueLauncher.php
│   │   │   └── ReviewPullRequestLauncher.php
│   │   ├── Mail/               # Mailables (1)
│   │   │   └── MagicLinkMail.php
│   │   ├── Models/             # Eloquent models (5)
│   │   │   ├── Launcher.php
│   │   │   ├── MagicLoginToken.php
│   │   │   ├── ProviderCredential.php
│   │   │   ├── Run.php
│   │   │   └── User.php
│   │   ├── Providers/          # Service providers (1)
│   │   │   └── AppServiceProvider.php
│   │   ├── Services/           # Domain services (10)
│   │   │   ├── AnthropicProvider.php
│   │   │   ├── ContextEncoder.php
│   │   │   ├── GeminiProvider.php
│   │   │   ├── GitHubContextAssembler.php
│   │   │   ├── GitHubContextFetcher.php
│   │   │   ├── GitHubService.php
│   │   │   ├── JsonSchemaValidator.php
│   │   │   ├── OpenAIProvider.php
│   │   │   ├── RunExecutor.php
│   │   │   └── RunStreamer.php
│   │   └── Support/            # Support classes (1)
│   │       └── AiProviders.php
│   ├── bootstrap/
│   │   ├── app.php             # Application bootstrap
│   │   └── providers.php
│   ├── config/                 # Config files (11)
│   │   ├── app.php, auth.php, cache.php, cors.php
│   │   ├── database.php, filesystems.php, logging.php
│   │   ├── mail.php, queue.php, services.php, session.php
│   ├── database/
│   │   ├── factories/
│   │   │   └── UserFactory.php
│   │   ├── migrations/         # 11 migrations
│   │   └── seeders/
│   │       └── DatabaseSeeder.php
│   ├── public/                 # Web root
│   │   └── index.php
│   ├── resources/
│   │   ├── css/
│   │   │   └── app.css         # Plain CSS (BEM-like)
│   │   ├── ts/                 # TypeScript frontend
│   │   │   ├── app.tsx         # Vite entry point
│   │   │   ├── components/     # 13 React components
│   │   │   │   ├── App.tsx, appUiState.ts
│   │   │   │   ├── Dashboard.tsx, ErrorBoundary.tsx
│   │   │   │   ├── Footer.tsx, Header.tsx, Home.tsx
│   │   │   │   ├── LauncherIcon.tsx, Logo.tsx
│   │   │   │   ├── ProviderSettings.tsx
│   │   │   │   ├── Report.tsx, Running.tsx, SignIn.tsx
│   │   │   │   └── RunHistory.tsx
│   │   │   ├── data/
│   │   │   │   └── launcherMeta.ts
│   │   │   ├── hooks/          # 2 custom hooks
│   │   │   │   ├── useRunFromPath.ts
│   │   │   │   └── useRunSubscription.ts
│   │   │   ├── lib/
│   │   │   │   ├── http.ts     # Fetch wrapper
│   │   │   │   └── scroll.ts
│   │   │   ├── services/       # 2 API service modules
│   │   │   │   ├── auth.ts
│   │   │   │   └── run.ts
│   │   │   └── types/
│   │   │       └── api.ts      # TypeScript API contracts
│   │   └── views/
│   │       └── app.blade.php   # Blade shell (SPA mount)
│   ├── routes/
│   │   ├── api.php             # API routes (public + auth)
│   │   ├── console.php         # Artisan commands
│   │   └── web.php             # SPA catch-all route
│   ├── storage/                # Logs, cache, sessions
│   └── tests/                  # 16 test files
│       ├── Feature/            # 8 feature tests
│       │   ├── ExecuteLauncherJobTest.php
│       │   ├── MagicLinkAuthTest.php
│       │   ├── ProviderCredentialApiTest.php
│       │   ├── ReapStuckRunsTest.php
│       │   ├── RunApiTest.php
│       │   ├── RunHistoryTest.php
│       │   └── RunOwnershipTest.php
│       ├── Unit/               # 8 unit tests
│       │   ├── CacheRunProgressedVersionTest.php
│       │   ├── ContextEncoderTest.php
│       │   ├── CredentialCipherTest.php
│       │   ├── GitHubContextAssemblerTest.php
│       │   ├── GitHubContextFetcherTest.php
│       │   ├── GitHubServiceTest.php
│       │   ├── OpenAIProviderTest.php
│       │   └── RunStreamerTest.php
│       └── TestCase.php
├── doc/
│   └── adr/                    # Architecture Decision Records (14)
│       ├── README.md
│       ├── 0001-vite-react-prototype-before-laravel-backend.md
│       ├── 0002-single-file-react-app-for-mvp-ui.md
│       ├── 0003-client-side-simulated-workflow-execution.md
│       ├── 0004-structured-report-ux-not-chat.md
│       ├── 0005-workflow-catalog-as-declarative-metadata.md
│       ├── 0006-amp-portal-for-preview-hosting.md
│       ├── 0007-laravel-api-in-backend-subdirectory.md
│       ├── 0008-queue-backed-execute-launcher-job.md
│       ├── 0009-launcher-classes-seeded-to-database.md
│       ├── 0010-github-rest-context-with-cache-no-clone.md
│       ├── 0011-ai-provider-interface-openai-json-schema.md
│       ├── 0012-runs-as-uuid-records-with-json-columns.md
│       ├── 0013-sse-run-stream-via-database-polling.md
│       └── 0014-api-throttling-and-public-unauthenticated-runs.md
├── scripts/
│   └── hooks/                  # Pre-commit hook scripts
│       ├── composer-validate.sh
│       ├── ensure-composer.sh
│       ├── env.sh
│       ├── npm-in-backend.sh
│       └── pint.sh
├── AGENTS.md                   # AI agent instructions
├── LICENSE
├── README.md
├── justfile
├── konsistent.json
├── renovate.json
└── .pre-commit-config.yaml
```

## Key Locations

| What | Where |
|------|-------|
| Application entry (HTTP) | `backend/public/index.php` |
| Application bootstrap | `backend/bootstrap/app.php` |
| SPA entry (TypeScript) | `backend/resources/ts/app.tsx` |
| Blade shell | `backend/resources/views/app.blade.php` |
| API routes | `backend/routes/api.php` |
| Web routes | `backend/routes/web.php` |
| AI provider interfaces | `backend/app/Contracts/AIProviderInterface.php` |
| Workflow definitions | `backend/app/Launchers/` |
| Domain logic | `backend/app/Services/` |
| Queue jobs | `backend/app/Jobs/ExecuteLauncherJob.php` |
| Database migrations | `backend/database/migrations/` |
| Tests | `backend/tests/Feature/` and `backend/tests/Unit/` |
| Architecture decisions | `doc/adr/` |
| Agent instructions | `AGENTS.md` |
| Project planning docs | `.planning/codebase/` |

## File Counts

| Type | Count |
|------|-------|
| PHP application files | 44 |
| TypeScript/TSX files | 23 |
| Test files (PHP) | 16 |
| Migration files | 11 |
| Configuration files | 11 |
| ADR documents | 14 |
| React components | 13 |

## Naming Conventions

- **Controllers:** PascalCase, suffixed with `Controller` (e.g., `RunController`, `ProviderCredentialController`).
- **Models:** PascalCase, singular (e.g., `Run`, `Launcher`, `User`, `ProviderCredential`).
- **Services:** PascalCase, descriptive (e.g., `RunExecutor`, `GitHubService`, `JsonSchemaValidator`).
- **Jobs:** PascalCase, descriptive verb (e.g., `ExecuteLauncherJob`).
- **Contracts:** PascalCase, suffixed with `Interface` (e.g., `AIProviderInterface`).
- **Launchers:** PascalCase, descriptive workflow name suffixed with `Launcher` (e.g., `ReviewPullRequestLauncher`).
- **Migrations:** `YYYY_MM_DD_HHMMSS_descriptive_snake_case.php`.
- **React components:** PascalCase, filename matches default export (enforced by `konsistent`).
- **React hooks:** camelCase, `use*` prefix, filename matches export (enforced by `konsistent`).
- **CSS:** BEM-like classes (e.g., `.header-cta`, `.auth-card`, `.run-item`).

---

*Structure analysis: 2026-07-13*
