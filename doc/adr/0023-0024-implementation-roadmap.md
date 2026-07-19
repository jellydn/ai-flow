# Implementation Roadmap: Custom Launchers + Per-User Visibility

Date: 2026-07-18 | ADRs: [0023](0023-user-custom-launchers.md), [0024](0024-per-user-launcher-visibility.md)

## Overview

Three phases, ordered by dependency. Each phase is independently shippable and testable.

---

## Phase 1 — Foundation (Database + Models)

**Goal:** Add the `user_launchers` table, `user_hidden_launchers` table, and `is_public` column. Update models and relationships. No API or UI changes yet — just the data layer.

### Migration: `user_launchers`

- [ ] Create migration for `user_launchers` table:
  - `id` (UUID primary, `HasUuids`)
  - `user_id` (FK → users, cascade on delete)
  - `slug` (string)
  - `name` (string)
  - `description` (text)
  - `prompt_template` (text)
  - `input_type` (string)
  - `output_schema` (JSON)
  - `timestamps`
  - Unique index: `['user_id', 'slug']`

### Migration: `user_hidden_launchers`

- [ ] Create migration for `user_hidden_launchers` table:
  - `id`
  - `user_id` (FK → users, cascade on delete)
  - `launcher_id` (FK → launchers, cascade on delete)
  - Unique index: `['user_id', 'launcher_id']`
  - `timestamps`

### Migration: `runs` changes

- [ ] Create migration to:
  - Add `user_launcher_id` (UUID, nullable, FK → `user_launchers.id`, null on delete)
  - Make `launcher_id` nullable (drop existing FK, re-add as nullable FK)
  - Add CHECK constraint: `(launcher_id IS NOT NULL AND user_launcher_id IS NULL) OR (launcher_id IS NULL AND user_launcher_id IS NOT NULL)`
  - Add `is_public` (boolean, default `false`)
  - Add index on `['user_launcher_id']`
  - Backfill `is_public = true` where `user_id IS NULL`
- **⚠️ Risk:** The `launcher_id` column change from non-nullable to nullable is a destructive operation. Test thoroughly on a staging database with real run data. The backfill must complete before the NOT NULL constraint is relaxed.

### Models

- [ ] Create `UserLauncher` model (`app/Models/UserLauncher.php`):
  - `HasUuids`, `HasFactory`
  - `$fillable`: `['user_id', 'slug', 'name', 'description', 'prompt_template', 'input_type', 'output_schema']`
  - `$casts`: `['output_schema' => 'array']`
  - Relationship: `belongsTo(User::class)`
  - Relationship: `hasMany(Run::class)`

- [ ] Create `UserHiddenLauncher` model (`app/Models/UserHiddenLauncher.php`):
  - `$fillable`: `['user_id', 'launcher_id']`
  - Relationship: `belongsTo(User::class)`, `belongsTo(Launcher::class)`

- [ ] Update `User` model:
  - Add `hasMany(UserLauncher::class)`
  - Add `hasMany(UserHiddenLauncher::class)`

- [ ] Update `Run` model:
  - Add `userLauncher()` → `belongsTo(UserLauncher::class)`
  - Add `$fillable`: `['user_launcher_id', 'is_public']`
  - Add `$casts`: `['is_public' => 'boolean']`
  - Add helper: `launcherSource()` that returns either `$this->launcher` or `$this->userLauncher`

### Validation

- [ ] Create `StoreUserLauncherRequest` form request:
  - `slug`: required, string, max 64, regex `/^[a-z0-9-]+$/`, unique per user
  - `name`: required, string, max 128
  - `description`: required, string, max 512
  - `prompt_template`: required, string, min 20
  - `input_type`: required, string, in: `['repository', 'pull_request', 'issue']`
  - `output_schema`: required, valid JSON, must parse as an object

- [ ] Create `UpdateUserLauncherRequest` (same rules, except slug is immutable — not in rules)

### Tests

- [ ] Unit test for `UserLauncher` model (casts, relationships)
- [ ] Unit test for `Run::launcherSource()` helper
- [ ] Feature test: migration runs without error
- [ ] Feature test: CHECK constraint rejects invalid runs (both launcher_id and user_launcher_id set, or neither set)
- [ ] Schema validation test: `StoreUserLauncherRequest` rejects invalid output_schema

**Ship check:** `php artisan test` passes. Migration runs cleanly forward and back.

---

## Phase 2 — API Layer (Backend)

**Goal:** New endpoints for custom launcher CRUD, built-in visibility toggling, and unified launcher listing. Update `StoreRunRequest` and run execution to support custom launchers.

### Custom launcher endpoints

