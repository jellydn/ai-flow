# Testing

**Analysis Date:** 2026-07-14

## Frameworks

| Area | Framework | Config | Runner |
|------|-----------|--------|--------|
| Backend (PHP) | PHPUnit ^13 | `backend/phpunit.xml` | `php artisan test` |
| Frontend (TS) | Vitest + @testing-library/react | `backend/resources/ts/test/setup.ts` | `npx vitest run` |
| E2E | Playwright ^1.61 | Playwright projects: `demo`, `real-backend` | `npm run test:e2e:demo` |
| Mocking (PHP) | Mockery ^1.6 | — | Integrated with PHPUnit |
| Style (PHP) | Laravel Pint ^1.24 | — | `./vendor/bin/pint --test` |
| Style (TS) | oxlint + oxfmt | `.oxlintrc.json`, `.oxfmtrc.json` | `npm run lint` |

## Backend Tests (`backend/tests/`)

### Structure

```
tests/
├── TestCase.php                          # Base: boots Laravel app
├── Unit/                                 # Pure unit tests (some extend Tests\TestCase for config())
│   ├── AiProviderRegistryTest.php        # 10 tests: get/has/ids/list for 4 providers
│   ├── CacheRunProgressedVersionTest.php # Event listener caching
│   ├── ContextEncoderTest.php            # Byte budget encoding
│   ├── CredentialCipherTest.php          # Encrypt/decrypt/mask
│   ├── GitHubContextAssemblerTest.php    # Raw data → context array
│   ├── GitHubContextFetcherTest.php      # HTTP mocking + error mapping
│   ├── GitHubServiceTest.php             # URL parsing + cache
│   ├── OpenAIProviderTest.php            # Generate + verify + errors
│   ├── OpenRouterProviderTest.php        # 13 tests: generate, verify, configurable base URL/referer
│   └── RunStreamerTest.php               # SSE generator polling
├── Feature/                              # Integration tests with RefreshDatabase
│   ├── AccountDeletionTest.php           # 8 tests: cascade delete, confirmation, logout
│   ├── ExecuteLauncherJobTest.php        # Job dispatch, provider resolution, encrypted payload
│   ├── MagicLinkAuthTest.php             # Request/verify/logout, token expiry, rate limit
│   ├── ProviderCredentialApiTest.php     # CRUD + verify + make-default
│   ├── ReapStuckRunsTest.php             # Scheduled command reaping stuck runs
│   ├── RunApiTest.php                    # POST /api/runs, GET /api/runs/{id}, SSE stream
│   ├── RunHistoryTest.php                # Authenticated user run list/retry/delete
│   ├── RunOwnershipTest.php              # User can only see their runs
│   └── SavedCredentialLaunchTest.php     # Launch with saved credential, IDOR protection
```

### Test Count

- **Unit:** 10 test files, ~50+ test methods
- **Feature:** 9 test files, ~60+ test methods
- **Total:** ~142 tests, 429 assertions (as of 2026-07-14)

### Patterns

**Base class:**
- `Tests\TestCase` — boots full Laravel app, provides `config()`, `Http::fake()`, etc.
- Pure unit tests that don't need Laravel extend `PHPUnit\Framework\TestCase` — but tests touching `config()` must extend `Tests\TestCase`

**Database:**
- `use RefreshDatabase;` in feature tests — migrates fresh schema each test
- `$this->seed()` in `setUp()` when launchers are needed
- SQLite `:memory:` (phpunit.xml) — fast, isolated

**Mocking:**
- `Mockery::mock(RunExecutorInterface::class)` — mock the executor in job tests
- `Http::fake([...])` — fake HTTP responses for GitHub/OpenAI/OpenRouter/Anthropic/Gemini
- `Queue::fake()` — assert job dispatch without executing
- `Mail::fake()` — assert magic-link email queued
- `vi.mocked($function).mockResolvedValue()` / `mockRejectedValue()` — Vitest mock control

**Factories:**
- `UserFactory` — creates `User` with faker email/name
- `ProviderCredential::forceCreate([...])` — bypasses mass-assignment guard for `user_id` in tests

