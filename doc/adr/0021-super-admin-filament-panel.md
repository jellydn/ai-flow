# 0021. Super admin control panel with Filament

Date: 2026-03-22

## Status

Accepted

## Context

Operators need to manage **users** and **workflow templates** (`launchers` table) without deploying code changes for every prompt or enable/disable tweak. Launchers are seeded from PHP launcher classes (ADR 0009) but persisted in the database; end users already customize prompts per account (ADR 0020). The public product UI is a React SPA served from Laravel; there is no role model today—only authenticated `User` records for the customer dashboard.

We evaluated Laravel admin options: **Filament** (free, TALL/Livewire, separate `/admin` panel), **Nova** (paid, Vue), **Backpack**, and a **custom React admin**. Building admin CRUD in React duplicates tables, forms, and auth for little gain when CRUD is the main need.

## Decision

1. Add **`users.is_super_admin`** (boolean, default false). Only users with this flag may access the admin panel.
2. Install **Filament v5** as a dedicated panel at **`/admin`**, using the same `User` model and session auth. Implement `FilamentUser` / `canAccessPanel()` to require `is_super_admin`.
3. **Filament resources**
   - **User**: list/view; toggle `is_super_admin` (restricted to super admins); no arbitrary password editing in v1 unless required.
   - **Launcher** (workflow template): edit `name`, `description`, `prompt_template`, `active`, and `output_schema` (JSON, validated as JSON on save). **`slug` is read-only** after create (API and URLs depend on it).
4. Bootstrap access via **`php artisan user:promote-super-admin {email}`** (or equivalent). Document in `backend/README.md`.
5. Keep the React SPA unchanged for customers; admin is a separate server-rendered surface (optional Filament `spa()` for in-panel navigation only).

Out of scope for initial delivery: impersonation, admin management of per-user `launcher_prompt_overrides`, run moderation resource, and automatic “re-sync launcher from PHP class” (remain `db:seed` / code deploy).

## Consequences

### Positive

- Fast, maintained admin UX without expanding the React bundle or public API surface.
- Clear security boundary: panel path + `is_super_admin` gate.
- Operators can tune prompts, schema, and `active` without redeploying launcher PHP classes for routine changes.

### Negative

- Second UI stack (Livewire/Tailwind via Filament) alongside React—acceptable for an internal panel only.
- Editing `output_schema` in admin can break AI validation if JSON Schema is invalid; mitigation: JSON validation on save and slug immutability.
- Re-running `DatabaseSeeder` can still overwrite launcher rows from code; operational docs must warn against blind `--seed` in production.
