# Concerns

> Technical debt, known issues, security, performance, and fragile areas in ai-flow.

## 🔴 Security

### GitHubBotTest env credential leakage
- **Where:** `backend/tests/Feature/GitHubBotTest.php`
- **Issue:** 10 tests fail when `GITHUB_APP_ID`/`GITHUB_APP_PRIVATE_KEY` are set in local `.env`. The real credentials trigger `GitHubService::appInstallationToken()` which bypasses the tests' generic `Http::fake(['api.github.com/*' => 404])` stubs (the token endpoint returns 404, causing "Failed to create GitHub App installation token").
- **Impact:** Developers with real GitHub App credentials get false test failures; CI doesn't set these vars so CI is green.
- **Fix:** Clear `GITHUB_APP_ID`/`GITHUB_APP_PRIVATE_KEY` in `phpunit.xml` `<php>` env block for test isolation.
- **Status:** Open — pre-existing, identical on `origin/main`.

### Credential encryption key fallback
- **Where:** `backend/config/credentials.php:35` — `'encryption_key' => env('CREDENTIAL_ENCRYPTION_KEY', env('APP_KEY'))`
- **Issue:** BYOK credentials fall back to `APP_KEY` for encryption if `CREDENTIAL_ENCRYPTION_KEY` is unset. Rotating `APP_KEY` invalidates all stored credentials.
- **Mitigation:** `AppServiceProvider::boot()` logs a production warning when `CREDENTIAL_ENCRYPTION_KEY` is absent. Rotation procedure documented in `config/credentials.php` comments.
- **Status:** Mitigated — warning + docs exist, but no hard enforcement.

## 🟠 Bugs / Fragility

### `GitHubService::appInstallationToken` installation listing
- **Where:** `backend/app/Services/GitHubService.php:262-271`
- **Issue:** When `installationId` is null, lists all installations and takes `$installations[0]['id']` (first installation only). Multi-installation apps may resolve the wrong installation.
- **Mitigation:** The webhook path always passes `installationId` from the payload (PR #89). The null path is only a fallback for single-installation apps.
- **Status:** Mitigated — webhook provides installationId; fallback is documented as single-installation only.

### GitHub context cache not installation-scoped
- **Where:** `backend/app/Services/GitHubService.php:51` — `Cache::remember('github:'.sha1($url), ...)`
- **Issue:** The main GitHub context cache (`context()`) is keyed only by URL, not by installation/token. Two different installations fetching the same URL share cached content. (The bot's `fetchRepoConfig` cache IS installation-scoped — fixed in PR #89.)
- **Impact:** Low — the main context fetch uses PAT (`GITHUB_TOKEN`) for public repos, and the bot path uses its own installation-scoped cache.
- **Status:** Low risk — acceptable for current single-deployment usage.

## 🟡 Tech debt

### SQLite not supported in production (Laravel 13)
- **Where:** `backend/app/Providers/AppServiceProvider.php` (boot guard)
- **Issue:** `turso/libsql-laravel` doesn't support Laravel 13 yet. Production requires managed Postgres/MySQL. SQLite is local-dev only.
- **Impact:** Cannot use serverless SQLite (Turso) in production.
- **Status:** Architectural constraint — documented in ADRs and AGENTS.md.

### Untracked `.env` in working tree
- **Where:** Repo root `.env`
- **Issue:** `.env` is untracked (not in `.gitignore` at start of conversation; a `.gitignore` change adding `/.env` is pending but unstaged).
- **Fix:** Commit the `.gitignore` change adding `/.env`.
- **Status:** Pending — `.gitignore` modification exists but unstaged.

### Frontend CSS consolidation
- **Where:** `backend/resources/css/app.css` (2,412+ lines)
- **Issue:** Per `git diff` stats, CSS was consolidated from multiple section files into a single large `app.css`. This is a maintainability concern for a file this size.
- **Status:** Recent change — may benefit from splitting into logical sections or adopting CSS modules.

## 🟢 Performance

### SSE via DB polling
- **Where:** `backend/app/Services/RunStreamer.php`
- **Issue:** SSE streaming polls the database (~55s window) rather than using WebSockets/push. This is an intentional architectural choice (ADR 0013) — simple, no extra infrastructure.
- **Impact:** Each connected client polls the DB; acceptable at current scale but won't scale to thousands of concurrent viewers.
- **Status:** Accepted — documented in ADR 0013.

### GitHub context caching
- **Where:** `backend/app/Services/GitHubService.php:51` — 10-min TTL
- **Status:** Good — cached via `Cache::remember`, reduces GitHub API calls.

### Repo config caching with sentinel
- **Where:** `backend/app/Services/GitHubBotService.php:96` — 5-min TTL, empty-array sentinel
- **Status:** Good — PR #89 fixed the null-not-cached issue by caching `[]` sentinel instead of null.

## 📋 Monitoring gaps

- **Sentry:** Configured for backend + frontend. Expected GitHub run failures excluded from Sentry (PR #81).
- **No structured metrics:** No Prometheus/Datadog integration; relies on Sentry + logs.
- **ReapStuckRuns:** Console command (`app/Console/Commands/ReapStuckRuns.php`) marks stuck running runs as failed — should be scheduled in production (cron/scheduler).

## 📚 Documentation

- **ADRs:** 24 architecture decision records in `doc/adr/` — well-maintained.
- **AGENTS.md:** Comprehensive AI-agent instructions (commands, conventions, gotchas).
- **GitHub App setup:** `doc/github-app-setup.md` + interactive `scripts/setup-github-app.sh`.
- **Deploy docs:** `backend/DOKKU_DEPLOY.md` (staging), `backend/CLOUD_DEPLOY.md` (Laravel Cloud production).
