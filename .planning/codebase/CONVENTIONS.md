# Coding Conventions

**Analysis Date:** 2026-07-13

## Naming Patterns

**Files:**

- **PHP (PSR-4 under `backend/app/`):** class name matches filename exactly.
  - Controllers: `*Controller.php` (e.g. `RunController.php`, `ProviderCredentialController.php`)
  - Form requests: `Store*Request.php` / `Update*Request.php` (e.g. `StoreRunRequest.php`)
  - API resources: `*Resource.php` (e.g. `RunResource.php`)
  - Jobs: `*Job.php` (e.g. `ExecuteLauncherJob.php`)
  - Launchers: `*Launcher.php` + `BaseLauncher.php`
  - Contracts: `*Interface.php` (e.g. `AIProviderInterface.php`)
  - Policies: `*Policy.php`
  - DTOs: descriptive nouns under `app/Data/` (e.g. `GitHubReference.php`)
  - Services: PascalCase noun phrases under `app/Services/` (e.g. `GitHubContextFetcher.php`)
  - Tests: `*Test.php` in `backend/tests/Feature/` or `backend/tests/Unit/`
- **TypeScript (under `backend/resources/ts/`):**
  - Components: PascalCase `.tsx` matching export name (`RunHistory.tsx` → `export function RunHistory`)
  - Class exception: `ErrorBoundary.tsx` exports class `ErrorBoundary` (see `konsistent.json`)
  - Non-component modules may live beside components (e.g. `appUiState.ts`)
  - Hooks: `use*.ts` in `hooks/` (e.g. `useRunSubscription.ts`)
  - Services / lib: camelCase modules (`run.ts`, `auth.ts`, `http.ts`, `navigate.ts`)
  - Types: domain modules under `types/` (e.g. `api.ts`)
  - Frontend tests: `*.test.tsx` under co-located `__tests__/` (e.g. `components/__tests__/RunHistory.test.tsx`)

**Functions:**

- **PHP:** camelCase methods; explicit return types on public methods (`store(...): JsonResponse`, `toArray(...): array`). Test methods: `test_*` snake_case (`test_run_is_validated_created_and_queued`).
- **TS:** camelCase functions (`fetchRun`, `decodeRun`, `assertObject`); React components and hooks use PascalCase / `use*` respectively. Event handlers often `handle*` or wrapped helpers (`withAction`).

**Variables:**

- **PHP:** camelCase locals (`$providerId`, `$apiKey`); DB columns and JSON API keys use snake_case (`source_url`, `user_id`, `started_at`, `masked_key`).
- **TS:** camelCase locals/state (`isLaunching`, `actioningId`); API-shaped fields stay snake_case to match backend (`source_url`, `started_at`, `completed_at`, `email_verified_at`). Numeric separators used for large literals (`10_000`, `50_000`).

**Types:**

- **PHP:** interfaces with `Interface` suffix; `readonly class` DTOs with promoted constructor props (`GitHubReference`); PHPDoc array shapes on DTO helpers (`@return array{owner: string, ...}`); model constants for enums (`Run::STATUSES`).
- **TS:** `export type` for unions (`RunStatus`), `export interface` for objects (`Run`, `Finding`, `Launcher`); props interfaces often colocated (`interface RunHistoryProps`); strict mode — no broad `any`.

## Code Style

**Formatting:**

- **PHP:** Laravel Pint (`laravel/pint` ^1.24). No project `pint.json` — uses Pint defaults (PSR-12 style). Observed quirks of Pint default: space after unary `!` (`if (! $key)`), trailing commas in multiline arrays, constructor property promotion with empty body `{}` when only promoted props. Check: `./vendor/bin/pint --test`; fix: `./vendor/bin/pint`. CI runs `./vendor/bin/pint --test` in `.github/workflows/ci.yml`.
- **TypeScript:** **oxfmt** (not Prettier). Config: repo-root `.oxfmtrc.json` (ignore patterns for `node_modules`, `public`, `vendor`). Double quotes, trailing commas, 4-space indent in components (observed). Scripts in `backend/package.json`: `npm run format` / `npm run format:check` / lint includes `--check`.

**Linting:**

- **PHP:** Pint is the style gate; PHPUnit for correctness. No PHPStan/Psalm config observed in-repo.
- **TypeScript:** **oxlint** via repo-root `.oxlintrc.json`:
  - Plugins: `typescript`, `unicorn`, `oxc`
  - Categories: `correctness: "error"`
  - Custom `rules` map is empty (defaults only)
- **Structural conventions:** **konsistent** (`konsistent.json` at repo root):
  - `components/{componentName}.tsx` must export function `${componentName}` (PascalCase)
  - `ErrorBoundary.tsx` must export class `ErrorBoundary`
  - `hooks/{hookName}.ts` must export function matching file basename
- **Typecheck:** `tsc --noEmit` with `"strict": true` in `backend/tsconfig.json` (`npm run typecheck`). Build runs typecheck first: `tsc --noEmit && vite build`.

## Import Organization

**Order (TypeScript, observed):**

1. External packages (`react`, `react-dom/client`, `lucide-react`, `vitest`, `@testing-library/*`)
2. Type-only imports (`import type { Run } from "../types/api.ts"`)
3. Internal modules by layer: `data/` → `hooks/` → `services/` → `lib/` → sibling components
4. Relative paths always include the file extension (`.ts` / `.tsx`) — required by `allowImportingTsExtensions` and used consistently

**Order (PHP, observed):**

1. `namespace` declaration
2. `use` statements grouped: `App\...` contracts/models/services, then `Illuminate\...` / framework, then third-party (`Mockery`, `Throwable`, etc.)
3. Class declaration; no unused imports

