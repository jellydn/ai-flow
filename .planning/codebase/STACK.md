# Technology Stack

## Languages & Runtimes

| Component | Version | Notes |
|---|---|---|
| PHP | `^8.4` | Required by Laravel 13; strict typing encouraged |
| TypeScript | 5.9.x | Strict mode (`tsconfig.json` `strict: true`, `noEmit: true`) |
| Node.js | 24+ | Required by CI; Vite 8 build pipeline |
| CSS | Vanilla (no Tailwind) | Hand-rolled styles in `backend/resources/css/app.css` |

## Backend Framework

**Laravel 13** (`laravel/framework: ^13.0`) — single-app deploy root at `backend/`.

- **Queue**: Database driver (`QUEUE_CONNECTION=database`); never `sync` in production (enforced by `AppServiceProvider::boot()` guard)
- **Cache**: File (dev), Redis/Memcached (production)
- **Session**: File (dev), Database/Redis (production); session-based auth via `web` guard
- **Mail**: Resend (`resend/resend-php: ^1.5`) — used for magic links + super-admin bootstrap
- **Error monitoring**: Sentry (`sentry/sentry-laravel: ^4.26`) — no-op when DSN unset
- **Admin panel**: Filament v5 (`filament/filament: ^5.0`) at `/admin`, gated by `is_super_admin`
- **Local DB**: SQLite (`database/database.sqlite`); production must use Postgres/MySQL (guard in `AppServiceProvider`)

### Production Dependencies (`composer.json`)

| Package | Purpose |
|---|---|
| `laravel/framework` | HTTP, queue, cache, validation, encryption |
| `filament/filament` | Super admin panel at `/admin` |
| `laravel/tinker` | Interactive REPL |
| `resend/resend-php` | Email delivery (magic links, admin bootstrap) |
| `sentry/sentry-laravel` | Error tracking (no-op when DSN unset) |

### Development Dependencies (`composer.json`)

| Package | Purpose |
|---|---|
| `phpunit/phpunit` | Test framework (`^13.0`) |
| `laravel/pint` | PSR-12 code formatter (`^1.24`) |
| `nunomaduro/collision` | Pretty test error output (`^8.8`) |
| `laravel/pail` | Log tailing (`^1.2.2`) |
| `laravel/sail` | Docker dev environment (`^1.41`) |
| `mockery/mockery` | Mocking framework (`^1.6`) |
| `fakerphp/faker` | Test data generation (`^1.23`) |

### Composer Scripts

- `setup`: install deps, copy `.env`, key:generate, migrate, seed, build frontend
- `dev`: concurrently runs `serve`, `queue:listen`, `pail`, `vite`
- `test`: `php artisan test` (with config clear)
- `post-autoload-dump`: `filament:upgrade` + `package:discover`

## Frontend Framework

**React 19 + TypeScript + Vite 8** under `backend/resources/ts/`.

- **Build**: Vite 8.1.5 with `@vitejs/plugin-react` + `laravel-vite-plugin`
- **SPA shell**: `backend/resources/views/app.blade.php` + catch-all `web.php` route (excludes `/api`, `/admin`)
- **Dev port**: 5173 (HMR); allowed hosts include `.localhost`, `.onamp.dev`, `.amp.dev`
- **Assets**: served from `public/build/` (gitignored)

### Production Dependencies (`package.json`)

| Package | Version | Purpose |
|---|---|---|
| `react` / `react-dom` | 19.2.7 (pinned) | UI framework |
| `lucide-react` | 1.24.0 (pinned) | Icon library |
| `react-markdown` | `^10.1.0` | Report markdown rendering |
| `remark-gfm` | `^4.0.1` | GitHub Flavored Markdown |
| `@sentry/react` | `^10.65.0` | Frontend error tracking |
| `consola` | `^3.4.2` | Logging utility |

### Development Dependencies

