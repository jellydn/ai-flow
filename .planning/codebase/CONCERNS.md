# Codebase Concerns

**Analysis Date:** 2026-07-13

> Scope: `backend/` (Laravel 13 API + jobs + React/TS SPA). Focus areas supplied by the request: rate limiting, SQLite vs managed DB, SSE buffering/proxy, AI provider timeouts, API aliases, Turso/libsql Laravel 13 incompatibility, demo mode, queue connection in prod, and env handling.

---

## Tech Debt

**Unused `RunProgressed` event dispatches**
- Issue: `App\Events\RunProgressed` is dispatched on every progress step, on completion, and on failure (`backend/app/Services/RunExecutor.php:59`, `:47`, `:52`; `backend/app/Jobs/ExecuteLauncherJob.php:58`), but there is no listener/subscriber anywhere in the codebase. It is effectively a no-op that builds a fresh `Run` model snapshot (`$run->fresh()`) on every call.
- Files: `backend/app/Events/RunProgressed.php`, `backend/app/Services/RunExecutor.php`, `backend/app/Jobs/ExecuteLauncherJob.php`
- Impact: Wasted model hydration/DB reads on each progress update; misleading "event-driven" signal in the code that suggests push notification wiring that does not exist. SSE is actually driven by DB polling (`backend/app/Services/RunStreamer.php`), not by this event.
- Fix approach: Either add a real listener (e.g., broadcasting) or remove the dispatch calls and the event class to avoid implying behavior that isn't implemented.

**Duplicated API alias route definitions**
- Issue: `/api/flows` and `/api/executions` are compatibility aliases re-declared line-by-line instead of sharing a route definition (`backend/routes/api.php:9`, `:13-15`). `StoreRunRequest::prepareForValidation()` additionally maps `flow_id`→`launcher` and `input.url`→`source_url` (`backend/app/Http/Requests/StoreRunRequest.php:11-17`) to support the alias payload shape.
- Files: `backend/routes/api.php`, `backend/app/Http/Requests/StoreRunRequest.php`
- Impact: Maintenance burden; any change to the canonical routes must be mirrored in the aliases, and the dual payload shapes multiply validation edge cases.
- Fix approach: Group alias routes in a loop or shared definition; document the alias contract in one place.

**Dead/unsupported `libsql` database connection left in config**
- Issue: `config/database.php` still defines a `libsql` connection (`backend/config/database.php:35-42`) even though `turso/libsql-laravel` does not support Laravel 13 (per `AGENTS.md`). The connection is dormant (default is `sqlite`, production uses `pgsql`) but remains wired.
- Files: `backend/config/database.php`
- Impact: If anyone sets `DB_CONNECTION=libsql` (plausible given the env example comments about Turso), the app fails at connection time. Confusing for operators.
- Fix approach: Remove the `libsql` block (or gate it behind a clear, supported driver) and add a comment explaining it is not usable on Laravel 13.

**Shared, hard-coded output schema for all launchers**
- Issue: `BaseLauncher::outputSchema()` returns one identical JSON schema for all four launchers (`backend/app/Launchers/BaseLauncher.php:9-12`). It is enforced both by the AI call (`backend/app/Services/OpenAIProvider.php:29`) and by `JsonSchemaValidator` (`backend/app/Services/JsonSchemaValidator.php`).
- Files: `backend/app/Launchers/BaseLauncher.php`, `backend/app/Services/JsonSchemaValidator.php`
- Impact: Launchers cannot return launcher-specific shapes; the validator will reject any schema deviation. Inflexible if a future launcher needs different output.
- Fix approach: Allow each launcher to declare its own `outputSchema()`; keep a shared default for the current four.

