# Codebase Concerns

**Analysis Date:** 2026-07-12

## Tech Debt

### Single-file frontend monolith
- **Issue:** Entire frontend UI (components, demo data, business logic, copy utility) lives in a single `src/main.jsx` file (390 lines). A separate `src/styles.css` (84 lines) holds all styles.
- **Files:**
  - `src/main.jsx` — all 7 components (`App`, `Home`, `Running`, `Report`, `Logo`, `WorkflowIcon`) + 4 hardcoded datasets + 2 utility functions
  - `src/styles.css` — all CSS in one flat file (no CSS modules, no component scoping)
- **Impact:** Hard to maintain, test, or extend. Any change risks breaking unrelated UI. No component isolation. No reuse boundaries.
- **Fix approach:** Split into component files (`src/components/`), separate data into `src/data/`, styles into `src/components/*.module.css`.

### Unpinned npm dependencies
- **Issue:** `react`, `react-dom`, and `lucide-react` all use `"latest"` as their version specifier in `package.json`.
- **File:** `/Users/huynhdung/src/tries/2026-07-12-ai-flow/package.json` (lines 14-16)
- **Impact:** Builds may break on major version bumps. Installs are non-reproducible across machines or CI. `npm ci` will vary over time.
- **Fix approach:** Pin to the exact versions currently resolved in `package-lock.json`.

### Hardcoded demo data — no API integration
- **Issue:** Workflows, recent runs, execution steps, and report findings are all static JS arrays defined at the module level in `src/main.jsx` (lines 27-122).
- **File:** `src/main.jsx`
- **Impact:** The app has no production value. Every "run" simulates progress via `setTimeout` (line 159). The report page shows hardcoded findings. No actual GitHub or AI integration.
- **Fix approach:** Implement backend API calls (`POST /api/runs`, `GET /api/runs/{uuid}`, SSE `/stream`); replace demo data with real fetch responses.

### No TypeScript
- **Issue:** The entire React codebase is plain JavaScript with no type checking.
- **Files:** `src/main.jsx`, `src/styles.css`
- **Impact:** Runtime errors that could be caught at compile time. Poor DX — no autocomplete, no type validation for props (all component props are implicit). Intent of data structures is undocumented.
- **Fix approach:** Adopt TypeScript gradually (`*.tsx`), starting with data types and component props.

### Hardcoded fallback and URL logic
- **Issue:** The repo parsing `useMemo` (line 148-151) falls back to `'jellydn/my-ai-tools'` when the URL doesn't match the regex. The report copy button (line 331) hardcodes `window.location.href + 'runs/a1f9c2'`.
- **File:** `src/main.jsx`
- **Impact:** Misleading fallback values. Copy link produces a broken URL in development.
- **Fix approach:** Remove fallback to static string; derive URL from API response. Use early return/guard when URL is empty.

### Default SQLite for production
- **Issue:** The default database connection is SQLite (config line 20), used for both local dev and the default Cloud config if `DB_CONNECTION` isn't set.
- **File:** `backend/config/database.php` (line 20)
- **Impact:** SQLite is unsuitable for production workloads (concurrent writes, scaling). Accidental production deploy may hit write contention.
- **Mitigation:** Documented in `backend/README.md` that durable `DB_*` must be set on Cloud. No guard in code.
- **Fix approach:** Add an environment check in `AppServiceProvider::boot()` that warns or fails if SQLite is used in the `production` environment.

### Debug-level logging as default
- **Issue:** Default `LOG_LEVEL` is `debug` (config line 65), which could leak sensitive information including AI payloads and GitHub context in production.
- **File:** `backend/config/logging.php` (line 65)
- **Impact:** Sensitive data exposure risk if deployed without explicit `LOG_LEVEL=warning` or higher.
- **Fix approach:** Default to `warning` or `error`; use `debug` only in local/testing environments.

## Known Bugs

### Frontend: URL validation bypass on empty strings
- **File:** `src/main.jsx`, lines 164-167
- **Issue:** The `launch()` validation regex requires the URL to start with `https://` but does not guard against empty or whitespace-only input. The `useMemo` repo parser falls back to a hardcoded string. No guard prevents "launching" with an empty URL.
- **Severity:** Low (prototype stage; no real API call is made)

### Frontend: Copy link generates wrong URL in dev
- **File:** `src/main.jsx`, line 331
- **Issue:** `window.location.href + 'runs/a1f9c2'` concatenates the dev server URL (e.g., `http://localhost:5173`) with a hardcoded run ID. This is never a real backend URL.
- **Severity:** Low (prototype)

### Scroll-to behavior race condition
- **File:** `src/main.jsx`, lines 186, 190, 256, 271
- **Issue:** Multiple click handlers call `scrollIntoView` wrapped in `setTimeout(…, 0)`. If rendering takes longer than one event loop tick, the scroll target doesn't exist and the call silently fails.
- **Severity:** Low (cosmetic)

## Security Considerations

