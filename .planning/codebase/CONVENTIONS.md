# Conventions

> Code style, patterns, error handling, and project-specific rules for ai-flow.

## PHP / Laravel

### Style
- **PSR-12** via Laravel Pint (`backend/vendor/bin/pint`). CI runs `pint --test` and fails on violations.
- **Explicit return types** on all methods (e.g., `public function handle(): void`).
- **No nested ternaries** — prefer `match` expressions or early returns.
- Use `filled()` / `blank()` helpers instead of `! empty()` / `empty()` where applicable.

### Validation
- **Form requests** (`app/Http/Requests/Store*Request`, `Update*Request`) for all input validation — controllers stay thin.
- Custom rules in `app/Rules/` (e.g., `PublicHttpUrl`, `JsonObjectSchemaRule`).

### API responses
- **API resources** (`app/Http/Resources/*Resource`) for JSON serialization — never return Eloquent models directly.
- `RunResource`, `UserResource`, `LauncherResource`, `ProviderCredentialResource`, `UserLauncherResource`.

### Service layer
- **Contracts + container binding** for swappable services (`AIProviderInterface`, `LauncherInterface`).
- **Jobs for slow/IO work** — never add synchronous OpenAI/GitHub calls to the HTTP cycle.
- `ExecuteLauncherJob` implements `ShouldBeEncrypted` (byok key encrypted in payload).

### Launchers
- One class per workflow under `app/Launchers/`, extending `BaseLauncher`.
- Metadata via `BaseLauncher::make()` (slug, name, description, prompt_template, input_type, output_schema).
- Seeded in `DatabaseSeeder` via `Launcher::updateOrCreate`.
- New built-in launcher = PHP class + `DatabaseSeeder` entry + feature test.
- Custom launchers created by authenticated users via API → `user_launchers` table (separate from built-in `launchers`).

### Routes
- Thin `routes/api.php` — controllers handle logic.
- Rate limiters defined in `AppServiceProvider::boot()`, applied via `throttle:{name}`.
- Route aliases for backward compat: `/api/flows`=`/api/launchers`, `/api/executions`=`/api/runs`.

### Error handling
- `UserFacingRunException` (`app/Exceptions/`) for user-visible GitHub errors (404 repo, rate limit, auth failure).
- `Run::markFailed()` stores raw failure messages; bot path sanitizes errors for public runs.
- Expected GitHub run failures are NOT reported to Sentry (PR #81).

### Config
- Provider IDs from `AiProviderRegistry::ids()`, not a config array.
- `AI_MODEL` overrides `OPENAI_MODEL`; per-adapter model env vars take precedence.
- `QUEUE_CONNECTION=database` by default; never `sync` in production (boot throws).

## React / TypeScript

### Style
- **Functional components + hooks** only (no class components except `ErrorBoundary`).
- **Strict mode** (`tsconfig.json`: `strict: true`). Avoid broad `any`.
- **oxlint + oxfmt** for lint/format (config at repo root `.oxlintrc.json` / `.oxfmtrc.json`) — NO Prettier.
- **`no-console`** is an oxlint error — no `console.log` in production code.

### File organization
- Entry: `resources/ts/app.tsx`.
- Folders: `components/` (`.tsx`), `data/`, `lib/`, `services/` (API clients), `types/`, `hooks/`.
- `components/*.tsx` must export a PascalCase component matching the filename (konsistent rule).
- `hooks/*.ts` must export `use*` functions (konsistent rule).
- `ErrorBoundary.tsx` must export the `ErrorBoundary` class (konsistent rule).

### Dependencies
- **Pin** React, Vite, `lucide-react` versions (per AGENTS.md).
- Markdown rendering: `react-markdown` + `remark-gfm`.

## Shell scripts

- `set -euo pipefail` at top.
- Cross-platform `sed -i`: detect `OSTYPE == darwin*` for macOS (needs `sed -i ''`), else GNU `sed -i`.
- ANSI color codes use `$'...'` ANSI-C quoting so they render, not print as literal text.

## Git

- **Conventional commits** (release-please generates changelog).
- Git remotes: `origin` = `github.com/jellydn/ai-flow`, `dokku` = staging deploy target.
- After rebasing onto `main`, use `git push --force-with-lease` (never `--force`).
- Pre-commit hooks via prek (`.pre-commit-config.yaml`): trailing-whitespace, end-of-file-fixer, check-yaml, check-added-large-files, composer-validate, pint, frontend-typecheck, oxlint.

## ADRs

Architecture decisions recorded in `doc/adr/` (24 ADRs, see `doc/adr/README.md`). Covers: single-file React app, launcher classes, runs as UUID records, SSE via DB polling, magic link auth, multi-provider registry, BYOK credential encryption, run ownership, super-admin panel, GitHub bot webhook, per-user launchers, etc.
