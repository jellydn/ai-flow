# Codebase Concerns

**Analysis Date:** 2026-07-15

## Tech Debt

**No claim flow for anonymous runs:**
- Issue: Anonymous users can create runs (`POST /api/runs` is public). `RunController::store` sets `user_id` to `$request->user()?->id` (null when unsigned). Runs started before sign-in stay anonymous and never appear in run history.
- Files: `backend/app/Http/Controllers/RunController.php`, `backend/app/Models/Run.php`, `backend/resources/ts/components/RunHistory.tsx`
- Impact: Users who launch then authenticate lose history linkage for that session’s runs.
- Fix approach: Claim endpoint (cookie/session token or signed run IDs) to attach anonymous runs to the authenticated user.

**Stored `encrypted_base_url` not applied at runtime:**
- Issue: Provider credentials persist optional `encrypted_base_url` after validation, but `ExecuteLauncherJob` / `AiProviderRegistry::get()` only inject API keys. Adapters read `config('services.openai.base_url')` and `openrouter_base_url`, not per-credential URLs.
- Files: `backend/app/Models/ProviderCredential.php`, `backend/app/Jobs/ExecuteLauncherJob.php`, `backend/app/Support/AiProviderRegistry.php`, `backend/app/Services/OpenAIProvider.php`, `backend/app/Services/OpenRouterProvider.php`
- Impact: UI/storage suggests custom endpoints work; BYOK users pointing at compatible proxies cannot use saved base URLs yet.
- Fix approach: Decrypt base URL in the job (same lifetime rules as API key), pass into provider constructors, and add integration tests.

**Home.tsx concentration:**
- Issue: Home bundles trending, recent runs, and launch UX in one component (~287 lines).
- Files: `backend/resources/ts/components/Home.tsx`
- Impact: Higher merge conflict risk; harder to unit-test recent-runs block in isolation.
- Fix approach: Extract `RecentRunsSection` / trending cards when next touching home layout.

**Legacy API field aliases:**
- Issue: `StoreRunRequest` still accepts `flow_id` and `input.url` alongside `launcher` / `source_url`.
- Files: `backend/app/Http/Requests/StoreRunRequest.php`, `backend/routes/api.php` (`/executions`, `/flows` aliases)
- Impact: Two naming schemes to document and test indefinitely unless deprecated with sunset.
- Fix approach: Deprecation headers or docs; remove aliases once clients migrate.

## Known Bugs

No confirmed production bugs at current HEAD (`fix/concerns-base-url-index`, commit `41b6620`).

## Security Considerations

**Credential `base_url` SSRF (mitigated at validation):**
- Risk: Malicious base URLs could target metadata or internal networks if workers fetch them.
- Files: `backend/app/Rules/PublicHttpUrl.php`, `backend/app/Http/Requests/StoreProviderCredentialRequest.php`, `backend/app/Http/Requests/UpdateProviderCredentialRequest.php`, `backend/tests/Feature/ProviderCredentialBaseUrlValidationTest.php`
- Current mitigation: `PublicHttpUrl` on create/update — HTTP/HTTPS only, blocks localhost/metadata hostnames, private/reserved IPs, and DNS A/AAAA to non-public IPs when resolution succeeds.
- Recommendations: Re-validate at outbound fetch time when credential `base_url` is wired into providers; consider blocking link-local and tightening DNS rebinding (TOCTOU between validation and fetch).

**Run input URLs (GitHub-only):**
- Risk: Open redirect or non-GitHub targets in workers.
- Files: `backend/app/Http/Requests/StoreRunRequest.php` (`regex` for `github.com`), `backend/app/Services/GitHubService.php` (HTTPS + host allowlist)
- Current mitigation: Validation + parse-time checks in `GitHubService::parse()`.
- Recommendations: Keep GitHub fetches in the queue worker only; never add synchronous GitHub/AI calls on HTTP request cycle.

**Credential encryption uses `APP_KEY`:**
- Risk: `APP_KEY` rotation or leak affects all stored credentials; ADR mentions separate key not implemented.
- Files: `backend/app/Security/CredentialCipher.php`, `doc/adr/0016-stored-encrypted-byok-credentials.md`
- Current mitigation: `Crypt::encryptString`, hidden columns, decrypt only in job verify path and `resolveApiKey()`.
- Recommendations: Implement or formally defer `CREDENTIAL_ENCRYPTION_KEY`; document rotation runbook.