### `allowedHosts: true` (Vite dev server)
- **Risk:** Vite's `server.allowedHosts: true` accepts all host headers, exposing the dev server to DNS rebinding attacks (CVE-2023-34092).
- **File:** `/Users/huynhdung/src/tries/2026-07-12-ai-flow/vite.config.js`, line 7
- **Current mitigation:** Dev-only binding. Not for production.
- **Recommendations:** Restrict to explicit allowed hosts (e.g., `[ 'localhost', '.amp.dev' ]`).

### No CORS configuration
- **Risk:** No `config/cors.php` exists in the backend. Laravel 12's default CORS middleware uses permissive defaults (any origin) if no explicit config is published.
- **File:** Missing — `backend/config/cors.php` does not exist.
- **Current mitigation:** The public API is unauthenticated by design (ADR 0014); rate limiting of 5 req/hr/IP limits abuse surface.
- **Recommendations:** Publish and configure CORS to explicitly allow only the known frontend origin(s).

### No authentication on API
- **Risk:** All API endpoints are unauthenticated. `POST /api/runs` is throttled (5/hr/IP), but `GET /api/runs/{uuid}` and the SSE `stream` endpoint are open to anyone who can guess a UUID.
- **Files:** `backend/routes/api.php` (lines 7-11), `backend/app/Http/Requests/StoreRunRequest.php` (line 9-11)
- **Current mitigation:** Run UUIDs are v4 UUIDs (unguessable). Rate limiting on create. Design decision per ADR 0014.
- **Recommendations:** Add token-based auth for production deployments. Rate-limit the stream endpoint.

### GitHub context may contain sensitive data
- **Risk:** `GitHubService::context()` fetches PR descriptions, issue bodies, and file diffs which may contain secrets or internal information. These are stored unencrypted in the `source_context` JSON column.
- **Files:** `backend/app/Services/GitHubService.php` (lines 66-83), `backend/database/migrations/2026_01_01_000000_create_launchers_and_runs.php` (line 30)
- **Current mitigation:** None. The frontend displays "Your code is read-only and never stored" (line 324), but the backend stores the data persistently.
- **Recommendations:** Auto-purge `source_context` after report generation. Encrypt the JSON column at rest.

### No `.env.example` in repo
- **Risk:** No reference `.env.example` file, making setup unclear for new developers.
- **File:** Missing — should be `backend/.env.example`
- **Impact:** Setup friction. Risk of developers committing real `.env` files.
- **Fix approach:** Create `backend/.env.example` documenting all required and optional variables.

### Rate limiting gap on stream endpoint
- **Risk:** `GET /api/runs/{run}/stream` is not rate-limited. A malicious client could open many SSE connections to exhaust database connections.
- **File:** `backend/routes/api.php` (line 11)
- **Recommendations:** Add `throttle` middleware to the stream endpoint.

## Performance Bottlenecks

### SSE database polling
- **Issue:** The `stream()` endpoint polls the database every 500ms (`usleep(500000)`) for up to 55 seconds, re-fetching and re-resolving the entire model on each iteration.
- **File:** `backend/app/Http/Controllers/RunController.php` (lines 39-56)
- **Impact:** Each SSE client causes ~110 database queries over 55 seconds. Under concurrent load, this stresses the database (especially SQLite).
- **Fix approach:** Use Laravel broadcasting (WebSockets) or database notifications. Short-term: increase polling to 1s, use a lightweight query instead of full model hydration.

### GitHub API calls inside queue job
- **Issue:** `GitHubService::context()` makes up to 5 sequential HTTP requests with 15s timeouts. The job timeout is 120s.
- **File:** `backend/app/Services/GitHubService.php`
- **Impact:** Large repos (500-tree file slice) increase memory and risk of job timeout.
- **Fix approach:** Chain a separate GitHub context gathering job. Cache aggressively (currently 10min TTL).

### Large AI prompt payloads
- **Issue:** The prompt sent to OpenAI includes the full JSON-encoded GitHub context, potentially 100KB+ with file diffs.
- **File:** `backend/app/Jobs/ExecuteLauncherJob.php` (line 41)
- **Impact:** High token usage increases cost and latency. No truncation or smart context windowing.
- **Fix approach:** Estimate token count and truncate context before sending.

## Fragile Areas

### Single-file component architecture
- **File:** `src/main.jsx` (390 lines)
- **Why fragile:** All 7 components and all data live in one file. Any edit affects everything. Adding a feature requires understanding the whole file.
- **Safe modification:** Extract one component at a time, verifying the UI still renders after each extraction.

### `ExecuteLauncherJob::handle()` mixed DI styles
- **File:** `backend/app/Jobs/ExecuteLauncherJob.php`, line 26
- **Why fragile:** `?JsonSchemaValidator $validator = null` with inline `app()` resolution (line 43) mixes constructor DI with service locator.
- **Safe modification:** Make the parameter required; Laravel can auto-resolve it.

### `GitHubService::parse()` returns plain array
- **File:** `backend/app/Services/GitHubService.php`, line 31
- **Why fragile:** Returns a plain array with stringly-typed keys. Consumers must know the shape. Adding a key could silently break callers.
- **Safe modification:** Introduce a DTO/value object for parsed references.

