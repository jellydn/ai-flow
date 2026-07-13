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

## Security

**Latent SSRF risk via stored `base_url`:**
- Issue: `StoreProviderCredentialRequest` accepts a user-supplied `base_url` (`'nullable', 'url', 'max:2048'`) and `ProviderCredentialController::store` encrypts and stores it. The `url` rule validates format but does NOT block localhost, private IPs, or cloud metadata endpoints (e.g. `169.254.169.254`). Currently the stored `base_url` is NOT passed to provider constructors (`AiProviderRegistry::get()` only accepts `providerId` and `apiKey`), so the risk is latent — but it will become exploitable if the stored `base_url` is wired into provider instantiation.
- Files: `backend/app/Http/Requests/StoreProviderCredentialRequest.php`, `backend/app/Http/Controllers/ProviderCredentialController.php`, `backend/app/Support/AiProviderRegistry.php`
- Impact: If stored `base_url` is used for provider construction, the server could be used for SSRF attacks.
- Fix approach: Implement URL validation (block localhost, private IPs, cloud metadata endpoints) in `StoreProviderCredentialRequest` before allowing user-supplied base URLs to reach provider constructors. ADR-0016 mentions `encrypted_base_url` storage but does not address SSRF — this should be documented when wiring stored `base_url` into provider construction.

**No rate limiting on credential verification (fixed):**
- Issue: `POST /api/user/provider-credentials/{id}/verify` had no rate limit, allowing excessive outbound API calls.
- Files: `backend/routes/api.php`, `backend/app/Providers/AppServiceProvider.php`
- Status: ✅ Fixed — Added `throttle:credentials` rate limiter (10/min/user) in `AppServiceProvider` and applied to the verify route.
- Impact: None — credential verification is now rate-limited.

**SSE polling resource usage:**
- Issue: `RunStreamer` polls the database every second for up to 55 seconds per SSE connection. With 30 concurrent streams (rate limit), that's 30 DB queries/second.
- Files: `backend/app/Services/RunStreamer.php`
- Impact: Database load scales linearly with concurrent SSE connections.
- Fix approach: Consider Redis pub/sub or WebSocket for production scale; the current DB-polling approach is fine for MVP but may need optimization.

## Performance

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

**Demo report view reset (fixed):**
- Issue: A `useEffect` in `App.tsx` reset the view to `home` when `pathRunId === null` and view type wasn't `home` or `demo-running`. When demo steps completed → `report` view, the effect immediately killed the report. Fixed in commit `680e36a` by excluding `"report"` from the reset condition.
- Files: `backend/resources/ts/components/App.tsx`
- Status: ✅ Fixed
- Impact: E2E test `demo-full-flow.spec.ts:63` was timing out because "Missing authorization check" text never rendered.

## Fragile Areas

**Squash-merge + stacked PR rebasing:**
- Issue: When using `gh stack` with squash merges, each lower PR squash-merge changes the commit hash, causing all upstack branches to conflict on files that were in the squash-merged PR. Resolving requires `git checkout --ours` on every conflicting file during rebase.
- Files: N/A (process issue)
- Impact: Time-consuming rebase conflicts when merging stacked PRs via squash.
- Fix approach: Use regular merge commits for stacked PRs, or merge all PRs in the stack at once via `gh stack merge`.

**`ProviderCredential::forceCreate()` in tests:**
- Issue: Tests use `forceCreate()` to bypass mass-assignment protection on `user_id`. If the `$fillable` array is changed to include `user_id`, tests would silently switch to `create()` behavior.
- Files: `backend/tests/Feature/SavedCredentialLaunchTest.php`, `backend/tests/Feature/AccountDeletionTest.php`
- Impact: Tests are slightly fragile against model changes.
- Fix approach: Acceptable for test code; document the pattern.

**E2E test depends on specific demo finding text:**
- Issue: `demo-full-flow.spec.ts` asserts `page.getByText("Missing authorization check")` — if the demo findings in `launcherMeta.ts` change, the E2E test breaks.
- Files: `backend/tests/E2E/flows/demo-full-flow.spec.ts`, `backend/resources/ts/data/launcherMeta.ts`
- Impact: E2E test is tightly coupled to demo data content.
- Fix approach: Use a more resilient selector (e.g. `.finding h3` or test ID) instead of specific text content.

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
