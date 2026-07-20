# Conventions

Code style, patterns, error handling, and conventions for ai-flow.

> Source of truth: `AGENTS.md` (repo root) + `doc/adr/` (24 ADRs). This document summarizes the enforced conventions.

## PHP / Laravel

### Style

- **PSR-12** via Laravel Pint (`./vendor/bin/pint`). CI fails on `pint --test` violations.
- **Explicit return types** on all methods.
- **No nested ternaries** — prefer `match` or early returns.

### Patterns

| Pattern | Where | Example |
|---------|-------|---------|
| Form Requests for validation | `app/Http/Requests/` | `StoreUserLauncherRequest`, `UpdateUserLauncherRequest` |
| API Resources for JSON | `app/Http/Resources/` | `RunResource`, `LauncherResource` |
| Contracts + container binding | `app/Contracts/` + `AppServiceProvider` | `AIProviderInterface`, `LauncherSource` |
| Jobs for slow/IO work | `app/Jobs/` | `ExecuteLauncherJob` (GitHub + AI calls never in HTTP cycle) |
| Thin routes | `routes/api.php` | Controllers delegate to services/jobs |
| Singletons for registries | `AppServiceProvider::register()` | `AiProviderRegistry`, `LauncherMetaService` |

### Launchers

- One class per workflow under `app/Launchers/`, extending `BaseLauncher`.
- Metadata via `BaseLauncher::make($slug, $name, $description, $inputType, $prompt)`.
- Shared `outputSchema()` in `BaseLauncher` (all built-in launchers use the same schema: `summary`, `risk`, `findings`, `verification_steps`).
- Seeded in `DatabaseSeeder` — `Launcher::updateOrCreate(['slug' => ...], [...])`.
- New built-in launcher = PHP class + `DatabaseSeeder` entry + feature test.
- Custom launchers: created by authenticated users via API, stored in `user_launchers` (separate table).

### Error handling

| Error type | Pattern | Log level |
|-----------|---------|-----------|
| User/input errors | `UserFacingRunException` | `warning` (Sentry ignores) |
| Network failures | `ConnectionException` → `markFailed()` | `warning` |
| AI operational errors | "API key not configured", "Invalid API key", "Unable to reach AI provider" | `warning` |
| Unexpected errors | `Throwable` → `markFailed()` + `Sentry\captureException()` | `error` |
| Run failure lifecycle | `Run::markFailed()` — single owner | configurable (`error`/`warning`/`info`) |

### Production guards (`AppServiceProvider::boot`)

Throws `RuntimeException` in production (HTTP only) when:
- `DB_CONNECTION=sqlite` (must use Postgres/MySQL)
- pgsql host has `.` but no TLS (`DB_SSLMODE` not `require`/`verify-ca`/`verify-full`)
- `QUEUE_CONNECTION=sync` (must use database/redis)
- Warns when `LOG_LEVEL=debug` in production
- Warns when `CREDENTIAL_ENCRYPTION_KEY` unset (BYOK falls back to `APP_KEY`)

## React / TypeScript

### Style

- **Functional components + hooks** only (exception: `ErrorBoundary.tsx` is a class component — the only one in the tree).
- **Strict mode** (`tsconfig.json` `strict: true`, `allowJs: false`).
- **Avoid broad `any`**.
- **Lint/format**: oxlint + oxfmt (config at repo root `.oxlintrc.json`/`.oxfmtrc.json`). No Prettier.
  - `correctness: error`, `no-console: error`.
- **Structural lint**: `konsistent` (`konsistent.json`):
  - `components/*.tsx` must export a PascalCase component matching the filename.
  - `hooks/*.ts` must export `use*` functions.
  - `ErrorBoundary.tsx` is the only allowed class component.
- **Pin versions**: React/Vite/`lucide-react` pinned (no `^` for core deps).

### Folder structure

| Folder | Convention |
|--------|-----------|
| `components/` | PascalCase `.tsx`, export matching component |
| `hooks/` | `use*.ts`, export `use*` function |
| `services/` | API clients (`run.ts`, `auth.ts`, `userLaunchers.ts`) |
| `lib/` | Utilities (`http.ts`, `logger.ts`, `decode.ts`, etc.) |
| `types/` | Shared types (`api.ts` — `RunStatus` synced with `Run::STATUSES`) |
| `data/` | Static data (`launcherMeta.ts`) |

### Frontend-backend sync

- `Run::STATUSES` (`queued`, `running`, `completed`, `failed`) is kept in sync with the frontend `RunStatus` enum in `resources/ts/types/api.ts` and the runtime guard `isRunStatus` in `resources/ts/services/run.ts`. Comment in `Run.php` marks this coupling.

### CSS

- Single file: `backend/resources/css/app.css`.
- Uses DESIGN.md design tokens (`var(--ink)`, `var(--orange)`, `var(--success)`, `var(--secondary)`, `var(--line)`, `var(--radius-md)`, etc.).
- No CSS modules, no CSS-in-JS, no Tailwind.

## API conventions

- **Slugs**: `review-pr`, `plan-issue`, `explain-repository`, `laravel-doctor` (built-in); authenticated users create custom slugs via `POST /api/user/launchers`.
- **Aliases**: `/api/flows` = `/api/launchers`, `/api/executions` = `/api/runs` (backward compat).
- **Run lifecycle**: `POST /api/runs` returns **202** + UUID; status at `GET /api/runs/{uuid}`; progress via SSE `GET /api/runs/{uuid}/stream` (DB-polled, ~55s window).
- **No synchronous OpenAI/GitHub calls in the HTTP cycle** — always queued.
- **Provider keys**: never stored on runs, never logged. User-supplied HTTPS GitHub URLs only.

## Git conventions

- Git remotes: `origin` = `github.com/jellydn/ai-flow`, `dokku` = staging deploy target.
- After rebasing a feature branch onto `main`, use `git push --force-with-lease` (never `--force`).
- Commit messages: conventional commits (`feat:`, `fix:`, `refactor:`, `docs:`, `chore:`, `test:`).