**SSE assumed via `response()->eventStream()` / `StreamedEvent`**
- Issue: `RunController::stream()` relies on `response()->eventStream()` and `Illuminate\Http\StreamedEvent` (`backend/app/Http/Controllers/RunController.php:50-52`, `backend/app/Services/RunStreamer.php:8,31,36`). These are Laravel 13 SSE primitives; they could not be verified locally because `vendor/` is not installed in this checkout.
- Files: `backend/app/Http/Controllers/RunController.php`, `backend/app/Services/RunStreamer.php`
- Impact: If the installed framework version lacks these APIs, the stream endpoint fatals at runtime. Should be confirmed against the locked `composer.lock`.
- Fix approach: Verify `laravel/framework` version in `composer.lock` provides `eventStream`/`StreamedEvent`; add a smoke test that hits `/api/runs/{id}/stream`.

---

## Known Bugs

**AI HTTP retry × timeout can exceed the job timeout, orphaning runs in `running`**
- Symptoms: A run sits in `status = 'running'` forever and never reaches `completed`/`failed`; the SPA keeps polling indefinitely.
- Files: `backend/app/Services/OpenAIProvider.php:42-44`, `backend/app/Jobs/ExecuteLauncherJob.php:20-22`
- Trigger: `OpenAIProvider::generate()` uses `->timeout((int) config('services.openai.timeout', 60))->retry(2, 500, throw: false)` → up to 3 attempts × 60s = 180s. The job `timeout` is only 120s. When the worker kills the job at 120s the abort is at OS level, not a catchable PHP exception, so `RunExecutor`'s try/catch never runs and the run is left `running`.
- Workaround: None automatic. A manual reaper/cleanup would be needed.

**GitHub fetch fan-out can also exceed the 120s job timeout on large/slow repos**
- Symptoms: Same orphaned-`running` symptom as above for repository/PR/issue runs.
- Files: `backend/app/Services/GitHubContextFetcher.php:30-61,88-101`, `backend/app/Services/GitHubContextAssembler.php`, `backend/app/Jobs/ExecuteLauncherJob.php:22`
- Trigger: `GitHubContextFetcher` makes up to ~9 sequential API calls (`repo`, `languages`, `readme`, `tree`, plus PR/issue + files + comments), each `->timeout(15)->retry(2, 200, ...)` = up to 45s per call → worst case hundreds of seconds, well beyond the 120s job timeout, especially for large recursive trees or rate-limited/retrying endpoints.
- Workaround: None; runs on slow targets are silently killed.

**No enforcement that `QUEUE_CONNECTION !== 'sync'` in production**
- Symptoms: If `QUEUE_CONNECTION=sync` is set in production, the entire GitHub fetch + OpenAI call runs synchronously inside the HTTP `POST /api/runs` request.
- Files: `backend/app/Providers/AppServiceProvider.php` (only guards sqlite + pgsql TLS, not queue), `backend/config/queue.php:16`, `backend/app/Http/Controllers/RunController.php:34`
- Trigger: Deploy with `QUEUE_CONNECTION=sync` (easy misconfiguration; `sync` is a built-in connection in `config/queue.php:34-36`). The architecture doc explicitly forbids this, but nothing enforces it.
- Workaround: Set `QUEUE_CONNECTION=database` (or redis) in production env. Recommend adding a boot-time guard in `AppServiceProvider` mirroring the sqlite check.

**Rate-limit keying on `$request->ip()` depends on trusted-proxy configuration**
- Symptoms: Either (a) all users behind a shared proxy/CDN collapse into one 5/hour bucket (global starvation), or (b) a client can spoof their IP via `X-Forwarded-For` and bypass the limit.
- Files: `backend/app/Providers/AppServiceProvider.php:29-30`, `backend/routes/api.php:10,12,13,15`
- Trigger: `bootstrap/app.php` `withMiddleware` is empty (`backend/bootstrap/app.php:14-16`) — there is no explicit `TrustProxies` configuration, so correctness of `$request->ip()` relies entirely on framework defaults / Cloud-injected proxy trust. On any setup where proxies are not trusted, the 5/hour/IP limiter is unreliable.
- Workaround: Ensure proxies are trusted; consider keying on a more stable identity if behind a CDN.

---

## Security Considerations

