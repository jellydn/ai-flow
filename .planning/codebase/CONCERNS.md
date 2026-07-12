# Codebase Concerns

**Analysis Date:** 2026-07-12

## Tech Debt

**Monolithic frontend shell (`src/main.jsx`):**
- Issue: `src/main.jsx` is 478 lines and owns routing, launch state, SSE lifecycle, fallback polling, and most page markup. Supporting modules exist (`src/lib/api.js`, `src/lib/scroll.js`, `src/data/workflows.js`, `src/components/ErrorBoundary.jsx`) but orchestration and views remain concentrated in one file.
- Files: `src/main.jsx`, `src/lib/api.js`, `src/data/workflows.js`, `src/components/ErrorBoundary.jsx`
- Impact: Unrelated UI changes conflict easily; run state-machine behaviour is hard to reason about or unit-test.
- Fix approach: Extract route/run orchestration and major views into focused components; keep `src/lib/api.js` as the network boundary.

**Duplicated API aliases:**
- Issue: `backend/routes/api.php` exposes identical `runs`/`executions` and `launchers`/`flows` routes.
- Files: `backend/routes/api.php`
- Impact: Middleware, throttling, and response-shape changes must stay synchronized on two paths.
- Fix approach: Prefer canonical `runs`/`launchers` endpoints; document aliases with deprecation/removal criteria.

**Events dispatched without transport consumers:**
- Issue: `RunExecutor` dispatches `RunProgressed`, but SSE polls the database; ADR notes no broadcast wiring.
- Files: `backend/app/Services/RunExecutor.php`, `backend/app/Events/RunProgressed.php`, `backend/app/Http/Controllers/RunController.php`, `doc/adr/0013-sse-run-stream-via-database-polling.md`
- Impact: Extra conceptual complexity without reducing DB polling load.
- Fix approach: Wire an event-backed transport, or remove/defer the event until push is needed.

**Partial custom JSON Schema implementation:**
- Issue: `JsonSchemaValidator` supports only the subset needed by current launchers; ADR notes divergence from provider enforcement.
- Files: `backend/app/Services/JsonSchemaValidator.php`, `doc/adr/0011-ai-provider-interface-openai-json-schema.md`
- Impact: Schema evolution can yield provider-valid output rejected locally, or unsupported constraints silently omitted.
- Fix approach: Adopt a maintained validator, or explicitly document and test the supported subset against seeded schemas.

**Frontend catalog independent of backend metadata:**
- Issue: UI workflows come from `src/data/workflows.js`; launchers are seeded in the DB. The UI does not call `GET /api/launchers`.
- Files: `src/data/workflows.js`, `src/main.jsx`, `backend/database/seeders/DatabaseSeeder.php`, `backend/routes/api.php`
- Impact: Slugs, availability, descriptions, and input types can drift.
- Fix approach: Load live metadata from `/api/launchers`, or generate both catalogs from one source.

## Known Bugs

**Configured queue retries do not retry execution failures:**
- Symptoms: Job declares `$tries = 2`, but transient GitHub/OpenAI failures never retry.
- Files: `backend/app/Jobs/ExecuteLauncherJob.php`, `backend/app/Services/RunExecutor.php`
- Trigger: Any exception inside `RunExecutor::execute` is caught, run marked `failed`, method returns normally; Laravel treats the job as successful.
- Workaround: Manually create a new run.
- Fix approach: Re-throw retryable exceptions after attempt bookkeeping; use `failed()` for terminal persistence; keep validation/user errors non-retryable.

**Shared report URLs ignore browser history navigation:**
- Symptoms: Back/forward after `pushState` can leave the UI inconsistent with the URL.
- Files: `src/main.jsx`
- Trigger: Launch or reset uses `history.pushState`; mount effect reads `pathname` once (`[]` deps) and there is no `popstate` listener.
- Workaround: Hard refresh or paste the URL again.
- Fix approach: Add a `popstate` handler (or a small router) with a single route-to-state function.

**SSE disconnect permanently degrades to polling:**
- Symptoms: After the first `EventSource` error, the client only polls REST for the rest of the run.
- Files: `src/lib/api.js`, `src/main.jsx`
- Trigger: Transient network/proxy interruption; `streamRun` closes the source on `onerror` and invokes `onDisconnect`, which starts polling.
- Workaround: None automatic; reload the page mid-run to re-open SSE.
- Fix approach: Retry SSE with bounded backoff before falling back, or avoid closing immediately so native `EventSource` reconnection can work.

**Malformed SSE payloads are silently ignored:**
- Symptoms: Bad JSON on an event leaves the running screen without progress or diagnostics.
- Files: `src/lib/api.js`
- Trigger: Serialization/proxy regression producing non-JSON `event.data`.
- Workaround: Wait for disconnect/poll path if the connection also errors.
- Fix approach: Count parse failures, treat repeated malformation as disconnect, and log a diagnostic.

