# Testing

## Test Frameworks

| Layer | Framework | Config |
|---|---|---|
| PHP Unit | PHPUnit 13 | `phpunit.xml` |
| PHP Feature | PHPUnit + Laravel | `tests/TestCase.php` |
| Frontend Unit | Vitest 4 | `vitest.config.ts` |
| Frontend E2E | Playwright | `playwright.config.ts` |

## Test Structure

```
tests/
├── TestCase.php              # Base test case (RefreshDatabase, etc.)
├── Unit/                     # 11 unit test files
│   ├── OpenAIProviderTest.php
│   ├── OpenRouterProviderTest.php
│   ├── AnthropicProviderTest.php   (implied)
│   ├── GitHubServiceTest.php       (implied)
│   ├── GitHubContextFetcherTest.php
│   ├── ContextEncoderTest.php
│   ├── RunStreamerTest.php
│   ├── AiProviderRegistryTest.php
│   ├── JsonSchemaValidatorTest.php (implied)
│   ├── CredentialCipherTest.php
│   └── CacheRunProgressedVersionTest.php
├── Feature/                  # 18 feature test files
│   ├── RunApiTest.php
│   ├── RunOwnershipTest.php
│   ├── RunHistoryTest.php
│   ├── RunPromptSnapshotTest.php
│   ├── SessionRunCsrfTest.php
│   ├── ExecuteLauncherJobTest.php
│   ├── LauncherPromptApiTest.php
│   ├── MagicLinkAuthTest.php
│   ├── PasswordAuthTest.php
│   ├── ProviderCredentialApiTest.php
│   ├── ProviderCredentialBaseUrlValidationTest.php
│   ├── SavedCredentialLaunchTest.php
│   ├── AccountDeletionTest.php
│   ├── SuperAdminBootstrapSeederTest.php
│   ├── TrendingRepositoriesApiTest.php
│   ├── FilamentPanelAccessTest.php
│   ├── ReapStuckRunsTest.php
│   └── RunRequiresProviderKeyTest.php
└── E2E/                      # 4 Playwright spec files
    └── flows/
        └── demo-full-flow.spec.ts
```

## Frontend Tests

```
resources/ts/
├── components/__tests__/
│   ├── AppViews.test.tsx
│   ├── HomeSubComponents.test.tsx
│   ├── LaunchAreaCredentials.test.tsx
│   └── Report.test.tsx
└── lib/__tests__/
    └── runModels.test.ts
```

## Testing Patterns

### PHP Feature Tests
```php
use RefreshDatabase;
use Queue::fake();           // Prevent actual job dispatch
use Http::fake();            // Mock GitHub/OpenAI API calls

// Seed launchers for consistent test data
$this->seed();

// Test API endpoints
$response = $this->postJson('/api/runs', [...]);
$response->assertStatus(202);
```

### PHP Unit Tests
- Test single class in isolation
- Mock dependencies via Mockery or Laravel's `mock()` helper
- Use `app->make()` for container-resolved classes with dependencies

### Frontend Unit Tests
- Vitest + React Testing Library
- `@testing-library/jest-dom` for DOM assertions
- `@testing-library/user-event` for user interactions
- `jsdom` environment for DOM simulation

### E2E Tests
- Playwright against a real Laravel server
- `scripts/e2e/serve-real.sh` starts server with `QUEUE_CONNECTION=sync`
- Requires `OPENAI_API_KEY` for full run-to-report flows

## Running Tests

```bash
# Backend
php artisan test                         # All 216 tests
php artisan test --filter=RunApiTest     # Focused test
./vendor/bin/pint --test                 # Style check

# Frontend
npm test                                 # Vitest (component tests)
npm run test:e2e                         # Playwright (E2E)
npm run typecheck                        # TypeScript strict check
npm run lint                             # oxlint + oxfmt
npm run konsistent                       # Structural conventions
```

## CI Pipeline

`.github/workflows/ci.yml`:

| Job | Runtime | Checks |
|---|---|---|
| **backend** | PHP 8.4 | `composer validate`, `php artisan test`, `pint --test` |
| **frontend** | Node 24 | `typecheck`, `lint`, `konsistent`, `build`, `test` |

Additional CI checks:
- GitGuardian Security Checks
- Socket Security (Project Report + PR Alerts)
- Code Review Doctor
- CodeRabbit (automated review)
- Deploy Staging (Dokku)

## Test Metrics

| Metric | Count |
|---|---|
| PHP test files | 29 |
| PHP tests passed | 216 |
| PHP assertions | 635 |
| TS test files | 8 |
| E2E spec files | 4 |

## Mocking Strategy

- **GitHub API**: `Http::fake()` with JSON fixtures
- **AI Providers**: Mock provider classes or `Http::fake()`
- **Queue**: `Queue::fake()` to prevent actual job dispatch
- **Auth**: `actingAs()` for authenticated user tests
- **Rate limiting**: Test limiters directly via `RateLimiter`
