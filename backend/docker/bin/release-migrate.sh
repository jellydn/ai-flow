#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

export DB_HOST="${DB_DIRECT_HOST:-${DB_HOST:-}}"

php artisan migrate --force
php artisan db:seed --force
