# Testing Patterns

**Analysis Date:** 2026-07-12

## Test Framework

- Backend tests use PHPUnit 11 (`phpunit/phpunit:^11.5.50`) through Laravel's test runner. The primary command is `composer run test` or `php artisan test` from `backend/`.
- `backend/phpunit.xml` defines separate `Unit` and `Feature` suites, bootstraps Composer autoloading, enables colored output, and includes `backend/app/` as the source tree for coverage.
- The test environment uses in-memory SQLite, array cache/session/mail, and a synchronous queue by default. Feature tests override the queue with `Queue::fake()` when dispatch itself is under test.
- Mockery 1.6 supplies object/interface mocks; Laravel's `Http::fake()` supplies HTTP fakes.
- The frontend currently has no test framework, test scripts, test dependencies, or `*.test.*` / `*.spec.*` files. Root verification is currently `npm run build`, not an automated UI test suite.

```xml
<!-- `backend/phpunit.xml` -->
<testsuites>
    <testsuite name="Unit">
        <directory>tests/Unit</directory>
    </testsuite>
    <testsuite name="Feature">
        <directory>tests/Feature</directory>
    </testsuite>
</testsuites>
<php>
    <env name="APP_ENV" value="testing"/>
    <env name="CACHE_STORE" value="array"/>
    <env name="DB_CONNECTION" value="sqlite"/>
    <env name="DB_DATABASE" value=":memory:"/>
    <env name="QUEUE_CONNECTION" value="sync"/>
</php>
```

## Test File Organization

- `backend/tests/TestCase.php` is the Laravel application base test case.
- `backend/tests/Feature/RunApiTest.php` covers routes, validation, persistence, queue dispatch, aliases, SSE headers/events, and rate limiting.
- `backend/tests/Feature/ExecuteLauncherJobTest.php` covers job delegation and the integrated execution workflow with database state plus mocked external boundaries.
- `backend/tests/Unit/GitHubServiceTest.php` is a framework-free PHPUnit test for URL parsing and rejection.
- `backend/tests/Unit/OpenAIProviderTest.php` extends the Laravel test case because it needs configuration and the `Http` facade.
- Test files are named after the production subject with a `Test.php` suffix; classes mirror filenames and use `Tests\Feature` or `Tests\Unit` namespaces.

## Test Structure

- Tests are class-based PHPUnit methods with `public function test_descriptive_behavior(): void` names; attributes and Pest syntax are not used.
- Feature tests use Arrange-Act-Assert without literal section comments. Setup is local unless shared across the class; `RunApiTest::setUp()` calls `parent::setUp()` then seeds launchers once per test.
- Assertions are often fluently chained on Laravel responses. Database and queue assertions follow the response assertions.
- Tests focus on observable contracts: HTTP status and JSON shape, persisted run state, queued job type, emitted SSE content, provider request payload, or thrown exception.
- Negative paths are explicit: malformed URLs, unknown launchers, rate limits, malformed AI results, provider/schema constraints, and oversized GitHub context.

```php
// `backend/tests/Feature/RunApiTest.php`
public function test_run_is_validated_created_and_queued(): void
{
    Queue::fake();
    $r = $this->postJson('/api/runs', [
        'launcher' => 'explain-repository',
        'source_url' => 'https://github.com/laravel/framework',
    ]);

    $r->assertStatus(202)
        ->assertJsonPath('status', 'queued')
        ->assertJsonPath('message', 'Workflow started');
    $this->assertDatabaseHas('runs', ['id' => $r->json('id')]);
    Queue::assertPushed(ExecuteLauncherJob::class);
}
```

## Mocking

- Mock external or replaceable boundaries, not Eloquent internals. `backend/tests/Feature/ExecuteLauncherJobTest.php` mocks `RunExecutorInterface`, `GitHubService`, and `AIProviderInterface`, while retaining the real job, executor, validator, models, and database.
- Bindings are passed directly when invoking classes manually; `ExecuteLauncherJob::handle($executor)` receives a Mockery contract mock.
- Use `shouldReceive(...)->once()` when call count matters and `withArgs` closures for semantic argument checks, such as model identity or bounded prompt JSON.
- Use `Http::fake()` for provider requests and `Http::assertSent()` to verify URL, model, schema, provider options, and authorization headers in `backend/tests/Unit/OpenAIProviderTest.php`.
- Use `Queue::fake()` before API calls and `Queue::assertPushed()` afterward when testing asynchronous dispatch in `backend/tests/Feature/RunApiTest.php`.
- No frontend mocking conventions exist yet because there is no frontend test harness.