## Security Considerations

**Paid execution is public and IP-throttled only:**
- Risk: Anyone can create runs; abuse via IP rotation; shared NAT unfairness.
- Files: `backend/app/Http/Requests/StoreRunRequest.php` (`authorize` returns true), `backend/app/Providers/AppServiceProvider.php` (5/hour/IP), `doc/adr/0014-api-throttling-and-public-unauthenticated-runs.md`
- Current mitigation: Throttle + GitHub URL validation + active launcher slug check.
- Recommendations: Budget caps, provider spend alerts, global concurrency limits, later authenticated per-user quotas.

**Rate limiting depends on shared cache and correct client IP:**
- Risk: Multi-instance limits fragment without shared `CACHE_STORE`; wrong trusted-proxy config spoofs or collapses IPs.
- Files: `backend/app/Providers/AppServiceProvider.php`, `backend/config/cache.php`
- Current mitigation: Laravel rate limiter keyed by `$request->ip()`.
- Recommendations: Validate shared cache and trusted proxies on Laravel Cloud.

**Untrusted repository text is injected into the AI prompt:**
- Risk: Prompt injection via README, diffs, issues, or comments can distort reports.
- Files: `backend/app/Services/RunExecutor.php`, `backend/app/Services/GitHubService.php`
- Current mitigation: No model tools/secrets; public sources only by design.
- Recommendations: Delimit untrusted data, strengthen system policy, add adversarial prompt tests.

**Completed reports are publicly readable by UUID:**
- Risk: UUID is a bearer secret, not access control; shared links expose submitted URLs and findings.
- Files: `backend/routes/api.php`, `backend/app/Http/Resources/RunResource.php`
- Current mitigation: UUID identifiers (hard to guess).
- Recommendations: Document in UI/privacy policy; add ownership or revocable share tokens before private/sensitive sources.

**Public GitHub content is sent to an external AI provider:**
- Risk: Accidentally committed credentials or personal data leave the platform.
- Files: `backend/app/Services/GitHubService.php`, `backend/app/Services/OpenAIProvider.php`
- Current mitigation: Public HTTPS github.com URLs only.
- Recommendations: User disclosure, retention policy, secret redaction, provider DPA/config.

**User-facing errors include selected operational detail:**
- Risk: All `RuntimeException` messages are written to `runs.error` (e.g. missing AI key, HTTP status). Non-`RuntimeException` errors become a generic message.
- Files: `backend/app/Services/RunExecutor.php`, `backend/app/Services/OpenAIProvider.php`
- Current mitigation: No secrets in current messages; generic fallback for unexpected throwables.
- Recommendations: Explicit safe domain exceptions; keep raw details server-side only.

## Performance Bottlenecks

**One DB query per open stream per second:**
- Problem: Stream loop refreshes the run every ~1s for up to 55s.
- Files: `backend/app/Http/Controllers/RunController.php`, `doc/adr/0013-sse-run-stream-via-database-polling.md`
- Cause: Intentional DB-poll SSE design without broadcast.
- Improvement path: Redis/broadcast notifications or a dedicated realtime service before meaningful concurrency.

**SSE reconnect churn for long runs:**
- Problem: 55s stream deadline forces reconnects; client may close SSE and switch to polling on error.
- Files: `backend/app/Http/Controllers/RunController.php`, `src/lib/api.js`, `src/main.jsx`
- Cause: Server deadline + client close-on-error.
- Improvement path: Explicit end/retry signal, optional `Last-Event-ID`, coordinated client backoff.

**GitHub context uses sequential HTTP calls:**
- Problem: Repo, languages, README, tree, then PR/issue + files + comments run serially (up to ~7 calls for a PR) with 15s timeout and 2 retries each.
- Files: `backend/app/Services/GitHubService.php`
- Cause: Sequential `Http` chain inside a 10-minute cache callback.
- Improvement path: Parallelize independent GETs, enforce a total context-fetch deadline, measure rate-limit responses.

**Full context persisted then discarded:**
- Problem: Full GitHub context is written to `runs.source_context` before prompt truncation; column cleared only on completion/failure.
- Files: `backend/app/Services/RunExecutor.php`
- Cause: Persist-then-encode design; `encodeContext` bounds the prompt only.
- Improvement path: Bound before persistence, or store only resumable/diagnostic metadata.

**No pagination beyond first GitHub pages:**
- Problem: PR files capped at 50, comments at 30, tree paths at 500 — silently incomplete for large repos.
- Files: `backend/app/Services/GitHubService.php`
- Cause: Hard `per_page` / `array_slice` limits (cost control).
- Improvement path: Surface truncation in results; selectively paginate within a strict budget.