- [ ] Create `UserLauncherController`:
  - `index`: List user's custom launchers. Return `UserLauncherResource` collection.
  - `store`: Create from `StoreUserLauncherRequest`. Return 201.
  - `update`: Update from `UpdateUserLauncherRequest`. Authorize ownership.
  - `destroy`: Delete. Authorize ownership. Cascade-deletes related runs.

- [ ] Create `UserLauncherResource` (API resource):
  - `id`, `slug`, `name`, `description`, `prompt_template`, `input_type`, `output_schema`, `created_at`, `updated_at`
  - Always include `is_custom: true`

### Visibility endpoints

- [ ] Create `UserHiddenLauncherController`:
  - `index`: List hidden launcher IDs for the authenticated user
  - `store`: Hide a launcher (by slug). Creates row in `user_hidden_launchers`.
  - `destroy`: Unhide a launcher (by slug). Deletes row.

### Unified launcher list

- [ ] Update `GET /api/launchers` closure in `routes/api.php`:
  - For authenticated users: exclude hidden launchers, append user's custom launchers
  - Add `icon`, `tone`, `is_custom` fields to the response
  - Built-in launchers get their existing icon/tone from a server-side map
  - Custom launchers get auto-assigned icon/tone (hash-based deterministic pick from 6 icons × 4 tones)
  - Consider extracting to a proper `LauncherController` class (the closure is getting complex)

### Run creation with custom launchers

- [ ] Update `StoreRunRequest`:
  - Replace `exists:launchers,slug` with custom validation closure
  - Accept optional `is_public` boolean (authenticated users only)
  - Accept optional `user_launcher_slug` field (mutually exclusive with `launcher`)

- [ ] Update `RunController::store`:
  - Resolve launcher from either `launchers` or `user_launchers` table
  - Set `launcher_id` or `user_launcher_id` accordingly
  - Set `is_public` based on request (default: `false` for authenticated, `true` for guest)

### Run execution with custom launchers

- [ ] Update `RunExecutor::execute`:
  - Use `$run->launcherSource()` to get the launcher (built-in or custom)
  - Use the launcher's `prompt_template` and `output_schema` from either source
  - No other changes needed — execution pipeline is identical

### Run resource and policy

- [ ] Update `RunResource`:
  - Include `is_public` in response
  - Resolve launcher name/slug from `launcherSource()`
  - Include `is_custom` boolean

- [ ] Update `RunPolicy::view`:
  - Allow if `$run->is_public || $run->isOwnedBy($user)`

### Routes

- [ ] Register new routes in `backend/routes/api.php` under `auth` middleware:
  ```
  GET    /api/user/launchers
  POST   /api/user/launchers
  PUT    /api/user/launchers/{userLauncher}
  DELETE /api/user/launchers/{userLauncher}
  GET    /api/user/hidden-launchers
  POST   /api/user/hidden-launchers/{launcher:slug}
  DELETE /api/user/hidden-launchers/{launcher:slug}
  ```

### Tests

- [ ] Feature test: CRUD lifecycle for custom launchers (create, list, update, delete)
- [ ] Feature test: slug uniqueness scoped to user (two users can share a slug)
- [ ] Feature test: hide and unhide built-in launchers
- [ ] Feature test: unified launcher list includes custom launchers for authenticated user
- [ ] Feature test: unified launcher list filters out hidden launchers
- [ ] Feature test: guest cannot access custom launcher endpoints
- [ ] Feature test: custom launcher run executes successfully (mock AI + GitHub)
- [ ] Feature test: `is_public` controls run visibility
- [ ] Feature test: `StoreRunRequest` validates against both launcher tables

**Ship check:** `php artisan test` passes. All new endpoints return correct HTTP status codes.

---

## Phase 3 — Frontend (React/TS)

**Goal:** Settings tab for custom launchers, visibility toggles in settings, custom launchers in home grid, public/private toggle on launch, report rendering for custom schemas.

### API types and services

- [ ] Add types to `backend/resources/ts/types/api.ts`:
  - `UserLauncher` interface (mirrors `UserLauncherResource`)
  - Update `Launcher` interface: add `icon`, `tone`, `is_custom`
  - Add `RunVisibility` type

- [ ] Add service functions to `backend/resources/ts/services/auth.ts` (or new `userLaunchers.ts`):
  - `fetchUserLaunchers()` → `GET /api/user/launchers`
  - `createUserLauncher(data)` → `POST /api/user/launchers`
  - `updateUserLauncher(id, data)` → `PUT /api/user/launchers/{id}`
  - `deleteUserLauncher(id)` → `DELETE /api/user/launchers/{id}`
  - `fetchHiddenLaunchers()` → `GET /api/user/hidden-launchers`
  - `hideLauncher(slug)` → `POST /api/user/hidden-launchers/{slug}`
  - `unhideLauncher(slug)` → `DELETE /api/user/hidden-launchers/{slug}`

