# Coding Conventions

**Analysis Date:** 2026-07-15

## Naming Patterns

**Files (PHP):**
- PSR-4 under `backend/app/`: one class per file; name matches class (`RunController.php`, `StoreRunRequest.php`, `ExecuteLauncherJob.php`).
- Launchers: `backend/app/Launchers/{Workflow}Launcher.php` (e.g. `ReviewPullRequestLauncher.php`, `ExplainRepositoryLauncher.php`), extending `BaseLauncher`.
- Contracts in `backend/app/Contracts/`, services in `backend/app/Services/`, API resources in `backend/app/Http/Resources/`.

**Files (TypeScript/React):**
- UI under `backend/resources/ts/`: `components/{PascalCase}.tsx`, `hooks/{useName}.ts`, `services/`, `lib/`, `types/`, `data/`.
- Tests co-located in `backend/resources/ts/components/__tests__/` as `{Component}.test.tsx`.
- E2E under `backend/tests/E2E/flows/*.spec.ts`.

**Functions (PHP):**
- camelCase methods with explicit return types (`public function store(StoreRunRequest $request): JsonResponse`).
- Test methods: `test_{snake_case_description}` or `test{PascalCaseDescription}` (both appear; prefer descriptive names ending in `: void`).

**Functions (TS):**
- camelCase; React components and hooks exported with names enforced by konsistent (see below).
- Hooks must be `use*` and match filename (`useRunSubscription.ts` → `useRunSubscription`).

**Variables:**
- PHP: camelCase for locals/properties; snake_case for DB columns and request keys where Laravel convention applies.
- TS: camelCase; React state via `useState`; avoid broad `any` (strict TS per `AGENTS.md`).

**Types:**
- TS types/interfaces in `backend/resources/ts/types/`; import with `.ts` extension (`import type { Run } from "../types/api.ts"`).
- PHP typed properties, constructor promotion, and interface contracts for swappable services (`AIProviderInterface`, `RunExecutorInterface`).

## Code Style

**Formatting (PHP):**
- **Laravel Pint** (PSR-12): `backend/vendor/bin/pint` to fix; `./vendor/bin/pint --test` for CI/pre-commit (fails on violations).
- Repo root `justfile`: `just pint-check`, `just pint` (both run inside `backend/`).

**Formatting (TypeScript):**
- **oxfmt** via repo-root `../.oxfmtrc.json`; scope `backend/resources/ts`. `npm run format` writes; `npm run format:check` / `npm run lint` checks.
- Ignore: `backend/node_modules/**`, `backend/public/**`, `backend/vendor/**`.

**Linting (TypeScript):**
- **oxlint** with `../.oxlintrc.json`: plugins `typescript`, `unicorn`, `oxc`; category `correctness` → `error`; rule `no-console` → `error` (use `backend/resources/ts/lib/logger.ts` instead).
- `npm run lint` = oxlint + oxfmt `--check` on `resources/ts`.
- **Typecheck** is separate: `npm run typecheck` (`tsc --noEmit`, strict).

**Structural conventions (TypeScript):**
- **konsistent** (`konsistent.json` at repo root): `components/{componentName}.tsx` must export function/class matching PascalCase basename; `hooks/{hookName}.ts` must export `use*` hook matching basename; `ErrorBoundary.tsx` exports class `ErrorBoundary`. Run: `npm run konsistent` from `backend/`.

**PHP/Laravel patterns (`AGENTS.md`):**
- Form requests (`StoreRunRequest`, etc.) for HTTP validation; API resources (`RunResource`) for JSON shapes.
- Thin `routes/api.php`; jobs for slow/IO work (`ExecuteLauncherJob`); contracts + container binding.
- No nested ternaries—prefer `match` or early returns.

## Import Organization

**PHP:**
- Namespace `App\...` per PSR-4; use statements grouped by framework, then app classes (Pint orders imports).

**TypeScript:**
- React and external packages first, then relative imports to `../services/`, `../lib/`, `../types/` with **explicit `.ts` / `.tsx` extensions** (`allowImportingTsExtensions` in `backend/tsconfig.json`).
- Type-only imports: `import type { ... }`.

**Path Aliases:**
- No `@/` alias in `tsconfig`; relative paths from feature folders.

## Error Handling

**PHP:**
- Validation failures: FormRequest rules + `assertJsonValidationErrors` in feature tests.
- Domain/runtime failures: throw `RuntimeException` (or specific exceptions) with **safe user-facing messages** (e.g. OpenAI provider maps 401 to `Invalid API key.` without leaking provider details).
- Job/run failures: persist `status`/`error` on `Run`; log context with `Log::error` / `Log::warning` (no API keys or credentials in logs).
- Never log or persist user `provider.api_key` on runs (asserted in `RunApiTest`).

**TypeScript:**
- Service errors surfaced in UI state; hooks use `logger.warn` / `logger.error` for non-fatal failures (e.g. SSE/polling in `useRunSubscription.ts`).
- `ErrorBoundary` class component for React tree failures; Sentry integration on frontend.

## Logging

**Backend:** Laravel `Illuminate\Support\Facades\Log` for server-side events (`ExecuteLauncherJob`, `RunExecutor`, `ReapStuckRuns`, `AppServiceProvider` production `LOG_LEVEL` warning).

**Frontend:** `backend/resources/ts/lib/logger.ts` — **consola** wrapper; dev verbose, prod info+; `logger.error` forwards to **Sentry**. **Do not use `console.*`** (oxlint `no-console`).

## Comments

**When to Comment:**
- Explain **why** (encryption in queue payload, alias fields in `StoreRunRequest`, logger/Sentry behavior)—not restating obvious code.
- PHPDoc on non-obvious request validation or public API behavior where helpful.

**JSDoc/TSDoc:**
- Used on shared utilities (`logger.ts` usage block); not required on every component.

## Function Design

**Size:** Controllers and launchers stay thin; orchestration in services (`RunExecutor`, `GitHubService`, providers).

**Parameters:** Constructor injection in controllers; job `handle()` receives resolved interfaces; TS components receive explicit props interfaces (`RunHistoryProps`).

**Return Values:** PHP explicit return types on all public methods; TS explicit return types on exported functions where clarity helps; React components return `JSX.Element` implicitly.

## Module Design

**Exports:**
- TS: named exports for components/hooks (`export function RunHistory`); konsistent enforces name ↔ file alignment.
- PHP: one primary class per file; launchers registered via `BaseLauncher::make()` metadata and seeder.

**Barrel Files:**
- Not used heavily; direct imports to concrete modules (`../services/auth.ts`).

---

*Convention analysis: 2026-07-15*
