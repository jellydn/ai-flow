# Concerns

## Tech Debt

### T1. ~~Frontend Test Suite Is a CI No-Op~~ (RESOLVED)

**Severity**: None (resolved)
**Location**: `backend/package.json` (`test` script), `.github/workflows/ci.yml`

**Resolved**: The CI `frontend` job already runs `npm run test --if-present`, and the `test` script is `vitest run` (not a no-op). The original concern was based on stale wording in `AGENTS.md` ("test (no-op)") which has been corrected. All 101 frontend tests pass in CI.

### T2. Only One Model Factory Defined

**Severity**: Low-Medium
**Location**: `backend/database/factories/UserFactory.php`

Only `UserFactory` exists. `Run`, `Launcher`, `ProviderCredential`, `LauncherPromptOverride` are constructed inline in tests. This leads to verbose, repetitive test setup and inconsistent fixtures.

**Recommendation**: Add factories for `Run`, `ProviderCredential` at minimum; use factory states for common scenarios (completed, failed, owned, public).

### T3. No Dedicated Tests for Several Core Services

**Severity**: Medium
**Location**: `backend/tests/Unit/`

Missing dedicated unit tests for:
- `LaunchParameters` (covered indirectly via `RunApiTest`, `RunRequiresProviderKeyTest` — but resolution logic is complex enough to warrant direct tests)
- `JsonSchemaValidator` (covered indirectly via `ExecuteLauncherJobTest`)
- `ContextBudget` (covered indirectly via `ContextEncoderTest`)
- `LauncherPromptResolver` (covered indirectly via `LauncherPromptApiTest`)

**Recommendation**: Add focused unit tests for `LaunchParameters::resolve()` (provider precedence, model validation, guest violations) and `JsonSchemaValidator` (nested validation, enum, additionalProperties).

### T4. `ReapStuckRuns` Has a Duplicate `$this->warn()` Line

**Severity**: Low (cosmetic)
**Location**: `backend/app/Console/Commands/ReapStuckRuns.php`

```php
$this->warn("Reaped stuck run: {$run->id} (started {$run->started_at?->diffForHumans()}, ttl={$ttl}s)");
$this->warn("Reaped stuck run: {$run->id}");  // duplicate, less informative
```

**Recommendation**: Remove the second `$this->warn()` line.

### T5. `assertIntegerId` Duplication Between `run.ts` and `auth.ts`

**Severity**: Low
**Location**: `backend/resources/ts/services/run.ts`, `backend/resources/ts/services/auth.ts`

`auth.ts` imports `assertArray`, `assertIntegerId`, `assertObject`, `assertString` from `run.ts`. The type-assertion helpers live in `run.ts` but are cross-used by `auth.ts`. This couples the auth service to the run service for utility imports.

**Recommendation**: Extract `assertObject`, `assertString`, `assertArray`, `assertIntegerId` into `lib/decode.ts` or `lib/assert.ts`; import from both services.

### T6. `fetchProviders` in `auth.ts` Returns Unvalidated Cast

**Severity**: Low-Medium
**Location**: `backend/resources/ts/services/auth.ts`

```typescript
export async function fetchProviders(): Promise<{ id: string; name: string; models: string[] }[]> {
    const body = await get("/api/providers");
    return assertArray(body, "providers") as { id: string; name: string; models: string[] }[];  // unchecked cast
}
```

Unlike `decodeRun`, `decodeUser`, `decodeCredential`, the provider list is cast without per-element validation. A malformed provider response would not throw at the boundary.

**Recommendation**: Add a `decodeProvider` function and `.map(decodeProvider)`.

### T7. `retryRun` Returns an Unvalidated Cast

**Severity**: Low
**Location**: `backend/resources/ts/services/auth.ts`

```typescript
export async function retryRun(id: string): Promise<{ id: string; status: string }> {
    const body = await post(`/api/user/runs/${id}/retry`, {});
    return body as { id: string; status: string };  // unchecked cast
}
```

Note: `deleteRun` (in the same file) does **not** have this issue — it returns `Promise<void>` and throws on `!raw.ok`, so no cast is involved.

**Recommendation**: Validate `retryRun` with `assertObject` + `assertString` or document why the cast is safe.

## Bugs

### B1. `GitHubTrendingService` Not Reviewed

**Severity**: Unknown
**Location**: `backend/app/Services/GitHubTrendingService.php` (referenced by `TrendingRepositoryController`)

This service scrapes GitHub trending. It was not read during this codemap pass. The scraping approach is inherently fragile (HTML structure changes, no API contract). No dedicated unit test was found for it (only `TrendingRepositoriesApiTest` feature test).

