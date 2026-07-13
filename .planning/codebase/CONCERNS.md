# Codebase Concerns

**Analysis Date:** 2026-07-13

## Tech Debt

### Large Frontend Components

- **Issue:** `Home.tsx` (384 lines) and `App.tsx` (378 lines) are the two largest files in the codebase. Both mix orchestration, state management, and rendering in single files.
- **Files:** `backend/resources/ts/components/Home.tsx`, `backend/resources/ts/components/App.tsx`
- **Impact:** Hard to test, hard to reason about, merge conflicts likely.
- **Assessment:** Valid tech debt. Not fixed now — refactoring without frontend test coverage is risky. Do after adding component tests.
- **Suggested fix:** Extract `App.tsx` view routing into a hook or separate component; split `Home.tsx` into launcher-selector, URL-input, and action-button sub-components.

### Unused Frontend Test Placeholder

- **Issue:** `npm run test` is a no-op (`echo "no frontend tests yet"`).
- **Files:** `backend/package.json`
- **Impact:** No frontend regression safety net. UI bugs only caught manually.
- **Assessment:** Valid tech debt. Not fixed now — requires full test infrastructure setup (Vitest + Testing Library + mocks).
- **Suggested fix:** Add Vitest + React Testing Library for component tests; prioritize critical paths (App state transitions, RunHistory CRUD, ProviderSettings verify flow).

### `ProviderSettings.tsx` (161 lines) Complexity

- **Issue:** Handles credential CRUD, verify flow, privacy toggles, default selection, and error states in a single component.
- **Files:** `backend/resources/ts/components/ProviderSettings.tsx`
- **Impact:** Multiple concerns in one component; verify flow async state is complex.
- **Assessment:** Valid tech debt. Not fixed now — extract after adding component tests.
- **Suggested fix:** Extract credential form, credential list, and privacy note into separate components.

## Known Bugs & Edge Cases

### ~~`/api/user` 500 Without Accept Header~~ ✅ FIXED

- **Issue:** Hitting `/api/user` without `Accept: application/json` header caused 500 (`Route [login] not defined`) because Laravel's `auth` middleware redirected to a nonexistent `login` route.
- **Files:** `backend/routes/api.php`
- **Fix:** Added named `login` route returning 401 JSON: `Route::get('/login', fn () => response()->json(['message' => 'Unauthenticated.'], 401))->name('login')`.
- **Result:** Unauthenticated API requests now get 401 JSON with or without Accept header.

### ~~PDO Deprecation Warning (PHP 8.5)~~ ✅ FIXED

- **Issue:** `PDO::PGSQL_ATTR_DISABLE_PREPARES` is deprecated in PHP 8.5.
- **Files:** `backend/config/database.php` (line 101)
- **Fix:** Replaced with `Pgsql::ATTR_DISABLE_PREPARES` + `use Pdo\Pgsql` import.

## Potential Issues

### Run Retry Race Condition

- **Issue:** `RunHistory.tsx` retry flow uses `actioningId` state to prevent double-clicks, but the API-level retry has no idempotency key. If two browser tabs both retry the same run simultaneously, duplicate runs could be created.
- **Files:** `backend/app/Http/Controllers/RunHistoryController.php` (retry method)
- **Assessment:** Low impact. The `actioningId` UI guard is sufficient for single-tab use. Multi-tab race is unlikely in practice.
- **Decision:** Not fixing now — low probability × low impact.

### ~~No Pagination-Limit Validation~~ ✅ FIXED

- **Issue:** `RunHistoryController::index()` had no `per_page` cap (allowing `?per_page=10000`).
- **Files:** `backend/app/Http/Controllers/RunHistoryController.php`
- **Fix:** Capped at 100 via `min($request->integer('per_page', 20), 100)` + validation `max:100`.

### ~~No Input Validation on Run History Filters~~ ✅ FIXED

- **Issue:** Query parameters `status`, `date_from`, `date_to` in `RunHistoryController::index()` were used directly in queries without validation. Invalid dates could cause SQL errors.
- **Files:** `backend/app/Http/Controllers/RunHistoryController.php`
- **Fix:** Added `$request->validate()` with `date_format:Y-m-d` on date fields, `in:queued,running,completed,failed` on status, `max:500` on search, and `min:1|max:100` on per_page.

## Security

### API Key Exposure Risk

- **Issue:** Provider API keys from users are stored encrypted (`ShouldBeEncrypted` job), but the frontend sends them in POST bodies. TLS is required.
- **Files:** `backend/app/Jobs/ExecuteLauncherJob.php`, frontend API service calls
- **Assessment:** Not a concern. Production `AppServiceProvider` blocks non-TLS Postgres connections; Laravel Cloud enforces HTTPS. Encryption at rest + TLS in transit = acceptable for current scope.

### No CSRF Protection on API Routes

- **Issue:** API routes in `routes/api.php` don't use CSRF middleware. The app uses session-based auth (magic links), so CSRF could theoretically allow session riding.
- **Files:** `backend/routes/api.php`
- **Assessment:** Not a concern. Standard Laravel SPA pattern — `fetch()` with same-origin requests + `SameSite=Lax` cookies provide sufficient protection. No form-based state changes on API routes.

### Public Run Creation

- **Issue:** `POST /api/runs` has no authentication — anyone with the URL can create runs. Rate limiting (5/hour/IP) is the only gate.
- **Files:** `backend/routes/api.php`
- **Assessment:** By design for MVP. Public runs are an intentional feature. Abuse limited by rate throttling.

## Performance

### SSE Database Polling

- **Issue:** `RunStreamer` polls the database every second for up to 55 seconds (55 queries per stream). At scale, many concurrent streams could create DB load.
- **Files:** `backend/app/Services/RunStreamer.php`
- **Assessment:** Not a concern at current scale. SSE connections are rate-limited (30/min/IP). Revisit if scaling to thousands of concurrent users.
- **Future mitigation:** Redis pub/sub or WebSocket push.

### GitHub Context Caching

- **Issue:** 10-minute cache for GitHub context. Same URL fetched by multiple users within the cache window shares the cached result.
- **Files:** `backend/app/Services/GitHubService.php`
- **Assessment:** Not a concern. This is a feature, not a bug — caching reduces GitHub API usage and improves response time.

## Missing Coverage

- **No frontend component tests** — UI regression risk is manual-only.
- **No E2E tests** — full user flows (sign-in → launch workflow → view report) not automated.
- **No performance/load tests** — SSE streaming under load not characterized.
- **No error telemetry** — failures only logged to `storage/logs/laravel.log`; no Sentry/Bugsnag integration.

---

*Concerns analysis: 2026-07-13*
