# Coding Conventions

## PHP / Laravel

### Style & Formatting

- **PSR-12** enforced via Laravel Pint (`backend/vendor/bin/pint`); CI fails on `pint --test` violations
- **Explicit return types** on all methods (`public function store(...): JsonResponse`)
- **Constructor property promotion** for DI: `public function __construct(private RunStreamer $streamer) {}`
- **Readonly properties** for value objects: `public readonly ?string $providerId`
- **No nested ternaries** — prefer `match()` or early returns

### Controllers — Thin, Delegate

Controllers validate, delegate to services/jobs, and format responses. No business logic.

```php
// RunController::store — delegates to LauncherResolutionService, prompt resolver, job dispatch
public function store(StoreRunRequest $request): JsonResponse
{
    $resolved = $this->launcherResolver->resolve($slug, $user);
    // ... Run::create() + ExecuteLauncherJob::dispatch()
    return response()->json([...], 202);
}
```

### Form Requests — Validation + Cross-Field Rules

`Store*Request` for validation; `withValidator($validator)->after()` for cross-field rules; `prepareForValidation()` mutates input before rules run.

```php
// StoreUserLauncherRequest — closure validation for output_schema JSON structure
$validator->after(function (Validator $validator) {
    $data = json_decode($this->output_schema, true);
    if (!is_array($data) || $data === []) {
        $validator->errors()->add('output_schema', 'Must decode to a non-empty array or object.');
    }
});
```

### API Resources — JSON Shape

`*Resource extends JsonResource` for response formatting; use `->resolve()` for raw arrays (not `->response()` which wraps in `{data: ...}`).

### Jobs — Queue + Encryption

`Execute*Job implements ShouldQueue, ShouldBeEncrypted` for slow/IO work. Properties typed, `tries`/`timeout` declared.

### Services — Constructor-Injected Dependencies

Services own business logic and external API boundaries:

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

### Contracts + Container Binding — Only When Needed

Interfaces only when multiple implementations exist. Single-implementation interfaces are removed (precedent: `RunExecutorInterface`, `LauncherMetaInterface` were deleted).

### Authorization — Policies

- Policies in `app/Policies/`; `$this->authorize('view', $run)` in controllers
- `RunPolicy::view`: public runs viewable by anyone; private runs owner-only
- `UserLauncherPolicy`: ownership-based CRUD

### Security Conventions

- **Provider keys never stored on runs** — BYOK keys transient (in-memory only)
- **Saved credentials encrypted at rest** via `CredentialCipher` (`CREDENTIAL_ENCRYPTION_KEY`)
- **Masked keys** for display: `sk-abcd...9X2A` (prefix 4 + suffix 4)
- **HTTPS-only GitHub URLs** enforced
- **Production guards** in `AppServiceProvider::boot()`: sqlite forbidden, `sync` queue forbidden, TLS required

### Error Handling

- `UserFacingRunException`: expected user errors (no Sentry)
- `RuntimeException`: operational errors (Sentry)
- `ConnectionException`: network unreachable (Sentry)
- `Throwable`: unexpected (Sentry, wrapped with class name)
- `Run::markFailed()` — single owner of failure lifecycle

### Rate Limiting

Defined in `AppServiceProvider::boot()` via `RateLimiter::for()`; attached to routes.

## React / TypeScript

### Style & Formatting

- **Functional components + hooks** only (except `ErrorBoundary` class)
- **Strict mode** (`tsconfig.json`: `strict: true`, `noEmit: true`)
- **Avoid broad `any`** — use `unknown` + runtime assertions
- **oxlint + oxfmt** for lint/format (`.oxlintrc.json`, `.oxfmtrc.json`)
- **konsistent** structural enforcement:
  - `components/*.tsx` → PascalCase component matching filename
  - `hooks/*.ts` → `use*` function matching filename

### Component Conventions

- **PascalCase** names matching filenames
- **Props via interfaces** typed inline or in `types/`
- **Hooks for stateful logic**: `useRunSubscription`, `useRunFromPath`

### Type Safety Boundary

Runtime assertions on API JSON via `decode*` / `assert*` functions:

```typescript
// lib/decode.ts — shared assert helpers
export function assertObject(value: unknown): Record<string, unknown> { ... }
export function assertString(value: unknown, field: string): string { ... }
// services/run.ts — run-specific decoders
export function decodeRun(value: unknown): Run { ... }
```

### HTTP Client (`lib/http.ts`)

- `get(path, timeout?)` / `post(path, payload, timeout?)` wrappers
- **CSRF**: `XSRF-TOKEN` cookie → `X-XSRF-TOKEN` header; fallback `X-CSRF-TOKEN` from `<meta>`
- **Credentials**: `credentials: "include"` (session cookies)
- **Timeout**: 10s default via `AbortController`

### State Management

- **State lifting**: `App.tsx` holds top-level state (`user`, `view`, `currentRunId`); prop-drilled
- **No router library**: path-based view switching via `useRunFromPath` + `AppViews`
- **Error boundary**: `ErrorBoundary.tsx` wraps `<App />` in `app.tsx`

## Git & Commit Conventions

- **Conventional commits**: `feat(scope):`, `fix(scope):`, `refactor:`, `docs:`, `chore:`
- **Force-with-lease**: `git push --force-with-lease` after rebasing (never `--force`)
- **Remotes**: `origin` = `github.com/jellydn/ai-flow`, `dokku` = staging
- **Pre-commit hooks**: trailing ws, EOF, YAML check, large files, composer-validate, pint, typecheck, oxlint, oxfmt, konsistent

## ADR Process

Architecture decisions recorded in `doc/adr/` (currently 24 ADRs). New decisions follow:
1. Draft ADR in `doc/adr/` with sequential number
2. Document alternatives considered, consequences, and trade-offs
3. Update `doc/adr/README.md` index
