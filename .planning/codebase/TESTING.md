# Testing

> Test framework, structure, mocking patterns, and coverage for ai-flow.

## Frameworks

| Layer | Framework | Config |
|-------|-----------|--------|
| Backend unit/feature | PHPUnit ^13 | `backend/phpunit.xml` |
| Frontend unit | Vitest 4 | `backend/vitest.config.ts` |
| E2E | Playwright 1.61 | `backend/playwright.config.ts` (`--project=real-backend`) |

## Backend tests (`backend/tests/`)

### PHPUnit config (`phpunit.xml`)
- **Env:** `APP_ENV=testing`, `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`
- **Mail/Queue/Cache:** `MAIL_MAILER=array`, `QUEUE_CONNECTION=sync`, `CACHE_STORE=array`
- **Mock keys:** `OPENAI_API_KEY=phpunit-test-openai-key`, `OPENROUTER_API_KEY=phpunit-test-openrouter-key`
- **Disabled:** `PULSE`, `TELESCOPE`, `NIGHTWATCH`

### Structure
**Base class:** `tests/TestCase.php` — shared base (not a test file itself).

**Unit tests** (`tests/Unit/` — 16 files): Test services and helpers in isolation.
- `AiProviderRegistryTest`, `AnthropicProviderTest`, `BaseAIProviderJsonExtractionTest`, `CacheRunProgressedVersionTest`, `ContextEncoderTest`, `CredentialCipherTest`, `GeminiProviderTest`, `GitHubServiceFetchTest`, `GitHubServiceTest`, `GitHubTrendingServiceParseTest`, `JsonSchemaValidatorTest`, `LaunchParametersTest`, `OpenAIProviderTest`, `OpenRouterProviderTest`, `RunStatusSyncTest`, `RunStreamerTest`

**Feature tests** (`tests/Feature/` — 21 files): Test full HTTP + queue flow.
- `RunApiTest`, `RunHistoryTest`, `RunOwnershipTest`, `RunPromptSnapshotTest`, `RunRequiresProviderKeyTest`, `ExecuteLauncherJobTest`, `SavedCredentialLaunchTest`, `ProviderCredentialApiTest`, `ProviderCredentialBaseUrlValidationTest`, `UserLauncherTest`, `UserLauncherCascadeDeleteTest`, `LauncherPromptApiTest`, `MagicLinkAuthTest`, `PasswordAuthTest`, `AccountDeletionTest`, `GitHubBotTest`, `SessionRunCsrfTest`, `TrendingRepositoriesApiTest`, `ReapStuckRunsTest`, `FilamentPanelAccessTest`, `SuperAdminBootstrapSeederTest`

### Patterns
- **`RefreshDatabase` trait** + `$this->seed()` — fresh DB per test, seeders populate built-in launchers.
- **`Queue::fake()`** — assert jobs dispatched without executing them (used in run/bot dispatch tests).
- **`Http::fake()`** — mock GitHub API responses (404 for missing config, canned PR/issue data).
- **`Mockery`** — partial mocks for `GitHubBotService` (e.g., `postComment`/`updateComment` expectations in two-phase job tests).
- **Reflection** — `callPrivate()` helper for testing private methods (`appJwt`, `appInstallationToken`, `deadlineExceeded`).
- **Form request tests** — validate authorization + rules separately from controller.
- **`StoreRunRequest`** validation covers `hasUsableKey()`, `isGuestViolationFor()`, `isModelAllowed()`.

### Commands
```bash
php artisan test                          # full suite
php artisan test --filter=SomeTest        # focused
php artisan test --filter=GitHubBotTest   # bot-specific
```

## Frontend tests (`backend/resources/ts/`)

### Vitest (10 files)
- `components/__tests__/`: `AppViews`, `DashboardAccount`, `HomeSubComponents`, `LaunchAreaCredentials`, `ProviderSettingsComponents`, `Report`, `RunHistory`
- `lib/__tests__/`: `jsonSchema`, `runModels`, `runStatusSync`
- Setup: `test/setup.ts`
- React Testing Library + `@testing-library/jest-dom`

```bash
npm run test     # vitest run
```

## E2E tests (`backend/tests/E2E/flows/`)

4 Playwright spec files (real-backend project):
- `all-launchers.real.spec.ts`
- `auth-user-flow.real.spec.ts`
- `launcher-prompts.real.spec.ts`
- `real-api-flow.real.spec.ts`

```bash
npm run test:e2e     # Playwright suite
./scripts/e2e/serve-real.sh   # helper to serve real backend for E2E
```

## CI

`.github/workflows/ci.yml`:
- **Backend (PHP 8.4, `sqlite3`+`pgsql` ext):** `composer validate`, `php artisan test`, `pint --test`
- **Frontend (Node 24):** `typecheck`, `lint`, `konsistent`, `build`, `test` (vitest run), `npm audit --production`

## Pre-commit hooks (`.pre-commit-config.yaml`)

Run via `just prek`:
- `trailing-whitespace`, `end-of-file-fixer`, `check-yaml`, `check-added-large-files`
- `composer-validate` (`scripts/hooks/composer-validate.sh`)
- `pint` (`scripts/hooks/pint.sh`)
- `frontend-typecheck` (`scripts/hooks/npm-in-backend.sh`)
- `env-check` (`scripts/hooks/env.sh`)

## Resolved: GitHubBotTest env credential leakage

`GitHubBotTest::setUp()` now clears `github-bot.app_id` and `github-bot.app_private_key` via `config(['github-bot.app_id' => null, 'github-bot.app_private_key' => null])`, so tests no longer pick up real GitHub App credentials from the shell environment or local `.env`. Without this, the installation-token flow bypassed the tests' generic `Http::fake()` 404 stubs and 10 tests failed for developers with real `GITHUB_APP_ID` set. The `phpunit.xml` `<env>` override approach was tried first but doesn't reliably shadow shell env vars in Laravel's config loader; the `config()` override in `setUp()` bypasses `env()` entirely. Individual tests needing credentials (e.g. `test_installation_token_*`) set them explicitly after `setUp()`. See `.planning/codebase/CONCERNS.md` for details. ✅ Fixed.
