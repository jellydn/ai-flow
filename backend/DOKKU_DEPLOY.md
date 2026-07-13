# Dokku VPS deployment

Dokku deploys the Laravel application from the repository's `backend/` directory. The Dockerfile builds the React assets and a production image with **nginx** and **PHP-FPM** (Laravel `public/` as document root). The Procfile runs migrations and idempotent launcher seeds during the release phase via `docker/bin/release-migrate.sh`, then exposes separate web and queue-worker process types. Dokku's edge proxy remains nginx in front of the container.

**Staging reference:** SSH host `docklight-staging.itman.fyi`, public app URL `https://ai-flow-staging.itman.fyi` (DNS **A** record must exist before Let's Encrypt).

## One-time Dokku setup

Run these commands on `docklight-staging.itman.fyi` as a Dokku administrator:

```bash
dokku apps:create ai-flow
dokku builder:set ai-flow build-dir backend
dokku builder:set ai-flow selected dockerfile
dokku ports:set ai-flow http:80:80
```

Skip `apps:create` if the app already exists.

Confirm the app uses the Dockerfile builder (not Herokuish). If deploy logs show `Building ai-flow from herokuish` or `Unable to select a buildpack`, run on the server:

```bash
dokku builder:report ai-flow
dokku builder:set ai-flow selected dockerfile
dokku builder:set ai-flow build-dir backend
```

Install the [dockerfile builder plugin](https://github.com/dokku/dokku/tree/master/plugins/builder-dockerfile) if `selected` cannot be set to `dockerfile`.

Create a DNS **A** record for the app hostname (for example `ai-flow-staging` → your VPS IP) and wait until it resolves before enabling TLS:

```bash
dig +short ai-flow-staging.itman.fyi A   # must return the server IP
dokku domains:set ai-flow ai-flow-staging.itman.fyi
dokku letsencrypt:enable ai-flow
```

The API streams progress with server-sent events, so disable proxy buffering and allow responses longer than the stream's 55-second polling window:

```bash
dokku nginx:set ai-flow proxy-buffering off
dokku nginx:set ai-flow proxy-read-timeout 75s
```

## Database

Laravel reads **`DB_CONNECTION`**, **`DB_URL`** (or discrete `DB_HOST` / `DB_*`), **`CACHE_STORE`**, and **`QUEUE_CONNECTION`**. It does **not** use Dokku's `DATABASE_URL` env var unless you copy it to `DB_URL`.

### Option A: Dokku Postgres plugin (staging)

Create and link a Postgres service, then map the link URL into Laravel:

```bash
dokku postgres:create ai-flow-db
dokku postgres:link ai-flow-db ai-flow
```

After link, Dokku sets `DATABASE_URL` on the app. Configure Laravel explicitly (one-shot on the server):

```bash
DATABASE_URL="$(dokku config:get ai-flow DATABASE_URL)"

dokku config:set ai-flow \
  DB_CONNECTION=pgsql \
  DB_URL="$DATABASE_URL" \
  DB_SSLMODE=require \
  CACHE_STORE=database \
  QUEUE_CONNECTION=database
```

If the queue worker crashes with `database.sqlite does not exist`, the app is still on SQLite defaults—set the block above plus `APP_KEY` (see Environment).

Run migrations if the release phase did not complete:

```bash
dokku run ai-flow php artisan migrate --force
dokku run ai-flow php artisan db:seed --force
```

### Option B: External Postgres (e.g. Neon)

Set discrete variables (do not rely on `DATABASE_URL` unless you also set `DB_URL` to the same value):

```bash
dokku config:set --no-restart ai-flow \
  DB_CONNECTION=pgsql \
  DB_HOST='your-pooled-host.neon.tech' \
  DB_PORT=5432 \
  DB_DATABASE='ai_flow' \
  DB_USERNAME='<db-user>' \
  DB_PASSWORD='<db-password>' \
  DB_SSLMODE=require \
  DB_DIRECT_HOST='your-direct-host.neon.tech'
```

For Neon, use the **pooled** hostname for web and worker traffic. Use **`DB_DIRECT_HOST`** for migrations; `docker/bin/release-migrate.sh` prefers it when set (falls back to `DB_HOST`).

To use a single URL instead of discrete fields:

```bash
dokku config:set ai-flow DB_URL='postgresql://<user>:<password>@<host>:5432/<database>?sslmode=require'
dokku config:unset ai-flow DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD
```

## Environment

Generate **`APP_KEY` once** on initial setup (do not rerun on every deploy — queued jobs and cookies depend on it):

```bash
dokku config:set --no-restart ai-flow APP_KEY="base64:$(openssl rand -base64 32)"
```

Set production defaults (safe to rerun):

```bash
dokku config:set --no-restart ai-flow \
  APP_ENV=production \
  APP_DEBUG=false \
  APP_URL=https://ai-flow-staging.itman.fyi \
  ASSET_URL=https://ai-flow-staging.itman.fyi \
  LOG_CHANNEL=stderr \
  LOG_LEVEL=warning \
  CACHE_STORE=database \
  QUEUE_CONNECTION=database \
  DB_CONNECTION=pgsql
```

Then set secrets without committing them:

```bash
dokku config:set --no-restart ai-flow \
  OPENAI_API_KEY='replace-me' \
  GITHUB_TOKEN='replace-me'
```

`OPENAI_API_KEY` is required for real AI workflow runs. `GITHUB_TOKEN` is recommended for GitHub API rate limits.

If you use **Option B (Neon)** instead of Dokku Postgres, add the `DB_*` / `DB_DIRECT_HOST` variables from the Database section; if you use **Option A**, ensure `DB_URL` is set from `DATABASE_URL` as shown above.

## Deploy

Add the Dokku Git remote once:

```bash
git remote add dokku dokku@docklight-staging.itman.fyi:ai-flow
```

Deploy from a branch that includes `backend/Dockerfile` and `backend/Procfile` (for example `main` or your Dokku PR branch):

```bash
git push dokku main:main
dokku ps:scale ai-flow web=1 worker=1
```

Use `git push dokku <local-branch>:main` if Dokku’s deploy branch is `main` but your work is on another branch. Avoid `main:HEAD` unless you intend to update Dokku’s default branch ref explicitly.

Dokku starts one web process automatically on the first deploy. The explicit scale command also starts the queue worker required to execute AI runs.

`app.json` defines healthchecks (`/up` on port 80) for both the `web` and `worker` process types so Dokku can verify container health and perform zero-downtime deploys.

## Verify

```bash
dokku ps:report ai-flow
dokku config:show ai-flow | grep -E 'APP_URL|DB_|CACHE|QUEUE|APP_KEY'
dokku logs ai-flow --tail
curl --fail https://ai-flow-staging.itman.fyi/up
curl --fail https://ai-flow-staging.itman.fyi/api/health
```

If TLS is not configured yet, verify the same endpoints over HTTP.

## Troubleshooting

| Symptom | Likely cause | Fix |
|--------|----------------|-----|
| Let's Encrypt `NXDOMAIN` | No DNS A/AAAA for app hostname | Add DNS, wait for propagation, retry `dokku letsencrypt:enable ai-flow` |
| Worker restart loop, `database.sqlite` does not exist | `DB_CONNECTION` still `sqlite`; `DATABASE_URL` not mapped to `DB_URL` | Set `DB_CONNECTION=pgsql`, `DB_URL` from `DATABASE_URL`, `CACHE_STORE=database`, `QUEUE_CONNECTION=database` |
| HTTP **500** / “Production PostgreSQL must use TLS” | Missing `DB_SSLMODE` in production | `dokku config:set ai-flow DB_SSLMODE=require` (required by `AppServiceProvider` for web requests) |
| HTTPS page blank / browser blocks assets | Vite tags used `http://` (mixed content) behind Dokku TLS | Set `ASSET_URL=https://<your-host>` and deploy with `trustProxies` + `URL::forceScheme('https')` in app bootstrap |
| Runs stay `queued` | No worker | `dokku ps:scale ai-flow web=1 worker=1` and check `dokku ps:report ai-flow` |
| 404 on `/api/*` | Wrong web root or missing `.htaccess` / nginx config | Confirm Dockerfile document root is `public/` and redeploy |

Do not paste production database URLs or API keys into tickets or chat; rotate credentials if they were exposed.