**Assertions:**
- `assertDatabaseHas('runs', [...])` / `assertDatabaseMissing('users', [...])`
- `$response->assertOk()`, `assertStatus(202)`, `assertJsonPath('message', '...')`
- `Queue::assertPushed(ExecuteLauncherJob::class, fn($job) => ...)`
- `Http::assertSent(fn($request) => $request->hasHeader('HTTP-Referer', '...'))`

**Testing auth:**
- `$this->actingAs($user)` — authenticate as a user
- `$this->deleteJson('/api/user/account', ['confirm' => true])` — test account deletion

## Frontend Tests (`backend/resources/ts/`)

### Structure

```
components/__tests__/
├── AppViews.test.tsx                    # Auth states, view rendering
├── DashboardAccount.test.tsx            # 18 tests: account tab, deletion flow, logout
├── HomeSubComponents.test.tsx           # UrlInput, LauncherSelector, LaunchArea basics
├── LaunchAreaCredentials.test.tsx       # 15 tests: saved-credential picker, auto-provider
├── ProviderSettingsComponents.test.tsx  # CredentialForm, CredentialList, PrivacyNote
└── RunHistory.test.tsx                  # Run list, status filter, retry/delete
```

### Test Count

- **6 test files**, ~93 tests total (as of 2026-07-14)

### Patterns

**Imports:**
```tsx
import { afterEach, describe, expect, it, vi } from "vitest";
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
```

**Component rendering:**
- `render(<Component {...defaultProps} />)` — render with props
- `screen.getByRole("button", { name: "Launch workflow" })` — query by role + name
- `screen.getByPlaceholderText(/github.com/)` — query by placeholder
- `screen.getByLabelText("AI provider")` — query by aria-label
- `await screen.findByText("error message")` — async wait for text

**User interaction:**
- `await userEvent.setup().click(button)` — click
- `await userEvent.setup().type(input, "text")` — type
- `await userEvent.setup().selectOptions(select, "value")` — select dropdown

**Mocking:**
- `vi.fn()` — mock callbacks
- `vi.mock("../../services/auth.ts", async (importActual) => { ... })` — mock module
- `vi.mocked(deleteAccount).mockResolvedValue(undefined)` — control mock behavior
- `vi.spyOn(console, "error").mockImplementation(() => {})` — suppress expected errors
- `new Promise(() => {})` — never-resolving promise for pending state testing

**Conventions:**
- `afterEach(() => { vi.clearAllMocks(); })` — reset between tests
- `vi.mocked(fn).mockReset()` in `afterEach` for modules that need full reset
- Tests use `try/finally` for spy cleanup to ensure restoration on failure
- No snapshot tests — all assertions are explicit

## E2E Tests (`backend/tests/E2E/`)

### Structure

```
tests/E2E/
└── flows/
    ├── demo-full-flow.spec.ts    # Demo mode: sign-in UI, URL paste → report, validation
    └── real-api-flow.real.spec.ts # Real backend: full flow with actual API (gated)
```

### Patterns

- **Demo mode:** `VITE_DEMO_MODE=true` build + `php artisan serve` started by `scripts/e2e/serve-demo.sh`
- Playwright projects: `demo` (runs on CI) and `real-backend` (requires API key, not in CI)
- Tests: navigate → paste URL → click launch → wait for running view → wait for report → verify findings text
- **Bug fix:** `App.tsx` excludes `"report"` from view reset condition to allow demo reports to render (fix `680e36a`)

## CI Test Pipeline

**Backend job:** `composer validate` → `composer install` → `migrate` → `php artisan test` → `pint --test`
**Frontend job:** `npm ci` → `typecheck` → `lint` → `konsistent` → `build` → `test` (no-op placeholder)
**E2E job:** `composer install` + `npm ci` + Playwright Chromium → `npm run test:e2e:demo`

## Running Tests Locally

```bash
cd backend

# Backend
php artisan test                              # full suite
php artisan test --filter=OpenRouterProviderTest  # specific test

# Frontend
npx vitest run                               # all vitest tests
npx vitest run resources/ts/components/__tests__/LaunchAreaCredentials.test.tsx  # specific

# E2E (requires server running)
npm run test:e2e:demo                        # demo mode E2E

# Style
./vendor/bin/pint --test                     # PHP style check
npm run lint                                 # TS lint + format check
```
