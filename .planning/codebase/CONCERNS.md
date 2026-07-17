# Concerns

## Current State

**Codebase health**: ✅ Green — 216/216 tests passing, Pint clean (137 files), oxlint/oxfmt clean, TypeScript strict mode compiles, CI all green.

- **PHP**: 70 files, 4,079 lines
- **TypeScript**: 47 files, 4,847 lines
- **Test files**: 29 PHP + 8 TS + 4 E2E
- **TODO/FIXME**: 0 found in codebase

## Architecture Notes

### Resolved Concerns
1. **Thin helper classes** — `GitHubContextFetcher`, `GitHubContextAssembler`, `LaunchAiKeyResolver` were merged into their primary consumers (`GitHubService`, `AiProviderRegistry`). Eliminated 3 source files.
2. **Speculative interface** — `RunExecutorInterface` (single implementation) deleted. Concrete `RunExecutor` type-hinted directly.
3. **`RecentRunSummary` coupling** — No longer depends on `GitHubService`; `repo_slug` and `repo_type` stored on `Run` at creation time.

### Current Concerns
1. **`GitHubService` is now ~200+ lines** — previously split into fetcher + assembler. Merging was intentional (always used together), but the combined class should be monitored for growth. Split again if it exceeds ~400 lines or gains independent callers.
2. **No dark mode** — intentional per DESIGN.md (dark accent sections only), but users may expect a toggle.
3. **Staging deploy serialization** — Dokku deploys are serialized across PRs (`fix(ci): serialize staging Dokku deploys` — commit `c70ab3e`). This means deploy queue can back up during active development.

## Deploy Notes
- **Staging**: `ai-flow-staging.itman.fyi` via Dokku (auto-deploy from `dokku` remote)
- **Production**: Not yet deployed (Laravel Cloud ready, see `CLOUD_DEPLOY.md`)
- **Worker**: `QUEUE_CONNECTION=database` with `php artisan queue:work --sleep=1 --tries=2 --timeout=120`
- **SSE**: Nginx `proxy-buffering` disabled, `proxy-read-timeout 75s` required

## Rate Limits
| Limiter | Threshold |
|---|---|
| Create runs (API) | 5/hr per IP |
| SSE streams | 30/min per IP |
| Magic link requests | 3/min per IP |
| Credential operations | 10/min per user |

## Security Notes
- Provider API keys: never stored on runs, never logged, encrypted at rest via `CredentialCipher`
- Queue jobs: encrypted (`ShouldBeEncrypted`) — protects BYOK keys in database queue
- GitHub URLs: only HTTPS accepted, validated via `PublicHttpUrl` rule
- CSS injection: React renders markdown via `react-markdown` with `remark-gfm`; no raw HTML

## Performance Notes
- **GitHub context caching**: `GitHubService` caches fetched repository context
- **Database queue**: in-memory by default (SQLite), durable in production
- **SSE window**: ~55 seconds before connection close; long runs may require reconnect
- **No image assets**: all icons via `lucide-react` (SVG), no raster images in UI

## Known Limitations
1. **No multi-tenancy**: Single app instance; all users share the same database
2. **No horizontal scaling**: Queue worker is single-threaded; scale by adding more workers
3. **Public repos only**: Private GitHub repos not supported (auth scope limited)
4. **`turso/libsql-laravel` not Laravel 13 compatible** — production uses managed Postgres/MySQL
5. **SSR not implemented**: SPA only; no server-side rendering for share URLs

## Open PRs (non-Renovate)
- **#15** `ci/vercel` — deploy SPA to Vercel and wire production API. Has merge conflicts, stale (opened earlier). Needs manual review or closure.
