# Testing

## Frameworks

| Layer | Framework | Config | Runner |
|---|---|---|---|
| Backend Unit | PHPUnit `^13.0` | `backend/phpunit.xml` | `php artisan test` or `composer test` |
| Backend Feature | PHPUnit `^13.0` | `backend/phpunit.xml` | `php artisan test` or `composer test` |
| Frontend Unit | Vitest | `backend/vitest.config.ts` (jsdom, setup file) | `npm test` or `npm run test:watch` |
| E2E | Playwright | `backend/playwright.config.ts` | `npm run test:e2e` (demo) / `npm run test:e2e:real` (real backend) |

## Backend Tests

### Structure

```
backend/tests/
├── TestCase.php                     # Base (extends Illuminate\Foundation\Testing\TestCase)
├── Unit/                            # 11 unit tests — isolated service/class tests
│   ├── AiProviderRegistryTest.php
│   ├── AnthropicProviderTest.php
│   ├── CacheRunProgressedVersionTest.php
│   ├── ContextEncoderTest.php
│   ├── CredentialCipherTest.php
│   ├── GeminiProviderTest.php
│   ├── GitHubServiceFetchTest.php
│   ├── GitHubServiceTest.php
│   ├── OpenAIProviderTest.php
│   ├── OpenRouterProviderTest.php
│   └── RunStreamerTest.php
└── Feature/                         # 18 feature tests — HTTP + DB + queue integration
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
    └── TrendingRepositoriesApiTest.php
```

### PHPUnit Configuration (`backend/phpunit.xml`)

