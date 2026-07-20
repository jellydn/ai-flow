# Structure

Directory layout, key locations, and naming conventions for ai-flow.

> Deploy root is `backend/`, not the repo root. All PHP/TS source lives under `backend/`.

## Top-level layout

```
.
├── backend/                    # Laravel app (deploy root)
│   ├── app/                    # PHP application code
│   ├── bootstrap/              # App bootstrap (app.php, providers.php)
│   ├── config/                 # Configuration files
│   ├── database/               # Migrations, factories, seeders
│   ├── docker/                 # Docker configs (nginx, supervisor, bin/)
│   ├── public/                 # Document root (index.php, build/)
│   ├── resources/              # Views, CSS, TypeScript
│   ├── routes/                 # API + web routes
│   ├── scripts/                # E2E serve scripts
│   ├── storage/                # Logs, framework cache, app storage
│   ├── tests/                  # PHPUnit + Playwright tests
│   ├── composer.json           # PHP dependencies
│   ├── package.json            # JS dependencies + scripts
│   ├── Dockerfile              # Multi-stage build (node → php-fpm)
│   ├── Procfile                # Dokku process types
│   ├── phpunit.xml             # PHPUnit config
│   ├── vite.config.ts          # Vite config
│   ├── vitest.config.ts        # Vitest config
│   ├── playwright.config.ts    # Playwright config
│   └── tsconfig.json           # TypeScript config
├── .agents/                    # Agent skills (setup)
├── .amp/                       # AMP portal config
├── .github/workflows/          # CI (ci.yml, deploy-staging.yml)
├── doc/adr/                    # Architecture Decision Records (0001–0024)
├── .planning/codebase/         # This codebase map
├── scripts/                    # Git hooks (pint.sh, env.sh, etc.)
├── AGENTS.md                   # AI agent guide
├── DESIGN.md                   # Design system spec
├── konsistent.json             # Structural TS conventions
├── justfile                    # Just command runner
├── .oxlintrc.json              # oxlint config (repo root)
├── .oxfmtrc.json               # oxfmt config (repo root)
└── .pre-commit-config.yaml     # Pre-commit hooks (prek)
```

## Backend PHP (`backend/app/`)

```
app/
├── Console/Commands/
│   ├── PromoteSuperAdminCommand.php
│   └── ReapStuckRuns.php              # Scheduled: reaps stuck "running" runs
├── Contracts/
│   ├── AIProviderInterface.php        # AI provider contract
│   ├── LauncherInterface.php          # Launcher contract
│   └── LauncherSource.php             # Unified Launcher + UserLauncher interface
├── Controllers/
│   ├── Auth/
│   │   ├── MagicLinkController.php
│   │   └── PasswordAuthController.php
│   ├── AccountController.php
│   ├── LauncherController.php
│   ├── LauncherPromptController.php
│   ├── ProviderController.php
│   ├── ProviderCredentialController.php
│   ├── RunController.php
│   ├── RunHistoryController.php
│   ├── TrendingRepositoryController.php
│   └── UserLauncherController.php
├── Data/
│   └── GitHubReference.php            # Typed GitHub URL parse result
├── Events/
│   └── RunProgressed.php              # Dispatched on run status change
├── Exceptions/
│   └── UserFacingRunException.php     # Expected user/input errors (Sentry ignores)
├── Filament/                          # Super-admin panel (Filament 5)
├── Http/
│   ├── Requests/                      # Form requests (Store*Request, Update*Request)
│   └── Resources/                     # API resources (RunResource, etc.)
├── Jobs/
│   └── ExecuteLauncherJob.php         # Queue job: runs a launcher
├── Launchers/
│   ├── BaseLauncher.php               # Abstract base + shared outputSchema()
│   ├── ExplainRepositoryLauncher.php
│   ├── LaravelDoctorLauncher.php
│   ├── PlanIssueLauncher.php
│   └── ReviewPullRequestLauncher.php
├── Listeners/
│   └── CacheRunProgressedVersion.php  # Bumps cache version on RunProgressed
├── Models/
│   ├── Launcher.php                   # Built-in launchers
│   ├── ProviderCredential.php         # Encrypted BYOK credentials
│   ├── Run.php                        # UUID, JSON columns, markFailed()
│   ├── User.php
│   └── UserLauncher.php               # User-created launchers (UUID)
├── Providers/
│   └── AppServiceProvider.php         # Rate limiters, production guards, singletons
├── Services/                          # See ARCHITECTURE.md for details
│   ├── AnthropicProvider.php
│   ├── BaseAIProvider.php
│   ├── ContextBudget.php
│   ├── ContextEncoder.php
│   ├── GeminiProvider.php
│   ├── GitHubService.php
│   ├── GitHubTrendingService.php
│   ├── JsonSchemaValidator.php
│   ├── LaunchParameters.php
│   ├── LauncherMetaService.php
│   ├── LauncherPromptResolver.php
│   ├── LauncherResolutionService.php
│   ├── OpenAIProvider.php
│   ├── OpenRouterProvider.php
│   ├── RecentRunSummary.php
│   ├── RunExecutor.php
│   └── RunStreamer.php
└── Support/
    └── AiProviderRegistry.php         # Singleton: provider registry + key resolution
```

## Frontend TypeScript (`backend/resources/ts/`)