| Package | Purpose |
|---|---|
| `typescript` | Type checking (strict) |
| `vite` | Build tool |
| `vitest` | Unit test runner (jsdom env) |
| `@testing-library/react` | Component testing |
| `@playwright/test` | E2E testing |
| `oxlint` / `oxfmt` | Linting + formatting (no Prettier) |
| `konsistent` | Structural TS conventions |
| `jsdom` | DOM environment for tests |

### npm Scripts

| Script | Command | Purpose |
|---|---|---|
| `build` | `tsc --noEmit && vite build` | Production build → `public/build` |
| `dev` | `vite` | Dev server with HMR |
| `typecheck` | `tsc --noEmit` | Type-only check |
| `lint` / `lint:ox` | `oxlint` / `oxlint --fix` | Lint (does NOT typecheck) |
| `format` / `format:check` | `oxfmt --write` / `--check` | Format |
| `konsistent` | `konsistent` | Structural TS conventions (`konsistent.json`) |
| `test` / `test:watch` | `vitest` / `vitest --watch` | Unit tests |
| `test:e2e` / `test:e2e:real` | Playwright (demo / real backend) | E2E |
| `doctor` | `npx react-doctor` | React health check |

## AI Providers

All implement `AIProviderInterface` via `BaseAIProvider` (HTTP lifecycle in base; subclass declares shape via hooks — ADR-0017, ADR-0022).

| Provider | Class | Default Model | Auth |
|---|---|---|---|
| OpenAI | `OpenAIProvider` | `gpt-4o-mini` (override `OPENAI_MODEL`/`AI_MODEL`) | Bearer header; `json_schema` response format |
| OpenRouter | `OpenRouterProvider` | `openrouter/free` (guest) | Bearer header + `HTTP-Referer`/`X-Title` |
| Anthropic | `AnthropicProvider` | `claude-sonnet-4-20250514` | `x-api-key` header + `anthropic-version` |
| Gemini | `GeminiProvider` | `gemini-2.0-flash` | `?key=` query param baked into endpoint URL |

- **Model resolution**: `AI_MODEL` overrides `OPENAI_MODEL` globally; per-adapter defaults in `config/services.php`.
- **Timeout**: shared `services.ai.timeout` (default 30s), with `OPENAI_TIMEOUT` backward-compat fallback.
- **Retry**: `BaseAIProvider::RETRY_ATTEMPTS = 2`, `RETRY_DELAY_MS = 500` (transient failures).
- **Verify timeout**: 10s (lightweight `/models?limit=1` GET).
- **Registry**: `AiProviderRegistry` — singleton bound in `AppServiceProvider::register()`; provider IDs from `AiProviderRegistry::ids()`, not a config array.

## External Services

| Service | Purpose | Required | Env |
|---|---|---|---|
| GitHub REST API | Repository context fetching (cached 10 min) | Optional (rate-limited without token) | `GITHUB_TOKEN` |
| OpenRouter | Guest run AI provider | Required for guests | `OPENROUTER_API_KEY` |
| OpenAI / Anthropic / Gemini | Signed-in AI providers | Per-user (BYOK or saved credential) | `OPENAI_API_KEY` / `ANTHROPIC_API_KEY` / `GEMINI_API_KEY` |
| Resend | Email delivery (magic links, admin bootstrap) | Required for auth | `RESEND_API_KEY` |
| Sentry | Error monitoring | Optional | `SENTRY_LARAVEL_DSN`, `VITE_SENTRY_DSN` |
| Filament | Admin panel | Built-in | — |

## Configuration Files

| File | Purpose |
|---|---|
| `backend/config/app.php` | App name, env, timezone, `frontend_url` for magic-link redirects |
| `backend/config/services.php` | AI model defaults, provider configs (openai/openrouter/anthropic/gemini/github/mail) |
| `backend/config/database.php` | SQLite (dev), PostgreSQL/MySQL (prod); `DB_SSLMODE=require` for Neon |
| `backend/config/auth.php` | Session-based auth (`web` guard, `users` provider) |
| `backend/config/queue.php` | Database queue driver |
| `backend/config/credentials.php` | `CREDENTIAL_ENCRYPTION_KEY` for BYOK credentials (falls back to `APP_KEY`) |
| `backend/config/super_admin.php` | Bootstrap email (`dung@productsway.com`), name, panel route |
| `backend/config/cors.php` | CORS for SPA |
| `backend/config/session.php` | Session config |
| `backend/config/cache.php` | Cache stores |
| `backend/config/logging.php` | Log channels |
| `backend/config/filesystems.php` | Storage disks |
| `backend/config/mail.php` | Mail config (Resend) |
| `backend/config/sentry.php` | Sentry DSN and config |

