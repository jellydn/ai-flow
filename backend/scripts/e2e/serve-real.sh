#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/../.."
PORT="${PORT:-8000}"

if [[ ! -f .env ]]; then
    cp .env.example .env
    php artisan key:generate
fi

touch database/database.sqlite
php artisan migrate --force --seed

npm run build
# Sync queue + array mail so magic-link E2E does not need Resend or a worker.
exec env QUEUE_CONNECTION=sync MAIL_MAILER=array RUNS_RATE_LIMIT_PER_HOUR=100 php artisan serve --host=localhost --port="${PORT}" --no-reload