**Recommendation**: Review the scraping implementation; add a unit test with a fixture HTML response; consider an official trending API or curated list if scraping breaks.

## Security

### S1. GitHub URL Validation Is Regex + Service Double-Check (Good)

**Severity**: Informational (positive)
**Location**: `backend/app/Http/Requests/StoreRunRequest.php`, `backend/app/Services/GitHubService.php`

`StoreRunRequest` enforces `regex:/^https:\/\/(?:www\.)?github\.com\//i` and `GitHubService::parse` re-validates scheme + host. Defense in depth — good pattern. SSRF surface is limited to `api.github.com` via the `Http::baseUrl()` call.

### S2. Credential Encryption Key Fallback to `APP_KEY`

**Severity**: Medium
**Location**: `backend/app/Security/CredentialCipher.php`, `backend/config/credentials.php`

`CREDENTIAL_ENCRYPTION_KEY` falls back to `APP_KEY` if empty. If `APP_KEY` rotates, previously encrypted credentials become undecryptable. The fallback is documented but could surprise operators.

**Recommendation**: Document key rotation procedure explicitly; consider failing loud if `CREDENTIAL_ENCRYPTION_KEY` is unset in production (rather than silent fallback).

### S3. Magic Link Token Storage Hashed (Good)

**Severity**: Informational (positive)
**Location**: `backend/app/Http/Controllers/Auth/MagicLinkController.php`

Raw token (32 bytes hex) is SHA-256 hashed before DB storage; single-use via `used_at`; 15-min expiry. Good practice — DB compromise doesn't expose valid tokens.

### S4. SSE Session Lock Release (Good)

**Severity**: Informational (positive)
**Location**: `backend/app/Http/Controllers/RunController.php`

`RunController::stream` calls `request()->session()->save()` before the long-lived SSE loop, releasing the session lock so same-user requests aren't blocked. Good pattern.

### S5. Production Guards (Good)

**Severity**: Informational (positive)
**Location**: `backend/app/Providers/AppServiceProvider.php`

`boot()` throws on: SQLite in production HTTP, `sync` queue in production, Postgres without TLS for external hosts, `LOG_LEVEL=debug` warning in production. Strong defensive posture.

## Performance

### P1. SSE DB Polling Mitigated by Cache Versioning (Good)

**Severity**: Informational (positive)
**Location**: `backend/app/Services/RunStreamer.php`, `backend/app/Listeners/CacheRunProgressedVersion.php`

The SSE loop checks a cache version key (`run:version:{id}`) before hitting the DB. When the version is unchanged, it sleeps without a query. This is a meaningful optimization over naive polling. Falls back to unconditional refresh when cache is unavailable (array driver in tests).

**Caveat**: The 55-second SSE window means a client must reconnect if a run takes longer. The frontend `useRunSubscription` has polling fallback (1.5s) for this case.

### P2. GitHub Context Cached 10 Minutes (Good)

**Severity**: Informational (positive)
**Location**: `backend/app/Services/GitHubService.php`

`Cache::remember('github:'.sha1($url), 10 minutes, ...)` — repeated runs of the same URL skip GitHub API calls. Good for rate limits and latency.

### P3. `ContextEncoder` Truncation Prevents Oversized Prompts (Good)

**Severity**: Informational (positive)
**Location**: `backend/app/Services/ContextEncoder.php`, `backend/app/Services/ContextBudget.php`

Two-stage truncation (fetch-time limits + budget-tier limits) keeps prompts within `MAX_CONTEXT_BYTES`. Prevents token-blowup and provider errors.

### P4. `Run::recent()` Query Is Unindexed on `completed_at`?

**Severity**: Low-Medium (needs verification)
**Location**: `backend/app/Http/Controllers/RunController.php`, `backend/database/migrations/`

`Run::recent()` queries `where('status', 'completed')->whereNull('user_id')->whereNotNull('result')->orderByDesc('completed_at')->limit(6)`. Migration `2026_07_15_000001_add_recent_runs_index_to_runs_table.php` suggests an index was added — verify it covers this exact query shape.

**Recommendation**: Confirm the composite index covers `(status, user_id, completed_at)`; consider `EXPLAIN` on the query in production.

### P5. `AiProviderRegistry::list()` Uses Static Cache (Good, with Caveat)

**Severity**: Low
**Location**: `backend/app/Support/AiProviderRegistry.php`

`private static ?array $cachedList = null` — provider metadata cached for the request lifecycle. Good for repeated calls within a request. Caveat: won't reflect config changes within a long-lived process (e.g., worker) without restart.

## Fragile Areas

### F1. AI Provider JSON Output Parsing

**Severity**: Medium
**Location**: `backend/app/Services/BaseAIProvider.php`

