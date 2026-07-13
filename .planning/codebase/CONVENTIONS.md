# Coding Conventions

**Analysis Date:** 2026-07-13

## PHP / Laravel (`backend/`)

### Style

- **PSR-12** enforced via **Laravel Pint** (`./vendor/bin/pint`).
- CI runs `./vendor/bin/pint --test` (fails on violations).
- Local fix: `./vendor/bin/pint` or via pre-commit hook.

### Architecture Patterns

- **Explicit return types** on methods where practical.
- **Form requests** for HTTP validation (`StoreRunRequest`), not fat controllers.
- **API resources** for JSON shape (`RunResource`, `UserResource`, `ProviderCredentialResource`).
- **Contracts + container binding** for swappable services (`AIProviderInterface` → providers, `RunExecutorInterface` → `RunExecutor`).
- **Jobs** for slow/IO work (`ExecuteLauncherJob`); controllers return **202** and dispatch.
- **Services** for domain logic (orchestration in `app/Services/`, not controllers).
- **Thin routes** in `routes/api.php`; logic in controllers, services, jobs, launchers.
- **DTOs** for parsed external data (`GitHubReference` is a readonly value object).

### Launcher Pattern

- One class per workflow under `app/Launchers/`.
- Each extends `BaseLauncher` which provides shared `outputSchema()`.
- Metadata method returns slug, name, description, input_type, prompt.
- Seeded into `launchers` table by `DatabaseSeeder`.
- Four launchers: `review-pr`, `plan-issue`, `explain-repository`, `laravel-doctor`.

### Exception Handling

- Domain failures as `RuntimeException` / `InvalidArgumentException` with safe user-facing messages in `runs.error`.
- Server-side logging via `Log::error`.
- No raw exception messages returned to clients.
- Job handles provider creation failures via try/catch → `failRun()`.

### Misc PHP

- **No nested ternaries** — prefer `match`, early returns, or clear `if/else`.
- **UUID primary keys** on `Run` model (auto-generated).
- **JSON casts** on `Run` for `input`, `source_context`, `result`, `progress` fields.
- **Eloquent relationships:** `Run` belongsTo `Launcher` and belongsTo `User`.
- **Encrypted jobs:** `ExecuteLauncherJob` implements `ShouldBeEncrypted`.

## TypeScript / React (`backend/resources/ts/`)

### Style

- **oxlint** (Rust-based) for linting, **oxfmt** for formatting.
- **konsistent** enforces: `components/*.tsx` must export PascalCase component matching filename, `hooks/*.ts` must export `use*` functions.
- No ESLint, no Prettier — Oxc toolchain only.
- TypeScript strict mode (`tsconfig.json`).

### Component Patterns

- **Functional components + hooks only** — no class components.
- **useReducer** for complex UI state (`App.tsx` drives view state: `home` → `live-running` → `report` → `failed`).
- **No global state library** — all state is local React state (useState, useReducer).
- **Props typing:** interface per component (e.g., `DashboardProps`, `RunHistoryProps`).
- **Runtime validation:** `decodeRun` in `services/run.ts` validates API responses at runtime.
- **Error boundaries:** `ErrorBoundary.tsx` catches React rendering errors.

### API Communication

- **`lib/http.ts`:** Shared fetch wrapper with `Accept: application/json`, timeout (default 10s), AbortController, typed error message extraction.
- **`services/run.ts`:** Public API calls (createRun, fetchRun, getLaunchers, GitHub URL validation).
- **`services/auth.ts`:** Authenticated API calls (fetchUser, logout, provider credentials CRUD, run history).
- **SSE:** `hooks/useRunSubscription.ts` uses `EventSource` with polling fallback (1500ms).

### Assorted Conventions

- **Relative imports** within `resources/ts/`.
- **`import type`** for type-only imports (`import type { Run } from "../types/api.ts"`).
- **No `any`** — use `unknown` with explicit narrowing.
- **Plain CSS** in `resources/css/app.css` — BEM-like class names (`.auth-card`, `.run-item`, `.header-cta`), no Tailwind or CSS-in-JS.
- **lucide-react** for SVG icons.

## Testing

### PHPUnit

- **Feature tests:** `RefreshDatabase` trait + database seeding.
- **Unit tests:** Mock external services (GitHub, AI providers).
- **Job testing:** `Queue::fake()` to assert dispatch without execution.
- **Rate limiting tests:** Verify throttling behavior.
- Test file naming: `*Test.php`, class name matches filename.

### Frontend

- No frontend tests currently (`npm run test` is a no-op placeholder).
- TypeScript typecheck acts as compile-time verification.

## Git Workflow

- **Branch naming:** `feat/*`, `fix/*`, `chore/*` prefixes.
- **Commit style:** Conventional commits (`feat:`, `fix:`, `chore:`, `docs:`).
- **CI:** GitHub Actions runs PHP tests (migrate, test, pint) and frontend checks (typecheck, lint, konsistent, build).
- **Pre-commit hooks:** composer validate → pint → frontend typecheck → oxlint → oxfmt → konsistent.
- **Renovate:** Automated dependency updates via `renovate.json`.

## Deployment

- **Laravel Cloud:** Deploys `backend/` as application root. Build command includes `composer install --no-dev && npm ci && npm run build`.
- **Dokku:** Dockerfile-based build with Nginx + PHP-FPM. Procfile defines `release` (migrations), `web` (supervisord), `worker` (queue).
- **Production:** Never `QUEUE_CONNECTION=sync`; never SQLite in production.

---

*Conventions analysis: 2026-07-13*
