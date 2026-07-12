# Laravel Cloud — ai-flow production

Console: [ai-flow production](https://cloud.laravel.com/dung-huynh-duc/ai-flow/production)

## Monorepo root

Set the application **root directory** to **`backend`** (not the repo root). Paths like `/var/www/html/resources/views/app.blade.php` assume Laravel’s `public/` is the web root inside that directory.

## Build command (fixes missing Vite manifest)

`public/build` is gitignored. Production **must** compile the React UI on every deploy.

**Recommended build command** (from [Laravel Cloud quickstart](https://cloud.laravel.com/docs/quickstart)):

```bash
composer install --no-dev && npm ci && npm run build
```

If `npm ci` fails (lockfile drift), use:

```bash
composer install --no-dev && npm install && npm run build
```

**Deploy command:**

```bash
php artisan migrate --force
```

After a successful build, the image should contain:

`public/build/manifest.json`

If you see:

`Vite manifest not found at: /var/www/html/public/build/manifest.json`

the build step did not run, failed silently, or ran from the wrong directory (wrong monorepo root).

## Database (fixes SQLite production error)

`AppServiceProvider` rejects `DB_CONNECTION=sqlite` when `APP_ENV=production`.

1. Attach **Serverless Postgres** or **Laravel MySQL** to the environment in Cloud.
2. Ensure env vars are set (Cloud usually injects `DB_*` when the resource is attached).
3. Set explicitly if needed:

```dotenv
APP_ENV=production
DB_CONNECTION=pgsql
DB_SSLMODE=require
```

Do **not** leave `DB_CONNECTION=sqlite` or unset `DB_CONNECTION` with a `.env` that still points at SQLite.

Use Neon’s **direct** hostname for migrations; pooled hostname is fine for web + workers after migrate (see `backend/README.md`).

## Worker

```bash
php artisan queue:work --sleep=1 --tries=2 --timeout=120
```

`QUEUE_CONNECTION=database` (not `sync`). Same `APP_KEY` on web and worker.

## Required env (minimum)

| Variable | Notes |
| --- | --- |
| `APP_KEY` | `php artisan key:generate --show` locally |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `OPENAI_API_KEY` | Required for runs |
| `DB_CONNECTION` | `pgsql` or `mysql` |
| `QUEUE_CONNECTION` | `database` |

Optional: `GITHUB_TOKEN`, `OPENROUTER_API_KEY` / `AI_BASE_URL`, `CACHE_STORE`.

## Verify after deploy

1. Open **Deployments** → latest deploy → **build logs** — confirm `npm run build` completed and `vite build` wrote assets.
2. Hit `/up` or `/api/health`.
3. Load `/` — no Vite manifest error in **Logs**.