All providers extract a string from the response, then `json_decode` it. OpenAI/OpenRouter use `json_schema` response format (reliable); Anthropic/Gemini rely on **prompt-only** JSON instructions (`jsonOnlySystemMessage()` — "Output only the JSON, no other text."). If the model wraps JSON in prose, `json_decode` fails and the run fails.

**Recommendation**: Consider a JSON extraction fallback (strip code fences, find first `{` / last `}`) for Anthropic/Gemini before failing.

### F2. GitHub Trending Scrape (No API Contract)

**Severity**: Medium
**Location**: `backend/app/Services/GitHubTrendingService.php`

Scraping GitHub trending HTML is inherently fragile. Any GitHub HTML structure change breaks `TrendingRepositoryController::index`. No dedicated unit test with a fixture was found.

**Recommendation**: Pin a fixture HTML in a unit test; monitor for breakage; have a fallback (empty list or cached result) on scrape failure.

### F3. `LaunchParameters::resolve()` Provider Precedence Logic

**Severity**: Medium
**Location**: `backend/app/Services/LaunchParameters.php`

The `rawProviderId` vs `effectiveProvider` distinction is subtle: `rawProviderId` preserves nullable behavior for job dispatch (credential provider when credential selected, raw `providerId` otherwise, `null` when both absent — job resolves `null → 'openai'`). This is correct but easy to break on refactor. ADR-0022 documents the intent.

**Recommendation**: Add a focused unit test for `LaunchParameters::resolve()` covering all precedence combinations (credential+providerId, credential only, providerId only, neither, guest).

### F4. Konsistent Exception for `ErrorBoundary`

**Severity**: Low
**Location**: `backend/konsistent.json`, `backend/resources/ts/components/ErrorBoundary.tsx`

`ErrorBoundary.tsx` is the only class component (konsistent enforces functional components elsewhere). The exception is explicit in `konsistent.json`. If konsistent rules are tightened or the exception is dropped, the build breaks.

**Recommendation**: Document why the exception exists (React error boundaries require class components) in `konsistent.json` or a comment.

### F5. `Run::TERMINAL_STATUSES` Duplicated in Frontend

**Severity**: Low
**Location**: `backend/app/Models/Run.php`, `backend/resources/ts/services/run.ts` (`isRunStatus`)

The status enum `['queued', 'running', 'completed', 'failed']` exists in PHP (`Run::STATUSES`, `Run::TERMINAL_STATUSES`) and TypeScript (`isRunStatus`). Changes to one must be mirrored in the other.

**Recommendation**: Acceptable for a small enum, but add a comment in both locations pointing to the other.

## Observability

### O1. Sentry Selective Capture (Good)

**Severity**: Informational (positive)
**Location**: `backend/app/Services/RunExecutor.php`

`UserFacingRunException` is explicitly NOT reported to Sentry (expected user errors). `RuntimeException`, `ConnectionException`, `Throwable` are captured. Good signal-to-noise ratio.

### O2. `markFailed` Logs Exception Class, Not Stack

**Severity**: Low
**Location**: `backend/app/Models/Run.php`

`Log::error($logContext, ['run_id' => ..., 'exception' => get_class($e)])` logs the exception class name but not the stack trace. Sentry captures the full exception separately, but the Laravel log entry is thin.

**Recommendation**: Consider logging `$e->getMessage()` alongside the class name for faster log-based debugging.

## Summary

| Area | Count | Severity Mix |
|---|---|---|
| Tech Debt | 7 | 1 resolved, 2 medium, 3 low/low-medium, 1 cosmetic |
| Bugs | 1 | 1 unknown (trending scrape) |
| Security | 5 | 3 positive (good patterns), 1 medium (key fallback), 1 informational |
| Performance | 5 | 4 positive (good patterns), 1 needs verification |
| Fragile Areas | 5 | 2 medium, 3 low |
| Observability | 2 | 1 positive, 1 low |

**Top priorities**: F1 (Anthropic/Gemini JSON parsing), F2 (trending scrape), S2 (credential key fallback documentation), T3 (LaunchParameters unit tests).

**Resolved in this pass**: T1 (CI already runs tests — was stale info), T2 (factories added), T3 (LaunchParameters + JsonSchemaValidator tests added), T4 (duplicate warn removed), T5 (assert helpers extracted), T6 (decodeProvider added), T7 (retryRun/verifyCredential casts removed), F1 (JSON extraction fallback added), F2 (trending parse unit tests added), F3 (LaunchParameters tests added), F4 (konsistent description enriched), F5 (status enum cross-ref comments added), O2 (markFailed logs message), S2 (rotation doc + prod warning added), P4 (index verified + documented).
