# Codebase Structure

**Analysis Date:** 2026-07-12

## Directory Layout

```
ai-flow/
├── .amp/                          # Amp portal deployment config
│   ├── live-sync.pid
│   └── portals/
├── .planning/                     # Planning and analysis docs
│   └── codebase/                  # Architecture and structure docs
├── backend/                       # Laravel 12 PHP backend (deploy root)
│   ├── app/
│   │   ├── Contracts/
│   │   │   ├── AIProviderInterface.php        # AI provider abstraction
│   │   │   └── LauncherInterface.php          # Launcher metadata contract
│   │   ├── Events/
│   │   │   └── RunProgressed.php              # Event dispatched on run state change
│   │   ├── Http/
│   │   │   ├── Controllers/
│   │   │   │   ├── Controller.php             # Base controller
│   │   │   │   └── RunController.php          # Run CRUD + SSE streaming
│   │   │   ├── Requests/
│   │   │   │   └── StoreRunRequest.php        # Form request with validation rules
│   │   │   └── Resources/
│   │   │       └── RunResource.php            # JSON resource for Run model
│   │   ├── Jobs/
│   │   │   └── ExecuteLauncherJob.php         # Queue job (tries=2, timeout=120)
│   │   ├── Launchers/
│   │   │   ├── BaseLauncher.php               # Abstract base with shared output schema
│   │   │   ├── ExplainRepositoryLauncher.php  # "Explain repository" workflow
│   │   │   ├── LaravelDoctorLauncher.php      # "Laravel doctor" workflow
│   │   │   ├── PlanIssueLauncher.php          # "Plan issue" workflow
│   │   │   └── ReviewPullRequestLauncher.php  # "Review PR" workflow
│   │   ├── Models/
│   │   │   ├── Launcher.php                   # Launcher model (workflow definitions)
│   │   │   ├── Run.php                        # Run model (UUID, JSON columns)
│   │   │   └── User.php                       # Default Laravel user model
│   │   ├── Providers/
│   │   │   └── AppServiceProvider.php         # DI bindings + rate limiter
│   │   └── Services/
│   │       ├── GitHubService.php              # GitHub REST API with cache
│   │       ├── JsonSchemaValidator.php        # Validates AI output against schema
│   │       └── OpenAIProvider.php             # OpenAI chat completions (JSON schema)
│   ├── bootstrap/
│   │   ├── app.php
│   │   ├── cache/
│   │   └── providers.php
│   ├── config/
│   │   ├── app.php
│   │   ├── auth.php
│   │   ├── cache.php
│   │   ├── database.php
│   │   ├── filesystems.php
│   │   ├── logging.php
│   │   ├── mail.php
│   │   ├── queue.php
│   │   ├── services.php               # GitHub token + OpenAI config
│   │   └── session.php
│   ├── database/
│   │   ├── factories/
│   │   ├── migrations/
│   │   │   ├── 0001_01_01_000000_create_users_table.php
│   │   │   ├── 0001_01_01_000001_create_cache_table.php
│   │   │   ├── 0001_01_01_000002_create_jobs_table.php
│   │   │   └── 2026_01_01_000000_create_launchers_and_runs.php
│   │   └── seeders/
│   │       └── DatabaseSeeder.php             # Seeds 4 launcher records
│   ├── public/
│   │   ├── .htaccess
│   │   ├── favicon.ico
│   │   ├── index.php                  # Web server entry point
│   │   └── robots.txt
│   ├── resources/
│   │   ├── css/
│   │   ├── js/
│   │   └── views/
│   │       └── welcome.blade.php
│   ├── routes/
│   │   ├── api.php                    # API route definitions (5 endpoints)
│   │   ├── console.php
│   │   └── web.php                    # Web route (welcome page)
│   ├── storage/
│   │   ├── app/
│   │   ├── framework/
│   │   └── logs/
│   ├── tests/
│   │   ├── Feature/
│   │   │   ├── ExecuteLauncherJobTest.php     # Job integration test
│   │   │   └── RunApiTest.php                 # API endpoint + rate limit test
│   │   ├── Unit/
│   │   └── TestCase.php
│   ├── .editorconfig
│   ├── .gitattributes
│   ├── .gitignore
│   ├── artisan                       # CLI entry point
│   ├── composer.json                 # PHP deps (Laravel 12, PHP ^8.2)
│   ├── composer.lock
│   ├── package.json
│   ├── phpunit.xml
│   ├── README.md
│   └── vite.config.js
├── doc/
│   └── adr/
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
│       ├── 0014-api-throttling-and-public-unauthenticated-runs.md
│       └── README.md                  # ADR index (frontend: 0001-0006, backend: 0007-0014)
├── src/
│   ├── main.jsx                      # Single React app (~390 lines, 6 components)
│   └── styles.css                    # All styles (~84 lines, responsive)
├── .gitignore
├── AGENTS.md                         # AI agent instructions
├── LICENSE
├── README.md                         # Project overview
├── index.html                        # HTML entry point, mounts React app
├── package.json                      # npm config (Vite 5, React, lucide-react)
├── package-lock.json
└── vite.config.js                    # Vite config (React plugin, allowedHosts: true)
```

