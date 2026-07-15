# Testing Patterns

**Analysis Date:** 2026-07-15

## Test Framework

**Runner (PHP):**
- **PHPUnit** (via Laravel): `php artisan test`
- Config: `backend/phpunit.xml` — suites `Unit` (`tests/Unit`), `Feature` (`tests/Feature`); in-memory SQLite (`DB_DATABASE=:memory:`), `QUEUE_CONNECTION=sync`, `APP_ENV=testing`.

**Runner (TypeScript):**
- **Vitest** ^4.1.10
- Config: `backend/vitest.config.ts` — `jsdom`, `globals: true`, setup `resources/ts/test/setup.ts`, include `resources/ts/**/*.test.{ts,tsx}`.

**E2E:**
- **Playwright** (`@playwright/test`); specs in `backend/tests/E2E/flows/`; projects `demo` and `real-backend`.

**Assertion Library:**
- PHP: PHPUnit assertions + Laravel HTTP test API (`assertOk`, `assertJsonPath`, `assertStatus(202)`, `assertDatabaseHas`, `Queue::assertPushed`).
- TS: Vitest `expect` + **@testing-library/jest-dom** matchers (`toBeInTheDocument`, etc.) via `import "@testing-library/jest-dom/vitest"` in setup.

**Run Commands:**
```bash
# From repo root (justfile)
just test                    # php artisan test (backend/)
just testf SomeTest          # php artisan test --filter=SomeTest
just test-js                 # vitest run
just e2e-demo                # Playwright demo project
just e2e-real                # Playwright real-backend + sync queue
just ci                      # pint-check, test, typecheck, lint-js, konsistent, build

# Inside backend/
php artisan test
php artisan test --filter=RunApiTest
npm run test                 # vitest run
npm run test:watch           # vitest watch
npm run test:e2e:demo
npm run test:e2e:real
./vendor/bin/pint --test     # style gate (also CI)
```

CI (`.github/workflows/ci.yml`): backend PHP 8.4 — `composer validate`, `php artisan test`, `pint --test`; frontend Node 24 — `typecheck`, `lint`, `konsistent`, `build`, `npm run test --if-present`; separate E2E job after both.

## Test File Organization

**Location (PHP):**
- Separate tree: `backend/tests/Unit/`, `backend/tests/Feature/` (not co-located with `app/`).

**Location (TS):**
- Co-located under `backend/resources/ts/components/__tests__/*.test.tsx` (and similar for other areas matching vitest `include`).

**Naming:**
- PHP: `*Test.php` (`RunApiTest.php`, `OpenAIProviderTest.php`).
- TS: `*.test.ts` / `*.test.tsx`.
- E2E: `*.spec.ts` (`demo-full-flow.spec.ts`, `auth-user-flow.real.spec.ts`).

**Structure:**
```
backend/
  phpunit.xml
  tests/
    TestCase.php
    Unit/
    Feature/
    E2E/flows/
  resources/ts/
    test/setup.ts
    components/__tests__/
  vitest.config.ts
  playwright.config.ts
```

## Test Structure

**Suite Organization (PHP):**
```php
class RunApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_run_is_validated_created_and_queued(): void
    {
        Queue::fake();
        $r = $this->postJson('/api/runs', [...]);
        $r->assertStatus(202)->assertJsonPath('status', 'queued');
        Queue::assertPushed(ExecuteLauncherJob::class);
    }
}
```

**Suite Organization (TS):**
```typescript
import { afterEach, describe, expect, it, vi } from "vitest";
import { render, screen } from "@testing-library/react";

afterEach(() => { vi.clearAllMocks(); });

describe("RunHistory", () => {
  it("shows empty state when there are no runs", async () => {
    render(<RunHistory navigate={vi.fn()} />);
    expect(await screen.findByText(/No runs in your history yet/)).toBeInTheDocument();
  });
});
```

**Patterns:**
- **Setup:** Feature tests `RefreshDatabase` + `$this->seed()` for launchers; unit tests often only `TestCase` + `Http::fake()` / `config()->set()`.
- **Teardown:** Mockery used in job tests; Vitest `vi.clearAllMocks()` in `afterEach`.
- **Assertions:** Prefer Laravel JSON path assertions; assert secrets never appear in DB/JSON/queue payload.

