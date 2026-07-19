# Testing

## Frameworks

| Layer | Framework | Config |
|-------|-----------|--------|
| PHP unit/feature | PHPUnit 13 | `phpunit.xml` |
| Frontend unit | Vitest 4 | `vitest.config.ts` |
| E2E | Playwright 1.61 | `playwright.config.ts` |

## Test Commands

```bash
# Backend
cd backend
php artisan test                    # All PHP tests
php artisan test --filter=SomeTest  # Focused test

# Frontend
npm run test                        # Vitest (unit)
npm run test:e2e                    # Playwright (--project=real-backend)

# Full CI gate
just ci                             # pint-check + test + typecheck + lint-js + konsistent + build
```

## PHP Testing

### Structure
```
tests/
├── Unit/                          # Unit tests (no database)
│   ├── GitHubServiceTest.php
│   ├── AiProviderRegistryTest.php
│   ├── JsonSchemaValidatorTest.php
│   ├── RunStreamerTest.php
│   └── ... (per-provider tests)
├── Feature/                       # Feature tests (with database)
│   ├── RunApiTest.php
│   ├── RunOwnershipTest.php
│   ├── TrendingRepositoriesApiTest.php
│   ├── ProviderCredentialApiTest.php
│   └── ... (auth, account deletion, etc.)
└── TestCase.php                   # Base test case
```

### Patterns

- **RefreshDatabase** trait on feature tests — fresh DB per test
- **Seed**: `DatabaseSeeder` runs for feature tests (seeds built-in launchers + super admin)
- **Queue::fake()** for dispatch — never executes jobs in HTTP tests
- **Mock GitHub/AI**: `Http::fake()` for external API calls
- **Database assertions**: `assertDatabaseHas()`, `assertDatabaseMissing()`, `assertModelExists()`
- **JSON assertions**: `assertJsonPath()`, `assertJsonFragment()`

### Key Test Patterns

```php
// Feature test: run creation
public function test_authenticated_user_can_create_run(): void
{
    $user = User::factory()->create();
    $response = $this->actingAs($user)->postJson('/api/runs', [
        'launcher' => 'review-pr',
        'source_url' => 'https://github.com/owner/repo/pull/1',
    ]);
    $response->assertStatus(202);
    $this->assertDatabaseHas('runs', ['user_id' => $user->id]);
}

// Unit test: provider generation
public function test_openai_provider_generates_report(): void
{
    Http::fake(['api.openai.com/*' => Http::response(['choices' => [...]]), 200]);
    $provider = new OpenAIProvider();
    $result = $provider->generate('prompt', $schema, 'gpt-4o-mini');
    $this->assertIsArray($result);
}
```

## Frontend Testing (Vitest)

### Structure
```
resources/ts/components/__tests__/
├── AppViews.test.tsx
├── HomeSubComponents.test.tsx
└── LaunchAreaCredentials.test.tsx
```

### Patterns

- **@testing-library/react** for component rendering
- **@testing-library/user-event** for interactions
- **jsdom** environment for DOM simulation
- **vi.mock** for service module mocking

```typescript
// Component test
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

test('submit button triggers createRun', async () => {
    const user = userEvent.setup();
    render(<LaunchArea createRun={vi.fn()} />);
    await user.click(screen.getByRole('button', { name: /run/i }));
    expect(createRunMock).toHaveBeenCalled();
});
```

## E2E Testing (Playwright)

### Structure
```
tests/E2E/flows/
├── real-api-flow.real.spec.ts
├── launcher-prompts.real.spec.ts
└── all-launchers.real.spec.ts
```

### Setup
- **Serve script**: `scripts/e2e/serve-real.sh` — starts Laravel with `QUEUE_CONNECTION=sync`
- **Project**: `real-backend` (configured in `playwright.config.ts`)
- **Database**: Fresh SQLite, seeded

### Patterns

```typescript
// E2E test
test('user can launch a review-pr run', async ({ page }) => {
    await page.goto('/');
    await page.fill('[data-testid="source-url"]', 'https://github.com/owner/repo/pull/1');
    await page.click('[data-testid="run-button"]');
    await expect(page.locator('[data-testid="run-status"]')).toContainText('completed');
});
```

## What's NOT Tested Yet

- **Custom launcher CRUD lifecycle**: Create, update, delete, slug uniqueness, unified listing (roadmap Phase 2)
- **Hidden launcher toggle behavior**: End-to-end visibility filtering
- **`is_public` run visibility**: Public/private access control in API
- **Custom launcher execution**: Running a custom launcher through the full queue pipeline

## CI Pipeline

`.github/workflows/ci.yml` runs on push/PR:

| Job | Commands | Environment |
|-----|----------|-------------|
| `backend` | `composer validate`, `pint --test`, `php artisan test` | PHP 8.4, SQLite + PgSQL ext |
| `frontend` | `typecheck`, `lint`, `konsistent`, `build`, `test` (vitest) | Node 24 |
| `e2e` | Playwright `--project=real-backend` | PHP 8.4 + Node 24 |
| `deploy` | Dokku staging deploy | On push to `main` |
