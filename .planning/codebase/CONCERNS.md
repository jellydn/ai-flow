# Codebase Concerns

**Analysis Date:** 2026-07-13

## Tech Debt

**Multi-provider stack half-wired:**
- Issue: `AnthropicProvider` and `GeminiProvider` exist, and `ProviderController` advertises anthropic/gemini/openrouter metadata, but DI always binds `AIProviderInterface` → `OpenAIProvider`, and `config('services.openai.providers')` only allows `['openai']`. Saved credentials, verify, and BYOK cannot select non-OpenAI providers end-to-end. ADR 0011 also describes an `AIProviderFactoryInterface` that is not present in code.
- Files: `backend/app/Providers/AppServiceProvider.php`, `backend/config/services.php`, `backend/app/Services/AnthropicProvider.php`, `backend/app/Services/GeminiProvider.php`, `backend/app/Http/Controllers/ProviderController.php`, `backend/app/Http/Requests/StoreRunRequest.php`, `doc/adr/0011-ai-provider-interface-openai-json-schema.md`
- Impact: Dead code paths, misleading API (`GET /api/providers` can list models for IDs clients cannot use), incomplete product story for multi-provider.
- Fix approach: Introduce a real factory mapping provider ID → adapter; expand `services.openai.providers` (or a dedicated `services.ai.providers` list); route verify/generate/BYOK through the factory; align frontend `createRun` with selected provider.

**Saved credentials not used for runs:**
- Issue: `provider_credentials` CRUD, encryption, and `runs.provider_credential_id` exist, but `RunController::store` only accepts a one-shot `provider.api_key` in the request body. No path loads the user’s default credential or sets `provider_credential_id` / `provider` / `model` on the run. `last_used_at` is never updated.
- Files: `backend/app/Http/Controllers/RunController.php`, `backend/app/Http/Controllers/ProviderCredentialController.php`, `backend/app/Models/Run.php`, `backend/database/migrations/2026_07_13_000004_add_ownership_to_runs_table.php`, `backend/resources/ts/services/run.ts`, `backend/resources/ts/components/Home.tsx`
- Impact: Credential storage is effectively a settings vault disconnected from execution; users must re-paste BYOK keys on every launch.
- Fix approach: Accept `provider_credential_id` (or resolve default for provider), decrypt only inside the job, set run ownership metadata, update `last_used_at`.

**Retry drops BYOK secrets:**
- Issue: `RunHistoryController::retry` dispatches `ExecuteLauncherJob::dispatch($newRun->id, $newRun->provider)` with no API key and no credential lookup. Original BYOK keys are never stored on the run (by design), so retries always fall back to the server key.
- Files: `backend/app/Http/Controllers/RunHistoryController.php`, `backend/app/Jobs/ExecuteLauncherJob.php`
- Impact: Retried runs may bill the server key or fail if no server key is configured; behavior differs from the original BYOK run.
- Fix approach: Persist `provider_credential_id` on first run and reuse it on retry; refuse retry when neither credential nor server key is available.

**Custom JsonSchemaValidator is incomplete:**
- Issue: Hand-rolled validator supports type/required/enum/nested object/array and `additionalProperties: false`, but missing type defaults to “accept anything”, no `minItems`/`maxLength`/format/oneOf, and OpenAI `strict` schema requirements (e.g. all properties required) are not enforced here for non-OpenAI providers.
- Files: `backend/app/Services/JsonSchemaValidator.php`, `backend/app/Launchers/BaseLauncher.php`, `doc/adr/0011-ai-provider-interface-openai-json-schema.md`
- Impact: Anthropic/Gemini paths (prompt-only JSON) can pass weaker shapes than OpenAI strict mode; schema drift is hard to detect.
- Fix approach: Prefer a maintained JSON Schema library, or expand validator + add dedicated unit tests for every launcher schema edge case.

**Magic-link `redirect_to` is incomplete:**
- Issue: `MagicLinkController::request` accepts `redirect_to` and `MagicLinkMail` appends a safe query param, but `verify()` ignores `redirect_to` and always uses `redirect()->intended(config('app.frontend_url', '/dashboard'))`.
- Files: `backend/app/Http/Controllers/Auth/MagicLinkController.php`, `backend/app/Mail/MagicLinkMail.php`
- Impact: Deep-link return after sign-in does not work; dead/misleading API surface.
- Fix approach: After login, honor validated `redirect_to` (relative/same-origin only) or remove the parameter entirely.

