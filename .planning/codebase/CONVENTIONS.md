# Coding Conventions

**Analysis Date:** 2026-07-12

## Project Structure

Monorepo with two distinct codebases:

| Layer | Path | Stack |
|-------|------|-------|
| Frontend (React UI) | repo root | Vite + React app in `src/` |
| Backend (API) | `backend/` | Laravel 12, PHP 8.2+ |
| Infrastructure | `doc/`, `.amp/`, `.planning/` | ADRs, Amp portal config, planning docs |

---

## Frontend Conventions (Vite + React)

### File Organization

- **Main UI module** -- page components remain in `src/main.jsx`; API, workflow data, scrolling, and the error boundary are extracted into focused modules
- **CSS** in `src/styles.css` (~84 lines, flat BEM-like classes)
- **Entry point** -- `index.html` at repo root with `<script type="module" src="/src/main.jsx">`
- **Supporting directories** -- `src/components/` contains shared UI boundaries, `src/data/` contains workflow/demo data, and `src/lib/` contains API and browser helpers
- **No test files** in `src/` (no test framework configured)

### Naming Patterns

- **Files:** kebab-case for config files (`vite.config.js`, `package-lock.json`)
- **Components:** PascalCase functional components (`App`, `Home`, `Running`, `Report`, `Logo`, `WorkflowIcon`)
- **Variables/state:** camelCase (`selected`, `setUrl`, `activeWorkflow`, `mobileOpen`)
- **CSS classes:** flat kebab-case with BEM-lite modifiers (`.url-box.has-error`, `.timeline-row.complete`, `.nav.open`)

### Code Style & Formatting

- **No formatter** -- no Prettier, no ESLint, no `.editorconfig` at root
- **Manual formatting only** -- inconsistent spacing observed (sometimes spaces around JSX braces, sometimes not)
- **No TypeScript** -- plain `.jsx` files with no type annotations
- **No prop-types** -- no runtime validation of props

### Import Organization (observed in `src/main.jsx`)

```javascript
// 1. Framework imports (React hooks, createRoot)
import React, { useEffect, useMemo, useState } from 'react'
import { createRoot } from 'react-dom/client'
// 2. Third-party libraries (lucide-react icons)
import { ArrowRight, BookOpen, Bot, ... } from 'lucide-react'
// 3. CSS
import './styles.css'
```

### Component Patterns

- **Named function declarations** for all components (not arrow functions, not `export default`)
- **Single default export** at the end via `createRoot(document.getElementById('root')).render(<App />)`
- **Props destructured inline** in function signature
- **No prop types** -- no TypeScript, no PropTypes
- **Hooks used:** local `useState`, `useEffect`, and `useMemo`; no external state library
- **Inline conditional rendering** with `&&` and ternary operators
- **JSX:** no fragment shorthand (`<>...</>`) used, explicit `<main>`, `<section>`, `<div>` wrappers
- **Event handlers:** inline arrow functions (`onClick={() => ...}`) -- no extracted handler functions

### Error Handling (Frontend)

- **URL validation:** regex check in `launch()` function, sets `error` state string
- **Error display:** conditional `<p className="input-error">` rendered when `error` is non-empty
- **API errors:** async API and run-restoration failures are caught and surfaced through UI error state
- **Clipboard API:** optional chaining (`navigator.clipboard?.writeText(...)`) -- only defensive null-check

### CSS Conventions

- **Imported Google Fonts:** DM Mono, Manrope, Playfair Display (italic only)
- **CSS custom properties** for palette (`--orange`, `--ink`, `--muted`, `--line`)
- **Flat class names** with BEM-style modifiers (`.has-error`, `.active`, `.selected`, `.complete`, `.current`)
- **Responsive via one media query** at `800px` breakpoint
- **No CSS modules** or CSS-in-JS -- all styles global in single file
- **No CSS preprocessor** -- plain CSS only

### State Management

- **Local component state only** via `useState` in `App` component, passed down as props
- **No context API, no Redux, no external state library**
- **View routing** via `view` state variable (`'home'`, `'running'`, `'report'`) -- no React Router

---

## Backend Conventions (Laravel / PHP)

### File Organization

