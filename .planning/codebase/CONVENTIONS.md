# Conventions

## PHP (Laravel)

### Code Style
- **PSR-12** enforced via **Pint** (`./vendor/bin/pint`)
- **Explicit return types** on all methods
- **`match` over nested ternaries** — no ternary chains
- **Early returns** over deep nesting (guard clauses)
- **No `die()`/`dd()`** in production code; use exceptions or `abort()`

### Architecture Rules
- **Form requests** for validation: `Store*Request`, `Update*Request` classes
- **API resources** for JSON: `*Resource` extends `JsonResource`
- **Thin controllers**: delegate to services/jobs; no business logic in controllers
- **Jobs for slow/IO work**: `ExecuteLauncherJob` queues all AI/GitHub calls
- **Contracts for swappable services**: `AIProviderInterface` for 4 providers
- **Single-implementation interfaces are speculative**: type-hint concrete class directly
- **Thin helpers merge into consumers**: when always used together with a single caller, merge them; split only when independent callers emerge

### Provider Resolution
- **Provider/model resolution**: `LaunchParameters::resolve()` — canonical source
- **Key resolution**: `AiProviderRegistry::resolveApiKey()` — canonical source
- **No duplicating resolution logic** across the codebase

### Launchers
- One class per workflow under `app/Launchers/`
- Extend `BaseLauncher` (implements `LauncherInterface`)
- Metadata via `BaseLauncher::make()` static factory
- Seeded in `DatabaseSeeder`
- Shared `outputSchema` in `BaseLauncher`

### Models
- `Run`: UUID primary key, JSON columns (`input`, `result`, `progress`), status enum
- `User`: Standard Laravel auth, `is_super_admin` flag
- `ProviderCredential`: Encrypted API keys per-user per-provider

### Naming
- Controllers: `*Controller` (thin, action methods)
- Services: `*Provider`, `*Service`, `*Executor`, `*Registry`
- Jobs: `Execute*Job` implements `ShouldQueue` + `ShouldBeEncrypted`
- Form requests: `Store*Request`, `Update*Request`
- Resources: `*Resource` extends `JsonResource`
- Mailables: `*Mail` extends `Mailable`

### Testing
- Feature tests: `RefreshDatabase` trait + seed, `Queue::fake()` for dispatch, mock GitHub/AI
- Unit tests: isolated, focused on single class
- After PHP changes: run `php artisan test`

## TypeScript (React)

### Code Style
- **oxlint + oxfmt** for linting and formatting (no Prettier)
- **konsistent** for structural conventions (`components/*.tsx` → PascalCase export matching filename, `hooks/*.ts` → `use*` functions)
- **Strict mode** TypeScript (`tsc --noEmit`)
- **Avoid broad `any`** — prefer explicit types or `unknown`

### Component Rules
- **Functional components + hooks** (no class components)
- **PascalCase** filenames with matching default export
- **No `useEffect` for derived state** — compute values directly
- Import from specific files, not barrel exports

### File Organization
| Directory | Contents |
|---|---|
| `components/` | React components (PascalCase, one per file) |
| `hooks/` | Custom hooks (`use*` functions) |
| `services/` | API client functions |
| `lib/` | Utility/helper functions |
| `types/` | TypeScript type definitions |
| `data/` | Static data and constants |

### API Client
- `services/run.ts` — primary API service for run operations
- `lib/http.ts` — HTTP helper functions
- All API calls go through these services
- SSE via `hooks/useRunSubscription.ts`

## General

### Version Pinning
- React 19.2.7, Vite 8.1.5, `lucide-react` 1.24.0 — pinned in `package.json`
- Laravel 13, PHP 8.4 — in `composer.json`

### Git
- Commit messages: [Conventional Commits](https://www.conventionalcommits.org/) format
- Branch naming: `feat/`, `fix/`, `chore/`, `refactor/`, `docs/` prefixes
- After rebase: use `git push --force-with-lease` (never `--force`)

### No-Go Patterns
- ❌ Direct `app()->make()` for provider resolution — use `AiProviderRegistry`
- ❌ Duplicating provider/model resolution — use `LaunchParameters::resolve()`
- ❌ Duplicating key resolution — use `AiProviderRegistry::resolveApiKey()`
- ❌ `QUEUE_CONNECTION=sync` in production
- ❌ Synchronous AI/GitHub calls in HTTP cycle
- ❌ Nested ternaries — use `match` or early returns
- ❌ Storing provider keys on runs or in logs
- ❌ Non-HTTPS GitHub URLs for `source_url`
