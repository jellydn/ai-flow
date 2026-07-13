# Concerns

**Analysis Date:** 2026-07-14

## Tech Debt

**No claim flow for anonymous runs:**
- Issue: Anonymous users can create runs (no auth required for `POST /api/runs`), but there's no mechanism to claim those runs after signing in. `RunController::store` sets `user_id` to `$request->user()?->id`, which is `null` for anonymous users. Runs created before auth are permanently anonymous.
- Files: `backend/app/Http/Controllers/RunController.php`, `backend/app/Models/Run.php`
- Impact: Users who launch a workflow then sign in lose access to their run history.
- Fix approach: Add a `claim_runs` endpoint that associates anonymous runs (by IP or cookie) with the newly authenticated user.

**Missing `completed_at` index for recent runs endpoint:**
- Issue: `RunController::recent()` queries `where('status', 'completed')->whereNull('user_id')->whereNotNull('result')->orderByDesc('completed_at')->limit(6)`. The `runs` table has indexes on `status` and `created_at`, but **no index on `completed_at`**. As the runs table grows, this query will require a full table scan or filesort.
- Files: `backend/app/Http/Controllers/RunController.php`, `backend/database/migrations/`
- Impact: Slow query on the public home page as run volume increases.
- Fix approach: Add a migration creating a composite index on `(status, user_id, completed_at)` to cover both the `recent()` and `RunHistoryController::index()` query patterns.

**Home.tsx growing large (403 lines):**
- Issue: `Home.tsx` is 403 lines with the trending card, real-runs fetch, fallback logic, and all section JSX inline. The `recent-section` alone is ~70 lines with a ternary branching between real and static runs.
- Files: `backend/resources/ts/components/Home.tsx`
- Impact: Unrelated UI changes conflict easily; hard to unit-test the recent-runs section in isolation.
- Fix approach: Extract `RecentRunsSection` and `TrendingCard` as sub-components, receiving `realRuns`, `navigate`, `setUrl`, `setSelected` as props.

## Security

**Latent SSRF risk via stored `base_url`:**
- Issue: `StoreProviderCredentialRequest` accepts a user-supplied `base_url` (`'nullable', 'url', 'max:2048'`) and `ProviderCredentialController::store` encrypts and stores it. The `url` rule validates format but does NOT block localhost, private IPs, or cloud metadata endpoints (e.g. `169.254.169.254`). Currently the stored `base_url` is NOT passed to provider constructors (`AiProviderRegistry::get()` only accepts `providerId` and `apiKey`), so the risk is latent — but it will become exploitable if the stored `base_url` is wired into provider instantiation.
- Files: `backend/app/Http/Requests/StoreProviderCredentialRequest.php`, `backend/app/Http/Controllers/ProviderCredentialController.php`, `backend/app/Support/AiProviderRegistry.php`
- Impact: If stored `base_url` is used for provider construction, the server could be used for SSRF attacks.
- Fix approach: Implement URL validation (block localhost, private IPs, cloud metadata endpoints) in `StoreProviderCredentialRequest` before allowing user-supplied base URLs to reach provider constructors. ADR-0016 mentions `encrypted_base_url` storage but does not address SSRF — this should be documented when wiring stored `base_url` into provider construction.

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