**Fully public, unauthenticated API that can spend the server's AI quota**
- Risk: `StoreRunRequest::authorize()` unconditionally returns `true` (`backend/app/Http/Requests/StoreRunRequest.php:19-22`) and no route has auth middleware (`backend/routes/api.php`). Any anonymous client can trigger a run that, unless they supply their own `provider.api_key`, executes against the server's `OPENAI_API_KEY` (`backend/app/Services/OpenAIProvider.php:15`, `backend/config/services.php:40`).
- Files: `backend/app/Http/Requests/StoreRunRequest.php`, `backend/routes/api.php`, `backend/config/services.php`, `backend/app/Services/OpenAIProvider.php`
- Current mitigation: The `runs` rate limiter caps this at 5/hour/IP (`backend/app/Providers/AppServiceProvider.php:29`).
- Recommendations: 5/hour is the only gate; consider requiring a quota/API key or captcha for anonymous use, and alerting on AI spend. Document the economic exposure explicitly.

**User-supplied API keys transit the queue**
- Risk: `provider.api_key` (up to 512 chars) is accepted from the request (`backend/app/Http/Requests/StoreRunRequest.php:31`), passed into `ExecuteLauncherJob` (`backend/app/Http/Controllers/RunController.php:37`), and the job implements `ShouldBeEncrypted` (`backend/app/Jobs/ExecuteLauncherJob.php:16`). With the `database` queue the key is encrypted at rest in the `jobs` table until processed, then the row is deleted — but it is briefly persisted outside the `runs` record (which only stores `source_url`).
- Files: `backend/app/Jobs/ExecuteLauncherJob.php`, `backend/app/Http/Controllers/RunController.php`, `backend/app/Models/Run.php`
- Current mitigation: `ShouldBeEncrypted` encrypts the job payload; `runs.input` does not persist the key.
- Recommendations: Confirm the `database` queue honors `ShouldBeEncrypted` in this Laravel version; avoid logging payloads; consider not enqueuing raw keys (resolve provider inside the worker from a vault instead).

**CORS is permissive on headers/methods**
- Risk: `config/cors.php` sets `allowed_methods => ['*']`, `allowed_headers => ['*']`, `supports_credentials => false`, with `allowed_origins` from `CORS_ALLOWED_ORIGINS` (`backend/config/cors.php`). EventSource cannot send credentials, so this is low risk, but `*` methods/headers are broader than needed.
- Files: `backend/config/cors.php`
- Current mitigation: Origins are env-controlled; no credentials.
- Recommendations: Tighten `allowed_methods` to the actual set (`GET`, `POST`) and `allowed_headers` to those used.

**Debug logging / `LOG_LEVEL=debug` exposure**
- Risk: `RunExecutor` and `ExecuteLauncherJob` log exception class names (`backend/app/Services/RunExecutor.php:51`, `backend/app/Jobs/ExecuteLauncherJob.php:57`); the OpenAI key is never logged (good). A `debug` log level in production could surface sensitive request data.
- Files: `backend/app/Providers/AppServiceProvider.php:57-59`, `backend/app/Services/RunExecutor.php`, `backend/app/Jobs/ExecuteLauncherJob.php`
- Current mitigation: `AppServiceProvider` emits a `Log::warning` if `LOG_LEVEL=debug` in production (`backend/app/Providers/AppServiceProvider.php:57-59`).
- Recommendations: Keep `LOG_LEVEL=warning`/`error` in prod; avoid logging full payloads.

**Runs are globally readable by UUID**
- Risk: `RunController::show()` and `stream()` bind `Run $run` with no ownership/scope check (`backend/app/Http/Controllers/RunController.php:43-53`, `backend/routes/api.php`). Anyone holding a run UUID can read its result/error.
- Files: `backend/app/Http/Controllers/RunController.php`, `backend/routes/api.php`
- Current mitigation: UUIDs are unguessable; this is effectively the intended "share by link" model.
- Recommendations: Acceptable for a sharing tool, but document the implication; if runs become sensitive, add scoping or expiry.

---

## Performance Bottlenecks