- **Suites**: `Unit` → `tests/Unit`, `Feature` → `tests/Feature`
- **Database**: in-memory SQLite (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`)
- **Env**: `OPENAI_API_KEY=test-key-openai`, `OPENROUTER_API_KEY=test-key-openrouter`, `GITHUB_TOKEN=` (empty)
- **Bootstrap**: `vendor/autoload.php`

### Test Patterns

#### Base TestCase

```php
abstract class TestCase extends BaseTestCase {} // Illuminate\Foundation\Testing\TestCase
```

#### Feature Tests — `RefreshDatabase` + Seed

```php
class RunApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();  // DatabaseSeeder → 4 launchers + super admin
    }

    public function test_guest_can_create_run(): void
    {
        Queue::fake();
        $response = $this->postJson('/api/runs', [
            'launcher' => 'review-pr',
            'source_url' => 'https://github.com/owner/repo/pull/1',
        ]);
        $response->assertStatus(202);
        Queue::assertPushed(ExecuteLauncherJob::class);
    }
}
```

Key patterns:
- `use RefreshDatabase` — migrate fresh each test
- `$this->seed()` — `DatabaseSeeder` (4 launchers + `SuperAdminBootstrapSeeder`)
- `Queue::fake()` — intercept job dispatch; `Queue::assertPushed()`, `Queue::assertNothingPushed()`
- `actingAs($user)` — authenticated requests
- `postJson`, `getJson`, `assertStatus`, `assertJsonCount`, `assertDatabaseHas`

#### Unit Tests — Isolated Service/Class

```php
class OpenAIProviderTest extends TestCase
{
    public function test_generate_sends_expected_request(): void
    {
        config()->set('services.openai.key', 'test-key');
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => '{"summary":"..."}']]],
            ]),
        ]);

        $provider = new OpenAIProvider('test-key');
        $result = $provider->generate('prompt', $schema);

        Http::assertSent(fn (Request $r) => $r->url() === '...'
            && $r->hasHeader('Authorization', 'Bearer test-key'));
    }
}
```

Key patterns:
- `config()->set(...)` — mock config values
- `Http::fake([...])` — intercept external API calls
- `Http::assertSent(fn (Request $r) => ...)` — verify outgoing request shape (URL, headers, body)
- Direct instantiation: `new OpenAIProvider('test-key')`

### Mocking

| What | How |
|---|---|
| External HTTP (OpenAI, Anthropic, Gemini, OpenRouter, GitHub) | `Http::fake([url => Http::response(...)])` |
| Job dispatch | `Queue::fake()` + `Queue::assertPushed(ExecuteLauncherJob::class)` |
| Config values | `config()->set('services.openai.key', 'test-key')` |
| Time | `Carbon::setTestNow(...)` or `$this->travelTo(...)` |
| Database | `RefreshDatabase` trait (migrate fresh) + `$this->seed()` |

### Factories

- **`database/factories/UserFactory.php`** — only factory currently defined
  - `definition()`: `name`, `email`, `password` via `fake()`
  - State modifiers: `unverified()`
- Other models (`Run`, `Launcher`, `ProviderCredential`) are created directly in tests via Eloquent or seeders

### Coverage Gaps

- No dedicated factories for `Run`, `Launcher`, `ProviderCredential`, `LauncherPromptOverride` (created inline)
- No dedicated test for `ContextBudget` constants (covered indirectly via `ContextEncoderTest`)
- No dedicated test for `LaunchParameters` (covered indirectly via `RunApiTest`, `RunRequiresProviderKeyTest`)
- No dedicated test for `JsonSchemaValidator` (covered indirectly via `ExecuteLauncherJobTest`)
- No integration test for SSE streaming over real HTTP (covered via `RunStreamerTest` unit test with array cache)

## Frontend Tests (Vitest)

### Structure

```
backend/resources/ts/
├── components/__tests__/
│   ├── AppViews.test.tsx               (273 lines)
│   ├── DashboardAccount.test.tsx       (272 lines)
│   ├── HomeSubComponents.test.tsx      (393 lines)
│   ├── LaunchAreaCredentials.test.tsx  (313 lines)
│   ├── ProviderSettingsComponents.test.tsx (337 lines)
│   └── RunHistory.test.tsx             (182 lines)
└── lib/__tests__/
```

### Vitest Configuration (`backend/vitest.config.ts`)

- **Environment**: `jsdom`
- **Root**: `resources/ts`
- **Setup file**: `resources/ts/test/setup.ts`
- **Test pattern**: `**/*.test.{ts,tsx}`

### Frontend Test Patterns

- `@testing-library/react` for component testing
- Render components, query by role/text, assert on output
- Mock `services/run.ts` / `services/auth.ts` as needed
- Note: `npm test` is currently a **no-op in CI** (per `package.json` `test` script and CI workflow); tests exist but run locally

## E2E Tests (Playwright)

### Structure

```
backend/tests/E2E/
├── flows/
│   ├── auth-user-flow.real.spec.ts     # Auth flows (password + magic link) with real backend
│   ├── demo-full-flow.spec.ts          # Full app flow (demo backend)
│   └── launcher-prompts.real.spec.ts   # Launcher prompt overrides with real backend
└── helpers/
    └── uniqueEmail.ts                  # Unique email generator for E2E
```

### E2E Configuration (`backend/playwright.config.ts`)

- Two modes: demo (mocked) and real (real backend)
- Scripts: `npm run test:e2e` (demo), `npm run test:e2e:real` (real)
- Real-backend server script: `backend/scripts/e2e/serve-real.sh`

## CI Testing

`.github/workflows/ci.yml`:
- **Backend** (PHP 8.4, `sqlite3` + `pgsql` ext): `composer validate`, `php artisan test`, `pint --test`
- **Frontend** (Node 24): `typecheck` (`tsc --noEmit`), `lint` (`oxlint`), `konsistent`, `build` (`tsc --noEmit && vite build`), `test` (no-op currently)

## Test Commands

| Command | What | Where |
|---|---|---|
| `php artisan test` | Full backend suite | `backend/` |
| `php artisan test --filter=SomeTest` | Focused test | `backend/` |
| `composer test` | Full backend suite (with config clear) | `backend/` |
| `./vendor/bin/pint --test` | Pint format check (CI fails on violations) | `backend/` |
| `./vendor/bin/pint` | Pint format fix | `backend/` |
| `npm test` | Vitest frontend unit | `backend/` |
| `npm run test:watch` | Vitest watch mode | `backend/` |
| `npm run test:e2e` | Playwright (demo) | `backend/` |
| `npm run test:e2e:real` | Playwright (real backend) | `backend/` |
| `just test` | Unified (PHP + JS) | repo root |
| `just ci` | Full CI: pint, test, typecheck, lint, konsistent, build | repo root |

## Testing Gotchas

- Feature tests use `RefreshDatabase` + `$this->seed()` — every test starts with 4 launchers + super admin
- `Queue::fake()` is essential — real `ExecuteLauncherJob` would make HTTP calls to GitHub/AI
- `Http::fake()` must match exact provider URLs (e.g., `api.openai.com/*`, `api.anthropic.com/*`)
- `RunStreamer` falls back to unconditional DB refresh when cache is `array` driver (tests) — `shouldRefresh(null, ...) === true`
- `OPENROUTER_API_KEY=test-key-openrouter` set in `phpunit.xml` for guest run tests
- In-memory SQLite means migrations run every test — keep them fast
- Frontend `npm test` is a no-op in CI; run locally with `npm test` or `npm run test:watch`
