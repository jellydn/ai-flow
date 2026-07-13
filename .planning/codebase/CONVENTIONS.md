# Conventions

**Analysis Date:** 2026-07-14

## PHP / Laravel (`backend/`)

### Style & Formatting

- **PSR-12** + **Laravel conventions** enforced by **Laravel Pint** (`./vendor/bin/pint`)
- **Explicit return types** on methods where practical (`: void`, `: JsonResponse`, `: array`)
- **Form requests** for HTTP validation (`StoreRunRequest`, `StoreProviderCredentialRequest`), not fat controllers
- **API resources** for JSON shape (`RunResource`, `ProviderCredentialResource`, `UserResource`)
- **Contracts + container binding** for swappable services (`AIProviderInterface` → `OpenAIProvider`, `RunExecutorInterface` → `RunExecutor`)
- **Jobs** for slow/IO work (`ExecuteLauncherJob`); controllers return **202** and dispatch
- **Thin routes** in `routes/api.php`; logic in controllers, services, jobs, launchers
- **No nested ternaries** — prefer `match`, early returns, or clear `if/else`

### Architecture Patterns

- **Launchers:** one class per workflow under `app/Launchers/`, metadata via `BaseLauncher::make()`; seed in `DatabaseSeeder`
- **Services:** orchestration in `app/Services/`, not controllers. Services are injected or resolved via `app()`
- **Singletons:** `AiProviderRegistry` registered as singleton in `AppServiceProvider`
- **Policies:** `RunPolicy`, `ProviderCredentialPolicy` for ownership checks on authenticated routes
- **Events:** `RunProgressed` dispatched on every run state change; `CacheRunProgressedVersion` listener caches version for SSE diffing

### Error Handling

- Domain failures as `RuntimeException` / `InvalidArgumentException` with safe user-facing messages in `runs.error`
- Log details server-side via `Log::error()` / `Log::warning()`; never return stack traces to the client
- `RunExecutor::execute` catches all `Throwable`: `RuntimeException` keeps its message, other `Throwable` becomes "Run failed unexpectedly."
- Provider errors: 401/403 → "Invalid API key", other non-2xx → generic failure message
- GitHub errors: 404/403/401 mapped to friendly messages in `GitHubContextFetcher::mapRequestException()`
- `AccountController::destroy()` logs out + invalidates session BEFORE deleting user (prevents SessionGuard interference)

### Security Patterns

- **Credential encryption:** `CredentialCipher` uses Laravel `Crypt::encryptString()` (AES-256 via `APP_KEY`)
- **Decrypt-on-use only:** Plaintext key never stored, logged, serialized, or returned in API responses
- **Masked display:** `CredentialCipher::mask()` shows `sk-abcd...9X2A` format
- **Mass-assignment:** `ProviderCredential.$hidden` includes `encrypted_api_key`, `encrypted_base_url`; `$fillable` excludes `user_id` (set manually in controller)
- **Token hashing:** Magic-link tokens stored as SHA-256 hash, never plaintext
- **Open redirect prevention:** `MagicLinkMail::isSafeRedirect()` only allows relative paths or same-origin URLs
- **Production guards:** `AppServiceProvider` throws if `sqlite`, `pgsql` without TLS, or `sync` queue in production

## React / Frontend (`backend/resources/ts/`)

### Component Patterns

- **Functional components + hooks only** — no class components (except `ErrorBoundary` which requires it)
- **TypeScript strict mode** — avoid broad `any`; use `unknown` with explicit narrowing via assertion functions
- **`useReducer` for complex state** — `AppUiState` in `appUiState.ts` drives view transitions
- **Prop drilling** for component configuration (no context provider for app state)
- **`konsistent` enforces:** `components/*.tsx` must export a PascalCase component matching the filename; `hooks/*.ts` must export `use*` functions

### Data & Transport

- **Same-origin `/api/*` requests** — no CORS needed in production (SPA served by Laravel)
- **Runtime decoders** — `services/run.ts` and `services/auth.ts` use `assertString`, `assertObject`, `assertArray` to validate API responses at runtime
- **Typed contracts** — `types/api.ts` defines `Run`, `RunResult`, `Finding`, `Launcher`, `ProgressStep`
- **SSE + polling fallback** — `useRunSubscription` opens `EventSource`, falls back to `fetchRun()` polling every 1500ms on error
- **Deep-link routing** — `useRunFromPath` parses `/runs/{uuid}` from `window.location.pathname`, no router library

### Styling

- **Plain CSS** in `resources/css/app.css` (BEM-like class names: `.launcher-card`, `.report-page`, `.running-page`)
- **No CSS-in-JS** or Tailwind — just imported CSS file
- **lucide-react** for icons — imported per-component, never globally

### Linting & Formatting

- **oxlint** (Rust-based) — config at repo root `.oxlintrc.json`, 95 rules
- **oxfmt** (Rust-based) — config at `.oxfmtrc.json`; run `npm run format` to fix
- **No ESLint/Prettier** — there is no `.prettierrc`
- **konsistent** — structural TS convention checks; run via `npm run konsistent`

### Frontend Error Handling

- `ErrorBoundary` catches uncaught React rendering errors and offers reload
- `lib/http.ts` extracts error messages from `message`/`error` keys in JSON responses
- Service decoders throw on malformed payloads, caught and surfaced in `useRunSubscription` / `App`
- Demo mode (`VITE_DEMO_MODE=true`) bypasses API entirely with timer-driven steps and static findings

## Git Conventions

- **Commit messages:** commitizen conventional commits — `<type>(<scope>): <subject>`
- **Types:** `feat`, `fix`, `test`, `docs`, `refactor`, `chore`, `ci`, `perf`, `style`, `build`, `revert`
- **Branches:** `feat/<description>`, `fix/<description>`, or date-based `YYYY-MM-DD-<description>`
- **PRs:** squash merge preferred; draft PRs for work-in-progress
- **Stacked PRs:** `gh stack` for dependent PR chains (rebase + force-push with lease)

## Configuration Conventions

- **Environment variables:** `KEY=value` in `.env`; documented in `.env.example`
- **AI provider config:** All under `config/services.php` → `openai` key (includes `openrouter_*`, `referer`, `timeout`, `providers` array)
- **Per-provider config:** `anthropic.key`/`anthropic.model`, `gemini.key`/`gemini.model` — separate top-level keys
- **Frontend env:** `VITE_*` prefix for Vite-injected env vars (`VITE_DEMO_MODE`, `VITE_SENTRY_DSN`)
- **Deploy:** `backend/` is always the app root; monorepo root is never deployed directly
