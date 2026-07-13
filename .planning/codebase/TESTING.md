# Testing Patterns

**Analysis Date:** 2026-07-13

Backend (Laravel/PHP) is covered by PHPUnit; the frontend currently has **no tests** (`npm run test` is a no-op placeholder that echoes `No frontend tests configured` and exits 0 — see `backend/package.json`).

## Test Framework

**Runner:**
- PHPUnit `^13.0` (`backend/composer.json` `require-dev`).
- Config: `backend/phpunit.xml`. Bootstrap `vendor/autoload.php`, colors on.
- Two test suites: `Unit` -> `tests/Unit`, `Feature` -> `tests/Feature` (`backend/phpunit.xml` `<testsuites>`).
- Source coverage includes `app/` (`<source><include><directory>app</directory></include></source>`).

**Assertion Library:** PHPUnit built-in assertions plus Laravel testing helpers (`assertOk`, `assertStatus`, `assertJsonPath`, `assertJsonCount`, `assertJsonMissingPath`, `assertJsonValidationErrors`, `assertDatabaseHas`, `assertHeader`, `streamedContent`, `Queue::assertPushed`).

**Run Commands:**
```bash
composer run test              # php artisan config:clear + php artisan test (all tests)
php artisan test               # all tests
php artisan test --filter=SomeTest        # focused test / class
php artisan test tests/Feature/RunApiTest.php     # single file
./vendor/bin/pint --test       # style check (CI)
npm run typecheck              # tsc --noEmit (frontend, not unit tests)
npm run lint                   # oxlint + oxfmt --check (frontend)
```
Frontend:
```bash
npm run test                   # no-op placeholder (echoes "No frontend tests configured")
```

## Test File Organization