**Path Aliases:**

- **None configured** in `backend/tsconfig.json` or Vite config — imports are relative (`../services/run.ts`, `./Dashboard.tsx`).
- PHP uses PSR-4: `App\` → `app/`, `Tests\` → `tests/`, `Database\Factories\` → `database/factories/`.

## Error Handling

**Patterns:**

- **HTTP validation:** Form requests (`StoreRunRequest`, `StoreProviderCredentialRequest`) own rules; controllers stay thin. Validation failures return 422 via Laravel (`assertUnprocessable()` / `assertJsonValidationErrors` in tests).
- **Authorization:** Policies (`RunPolicy`, `ProviderCredentialPolicy`) + `$this->authorize(...)` in controllers. Guest-viewable public runs vs owner-only private runs encoded in policy docs/methods.
- **Domain/runtime failures:** `RuntimeException` (and occasionally `InvalidArgumentException`) with **safe, user-facing messages** (e.g. `"Invalid API key."`, never provider raw bodies). Unexpected `Throwable` mapped to generic `"Run failed unexpectedly."` in `RunExecutor`.
- **Jobs:** `ExecuteLauncherJob` catches provider-resolution failures, marks run `failed`, logs structured context, dispatches `RunProgressed`.
- **Frontend:** `lib/http.ts` throws `Error` with server `message`/`error` or status text; abort → timeout message. Services use `assert*` / `decode*` helpers that throw on shape mismatch. Components catch and surface strings in UI state (`setError(...)`); empty `catch` only when intentional (e.g. keep polling).
- **Security:** API keys never persisted on runs; encrypted credentials via `CredentialCipher`; job implements `ShouldBeEncrypted`; errors and logs must not include secrets (covered by tests).

## Logging

**Framework:** Laravel `Illuminate\Support\Facades\Log` (Monolog under the hood). Frontend has **no** `console.log` / `console.error` usage in `resources/ts/`.

**Patterns:**

- Structured context arrays — prefer IDs and exception **class names**, not full exception messages when secrets might leak:
  - `Log::error('Launcher run failed', ['run_id' => $run->id, 'exception' => get_class($e)]);` in `RunExecutor`
  - `Log::error('Launcher run failed during provider setup', ['run_id' => ..., 'exception' => ...]);` in `ExecuteLauncherJob`
  - `Log::warning('Reaped stuck run', [...])` in `ReapStuckRuns`
  - Production guard in `AppServiceProvider` warns if `LOG_LEVEL` is `debug`
- Do **not** log API keys, BYOK secrets, or raw provider error bodies.

## Comments

**When to Comment:**

- Prefer self-documenting names; comments explain **why** or policy intent.
- PHPDoc on contracts (`AIProviderInterface`), policies (ownership rules), and non-obvious model helpers (`isOwned`, `isOwnedBy`).
- Controllers sometimes document endpoint purpose (`/** List the authenticated user's ... */`).
- Frontend: short JSDoc on non-obvious helpers (e.g. `withAction` in `RunHistory.tsx`, mock helpers in tests). Inline comments for intentional empty catches / security-sensitive behavior.

**JSDoc/TSDoc:**

- Sparse; used for non-obvious helpers and test utilities, not on every export.
- PHP: PHPDoc array shapes and `@return list<string>` on interfaces more common than full method docs.

## Function Design

**Size:**

- Controllers stay thin: validate → authorize → create/dispatch → return resource/response.
- Business logic in services (`RunExecutor`, `GitHubService`, providers) and jobs.
- Frontend: focused components; complex UI state via reducers (`uiReducer` in `App.tsx`, `pathReducer` in `useRunFromPath.ts`).

**Parameters:**

- Constructor **property promotion** + DI for services/controllers/jobs (`private RunStreamer $streamer`).
- Jobs: public constructor props for serializable IDs (`public string $runId`); secrets as private constructor args + `ShouldBeEncrypted`.
- Optional/nullable provider and API key on run create: `?string $provider = null`.
- Frontend: props interfaces; callbacks passed down (`navigate: (pathname: string) => void`).

**Return Values:**

- PHP: explicit return types everywhere public; resources return arrays/`JsonResource`; store endpoints return `JsonResponse` with explicit status (202 for runs, 201 for credentials).
- TS: `Promise<T>` on async service functions; decode functions return typed domain objects; hooks return object tuples/records (`{ run, error }`).

## Module Design

**Exports:**

- **Named exports** for React components and hooks (`export function RunHistory`, `export function useRunSubscription`). Default exports not the norm.
- Services export many named functions (`decodeRun`, `createRun`, `getLaunchers`) plus shared assert helpers.
- Types exported from `types/api.ts` and re-exported or defined beside services when domain-specific (`User` in `auth.ts`).
- PHP: one primary class per file; interfaces in `app/Contracts/`; container bindings in `AppServiceProvider`.

**Barrel Files:**

- **Not used** — no `index.ts` re-export barrels under `resources/ts/`. Import concrete module paths with extensions.

**Layering (backend):**

```
routes/api.php, routes/auth.php
  → Controllers + Form Requests + Resources
  → Jobs / Policies / Models
  → Services + Launchers + Security
  → Contracts (interfaces)
```

**Layering (frontend):**

```
app.tsx → components/ → hooks/ + services/ + lib/ + data/ + types/
```

**Launchers:** one class per workflow under `app/Launchers/`, metadata via `BaseLauncher::make()`, seeded in `DatabaseSeeder` (not nested ternaries; prefer early returns / `match` where needed).

---

*Convention analysis: 2026-07-13*