```
resources/ts/
├── app.tsx                        # React entry point
├── types/
│   └── api.ts                     # Shared API types (RunStatus synced with Run::STATUSES)
├── components/
│   ├── App.tsx                    # Root component (routing)
│   ├── AppViews.tsx               # View switching
│   ├── appUiState.ts              # UI state helpers
│   ├── Home.tsx                   # Home page (launcher selector + URL input)
│   ├── Dashboard.tsx              # Authenticated dashboard (tabs)
│   ├── LaunchArea.tsx             # Launcher + URL input + run trigger
│   ├── LauncherSelector.tsx
│   ├── LauncherIcon.tsx
│   ├── LauncherVisibilitySection.tsx  # Built-in launcher show/hide toggle
│   ├── CustomLaunchersSection.tsx     # User custom launcher CRUD
│   ├── WorkflowPromptsSection.tsx     # Per-launcher prompt overrides
│   ├── ProviderSettings.tsx           # API key management (BYOK)
│   ├── CredentialForm.tsx
│   ├── CredentialList.tsx
│   ├── PrivacyNote.tsx
│   ├── Report.tsx                 # Structured report display
│   ├── MarkdownBody.tsx           # Markdown rendering
│   ├── Running.tsx                # Run progress view
│   ├── RunHistory.tsx             # Authenticated run history
│   ├── RecentRunsSection.tsx
│   ├── TrendingCard.tsx
│   ├── SignIn.tsx                 # Auth (password + magic link)
│   ├── Header.tsx
│   ├── Footer.tsx
│   ├── Logo.tsx
│   ├── UrlInput.tsx
│   ├── ErrorBoundary.tsx          # Class component (only one in tree)
│   └── __tests__/                 # Vitest unit tests (7 files)
├── hooks/
│   ├── useRunFromPath.ts          # Extract run UUID from URL
│   └── useRunSubscription.ts      # SSE subscription
├── services/
│   ├── run.ts                     # Run API client + SSE
│   ├── auth.ts                    # Auth API client
│   └── userLaunchers.ts           # Custom launcher API client
├── lib/
│   ├── http.ts                    # Fetch wrapper
│   ├── logger.ts                  # consola logger
│   ├── decode.ts
│   ├── runModels.ts
│   ├── appPaths.ts
│   ├── navigate.ts
│   ├── scroll.ts
│   └── __tests__/runModels.test.ts
├── data/
│   └── launcherMeta.ts            # Static launcher metadata (icons, tones)
└── test/
    └── setup.ts                   # Vitest setup (jsdom + Testing Library)
```

## Routes (`backend/routes/`)

| File | Purpose |
|------|---------|
| `api.php` | JSON API: `/api/runs`, `/api/launchers`, `/api/user/*`, `/api/providers`, aliases (`/flows`, `/executions`) |
| `web.php` | Catch-all SPA route: `Route::view('/{path?}', 'app')` (excludes `api`, `admin`, `build`, etc.) |
| `auth.php` | Auth: register, login, magic-link, logout |
| `console.php` | Scheduled commands: `ReapStuckRuns` every minute (production) |

## Database (`backend/database/`)

```
database/
├── factories/           # Model factories (LauncherFactory, UserLauncherFactory, RunFactory, etc.)
├── migrations/          # Schema migrations (users, launchers, runs, user_launchers, provider_credentials, etc.)
└── seeders/
    ├── DatabaseSeeder.php           # Seeds 4 built-in launchers + super admin
    └── SuperAdminBootstrapSeeder.php
```

## Config (`backend/config/`)

| File | Key concern |
|------|-------------|
| `services.php` | AI providers, GitHub, Resend, mail |
| `credentials.php` | `CREDENTIAL_ENCRYPTION_KEY` for BYOK |
| `super_admin.php` | Bootstrap super admin |
| `database.php` | SQLite local, Postgres production |
| `queue.php` | `database` driver default |
| `auth.php`, `session.php`, `cache.php`, `cors.php`, `logging.php`, `filesystems.php`, `mail.php`, `sentry.php` | Standard Laravel config |

## Tests (`backend/tests/`)

```
tests/
├── TestCase.php                   # Base test case
├── Unit/                          # 10 unit test files (providers, services, validators)
└── Feature/                       # 17 feature test files (API, auth, runs, launchers, etc.)
```

E2E tests: `backend/tests/E2E/flows/*.real.spec.ts` (Playwright, `--project=real-backend`).

## ADRs (`doc/adr/`)

24 Architecture Decision Records (0001–0024) documenting key design decisions. See `doc/adr/README.md` for the index.

## Naming conventions

- **PHP**: PSR-12, PSR-4 autoload (`App\` → `app/`). Controllers `*Controller`, form requests `Store*Request`/`Update*Request`, resources `*Resource`, jobs `*Job`, models PascalCase singular.
- **TS**: functional components + hooks, strict mode. `components/*.tsx` export PascalCase matching filename. `hooks/*.ts` export `use*` functions. `services/*.ts` for API clients. `lib/*.ts` for utilities. `types/*.ts` for shared types. `data/*.ts` for static data.
- **CSS**: `backend/resources/css/app.css` — single file, uses DESIGN.md design tokens (`var(--ink)`, `var(--orange)`, `var(--success)`, etc.).
