# Coding Conventions

**Analysis Date:** 2026-07-13

This is a Laravel 13 + React/TypeScript monorepo. All application code lives under `backend/`; the frontend is bundled inside `backend/` and served by Laravel via Vite. Detailed guidance also lives in `AGENTS.md`.

## Naming Patterns

**Files:**
- PHP files use `PascalCase` class names matching the file name, one class per file, under PSR-4 namespaces mapped in `backend/composer.json` (`App\` -> `backend/app/`).
- Launchers: one class per workflow under `backend/app/Launchers/` (e.g. `backend/app/Launchers/ReviewPullRequestLauncher.php`).
- Contracts/interfaces live under `backend/app/Contracts/` and are suffixed `Interface` (e.g. `backend/app/Contracts/AIProviderInterface.php`).
- Services under `backend/app/Services/`, HTTP layer under `backend/app/Http/{Controllers,Requests,Resources}`, jobs under `backend/app/Jobs/`, models under `backend/app/Models/`, events under `backend/app/Events/`, data objects under `backend/app/Data/`, support classes under `backend/app/Support/`.
- Frontend TypeScript lives under `backend/resources/ts/` split into `components/`, `hooks/`, `data/`, `lib/`, `services/`, `types/`. Entry is `backend/resources/ts/app.tsx`; Blade shell is `backend/resources/views/app.blade.php`.

**Functions / Methods:**
- PHP methods use `camelCase` (e.g. `outputSchema()`, `prepareForValidation()`).
- Launchers expose a `public static function metadata(): array` (see `backend/app/Launchers/BaseLauncher.php`, `backend/app/Launchers/ExplainRepositoryLauncher.php`).
- Console/logging-related closure callbacks are also `camelCase` (rate limiter closures in `backend/app/Providers/AppServiceProvider.php`).

**Variables:**
- `camelCase` for PHP variables and properties; `snake_case` is used for DB column names and JSON array keys returned by services (e.g. `source_url`, `full_name`, `changed_files`).
- Constants use `UPPER_CASE` (e.g. `backend/app/Support/AiProviders.php` defines `public const OPENAI = 'openai';`).

**Types:**
- PHP type declarations are explicit everywhere: property types (`public int $tries`), parameter types, and return types (`array`, `string`, `void`, etc.). See `backend/app/Jobs/ExecuteLauncherJob.php` and `backend/app/Http/Requests/StoreRunRequest.php`.
- PHPDoc type hints are used where generics are needed, e.g. `/** @return list<string> */` in `backend/app/Support/AiProviders.php`.
- Frontend uses TypeScript strict mode (see `backend/tsconfig.json`, `"strict": true`); avoid broad `any`, prefer `unknown` with explicit narrowing.

## Code Style

**Formatting (PHP):**
- **Laravel Pint** (`backend/composer.json` `require-dev`, `laravel/pint` `^1.24`) enforces PSR-12 plus Laravel's opinionated preset. There is **no `pint.json`** in the repo, so Pint runs with its default Laravel preset.
- CI checks style with `./vendor/bin/pint --test` (fails on violations); run `./vendor/bin/pint` locally to auto-fix. See `AGENTS.md`.
- Pre-commit hook runs Pint on all `backend/**/*.php` files via `scripts/hooks/pint.sh` (configured in `.pre-commit-config.yaml`).

**Linting / Formatting (TypeScript):**
- **oxlint** for linting and **oxfmt** for formatting (Rust-based). Config at repo root: `.oxlintrc.json` (plugins `typescript`, `unicorn`, `oxc`; `categories.correctness: "error"`) and `.oxfmtrc.json` (ignores `node_modules/`, `public/`, `vendor/`).
- npm scripts in `backend/package.json`: `lint` (`oxlint -c ../.oxlintrc.json resources/ts && oxfmt -c ../.oxfmtrc.json --check resources/ts`), `lint:ox`, `format` (`oxfmt -c ../.oxfmtrc.json --write`), `format:check`. There is **no ESLint/Prettier** config in the repo.
- Run `npm run format` to fix formatting, not `prettier --write`.
- Pre-commit hooks `oxlint`, `oxfmt`, and `frontend-typecheck` run on `backend/resources/ts/**/*.{ts,tsx}` (`.pre-commit-config.yaml`).

**Frontend structural convention (konsistent):**
- `konsistent` (`backend/package.json` script `konsistent`; config `konsistent.json` at repo root) enforces structural TS rules:
  - `components/*.tsx` must export a PascalCase component matching the filename (`backend/resources/ts/components/{componentName}.tsx`); `ErrorBoundary.tsx` is exempt and must instead export a class `ErrorBoundary`.
  - `hooks/*.ts` must export `use*` functions named after the file (`backend/resources/ts/hooks/{hookName}.ts`).

## Import Organization

**PHP (PSR-4, Pint default grouping):**
- Class imports grouped by Pint; framework/base classes first, then app classes. Example ordering in `backend/app/Jobs/ExecuteLauncherJob.php` (`App\...`, then `Illuminate\...`, then global `InvalidArgumentException`, `Throwable`).
- Global functions/classes (`InvalidArgumentException`, `Throwable`, `RuntimeException`) are referenced unqualified.

**TypeScript:**
- Module resolution is `Bundler` with `allowImportingTsExtensions: true` (`backend/tsconfig.json`); same-origin `/api/*` requests to the Laravel backend.
- No path aliases configured beyond Vite defaults; relative imports used within `backend/resources/ts/`.

## Error Handling

**Patterns:**
- Domain failures are thrown as `RuntimeException` (user-facing safe messages) or `InvalidArgumentException` (e.g. unsupported provider); the safe message is persisted into `runs.error`. The actual exception class is logged server-side but the message is not exposed unless it is an allow-listed `RuntimeException`. See `backend/app/Services/RunExecutor.php` lines 48-53: only `RuntimeException` messages are shown to the user; other `Throwable`s become `'Run failed unexpectedly.'`.
- The job layer `backend/app/Jobs/ExecuteLauncherJob.php` (`failRun()`) catches `InvalidArgumentException` and generic `Throwable` separately, recording `runs.error`, clearing `source_context`, setting `completed_at`, logging via `Log::error`, and dispatching `RunProgressed`.
- `backend/app/Services/OpenAIProvider.php` throws `RuntimeException` with safe messages (`'Invalid API key.'`, `'The AI provider API key is not configured.'`, `'AI provider request failed (HTTP ...)'`, `'AI provider returned invalid JSON.'`).
- `backend/app/Support/AiProviders.php` uses a `match` expression and throws `InvalidArgumentException('Unsupported AI provider.')` in the `default` branch (no nested ternaries).
- `backend/app/Services/GitHubContextFetcher.php` maps GitHub 404/403 responses to `RuntimeException` with safe, specific messages (e.g. `'Repository a/missing was not found or is private.'`).

**No nested ternaries:** The codebase prefers `match` expressions (e.g. `backend/app/Support/AiProviders.php`), early returns, or explicit `if/else`. No nested ternary chains.

**Secrets / sensitive data:** BYOK API keys are encrypted in the queue (`ShouldBeEncrypted` on `ExecuteLauncherJob`) and never logged. Tests assert keys are absent from the queue payload and from logs (`backend/tests/Feature/ExecuteLauncherJobTest.php` `test_byok_failure_does_not_log_api_key`, `test_job_payload_encrypts_byok_secret`).

## Logging

**Framework:** Laravel `Illuminate\Support\Facades\Log`.

**Patterns:**
- Errors are logged with `Log::error($message, ['run_id' => ..., 'exception' => get_class($e)])` — `exception` is the class name only, never the message, to avoid leaking secrets (`backend/app/Services/RunExecutor.php`, `backend/app/Jobs/ExecuteLauncherJob.php`).
- `Log::spy()` is used in tests to assert a key is never present in the message or context (`backend/tests/Feature/ExecuteLauncherJobTest.php`).
- Production hardening in `backend/app/Providers/AppServiceProvider.php`: warns when `LOG_LEVEL=debug` in production and throws if SQLite is used as the production DB or PostgreSQL lacks TLS.

## Comments

**When to Comment:**
- Docblocks are used sparingly, primarily for framework lifecycle methods (`register()`, `boot()` in `backend/app/Providers/AppServiceProvider.php`) and to explain non-obvious production guards.
- Inline comments explain *why* (BYOK encryption note, SQLite/TLS production safety checks). Tests include explanatory comments for non-obvious assertions (e.g. `backend/tests/Unit/RunStreamerTest.php` explaining the 2-event expectation).

**JSDoc/TSDoc:**
- Not systematically used in the frontend; rely on TypeScript strict type inference. Shared contracts (`Run`, `Launcher`) are typed; SSE uses a polling fallback.

## Function Design

**Size:** Methods are small and single-purpose (e.g. `BaseLauncher::make()` builds metadata; `RunExecutor::progress()` updates progress and dispatches events).

**Parameters:** Constructor injection preferred for services (`backend/app/Services/RunExecutor.php` injects `GitHubService`, `ContextEncoder`, `JsonSchemaValidator`). Public methods take explicit typed args, e.g. `AIProviderInterface::generate(string $prompt, array $schema)`.

**Return Values:** Explicit return types throughout. Launcher `metadata()` returns `array`; services return typed arrays or `void`. Controllers return `JsonResponse` / `JsonResource` / `StreamedResponse`.

## Module Design

**Exports (PHP):**
- One public class/interface per file. Launchers expose a static `metadata()` factory; shared logic is in `BaseLauncher` (`backend/app/Launchers/BaseLauncher.php`) via `protected static function make()` and `outputSchema()`.
- Contracts are bound to concrete implementations in the container: `backend/app/Providers/AppServiceProvider.php` `register()` does `$this->app->bind(RunExecutorInterface::class, RunExecutor::class);`. `AIProviderInterface` is resolved via `app()->make(OpenAIProvider::class, ['apiKey' => ...])` in `backend/app/Support/AiProviders.php`.

**Exports (TypeScript):** Functional components and `use*` hooks; see konsistent conventions above.

**Barrel Files:** None observed; modules imported directly.

## Architectural / Layering Conventions

- **Thin routes:** `backend/routes/api.php` keeps logic in controllers/services/jobs/launchers. Closure routes only for trivial read endpoints; mutating routes point at `RunController`.
- **Form Requests** for HTTP validation: `backend/app/Http/Requests/StoreRunRequest.php` (`rules()`, `prepareForValidation()` to normalize `flow_id`/`input.url` aliases).
- **API Resources** for JSON shaping: `backend/app/Http/Resources/RunResource.php` uses `$this->when(...)` to conditionally include `error`.
- **Jobs for slow/IO work:** `backend/app/Jobs/ExecuteLauncherJob.php` implements `ShouldQueue` + `ShouldBeEncrypted`, `$tries = 2`, `$timeout = 120`; the controller returns **202** and dispatches. No synchronous OpenAI/GitHub calls in the HTTP cycle (`AGENTS.md`).
- **Launchers:** one class per workflow, registered in `backend/database/seeders/DatabaseSeeder.php` via `updateOrCreate` using each launcher's `metadata()`. Shared `outputSchema` is defined once in `BaseLauncher::outputSchema()`.
- **Rate limiting:** defined in `backend/app/Providers/AppServiceProvider.php` via `RateLimiter::for('runs', ...)` (5/hour/IP) and `'runs-stream'` (30/min/IP); applied as `throttle:runs` middleware in `backend/routes/api.php`.
- **API aliases:** `/api/flows` and `/api/executions` mirror `/api/launchers` and `/api/runs` (same contracts), declared in `backend/routes/api.php`.

---

*Convention analysis: 2026-07-13*
