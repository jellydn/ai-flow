# Coding Conventions

**Analysis Date:** 2026-07-13

## PHP / Laravel

### Formatting
- **Laravel Pint** (`laravel/pint` ^1.24) enforces PSR-12 + Laravel preset. No `pint.json` exists.
- CI: `./vendor/bin/pint --test` (fails on violations). Local: `./vendor/bin/pint` (auto-fix).
- Pre-commit hook via `scripts/hooks/pint.sh`.

### Naming
- Methods: `camelCase` (e.g., `outputSchema()`, `prepareForValidation()`).
- Constants: `UPPER_CASE` (e.g., `AiProviders::OPENAI`).
- DB columns: `snake_case` (e.g., `source_url`, `started_at`).

### Types
- Explicit property types (`public int $tries`), parameter types, and return types (`array`, `string`, `void`).
- PHPDoc generics where needed (`/** @return list<string> */`).

### Patterns
- **Form requests** for HTTP validation (`StoreRunRequest`).
- **API resources** for JSON shape (`RunResource`).
- **Contracts + container binding** for swappable services.
- **Jobs** for slow/IO work (`ExecuteLauncherJob`); controllers return **202**.
- **Thin routes** in `routes/api.php`; logic in controllers, services, jobs, launchers.
- **Launchers:** one class per workflow, metadata via `BaseLauncher::make()`, seeded in `DatabaseSeeder`.

### Error Handling
- Domain failures: `RuntimeException` (safe messages) or `InvalidArgumentException`.
- Executor catches all `Throwable`; only `RuntimeException` messages shown to users.
- No nested ternaries — prefer `match`, early returns, or `if/else`.

### Secrets
- BYOK API keys encrypted via `ShouldBeEncrypted` on `ExecuteLauncherJob`.
- Never logged; tests assert absence from queue payload and logs.

## TypeScript / React

### Formatting
- **oxlint** (linting) + **oxfmt** (formatting) — Rust-based, no ESLint/Prettier.
- Config: `.oxlintrc.json` (plugins: `typescript`, `unicorn`, `oxc`) + `.oxfmtrc.json`.
- Commands: `npm run lint` (check), `npm run format` (fix).
- **konsistent** enforces structural conventions.

### Conventions
- Functional components + hooks; TypeScript **strict mode**.
- Avoid broad `any`; prefer `unknown` with explicit narrowing.
- `components/*.tsx` exports PascalCase component matching filename.
- `hooks/*.ts` exports `use*` function matching filename.
- `ErrorBoundary.tsx` exempt; exports class `ErrorBoundary`.
- Relative imports within `backend/resources/ts/`.

### API Client
- Same-origin `/api/*` requests.
- Typed contracts in `types/api.ts`.
- Runtime validation with `decodeRun` (strict).

## Production Guards

- SQLite rejected in production web requests.
- PostgreSQL requires TLS for external hosts (hostname has dots).
- `QUEUE_CONNECTION=sync` rejected in production.
- `LOG_LEVEL=debug` warns in production.

## Testing

- **PHPUnit** 13 with `RefreshDatabase` + seed.
- `Queue::fake()` when asserting dispatch.
- Mock GitHub/AI in job tests.
- Feature tests for endpoints (validation, queueing, rate limiting).
- Unit tests for services (streamer, encoder, fetcher, assembler, provider).

---

*Convention analysis: 2026-07-13*
