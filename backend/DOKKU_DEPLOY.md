# Dokku VPS deployment

Dokku deploys the Laravel application from the repository's `backend/` directory. The Dockerfile builds the React assets and a production image with **Apache** (Laravel `public/` as document root). The Procfile runs migrations and idempotent launcher seeds during the release phase, then exposes separate web and queue-worker process types. Dokku's edge proxy remains nginx in front of the container.

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

Install the [dockerfile builder plugin](https://github.com/dokku/dokku/tree/master/plugins/builder-dockerfile) if `selected` cannot be set to `dockerfile`. If DNS is configured for an application domain, set it before enabling TLS:

```bash
dokku domains:set ai-flow ai-flow.docklight-staging.itman.fyi
dokku letsencrypt:enable ai-flow
```

The API streams progress with server-sent events, so disable proxy buffering and allow responses longer than the stream's 55-second polling window:

```bash
dokku nginx:set ai-flow proxy-buffering off
dokku nginx:set ai-flow proxy-read-timeout 75s
```

## Environment

Set a stable application key and production defaults:

```bash
dokku config:set --no-restart ai-flow \
  APP_KEY="base64:$(openssl rand -base64 32)" \
  APP_ENV=production \
  APP_DEBUG=false \
  APP_URL=https://ai-flow.docklight-staging.itman.fyi \
  LOG_CHANNEL=stderr \
  LOG_LEVEL=warning \
  CACHE_STORE=database \
  QUEUE_CONNECTION=database \
  DB_CONNECTION=pgsql \
  DB_SSLMODE=require
```

Then set the credentials without committing them:

```bash
dokku config:set --no-restart ai-flow \
  OPENAI_API_KEY='replace-me' \
  GITHUB_TOKEN='replace-me' \
  DB_HOST='replace-me' \
  DB_DIRECT_HOST='replace-me' \
  DB_PORT=5432 \
  DB_DATABASE='replace-me' \
  DB_USERNAME='replace-me' \
  DB_PASSWORD='replace-me'
```

For Neon, set `DB_HOST` to the **pooled** hostname for web and worker traffic. Set `DB_DIRECT_HOST` to the **direct** hostname; the `release` process in `Procfile` uses it for migrations (falls back to `DB_HOST` if unset).

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

## Verify

```bash
dokku ps:report ai-flow
dokku logs ai-flow --tail
curl --fail https://ai-flow.docklight-staging.itman.fyi/up
curl --fail https://ai-flow.docklight-staging.itman.fyi/api/health
```

If TLS is not configured yet, verify the same endpoints over HTTP.
