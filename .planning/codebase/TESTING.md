# Testing

Framework, structure, mocking patterns, and coverage for ai-flow.

## Backend — PHPUnit 13

### Config (`backend/phpunit.xml`)

| Setting | Value |
|---------|-------|
| Env | `APP_ENV=testing` |
| DB | SQLite `:memory:` |
| Cache | `array` |
| Queue | `sync` |
| Session | `array` |
| Mail | `array` |
| Sentry | disabled (empty DSN) |
| API keys | `OPENAI_API_KEY=phpunit-test-openai-key`, `OPENROUTER_API_KEY=phpunit-test-openrouter-key` |
| BCRYPT_ROUNDS | 4 (fast) |

### Structure

```
tests/
├── TestCase.php                       # Base (extends Illuminate\Foundation\Testing\TestCase)
├── Unit/  (15 files)                  # Isolated unit tests
│   ├── AiProviderRegistryTest.php
│   ├── AnthropicProviderTest.php
│   ├── BaseAIProviderJsonExtractionTest.php
│   ├── CacheRunProgressedVersionTest.php
│   ├── ContextEncoderTest.php
│   ├── CredentialCipherTest.php
│   ├── GeminiProviderTest.php
│   ├── GitHubServiceFetchTest.php
│   ├── GitHubServiceTest.php
│   ├── GitHubTrendingServiceParseTest.php
│   ├── JsonSchemaValidatorTest.php
│   ├── LaunchParametersTest.php
│   ├── OpenAIProviderTest.php
│   ├── OpenRouterProviderTest.php
│   └── RunStreamerTest.php
└── Feature/  (19 files)               # Integration tests with RefreshDatabase
    ├── AccountDeletionTest.php
    ├── ExecuteLauncherJobTest.php
    ├── FilamentPanelAccessTest.php
    ├── LauncherPromptApiTest.php
    ├── MagicLinkAuthTest.php
    ├── PasswordAuthTest.php
    ├── ProviderCredentialApiTest.php
    ├── ProviderCredentialBaseUrlValidationTest.php
    ├── ReapStuckRunsTest.php
    ├── RunApiTest.php
    ├── RunHistoryTest.php
    ├── RunOwnershipTest.php
    ├── RunPromptSnapshotTest.php
    ├── RunRequiresProviderKeyTest.php
    ├── SavedCredentialLaunchTest.php
    ├── SessionRunCsrfTest.php
    ├── SuperAdminBootstrapSeederTest.php
    ├── TrendingRepositoriesApiTest.php
    └── UserLauncherTest.php
```

### Patterns

| Pattern | How |
|---------|-----|
| Database | `RefreshDatabase` trait + `$this->seed()` in `setUp()` |
| Queue | `Queue::fake()` for dispatch assertions; `sync` driver for job execution tests |
| GitHub mock | Mock `GitHubService` or use `GitHubServiceFetchTest` with mocked HTTP |
| AI mock | Mock `AIProviderInterface` — providers have unit tests with mocked Http facade |
| Factories | `LauncherFactory`, `UserLauncherFactory`, `RunFactory`, `User::factory()`, `ProviderCredential::factory()` |
| Rate limiting | Tests set higher limits via env or test-specific config |

### Commands

```bash
php artisan test                          # full suite
php artisan test --filter=SomeTest        # focused
./vendor/bin/pint --test && ./vendor/bin/pint  # CI: --test fails on violations
```

## Frontend — Vitest 4

### Config (`backend/vitest.config.ts`)

| Setting | Value |
|---------|-------|
| Environment | `jsdom` |
| Globals | `true` |
| Setup | `resources/ts/test/setup.ts` (Testing Library jest-dom matchers) |
| Include | `resources/ts/**/*.test.{ts,tsx}` |

### Structure

```
resources/ts/
├── test/setup.ts                              # Vitest setup (jest-dom)
├── components/__tests__/  (7 files)           # Component tests
│   ├── AppViews.test.tsx
│   ├── DashboardAccount.test.tsx
│   ├── HomeSubComponents.test.tsx
│   ├── LaunchAreaCredentials.test.tsx
│   ├── ProviderSettingsComponents.test.tsx
│   ├── Report.test.tsx
│   └── RunHistory.test.tsx
└── lib/__tests__/runModels.test.ts            # Utility tests
```

### Patterns

- **Testing Library** (`@testing-library/react`, `@testing-library/user-event`, `@testing-library/jest-dom`).
- Component tests render React components, simulate user events, assert DOM.
- `act()` warnings in `RunHistory.test.tsx` and `DashboardAccount.test.tsx` (known, non-blocking).
- Mock fetch via `vi.fn()` / `vi.mock()` for API calls.

### Command

```bash
npm run test          # vitest run
npm run test:watch    # vitest (watch mode)
```

## E2E — Playwright 1.61

### Config (`backend/playwright.config.ts`)

| Setting | Value |
|---------|-------|
| Test dir | `./tests/E2E` |
| Test match | `**/*.real.spec.ts` |
| Project | `real-backend` (Desktop Chrome) |
| Web server | `bash scripts/e2e/serve-real.sh` (port 8000, 180s timeout) |
| CI | `retries: 1`, `reporter: github`, `forbidOnly: true` |
| Trace | `on-first-retry` |
| Screenshot | `only-on-failure` |

### Structure

```
tests/E2E/
├── flows/
│   ├── all-launchers.real.spec.ts
│   ├── launcher-prompts.real.spec.ts
│   ├── real-api-flow.real.spec.ts
│   └── ... (other flow specs)
└── helpers/
    ├── authCard.ts
    └── uniqueEmail.ts              # E2E_PASSWORD, uniqueEmail() helper
```

### Patterns

- Serial mode (`test.describe.configure({ mode: "serial" })`).
- Real backend (Laravel serve + `QUEUE_CONNECTION=sync`).
- Auth via registration (unique email + `E2E_PASSWORD`).
- Tab navigation by role (`page.getByRole("tab", { name: "..." })`).

### Command

```bash
npm run test:e2e     # npx playwright test --project=real-backend
```

## CI gate (`.github/workflows/ci.yml`)

| Job | Runner | Steps |
|-----|--------|-------|
| Backend | PHP 8.4 | `composer validate`, `php artisan test`, `pint --test` |
| Frontend | Node 24 | `typecheck`, `lint`, `konsistent`, `build`, `test` (vitest) |

## Pre-commit hooks (`.pre-commit-config.yaml`)

Run via prek: `just prek` or `prek run --all-files`.

| Hook | Scope |
|------|-------|
| trailing-whitespace | all files |
| end-of-file-fixer | all files |
| check-yaml | YAML files |
| check-added-large-files | all files |
| composer-validate | `composer.json`/`composer.lock` |
| pint | `*.php` |
| frontend-typecheck | TS/TSX |
| oxlint | TS/TSX |
| oxfmt check | TS/TSX |
| konsistent | TS/TSX |

## Local gate

`just ci` = `pint-check test typecheck lint-js konsistent build` (full backend + frontend).