**Duplicate API aliases without shared router group:**
- Issue: `/api/flows` ≡ `/api/launchers` and `/api/executions` ≡ `/api/runs` are duplicated route closures rather than a single group with dual paths.
- Files: `backend/routes/api.php`
- Impact: Future middleware/rate-limit changes can diverge between aliases.
- Fix approach: Register once and map aliases, or document deprecation and remove one pair.

**Demo mode vs live product surface:**
- Issue: `VITE_DEMO_MODE=true` simulates the full UI without queue/API; marketing copy still claims live BYOK/shareable runs. Restart Vite after env change is required (Agents.md gotcha).
- Files: `backend/resources/ts/components/App.tsx`, `backend/resources/ts/data/launcherMeta.ts`, `backend/.env.example`, `Agents.md`
- Impact: Demo/staging confusion; false confidence that workers are healthy.
- Fix approach: Surface a visible “demo mode” badge; fail closed if demo is on in production builds.

**Launcher metadata dual sources:**
- Issue: Backend seeders own truth for slugs/prompts/schema; frontend also has `staticLaunchers` / `launcherMetaBySlug` for icons, demo findings, and fallback when `getLaunchers()` fails.
- Files: `backend/database/seeders/DatabaseSeeder.php`, `backend/app/Launchers/*`, `backend/resources/ts/data/launcherMeta.ts`
- Impact: UI can show launchers/icons that no longer match active DB rows or vice versa.
- Fix approach: Drive icons/metadata from API or generate frontend constants from the same source as seeders.

## Known Bugs

**Session auth not wired to API middleware group:**
- Symptoms: Browser SPA signs in via magic link (session cookie on web routes), but `api` middleware group only has `SubstituteBindings`—no `StartSession` / Sanctum stateful middleware. Authenticated API routes can always 401 in production; `$request->user()` on `POST /api/runs` stays null so runs never attach `user_id`.
- Files: `backend/bootstrap/app.php`, `backend/routes/api.php`, `backend/routes/web.php`, `backend/routes/auth.php`, `backend/config/auth.php`, `backend/app/Http/Controllers/RunController.php`
- Trigger: Complete magic-link login in the browser, then call `GET /api/user` or create a run expecting ownership.
- Workaround: Feature tests use `actingAs()` which bypasses real session middleware, so CI can pass while the SPA is broken. Fix by enabling stateful SPA auth (`$middleware->statefulApi()` + Sanctum, or move authenticated routes under `web`, or prepend session middleware to `api`).

**CSRF token meta unused by frontend HTTP client:**
- Symptoms: `app.blade.php` emits `csrf-token`, but `resources/ts/lib/http.ts` never sends `X-CSRF-TOKEN` / `X-XSRF-TOKEN`. Web-middleware POSTs such as `/auth/magic-link` and `/auth/logout` can return 419.
- Files: `backend/resources/views/app.blade.php`, `backend/resources/ts/lib/http.ts`, `backend/resources/ts/services/auth.ts`, `backend/routes/auth.php`
- Trigger: Submit Sign In form from the SPA in a real browser against the Laravel host.
- Workaround: Tests use `postJson` without full browser CSRF; production needs cookie/header CSRF wiring or explicit exclusion with alternative protection.

**`decodeUser` expects string id, API returns integer:**
- Symptoms: `UserResource` exposes integer `id`; frontend does `Number(assertString(data.id, "id"))`, which throws when `id` is a JSON number.
- Files: `backend/resources/ts/services/auth.ts`, `backend/app/Http/Resources/UserResource.php`
- Trigger: Successful `GET /api/user` after auth is fixed.
- Workaround: None in UI—`fetchUser` catch treats user as logged out (`App.tsx`).