- Standard Laravel 12 structure under `backend/`
- **PSR-4 autoloading:** `App\` -> `app/`, `Database\` -> `database/`, `Tests\` -> `tests/`
- **Contracts** in `app/Contracts/` for swappable services (`AIProviderInterface`, `LauncherInterface`)
- **Services** in `app/Services/` for implementation (`OpenAIProvider`, `GitHubService`, `JsonSchemaValidator`)
- **Launchers** in `app/Launchers/` -- one class per workflow, extending `BaseLauncher`
- **Jobs** in `app/Jobs/` -- single `ExecuteLauncherJob` for async AI execution
- **Models** in `app/Models/` -- `Run`, `Launcher`, `User`
- **Requests** in `app/Http/Requests/` -- `StoreRunRequest` with validation rules
- **Resources** in `app/Http/Resources/` -- `RunResource` for JSON shape
- **Routes** in `routes/api.php` (thin, logic delegated to controller)
- **Migrations** in `database/migrations/` -- standard Laravel timestamp naming
- **Seeders** in `database/seeders/` -- `DatabaseSeeder` iterates launcher classes

### Naming Patterns

- **Classes:** PascalCase (`RunController`, `ExecuteLauncherJob`, `StoreRunRequest`)
- **Methods/functions:** camelCase (`store()`, `show()`, `stream()`, `handle()`, `progress()`)
- **Variables:** camelCase (`$run`, `$launcher`, `$input`, `$context`)
- **Database columns:** snake_case (`source_url`, `prompt_template`, `class_name`, `started_at`)
- **Routes/endpoints:** kebab-case (`/api/runs/{run}/stream`), slugs (`review-pr`, `laravel-doctor`)
- **Table names:** snake_case plural (`launchers`, `runs`)
- **Foreign keys:** snake_case with `_id` suffix (`launcher_id`)
- **Laravel Pint:** available in `require-dev`, run locally after PHP edits, and enforced by CI with `./vendor/bin/pint --test`

### Code Style & Formatting

- **EditorConfig** configured at `backend/.editorconfig`:
  - `charset = utf-8`, `end_of_line = lf`, `indent_style = space`, `indent_size = 4`
  - `insert_final_newline = true`, `trim_trailing_whitespace = true`
  - YAML files: `indent_size = 2`, compose.yaml: `indent_size = 4`
- **Laravel Pint** available for PSR-12/Laravel style enforcement (`./vendor/bin/pint`)
- **Explicit return types** on methods (observed: `: void`, `: bool`, `: array`, `: JsonResponse`, `: JsonResource`, `: StreamedResponse`)

### Contract & Service Layer

- **Interface segregation:** `AIProviderInterface` defines `generate(string $prompt, array $schema): array`
- **Dependency injection** in service provider: `$this->app->bind(AIProviderInterface::class, OpenAIProvider::class)`
- **Constructor injection** in jobs: `public function __construct(public string $runId) {}`
- **Method injection** in job handler: `public function handle(GitHubService $github, AIProviderInterface $ai, JsonSchemaValidator $validator)`

### Route Patterns

```php
// routes/api.php -- thin routes, logic in controllers
Route::get('/health', fn () => ...);
Route::post('/runs', [RunController::class, 'store'])->middleware('throttle:runs');
Route::get('/runs/{run}', [RunController::class, 'show']);
Route::get('/runs/{run}/stream', [RunController::class, 'stream'])->middleware('throttle:runs-stream');
```

### Validation

- **Form request classes** for HTTP validation (`StoreRunRequest`)
- Rules returned as array from `rules()` method
- **Explicit authorization** in `authorize()` method (returns `true` currently)
- **Route-model binding** for `Run` model lookup (`{run}` parameter)

### Error Handling (Backend)

- **Domain failures as `RuntimeException`** with safe user-facing messages
- **Try/catch in queue jobs** -- catches `Throwable`, logs error details with `Log::error()`
- **Unprocessable entity responses** (422) for validation failures (Laravel default)
- **404** for missing launcher slugs via `firstOrFail()`
- **429** for rate-limited requests via `RateLimiter::for('runs', ...)`

### Logging

- **Laravel logging** configured via `config/logging.php`
- Job failures logged with structured context: `Log::error('Launcher run failed', ['run_id' => $run->id, 'exception' => get_class($e)])`
- No `console.log` equivalent in backend -- all logging through Laravel

### Database / Migrations

- **SQLite for local/testing**, configurable for production
- **JSON columns** for flexible data (`output_schema`, `progress`, `input`, `source_context`, `result`)
- **UUID primary keys** on `runs` table via `HasUuids` trait
- **Timestamp columns** for tracking (`started_at`, `completed_at`)
- **JSON casting** on models: `protected function casts(): array { return ['field' => 'array', ...]; }`
- **Foreign key constraints** with `cascadeOnDelete`

### Queues / Jobs

- **Synchronous in tests** (`QUEUE_CONNECTION=sync`)
- **Async in production** via separate queue worker
- Job has `$tries = 2` and `$timeout = 120` properties
- `ShouldQueue` interface + `Queueable` trait
- Events dispatched on progress and completion (`RunProgressed`)

### Launcher Metadata

- Each launcher class implements `LauncherInterface` with static `metadata(): array`
- `BaseLauncher::make()` returns `compact('slug', 'name', 'description', 'inputType', 'prompt') + ['outputSchema' => ...]`
- Shared `outputSchema` (JSON Schema) in `BaseLauncher::outputSchema()`

---

## Cross-Cutting

### Git Practices

- `.gitignore` covers `node_modules/`, `dist/`, `.DS_Store`, `backend/` runtime artifacts (`vendor/`, `.env`, `storage/*.key`, `database/*.sqlite`)
- Root `.gitignore` handles both frontend and backend ignores
- Amp git remote (`origin`) + GitHub remote (`github`) -- dual remote setup

### Dependency Management

- **Frontend:** npm with `package.json` at root; dependency versions are pinned
- **Backend:** Composer with `composer.json` in `backend/`; PHP 8.2+, Laravel 12, PHPUnit 11.5
- **Frontend lockfile:** `package-lock.json` is committed and CI installs it with `npm ci`

### Documentation Patterns

- **ADRs** in `doc/adr/` with `README.md` index
- **PHPDoc** comments used sparingly (mostly on public methods, e.g., `register()`, `boot()`, `run()`)
- **No JSDoc** on frontend -- minimal to no comments in JSX