**Location:** Separate `tests/` directory (PSR-4 `Tests\` -> `tests/`, `backend/composer.json` `autoload-dev`), split into `tests/Unit/` and `tests/Feature/`. No co-location with source.

**Naming:** `*Test.php` classes, `PascalCase` names, `extends TestCase`. Base class `backend/tests/TestCase.php` extends `Illuminate\Foundation\Testing\TestCase`. Namespaces: `Tests\Unit` and `Tests\Feature`.

**Structure:**
```
backend/tests/
├── TestCase.php
├── Unit/
│   ├── ContextEncoderTest.php
│   ├── GitHubContextAssemblerTest.php
│   ├── GitHubContextFetcherTest.php
│   ├── GitHubServiceTest.php
│   ├── OpenAIProviderTest.php
│   └── RunStreamerTest.php
└── Feature/
    ├── ExecuteLauncherJobTest.php
    └── RunApiTest.php
```

## Test Structure

**Suite Organization:**
- `Unit` tests cover pure logic / single services without HTTP (encoders, assemblers, providers, streamers, URL parsing).
- `Feature` tests cover HTTP endpoints (`RunApiTest`) and queued job behavior end-to-end in a realistic context (`ExecuteLauncherJobTest`).

**Patterns:**
- `Feature` and DB-backed tests use `use RefreshDatabase;` and seed in `setUp()`:
  ```php
  protected function setUp(): void
  {
      parent::setUp();
      $this->seed();
  }
  ```
  See `backend/tests/Feature/RunApiTest.php` and `backend/tests/Feature/ExecuteLauncherJobTest.php`.
- Some `Unit` tests also use `RefreshDatabase` + `$this->seed()` when they create `Run`/`Launcher` models (`backend/tests/Unit/RunStreamerTest.php`).
- Pure unit tests (no DB) extend `PHPUnit\Framework\TestCase` directly, e.g. `backend/tests/Unit/GitHubServiceTest.php`, `backend/tests/Unit/GitHubContextAssemblerTest.php`, `backend/tests/Unit/ContextEncoderTest.php`.
- Test methods are `test_*` with explicit `: void` return types.

**Setup / Teardown:** `setUp()` for seeding; no explicit `tearDown` (Laravel `RefreshDatabase` handles cleanup). `phpunit.xml` sets testing env: `APP_ENV=testing`, `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, `QUEUE_CONNECTION=sync`, `CACHE_STORE=array`, `SESSION_DRIVER=array`.

## Mocking

**Framework:** Mockery (`mockery/mockery` in `backend/composer.json` `require-dev`) and Laravel facades (`Illuminate\Support\Facades\Http`, `Queue`, `Log`). Mockery is auto-integrated via Laravel's `TestCase`.

**Patterns:**
- External HTTP is faked with `Http::fake([...])` and asserted with `Http::assertSent(fn ($request) => ...)`. See `backend/tests/Unit/OpenAIProviderTest.php` and `backend/tests/Unit/GitHubContextFetcherTest.php` (wildcard URL matchers like `'*api.github.com/repos/a/b'`).
- GitHub/AI mocked at the boundary in job/executor tests: `Mockery::mock(GitHubService::class)` and `Mockery::mock(App\Contracts\AIProviderInterface::class)` injected into `RunExecutor` directly (constructor injection makes this easy). See `backend/tests/Feature/ExecuteLauncherJobTest.php` (`test_run_executor_uses_server_key_when_byok_omitted`, `test_job_records_structured_result`).
- `Mockery::mock(RunExecutorInterface::class)` passed to `ExecuteLauncherJob::handle($executor)` to assert delegation (`shouldReceive('execute')->once()`, `shouldNotReceive('execute')`).
- `Queue::fake()` used in HTTP feature tests to assert `ExecuteLauncherJob` is dispatched and the controller returns **202** (`backend/tests/Feature/RunApiTest.php`).
- `Log::spy()` verifies secrets are never logged (`backend/tests/Feature/ExecuteLauncherJobTest.php` `test_byok_failure_does_not_log_api_key`).
- Exception expectations use `$this->expectException(...)` + `$this->expectExceptionMessage(...)` (e.g. `backend/tests/Unit/OpenAIProviderTest.php`, `backend/tests/Unit/GitHubContextFetcherTest.php`).

**What to Mock:** GitHub API and OpenAI HTTP calls (via `Http::fake`); `GitHubService` and `AIProviderInterface` when testing `RunExecutor`; `RunExecutorInterface` when testing the job.

**What NOT to Mock:** The database and models (use `RefreshDatabase` + seed + real `Run`/`Launcher` records); queue in feature tests is faked but the job itself is often executed synchronously via `$executor->execute(...)` direct calls rather than `dispatch()`.

## Fixtures and Factories

**Test Data:**
- No Eloquent model factories are used in the current tests; records are created inline with `Run::create([...])` and looked up via `Launcher::where('slug', ...)->value('id')` (see `backend/tests/Feature/ExecuteLauncherJobTest.php`).
- `Database\Seeders\DatabaseSeeder` (run via `$this->seed()`) provides the four seeded launchers (`review-pr`, `plan-issue`, `explain-repository`, `laravel-doctor`).
- Large-context edge cases are built with `str_repeat(...)` / `array_fill(...)` literals (e.g. `backend/tests/Unit/ContextEncoderTest.php`, `backend/tests/Unit/GitHubContextAssemblerTest.php`).

**Location:** Inline within each test method; no separate fixtures directory.

## Coverage

**Requirements:** No enforced coverage threshold in `backend/phpunit.xml` (only `<source><include>app</include></source>` for potential coverage, no `<coverage>` block). CI runs `php artisan test` but does not gate on coverage.

**View Coverage:**
```bash
php artisan test --coverage
```

## Test Types

**Unit Tests:**
- Scope: individual services/data transformers/providers without the framework HTTP layer. Examples:
  - `backend/tests/Unit/ContextEncoderTest.php` — context size-bounding tiers (small / bounded / minimal), truncation flags, encoding limits.
  - `backend/tests/Unit/GitHubContextAssemblerTest.php` — assembling repo/PR/issue contexts, capping files (50) and comments (30), stripping unknown fields.
  - `backend/tests/Unit/GitHubContextFetcherTest.php` — GitHub API mapping, 404/403 -> `RuntimeException`, token header via `Http::fake`.
  - `backend/tests/Unit/OpenAIProviderTest.php` — provider request shape, BYOK key override, safe error on 401.
  - `backend/tests/Unit/RunStreamerTest.php` — SSE event sequence (progress -> completed/failed), no re-yield of unchanged snapshots.
  - `backend/tests/Unit/GitHubServiceTest.php` — URL parsing, host/format rejection.

**Integration Tests:**
- `backend/tests/Feature/RunApiTest.php` — full HTTP cycle: health, listing launchers (and `/api/flows` alias), run creation returning **202**, queue dispatch assertion, BYOP contract (no key persisted/returned), unsupported provider rejection, rate limit (5/hour -> 429), SSE stream header `X-Accel-Buffering: no`.
- `backend/tests/Feature/ExecuteLauncherJobTest.php` — job behavior with seeded DB, encryption of BYOK secrets in the queue (`DB::table('jobs')`), executor delegation, structured result recording, malformed AI result -> `failed`, large-context bounds, error message allow-listing (`RuntimeException` vs hidden `InvalidArgumentException`).

**E2E Tests:** Not used.

## Common Patterns

**Async / Streaming Testing:**
```php
$events = iterator_to_array($streamer->stream($run, 1, 10_000));
$this->assertInstanceOf(StreamedEvent::class, $events[0]);
$this->assertSame('completed', $events[count($events) - 1]->event);
```
See `backend/tests/Unit/RunStreamerTest.php`. SSE HTTP response checked via `->assertHeader('X-Accel-Buffering', 'no')` and `->streamedContent()` in `backend/tests/Feature/RunApiTest.php`.

**Error Testing:**
```php
$this->expectException(\RuntimeException::class);
$this->expectExceptionMessage('Repository a/missing was not found or is private.');
(new GitHubContextFetcher)->fetch(new GitHubReference('a', 'missing', 'repository'));
```
See `backend/tests/Unit/GitHubContextFetcherTest.php`. User-facing vs internal messages verified by asserting `runs.error` values (e.g. `'Run failed unexpectedly.'` for hidden `InvalidArgumentException` in `backend/tests/Feature/ExecuteLauncherJobTest.php`).

**Queue / Job Testing:**
```php
Queue::fake();
$this->postJson('/api/runs', [...])->assertStatus(202);
Queue::assertPushed(ExecuteLauncherJob::class);
```
For direct execution, instantiate the executor with mocked collaborators:
```php
(new RunExecutor($github, new ContextEncoder, new JsonSchemaValidator))->execute($run, $ai);
```

---

*Testing analysis: 2026-07-13*