**SSE polls the DB every second for up to 55s**
- Problem: `RunStreamer::stream()` refreshes the `Run` model and re-serializes a full `RunResource` (including the `result` payload) once per second (`backend/app/Services/RunStreamer.php:20-42`). Under concurrent viewers this multiplies DB reads.
- Files: `backend/app/Services/RunStreamer.php`, `backend/app/Http/Resources/RunResource.php`
- Cause: Busy-loop polling (`usleep(1_000_000)`) with full-model fetch + resource resolve each tick.
- Improvement path: Use `DB::listen`/cache the last-seen hash, select only changed columns, or move to broadcast (the `RunProgressed` event already exists but is unused — see Tech Debt). Increase poll interval or use LISTEN/NOTIFY on Postgres.

**GitHub context fetch is sequential and unbounded in call count**
- Problem: `GitHubContextFetcher::fetchRaw()` performs up to ~9 sequential HTTP calls per run (`backend/app/Services/GitHubContextFetcher.php:30-61`). Recursive tree fetch can return thousands of entries; readme has no size cap before decode.
- Files: `backend/app/Services/GitHubContextFetcher.php`, `backend/app/Services/GitHubContextAssembler.php`
- Cause: No concurrency, no per-call size cap; relies on assembler-side truncation (`file_tree` sliced to 500, readme to 20000).
- Improvement path: Fetch in parallel (pooled HTTP), cap readme bytes before `base64_decode`, and cap tree depth/page size.

**Redundant context truncation**
- Problem: `GitHubContextAssembler` already truncates readme (20000) and file_tree (500), then `ContextEncoder` truncates again (readme→10000, file_tree→250) and only if total > 120KB (`backend/app/Services/ContextEncoder.php:7-47`, `backend/app/Services/GitHubContextAssembler.php:24-25`).
- Files: `backend/app/Services/ContextEncoder.php`, `backend/app/Services/GitHubContextAssembler.php`
- Cause: Two layers doing overlapping work; encoder's branch is rarely hit because the assembler already bounds the data.
- Improvement path: Consolidate truncation in one place (the encoder) and have the assembler pass raw-ish data.

---

## Fragile Areas

**SSE 55s deadline vs job 120s timeout**
- Files: `backend/app/Services/RunStreamer.php:20`, `backend/app/Jobs/ExecuteLauncherJob.php:22`, `backend/resources/ts/hooks/useRunSubscription.ts`
- Why fragile: The stream yields for at most 55s (`$deadlineSeconds = 55`) but a run can take up to 120s. If the run hasn't finished by 55s the SSE closes WITHOUT a terminal `completed`/`failed` event. Correctness depends entirely on the frontend's polling fallback (`useRunSubscription.ts:105-110` switches to `setInterval` polling on `onerror`). The fallback is present, but any regression in `EventSource.onerror` handling silently breaks final-state delivery.
- Safe modification: If you change the job timeout, also revisit the 55s deadline and the `runs-stream` 30/min throttle (`backend/app/Providers/AppServiceProvider.php:30`).
- Test coverage: Covered by `tests/Unit/RunStreamerTest.php` (deadline/terminal behavior) — verify it asserts the no-terminal-event-on-timeout case.

**Production DB guard is narrow**
- Files: `backend/app/Providers/AppServiceProvider.php:34-55`
- Why fragile: It blocks `sqlite` and enforces pgsql TLS, but does not validate `CACHE_STORE`, `QUEUE_CONNECTION`, or that the queue driver matches the DB. A deploy with `QUEUE_CONNECTION=sync` or `CACHE_STORE=file` on Cloud would pass the guard yet violate the documented architecture.
- Safe modification: Extend the boot guard to assert `QUEUE_CONNECTION` is not `sync` and (optionally) `CACHE_STORE` is not `file` in production.

