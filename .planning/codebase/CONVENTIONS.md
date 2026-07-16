# Coding Conventions

## PHP (Laravel)

### Style
- **PSR-12** enforced via `laravel/pint` (^1.24)
- Run: `./vendor/bin/pint --test` (CI check) or `./vendor/bin/pint` (auto-fix)
- Explicit return types on all methods
- No nested ternaries — prefer `match` or early returns

### HTTP Layer
- **Form requests** for validation: `StoreRunRequest`, `StoreProviderCredentialRequest`, etc.
  - Validation rules in `rules()`, custom logic in `withValidator(Validator $validator)` using `$validator->after()`
  - `LaunchParameters::resolve()` for provider/model/key resolution
  - `prepareForValidation()` for input normalization (aliases, guest defaults)
- **API resources** for JSON serialization: `RunResource`, `UserResource`, `ProviderCredentialResource`
  - Use `$this->when()` for conditional fields
- **Controllers** are thin: validate → resolve → dispatch/query → respond
  - Constructor injection for service dependencies
  - `private` readonly properties for injected services

### Jobs & Queue
- Slow/IO work goes through jobs, never in the HTTP cycle
- `ExecuteLauncherJob` is the single queued job
- Constructor receives scalar/string parameters (run UUID, provider ID, API key, model)
- `handle()` method resolves models + services from the container
- Use `Queue::fake()` in feature tests
- Private helper methods for job-internal logic (e.g., `failRun()` → now `Run::markFailed()`)

### Services
- Located in `app/Services/`, not `app/Services/` (no sub-namespace by domain)
- Contract + container binding for swappable services
- `BaseAIProvider` uses template method pattern — concrete adapters declare hooks, base owns lifecycle
- `LaunchParameters` is a value object (readonly properties, static factory)
- `ContextBudget` is a constants-only class (no instantiation needed)
- `RecentRunSummary` is a transformer (static `from(Run): array`)

### Error Handling
- AI provider errors: `throw new RuntimeException('message')` — caught in `ExecuteLauncherJob`
- Connection errors: `catch (ConnectionException)` → user-friendly message
- `Run::markFailed()` as single failure transition owner
- Sentry captures exceptions in job catch blocks
- Never log API keys: `Log::error()` context excludes key material

### Models
- UUID primary keys on `Run`
- JSON columns: `input`, `result`, `progress` on `Run`; `prompt_template` on `Launcher`
- `markFailed()` method on `Run` for consistent failure state transitions
- Relationships: `Run → Launcher` (belongsTo), `Run → User` (belongsTo, nullable), `Run → ProviderCredential` (belongsTo, nullable)
- `LauncherPromptOverride` for per-user prompt customization

### Launchers
- One class per workflow under `app/Launchers/`
- Extend `BaseLauncher` → abstract `slug()`, `make()` returns metadata array
- Seeded in `DatabaseSeeder`
- Shared `outputSchema` in `BaseLauncher`

### Configuration
- AI keys in `config/services.php`: `services.openai.key`, `services.anthropic.key`, etc.
- Timeout: `services.ai.timeout` (new) with backward-compat `services.openai.timeout`
- Model resolution: `AI_MODEL` > `OPENAI_MODEL` > code default `gpt-4o-mini`
- Per-adapter model configs: `ANTHROPIC_MODEL`, `GEMINI_MODEL`

## TypeScript / React

### Style
- **oxlint** (^1.73.0) for linting, **oxfmt** (^0.59.0) for formatting
- No ESLint, no Prettier
- Config at repo root: `.oxlintrc.json`, `.oxfmtrc.json`
- **konsistent** enforces: `components/*.tsx` → export PascalCase matching filename; `hooks/*.ts` → export `use*` functions
- `npm run lint` = oxlint + oxfmt --check; `npm run format` = oxfmt --write

### Components
- Functional components + hooks only (no class components)
- Strict TypeScript mode (`tsconfig.json` strict: true)
- Avoid broad `any` types
- Each component file exports a PascalCase component matching the filename
- Tests in `__tests__/` subdirectories: `components/__tests__/Report.test.tsx`
- Pin `react`, `vite`, `lucide-react` versions

### State Management
- Component-local state via `useState` / `useReducer`
- `appUiState.ts` for app-level UI state (view routing)
- `useRunSubscription` hook for SSE streaming state
- No Redux, no Zustand, no context-heavy patterns

### API Client
- `services/run.ts` — run creation, status polling, SSE subscription
- `services/auth.ts` — login, register, logout, user info
- `lib/http.ts` — shared HTTP client wrapper
- Types in `types/api.ts` — centralized API response interfaces

### Routing
- Client-side routing via `appPaths.ts` constants + `navigate.ts`
- `AppViews.tsx` as view state machine
- URL-based run loading: `useRunFromPath.ts`

## Architecture Decision Records (ADRs)

- All significant architectural decisions documented in `doc/adr/`
- Format: `NNNN-kebab-case-title.md`
- Index: `doc/adr/README.md`
- Template includes: Status, Context, Decision, Consequences
- Reference ADRs in code comments for context (e.g., `// See ADR-0022`)

## Git

- Remote: `origin` = `github.com/jellydn/ai-flow`
- Deploy remote: `dokku` = staging target
- Conventional commits: `feat:`, `fix:`, `refactor:`, `docs:`, `style:`, `test:`
- CI: `.github/workflows/ci.yml` — PHP 8.4 + Node 24
- Pre-commit hooks via `prek`: pint, composer validate, env check, npm-in-backend

## No-Go Patterns

- ❌ Synchronous OpenAI/GitHub calls in HTTP cycle — always queue
- ❌ `QUEUE_CONNECTION=sync` in production — always `database`
- ❌ Nested ternaries — use `match` or early returns
- ❌ Storing API keys on run records — keys are transient, only credential IDs stored
- ❌ Logging API keys — `Log::error()` context must not contain key material
- ❌ Direct `app()->make()` for provider resolution — use `AiProviderRegistry`
- ❌ Duplicating provider/model/key resolution — use `LaunchParameters`
- ❌ Duplicating failure transition logic — use `Run::markFailed()`
