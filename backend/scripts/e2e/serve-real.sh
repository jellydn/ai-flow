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
exec env QUEUE_CONNECTION=sync php artisan serve --port="${PORT}" --no-reload