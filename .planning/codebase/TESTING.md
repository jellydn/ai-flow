# Testing

## Frameworks

| Layer | Framework | Version | Command |
|-------|-----------|---------|---------|
| Backend Unit/Feature | **PHPUnit** | 13.0 | `php artisan test` |
| Frontend Unit/Component | **Vitest** | 4.1.10 | `npm test` (no-op placeholder) |
| E2E | **Playwright** | 1.61.1 | `npx playwright test` |

## Backend: PHPUnit

### Configuration
- Config: `backend/phpunit.xml`
- Base class: `tests/TestCase.php`
- Database: `RefreshDatabase` trait used in feature tests
- Seeders: `$this->seed()` in feature tests
- Environment: `.env.testing` or `.env` with `DB_CONNECTION=sqlite`

### Test Structure
```
tests/
├── TestCase.php                    # Base: CreatesApplication, setUp/tearDown
├── Feature/                        # 18 feature test files
│   ├── RunApiTest.php              # Core run creation + lifecycle
│   ├── ExecuteLauncherJobTest.php  # Job orchestration
│   ├── ReapStuckRunsTest.php       # Stuck run reaper
│   ├── RunStreamerTest.php         # (Unit) SSE streaming
│   ├── RunOwnershipTest.php        # Authorization policies
│   ├── RunHistoryTest.php          # User run history
│   ├── RunPromptSnapshotTest.php   # Prompt override snapshotting
│   ├── RunRequiresProviderKeyTest.php
│   ├── LauncherPromptApiTest.php
│   ├── MagicLinkAuthTest.php
│   ├── PasswordAuthTest.php
│   ├── AccountDeletionTest.php
│   ├── SessionRunCsrfTest.php
│   ├── ProviderCredentialApiTest.php
│   ├── ProviderCredentialBaseUrlValidationTest.php
│   ├── SavedCredentialLaunchTest.php
│   ├── FilamentPanelAccessTest.php
│   ├── SuperAdminBootstrapSeederTest.php
│   └── TrendingRepositoriesApiTest.php
├── Unit/                           # 12 unit test files
│   ├── AiProviderRegistryTest.php
│   ├── AnthropicProviderTest.php   # (ADR-0022)
│   ├── CacheRunProgressedVersionTest.php
│   ├── ContextEncoderTest.php
│   ├── CredentialCipherTest.php
│   ├── GeminiProviderTest.php      # (ADR-0022)
│   ├── GitHubContextAssemblerTest.php
│   ├── GitHubContextFetcherTest.php
│   ├── GitHubServiceTest.php
│   ├── OpenAIProviderTest.php
│   ├── OpenRouterProviderTest.php
│   └── RunStreamerTest.php
└── E2E/
    └── flows/
        └── demo-full-flow.spec.ts
```

**Total**: 216 tests, 635 assertions as of latest run.

### Mocking Patterns

**HTTP Faking (AI Providers)**:
```php
Http::fake([
    'api.openai.com/v1/chat/completions' => Http::response([...], 200),
    'openrouter.ai/api/v1/key' => Http::response(['data' => [...]], 200),
]);
Http::assertSent(fn ($request) => $request->hasHeader('Authorization'));
```

**Queue Faking**:
```php
Queue::fake();
// ... dispatch job ...
Queue::assertPushed(ExecuteLauncherJob::class, fn ($job) => ...);
```

**Service Mocking**:
```php
$this->mock(GitHubService::class, fn ($mock) =>
    $mock->shouldReceive('parse')->andReturn(...)
);
```

**Config Overrides** (for provider tests):
```php
config()->set('services.openai.key', 'sk-test');
config()->set('services.openai.openrouter_key', null);
```

### Key Testing Principles

- **Feature tests** use `RefreshDatabase` + seed, `Queue::fake()` for job dispatch, mock GitHub/AI for isolation
- **Unit tests** test one class in isolation, `Http::fake()` for HTTP, no database
- New launcher = PHP class + `DatabaseSeeder` entry + **feature test**
- New service = **unit test** for that service
- Test the **interface**, not the implementation — `AIProviderInterface` is the test surface
- `allowCustom` parameter in `resolveModel()` tested via `AiProviderRegistryTest`
- Provider adapter tests verify: generate success, invalid key, connection errors, JSON errors, no-key-configured, config fallback, custom base URL

### Running Tests

```bash
# All backend tests
php artisan test

# Specific test
php artisan test --filter=RunApiTest

# Specific test method
php artisan test --filter=test_run_passes_null_provider_when_provider_id_omitted

# With coverage (requires Xdebug/PCOV)
php artisan test --coverage
```

## Frontend: Vitest

### Configuration
- Config: `backend/vitest.config.ts`
- Environment: `jsdom` ^29.1.1
- Testing Library: `@testing-library/react` ^16.3.2

### Test Locations
- `resources/ts/components/__tests__/` — Component tests
  - `AppViews.test.tsx`
  - `HomeSubComponents.test.tsx`
  - `LaunchAreaCredentials.test.tsx`
  - `Report.test.tsx`
- `resources/ts/lib/__tests__/` — Utility tests
  - `runModels.test.ts`

### Running
```bash
npm test          # Currently no-op — use vitest directly
npx vitest run    # Run once
npx vitest        # Watch mode
```

## E2E: Playwright

### Configuration
- Config: `backend/playwright.config.ts`
- Test: `tests/E2E/flows/demo-full-flow.spec.ts`
- Serve script: `scripts/e2e/serve-real.sh`

### Running
```bash
npx playwright test
npx playwright test --ui       # Interactive
npx playwright test --debug    # Debug mode
```

## CI

`.github/workflows/ci.yml` runs on every PR:
- **Backend** (PHP 8.4, sqlite3 + pgsql ext):
  - `composer validate`
  - `php artisan test` (all 216 tests)
  - `./vendor/bin/pint --test`
- **Frontend** (Node 24):
  - `npm run typecheck` (tsc --noEmit)
  - `npm run lint` (oxlint + oxfmt --check)
  - `npm run konsistent`
  - `npm run build` (tsc + vite build)
  - `npm test` (no-op placeholder)
