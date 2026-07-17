# Technology Stack

## Languages & Runtimes

| Component | Version |
|---|---|
| PHP | ^8.4 |
| TypeScript | 5.9.3 |
| Node.js | 24+ |
| CSS | Vanilla (no Tailwind) |

## Backend Framework

**Laravel 13** (`laravel/framework: ^13.0`)

- Queue: Database driver (never `sync` in production)
- Cache: File (dev), Redis/Memcached (production)
- Session: File (dev), Database/Redis (production)
- Mail: Resend (`resend/resend-php: ^1.5`)
- Error monitoring: Sentry (`sentry/sentry-laravel: ^4.26`)
- Admin panel: Filament (`filament/filament: ^5.0`)

### Production Dependencies

| Package | Purpose |
|---|---|
| `laravel/framework` | HTTP, queue, cache, validation, encryption |
| `filament/filament` | Super admin panel at `/admin` |
| `laravel/tinker` | Interactive REPL |
| `resend/resend-php` | Email delivery (magic links, admin bootstrap) |
| `sentry/sentry-laravel` | Error tracking (no-op when DSN unset) |

### Development Dependencies

| Package | Purpose |
|---|---|
| `phpunit/phpunit` | Test framework |
| `laravel/pint` | PSR-12 code formatter |
| `nunomaduro/collision` | Pretty test error output |
| `laravel/pail` | Log tailing |
| `mockery/mockery` | Mocking framework |
| `fakerphp/faker` | Test data generation |

## Frontend Framework

**React 19 + TypeScript + Vite 8** (`backend/resources/ts/`)

- Build: Vite 8.1.5 with `@vitejs/plugin-react` and `laravel-vite-plugin`
- SPA shell: `resources/views/app.blade.php` with catch-all route
- Assets served from `public/build/` (gitignored)

### Production Dependencies

| Package | Purpose |
|---|---|
| `react` / `react-dom` | UI framework (19.2.7, pinned) |
| `lucide-react` | Icon library (1.24.0, pinned) |
| `react-markdown` | Report markdown rendering |
| `remark-gfm` | GitHub Flavored Markdown support |
| `@sentry/react` | Frontend error tracking |
| `consola` | Logging utility |

### Development Dependencies

| Package | Purpose |
|---|---|
| `typescript` | Type checking (strict mode) |
| `vite` | Build tool |
| `vitest` | Unit test runner |
| `@testing-library/react` | Component testing |
| `@playwright/test` | E2E testing |
| `oxlint` / `oxfmt` | Linting + formatting (no Prettier) |
| `konsistent` | Structural TS conventions |
| `jsdom` | DOM environment for tests |

## AI Providers

All implement `AIProviderInterface` via `BaseAIProvider`:

- **OpenAI** (`OpenAIProvider`) — default, uses `gpt-4o-mini` (override via `OPENAI_MODEL`/`AI_MODEL`)
- **OpenRouter** (`OpenRouterProvider`) — free tier for guest runs (`openrouter/free`)
- **Anthropic** (`AnthropicProvider`) — `claude-sonnet-4-20250514`
- **Gemini** (`GeminiProvider`) — `gemini-2.0-flash`

Model resolution: `AI_MODEL` overrides `OPENAI_MODEL` globally. Per-adapter defaults in `config/services.php`.

## External Services

| Service | Purpose | Required |
|---|---|---|
| GitHub REST API | Repository context fetching | Optional (rate-limited without token) |
| OpenRouter | Guest run AI provider | Required for guests |
| OpenAI / Anthropic / Gemini | Signed-in AI providers | Per-user (BYOK or saved credential) |
| Resend | Email delivery | Required for magic links |
| Sentry | Error monitoring | Optional |
| Filament | Admin panel | Built-in |

## Configuration Files

| File | Purpose |
|---|---|
| `config/app.php` | App name, env, timezone |
| `config/services.php` | AI model defaults, provider configs |
| `config/database.php` | SQLite (dev), PostgreSQL/MySQL (prod) |
| `config/auth.php` | Session-based auth (password + magic link) |
| `config/queue.php` | Database queue driver |
| `config/cors.php` | CORS for SPA |
| `config/super_admin.php` | Bootstrap email, panel route |
| `config/session.php` | Session config |
| `config/cache.php` | Cache stores |
| `config/logging.php` | Log channels |
| `config/filesystems.php` | Storage disks |
| `config/mail.php` | Mail config (Resend) |
| `config/sentry.php` | Sentry DSN and config |

## Deployment

| Target | Config |
|---|---|
| **Dokku (staging)** | Dockerfile with nginx + PHP-FPM, `DB_URL`, `dokku` git remote → `docklight-staging.itman.fyi:ai-flow` |
| **Laravel Cloud** | `backend/` as app root, Neon Postgres, `QUEUE_CONNECTION=database` |
| **Worker** | `php artisan queue:work --sleep=1 --tries=2 --timeout=120` |