**Env-driven demo mode is build-time, not runtime**
- Files: `backend/resources/ts/components/App.tsx:19` (`import.meta.env.VITE_DEMO_MODE === "true"`), `backend/.env.example:57`, `backend/resources/ts/components/Report.tsx:26`
- Why fragile: `VITE_DEMO_MODE` is inlined by Vite at build time; toggling it in `.env` after `npm run build` has no effect without a rebuild. In demo mode, `Report.tsx` renders hard-coded `demoFindings` (`backend/resources/ts/data/launcherMeta.ts:169`) and shares `/runs/demo` (`backend/resources/ts/components/Report.tsx:41`). Mixing demo and live data paths is easy to get wrong and could present static demo content as if it were a real report.
- Safe modification: Keep demo vs live branches isolated; add an explicit `isDemo` flag derived from a real run id rather than a global build flag where feasible.

**Provider abstraction is a single-implementation facade**
- Files: `backend/app/Support/AiProviders.php`, `backend/app/Contracts/AIProviderInterface.php`, `backend/app/Services/OpenAIProvider.php`
- Why fragile: `AiProviders::ids()` returns only `['openai']` and `createProvider()` is a `match` with a single arm (`backend/app/Support/AiProviders.php:14-25`). Adding a provider requires touching the contract, the factory, the validation rule (`Rule::in(AiProviders::ids())` in `backend/app/Http/Requests/StoreRunRequest.php:30`), and the frontend `provider.id: "openai"` literal (`backend/resources/ts/services/run.ts:170`). Easy to forget one.
- Safe modification: Drive allowed providers from a config array; keep the `match`/rule in sync.

---

## Scaling Limits

**Run creation: 5/hour/IP**
- Current capacity: 5 runs/hour per client IP (`backend/app/Providers/AppServiceProvider.php:29`).
- Limit: Hard cap; legitimate heavy users are throttled aggressively. Stream is 30 req/min/IP (`backend/app/Providers/AppServiceProvider.php:30`).
- Scaling path: Raise/parameterize the limit via env; add per-user quotas; shard by API key. Note the IP-keying fragility above before raising.

**Single worker, 120s job ceiling**
- Current capacity: One `ExecuteLauncherJob` per worker slot, ≤120s (`backend/app/Jobs/ExecuteLauncherJob.php:20-22`); `tries=2` (`backend/app/Jobs/ExecuteLauncherJob.php:20`).
- Limit: AI + GitHub work must finish within 120s or the run is orphaned (see Known Bugs). Large repos or slow models miss the window.
- Scaling path: Increase `timeout` and `retry_after` together; split GitHub fetch from AI generation into separate jobs; add a "stuck run" reaper that flips `running` runs past a TTL to `failed`.

**Context size ceiling**
- Current capacity: 120KB encoded context (`backend/app/Services/ContextEncoder.php:7`).
- Limit: Above this, the assembler/encoder degrade context (truncate readme/file_tree/diffs, then drop comments). Reports on very large repos lose fidelity silently (`truncated: true` is set but the client does not surface it).
- Scaling path: Compress/summarize context; stream partial context to the model; surface the `truncated` flag to the UI.

---

## Dependencies at Risk

**`turso/libsql-laravel` (Laravel 13 incompatibility)**
- Risk: The `libsql` DB connection in `config/database.php` (`backend/config/database.php:35-42`) is unusable because the package does not support `illuminate/database ^13`. The ADR/notes say to re-add Turso "when the package supports Laravel 13."
- Impact: Any operator selecting `libsql` gets a runtime failure; the config is dead weight and a trap.
- Migration plan: Remove the block until the driver is Laravel 13-compatible; track upstream support.

**OpenAI-compatible provider quirks**
- Risk: `OpenAIProvider` special-cases `openrouter.ai` by injecting `provider: {require_parameters: true}` only when the base URL contains that string (`backend/app/Services/OpenAIProvider.php:32-34`). Other OpenAI-compatible gateways (Azure, local LLMs, Anyscale, etc.) are not handled and may reject the `response_format.json_schema` strict mode or the extra headers.
- Impact: Non-OpenAI, non-OpenRouter bases may 400/422. `HTTP-Referer`/`X-OpenRouter-Title` headers are sent to all providers (`backend/app/Services/OpenAIProvider.php:38-41`).
- Migration plan: Make provider-specific behavior data-driven (per-provider config) rather than string-matching the URL.

