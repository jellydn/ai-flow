# Structure

**Analysis Date:** 2026-07-14

## Top-Level Layout

```
ai-flow/                          # Monorepo root
├── backend/                      # Laravel application (deploy root)
├── doc/                          # ADRs and privacy docs
├── .planning/                    # Codebase analysis documents
├── scripts/                      # Pre-commit hooks
├── .github/                      # CI workflows + deploy
├── AGENTS.md                     # AI agent instructions
├── README.md                     # Product/marketing README
├── justfile                      # Task runner commands
├── .pre-commit-config.yaml       # Pre-commit hook config (prek)
├── .oxlintrc.json                # oxlint config
├── .oxfmtrc.json                 # oxfmt config
├── konsistent.json               # TS structural convention config
└── renovate.json                 # Dependency automation
```

## Backend Structure (`backend/`)

```
backend/
├── app/
│   ├── Console/Commands/
│   │   └── ReapStuckRuns.php              # Scheduled: reap orphaned running runs
│   ├── Contracts/
│   │   ├── AIProviderInterface.php        # generate(), verifyCredential(), id(), models()
│   │   ├── LauncherInterface.php          # metadata()
│   │   └── RunExecutorInterface.php       # execute()
│   ├── Data/
│   │   └── GitHubReference.php            # Immutable DTO: owner/repo/type/number
│   ├── Events/
│   │   └── RunProgressed.php              # Dispatched on every run state change
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Auth/MagicLinkController.php  # request/verify/logout
│   │   │   ├── AccountController.php         # DELETE /api/user/account
│   │   │   ├── ProviderController.php        # GET /api/providers
│   │   │   ├── ProviderCredentialController.php  # CRUD + verify + make-default
│   │   │   ├── RunController.php             # store/show/stream
│   │   │   ├── RunHistoryController.php      # list/show/retry/destroy
│   │   │   └── Controller.php               # Base controller
│   │   ├── Requests/
│   │   │   ├── StoreRunRequest.php          # Validates launcher+URL+provider+credential
│   │   │   ├── StoreProviderCredentialRequest.php
│   │   │   └── UpdateProviderCredentialRequest.php
│   │   └── Resources/
│   │       ├── RunResource.php              # JSON shape for runs
│   │       ├── ProviderCredentialResource.php  # Masked key, no plaintext
│   │       └── UserResource.php
│   ├── Jobs/
│   │   └── ExecuteLauncherJob.php           # ShouldQueue + ShouldBeEncrypted
│   ├── Launchers/
│   │   ├── BaseLauncher.php                 # Abstract: shared outputSchema + make()
│   │   ├── ReviewPullRequestLauncher.php
│   │   ├── PlanIssueLauncher.php
│   │   ├── ExplainRepositoryLauncher.php
│   │   └── LaravelDoctorLauncher.php
│   ├── Listeners/
│   │   └── CacheRunProgressedVersion.php    # Caches run version for SSE diffing
│   ├── Mail/
│   │   └── MagicLinkMail.php                # Mailable: magic-link sign-in email
│   ├── Models/
│   │   ├── Launcher.php                     # Eloquent: launchers table
│   │   ├── Run.php                          # Eloquent: runs table (UUID key)
│   │   ├── User.php                         # Authenticatable, passwordless
│   │   └── ProviderCredential.php           # UUID, encrypted key, auto-deselect is_default
│   ├── Policies/
│   │   ├── RunPolicy.php                    # Ownership: user can only see/manage their runs
│   │   └── ProviderCredentialPolicy.php     # Ownership: user can only manage their credentials
│   ├── Providers/
│   │   └── AppServiceProvider.php           # Bindings, rate limiters, production guards
│   ├── Security/
│   │   └── CredentialCipher.php             # AES-256 encrypt/decrypt/mask via Crypt facade
│   ├── Services/
│   │   ├── AnthropicProvider.php            # Anthropic Messages API adapter
│   │   ├── ContextEncoder.php               # Bounds context to byte budget
│   │   ├── GeminiProvider.php               # Google Gemini adapter
│   │   ├── GitHubContextAssembler.php       # Shapes raw GitHub data → context array
│   │   ├── GitHubContextFetcher.php         # Raw GitHub REST API calls
│   │   ├── GitHubService.php                # URL parse + cached context fetch
│   │   ├── JsonSchemaValidator.php          # Recursive JSON schema validation
│   │   ├── OpenAIProvider.php               # OpenAI Chat Completions adapter
│   │   ├── OpenRouterProvider.php           # OpenRouter adapter (configurable base URL)
│   │   ├── RunExecutor.php                  # Orchestrates run end-to-end
│   │   └── RunStreamer.php                  # SSE generator polling DB
│   └── Support/
│       ├── AiProviderRegistry.php           # Central provider ID → adapter class map
│       └── AiProviders.php                  # Legacy factory (kept for backward compat)
├── bootstrap/
│   └── app.php                              # Laravel bootstrap: routing, middleware
├── config/
│   ├── app.php, auth.php, cache.php         # Standard Laravel config
│   ├── database.php, queue.php, session.php
│   ├── services.php                         # AI providers, GitHub, mail, Slack config
│   ├── cors.php, logging.php, filesystems.php
│   └── mail.php
├── database/
│   ├── migrations/
│   │   ├── 0001_01_01_000000_create_users_table.php
│   │   ├── 0001_01_01_000001_create_cache_table.php
│   │   ├── 0001_01_01_000002_create_jobs_table.php
│   │   ├── 2026_01_01_000000_create_launchers_and_runs.php
│   │   ├── 2026_07_12_000001_add_runs_created_at_index.php
│   │   ├── 2026_07_12_000002_drop_class_name_from_launchers.php
│   │   ├── 2026_07_13_000001_adapt_users_for_magic_link_auth.php
│   │   ├── 2026_07_13_000002_create_magic_login_tokens_table.php
│   │   ├── 2026_07_13_000003_create_provider_credentials_table.php
│   │   └── 2026_07_13_000004_add_ownership_to_runs_table.php
│   ├── seeders/
│   │   └── DatabaseSeeder.php               # Seeds 4 launchers
│   └── factories/
│       └── UserFactory.php
├── public/
│   └── index.php                            # Laravel front controller
├── resources/
│   ├── css/app.css                          # Plain CSS (BEM-like)
│   ├── views/app.blade.php                  # Blade shell for Vite entry
│   └── ts/                                  # Frontend SPA (see below)
├── routes/
│   ├── api.php                              # API routes + auth group
│   ├── web.php                              # SPA catch-all + auth routes
│   └── console.php                          # Schedule reaper
├── tests/                                   # (see TESTING.md)
├── Dockerfile                               # Dokku/Cloud deploy image
├── Procfile                                 # web + worker processes
├── composer.json, composer.lock
├── package.json, package-lock.json
├── phpunit.xml, tsconfig.json, vite.config.ts
├── .env.example
└── README.md, DOKKU_DEPLOY.md, CLOUD_DEPLOY.md
```