## Fragile Areas

**Production correctness is configuration-dependent:**
- Files: `backend/config/queue.php`, `backend/app/Providers/AppServiceProvider.php`
- Why fragile: Default queue is `database`, but production can still set `QUEUE_CONNECTION=sync`. Unlike the SQLite production guard, there is no runtime reject for sync queues on the web process.
- Safe modification: Add a production boot check and deploy smoke test for queue driver + worker health.

**Queue timeout has little margin over network retries:**
- Files: `backend/app/Jobs/ExecuteLauncherJob.php` (`$timeout = 120`), `backend/app/Services/OpenAIProvider.php` (60s × retries), `backend/app/Services/GitHubService.php` (15s × retries)
- Why fragile: Worst-case network time can exceed the job timeout before `RunExecutor` records failure or clears context.
- Safe modification: Define an end-to-end deadline and shorter per-attempt budgets that fit under 120s.

**Worker death can strand runs:**
- Files: `backend/app/Services/RunExecutor.php`, `backend/app/Jobs/ExecuteLauncherJob.php`
- Why fragile: Failures are only marked inside the executor catch block; no job `failed()` handler and no stale-run reconciler.
- Safe modification: Terminal `failed()` handling + scheduled recovery for stuck `queued`/`running` rows.

**SSE relies on infrastructure behaviour:**
- Files: `backend/app/Http/Controllers/RunController.php` (`X-Accel-Buffering: no`), `backend/README.md`, `AGENTS.md`
- Why fragile: Proxy buffering and long-lived response timeouts are external; misconfig breaks live progress.
- Safe modification: Deployed SSE smoke test and observable heartbeats after platform changes.

**Frontend demo/live behaviour is compile-time:**
- Files: `src/main.jsx` (`VITE_DEMO_MODE`), `src/data/workflows.js`
- Why fragile: Wrong production build presents simulated runs and `/runs/demo` share links.
- Safe modification: Clear demo indicator in UI; verify built env vars in deploy checks.

**Frontend vs backend URL validation differs:**
- Files: `src/lib/api.js` (`isValidGithubUrl`), `backend/app/Http/Requests/StoreRunRequest.php`, `backend/app/Services/GitHubService.php`, `backend/app/Services/RunExecutor.php`
- Why fragile: Client accepts broad `github.com/owner/repo…` shapes; server rejects unsupported paths and checks launcher `input_type` only inside the queued job — after rate-limit consumption.
- Safe modification: Align validation semantics; optionally pre-validate parsed type before creating the run.

## Scaling Limits

**Database queue, cache, and SSE polling share one datastore (defaults):**
- Current capacity: Fine for local/MVP traffic.
- Limit: Job reservation, GitHub cache, run updates, and stream reads contend under load.
- Files: `backend/.env.example`, `backend/config/queue.php`, `backend/config/cache.php`
- Scaling path: Managed Redis (or equivalent) for queue/cache; size workers separately from web.

**No global concurrency or provider backpressure:**
- Current capacity: One job dispatch per accepted `POST /api/runs`.
- Limit: IP throttle does not cap aggregate AI/GitHub load.
- Files: `backend/app/Jobs/ExecuteLauncherJob.php`, `backend/app/Providers/AppServiceProvider.php`
- Scaling path: Queue concurrency controls, circuit breakers, provider-aware retry/backoff.

**No visible run retention lifecycle:**
- Current capacity: Unbounded growth of runs (URLs, progress, results, errors).
- Limit: Storage and privacy exposure grow indefinitely.
- Files: `backend/database/migrations/`, `backend/app/Models/Run.php`
- Scaling path: Retention policy + scheduled delete/anonymize.

**Health check is shallow:**
- Current capacity: Always `{"status":"ok"}`.
- Limit: Traffic can hit instances that cannot execute runs.
- Files: `backend/routes/api.php`
- Scaling path: Cheap liveness plus separate readiness (DB, queue worker freshness, AI config).

## Dependencies at Risk

**Pinned frontend toolchain without automated upgrade path:**
- Risk: Root pins Vite `5.4.14`, `@vitejs/plugin-react` `4.3.4`, React `19.2.7` intentionally (`package.json` / `AGENTS.md`); major bumps accumulate migration work and security-patch lag if unattended.
- Impact: Manual upgrades only; no Dependabot/renovate config in-repo for the SPA.
- Migration plan: Automated dependency/security PRs; upgrade Vite/React plugins incrementally with build smoke tests.

**Provider adapter assumes Chat Completions + JSON Schema:**
- Risk: Always posts `/chat/completions` with `response_format.json_schema`; OpenRouter branch is URL-string based.
- Files: `backend/app/Services/OpenAIProvider.php`, `doc/adr/0011-ai-provider-interface-openai-json-schema.md`
- Impact: Model/provider changes can reject the format or change content shape.
- Migration plan: Capability-aware adapters + contract tests per supported provider/model.