## Key File Locations

### Entry Points
| File | Purpose |
|------|---------|
| `index.html` | HTML shell, mounts React app at `<div id="root">` |
| `src/main.jsx` | All React components + data + rendering (render call at line 390) |
| `backend/public/index.php` | Laravel web server entry point |
| `backend/artisan` | Laravel CLI entry point |

### Configuration
| File | Purpose |
|------|---------|
| `vite.config.js` | Vite dev server config (host 0.0.0.0, allowedHosts: true) |
| `package.json` | npm scripts (dev, build, preview) + deps (Vite 5.4, React, lucide-react) |
| `backend/composer.json` | PHP deps (Laravel 12, PHP ^8.2) |
| `backend/config/services.php` | GitHub token, OpenAI API key/model/timeout |
| `backend/config/queue.php` | Queue connection config |
| `backend/config/database.php` | Database config |
| `backend/.env.example` | Environment template (copy to `.env`) |

### Core Logic
| File | Purpose |
|------|---------|
| `src/main.jsx` | All frontend application logic (single file, 390 lines) |
| `backend/app/Http/Controllers/RunController.php` | API controller for run CRUD + SSE |
| `backend/app/Jobs/ExecuteLauncherJob.php` | Queue job orchestrating AI workflow |
| `backend/app/Services/GitHubService.php` | GitHub REST API client with caching |
| `backend/app/Services/OpenAIProvider.php` | OpenAI API client with JSON schema |
| `backend/app/Http/Requests/StoreRunRequest.php` | Request validation |
| `backend/app/Http/Resources/RunResource.php` | API response shaping |
| `backend/app/Launchers/BaseLauncher.php` | Abstract launcher with shared schema |
| `backend/app/Launchers/ReviewPullRequestLauncher.php` | PR review workflow definition |
| `backend/app/Launchers/PlanIssueLauncher.php` | Issue planning workflow definition |
| `backend/app/Launchers/ExplainRepositoryLauncher.php` | Repository explanation workflow |
| `backend/app/Launchers/LaravelDoctorLauncher.php` | Laravel audit workflow definition |

### Tests
| File | Purpose |
|------|---------|
| `backend/tests/Feature/RunApiTest.php` | API endpoint + validation + rate limiting tests |
| `backend/tests/Feature/ExecuteLauncherJobTest.php` | Job execution + JSON validation tests |

## Naming Conventions

**Files:**
- kebab-case for config files (`vite.config.js`, `composer.json`)
- PascalCase for React components and PHP classes
- snake_case for PHP filenames (`ExecuteLauncherJob.php`, `store_run_request.php` — though the latter uses StudlyCase in practice)
- CSS classes are BEM-like flat: `.launcher-card`, `.workflow-icon.orange`, `.finding-header`

**Backend Conventions:**
- PSR-12 / Laravel Pint enforced
- Controllers singular (`RunController`)
- Form requests descriptive (`StoreRunRequest`)
- Resources named after model (`RunResource`)
- Contracts suffixed with `Interface` (`AIProviderInterface`)
- Launchers suffixed with `Launcher` (`ReviewPullRequestLauncher`)
- Jobs suffixed with `Job` (`ExecuteLauncherJob`)
- Events past-tense (`RunProgressed`)

**Frontend Conventions:**
- Functional components with hooks
- `useState` for all state (no useReducer or external libraries)
- Props passed directly, no prop drilling depth beyond 1 level
- Inline SVG icons from lucide-react
- CSS-in-JS avoided; all styles in `styles.css`

## Where to Add New Code

### New Frontend Feature
1. **Simple addition:** Extend existing component in `src/main.jsx` (add JSX, state, handler)
2. **New component:** Add a new functional component function in `src/main.jsx` (recommended to eventually extract to separate files)
3. **New style:** Add CSS class rules in `src/styles.css`
4. **New workflow (UI only):** Add entry to `workflows` array in `src/main.jsx`

### New Backend Feature
1. **New API endpoint:** Add route in `backend/routes/api.php`, create controller method in `backend/app/Http/Controllers/`
2. **New launcher/workflow:** Create class in `backend/app/Launchers/` extending `BaseLauncher`, register in `backend/database/seeders/DatabaseSeeder.php`
3. **New service:** Create class in `backend/app/Services/`, bind in `AppServiceProvider` if needed
4. **New model:** Create in `backend/app/Models/`, add migration, optionally create resource/controller
5. **New AI provider:** Implement `AIProviderInterface`, bind in `AppServiceProvider`
6. **Tests:** Add to `backend/tests/Feature/` with `RefreshDatabase` + `Queue::fake()` where appropriate
