# Testing

**Analysis Date:** 2026-07-13

## Framework & Configuration

| Aspect | Detail |
|--------|--------|
| Framework | PHPUnit 13 |
| Config | `backend/phpunit.xml` |
| Base class | `Tests\TestCase` extends `Illuminate\Foundation\Testing\TestCase` |
| Database | SQLite in-memory (`DB_CONNECTION=sqlite` for tests) |
| Traits used | `RefreshDatabase` in feature tests |

## Test Structure

```
backend/tests/
├── TestCase.php                              # Base test case
├── Feature/                                   # 8 integration tests
│   ├── ExecuteLauncherJobTest.php            # Job execution with mocked GitHub/AI
│   ├── MagicLinkAuthTest.php                 # Magic link request + verification
│   ├── ProviderCredentialApiTest.php         # Credential CRUD + verify + defaults
│   ├── ReapStuckRunsTest.php                 # Stuck run cleanup command
│   ├── RunApiTest.php                        # Run create, show, stream endpoints
│   ├── RunHistoryTest.php                    # Authenticated run history CRUD
│   └── RunOwnershipTest.php                  # Run ownership authorization
└── Unit/                                      # 8 unit tests
    ├── CacheRunProgressedVersionTest.php     # SSE version caching
    ├── ContextEncoderTest.php                # Context size bounding
    ├── CredentialCipherTest.php              # Provider credential encryption
    ├── GitHubContextAssemblerTest.php        # Context assembly from raw data
    ├── GitHubContextFetcherTest.php          # GitHub REST API calls
    ├── GitHubServiceTest.php                 # URL parsing + context fetching
    ├── OpenAIProviderTest.php                # AI provider interface
    └── RunStreamerTest.php                   # SSE streaming behavior
```

**Total: 16 test files, 96 tests, 291 assertions (passing).**

## Testing Patterns

### Feature Tests

- **`RefreshDatabase` trait** ensures clean state per test.
- **Database seeding:** Tests rely on `DatabaseSeeder` for launcher records.
- **HTTP assertions:** `$this->post('/api/runs', ...)`, `$this->get('/api/user/runs')`, status code checks.
- **JSON structure assertions:** `assertJsonStructure()`, `assertJsonFragment()`.
- **Rate limiting:** Tests verify 429 responses after exceeding throttle limits.

### Job Testing

- `Queue::fake()` to prevent actual job execution.
- Assert job dispatch: `Queue::assertPushed(ExecuteLauncherJob::class)`.
- Separate test for job execution with mocked dependencies: `ExecuteLauncherJobTest` uses `Queue::fake()` to prevent dispatch but manually instantiates the job with mocked services.

### Unit Tests

- **Mocking:** External services (GitHub HTTP, OpenAI HTTP) mocked via Laravel's `Http::fake()`.
- **Value object testing:** `GitHubReference` creation from various URL formats.
- **Context bounds testing:** `ContextEncoder` tested with known input sizes.
- **SSE streaming:** `RunStreamer` tested with mock `Run` model states.

### Auth Testing

- `MagicLinkAuthTest`: Tests token generation, email sending (Mail::fake()), token verification.
- `RunOwnershipTest`: Tests that users can only access their own runs.
- `ProviderCredentialApiTest`: Tests credential CRUD with authentication.

## Mocking Strategy

| What | How |
|------|-----|
| GitHub API | `Http::fake()` with canned JSON responses |
| OpenAI API | `Http::fake()` with controlled chat completion responses |
| Queue | `Queue::fake()` to prevent real job execution |
| Mail | `Mail::fake()` to prevent real email sending |
| Cache | `Cache::fake()` or real cache in unit tests |
| Events | `Event::fake()` when testing event dispatching |

## Running Tests

```bash
cd backend
php artisan test                          # Run all tests
php artisan test --filter=RunApiTest      # Run specific test class
php artisan test --filter=test_store      # Run specific test method
```

**CI (`.github/workflows/ci.yml`):** `php artisan migrate --force` → `php artisan test`.

## Frontend Testing

- **No frontend tests currently.** `npm run test` in `backend/package.json` is a no-op placeholder (`echo "no frontend tests yet"`).
- TypeScript `tsc --noEmit` acts as compile-time verification.
- `npm run doctor` runs `react-doctor` for codebase analysis.
- `npm run konsistent` enforces structural conventions.

## Test Quality Notes

- Unit tests cover core services: GitHub parsing, context encoding, SSE streaming, provider verification.
- Feature tests cover the primary API surface: run create/show/stream, auth flows, provider credential management, run history.
- Job execution is tested both as dispatch assertion (integration) and with mocked dependencies (unit).
- Rate limiting and authorization (ownership) are tested at the feature level.
- Missing: no frontend component tests, no E2E tests.

---

*Testing analysis: 2026-07-13*
