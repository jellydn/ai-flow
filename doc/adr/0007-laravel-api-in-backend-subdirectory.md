# 7. Laravel API in `backend/` subdirectory

Date: 2026-07-12

## Status

Accepted

## Context

ADR 0001 recorded a Vite + React prototype at the repository root with Laravel deferred. The tree now includes a full Laravel 13 application under `backend/` (API, jobs, migrations, tests) while the root remains the Amp-synced React launcher UI.

## Decision

Use a **monorepo layout**: `backend/` is the deployable Laravel application root (Laravel Cloud: “Deploy `backend` as the application root”). Keep the marketing/launcher SPA at the repo root until it is wired to the API or consolidated.

Document backend setup in `backend/README.md`; product ADRs live in `doc/adr/` at repo root.

## Consequences

### Positive

- Clear separation for Cloud deploy, `composer install`, and `php artisan` without mixing Node and PHP roots.
- Frontend and API can evolve on different release cadences.
- Matches README product stack (Laravel) with a concrete implementation path.

### Negative

- Two `package.json` / Vite configs (root UI vs Laravel’s default frontend assets).
- Cross-origin and env configuration needed when the SPA calls `POST /api/runs`.
- ADR 0001 is only partially accurate until integration is explicit in docs and code.