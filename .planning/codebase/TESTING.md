# Testing

**Analysis Date:** 2026-07-13

## Framework

| Detail | Value |
|--------|-------|
| **Framework** | PHPUnit 13 |
| **Config** | `backend/phpunit.xml` |
| **Base class** | `Tests\TestCase` (extends Laravel's base TestCase) |

## Test Structure

```
backend/tests/
├── TestCase.php
├── Feature/
│   ├── RunApiTest.php              # Endpoint validation, queueing, rate limiting
│   └── ExecuteLauncherJobTest.php  # Job execution with mocked GitHub/AI
└── Unit/
    ├── GitHubContextAssemblerTest.php
    ├── GitHubContextFetcherTest.php
    ├── GitHubServiceTest.php
    ├── ContextEncoderTest.php
    ├── OpenAIProviderTest.php
    └── RunStreamerTest.php         # SSE deadline/terminal behavior
```

## Patterns

### Feature Tests
- Use `RefreshDatabase` + seed to reset state.
- `Queue::fake()` when asserting job dispatch (don't execute jobs in HTTP tests).
- Mock `GitHubService` and `OpenAIProvider` in job execution tests.
- Assert HTTP status codes (202 for create, 200 for show, 422 for validation).
- Assert rate limiting (429 after 5 requests).

### Unit Tests
- Mock external dependencies (GitHub HTTP, OpenAI HTTP).
- Test individual service classes in isolation.
- `RunStreamerTest.php`: asserts terminal state delivery, 55s deadline behavior.
- `ContextEncoderTest.php`: asserts truncation at 120KB budget.

### Mocking
- Laravel's `Http::fake()` for HTTP clients.
- `Log::spy()` to assert keys are never logged.
- `Event::fake()` when needed.

## Test Commands

```bash
php artisan test                          # Run all tests
php artisan test --filter=SomeTest        # Run specific test
```

Frontend: `npm run test` is a no-op placeholder (no JS test framework configured).

## Coverage Gaps (known)

- Job-timeout/orphan-run path untested (HIGH).
- SSE no-terminal-event-on-timeout + frontend poll fallback (HIGH).
- Proxy/rate-limit keying behavior (MEDIUM).
- `libsql` config / production guards (MEDIUM).
- Frontend demo vs live separation (MEDIUM).
- OpenAIProvider non-OpenAI bases (LOW).

## CI

- GitHub Actions: `php artisan test` runs in `backend` job.
- Pre-commit hooks: no test execution (format/lint only).

---

*Testing analysis: 2026-07-13*