### Hardcoded frontend progress simulation
- **File:** `src/main.jsx`, lines 92-98
- **Why fragile:** Execution steps are a static array. If the backend changes its progress convention, the frontend simulation won't match real behavior.
- **Safe modification:** Remove simulation when API integration is added; render real progress from SSE events.

## Dependencies at Risk

### `react` / `react-dom` / `lucide-react` using `"latest"`
- **Risk:** Unpredictable version bumps on `npm install` or clean installs.
- **Files:** `package.json` (lines 14-16)
- **Impact:** Breaking changes may break the build without warning.
- **Migration plan:** Pin to known-good versions from `package-lock.json`. Update deliberately with changelog review.

### Backend: No unusual risk
- **Note:** `composer.lock` (310KB) is committed. Dependency versions are locked. No unpinned ranges in `composer.json`.

## Missing Critical Features

### No frontend API layer
- **Problem:** Zero network calls. All data is hardcoded. No fetch, no axios, no SSE client.
- **Blocks:** Any production use.
- **Files:** `src/main.jsx`

### No error boundaries
- **Problem:** No React error boundaries. Any unhandled exception will white-screen the app.
- **Files:** `src/main.jsx`

### No loading/skeleton states
- **Problem:** The "running" view simulates progress via `setTimeout`. No skeleton, no spinner for API calls, no retry UI.

### No frontend testing
- **Problem:** Zero frontend tests. No Vitest, no Testing Library, no Playwright.
- **Risk:** Refactoring the single-file monolith is high-risk without test coverage.

## Test Coverage Gaps

### Frontend: 0% coverage
- **What's not tested:** 100% of `src/main.jsx`, `src/styles.css`, `index.html`
- **Files:** `src/*`
- **Risk:** Complete blind spot. Regression risk is high.
- **Priority:** High (before any refactoring)

### Backend: Incomplete coverage
- **What's tested:**
  - `GitHubService::parse()` — 3 happy-path + 1 rejection case
  - `RunController` — 4 HTTP endpoint tests (health, create, show, validation, rate limit)
  - `ExecuteLauncherJob` — 2 scenario tests (happy path + JSON validation failure)
- **What's NOT tested:**
  - `GitHubService::context()` (no HTTP mock tests)
  - `OpenAIProvider` (no test file)
  - `JsonSchemaValidator` (no direct unit tests)
  - `RunResource` (no test)
  - `RunProgressed` event (no test)
  - SSE stream endpoint (no test for HTTP 200 + event-stream content-type)
  - All 4 launcher classes (only tested indirectly through mocked job)
  - `AppServiceProvider` (no test)
- **Total:** 143 lines of test code across 3 files vs. 822 lines of app code
- **Priority:** Medium

## Backend Concerns

### No dedicated CORS config
- **Issue:** `backend/config/cors.php` does not exist. Laravel serves with default permissive CORS.
- **Impact:** Any website could make API calls from users' browsers (mitigated by rate limiting).
- **Fix:** Publish CORS config with explicit allowed origins.

### AI provider tightly coupled to OpenAI
- **Issue:** Only one implementation of `AIProviderInterface` exists (`OpenAIProvider`). No factory or registry for selecting providers.
- **File:** `backend/app/Services/OpenAIProvider.php`
- **Impact:** Vendor lock-in. Changing AI provider requires code change.
- **Fix:** Add a provider registry with env-based selection (`AI_PROVIDER=openai`).

### Missing database indexes
- **Issue:** No indexes on `runs.status` or `runs.created_at` for the polling SSE query (`WHERE status IN ('completed', 'failed')`).
- **Impact:** SSE polling will perform full table scans as runs accumulate.
- **Fix:** Add database indexes for `status`, `created_at`, and `launcher_id` on the `runs` table.

### `RunResource` exposes internal error messages
- **Issue:** The `error` field exposed by `RunResource` returns whatever is stored. While `RuntimeException` messages are user-safe, the pattern could leak internal details if a different exception type is stored.
- **File:** `backend/app/Http/Resources/RunResource.php` (line 12)
- **Impact:** Potential information disclosure.
- **Fix:** Only expose user-safe error messages; sanitize the `error` field in the resource.

## Non-Code Concerns

### No CI/CD pipeline
- **Issue:** No GitHub Actions configuration. No automated test runner.
- **Impact:** No guard against regressions.
- **Fix:** Add a minimal GitHub Actions workflow (run `php artisan test` and `npm run build`).

### No Docker environment
- **Issue:** No `docker-compose.yml` or `Dockerfile`. Every developer must manually set up PHP 8.2, Composer, Node, SQLite/MySQL.
- **Impact:** Inconsistent local environments. Onboarding friction.
- **Fix:** Add Docker Compose with app, queue worker, and database services.

### No changelog or versioning strategy
- **Issue:** No `CHANGELOG.md`. Version is static `0.1.0`.
- **Impact:** No release management. Breaking changes are not communicated.
- **Fix:** Adopt Keep a Changelog and Semantic Versioning.

---

_Generated by codebase analysis on 2026-07-12. Covers both frontend (`src/`) and backend (`backend/`)._
