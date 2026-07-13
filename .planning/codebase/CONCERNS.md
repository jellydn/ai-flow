# Concerns

**Analysis Date:** 2026-07-14

## Tech Debt

**Legacy `AiProviders.php` factory:**
- Issue: `backend/app/Support/AiProviders.php` is a legacy factory that duplicates the functionality of `AiProviderRegistry`. The registry was introduced as a replacement, but the old factory is kept for backward compatibility.
- Files: `backend/app/Support/AiProviders.php`, `backend/app/Support/AiProviderRegistry.php`
- Impact: Two code paths for provider resolution; confusion about which to use.
- Fix approach: Audit all usages of `AiProviders::createProvider()` and migrate to `AiProviderRegistry::get()`, then remove the legacy factory.

**Redundant `config('services.openai.providers')` array:**
- Issue: The `providers` array in `config/services.php` under the `openai` key lists `['openai', 'openrouter', 'anthropic', 'gemini']` but is redundant — the authoritative source is `AiProviderRegistry::PROVIDERS`.
- Files: `backend/config/services.php`
- Impact: Two sources of truth for provider IDs; risk of drift.
- Fix approach: Remove the `providers` array from config; use `AiProviderRegistry::ids()` everywhere.

**No claim flow for anonymous runs:**
- Issue: Anonymous users can create runs (no auth required for `POST /api/runs`), but there's no mechanism to claim those runs after signing in. Runs created before auth are permanently anonymous.
- Files: `backend/app/Http/Controllers/RunController.php`, `backend/app/Models/Run.php`
- Impact: Users who launch a workflow then sign in lose access to their run history.
- Fix approach: Add a `claim_runs` endpoint that associates anonymous runs (by IP or cookie) with the newly authenticated user.

**`libsql` connection in database config:**
- Issue: `config/database.php` retains a `libsql` connection for a future Turso return, but `turso/libsql-laravel` doesn't support Laravel 13 yet.
- Files: `backend/config/database.php`
- Impact: Dead configuration that may confuse developers.
- Fix approach: Remove the `libsql` connection until the package supports Laravel 13.

## Security

**SSRF protection deferred:**
- Issue: No user-supplied custom base URLs are supported for AI providers, but if this feature is added, there's no SSRF validation. The `OpenRouterProvider` constructor accepts `$baseUrl` and `$referer` without validating them.
- Files: `backend/app/Services/OpenRouterProvider.php`, `backend/app/Http/Controllers/ProviderCredentialController.php`
- Impact: If user-supplied base URLs are introduced, the server could be used for SSRF attacks.
- Fix approach: Implement URL validation (block localhost, private IPs, cloud metadata endpoints) before allowing user-supplied base URLs. Documented as deferred in ADR-0016.

**No rate limiting on credential verification:**
- Issue: `POST /api/user/provider-credentials/{id}/verify` has no rate limit. An authenticated user could repeatedly verify credentials, causing excessive outbound API calls.
- Files: `backend/routes/api.php`, `backend/app/Http/Controllers/ProviderCredentialController.php`
- Impact: Potential abuse for outbound API probing.
- Fix approach: Add a `throttle:credentials` rate limiter (e.g. 10/min/user).

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

**No query optimization for run history:**
- Issue: `RunHistoryController::index` fetches all runs for a user without pagination. If a user accumulates hundreds of runs, this could become slow.
- Files: `backend/app/Http/Controllers/RunHistoryController.php`
- Impact: Slow response for power users with many runs.
- Fix approach: Add pagination (e.g. `->paginate(20)`) and return cursor-based links.

**Frontend bundle size:**
- Issue: The Vite build bundles all React components into a single chunk. No code splitting or lazy loading.
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

**`AGENTS.md` references outdated remote names:**
- Issue: `AGENTS.md` mentions `origin` may point at Amp git and `github` remote is `jellydn/ai-flow`. In practice, `origin` is `github.com/jellydn/ai-flow` and `dokku` is the staging remote.
- Files: `AGENTS.md`
- Impact: Minor confusion for developers reading the docs.
- Fix approach: Update the remote references in `AGENTS.md`.

**ADR-0017 mentions `CREDENTIAL_ENCRYPTION_KEY` env var:**
- Issue: ADR-0017 documents a future `CREDENTIAL_ENCRYPTION_KEY` env var for dedicated credential encryption, but this is not implemented — credentials use `APP_KEY` via Laravel `Crypt`.
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
