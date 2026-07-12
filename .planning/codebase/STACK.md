# Technology Stack

**Analysis Date:** 2026-07-12

## Languages

**Primary:**
- JavaScript (ES Modules, `"type": "module"`) — Frontend in `src/`
- PHP 8.2+ — Backend in `backend/`

**Secondary:**
- HTML — `index.html` entry point (root), `backend/resources/views/` (Laravel Blade)
- CSS — `src/styles.css` (root, frontend), `backend/resources/css/` (Laravel)
- SQL — Database migrations in `backend/database/migrations/`
- JSON — Configuration, schemas, API payloads

## Runtime

**Environment:**
- Node.js — Vite dev server for frontend
- PHP CLI + built-in server — Laravel backend (`php artisan serve`)

**Package Managers:**
- npm — Frontend dependencies (root `package.json`)
- Composer — PHP dependencies (`backend/composer.json`)
- Lockfiles: `package-lock.json` (root), `backend/composer.lock`

## Frameworks

**Core:**
- React (latest, unpinned) — Frontend UI library (functional components, hooks, no TypeScript)
- Vite 5.4.14 — Build tool and dev server with HMR (`--host 0.0.0.0`)
- Laravel ^12.0 — Backend full-stack framework (Eloquent ORM, queue, cache, auth)

**Testing:**
- PHPUnit ^11.5.50 — Backend tests (`backend/phpunit.xml` with Unit + Feature suites, in-memory SQLite)
- Mockery ^1.6 — PHP mocking framework (dev dependency)
- Laravel Pail ^1.2.2 — Dev log viewer (for `composer run dev`)

**Build/Dev Tools:**
- @vitejs/plugin-react 4.3.4 — Vite plugin for React JSX transform (root)
- Laravel Vite Plugin ^2.0 — Vite integration for Laravel (`backend/vite.config.js`)
- Tailwind CSS ^4.0 — CSS utility framework (backend admin/views)
- @tailwindcss/vite ^4.0 — Tailwind Vite plugin (backend)
- Laravel Pint ^1.24 — PHP code style fixer (PSR-12 / Laravel conventions)
- concurrently ^9.0.1 — Run multiple dev processes simultaneously (backend `composer run dev`)
- Axios ^1.11 — HTTP client (backend npm devDependency, for Laravel frontend assets)

## Key Frontend Dependencies

**Critical (root `package.json`):**
- react + react-dom (latest, unpinned) — UI rendering
- lucide-react (latest, unpinned) — SVG icon component library (~24 icons imported)
- vite 5.4.14 — Build tool

**Infrastructure (root):**
- @vitejs/plugin-react 4.3.4 — React JSX transform

## Key Backend Dependencies

**Critical (`backend/composer.json`):**
- laravel/framework ^12.0 — Core framework
- laravel/tinker ^2.10 — REPL for artisan

**Dev (`backend/composer.json require-dev`):**
- phpunit/phpunit ^11.5 — Testing
- laravel/pint ^1.24 — Code style
- laravel/sail ^1.41 — Docker dev environment
- fakerphp/faker ^1.23 — Fake data generation
- mockery/mockery ^1.6 — Test mocking
- nunomaduro/collision ^8.6 — Error handling for CLI

**Backend npm devDependencies (`backend/package.json`):**
- vite ^7.0.7 — Laravel-side Vite
- laravel-vite-plugin ^2.0 — Laravel Vite bridge
- tailwindcss ^4.0 — CSS framework
- @tailwindcss/vite ^4.0 — Tailwind for Vite
- axios ^1.11 — HTTP client
- concurrently ^9.0.1 — Dev process orchestration

## Backend Architecture

**Design Patterns:**
- Controller → Service → Provider — HTTP layer delegates to services
- Contracts/Interfaces — `AIProviderInterface`, `LauncherInterface` for swappable implementations
- Queue Jobs — `ExecuteLauncherJob` for async AI/GitHub processing
- Form Requests — Dedicated classes for HTTP validation (e.g., `StoreRunRequest`)
- API Resources — Dedicated classes for JSON response shaping (e.g., `RunResource`)
- Repository-style Models — `Launcher`, `Run`, `User` Eloquent models