**Job `$tries = 2` rarely applies to application failures:**
- Symptoms: `RunExecutor` and `ExecuteLauncherJob::failRun` catch throwables, mark the run `failed`, and return successfully from the worker’s perspective—no exception for queue retry. Transient GitHub/AI blips do not get a second attempt unless the process dies mid-handle.
- Files: `backend/app/Jobs/ExecuteLauncherJob.php`, `backend/app/Services/RunExecutor.php`, `doc/adr/0008-queue-backed-execute-launcher-job.md`
- Trigger: Any handled runtime failure (invalid JSON, 5xx from provider, rate limit).
- Workaround: Manual retry API for owned runs only; anonymous users cannot retry.

**Stuck-run reaper scheduled but never executed in deploy stack:**
- Symptoms: `Schedule::command(ReapStuckRuns::class)->everyMinute()->environments(['production'])` exists, but Dockerfile/supervisord/Procfile have no `schedule:run` / cron. Worker timeout or crash leaves `status=running` forever.
- Files: `backend/routes/console.php`, `backend/app/Console/Commands/ReapStuckRuns.php`, `backend/docker/supervisor/supervisord.conf`, `backend/Procfile`, `backend/Dockerfile`
- Trigger: Kill worker while a run is `running`, or exceed job timeout without clean failure handling.
- Workaround: Manually `php artisan app:reap-stuck-runs`; add scheduler process or run reaper from worker sidecar.

**SSE 55s window with no client reconnect:**
- Symptoms: `RunStreamer` ends after ~55s; `useRunSubscription` falls back to 1.5s REST polling only on EventSource `error`, not on clean stream end without terminal event. Long AI runs can stall the UI until a late poll or page refresh.
- Files: `backend/app/Services/RunStreamer.php`, `backend/resources/ts/hooks/useRunSubscription.ts`, `doc/adr/0013-sse-run-stream-via-database-polling.md`
- Trigger: Run lasting >55s with SSE still open (proxy-friendly) or stream ending without `completed`/`failed`.
- Workaround: REST poll fallback eventually if EventSource errors; improve by reconnecting SSE with `Last-Event-ID` or always dual-running lightweight poll.

## Security Considerations

**Public unauthenticated runs burn shared OpenAI budget:**
- Risk: Anyone can `POST /api/runs` (authorize always true) and consume `OPENAI_API_KEY` / GitHub quota. Rate limit is 5/hour/IP only—shared NAT and IP rotation remain abuse paths (ADR 0014 negatives).
- Files: `backend/app/Http/Requests/StoreRunRequest.php`, `backend/app/Providers/AppServiceProvider.php`, `backend/routes/api.php`, `doc/adr/0014-api-throttling-and-public-unauthenticated-runs.md`
- Current mitigation: IP throttle `runs` (5/hr), URL must be public HTTPS github.com; production forbids `QUEUE_CONNECTION=sync`.
- Recommendations: Require auth for server-key runs; allow anonymous only with BYOK; add CAPTCHA or global spend caps; stricter per-account quotas when auth works.

**BYOK API keys on the wire and in encrypted jobs:**
- Risk: Clients POST raw `provider.api_key` over the public API; key is held on `ExecuteLauncherJob` (mitigated by `ShouldBeEncrypted` + `APP_KEY`). Key rotation of `APP_KEY` breaks queued jobs and stored credential ciphertext.
- Files: `backend/app/Http/Controllers/RunController.php`, `backend/app/Jobs/ExecuteLauncherJob.php`, `backend/app/Security/CredentialCipher.php`, `backend/tests/Feature/ExecuteLauncherJobTest.php`
- Current mitigation: Job encryption test asserts plaintext not in `jobs` payload; keys not persisted on run rows; error logs avoid message bodies with secrets.
- Recommendations: Prefer credential IDs over BYOK body fields; document stable `APP_KEY`; never log request bodies; ensure TLS everywhere (`URL::forceScheme` only when `APP_URL` is https).

**Anonymous run results are world-readable:**
- Risk: `RunPolicy::view` allows anyone to view runs with `user_id = null`. Shareable `/runs/:uuid` is intentional marketing, but UUIDs still leak full reports if discovered or logged.
- Files: `backend/app/Policies/RunPolicy.php`, `backend/app/Http/Resources/RunResource.php`, `backend/resources/ts/components/Report.tsx`, `backend/tests/Feature/RunOwnershipTest.php`
- Current mitigation: UUID primary keys; owner-only fields (`provider`, `model`) gated; private runs require owner.
- Recommendations: Optional unlisted expiry/TTL; redact sensitive finding content for unauthenticated viewers; rate-limit show/stream more tightly.

