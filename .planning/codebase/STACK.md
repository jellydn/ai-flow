# Technology Stack

## Languages & Runtime

| Layer | Language | Runtime |
|-------|----------|---------|
| API/backend | PHP 8.4 | PHP-FPM (production), `php artisan serve` (dev) |
| Frontend | TypeScript 5.9 | Node 24, Vite 8 |
| Queue worker | PHP 8.4 | `php artisan queue:work` |
| CI | Node 24 (frontend), PHP 8.4 (backend) | GitHub Actions |

## Core Framework

| Package | Version | Purpose |
|---------|---------|---------|
| `laravel/framework` | ^13.0 | Full-stack PHP framework |
| `filament/filament` | ^5.0 | Admin panel (super-admin only, `app/Filament`) |
| `laravel/tinker` | ^3.0 | REPL for artisan |

## Frontend

| Package | Version | Purpose |
|---------|---------|---------|
| `react` / `react-dom` | 19.2.7 | UI library |
| `vite` | 8.1.5 | Build tool & dev server |
| `@vitejs/plugin-react` | 6.0.3 | React Fast Refresh for Vite |
| `laravel-vite-plugin` | 3.1.3 | Laravel-Vite integration |
| `lucide-react` | 1.25.0 | Icon library |
| `react-markdown` | ^10.1.0 | Markdown rendering |
| `remark-gfm` | ^4.0.1 | GitHub-flavored Markdown |
| `@sentry/react` | ^10.65.0 | Error monitoring |
| `consola` | ^3.4.2 | Logging utility |

## AI Providers

| Provider | Config Key | Model Default |
|----------|-----------|---------------|
| OpenAI | `OPENAI_API_KEY` | `gpt-4o-mini` (overridable via `AI_MODEL`) |
| OpenRouter | `OPENROUTER_API_KEY` | `openrouter/free` |
| Anthropic | `ANTHROPIC_API_KEY` | `claude-sonnet-4-20250514` |
| Gemini | `GEMINI_API_KEY` | `gemini-2.0-flash` |

## External Services

| Service | Purpose | Config |
|---------|---------|--------|
| GitHub REST API | Repository context fetching | `GITHUB_TOKEN` |
| Resend | Transactional email (magic links) | `RESEND_API_KEY` |
| Sentry | Error tracking | `SENTRY_LARAVEL_DSN` |
| Dokku | Staging deployment | `dokku` git remote |
| Laravel Cloud | Alternative production deploy | `CLOUD_DEPLOY.md` |

## Database

| Environment | Connection | Driver |
|-------------|------------|--------|
| Local/CI | SQLite | `database/database.sqlite` |
| Production | PostgreSQL (Neon) | `pgsql` with `sslmode=require` |
| Alternative | MySQL/MariaDB | Configured but not primary |

## Queue

| Connection | Notes |
|------------|-------|
| `database` (default) | Required for production; `sync` forbidden |
| Worker flags | `--sleep=1 --tries=2 --timeout=120` |

## Dev Dependencies

| Package | Purpose |
|---------|---------|
| `phpunit/phpunit` ^13.0 | PHP testing |
| `laravel/pint` ^1.24 | PHP code style (PSR-12) |
| `vitest` ^4.1.10 | Frontend unit tests |
| `@playwright/test` ^1.61.1 | E2E tests |
| `@testing-library/react` ^16.3.2 | Component testing |
| `oxlint` ^1.73.0 | TypeScript linting |
| `oxfmt` ^0.59.0 | TypeScript formatting |
| `konsistent` ^1.0.0-beta.3 | Structural conventions enforcement |
| `typescript` 5.9.3 | Type checking |
| `jsdom` ^29.1.1 | DOM environment for vitest |
| `mockery/mockery` ^1.6 | PHP mocking |
| `laravel/pail` ^1.2.2 | Log tailing |
| `laravel/sail` ^1.41 | Docker dev environment |

## Deployment

| Target | Method | URL |
|--------|--------|-----|
| Staging (Dokku) | `git push dokku` | `https://ai-flow-staging.itman.fyi` |
| Production (Laravel Cloud) | Cloud deploy | TBD |

### Docker (Dokku)
- **Base image**: `php:8.5-fpm-bookworm` (Node 24 for build stage)
- **Web server**: nginx + PHP-FPM
- **Process manager**: Supervisor (nginx + queue worker)
- **Health check**: Laravel `/up` endpoint (port 80)
- **SSE config**: `proxy-buffering: off`, `proxy-read-timeout: 75s`
