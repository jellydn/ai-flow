default:
    @just --list

# Run all prek hooks on every file in the repo (requires backend deps installed).
prek:
    prek run --all-files

# Install backend (PHP + JS) and frontend dependencies.
install:
    cd backend && composer install
    cd backend && npm install

# Copy .env, generate key, run migrations and seed.
setup:
    cd backend && cp .env.example .env
    cd backend && php artisan key:generate
    cd backend && touch database/database.sqlite
    cd backend && php artisan migrate --seed

# Run serve + queue:listen + pail + vite together.
dev:
    cd backend && composer run dev

# Serve the Laravel app.
serve:
    cd backend && php artisan serve

# Run the queue worker.
queue:
    cd backend && php artisan queue:work --sleep=1 --tries=2 --timeout=120

# Run the test suite.
test:
    cd backend && php artisan test

# Run a focused test by name.
testf filter:
    cd backend && php artisan test --filter={{filter}}

# Frontend typecheck (tsc --noEmit).
typecheck:
    cd backend && npm run typecheck

# Oxlint + oxfmt check on frontend TS.
lint-js:
    cd backend && npm run lint

# Format frontend TS in place.
fmt:
    cd backend && npm run format

# Frontend build (typecheck + vite build).
build:
    cd backend && npm run build

# Frontend tests (vitest).
test-js:
    cd backend && npm run test

# Structural TS conventions (konsistent).
konsistent:
    cd backend && npm run konsistent

# React component doctor.
doctor:
    cd backend && npm run doctor

# Laravel Pint check (CI mode).
pint-check:
    cd backend && ./vendor/bin/pint --test

# Laravel Pint fix in place.
pint:
    cd backend && ./vendor/bin/pint

# Run everything that CI runs (backend + frontend).
ci: pint-check test typecheck lint-js konsistent build
