# Concerns

**Analysis Date:** 2026-07-14

## Tech Debt

**Redundant `config('services.openai.providers')` array (fixed):**
- Issue: The `providers` array in `config/services.php` duplicated `AiProviderRegistry::PROVIDERS`, creating two sources of truth for provider IDs.
- Files: `backend/config/services.php`, `backend/app/Support/AiProviderRegistry.php`, `backend/app/Http/Requests/StoreProviderCredentialRequest.php`
- Status: ✅ Fixed — `StoreProviderCredentialRequest` now injects `AiProviderRegistry` and uses `$registry->ids()` for validation; the `providers` array was removed from `config/services.php`.
- Impact: None — `AiProviderRegistry` is now the single source of truth.

**No claim flow for anonymous runs:**
- Issue: Anonymous users can create runs (no auth required for `POST /api/runs`), but there's no mechanism to claim those runs after signing in. `RunController::store` sets `user_id` to `$request->user()?->id`, which is `null` for anonymous users. Runs created before auth are permanently anonymous.
- Files: `backend/app/Http/Controllers/RunController.php`, `backend/app/Models/Run.php`
- Impact: Users who launch a workflow then sign in lose access to their run history.
- Fix approach: Add a `claim_runs` endpoint that associates anonymous runs (by IP or cookie) with the newly authenticated user.

**Missing `completed_at` index for recent runs endpoint (fixed):**
- Issue: `RunController::recent()` queries `where('status', 'completed')->whereNull('user_id')->whereNotNull('result')->orderByDesc('completed_at')->limit(6)`. The `runs` table had indexes on `status` and `created_at`, but no index on `completed_at`.
- Files: `backend/app/Http/Controllers/RunController.php`, `backend/database/migrations/2026_07_15_000001_add_recent_runs_index_to_runs_table.php`
- Status: ✅ Fixed — composite index `runs_status_user_completed_at_index` on `(status, user_id, completed_at)`.
- Impact: None for typical volumes; home-page recent runs query is indexed.

**Home.tsx size (watch):**
- Issue: `Home.tsx` still bundles trending, recent runs, and launch sections in one file (~287 lines at current HEAD; was larger when this map was first written).
- Files: `backend/resources/ts/components/Home.tsx`
- Impact: Unrelated UI changes can still conflict; recent-runs block is harder to test in isolation.
- Fix approach: Extract `RecentRunsSection` and `TrendingCard` when touching home layout again.

## Security

**Latent SSRF risk via stored `base_url` (mitigated):**
- Issue: User-supplied `base_url` on provider credentials could target localhost, private IPs, or cloud metadata if later used for outbound HTTP from workers.
- Files: `backend/app/Rules/PublicHttpUrl.php`, `backend/app/Http/Requests/StoreProviderCredentialRequest.php`, `backend/app/Http/Requests/UpdateProviderCredentialRequest.php`, `backend/tests/Feature/ProviderCredentialBaseUrlValidationTest.php`
- Status: ✅ Mitigated — `PublicHttpUrl` validation blocks non-public hosts and private/reserved IPs on create and update. Stored `base_url` is still not wired into `AiProviderRegistry::get()`; re-validate when that lands.
- Impact: Credentials cannot be saved with obvious SSRF targets; remaining risk is DNS rebinding if providers fetch stored URLs without additional hardening.

**No rate limiting on credential verification (fixed):**
- Issue: `POST /api/user/provider-credentials/{id}/verify` had no rate limit, allowing excessive outbound API calls.
- Files: `backend/routes/api.php`, `backend/app/Providers/AppServiceProvider.php`
- Status: ✅ Fixed — Added `throttle:credentials` rate limiter (10/min/user) in `AppServiceProvider` and applied to the verify route.
- Impact: None — credential verification is now rate-limited.

## Performance

**SSE polling resource usage:**
- Issue: `RunStreamer` polls the database every second for up to 55 seconds per SSE connection. With 30 concurrent streams (rate limit), that's 30 DB queries/second.
- Files: `backend/app/Services/RunStreamer.php`
- Impact: Database load scales linearly with concurrent SSE connections.
- Fix approach: Consider Redis pub/sub or WebSocket for production scale; the current DB-polling approach is fine for MVP but may need optimization.

**GitHub context caching is in-memory for tests, database for production:**
- Issue: `GitHubService::context()` caches for 10 minutes using the default cache store. In production with `CACHE_STORE=database`, this works but adds DB queries. In production with high traffic, the cache miss path fetches from GitHub REST API synchronously in the queue worker.
- Files: `backend/app/Services/GitHubService.php`
- Impact: First request for a URL is slow (GitHub API fetch); subsequent requests are cached.
- Fix approach: Consider Redis cache for production; current approach is adequate for MVP.

