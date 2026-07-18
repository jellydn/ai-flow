# Coding Conventions

## PHP / Laravel

### Style & Formatting

- **PSR-12** enforced via Laravel Pint (`backend/vendor/bin/pint`); CI fails on `pint --test` violations
- **Explicit return types** on all methods (`public function store(...): JsonResponse`)
- **Constructor property promotion** for DI: `public function __construct(private RunStreamer $streamer) {}`
- **Readonly properties** for value objects: `public readonly ?string $providerId`
- **No nested ternaries** — prefer `match()` or early returns
- **`.editorconfig`** for editor-level consistency

### Architecture Patterns

#### Controllers — Thin, Delegate

Controllers validate, delegate to services/jobs, and format responses. No business logic.

```php
// RunController::store — delegates to LaunchParameters, LauncherPromptResolver, GitHubService, job dispatch
public function store(StoreRunRequest $request): JsonResponse
{
    $launcher = Launcher::where('slug', $request->validated('launcher'))->where('active', true)->firstOrFail();
    $params = LaunchParameters::resolve(...);
    // ... Run::create() + ExecuteLauncherJob::dispatch()
    return response()->json([...], 202);
}
```

#### Form Requests — Validation + Cross-Field Rules

`Store*Request` for validation; `withValidator($validator)->after()` for cross-field rules that need service collaboration.

```php
// StoreRunRequest::withValidator — uses LaunchParameters for hasUsableKey, isGuestViolationFor, isModelAllowed
$validator->after(function (Validator $validator): void {
    if ($validator->errors()->isNotEmpty()) return;
    $params = LaunchParameters::resolve(...);
    if ($params->hasCredentialKeyConflict()) { $validator->errors()->add(...); return; }
    // ...
});
```

`prepareForValidation()` mutates input before rules run (e.g., guests forced to `openrouter`).

#### API Resources — JSON Shape

`*Resource extends JsonResource` for response formatting; `->resolve()` for SSE snapshots.

```php
// RunResource::toArray — includes provider_label via registry, conditional error on failed
'error' => $this->when($this->status === 'failed', $this->error),
```

#### Jobs — Queue + Encryption

`Execute*Job implements ShouldQueue, ShouldBeEncrypted` for slow/IO work. Properties typed, `tries`/`timeout` declared.

```php
class ExecuteLauncherJob implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;
    public int $tries = 2;
    public int $timeout = 120;
}
```

#### Services — Business Logic, External API Calls

Services own business logic and external API boundaries. Constructor-injected dependencies.

```php
class RunExecutor
{
    public function __construct(
        private GitHubService $github,
        private ContextEncoder $encoder,
        private JsonSchemaValidator $validator,
    ) {}
}
```

#### Contracts + Container Binding — Swappable Services

Interfaces in `app/Contracts/`; concrete bindings in `AppServiceProvider::register()` (e.g., `AiProviderRegistry` as singleton). Single-implementation interfaces are removed (no speculative generality — `RunExecutorInterface` was deleted).

#### Strategy Pattern — Launchers

One class per workflow under `app/Launchers/`, metadata via `BaseLauncher::make()`, seeded in `DatabaseSeeder`.

```php
class ReviewPullRequestLauncher extends BaseLauncher
{
    public static function metadata(): array
    {
        return static::make('review-pr', 'Review Pull Request', '...', 'pull_request', '...');
    }
}
```

#### Adapter Pattern — AI Providers

`BaseAIProvider` owns the HTTP lifecycle; subclasses declare shape via protected hooks (`configureRequest`, `endpoint`, `buildPayload`, `extractContent`, `verifyEndpoint`, `configKey`, `defaultModel`, `systemMessage`). See ARCHITECTURE.md.

#### DTOs — Readonly Value Objects

`App\Data\GitHubReference` is a readonly DTO (`owner`, `repo`, `type`, `number`).

### Error Handling

#### Exception Hierarchy