**Credential mask still decrypts full key for display:**
- Risk: `ProviderCredentialResource` calls `maskedKey()` which decrypts the full ciphertext on every list/show to produce a mask. Compromise of app process/memory or verbose error paths increases exposure window.
- Files: `backend/app/Http/Resources/ProviderCredentialResource.php`, `backend/app/Models/ProviderCredential.php`, `backend/app/Security/CredentialCipher.php`
- Current mitigation: Encrypted at rest with `Crypt`; `encrypted_*` hidden on model; API never returns full key.
- Recommendations: Store `key_prefix` / `key_hint` columns at write time so list endpoints never decrypt.

**Magic-link user creation and mail abuse:**
- Risk: Any email creates a user row (`firstOrCreate`); tokens accumulate with no prune job; 3/min/IP+email throttle can still spam inboxes if mail is misconfigured. `Auth::login($user, true)` issues long-lived remember cookies.
- Files: `backend/app/Http/Controllers/Auth/MagicLinkController.php`, `backend/app/Providers/AppServiceProvider.php`
- Current mitigation: Hashed tokens, 15-minute expiry, single-use `used_at`, generic response message.
- Recommendations: Rate-limit by IP alone more strictly; prune expired tokens; consider not creating users until first successful verify; set remember carefully.

**Trust proxies set to `*`:**
- Risk: `$middleware->trustProxies(at: '*')` trusts all `X-Forwarded-*`. If the app is ever exposed without a trusted edge proxy, clients can spoof IPs and evade rate limits.
- Files: `backend/bootstrap/app.php`
- Current mitigation: Documented for Dokku TLS termination.
- Recommendations: Restrict to known proxy CIDRs in production.

**Sensitive config logging guard is warning-only:**
- Risk: Production with `LOG_LEVEL=debug` only logs a warning; request/exception dumps may still capture secrets depending on handlers.
- Files: `backend/app/Providers/AppServiceProvider.php`, `backend/.env.example`
- Current mitigation: Warning in boot.
- Recommendations: Fail boot (like SQLite/sync checks) when `LOG_LEVEL=debug` in production.

**Prompt injection via public GitHub content:**
- Risk: Untrusted README/PR diffs/comments are concatenated into the AI prompt (`prompt_template` + encoded context). Malicious repos can steer report content.
- Files: `backend/app/Services/RunExecutor.php`, `backend/app/Services/GitHubContextAssembler.php`, `backend/app/Services/ContextEncoder.php`
- Current mitigation: Structured schema output; size truncation; no tool-calling.
- Recommendations: Delimit untrusted content clearly; system instructions to ignore instruction-like repo text; content safety review of results before share.

## Performance Bottlenecks

**SSE stream DB (or cache+DB) polling per viewer:**
- Problem: Each open stream polls up to ~55s at 1s intervals; cache version miss falls back to unconditional `refresh()` + `RunResource` JSON encode every tick.
- Files: `backend/app/Services/RunStreamer.php`, `backend/app/Listeners/CacheRunProgressedVersion.php`, `doc/adr/0013-sse-run-stream-via-database-polling.md`
- Cause: No Redis/broadcast driver; pull-based transport.
- Improvement path: Redis pub/sub or Laravel broadcasting; longer cache TTL; shorter payload events (diff/progress only).

**GitHub REST fan-out without clone, still heavy:**
- Problem: Repository context issues multiple sequential API calls including recursive git trees; monorepos pull large trees into PHP memory before slicing to 1000 paths.
- Files: `backend/app/Services/GitHubContextFetcher.php`, `backend/app/Services/GitHubContextAssembler.php`, `backend/app/Services/GitHubService.php`
- Cause: Synchronous multi-request fetch inside the job; 10-minute URL cache helps only for exact same URL.
- Improvement path: Concurrent HTTP where safe; shallow tree listings; cache partials by repo+ref; require `GITHUB_TOKEN` in production.