- [ ] Update `createRun` in `backend/resources/ts/services/run.ts`:
  - Accept optional `isPublic` parameter
  - Send `is_public` in request body (authenticated users only)
  - Update `Run` type to include `is_public`

### Custom launcher creation form (settings tab)

- [ ] Create `CustomLaunchersSection.tsx` component:
  - List view: shows user's custom launchers with edit/delete actions
  - Create form: name, description, slug, input_type (dropdown), prompt_template (textarea), output_schema (code textarea with JSON validation)
  - Edit form: same as create, but slug is read-only
  - Inline JSON validation for output_schema (try `JSON.parse`, show error)
  - Confirmation dialog for deletion

- [ ] Integrate into `Dashboard.tsx` (or wherever settings tabs live):
  - Add "Custom launchers" tab/section
  - Render `CustomLaunchersSection`

### Visibility toggles (settings tab)

- [ ] Create `LauncherVisibilitySection.tsx` component:
  - List all built-in launchers with toggle switches
  - Optimistic UI: toggle updates immediately, API call happens in background
  - Revert on API failure

- [ ] Integrate into `Dashboard.tsx`:
  - Add "Launcher visibility" section

### Home page: custom launchers in grid + selector

- [ ] Update `LauncherIcon` component:
  - Accept `icon` as a string name (mapped to Lucide icon) — or keep the current LucideIcon prop and map server-side
  - Handle "custom" launchers with a default icon

- [ ] Update `LauncherSelector`:
  - Show "Custom" badge on custom launcher buttons
  - Handle launchers without `launcherMeta` entries (fallback to backend-provided `icon`/`tone`)

- [ ] Update `Home.tsx`:
  - Custom launchers already appear in the `launchers` array from the unified API
  - Add "Custom" badge to workflow cards where `is_custom: true`
  - Handle missing `launcherMeta` entries gracefully (use backend-provided metadata)

- [ ] Update `launcherMeta.ts`:
  - Keep built-in metadata as a fallback
  - Function `getLauncherMeta(launcher)` that checks `launcherMetaBySlug` first, then uses backend-provided `icon`/`tone` for custom launchers

### Public/private toggle on launch

- [ ] Add visibility toggle to `LaunchArea.tsx` (or the launch card):
  - Radio or toggle: "Public" / "Private"
  - Only shown for authenticated users
  - Default: Private
  - Pass `is_public` to `createRun`

### Report rendering for custom schemas

- [ ] Update `Report.tsx`:
  - If run result has the standard schema fields (`summary`, `risk`, `findings`, `verification_steps`), render as today
  - If custom schema, render a generic JSON tree view or formatted `<pre>` block
  - A simple collapsible JSON viewer is sufficient for MVP — full schema-aware rendering can come later

### E2E tests

- [ ] Add E2E test: create a custom launcher, run it, verify report renders
- [ ] Add E2E test: hide a built-in launcher, verify it's gone from the grid
- [ ] Add E2E test: public custom launcher run is viewable without auth
- [ ] Add E2E test: private custom launcher run requires auth to view

### Frontend validation

- [ ] `npm run typecheck` passes
- [ ] `npm run lint` passes
- [ ] `npm run test` (vitest) passes
- [ ] `npm run konsistent` passes

**Ship check:** Full E2E suite passes (`npm run test:e2e`). UI is visually consistent with existing design.

---

## Rollback & Risk Mitigation

| Risk | Likelihood | Mitigation |
|------|-----------|------------|
| `launcher_id` NOT NULL → nullable migration is destructive | Medium | Test on staging with production-like data. Run backfill before migration. Take a DB snapshot. |
| Dual FK on runs breaks existing queries | Medium | Audit all `Run::with('launcher')` calls. Add `launcherSource()` helper early. |
| Custom output schemas break Report rendering | High | Phase 3 includes generic JSON viewer fallback. |
| Slug collisions between built-in and custom | Low | Custom slugs are per-user scoped. Built-in slugs are global. But validate against built-in slugs during creation to prevent confusion. |
| Rate limit exhaustion from custom launcher abuse | Low | Existing rate limits apply. Custom launchers use the same `POST /api/runs` endpoint, same throttles. |

## Estimated Effort

| Phase | Est. hours | Key files touched |
|-------|-----------|-------------------|
| Phase 1 (Foundation) | 4-6 | 3 migrations, 3 models, 1 form request, 6 test files |
| Phase 2 (API) | 5-8 | 3 controllers, 2 resources, 1 form request update, 1 route file, 8 test files |
| Phase 3 (Frontend) | 8-12 | 4 new components, 3 component updates, 2 service files, 1 type file, 4 E2E tests |
| **Total** | **17-26** | — |