## Environment Variables (`.env.example`)

### Required
- `OPENROUTER_API_KEY` — guest workflow runs

### Optional (AI)
- `OPENAI_API_KEY`, `OPENAI_MODEL`, `AI_MODEL`, `AI_BASE_URL`, `AI_SITE_URL`, `OPENAI_TIMEOUT`
- `ANTHROPIC_API_KEY`, `ANTHROPIC_MODEL`
- `GEMINI_API_KEY`, `GEMINI_MODEL`
- `GITHUB_TOKEN` (rate-limit relief)

### Optional (Mail / Auth)
- `MAIL_MAILER`, `MAIL_FROM_ADDRESS`, `RESEND_API_KEY`
- `FRONTEND_URL` (magic-link redirect target)
- `SUPER_ADMIN_BOOTSTRAP_EMAIL` / `SUPER_ADMIN_BOOTSTRAP_NAME`

### Optional (Security / Monitoring)
- `CREDENTIAL_ENCRYPTION_KEY` — dedicated key for stored AI credentials (falls back to `APP_KEY`)
- `SENTRY_LARAVEL_DSN`, `VITE_SENTRY_DSN`

### Database
- Default: `DB_CONNECTION=sqlite` (local/CI)
- Production: PostgreSQL (`DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_SSLMODE=require`)

## Deployment

| Target | Config |
|---|---|
| **Dokku (staging — what CI actually ships)** | `backend/Dockerfile` (nginx + PHP-FPM), `DB_URL`, `dokku` git remote → `docklight-staging.itman.fyi:ai-flow`, URL `https://ai-flow-staging.itman.fyi`. Release phase migrates + seeds. Disable nginx `proxy-buffering`; set `proxy-read-timeout 75s` for SSE. |
| **Laravel Cloud (alternative)** | `backend/` as app root, `npm ci && npm run build`, stable shared `APP_KEY`, durable Neon Postgres (`DB_SSLMODE=require`), `QUEUE_CONNECTION=database`. See `backend/CLOUD_DEPLOY.md`. |
| **Worker (both)** | `php artisan queue:work --sleep=1 --tries=2 --timeout=120` |

## Tooling & Conventions Enforcement

| Tool | Config | Enforces |
|---|---|---|
| Pint | (Laravel default) | PSR-12 PHP formatting |
| oxlint | `.oxlintrc.json` (typescript, unicorn, oxc plugins; `correctness: error`; `no-console`) | TS/TSX lint |
| oxfmt | `.oxfmtrc.json` (ignores `node_modules`, `public`, `vendor`) | TS/TSX format (no Prettier) |
| konsistent | `konsistent.json` | PascalCase components matching filename; `use*` hooks; `ErrorBoundary` class exception |
| pre-commit / prek | `.pre-commit-config.yaml` | Trailing ws, EOF, YAML check, large files; pint, typecheck, oxlint, oxfmt, konsistent |
| Renovate | `renovate.json` | Dependency updates |
| just | `justfile` | `just ci`, `just test`, `just dev`, etc. |

## CI

`.github/workflows/ci.yml`:
- **Backend** on PHP 8.4 (`sqlite3`, `pgsql` ext): `composer validate`, `php artisan test`, `pint --test`
- **Frontend** on Node 24: `typecheck`, `lint`, `konsistent`, `build`, `test` (currently a no-op)
- `.github/workflows/deploy-staging.yml`: Dokku staging deploy
- `.github/workflows/react-doctor.yml`: React Doctor check