**Context truncation still builds large intermediate JSON:**
- Problem: Full assembled context is stored on `runs.source_context` mid-run, then encoded with up to 120KB prompt budget after multi-tier truncation.
- Files: `backend/app/Services/RunExecutor.php`, `backend/app/Services/ContextEncoder.php`
- Cause: Persist full context for progress/debug then null out; encode may still serialize huge trees first.
- Improvement path: Stream-bound encoding without persisting full context; compress or store object storage for debug only.

**AI HTTP timeouts vs job budget:**
- Problem: Default job timeout 120s; `OPENAI_TIMEOUT` defaults to 30 in config / 60 in `.env.example`; providers retry 2× with 500ms delay. Worst-case retries can approach job timeout; no mid-generate progress.
- Files: `backend/app/Jobs/ExecuteLauncherJob.php`, `backend/config/services.php`, `backend/app/Services/OpenAIProvider.php`, `backend/app/Services/AnthropicProvider.php`, `backend/app/Services/GeminiProvider.php`
- Cause: Single blocking generate call; timeout config shared oddly (`openai.timeout` used by all providers).
- Improvement path: Per-provider timeouts; progress heartbeats; shorter models for free tier.

**Database queue under load:**
- Problem: Default `QUEUE_CONNECTION=database` + `CACHE_STORE=database` + `SESSION_DRIVER=database` concentrate load on one Postgres/SQLite DB.
- Files: `backend/config/queue.php`, `backend/config/cache.php`, `backend/.env.example`, `backend/Procfile`
- Cause: MVP simplicity (ADR 0008).
- Improvement path: Redis for cache/queue/session before multi-worker scale-out.

## Fragile Areas

**ExecuteLauncherJob + RunExecutor failure contract:**
- Files: `backend/app/Jobs/ExecuteLauncherJob.php`, `backend/app/Services/RunExecutor.php`
- Why fragile: Partial progress arrays, mid-run `source_context`, and terminal status updates must stay consistent with SSE cache version bumps; any new early return can leave orphaned `running` rows if reaper is offline.
- Safe modification: Always clear `source_context`, set `completed_at`, dispatch `RunProgressed`; add feature tests for each new failure branch.
- Test coverage: Feature tests cover schema failure, BYOK log safety, unsupported provider; not timeout/orphan paths in real queue workers.

**GitHub URL parse + launcher input_type coupling:**
- Files: `backend/app/Services/GitHubService.php`, `backend/app/Services/RunExecutor.php`, `backend/app/Launchers/*`
- Why fragile: Only repo / `pull/{n}` / `issues/{n}` paths; extra path segments rejected; type mismatch fails at job time (after queue) not at validation.
- Safe modification: Mirror parse rules in `StoreRunRequest` or a shared value object; extend unit tests in `GitHubServiceTest`.
- Test coverage: Unit tests strong for parse/fetch; store request does not validate input_type vs URL shape.

**Output schema shared by all launchers:**
- Files: `backend/app/Launchers/BaseLauncher.php`
- Why fragile: One shared schema for review/plan/explain/doctor; product-specific fields would require coordinated frontend `decodeRunResult` changes.
- Safe modification: Per-launcher schemas with versioned result DTOs and decoder updates.
- Test coverage: Executor tests use the shared shape only.

**Frontend run decoding is strict:**
- Files: `backend/resources/ts/services/run.ts`, `backend/resources/ts/hooks/useRunSubscription.ts`
- Why fragile: Any backend field type change breaks SSE/REST decoding and surfaces as subscription errors.
- Safe modification: Evolve types with tests; make optional fields explicit.
- Test coverage: Only `RunHistory` component test; no decoder unit tests.

**Deploy env footguns (Dokku DB_URL, APP_KEY, SSE proxy):**
- Files: `backend/DOKKU_DEPLOY.md`, `backend/CLOUD_DEPLOY.md`, `Agents.md`, `backend/app/Providers/AppServiceProvider.php`
- Why fragile: Dokku `DATABASE_URL` ≠ Laravel `DB_URL`; rotating `APP_KEY` breaks encrypted jobs/credentials; SSE needs `proxy-buffering off` and 75s read timeout.
- Safe modification: Checklist in release; production boot guards already block sqlite/sync/weak sslmode.
- Test coverage: No deploy smoke tests in CI.

