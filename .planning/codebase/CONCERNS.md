# Codebase Concerns

**Analysis Date:** 2026-07-13

## Tech Debt

### Large Frontend Components

- **Issue:** `Home.tsx` (384 lines) and `App.tsx` (378 lines) are the two largest files in the codebase. Both mix orchestration, state management, and rendering in single files.
- **Files:** `backend/resources/ts/components/Home.tsx`, `backend/resources/ts/components/App.tsx`
- **Impact:** Hard to test, hard to reason about, merge conflicts likely.
- **Suggested fix:** Extract `App.tsx` view routing into a hook or separate component; split `Home.tsx` into launcher-selector, URL-input, and action-button sub-components.

### Unused Frontend Test Placeholder

- **Issue:** `npm run test` is a no-op (`echo "no frontend tests yet"`).
- **Files:** `backend/package.json`
- **Impact:** No frontend regression safety net. UI bugs only caught manually.
- **Suggested fix:** Add Vitest + React Testing Library for component tests; prioritize critical paths (App state transitions, RunHistory CRUD, ProviderSettings verify flow).

### `ProviderSettings.tsx` (161 lines) Complexity

- **Issue:** Handles credential CRUD, verify flow, privacy toggles, default selection, and error states in a single component.
- **Files:** `backend/resources/ts/components/ProviderSettings.tsx`
- **Impact:** Multiple concerns in one component; verify flow async state is complex.
- **Suggested fix:** Extract credential form, credential list, and privacy note into separate components.

## Known Bugs & Edge Cases

### `/api/user` 500 Without Accept Header

- **Issue:** Hitting `/api/user` without `Accept: application/json` header causes 500 (`Route [login] not defined`) because Laravel's `auth` middleware redirects to a nonexistent `login` route.
- **Files:** `backend/routes/api.php` (line 18: `Route::middleware('auth')`)
- **Impact:** Non-frontend API consumers (curl scripts, third-party tools) get 500 instead of 401. Frontend is unaffected (sends proper headers).
- **Suggested fix:** Either add a named `login` route returning 401 JSON, or configure the exception handler to always return JSON for `api/*` routes (using `shouldRenderJsonWhen` or `renderable` in `bootstrap/app.php` — both attempted but not confirmed working on Laravel 13).

### PDO Deprecation Warning (PHP 8.5)

- **Issue:** `PDO::PGSQL_ATTR_DISABLE_PREPARES` is deprecated in PHP 8.5.
- **Files:** `backend/config/database.php` (line 101)
- **Status:** **FIXED** — replaced with `Pgsql::ATTR_DISABLE_PREPARES` + `use Pdo\Pgsql` import.

## Potential Issues

### Run Retry Race Condition

- **Issue:** `RunHistory.tsx` retry flow uses `actioningId` state to prevent double-clicks, but the API-level retry has no idempotency key. If two browser tabs both retry the same run simultaneously, duplicate runs could be created.
- **Files:** `backend/app/Http/Controllers/RunHistoryController.php` (retry method)
- **Impact:** Low — `actioningId` UI guard is sufficient for single-tab use.
- **Suggested fix:** Add idempotency key or check for existing queued retries on the server.

### No Pagination-Limit Validation

- **Issue:** `RunHistoryController::index()` had no `per_page` cap (allowing `?per_page=10000`).
- **Files:** `backend/app/Http/Controllers/RunHistoryController.php`
- **Status:** **FIXED** — capped at 100 via `min($request->integer('per_page', 20), 100)`.

### No Input Validation on Run History Filters

- **Issue:** Query parameters `status`, `date_from`, `date_to` in `RunHistoryController::index()` are used directly in queries without validation. Invalid dates could cause SQL errors.
- **Files:** `backend/app/Http/Controllers/RunHistoryController.php`
- **Impact:** Low — frontend controls the values, but API consumers could trigger errors.
- **Suggested fix:** Add `date_format:Y-m-d` validation or wrap in try/catch with 422 response.

## Security

### API Key Exposure Risk

- **Issue:** Provider API keys from users are stored encrypted (`ShouldBeEncrypted` job), but the frontend sends them in POST bodies. TLS is required.
- **Files:** `backend/app/Jobs/ExecuteLauncherJob.php`, frontend API service calls
- **Mitigation:** Production `AppServiceProvider` blocks non-TLS Postgres connections; Laravel Cloud enforces HTTPS. Acceptable for current scope.

### No CSRF Protection on API Routes

- **Issue:** API routes in `routes/api.php` don't use CSRF middleware. The app uses session-based auth (magic links), so CSRF could theoretically allow session riding.
- **Files:** `backend/routes/api.php`
- **Impact:** Low — the SPA uses `fetch()` with same-origin requests; cookies are `SameSite=Lax` by default. Standard Laravel SPA pattern.

### Public Run Creation

- **Issue:** `POST /api/runs` has no authentication — anyone with the URL can create runs. Rate limiting (5/hour/IP) is the only gate.
- **Files:** `backend/routes/api.php`
- **Impact:** By design for MVP — public runs are an intentional feature. Abuse limited by rate throttling.

## Performance

### SSE Database Polling

- **Issue:** `RunStreamer` polls the database every second for up to 55 seconds (55 queries per stream). At scale, many concurrent streams could create DB load.
- **Files:** `backend/app/Services/RunStreamer.php`
- **Impact:** Low for current usage. If scaling up, consider Redis pub/sub or WebSocket push instead.
- **Mitigation:** SSE connections are rate-limited (30/min/IP).

### GitHub Context Caching

- **Issue:** 10-minute cache for GitHub context. Same URL fetched by multiple users within the cache window shares the cached result.
- **Files:** `backend/app/Services/GitHubService.php`
- **Impact:** Cache hit rate depends on URL diversity. Good enough for current usage.

## Missing Coverage

- **No frontend component tests** — UI regression risk is manual-only.
- **No E2E tests** — full user flows (sign-in → launch workflow → view report) not automated.
- **No performance/load tests** — SSE streaming under load not characterized.
- **No error telemetry** — failures only logged to `storage/logs/laravel.log`; no Sentry/Bugsnag integration.

---

*Concerns analysis: 2026-07-13*
