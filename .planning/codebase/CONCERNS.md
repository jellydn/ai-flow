# Concerns

## No TODO/FIXME/HACK Comments

A comprehensive search across all PHP and TypeScript source files found **zero** TODO, FIXME, HACK, or XXX comments. The codebase is well-maintained with no deferred debt markers.

## Technical Debt & Architectural Notes

### 1. `CacheRunProgressedVersion` — Redis dependency awareness
- **File**: `backend/app/Listeners/CacheRunProgressedVersion.php`
- The SSE polling uses `CacheRunProgressedVersion` to skip DB queries when nothing changed. If the cache driver is `file` (default in dev) but the production env uses Redis, the cache behavior differs. This is a known design choice, not a bug, but worth noting for production debugging.

### 2. SSE polling window (~55s)
- **Files**: `backend/app/Services/RunStreamer.php`, `backend/routes/api.php`
- The SSE stream polls the database every ~500ms and has a finite window (~55 seconds). Long-running AI calls may exceed this. The nginx `proxy-read-timeout` is set to 75s for Dokku, but there's no explicit reconnect mechanism in the frontend if the stream closes prematurely. (ADR-0013 acknowledges this trade-off vs WebSockets.)

### 3. SQLite in development vs production Postgres
- **File**: `backend/config/database.php`
- Local dev uses SQLite (`database/database.sqlite`), but production uses managed Postgres/MySQL. SQLite has different concurrency semantics (single-writer lock). Queue workers + HTTP requests in dev may encounter `database is locked` errors under heavy load. This is an accepted dev trade-off.

### 4. BYOK credential encryption — key rotation risk
- **Files**: `backend/app/Security/CredentialCipher.php`, `backend/config/app.php`
- User-provided API keys are encrypted with AES-256-CBC using `APP_KEY`. If `APP_KEY` is rotated (e.g., after a security incident), all stored credentials become unreadable. There is no re-encryption mechanism. This is a standard Laravel encryption trade-off, but worth documenting.

### 5. Guest run model selection enforcement is split
- **Files**: `backend/app/Http/Requests/StoreRunRequest.php`, `backend/app/Services/LaunchParameters.php`
- Guest provider/model restrictions are enforced in two places:
  - `StoreRunRequest::prepareForValidation()` — forces `openrouter` / `openrouter/free` for unauthenticated users
  - `LaunchParameters::isGuestProviderViolation()` / `isModelAllowed()` — validates in the request validator
  - If someone adds a new API endpoint that creates runs without going through `StoreRunRequest`, the guest restriction would need to be re-implemented there.

### 6. Frontend test coverage is sparse
- **Files**: `backend/resources/ts/components/__tests__/`, `backend/resources/ts/lib/__tests__/`
- Only 5 test files exist: `AppViews.test.tsx`, `HomeSubComponents.test.tsx`, `LaunchAreaCredentials.test.tsx`, `Report.test.tsx`, `runModels.test.ts`
- Missing coverage: `LaunchArea.tsx` (the main input form), `App.tsx` (root routing), `Dashboard.tsx`, `Header.tsx`, hooks (`useRunFromPath`, `useRunSubscription`), services (`run.ts`, `auth.ts`)
- CI `npm test` is a no-op — Vitest is configured but tests aren't run in CI

### 7. `ContextBudget` constants — adoption verification needed
- **Files**: `backend/app/Services/ContextBudget.php`, `backend/app/Services/GitHubContextAssembler.php`, `backend/app/Services/ContextEncoder.php`
- `ContextBudget` is a new constants class (from architecture deepening). Only `GitHubContextAssembler` and `ContextEncoder` currently reference it. Other context-related services (`GitHubContextFetcher`, `GitHubService`) may have independent truncation that should reference `ContextBudget` for consistency.

### 8. RecentRunSummary — potential for stale data
- **File**: `backend/app/Services/RecentRunSummary.php`
- The `recent()` endpoint transforms runs in-memory via `RecentRunSummary::from($run)`. It does not cache the result. With 6 runs per page this is negligible, but if pagination is added later, caching should be considered.

## Performance Considerations

### 9. GitHub context assembly is synchronous in the job
- **File**: `backend/app/Jobs/ExecuteLauncherJob.php`
- The entire GitHub context fetch + assembly runs in a single queued job. For very large repositories, fetching tree, README, and recent commits could take several seconds. There's no chunking or incremental context delivery. Acceptable given the ~120s job timeout and caching, but worth monitoring.

### 10. No context size guard for AI prompts
- **File**: `backend/app/Services/ContextEncoder.php`
- `ContextEncoder::truncate()` uses `ContextBudget::MAX_CONTEXT_CHARS` but there's no pre-flight check to warn if the assembled context exceeds model token limits. The AI call would fail with a token-limit error, which is caught and surfaced as a run failure. A pre-flight estimate could provide a better UX.

## Security Considerations

### 11. API keys passed as constructor arguments
- **Files**: All provider adapters (`OpenAIProvider`, `OpenRouterProvider`, `AnthropicProvider`, `GeminiProvider`)
- API keys are passed to provider constructors as plain strings. While they're never logged or stored on runs (only credential IDs are stored), a stack trace in an error handler could accidentally expose a key. Sentry is configured, but care must be taken that Sentry's `before_send` scrubs API keys from stack traces.

### 12. Public runs have no abuse prevention beyond rate limiting
- **Files**: `backend/app/Http/Requests/StoreRunRequest.php`, `backend/app/Providers/AppServiceProvider.php`
- Unauthenticated users can create runs at 5/hour/IP (rate limiter: `runs`). There's no content filtering on `source_url` beyond HTTPS + GitHub domain validation. Malicious URLs could theoretically probe internal services if the GitHub fetcher follows redirects.

### 13. `LaunchAiKeyResolver` resolves keys from config
- **File**: `backend/app/Services/LaunchAiKeyResolver.php`
- When no injected key or credential is available, the resolver falls back to `config('services.{provider}.key')`. If a server-side API key is configured, every unauthenticated run consumes that key's quota. The guest model `openrouter/free` mitigates cost but the server key is still used as the auth mechanism.

## Dependency & Upgrade Notes

### 14. Laravel 13 + `turso/libsql-laravel` incompatibility
- Turso's Laravel driver doesn't support Laravel 13 yet. Production uses managed Postgres/MySQL instead of SQLite. (Noted in `AGENTS.md`.)

### 15. Pinned versions
- `lucide-react` pinned at 1.24.0
- `react` / `react-dom` pinned at 19.2.7
- `vite` pinned at 8.1.4
- These are intentionally pinned to prevent breaking changes from icon/library updates.

## Areas for Future Improvement

| Area | Priority | Notes |
|------|----------|-------|
| Frontend test coverage | Medium | Vitest configured but few tests exist; CI `npm test` is a no-op |
| Pre-flight token estimation | Low | Warn before AI calls if context exceeds model limits |
| Credential re-encryption | Low | Handle APP_KEY rotation for stored BYOK credentials |
| Pagination for recent runs | Low | Currently hardcoded to 6; pagination would need caching strategy |
| WebSocket upgrade from SSE | Low | ADR-0013 chose SSE for simplicity; WebSockets would eliminate polling overhead |