## Frontend Structure (`backend/resources/ts/`)

```
resources/ts/
├── app.tsx                          # Entry: createRoot + Sentry init
├── types/
│   └── api.ts                       # Run, RunResult, Finding, Launcher, RunStatus
├── components/
│   ├── App.tsx                      # Root: useReducer state, auth, launch flow
│   ├── appUiState.ts                # ViewState type, uiStateFromRun, initialAppUiState
│   ├── AppViews.tsx                 # Renders Home/Dashboard/Running/Report/Failed/SignIn
│   ├── Home.tsx                     # Launcher picker + URL input + launch area
│   ├── LaunchArea.tsx               # Provider select + API key + saved credential dropdown
│   ├── LauncherSelector.tsx         # Quick-select pills (max 4)
│   ├── UrlInput.tsx                 # GitHub URL input with validation
│   ├── Running.tsx                  # Progress steps + pulse loader
│   ├── Report.tsx                   # Structured report with findings, checklist, share
│   ├── Dashboard.tsx                # Tabs: Run History, API Keys, Account (deletion)
│   ├── RunHistory.tsx               # Authenticated user's run list + retry/delete
│   ├── ProviderSettings.tsx         # Credential CRUD: form + list + privacy note
│   ├── CredentialForm.tsx           # Add credential: provider + label + key
│   ├── CredentialList.tsx           # List credentials: verify + delete
│   ├── PrivacyNote.tsx              # Encryption explanation
│   ├── SignIn.tsx                   # Magic-link email form
│   ├── Header.tsx                   # Nav + auth button
│   ├── Footer.tsx
│   ├── Logo.tsx                     # Brand mark
│   ├── LauncherIcon.tsx             # Tone-colored icon wrapper
│   ├── ErrorBoundary.tsx            # React error catch
│   └── __tests__/                   # Component tests (see TESTING.md)
├── hooks/
│   ├── useRunSubscription.ts        # EventSource + polling fallback
│   └── useRunFromPath.ts            # Deep-link /runs/{id} resolver
├── services/
│   ├── run.ts                       # HTTP helpers, decoders, URL validation
│   └── auth.ts                      # Auth + credential API + decoders
├── lib/
│   ├── http.ts                      # get/post helpers, error extraction
│   ├── navigate.ts                  # goto() pushState wrapper
│   └── scroll.ts                    # scrollToSelector helper
├── data/
│   └── launcherMeta.ts              # Launcher metadata, demo steps, demo findings
└── test/
    └── setup.ts                     # Vitest setup: @testing-library/jest-dom
```

