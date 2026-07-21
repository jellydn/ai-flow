# Stack

> Technologies, dependencies, and runtime configuration for **ai-flow** — a single Laravel 13 app that serves a React 19 SPA and a queue-backed API turning GitHub URLs into structured AI workflow reports.

## Runtime

| Layer | Technology | Version |
|-------|-----------|---------|
| Backend language | PHP | ^8.4 (dev: 8.5.8) |
| Backend framework | Laravel | ^13.0 |
| Frontend language | TypeScript | 5.9.3 (strict) |
| Frontend library | React | 19.2.7 |
| Bundler | Vite | 8.1.5 (`@vitejs/plugin-react` + `laravel-vite-plugin`) |
| Admin panel | Filament | ^5.0 |
| Node | Node.js | 24 (CI) / v24.18.0 (dev) |

## Backend dependencies (`backend/composer.json`)

### Production (`require`)
- `laravel/framework` ^13.0 — core framework (routing, Eloquent, queue, cache, mail)
- `filament/filament` ^5.0 — super-admin panel (`App\Filament\Resources\`)
- `laravel/tinker` ^3.0 — REPL
- `resend/resend-php` ^1.5 — transactional email (magic links)
- `sentry/sentry-laravel` ^4.26 — error monitoring (config at `backend/config/sentry.php`)

### Development (`require-dev`)
- `phpunit/phpunit` ^13.0 — test framework
- `mockery/mockery` ^1.6 — test doubles
- `fakerphp/faker` ^1.23 — test data generation
- `laravel/pint` ^1.24 — PHP formatter (PSR-12; CI runs `--test`)
- `laravel/pail` ^1.2.2 — log tailing (`composer run dev`)
- `laravel/sail` ^1.41 — Docker dev environment (optional)
- `nunomaduro/collision` ^8.8 — pretty error output

## Frontend dependencies (`backend/package.json`)

### Production
- `react` / `react-dom` 19.2.7 — UI library
- `lucide-react` — icon set (pinned version per AGENTS.md)
- `react-markdown` + `remark-gfm` — render AI-generated Markdown reports
- `@sentry/react` v10 — frontend error monitoring

### Development
- `vite` 8.1.5 + `@vitejs/plugin-react` + `laravel-vite-plugin` — build/dev server
- `typescript` 5.9.3 — type checking (`tsc --noEmit`)
- `vitest` 4 — frontend unit tests
- `@testing-library/react` + `@testing-library/jest-dom` — component testing
- `playwright` 1.61 — E2E suite (`--project=real-backend`)
- `oxlint` — linter (config at repo-root `.oxlintrc.json`)
- `oxfmt` — formatter (config at repo-root `.oxfmtrc.json`)
- `konsistent` — structural TS conventions (config at repo-root `konsistent.json`)

## TypeScript config (`backend/tsconfig.json`)

- **Target:** ES2022 | **Module:** ESNext | **Module resolution:** Bundler
- **JSX:** `react-jsx` (automatic runtime for React 19)
- **Strictness:** `strict: true`, `skipLibCheck: true`
- **Include:** `resources/ts/`, `vite.config.ts`, `vitest.config.ts`, `tests/E2E/`, `playwright.config.ts`

## Configuration files (`backend/config/`)

| File | Purpose |
|------|---------|
| `app.php` | Name, env, URL, key, rate-limit defaults (`runs_rate_limit_per_hour`, etc.) |
| `auth.php` | Guard (`web`), password broker, model binding |
| `cache.php` | Default store `database`; supports Redis/Memcached/DynamoDB |
| `cors.php` | Allowed origins via `CORS_ALLOWED_ORIGINS` (default localhost:5173) |
| `credentials.php` | `CREDENTIAL_ENCRYPTION_KEY` (dedicated BYOK key, falls back to `APP_KEY`) |
| `database.php` | SQLite (local), MySQL, Postgres (`DB_SSLMODE=require` in prod) |
| `filesystems.php` | Local default; S3 supported |
| `github-bot.php` | Webhook secret, App ID, private key, poll settings, rate limit |
| `logging.php` | Stack default; Slack/Papertrail/stdout options |
| `mail.php` | Default `log`; Resend supported |
| `queue.php` | Default `database` (never `sync` in prod); Redis/SQS supported |
| `sentry.php` | DSN, sample rates, breadcrumbs, trace config |
| `services.php` | GitHub token, OpenAI/OpenRouter/Anthropic/Gemini keys + models |
| `session.php` | Default `database` driver |
| `super_admin.php` | Bootstrap email/name for first super-admin |

## Environment variables (key — `backend/.env.example`)

- **Required:** `APP_KEY`, `OPENAI_API_KEY`
- **Recommended:** `GITHUB_TOKEN` (GitHub API rate limits)
- **AI providers:** `OPENAI_API_KEY`, `OPENROUTER_API_KEY`, `ANTHROPIC_API_KEY`, `GEMINI_API_KEY` (optional; model overrides via `AI_MODEL`, `OPENAI_MODEL`, `ANTHROPIC_MODEL`, `GEMINI_MODEL`)
- **GitHub bot:** `GITHUB_APP_ID`, `GITHUB_APP_PRIVATE_KEY`, `GITHUB_WEBHOOK_SECRET`, `GITHUB_BOT_COMMENT_LABEL`
- **Security:** `CREDENTIAL_ENCRYPTION_KEY` (dedicated BYOK credential encryption; falls back to `APP_KEY`)
- **Email:** `RESEND_API_KEY`, `MAIL_FROM_ADDRESS`
- **Monitoring:** `SENTRY_LARAVEL_DSN` / `SENTRY_DSN`

## Tooling commands (run inside `backend/`)

```bash
composer run dev          # serve + queue:listen + pail + vite (concurrent)
php artisan serve
php artisan queue:work --tries=2 --timeout=120   # standalone worker
php artisan test
./vendor/bin/pint --test && ./vendor/bin/pint     # CI fails on --test violations
npm run typecheck     # tsc --noEmit (strict)
npm run lint          # oxlint + oxfmt --check
npm run format        # oxfmt --write
npm run build         # tsc --noEmit && vite build -> public/build
npm run konsistent    # structural TS conventions
npm run test          # vitest run
npm run test:e2e      # Playwright e2e suite
```

`just ci` runs the full backend+frontend gate locally. Pre-commit hooks via prek (`.pre-commit-config.yaml`).
