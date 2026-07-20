# Concerns

Technical debt, known issues, security, performance, and fragile areas in ai-flow.

## Tech debt

### 1. Frontend-backend status enum duplication
- `Run::STATUSES` (PHP) is manually kept in sync with `RunStatus` (TS, `resources/ts/types/api.ts`) and `isRunStatus` (`resources/ts/services/run.ts`).
- A comment in `Run.php` marks the coupling.
- **Status**: ✅ **Resolved** — automated drift-detection tests added (`RunStatusSyncTest.php` on backend, `runStatusSync.test.ts` on frontend). Both fail if the status sets diverge.

### 2. SSE polling architecture
- `RunStreamer` polls the database (~55s window) with cache-version gating to reduce DB load.
- The cache-version optimization (`CacheRunProgressedVersion`) adds complexity; falls back to unconditional DB refresh when cache is unavailable (e.g. `array` driver in tests).
- **Risk**: under high concurrency, DB polling could be a bottleneck. No WebSocket/Redis pub-sub alternative.
- **Mitigation**: cache gating helps; `runs-stream` rate limiter (30/min/IP) caps load.

### 3. Single CSS file
- ~~All styles in `backend/resources/css/app.css` (one large file, 2,786 lines).~~
- **Status**: ✅ **Resolved** — `app.css` split into 8 logical section files under `resources/css/sections/` (base, auth, dashboard, provider-settings, custom-launchers, launcher-visibility, run-history, responsive). `app.css` is now a 22-line entry point with `@import` statements. Vite/PostCSS bundles them into a single stylesheet at build time.
- DESIGN.md design tokens still provide consistency; consistent class naming.

### 4. `turso/libsql-laravel` incompatibility
- Doesn't support Laravel 13 yet — production can't use libSQL/Turso.
- **Mitigation**: production uses managed Postgres/MySQL. AppServiceProvider throws if SQLite is used in production.

## Security

### 5. BYOK credential encryption key fallback
- `CREDENTIAL_ENCRYPTION_KEY` falls back to `APP_KEY` when unset.
- Rotating `APP_KEY` in this state silently invalidates all stored credentials.
- **Mitigation**: `AppServiceProvider` logs a warning in production when the dedicated key is unset. `config/credentials.php` documents the rotation procedure.

### 6. Provider keys never stored on runs
- API keys are passed through the job, never persisted to the `Run` model, never logged.
- Saved credentials encrypted at rest via `ProviderCredential` model.
- **Status**: ✅ correctly implemented. No concern — documented as a positive.

### 7. GitHub URL validation
- `GitHubService::parse()` requires HTTPS, `github.com`/`www.github.com` only.
- Rejects non-GitHub hosts, malformed paths, non-HTTPS.
- **Status**: ✅ correctly implemented.

### 8. Rate limiting
- 6 rate limiters configured (runs, runs-stream, magic-link, auth-login, auth-register, credentials).
- E2E/CI sets higher limits via env vars.
- **Status**: ✅ comprehensive. No concern.

## Performance

### 9. GitHub context caching
- 10-minute cache per URL (`Cache::remember('github:'.sha1($url), ...)`).
- `ContextBudget` limits README/file-tree/diff/comment lengths to cap prompt size.
- **Status**: ✅ well-designed. No concern.

### 10. AI provider retry
- `BaseAIProvider`: 2 retries with 500ms delay for transient failures.
- Verification timeout (10s) is shorter than generate timeout.
- **Status**: ✅ reasonable. No concern.

## Fragile areas

### 11. `BaseAIProvider::extractJson()` heuristic
- Tries 3 strategies to extract JSON from AI responses: direct parse, strip markdown fence, slice first `{` to last `}`.
- Providers without native `json_schema` enforcement (Anthropic, Gemini) sometimes wrap JSON in prose.
- **Risk**: a model that emits malformed wrapping could fail all 3 strategies. Known limitation: prose containing `{`/`}` characters causes the slicer to produce invalid JSON (all 3 strategies fail → `null`).
- **Status**: ✅ **Documented + tested** — 5 new edge case tests added to `BaseAIProviderJsonExtractionTest` (whitespace, nested objects, array root, unicode, prose-with-braces limitation). Error message includes a 200-char preview for debugging.

### 12. `ReapStuckRuns` TTL
- Scheduled every minute in production; reaps runs stuck in "running" for >300s (was 180s).
- **Status**: ✅ **Fixed** — default TTL increased from 180s to 300s. `ExecuteLauncherJob` has `timeout=120` × `tries=2` = 240s max execution; the 300s TTL gives a 60s buffer to avoid reaping legitimate runs. Configurable via `--ttl` option.

### 13. `UserLauncher` cascade-delete
- `UserLauncher::booted()` deletes associated runs via bulk DELETE query (no model events fire).
- Intentional: `Run` has no observers that need to run on cascade, and bulk is faster.
- **Status**: ✅ **Tested** — `UserLauncherCascadeDeleteTest` added (2 tests): verifies all associated runs are removed, other launchers' runs are unaffected. Risk of future Run observers not firing is documented in the `booted()` callback comment.

### 14. Vitest `act()` warnings
- ~~`RunHistory.test.tsx` and `DashboardAccount.test.tsx` emit `act()` warnings.~~
- **Status**: ✅ **Fixed** — async tests now use `await waitFor(() => {})` to flush pending `useEffect` state updates inside `act()`. Warnings eliminated.

## Known non-issues (documented as resolved)

- ✅ Expected GitHub run failures no longer reported to Sentry (PR #81).
- ✅ All actionable CONCERNS.md items from prior reviews addressed (PR #83).
- ✅ Custom user launchers + per-user visibility implemented (PR #84).
- ✅ Dashboard tab UI redesigned per DESIGN.md (PR #86).
- ✅ `isValidJsonObjectSchema` frontend validation now checks for `type`/`properties` keys, consistent with JSON Schema root shape.
- ✅ **Status enum drift detection** (#1): `RunStatusSyncTest` (PHP) + `runStatusSync.test.ts` (TS) guard against PHP↔TS divergence.
- ✅ **CSS split** (#3): monolithic `app.css` (2,786 lines) → 8 section files + thin entry point.
- ✅ **extractJson edge cases** (#11): 5 new tests document the heuristic's behavior including the prose-with-braces limitation.
- ✅ **ReapStuckRuns TTL** (#12): default increased 180s → 300s (60s buffer over max 240s execution).
- ✅ **UserLauncher cascade-delete** (#13): `UserLauncherCascadeDeleteTest` verifies cascade behavior.
- ✅ **Vitest act() warnings** (#14): fixed with `waitFor(() => {})` to flush async effects.