## Doc Structure (`doc/`)

```
doc/
├── adr/
│   ├── README.md                    # ADR index
│   ├── 0001-vite-react-prototype-before-laravel-backend.md
│   ├── 0002-single-file-react-app-for-mvp-ui.md
│   ├── 0003-client-side-simulated-workflow-execution.md
│   ├── 0004-structured-report-ux-not-chat.md
│   ├── 0005-workflow-catalog-as-declarative-metadata.md
│   ├── 0006-amp-portal-for-preview-hosting.md
│   ├── 0007-laravel-api-in-backend-subdirectory.md
│   ├── 0008-queue-backed-execute-launcher-job.md
│   ├── 0009-launcher-classes-seeded-to-database.md
│   ├── 0010-github-rest-context-with-cache-no-clone.md
│   ├── 0011-ai-provider-interface-openai-json-schema.md
│   ├── 0012-runs-as-uuid-records-with-json-columns.md
│   ├── 0013-sse-run-stream-via-database-polling.md
│   ├── 0014-api-throttling-and-public-unauthenticated-runs.md
│   ├── 0015-magic-link-authentication.md
│   ├── 0016-stored-encrypted-byok-credentials.md
│   ├── 0017-multi-provider-registry.md
│   └── 0018-run-ownership-and-visibility.md
└── PRIVACY.md                       # User-facing privacy documentation
```

## Naming Conventions

| Type | Convention | Example |
|------|-----------|---------|
| PHP class | PascalCase | `RunExecutor`, `OpenRouterProvider` |
| PHP method | camelCase | `verifyCredential()`, `resolveApiKey()` |
| PHP file | PascalCase matching class | `RunController.php` |
| TS component | PascalCase matching filename | `LaunchArea.tsx` exports `LaunchArea` |
| TS hook | `use*` prefix | `useRunSubscription.ts` exports `useRunSubscription` |
| TS service | camelCase | `run.ts`, `auth.ts` |
| TS type | PascalCase | `RunResult`, `ProviderCredential` |
| Migration | `YYYY_MM_DD_HHMMSS_description.php` | `2026_07_13_000004_add_ownership_to_runs_table.php` |
| Test file | `*Test.php` (PHPUnit), `*.test.tsx` / `*.spec.ts` (Vitest) | `OpenRouterProviderTest.php`, `LaunchAreaCredentials.test.tsx` |
| ADR | `NNNN-kebab-case-title.md` | `0017-multi-provider-registry.md` |

## Key File Locations

| What | Where |
|------|-------|
| API routes | `backend/routes/api.php` |
| Container bindings | `backend/app/Providers/AppServiceProvider.php` |
| AI provider registry | `backend/app/Support/AiProviderRegistry.php` |
| Credential encryption | `backend/app/Security/CredentialCipher.php` |
| Run orchestration | `backend/app/Services/RunExecutor.php` |
| Queue job | `backend/app/Jobs/ExecuteLauncherJob.php` |
| SSE streamer | `backend/app/Services/RunStreamer.php` |
| GitHub integration | `backend/app/Services/GitHubService.php` |
| Magic-link auth | `backend/app/Http/Controllers/Auth/MagicLinkController.php` |
| Frontend entry | `backend/resources/ts/app.tsx` |
| Frontend state | `backend/resources/ts/components/appUiState.ts` |
| Frontend HTTP | `backend/resources/ts/services/run.ts`, `auth.ts` |
| Database schema | `backend/database/migrations/` |
| Launcher seeds | `backend/database/seeders/DatabaseSeeder.php` |
| CI config | `.github/workflows/ci.yml` |
| Deploy config | `.github/workflows/deploy-staging.yml`, `backend/Dockerfile` |