**`Illuminate\Http\StreamedEvent` / `eventStream` (framework-version dependent)**
- Risk: SSE depends on these Laravel 13 primitives (see Tech Debt). If `composer.lock` resolves to a version lacking them, `/api/runs/{id}/stream` fails.
- Impact: Core live-update feature broken.
- Migration plan: Pin/verify `laravel/framework` version; add a stream smoke test.

---

## Missing Critical Features

**No stuck-run reaper / TTL sweep**
- Problem: Runs killed by the job timeout (or worker restart) stay `running` forever; nothing transitions them to `failed`. The UI relies on eventual `completed`/`failed` from SSE/poll that never arrives.
- Blocks: Reliable terminal UX; accurate "recent runs" lists (`backend/resources/ts/components/Home.tsx:240`).

**No queue-connection production guard**
- Problem: `QUEUE_CONNECTION=sync` is allowed in production (see Known Bugs). There is no boot-time assertion.
- Blocks: Safe deploys; prevents accidental synchronous AI execution in the web process.

**No surfacing of `truncated` context**
- Problem: `ContextEncoder` sets `truncated: true` (`backend/app/Services/ContextEncoder.php:17,46`) but `RunResource` (`backend/app/Http/Resources/RunResource.php`) does not expose it and the UI does not show it.
- Blocks: Users cannot tell when a report was generated from reduced context.

**No provider/key validation before enqueue**
- Problem: `provider.api_key` is accepted and enqueued without verification; failure only surfaces after the job runs (and only as a generic `failed`). No pre-flight check that the chosen provider exists beyond `Rule::in`.
- Blocks: Fast user feedback on bad keys.

---

## Test Coverage Gaps

**Job-timeout / orphan-run path is untested**
- What's not tested: Behavior when `ExecuteLauncherJob` is killed at the OS level (run left `running`); the AI retry×timeout > job timeout scenario.
- Files: `backend/app/Jobs/ExecuteLauncherJob.php`, `backend/tests/Feature/ExecuteLauncherJobTest.php`
- Risk: The most likely production failure mode (long AI/GitHub calls) has no regression test.
- Priority: High

**SSE no-terminal-event-on-timeout case**
- What's not tested: That `RunStreamer` closes without emitting `completed`/`failed` when the run outlives the 55s deadline, and that the frontend poll fallback recovers.
- Files: `backend/app/Services/RunStreamer.php`, `backend/resources/ts/hooks/useRunSubscription.ts`, `backend/tests/Unit/RunStreamerTest.php`
- Risk: Silent breakage of final-state delivery.
- Priority: High

**Proxy / rate-limit keying**
- What's not tested: `$request->ip()` behavior behind a proxy and the 5/hour bucket granularity.
- Files: `backend/app/Providers/AppServiceProvider.php`, `backend/tests/Feature/RunApiTest.php`
- Risk: A misconfigured proxy silently defeats the limiter.
- Priority: Medium

**`libsql` config / production guards**
- What's not tested: The sqlite/pgsql-TLS boot guards, and absence of a `sync` queue guard.
- Files: `backend/app/Providers/AppServiceProvider.php`, `backend/config/database.php`
- Risk: Config traps ship unnoticed.
- Priority: Medium

**OpenAIProvider non-OpenAI bases**
- What's not tested: Behavior with Azure/local/other OpenAI-compatible endpoints (strict schema, extra headers).
- Files: `backend/app/Services/OpenAIProvider.php`, `backend/tests/Unit/OpenAIProviderTest.php`
- Risk: Silent 4xx from alternate providers.
- Priority: Low

**Frontend demo vs live separation**
- What's not tested: That demo findings (`backend/resources/ts/data/launcherMeta.ts:169`) never leak into a live run view and vice versa.
- Files: `backend/resources/ts/components/App.tsx`, `backend/resources/ts/components/Report.tsx`
- Risk: Demo content presented as a real report.
- Priority: Medium

---

*Concerns audit: 2026-07-13*