## Mocking

**Framework:**
- PHP: `Queue::fake()`, `Http::fake()`, `Mockery::mock()` for interfaces (`RunExecutorInterface`), `Log` facade when needed.
- TS: `vi.mock()` for modules (`../../services/auth.ts`), `vi.fn()`, `vi.mocked()`, deferred promises for async UI.

**Patterns (PHP):**
```php
Queue::fake();
Http::fake(['api.openai.com/*' => Http::response([...])]);
$executor = Mockery::mock(RunExecutorInterface::class);
$executor->shouldReceive('execute')->once();
```

**Patterns (TS):**
```typescript
vi.mock("../../services/auth.ts", () => ({
  fetchUserRuns: vi.fn().mockResolvedValue({ data: [] }),
}));
vi.mocked(fetchUserRuns).mockRejectedValueOnce(new Error("fail"));
```

**What to Mock:**
- External HTTP (OpenAI, GitHub), queue dispatch in API tests, AI/run executor in job-focused tests, frontend API services in component tests.

**What NOT to Mock:**
- Laravel validation/routing stack in feature tests (use real HTTP kernel); DB migrations/seed data for launcher slugs; JSON schema validation paths covered in unit tests with real validator where cheap.

## Fixtures and Factories

**Test Data:**
- PHP: `DatabaseSeeder` for launchers; inline `Run::create([...])` with `launcher_id` from seeded slugs; `config()->set('services.openai', [...])` per test.
- TS: local helpers like `mockRun(overrides)` in test files; no large shared fixture directory.

**Location:**
- Seeders/factories: `backend/database/`; TS helpers exported from test files when reused (`RunHistory.test.tsx` exports `mockRun`).

## Coverage

**Requirements:** None enforced in CI (`phpunit.xml` includes `<source><directory>app</directory></source>` but workflow uses `coverage: none`).

**View Coverage:**
```bash
# Not part of default workflow; enable PHPUnit coverage driver locally if needed
cd backend && php artisan test --coverage  # only if configured with Xdebug/pcov
```

## Test Types

**Unit Tests (PHP):**
- `tests/Unit/` — providers (`OpenAIProviderTest`), encoders, GitHub assemblers, cipher, registry; heavy use of `Http::fake()` and config overrides.

**Feature Tests (PHP):**
- `tests/Feature/` — API contracts (`RunApiTest`), auth, credentials, job integration (`ExecuteLauncherJobTest`), ownership, CSRF; **`Queue::fake()`** for dispatch; **`RefreshDatabase` + seed** standard.

**Unit/Component Tests (TS):**
- Vitest + Testing Library for components (`AppViews`, `RunHistory`, `LaunchAreaCredentials`, etc.).

**Integration Tests:**
- PHP feature tests hitting full HTTP + DB; `ExecuteLauncherJobTest` exercises executor with faked HTTP and sometimes real job handle path.

**E2E Tests:**
- Playwright: demo mode (`VITE_DEMO_MODE`) full flow; real backend with `QUEUE_CONNECTION=sync` for API/auth flows; CI runs demo + selected real spec.

## Common Patterns

**Async Testing (TS):**
```typescript
expect(await screen.findByText("fail")).toBeInTheDocument();
const { promise, resolve } = deferred<void>();
// ... userEvent + resolve() when testing in-flight UI
```

**Error Testing (PHP):**
```php
$this->expectException(RuntimeException::class);
$this->expectExceptionMessage('Invalid API key.');
(new OpenAIProvider('bad-user-key'))->generate('Inspect.', ['type' => 'object']);
```

**Security-sensitive assertions:**
```php
$this->assertStringNotContainsString($apiKey, json_encode($run->getAttributes(), JSON_THROW_ON_ERROR));
Queue::assertPushed(ExecuteLauncherJob::class, function (ExecuteLauncherJob $job) use ($response): bool { ... });
```

**Pre-commit / local gates:**
- `.pre-commit-config.yaml` + `just prek`: Pint on PHP, typecheck, oxlint, oxfmt-related hooks on touched frontend files.

---

*Testing analysis: 2026-07-15*
