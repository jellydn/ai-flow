# Codebase Concerns

**Analysis Date:** 2026-07-13

> Scope: `backend/` (Laravel 13 API + jobs + React/TS SPA).

## Known Bugs

### AI retry × timeout > job timeout (180s vs 120s)
- **Files:** `OpenAIProvider.php:42-44`, `ExecuteLauncherJob.php:20-22`
- **Symptom:** Run sits in `status='running'` forever, SPA polls indefinitely.
- **Trigger:** `OpenAIProvider::generate()` retries up to 3×60s=180s. Job timeout is 120s. Worker kills job at OS level → `RunExecutor` try/catch never runs → run orphaned.
- **Mitigation:** `ReapStuckRuns` command (every minute, 180s TTL). Fix: align job timeout with provider timeout.

### GitHub fetch fan-out > job timeout
- **Files:** `GitHubContextFetcher.php:30-61`, `ExecuteLauncherJob.php:22`
- **Symptom:** Same orphaned-`running` as above for large/slow repos.
- **Trigger:** Up to ~9 sequential API calls at 45s/call → hundreds of seconds.
- **Mitigation:** `ReapStuckRuns` command. Fix: parallelize fetches or split into separate job.

### Rate-limit IP keying fragile behind proxies
- **Files:** `AppServiceProvider.php:29-30`, `routes/api.php`
- **Symptom:** All users behind CDN/proxy collapse into one 5/hour bucket, or IP spoofable.
- **Trigger:** No explicit `TrustProxies` config (`bootstrap/app.php:14-16` empty).
- **Fix:** Configure proxy trust or key on stable identity.

## Tech Debt

### Unused `RunProgressed` event
- **Files:** `RunExecutor.php:59,47,52`, `ExecuteLauncherJob.php:58`, `RunProgressed.php`
- **Issue:** Dispatched everywhere but has zero listeners. Wasted DB reads on every progress step.
- **Fix:** Add broadcaster/listener or remove dispatch calls.

### Duplicated API route aliases
- **Files:** `routes/api.php:9,13-15`
- **Issue:** `/api/flows` and `/api/executions` re-declared line-by-line instead of shared definition.
- **Fix:** Group in loop or shared definition.

### Dead `libsql` connection in config
- **Files:** `config/database.php:35-42`
- **Issue:** `turso/libsql-laravel` doesn't support Laravel 13. Connection is dormant but a trap.
- **Fix:** Remove or gate behind supported driver check.

### Shared hard-coded output schema
- **Files:** `BaseLauncher.php:9-12`
- **Issue:** One schema for all four launchers; validator rejects any deviation.
- **Fix:** Allow per-launcher `outputSchema()` override.

## Security

### Fully public, unauthenticated API
- **Files:** `StoreRunRequest.php:19-22`, `routes/api.php`
- **Risk:** Any anonymous client triggers AI runs against server's `OPENAI_API_KEY`.
- **Mitigation:** 5/hour/IP rate limit.
- **Recommendation:** Consider API key/captcha for anonymous use.

### BYOK keys transit the queue
- **Files:** `ExecuteLauncherJob.php:16`, `RunController.php:37`
- **Risk:** API keys briefly persisted in `jobs` table.
- **Mitigation:** `ShouldBeEncrypted` encrypts payload; keys never in `runs` record.

### CORS permissive on methods/headers
- **Files:** `config/cors.php`
- **Risk:** `*` methods/headers broader than needed.
- **Mitigation:** Origins env-controlled; no credentials.

### Runs globally readable by UUID
- **Files:** `RunController.php:43-53`, `routes/api.php`
- **Risk:** Anyone holding UUID can read result/error.
- **Intention:** This is the "share by link" model — acceptable by design.

## Performance

### SSE polls DB every second for 55s
- **Files:** `RunStreamer.php:20-42`
- **Issue:** Full-model fetch + `RunResource` serialize each tick.
- **Fix:** Use broadcast (existing `RunProgressed` event is unused) or LISTEN/NOTIFY on Postgres.

### Sequential GitHub context fetch
- **Files:** `GitHubContextFetcher.php:30-61`
- **Issue:** ~9 sequential HTTP calls per run; no concurrency.
- **Fix:** Fetch in parallel (pooled HTTP).

### Redundant context truncation
- **Files:** `ContextEncoder.php`, `GitHubContextAssembler.php`
- **Issue:** Assembler truncates (readme→20000, file_tree→500), then encoder truncates again (readme→10000, file_tree→250).
- **Fix:** Consolidate in one place.

## Fragile Areas

### SSE 55s deadline vs job 120s timeout
- **Files:** `RunStreamer.php:20`, `ExecuteLauncherJob.php:22`
- **Risk:** Stream closes at 55s without terminal event; depends on frontend polling fallback.

### Env-driven demo mode is build-time
- **Files:** `App.tsx:19` (`import.meta.env.VITE_DEMO_MODE`)
- **Risk:** Toggling after build has no effect; requires rebuild.

### Provider abstraction is single-implementation
- **Files:** `AiProviders.php:14-25`
- **Risk:** `match` with single arm; adding provider touches contract, factory, validation, frontend.

## Missing Critical Features

- **Stuck-run reaper:** `ReapStuckRuns` command added (every minute, 180s TTL) — partial fix only.
- **Queue-connection guard:** Added in `AppServiceProvider` — resolved.
- **Truncated context surface:** `truncated: true` set by encoder but not exposed in UI.
- **Provider key pre-flight:** No validation before enqueue; failure only surfaces after job runs.

## Test Coverage Gaps

| Area | Priority | Detail |
|------|----------|--------|
| Job-timeout/orphan-run path | HIGH | OS-level kill behavior untested |
| SSE no-terminal + poll fallback | HIGH | Silent breakage risk |
| Proxy/rate-limit keying | MEDIUM | Proxy misconfiguration defeats limiter |
| `libsql` config / guards | MEDIUM | Config traps ship unnoticed |
| Frontend demo vs live separation | MEDIUM | Demo content leaking into live view |
| Non-OpenAI provider bases | LOW | Silent 4xx from alternate providers |

---

*Concerns audit: 2026-07-13*
