# Technology Stack

**Analysis Date:** 2026-07-12

## Languages
**Primary:** JavaScript/JSX powers the root SPA (`src/main.jsx`, `src/lib/api.js`); PHP 8.3+ powers the API and workers (`backend/composer.json`, `backend/app/`).

**Secondary:** CSS and HTML provide the SPA presentation and entry point (`src/styles.css`, `index.html`); Blade, JavaScript, and CSS remain in the Laravel scaffold (`backend/resources/`). JSON, YAML, XML, and Markdown define packages, CI, tests, and documentation (`package.json`, `backend/composer.json`, `.github/workflows/ci.yml`, `backend/phpunit.xml`).

## Runtime
**Environment:** The monorepo has two independently built applications: a browser SPA at the repository root and a Laravel API under `backend/` (`doc/adr/0007-laravel-api-in-backend-subdirectory.md`). CI fixes Node.js 22 and PHP 8.4 (`.github/workflows/ci.yml`); Laravel itself requires PHP `^8.4` (`backend/composer.json`).

**Package Manager:** npm manages both JavaScript dependency trees, locked by `package-lock.json` and `backend/package-lock.json`; Composer manages backend PHP packages through `backend/composer.json` and `backend/composer.lock`.

## Frameworks
**Core:** React 19.2.7 with React DOM renders the root SPA (`package.json`, `src/main.jsx`). Vite 5.4.14 and `@vitejs/plugin-react` 4.3.4 build it (`package.json`, `vite.config.js`). Laravel Framework 12 is the backend framework (`backend/composer.json`), using Eloquent models, HTTP resources/form requests, queued jobs, facades, and streamed responses (`backend/app/Models/`, `backend/app/Http/`, `backend/app/Jobs/ExecuteLauncherJob.php`).

**Testing:** PHPUnit 11.5 provides backend unit and feature testing (`backend/composer.json`, `backend/phpunit.xml`, `backend/tests/`); Mockery supports mocks and Faker supports generated fixtures (`backend/composer.json`). Tests use in-memory SQLite, array cache/session/mail, and a synchronous queue (`backend/phpunit.xml`). No root frontend test framework is declared (`package.json`).

**Build/Dev:** Root scripts expose Vite dev, build, and preview commands (`package.json`). The Laravel development script runs the PHP server, queue listener, Laravel Pail, and its Vite process concurrently (`backend/composer.json`). The backend asset scaffold uses Vite 7, Laravel Vite Plugin 2, Tailwind CSS 4, and Axios (`backend/package.json`, `backend/vite.config.js`); this asset pipeline is separate from the product SPA at the root.

## Key Dependencies
**Critical:** `react`, `react-dom`, and `lucide-react` implement the launcher UI and iconography (`package.json`, `src/main.jsx`). `laravel/framework` provides HTTP, database, cache, queue, validation, and HTTP-client facilities, while `laravel/tinker` provides an interactive shell (`backend/composer.json`). The backend intentionally uses Laravel's HTTP client rather than dedicated OpenAI or GitHub SDK packages (`backend/app/Services/OpenAIProvider.php`, `backend/app/Services/GitHubService.php`).

**Infrastructure:** Database-backed queues and cache are defaults (`backend/config/queue.php`, `backend/config/cache.php`, `backend/.env.example`). Laravel Pint enforces backend formatting, Pail tails logs, Sail offers containerised development, and Collision formats console/test failures (`backend/composer.json`). Queue work is isolated in `ExecuteLauncherJob` with two attempts and a 120-second timeout (`backend/app/Jobs/ExecuteLauncherJob.php`).

## Configuration
**Environment:** Frontend settings are Vite variables: `VITE_API_BASE_URL`, `VITE_PUBLIC_APP_URL`, and optional `VITE_DEMO_MODE` (`.env.example`, `src/lib/api.js`, `src/main.jsx`). Backend application, database, cache, queue, CORS, AI-provider, GitHub, session, filesystem, and logging settings come from `backend/.env.example` and the files under `backend/config/`. AI configuration accepts OpenAI or OpenRouter credentials and any OpenAI-compatible base URL (`backend/config/services.php`).

**Build:** Root Vite enables the React plugin and restricts development hosts to localhost and Amp domains (`vite.config.js`). Backend Vite compiles `backend/resources/css/app.css` and `backend/resources/js/app.js` with Laravel and Tailwind plugins (`backend/vite.config.js`). CI performs `npm ci && npm run build` at root, then Composer installation, migration/seed, Pint check, and PHPUnit in `backend/` (`.github/workflows/ci.yml`).

## Platform Requirements
**Development:** Node.js 22 is the CI baseline (`.github/workflows/ci.yml`); PHP 8.3+, Composer, npm, SQLite, and `mbstring` are required for the backend path (`backend/composer.json`, `.github/workflows/ci.yml`). Local frontend and backend defaults are ports 5173 and 8000 with explicit CORS origins (`.env.example`, `backend/.env.example`, `backend/config/cors.php`). A queue listener/worker is needed for non-synchronous workflow execution (`backend/composer.json`).

**Production:** Laravel Cloud deploys only `backend/`; the root SPA must be hosted separately with SPA fallback (`backend/README.md`, `doc/adr/0007-laravel-api-in-backend-subdirectory.md`). Production requires a durable MySQL or PostgreSQL database, durable cache, a non-`sync` queue, and a worker (`backend/README.md`, `backend/app/Providers/AppServiceProvider.php`). The proxy must support roughly 55-second SSE responses without buffering (`backend/app/Http/Controllers/RunController.php`, `doc/adr/0013-sse-run-stream-via-database-polling.md`).

---
*Stack analysis: 2026-07-12*
