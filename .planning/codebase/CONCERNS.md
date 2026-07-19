# Concerns

## Missing Test Coverage

### Custom Launcher Feature Tests
The custom launcher feature (PR #84) shipped with full implementation code but no automated feature tests for:
- CRUD lifecycle (create, read, update, delete custom launchers)
- Slug uniqueness validation (cannot collide with built-in slugs)
- Unified `/api/launchers` listing (built-in + custom mixed, hidden filtering)
- Hidden launcher toggle (hide/unhide + persistence)
- `is_public` run visibility (public/private access control)
- Custom launcher execution through full queue pipeline

**Risk**: Regression during future refactors; manual testing burden.
**Priority**: High — roadmap Phase 2 item.

## Architecture Debt

### LauncherSource Interface Half-Consumed
`LauncherSource` is implemented by `Launcher` and `UserLauncher`, and `Run::launcherSource()` returns `?LauncherSource`. However, the contract is only fully consumed in `RunExecutor` (uses interface methods). This is a recent fix — verify no other callers access raw Eloquent properties.

**Risk**: Low — addressed in commit `3c2a3f9`.

### Placeholder `launcher_id` for Custom Runs
Custom-launcher runs use a placeholder `launcher_id` (first active built-in) because the FK is NOT NULL. This means:
- `Run::launcher` relation may point to a different launcher than what was actually used
- Reporting/analytics on `launcher_id` will conflate custom runs with their placeholder built-in

**Risk**: Medium — documented trade-off; could be resolved with polymorphic relations or making the FK nullable.

### Duplicated Icon Mapping (PHP + TypeScript)
`LauncherMetaService` defines built-in icon/tone mappings as PHP constants. The frontend `LauncherIcon.tsx` has equivalent mappings. Adding a built-in launcher requires updating both independently.

**Risk**: Low — 4 built-in launchers today; only changes when new built-in launchers are added.

## Security Considerations

### Rate Limiter Configuration
Rate limits are configured via env vars with defaults in `config/app.php`. Ensure production overrides are documented and tested:
- `RUNS_RATE_LIMIT_PER_HOUR` (default 5)
- `AUTH_REGISTER_RATE_LIMIT_PER_MIN` (default 5)

### Production Guards
`AppServiceProvider::boot()` has multiple production guards that throw `RuntimeException`:
- SQLite forbidden in production
- `sync` queue forbidden in production
- PostgreSQL must use TLS for external hosts
- These guards are defense-in-depth; ensure they survive config caching (`php artisan config:cache`)

## Performance

### SSE Polling Without Redis
The SSE stream uses database polling with cache-version optimization (`CacheRunProgressedVersion`). Without Redis, the cache version check falls back to always-refresh. In production with a database cache driver, this still hits the DB each poll cycle (1s interval, ~55s window = ~55 queries per stream).

**Risk**: Low — acceptable for current scale; Redis would reduce DB load.

### GitHub Context Caching
`GitHubService::context()` caches for 10 minutes. No cache busting mechanism for rapidly-changing PRs/Issues within that window.

**Risk**: Low — stale context is acceptable for AI analysis; user can wait 10 minutes or re-run.

## Unused Code

### No Known Dead Code
The recent simplify passes (commits `084c6cd`, `3c2a3f9`) removed:
- `LauncherMetaInterface` (single-implementation interface)
- `UserHiddenLauncherController` (merged into `UserLauncherController`)
- `Helpers/LauncherMeta.php` (replaced by `LauncherMetaService`)
- Static setter pattern in `LauncherResource`

No remaining dead code identified.

## Dependency Risks

### Laravel 13 + Turso
`turso/libsql-laravel` does not support Laravel 13 yet. Production uses managed Postgres/MySQL — no impact.

### Filament v5
Super-admin panel dependency. Breaking changes in minor versions may require migration. Currently on `^5.0` — monitor Filament v6 announcements.

## TODO / FIXME
No `TODO` or `FIXME` comments found in backend PHP or frontend TypeScript source.
The codebase is clean of deferred-work markers.