| Exception | When | Sentry? | User-Visible? |
|---|---|---|---|
| `UserFacingRunException` | Expected user/input errors (malformed URL, wrong launcher, missing repo, rate limit, invalid key) | No | Yes — message shown directly |
| `RuntimeException` | Operational errors (AI provider failure, schema validation, GitHub API non-404/403/401) | Yes | Yes — message shown |
| `ConnectionException` | Network unreachable (GitHub, AI provider) | Yes | Yes — "Unable to reach..." |
| `Throwable` | Unexpected errors | Yes | Yes — "Run failed unexpectedly ({Class})." |

`RunExecutor::execute()` catch chain (in order): `UserFacingRunException` → `ConnectionException` → `RuntimeException` → `Throwable`.

#### `Run::markFailed()` — Single Failure Owner

```php
public function markFailed(string $message, ?Throwable $e = null, string $logContext = 'Launcher run failed'): void
{
    $this->update(['status' => 'failed', 'error' => $message, 'source_context' => null, 'completed_at' => now()]);
    Log::error($logContext, ['run_id' => $this->id, 'exception' => $e ? get_class($e) : null]);
    RunProgressed::dispatch($this->fresh());
}
```

Called by `ExecuteLauncherJob`, `RunExecutor`, and `ReapStuckRuns` — single owner of the failure lifecycle.

#### GitHub Error Mapping

`GitHubService::mapRequestException()` maps HTTP status → typed exception:
- 404 → `UserFacingRunException` (context-specific: PR/Issue/Repo not found)
- 403 → `UserFacingRunException` (rate limit; suggests `GITHUB_TOKEN`)
- 401 → `UserFacingRunException` (auth failed)
- Other → `RuntimeException` (`GitHub API request failed (HTTP {status}).`)

#### AI Provider Error Mapping

`BaseAIProvider::generate()`:
- 401/403 → `RuntimeException('Invalid API key.')`
- Non-success → `RuntimeException('AI provider request failed (HTTP {status}).')`
- `json_decode` failure → `RuntimeException('AI provider returned invalid JSON (json error: {error}, preview: {preview}).')` (preview truncated to `MAX_ERROR_PREVIEW_LENGTH=200`)
- `ConnectionException` → `RuntimeException('Unable to reach the AI provider. Check your network.')`

### Validation

- **Form requests** for HTTP validation (`Store*Request`, `Update*Request`)
- **Custom rules** in `app/Rules/` (`PublicHttpUrl`)
- **`Rule::in($registry->ids())`** for provider IDs
- **`Rule::exists(...)->where(...)`** for ownership-scoped existence (e.g., `provider_credential_id` must belong to user)
- **`LaunchParameters`** for cross-field validation that needs service collaboration (returns structured `{valid, error}` arrays)

### Authorization

- **Policies** in `app/Policies/` (`RunPolicy`, `ProviderCredentialPolicy`)
- `$this->authorize('view', $run)` in controllers
- `RunPolicy::view`: public runs (`user_id === null`) viewable by anyone; private runs owner-only
- `User::canAccessPanel(Panel)`: `is_super_admin === true` for `admin` panel

### Security Conventions

- **Provider keys never stored on runs** — BYOK keys transient (in-memory only, encrypted in queue payload via `ShouldBeEncrypted`)
- **Saved credentials encrypted at rest** via `CredentialCipher` (`CREDENTIAL_ENCRYPTION_KEY` → `APP_KEY` fallback; AES-256-CBC)
- **Plaintext keys must not be stored, logged, serialized, or returned in API responses**
- **`masked_key`** for display: `sk-abcd...9X2A` (prefix 4 + suffix 4, ellipsis middle; <8 chars fully masked)
- **`ProviderCredential::$hidden`**: `encrypted_api_key`, `encrypted_base_url` never serialized
- **HTTPS-only GitHub URLs** enforced (`StoreRunRequest` regex + `GitHubService::parse` scheme check)
- **Production guards** in `AppServiceProvider::boot()`: sqlite forbidden, `sync` queue forbidden, Postgres TLS required, `LOG_LEVEL=debug` warning

### Rate Limiting

Defined in `AppServiceProvider::boot()` (see INTEGRATIONS.md). Limiters attached to routes in `routes/api.php` / `routes/auth.php`.

## React / TypeScript

### Style & Formatting

