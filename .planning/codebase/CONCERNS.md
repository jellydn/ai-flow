# Codebase Concerns

**Last updated:** 2026-07-12 (after remediation pass)

## Resolved (this pass)

| Concern | Fix |
|---------|-----|
| Unpinned npm deps | Pinned `react` / `react-dom` 19.2.7, `lucide-react` 1.24.0 |
| No `backend/.env.example` | Added with OpenAI, GitHub, CORS, DB notes |
| No CORS config | `backend/config/cors.php` + `CORS_ALLOWED_ORIGINS` |
| Debug default logging | Production default `warning` when `APP_ENV=production` |
| SQLite in production | `AppServiceProvider` throws if `production` + `sqlite` |
| Stream endpoint unlimited | `throttle:runs-stream` (30/min/IP) |
| SSE poll load | 1s interval; lighter `refresh` + `loadMissing` |
| Large AI prompts | Truncate GitHub JSON context at ~120KB in job |
| `source_context` retention | Cleared after successful completion |
| Job mixed DI | Required `JsonSchemaValidator` in `handle()` |
| GitHub parse array | `GitHubReference` DTO |
| `RunResource` errors | `error` only when `status === failed` |
| Frontend URL validation / fallback | `trim()`, no fake repo fallback; slug guard |
| Copy link URL | `shareRunUrl(runId)` + env `VITE_PUBLIC_APP_URL` |
| Scroll race | `scrollToSelector()` double `rAF` |
| `allowedHosts: true` | Explicit hosts + `.onamp.dev` / `.amp.dev` |
| No API integration | `src/lib/api.js` — POST, SSE, demo via `VITE_DEMO_MODE` |
| Single-file data | `src/data/workflows.js`, `ErrorBoundary`, partial split |
| Missing CI | `.github/workflows/ci.yml` (build + `php artisan test`) |
| `runs.created_at` index | Migration `2026_07_12_000001_add_runs_created_at_index` |
| Misleading “never stored” copy | Running note updated to match backend purge |

## Open / deferred

| Concern | Notes |
|---------|--------|
| Full component split | `main.jsx` still holds views; further extract to `src/components/` as needed |
| TypeScript | Not adopted; still JS |
| Frontend tests | No Vitest/Playwright yet |
| Backend test gaps | `OpenAIProvider`, `JsonSchemaValidator` unit, SSE feature test |
| Auth on API | By design (ADR 0014); add tokens when product requires |
| AI provider registry | Still single `OpenAIProvider` binding |
| Laravel broadcasting for SSE | Still DB polling; acceptable for MVP |
| Docker / CHANGELOG | Not added |
| Encrypt `source_context` at rest | Purge on success reduces exposure; encryption optional later |
| GitHub `context()` tests | No HTTP fake tests yet |

## False or overstated in original doc

- **`runs.status` index:** Already present on migration `2026_01_01_000000_create_launchers_and_runs.php`.
- **“No backend”:** Outdated; `backend/` is production API on Laravel Cloud.

---

_Original analysis: 2026-07-12. Remediation aligned with `doc/adr/` and `AGENTS.md`._