```php
// `backend/tests/Feature/ExecuteLauncherJobTest.php`
$github = Mockery::mock(GitHubService::class);
$github->shouldReceive('parse')
    ->andReturn(new GitHubReference('a', 'b', 'repository'));
$github->shouldReceive('context')
    ->andReturn(['repository' => ['full_name' => 'a/b']]);

$ai = Mockery::mock(AIProviderInterface::class);
$ai->shouldReceive('generate')
    ->andReturn(['summary' => 'Good', 'risk' => 'low', 'findings' => [], 'verification_steps' => []]);
```

## Fixtures and Factories

- Feature tests using the database apply `RefreshDatabase`, then call `$this->seed()` to load the four launcher definitions from `backend/database/seeders/DatabaseSeeder.php`.
- Tests create `Run` records directly with `Run::create(...)`; there is currently no `RunFactory` or `LauncherFactory`.
- `backend/database/factories/UserFactory.php` is the only model factory and is Laravel skeleton infrastructure; current domain tests do not use it.
- Small input/result fixtures are inline arrays near the behavior they exercise. The large-context test uses `str_repeat()` and `array_fill()` rather than storing fixture files.
- `GitHubReference` objects serve as typed return fixtures for mocked URL parsing.

```php
// `backend/tests/Feature/ExecuteLauncherJobTest.php`
$run = Run::create([
    'launcher_id' => Launcher::where('slug', 'explain-repository')->value('id'),
    'source_url' => 'https://github.com/a/b',
    'input' => ['source_url' => 'https://github.com/a/b'],
    'progress' => [],
]);
```

## Coverage

- `backend/phpunit.xml` marks `backend/app/` as source, so PHPUnit can collect application coverage when a compatible driver (Xdebug or PCOV) is installed.
- There is no minimum coverage threshold, coverage report format, or dedicated coverage script configured in `backend/composer.json`.
- Existing tests concentrate on the critical execution path: API creation/read/stream contracts, dispatch, job delegation, GitHub URL parsing, structured AI output validation, context bounding, and OpenAI-compatible request construction.
- Notable untested or lightly tested areas include GitHub API context retrieval/caching and its issue/PR branches, `JsonSchemaValidator` as a standalone unit, exception/logging paths in `OpenAIProvider` and `RunExecutor`, model relationships/casts, service-provider production guards, SSE timeout/reconnect behavior, and all frontend components/helpers.
- For changed backend behavior, add focused tests in the matching suite and run `php artisan test`; for frontend changes, at minimum run `npm run build` until a frontend runner is introduced.

## Test Types

- **Unit:** Pure or narrowly scoped behavior in `backend/tests/Unit/`, including URL parsing and provider request construction. Unit tests may still use Laravel `Tests\TestCase` when facades/configuration are required.
- **Feature/API:** Full Laravel HTTP requests with routing, middleware, validation, serialization, database, throttling, and queue fakes in `backend/tests/Feature/RunApiTest.php`.
- **Service/job integration:** Real database + real `RunExecutor`/`JsonSchemaValidator` with mocked network/AI boundaries in `backend/tests/Feature/ExecuteLauncherJobTest.php`.
- **Streaming:** The SSE endpoint is exercised as a streamed response, including `X-Accel-Buffering: no`, terminal event name, and serialized terminal status.
- **Frontend:** No unit, component, browser, accessibility, or end-to-end tests are present.

## Common Patterns

- Seed launcher metadata before creating runs:

```php
// `backend/tests/Feature/ExecuteLauncherJobTest.php`
$this->seed();
$run = Run::create([
    'launcher_id' => Launcher::where('slug', 'explain-repository')->value('id'),
    // ...
]);
```

- Assert API behavior through exact paths and hidden fields rather than broad snapshots:

```php
// `backend/tests/Feature/RunApiTest.php`
$this->getJson('/api/launchers')
    ->assertOk()
    ->assertJsonCount(4)
    ->assertJsonPath('0.id', 'review-pr')
    ->assertJsonMissingPath('0.class_name');
```

- Verify terminal database state after execution with a fresh model, including cleanup of temporary context:

```php
// `backend/tests/Feature/ExecuteLauncherJobTest.php`
$this->assertSame('failed', $run->fresh()->status);
$this->assertNull($run->fresh()->result);
$this->assertNull($run->fresh()->source_context);
```

- Use Laravel exception expectations for a single invalid case; use a loop with `try`/`catch` plus `addToAssertionCount(1)` for a compact malformed-input matrix in `backend/tests/Unit/GitHubServiceTest.php`.
- Test configured integrations by setting `config()` in the test, faking the transport, invoking the real service, then asserting both returned data and the outbound request.
- Keep tests deterministic: no real GitHub/OpenAI network calls, in-memory database, array stores, fixed payloads, and bounded generated data.

---

_Testing analysis: 2026-07-12_