- **Functional components + hooks** only (no class components except `ErrorBoundary` — `konsistent.json` exception)
- **Strict mode** (`tsconfig.json`: `strict: true`, `noEmit: true`)
- **Avoid broad `any`** — use `unknown` + runtime assertions
- **oxlint + oxfmt** for lint/format (no Prettier, no ESLint)
  - `.oxlintrc.json`: `typescript`, `unicorn`, `oxc` plugins; `correctness: error`; `no-console`
  - `.oxfmtrc.json`: ignores `node_modules`, `public`, `vendor`
- **konsistent** (`konsistent.json`) structural enforcement:
  - `components/*.tsx` must export a PascalCase component matching the filename (default export)
  - `hooks/*.ts` must export a `use*` function matching the filename
  - `ErrorBoundary.tsx` must export the `ErrorBoundary` class (explicit exception)

### Component Conventions

- **PascalCase** component names matching filenames
- **Named exports** (not default) for most; default export required by konsistent for components
- **Props via interfaces** typed inline or in `types/`
- **Hooks for stateful logic**: `useRunSubscription`, `useRunFromPath`

### Type Safety Boundary

Runtime assertions on API JSON via `decode*` / `assert*` functions (no implicit trust):

```typescript
// services/run.ts
export function assertObject(value: unknown): Record<string, unknown> { ... }
export function assertString(value: unknown, field: string): string { ... }
export function decodeRun(value: unknown): Run { ... }  // throws on shape mismatch
```

### HTTP Client (`lib/http.ts`)

- `get(path, timeout?)` / `post(path, payload, timeout?)` wrappers
- **CSRF**: `XSRF-TOKEN` cookie → `X-XSRF-TOKEN` header (decoded); fallback `X-CSRF-TOKEN` from `<meta>`
- **Credentials**: `credentials: "include"` (session cookies)
- **Timeout**: 10s default via `AbortController`
- **Error messages**: extracts first validation error from Laravel `errors` bag, then `message`, then `error`, then `HTTP {status} {statusText}`

### Frontend Patterns

- **SSE + polling fallback**: `useRunSubscription` uses `EventSource`; falls back to 1.5s polling on error or if `EventSource` undefined
- **State lifting**: `App.tsx` holds top-level state (`user`, `view`, `currentRunId`); prop-drilled
- **No router library**: path-based view switching via `useRunFromPath` + `AppViews`
- **Error boundary**: `ErrorBoundary.tsx` wraps `<App />` in `app.tsx`
- **Sentry**: `Sentry.init()` in `app.tsx` (0.1 sample rate prod, 0 dev)

## Git & Commit Conventions

- **Conventional commits**: `fix(api):`, `feat(ui):`, `docs:`, `refactor:`, `test:`, `chore:` (visible in git history)
- **Force-with-lease**: `git push --force-with-lease` after rebasing feature branches (never `--force`)
- **Git remotes**: `origin` = `github.com/jellydn/ai-flow`, `dokku` = staging deploy target
- **Pre-commit hooks** (`.pre-commit-config.yaml` via prek): trailing ws, EOF, YAML check, large files, composer-validate, pint, typecheck, oxlint, oxfmt, konsistent

## ADR-Driven Decisions

Architecture decisions are recorded in `doc/adr/` (22 ADRs). Notable:
- **ADR-0002**: Single-file React app for MVP UI
- **ADR-0007**: Laravel API in `backend/` subdirectory
- **ADR-0008**: Queue-backed `ExecuteLauncherJob`
- **ADR-0010**: GitHub REST context with cache, no clone
- **ADR-0011**: AI provider interface (OpenAI JSON schema)
- **ADR-0012**: Runs as UUID records with JSON columns
- **ADR-0013**: SSE run stream via database polling
- **ADR-0015**: Magic-link authentication
- **ADR-0016**: Stored encrypted BYOK credentials
- **ADR-0017**: Multi-provider registry
- **ADR-0018**: Run ownership and visibility
- **ADR-0020**: Per-user launcher prompt overrides
- **ADR-0021**: Super-admin Filament panel
- **ADR-0022**: Base AI provider deepening (subclass hooks)