## Scaling Limits

**POST /api/runs rate limit:**
- Current capacity: 5 requests per hour per IP (`RateLimiter::for('runs')`).
- Limit: Shared offices/carriers share one bucket; determined abusers rotate IPs; each accepted request can cost multiple GitHub calls + one AI completion.
- Scaling path: Auth-based quotas, BYOK-only anonymous tier, Redis rate limiter, billing.

**SSE concurrent viewers:**
- Current capacity: Roughly one PHP worker/connection held ≤55s, polling every 1s; stream throttle 30/min/IP.
- Limit: Many open tabs exhaust PHP-FPM workers; database thrash under cache-miss fallback.
- Scaling path: Non-blocking event transport (Redis/SSE gateway), shorter poll, dedicated stream workers.

**GitHub unauthenticated vs token rate limits:**
- Current capacity: Without `GITHUB_TOKEN`, low shared IP limits (~60/hr); with token, higher secondary limits still apply per job multi-call fan-out.
- Limit: Bursts of distinct URLs bypass 10-minute cache and exhaust quota → 403 mapped to user-facing rate-limit error.
- Scaling path: Require token in production; cache by owner/repo/sha; back off on 403.

**Queue workers:**
- Current capacity: Dokku `worker` process type must be scaled separately from `web`; single worker serializes AI jobs (~tens of seconds each).
- Limit: Queue latency grows linearly with concurrent runs; job timeout 120s caps model size.
- Scaling path: Horizontal `queue:work` processes; Redis queue; separate high-priority queues for paid users.

**Context size:**
- Current capacity: ~120KB encoded context ceiling; PR files capped (e.g. 50 files fetch, 30×1k diffs after bound); tree 1000 paths / 250 after bound.
- Limit: Large PRs lose signal; analysis quality degrades silently (`truncated: true`).
- Scaling path: Smarter file selection (changed paths only, language filters); multi-pass models.

## Dependencies at Risk

**Laravel 13 + PHP version drift:**
- Risk: `composer.json` requires `php: ^8.4` and CI uses PHP 8.4; production Dockerfile uses `php:8.5-fpm-bookworm`. Agents.md documents PHP 8.4.
- Impact: Subtle runtime differences; untested 8.5 edge cases in deploy only.
- Migration plan: Align CI image and Dockerfile to the same PHP minor; document the supported version in one place.

**Hand-rolled multi-provider without shared SDK:**
- Risk: Three custom HTTP providers + OpenRouter via OpenAI-compatible base URL; Anthropic/Gemini ignore schema strictness and reuse `services.openai.timeout`.
- Impact: Behavioral inconsistency; broken adapters if vendor APIs change.
- Migration plan: Official SDKs or one OpenAI-compatible proxy for all; schema enforcement post-generation already present—keep it mandatory.

**Database queue / cache / sessions (no Redis package):**
- Risk: No Redis in `composer.json`; all async/state on SQL.
- Impact: Contended DB under concurrent SSE + jobs + sessions.
- Migration plan: Add Redis (or Valkey) for queue/cache/session before multi-tenant scale.

**Frontend test stack underused:**
- Risk: Vitest + Testing Library installed; CI runs `npm run test`, but only one component test file exists; failed Playwright demo artifacts under `test-results/` suggest flaky/e2e gaps.
- Impact: UI regressions (auth CSRF, decoder bugs) ship unnoticed.
- Migration plan: Decoder/service unit tests; critical-path component tests; optional Playwright smoke in CI.

**No broadcast driver package:**
- Risk: ADR 0013 notes no broadcast wiring; `RunProgressed` is cache-only.
- Impact: Cannot scale real-time UX without rework.
- Migration plan: Laravel Reverb/Pusher/Redis when multi-instance.

**Turso/libsql (documented non-support):**
- Risk: Agents.md notes `turso/libsql-laravel` does not support Laravel 13.
- Impact: Cannot use Turso for production without alternative driver.
- Migration plan: Stick to managed Postgres/MySQL as already required by production guards.

## Missing Critical Features