**No frontend quality-tool dependencies:**
- Risk: Root `package.json` has no test runner, linter, formatter, or typecheck script (`devDependencies` empty).
- Impact: Lifecycle regressions rely on manual checks and `vite build` syntax validation.
- Migration plan: Focused React tests + lightweight lint/static-analysis baseline.

## Missing Critical Features

**No authentication, ownership, or revocation:**
- Problem: Public unauthenticated MVP (ADR 0014) blocks private repos, user history, reliable quotas, and report deletion.
- Blocks: Private sources, per-user spend control, true access control on reports.
- Files: `doc/adr/0014-api-throttling-and-public-unauthenticated-runs.md`, `backend/routes/api.php`

**No cancellation or idempotency:**
- Problem: Only create/show/stream; no cancel path; retries of `POST /api/runs` create duplicate paid work.
- Blocks: Stopping expensive runs; safe client retries.
- Files: `backend/routes/api.php`, `backend/app/Http/Controllers/RunController.php`

**No operational failed-job / queue monitoring:**
- Problem: Workers defined in docs/jobs, but no alerting, queue-lag SLO, or dead-letter runbook in-repo.
- Blocks: Detecting stuck workers and silent backlog.
- Files: `backend/app/Jobs/ExecuteLauncherJob.php`, `backend/README.md`

**No report deletion path or retention policy:**
- Problem: Public reports persist without user-accessible deletion.
- Blocks: Privacy compliance and storage control.
- Files: `backend/app/Http/Controllers/RunController.php`, `backend/app/Models/Run.php`

## Test Coverage Gaps

**No frontend tests:**
- What's not tested: URL routing/history, launch errors, demo/live switching, SSE terminal events, malformed events, disconnect fallback, report rendering.
- Files: `src/` (no `*.test.*` / `*.spec.*`), root `package.json` (no test script)
- Risk: High-impact UI regressions ship unnoticed.
- Priority: High

**Queue retry and hard-failure semantics untested:**
- What's not tested: Interaction of `$tries` with `RunExecutor` catch-all, timeout cleanup, `failed_jobs`, stranded-run recovery.
- Files: `backend/tests/Feature/ExecuteLauncherJobTest.php`, `backend/app/Jobs/ExecuteLauncherJob.php`, `backend/app/Services/RunExecutor.php`
- Risk: Retry config looks correct but never fires.
- Priority: High

**SSE coverage is partial, not end-to-end:**
- What's covered: `backend/tests/Feature/RunApiTest.php` → `test_stream_emits_terminal_snapshot_without_buffering` checks terminal event content for a completed run.
- What's not tested: 55s disconnect behaviour, client aborts, stream rate limits, buffering headers under a proxy, reconnect/poll client path.
- Files: `backend/app/Http/Controllers/RunController.php`, `src/lib/api.js`, `src/main.jsx`
- Risk: Infra/proxy regressions and long-run reconnect behaviour go unnoticed.
- Priority: Medium

**Security/abuse cases incomplete:**
- What's covered: Basic throttling in `backend/tests/Feature/RunApiTest.php`.
- What's not tested: Forwarded-IP/trusted-proxy behaviour, distributed cache semantics, global spend caps, UUID access policy, prompt injection, secret redaction.
- Files: `backend/app/Providers/AppServiceProvider.php`, `backend/app/Services/RunExecutor.php`
- Risk: Production abuse or privacy issues.
- Priority: High

**GitHub large/truncated/error scenarios thin:**
- What's not tested thoroughly: Pagination truncation, malformed base64 README, rate-limit responses, enormous trees, missing patches, partial endpoint failures.
- Files: `backend/tests/Unit/GitHubServiceTest.php`, `backend/app/Services/GitHubService.php`
- Risk: Silent incomplete reports or hard failures on edge repos.
- Priority: Medium

**Custom schema validation needs a compatibility suite:**
- What's not tested: Every seeded launcher schema plus additionalProperties, nested required, enums, nullability, numeric types, malformed provider output.
- Files: `backend/app/Services/JsonSchemaValidator.php`, `backend/database/seeders/DatabaseSeeder.php`
- Risk: Schema drift breaks runs only in production AI paths.
- Priority: Medium

**No deployed integration smoke suite:**
- What's not tested: Laravel Cloud app-root (`backend/`), non-sync queue, worker availability, CORS, trusted proxies, SSE proxy buffering (`AGENTS.md`, `backend/README.md`).
- Risk: Green local CI, broken production progress/execution.
- Priority: High

---

*Concerns audit: 2026-07-12 (verified against source)*