**Frontend bundle size:**
- Issue: The Vite build bundles all React components into a single chunk. No code splitting or lazy loading. No `React.lazy()` usage found and no `manualChunks` in `vite.config.ts`.
- Files: `backend/vite.config.ts`, `backend/resources/ts/app.tsx`
- Impact: Initial page load includes all components (Dashboard, RunHistory, ProviderSettings) even if the user is anonymous.
- Fix approach: Add `React.lazy()` for authenticated-only views (Dashboard, ProviderSettings, RunHistory).

## Bugs

No known bugs at current HEAD.

## Fragile Areas

**Squash-merge + stacked PR rebasing:**
- Issue: When using `gh stack` with squash merges, each lower PR squash-merge changes the commit hash, causing all upstack branches to conflict on files that were in the squash-merged PR.
- Files: N/A (process issue)
- Impact: Time-consuming rebase conflicts when merging stacked PRs via squash.
- Fix approach: Use regular merge commits for stacked PRs, or merge all PRs in the stack at once via `gh stack merge`.

**`ProviderCredential::forceCreate()` in tests:**
- Issue: Tests use `forceCreate()` to bypass mass-assignment protection on `user_id`. If the `$fillable` array is changed to include `user_id`, tests would silently switch to `create()` behavior.
- Files: `backend/tests/Feature/SavedCredentialLaunchTest.php`, `backend/tests/Feature/AccountDeletionTest.php`
- Impact: Tests are slightly fragile against model changes.
- Fix approach: Acceptable for test code; document the pattern.

## Documentation

**`AGENTS.md` references outdated remote names (fixed):**
- Issue: `AGENTS.md` previously stated "`origin` may point at Amp git; `github` remote is `jellydn/ai-flow`". The stale Amp sync note has been removed and the Gotchas section now correctly states `origin` = `github.com/jellydn/ai-flow`, `dokku` = staging deploy target.
- Files: `AGENTS.md`
- Status: ✅ Fixed (already resolved in current `main`)
- Impact: None — documentation is now accurate.

**ADR-0016 mentions `CREDENTIAL_ENCRYPTION_KEY` env var:**
- Issue: ADR-0016 (`doc/adr/0016-stored-encrypted-byok-credentials.md`) documents a future `CREDENTIAL_ENCRYPTION_KEY` env var for dedicated credential encryption, but this is not implemented — credentials use `APP_KEY` via Laravel `Crypt`.
- Files: `doc/adr/0016-stored-encrypted-byok-credentials.md`
- Impact: Gap between documentation and implementation.
- Fix approach: Either implement the dedicated key or update the ADR to note it's deferred.

## Missing Features

**No token/cost metadata tracking:**
- Issue: AI provider responses include token usage and cost metadata, but these are not captured or stored. The `runs` table has no `tokens_used` or `cost` column.
- Files: `backend/app/Services/RunExecutor.php`, `backend/app/Models/Run.php`
- Impact: No visibility into API costs or token consumption.
- Fix approach: Add `tokens_used` (int) and `cost_estimate` (decimal) columns to `runs`; parse from provider response and store on completion.

**No webhook/notification on run completion:**
- Issue: Users must keep the browser open to see run results. There's no email notification or webhook when a run completes.
- Files: N/A
- Impact: Users miss results for long-running workflows.
- Fix approach: Add optional email notification on completion (reuse Resend); or add a webhook URL field to the run creation request.

## Previously Fixed (for reference)

- ✅ **Redundant `config('services.openai.providers')` array** — `AiProviderRegistry` is now the single source of truth; config array removed.
- ✅ **No rate limiting on credential verification** — Added `throttle:credentials` (10/min/user).
- ✅ **Demo report view reset** — `useEffect` in `App.tsx` now excludes `"report"` from the reset condition.
- ✅ **AGENTS.md references outdated remote names** — Stale Amp sync note removed; `origin` = `github.com/jellydn/ai-flow` documented.
- ✅ **E2E test depends on specific demo finding text** — Replaced `toContainText("Missing authorization check")` with structural `toBeVisible()` + `not.toBeEmpty()` on `finding-severity` and `finding-title` test IDs.
- ✅ **Silent catches in `useRunSubscription.ts` and `App.tsx`** — Replaced with `logger.warn()` calls via consola logger integration.
- ✅ **Silent catches in `SignIn.tsx`, `ProviderSettings.tsx`, `RunHistory.tsx`** — All 3 remaining `catch {}` blocks replaced with `logger.warn()` calls, completing the frontend logging coverage.
- ✅ **Missing `completed_at` index for recent runs** — Composite index on `(status, user_id, completed_at)`.
- ✅ **Latent SSRF via credential `base_url`** — `PublicHttpUrl` rule on store/update provider credentials.