**Anonymous run visibility:**
- Risk: Anyone with a run UUID can view public (`user_id` null) runs per policy; share links are capability URLs.
- Files: `backend/routes/api.php`, run policies / `authorize('view')`
- Recommendations: Optional signed share tokens or expiring links for sensitive deployments.

## Performance Bottlenecks

**SSE connection churn:**
- Problem: `RunStreamer` runs up to ~55s per connection; clients reconnect via `useRunSubscription` after terminal events or errors. Rate limit `runs-stream` is 30/min/IP.
- Files: `backend/app/Services/RunStreamer.php`, `backend/app/Http/Controllers/RunController.php`, `backend/resources/ts/hooks/useRunSubscription.ts`, `backend/DOKKU_DEPLOY.md` (proxy-buffering off, 75s read timeout)
- Cause: Long-poll SSE model + 1s poll interval when cache version unchanged still wakes the loop; cache miss path hits DB each version bump.
- Improvement path: Redis pub/sub or shorter client reconnect strategy; scale DB/cache for concurrent streams.

**Database polling in `RunStreamer`:**
- Problem: When cache version is null (e.g. array driver), every loop iteration calls `fetchSnapshot()` → `refresh()` on `runs`.
- Files: `backend/app/Services/RunStreamer.php`, `backend/app/Listeners/CacheRunProgressedVersion.php`
- Cause: Fallback path for tests; production should use database/redis cache store.
- Improvement path: Ensure `CACHE_STORE` is not `array` in production; monitor query rate per active SSE.

**GitHub context cache:**
- Problem: `Cache::remember` 10 minutes; with `CACHE_STORE=database`, cache adds DB reads/writes; cache miss triggers full GitHub REST fetch in the worker.
- Files: `backend/app/Services/GitHubService.php`
- Cause: No Redis by default in docs; cold URLs are slow.
- Improvement path: Redis/Memcached in production; optional warming for popular repos.

**Run history search:**
- Problem: `RunHistoryController` uses `source_url LIKE %search%` without dedicated index.
- Files: `backend/app/Http/Controllers/RunHistoryController.php`
- Cause: Pattern leading wildcard prevents simple B-tree use.
- Improvement path: Trigram/GIN on Postgres or restrict search to prefix/exact repo slug.

**Frontend single bundle:**
- Problem: No `React.lazy()` or Vite `manualChunks`; authenticated views ship on first paint.
- Files: `backend/vite.config.ts`, `backend/resources/ts/app.tsx`, large shells `App.tsx` (~413 lines), `SignIn.tsx` (~447 lines)
- Improvement path: Lazy-load dashboard, provider settings, run history.

## Fragile Areas

**Stacked PR / squash-merge workflow:**
- Files: N/A (process)
- Why fragile: Squash-merge rewrites SHAs; upstack branches conflict on shared files.
- Safe modification: Prefer merge commits for stacks or merge stack atomically.
- Test coverage: N/A

**`ProviderCredential::forceCreate()` in tests:**
- Files: `backend/tests/Feature/SavedCredentialLaunchTest.php`, `backend/tests/Feature/AccountDeletionTest.php`
- Why fragile: Bypasses mass assignment for `user_id`; model `$fillable` changes could alter behavior silently.
- Safe modification: Keep `user_id` out of `$fillable`; use factories with explicit ownership helpers.
- Test coverage: Adequate for current flows.

**AI JSON schema validation:**
- Files: `backend/app/Services/RunExecutor.php`, launcher `outputSchema` in `BaseLauncher`
- Why fragile: Provider drift or truncated JSON fails runs after GitHub work completed.
- Safe modification: Extend schema tests per launcher; log validation failures without leaking keys.
- Test coverage: Feature tests mock AI; limited live-provider contract tests.

## Scaling Limits

**Queue workers:**
- Current capacity: Single worker process type in Dokku Procfile; job timeout 120s, 2 tries.
- Limit: Throughput = workers × (1 / median job duration); GitHub + LLM bound.
- Scaling path: Horizontal queue workers; monitor `jobs` table depth; avoid `QUEUE_CONNECTION=sync` in production.

**SSE + PHP-FPM:**
- Current capacity: ~30 stream attempts/min/IP; each holds a worker up to ~55s.
- Limit: FPM pool exhaustion under many concurrent users watching runs.
- Scaling path: Dedicated SSE service or edge-friendly streaming; increase pool size and timeouts coherently with nginx.

