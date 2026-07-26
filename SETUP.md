# Development Environment Setup Guide

## Overview

This document provides a comprehensive guide for setting up the ai-flow development environment from scratch on a fresh Ubuntu 24.04 system.

## Prerequisites

- Ubuntu 24.04 LTS (Noble Numbat)
- `sudo` access
- Internet connection

## System Dependencies

### PHP 8.4

Add the Ondřej Surý PPA for PHP 8.4:

```bash
sudo add-apt-repository ppa:ondrej/php -y
sudo apt-get update
```

Install PHP 8.4 and required extensions:

```bash
sudo apt-get install -y \
  php8.4 \
  php8.4-cli \
  php8.4-common \
  php8.4-curl \
  php8.4-mbstring \
  php8.4-xml \
  php8.4-zip \
  php8.4-sqlite3 \
  php8.4-pgsql \
  php8.4-intl
```

Verify installation:

```bash
php --version
# Expected: PHP 8.4.23 (cli) or newer
```

### Composer

Install Composer globally:

```bash
curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer
```

Verify installation:

```bash
composer --version
# Expected: Composer version 2.10.2 or newer
```

### Node.js

Node.js should already be installed (v22.14.0 or newer). Verify:

```bash
node --version
# Expected: v22.14.0 or newer
```

## Application Setup

All commands are run from the `backend/` directory:

```bash
cd backend
```

### 1. Environment Configuration

```bash
cp .env.example .env
```

### 2. Install Dependencies

Install PHP dependencies:

```bash
composer install
```

Install Node.js dependencies:

```bash
npm install
```

### 3. Application Key

Generate the Laravel application key:

```bash
php artisan key:generate
```

### 4. Database Setup

Create SQLite database file:

```bash
touch database/database.sqlite
```

Run migrations and seeders:

```bash
php artisan migrate --seed
```

This will:
- Create all database tables (18 migrations)
- Seed the database with built-in launchers
- Bootstrap a super admin user (default: `dung@productsway.com`)

## Running the Application

### Development Server

Start all development services (Laravel server, Vite, queue worker, and log tailing):

```bash
composer run dev
```

This starts:
- **Laravel server** at `http://localhost:8000`
- **Vite dev server** at `http://localhost:5173`
- **Queue worker** listening on the default queue
- **Log tailing** via `pail`

### Individual Services

Alternatively, run services separately:

```bash
# Laravel server
php artisan serve

# Queue worker (production/standalone)
php artisan queue:work --tries=2 --timeout=120

# Vite dev server
npm run dev

# Log tailing
php artisan pail
```

## Verification

### Test the Application

Access the homepage:

```bash
curl http://localhost:8000
```

Test the API:

```bash
curl http://localhost:8000/api/launchers
```

Expected response: JSON array with 4 built-in launchers:
- `review-pr` - Review Pull Request
- `plan-issue` - Plan GitHub Issue
- `explain-repository` - Explain Repository
- `laravel-doctor` - Laravel Project Doctor

### Run Tests

Backend tests (PHPUnit):

```bash
php artisan test
```

Expected: 345 tests passed

Frontend tests (Vitest):

```bash
npm run test
```

Expected: 115 tests passed

### Code Quality Checks

PHP linting:

```bash
./vendor/bin/pint --test
```

TypeScript type checking:

```bash
npm run typecheck
```

Frontend linting:

```bash
npm run lint
```

## Configuration Notes

### Database

The default configuration uses SQLite for local development. The database file is located at `backend/database/database.sqlite`.

For production deployments, use PostgreSQL or MySQL as specified in `CLOUD_DEPLOY.md` or `DOKKU_DEPLOY.md`.

### Queue Connection

The application uses `QUEUE_CONNECTION=database` by default. Jobs are processed by the queue worker.

Never use `sync` in production environments.

### AI Provider Keys

To test AI workflow functionality, set one of the following API keys in `.env`:

- `OPENROUTER_API_KEY` - Required for guest workflow runs
- `OPENAI_API_KEY` - Optional for authenticated runs

Models can be configured via:
- `AI_MODEL` - Overrides `OPENAI_MODEL`
- `OPENAI_MODEL` - Defaults to `gpt-5` in `.env.example`

### Rate Limits

Default rate limits are configured in `.env`:

- Workflow runs: 5 per hour per IP
- Stream endpoint: 30 per minute per IP
- Magic link: 3 per minute per IP
- Auth login: 10 per minute per IP
- Auth register: 5 per minute per IP

## Troubleshooting

### Missing PHP Extensions

If you encounter errors about missing PHP extensions, install them:

```bash
sudo apt-get install -y php8.4-<extension-name>
```

Common extensions: `intl`, `curl`, `mbstring`, `xml`, `zip`, `sqlite3`, `pgsql`

### Composer Platform Requirements

If Composer complains about platform requirements, either:

1. Install the missing extension (preferred)
2. Temporarily ignore with `--ignore-platform-req=ext-<name>`

### Port Already in Use

If port 8000 or 5173 is already in use, you can:

1. Stop the conflicting process
2. Change the port in `.env` (`APP_URL`)
3. Use `php artisan serve --port=<port>`

## Next Steps

1. Configure AI provider API keys for workflow testing
2. Set up GitHub token for higher API rate limits (`GITHUB_TOKEN`)
3. Review `backend/README.md` for detailed architecture information
4. Review ADRs in `doc/adr/README.md` for architectural decisions

## System Information

Setup verified on:
- **OS:** Ubuntu 24.04.4 LTS (Noble Numbat)
- **PHP:** 8.4.23
- **Composer:** 2.10.2
- **Node.js:** v22.14.0
- **Date:** July 25, 2026