**Directory Layout (`backend/app/`):**
- `Contracts/` — `AIProviderInterface.php`, `LauncherInterface.php`
- `Http/Controllers/` — `RunController.php`
- `Http/Requests/` — Form request validation classes
- `Http/Resources/` — API resource transformers
- `Jobs/` — `ExecuteLauncherJob.php`
- `Launchers/` — 5 workflow classes: `BaseLauncher.php`, `ReviewPullRequestLauncher.php`, `PlanIssueLauncher.php`, `ExplainRepositoryLauncher.php`, `LaravelDoctorLauncher.php`
- `Models/` — `Launcher.php`, `Run.php`, `User.php`
- `Providers/` — `AppServiceProvider.php`
- `Services/` — `OpenAIProvider.php`, `GitHubService.php`, `JsonSchemaValidator.php`

**API Routes (`backend/routes/api.php`):**
- `GET /api/health` — Health check
- `GET /api/launchers` — List active launchers
- `POST /api/runs` — Create a run (throttled: `throttle:runs`)
- `GET /api/runs/{run}` — Get run status/results
- `GET /api/runs/{run}/stream` — SSE stream for live progress

**Database Schema (`backend/database/migrations/`):**
- `users` table — Standard Laravel users
- `cache` + `cache_locks` tables — Database cache driver
- `jobs` + `job_batches` + `failed_jobs` tables — Database queue driver
- `launchers` table — Workflow definitions (slug, name, prompt_template, output_schema, class_name)
- `runs` table — Run instances (UUID pk, launcher FK, source_url, status, progress, result, error)

## Configuration

**Frontend:**
- No `.env` files
- `vite.config.js` — `plugins: [react()]`, `server.allowedHosts: true`
- `index.html` — Client entry with `<script type="module" src="/src/main.jsx">`

**Backend:**
- `.env.example` is absent (the `composer.json` `post-root-package-install` script copies `.env.example` to `.env` during project creation)
- Default env from `composer.json` setup: SQLite database, `APP_KEY` generated
- `config/app.php` — APP_NAME, APP_ENV, APP_DEBUG, APP_URL, timezone UTC
- `config/database.php` — Default SQLite, supports MySQL, MariaDB, PostgreSQL, SQL Server, Redis
- `config/cache.php` — Default database store, supports file/memcached/redis/dynamodb/octane
- `config/queue.php` — Default database connection, supports sync/beanstalkd/SQS/redis
- `config/session.php` — Default database driver, supports file/cookie/memcached/redis/dynamodb/array
- `config/filesystems.php` — Default local disk, supports S3
- `config/logging.php` — Default stack channel (single), supports daily/slack/papertrail/syslog/errorlog
- `config/mail.php` — Default log mailer, supports SMTP/SES/Postmark/Resend/sendmail
- `config/auth.php` — Session guard, Eloquent user provider
- `config/services.php` — OpenAI API (key, model, timeout), GitHub REST API (token), Postmark, Resend, SES, Slack

## Platform Requirements

**Development:**
- Node.js 18+ (Vite 5 requirement)
- PHP 8.2+ with extensions: PDO SQLite, mbstring, JSON, fileinfo, OpenSSL
- Composer 2.x
- SQLite (default dev database) or MySQL/PostgreSQL

**Production (Laravel Cloud):**
- PHP 8.2+ runtime
- Database (SQLite or MySQL via Cloud)
- Queue worker (separate process from web)
- Required env vars: `APP_KEY`, `OPENAI_API_KEY`, `DB_*`, `QUEUE_CONNECTION`
- Recommended: `GITHUB_TOKEN` (for rate limits)
- Optional: `OPENAI_MODEL`, `OPENAI_TIMEOUT`