**End-to-end authenticated product loop:**
- Problem: Magic link, credentials, and run history APIs exist, but SPA session+CSRF+API middleware gaps and unused credentials mean the “signed-in power user” path is incomplete.
- Blocks: Owned run history, private runs, default BYOK, fair quotas.

**Provider factory and non-OpenAI execution:**
- Problem: Adapters incomplete; no model selection from client/credential defaults on generate.
- Blocks: Multi-provider product claims, OpenRouter-as-first-class provider ID (only via base_url override on OpenAI adapter).

**Operational scheduler / stuck-run automation in containers:**
- Problem: Reaper is code-complete but not invoked by deploy processes.
- Blocks: Reliable cleanup of timed-out runs without manual ops.

**Spending / usage accounting:**
- Problem: No tokens-used, cost, or per-user budget tracking on runs.
- Blocks: Cost control, chargeback, free-tier limits.

**Private GitHub repositories:**
- Problem: Only public HTTPS github.com; no user GitHub OAuth token for private repos (ADR 0010).
- Blocks: Private code review workflows.

**Idempotent/job-safe retries for transient provider errors:**
- Problem: Failures mark terminal without retry classification.
- Blocks: Higher success rate under flaky upstreams.

## Test Coverage Gaps

**Session/CSRF SPA integration:**
- What's not tested: Real browser cookie session for `/api/user/*`; CSRF on `/auth/magic-link`; frontend `decodeUser` against live `UserResource` JSON.
- Files: `backend/resources/ts/lib/http.ts`, `backend/resources/ts/services/auth.ts`, `backend/bootstrap/app.php`
- Risk: Auth and ownership appear green in PHPUnit while production SPA is unauthenticated.
- Priority: High

**JsonSchemaValidator unit suite:**
- What's not tested: Dedicated unit tests for type mismatches, additionalProperties, nested required, unknown type default-true behavior.
- Files: `backend/app/Services/JsonSchemaValidator.php`
- Risk: Silent acceptance of malformed AI payloads if OpenAI strict mode is off (other providers).
- Priority: High

**Retry + credential/BYOK integration:**
- What's not tested: Retry with original BYOK or `provider_credential_id`; assertion that retry job receives a usable key.
- Files: `backend/app/Http/Controllers/RunHistoryController.php`, `backend/tests/Feature/RunHistoryTest.php`
- Risk: “Retry” always uses server key; production cost or hard failure.
- Priority: High

**AnthropicProvider / GeminiProvider:**
- What's not tested: No unit/feature tests for generate/verify paths.
- Files: `backend/app/Services/AnthropicProvider.php`, `backend/app/Services/GeminiProvider.php`
- Risk: Dead/broken adapters when enabled.
- Priority: Medium

**SSE reconnect and long-run UX:**
- What's not tested: Stream expiry without terminal event; client fallback behavior after 55s.
- Files: `backend/app/Services/RunStreamer.php`, `backend/resources/ts/hooks/useRunSubscription.ts`, `backend/tests/Unit/RunStreamerTest.php`
- Risk: Stuck “running” UI for slow models.
- Priority: Medium

**Scheduler / reaper in production topology:**
- What's not tested: That `schedule:run` is part of deploy; only command unit/feature tests exist.
- Files: `backend/routes/console.php`, `backend/docker/*`, `backend/tests/Feature/ReapStuckRunsTest.php`
- Risk: Orphaned `running` rows indefinitely.
- Priority: Medium

**Frontend coverage breadth:**
- What's not tested: `App` launch flow, `useRunSubscription`, `http` errors, ProviderSettings, Report share links, demo mode.
- Files: `backend/resources/ts/components/*`, `backend/resources/ts/hooks/*` (only `components/__tests__/RunHistory.test.tsx`)
- Risk: UI regressions; demo-mode misconfiguration.
- Priority: Medium

**Input_type vs URL validation at HTTP edge:**
- What's not tested: `POST /api/runs` with PR launcher + repo-only URL succeeds with 202 then fails in job.
- Files: `backend/app/Http/Requests/StoreRunRequest.php`, `backend/app/Services/RunExecutor.php`
- Risk: Poor UX and wasted queue capacity.
- Priority: Low

---

*Concerns audit: 2026-07-13*
