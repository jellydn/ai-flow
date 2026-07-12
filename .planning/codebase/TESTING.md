# Testing Patterns

**Analysis Date:** 2026-07-12

---

## Frontend Testing

### Status: No test framework configured

- **No test files** found in `src/` (only `main.jsx` and `styles.css`)
- **No test dependencies** in `package.json` `devDependencies` (empty `{}`)
- **No test script** in `package.json` scripts -- only `dev`, `build`, `preview`
- **No coverage requirements** or configuration
- **No linting or type checking** that could substitute for tests

**Verdict:** Frontend is a prototype with zero automated tests. Quality is manual-only.

### Run Commands (frontend only, no tests)

```bash
npm run dev      # Start Vite dev server
npm run build    # Production build (no test step)
npm run preview  # Preview production build
```

---

## Backend Testing

### Test Framework

- **PHPUnit** 11.5 configured (in `composer.json` require-dev)
- Configuration in `backend/phpunit.xml`
- Two test suites defined: `Unit` and `Feature`
- Tests extend `Tests\TestCase` (which extends `Illuminate\Foundation\Testing\TestCase`)
- **Mockery** available for mocking (in `composer.json` require-dev)

### Run Commands

```bash
cd backend && php artisan test          # Laravel test runner (recommended)
cd backend && ./vendor/bin/phpunit      # PHPUnit directly
cd backend && composer run test         # Runs `php artisan config:clear && php artisan test`
```

### phpunit.xml Configuration (key settings)

```xml
<env name="APP_ENV" value="testing"/>
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>   <!-- In-memory SQLite -->
<env name="QUEUE_CONNECTION" value="sync"/>  <!-- Jobs run synchronously -->
<env name="CACHE_STORE" value="array"/>
<env name="SESSION_DRIVER" value="array"/>
```

- **3 test files** total, covering both unit and feature tests

### Test File Organization

```
backend/tests/
  TestCase.php                          # Base test case (empty, extends Laravel's)
  Feature/
    RunApiTest.php                      # HTTP endpoint tests (59 lines)
    ExecuteLauncherJobTest.php          # Queue job tests (47 lines)
  Unit/
    GitHubServiceTest.php               # URL parsing unit tests (36 lines)
```

### Test Patterns Observed

#### Feature Test: `RunApiTest.php`
- Uses `RefreshDatabase` trait + `$this->seed()` to reset state
- **`Queue::fake()`** to assert job dispatching without execution
- **HTTP verb methods:** `$this->getJson()`, `$this->postJson()`
- **Assertion chaining:** `->assertOk()->assertExactJson([...])`
- **Assert JSON structure:** `assertJsonCount(4)`, `assertJsonPath('0.id', 'review-pr')`, `assertJsonMissingPath('0.class_name')`
- **Assert status codes:** `assertStatus(202)`, `assertStatus(429)`, `assertUnprocessable()`
- **Assert validation errors:** `assertJsonValidationErrors(['launcher', 'source_url'])`
- **Assert database:** `assertDatabaseHas('runs', ['id' => $r->json('id')])`
- **Assert queue:** `Queue::assertPushed(ExecuteLauncherJob::class)`
- **Rate limit test:** loops allowed requests then asserts 429

#### Feature Test: `ExecuteLauncherJobTest.php`
- Uses `RefreshDatabase` trait + `$this->seed()`
- **Mockery mocks** for `GitHubService` and `AIProviderInterface`:
  ```php
  $gh = Mockery::mock(GitHubService::class);
  $gh->shouldReceive('parse')->andReturn([...]);
  ```
- **Direct job handling** (not dispatching): `(new ExecuteLauncherJob($run->id))->handle($gh, $ai)`
- **Assert model state:** `$this->assertSame('completed', $run->fresh()->status)`
- Tests both happy path (structured result recorded) and failure path (malformed AI result)

#### Unit Test: `GitHubServiceTest.php`
- Extends `PHPUnit\Framework\TestCase` directly (not the app's `TestCase`)
- No database or Laravel bootstrapping needed
- **Assert exception:** `$this->expectException(InvalidArgumentException::class)`
- **Loop-based parameterized testing:** iterates malformed URLs and asserts each throws
- **Manual assertion counting:** `$this->addToAssertionCount(1)` for exceptions caught in try/catch
- **Assert exact values:** `assertSame()`, `assertSame('repository', ...['type'])`
- **Assert failure with message:** `$this->fail("Accepted malformed URL: {$url}")`

### Summary of Assertions Used

| Assertion | Test File |
|-----------|-----------|
| `assertOk()` | RunApiTest |
| `assertStatus(202/429/422)` | RunApiTest |
| `assertExactJson()` | RunApiTest |
| `assertJsonCount()` | RunApiTest |
| `assertJsonPath()` | RunApiTest |
| `assertJsonMissingPath()` | RunApiTest |
| `assertUnprocessable()` | RunApiTest |
| `assertJsonValidationErrors()` | RunApiTest |
| `assertDatabaseHas()` | RunApiTest |
| `assertSame()` | GitHubServiceTest, ExecuteLauncherJobTest |
| `expectException()` | GitHubServiceTest |
| `addToAssertionCount()` | GitHubServiceTest |
| `fail()` | GitHubServiceTest |
| `assertNull()` | ExecuteLauncherJobTest |
| `Queue::assertPushed()` | RunApiTest |

### Coverage

- **No coverage configuration** in `phpunit.xml` (no `<coverage>` element)
- **No minimum coverage requirements**
- **Source includes:** `<directory>app</directory>` (coverage instrumentation configured but no thresholds)
- **3 test files** cover: API endpoints (4 tests), job execution (2 tests), URL parsing (3 tests) = **9 total test methods**

### Testing Dependencies

```json
"require-dev": {
    "mockery/mockery": "^1.6",
    "phpunit/phpunit": "^11.5.50",
    "fakerphp/faker": "^1.23",
    "laravel/pail": "^1.2.2",
    "laravel/pint": "^1.24",
    "laravel/sail": "^1.41",
    "nunomaduro/collision": "^8.6"
}
```

### Testing Conventions (Backend)

1. **Feature tests use `RefreshDatabase` + `seed()`** for predictable state
2. **Use `Queue::fake()`** when the test does not need the job to run
3. **Mock external services** (GitHub, AI) with Mockery -- never call real APIs
4. **Feature tests extend app's `TestCase`**; pure unit tests may extend `PHPUnit\Framework\TestCase` directly
5. **Job tests call `handle()` directly** with mocked dependencies rather than dispatching
6. **Test method names** follow `snake_case` descriptive pattern: `test_lists_seeded_launchers_and_health`, `test_rejects_invalid_url_and_unknown_launcher`
7. **Validation tests** cover both valid input (success path) and invalid input (422/unprocessable)

### Future Considerations (from AGENTS.md)

Planned but not yet implemented:
- Feature tests for new launchers when added
- `Queue::fake()` pattern already established for job assertion
- GitHub/AI calls always mocked in tests -- never hit real endpoints