**IP rate limits on runs:**
- Current capacity: `runs` 5/hr/IP for create.
- Limit: Shared NAT blocks legitimate users.
- Scaling path: Authenticated higher limits; CAPTCHA or account-based quotas.

## Dependencies at Risk

**`turso/libsql-laravel` vs Laravel 13:**
- Risk: Not supported on Laravel 13; local SQLite only for dev.
- Impact: Cannot adopt Turso without framework upgrade path or alternate driver.
- Migration plan: Managed Postgres/MySQL (documented in `AGENTS.md`, `backend/README.md`).

**Pinned AI provider HTTP APIs:**
- Risk: OpenAI/Anthropic/Gemini/OpenRouter API shape changes break adapters.
- Impact: All launchers fail until adapters updated.
- Migration plan: Contract tests with recorded fixtures; registry isolates provider classes.

## Missing Critical Features

**No token/cost metadata on runs:**
- Problem: No `tokens_used` / cost columns; usage not persisted from provider responses.
- Blocks: Cost dashboards, per-user billing, abuse detection.

**No completion notifications:**
- Problem: No email/webhook on terminal status.
- Blocks: Fire-and-forget workflows without keeping the tab open.

**No anonymous-run claim:**
- Problem: See tech debt above.
- Blocks: Coherent post-login history for casual try-then-sign-up flows.

## Test Coverage Gaps

**Per-credential `base_url` execution:**
- What's not tested: End-to-end job using decrypted custom base URL (field unused in runtime).
- Files: `backend/tests/Feature/ExecuteLauncherJobTest.php` (server config URL only)
- Risk: SSRF hardening validated at HTTP layer but bypass if fetch logic ships without tests.
- Priority: High when wiring runtime base URL.

**`PublicHttpUrl` DNS edge cases:**
- What's not tested: NXDOMAIN pass-through, IPv6-only resolution failures, rebinding races.
- Files: `backend/tests/Feature/ProviderCredentialBaseUrlValidationTest.php`
- Risk: Rare bypass or false rejects.
- Priority: Medium

**Frontend SSE fallback:**
- What's not tested: Systematic Playwright coverage for EventSource failure → polling path.
- Files: `backend/resources/ts/hooks/useRunSubscription.ts`, E2E mostly demo/real auth flow.
- Risk: Regressions in browsers with strict SSE policies.
- Priority: Medium

**Large UI components:**
- What's not tested: `SignIn.tsx` / `Home.tsx` have partial RTL coverage; not exhaustive.
- Files: `backend/resources/ts/components/__tests__/`
- Risk: Auth and launch regressions.
- Priority: Medium

## Documentation Gaps

**`CREDENTIAL_ENCRYPTION_KEY` in ADR-0016:**
- Issue: Documented as future enhancement; implementation uses `APP_KEY` only.
- Files: `doc/adr/0016-stored-encrypted-byok-credentials.md`
- Fix: Update ADR status note or implement dedicated key.

**Deploy docs split:**
- Issue: No repo-root `DEPLOY.md`; operators must read `backend/DOKKU_DEPLOY.md` and `backend/CLOUD_DEPLOY.md`.
- Impact: Easy to miss SSE nginx settings or `DB_URL` vs Dokku `DATABASE_URL` mapping.

**No `autoresearch.jsonl` / `autoresearch.ideas.md` in repo:**
- Impact: Automated concern mining from those files not applicable; rely on code scan and ADRs.

## Codebase Scan Notes

- **TODO / FIXME / HACK:** None found in `backend/` PHP/TS/TSX (2026-07-15 scan).
- **PR #57 / branch `fix/concerns-base-url-index` (commit `41b6620`):** Landed `PublicHttpUrl`, credential request validation, composite index `runs_status_user_completed_at_index` on `(status, user_id, completed_at)` for `GET /api/runs/recent`, and `ProviderCredentialBaseUrlValidationTest`.

## Previously Fixed (for reference)

- ✅ Redundant `config('services.openai.providers')` — `AiProviderRegistry` single source of truth.
- ✅ Credential verify rate limit — `throttle:credentials` (10/min/user) on verify route.
- ✅ Recent runs query index — `2026_07_15_000001_add_recent_runs_index_to_runs_table.php`.
- ✅ Credential `base_url` validation — `PublicHttpUrl` on store/update.
- ✅ Demo report view reset, E2E brittle text assertions, silent `catch {}` → `logger.warn()` across frontend.
- ✅ `AGENTS.md` remote naming (`origin` = GitHub, `dokku` = staging).

---

*Concerns audit: 2026-07-15*
