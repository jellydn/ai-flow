# 23. User-created custom launchers

Date: 2026-07-18

## Status

Proposed

## Context

The platform ships four built-in launchers (review-pr, plan-issue, explain-repository, laravel-doctor) seeded from PHP classes into the `launchers` table. Signed-in users can already override the prompt template per built-in launcher via `LauncherPromptOverride` ([ADR 0020](0020-per-user-launcher-prompt-overrides.md)), but they cannot create entirely new workflows.

Product feedback: developers want to define their own launchers — a "security scan" for PRs, "release note" generator, "DB schema reviewer" — with custom names, prompts, input types, and output schemas. These should feel like first-class citizens alongside built-in launchers in the UI, without requiring code deploys or database access.

The feature scope from the spec interview:
- Users define name, description, prompt, input type, and custom JSON output schema
- Custom launchers appear in the home page launcher grid alongside built-in ones
- Run visibility is per-run (public/private toggle, defaulting to private for authenticated users)
- Visual differentiation via a "Custom" badge (auto-assigned icon/color)

## Decision

### 1. Separate `user_launchers` table

Custom launchers live in their own table to keep the built-in `launchers` table free of user-owned records. This avoids accidental exposure of user data through shared queries and keeps the Filament admin panel scoped to platform templates.

**Columns:** `id` (UUID primary), `user_id` (FK → users, cascade on delete), `slug` (string, unique per user), `name`, `description`, `prompt_template` (text), `input_type` (string), `output_schema` (JSON), `timestamps`.

**Slug uniqueness:** Scoped per-user (`$table->unique(['user_id', 'slug'])`). Two users can both have a "security-scan" slug without collision. The API uses the user's authenticated context to resolve.

**Output schema validation:** The `output_schema` JSON column stores an arbitrary JSON Schema object. Validate that it is well-formed JSON at write time. At runtime, `RunExecutor` uses the stored schema exactly as it does for built-in launchers via `JsonSchemaValidator`. The user is responsible for designing a schema the AI model can produce; malformed schemas will surface as validation failures on the run.

### 2. Runs relationship: dual nullable FK

The existing `runs.launcher_id` FK constrains runs to built-in launchers only. Custom launcher runs need a different reference.

**Approach:** Add `user_launcher_id` (nullable FK → `user_launchers.id`). Make `launcher_id` nullable. Add a CHECK constraint ensuring exactly one of the two is set:

```sql
CHECK ((launcher_id IS NOT NULL AND user_launcher_id IS NULL)
    OR (launcher_id IS NULL AND user_launcher_id IS NOT NULL))
```

This avoids polymorphic relationships (which have weak referential integrity) while keeping the FK guarantees. At query time, a run belongs to either a built-in or a custom launcher — never both, never neither.

**Why not morphTo:** Polymorphic relationships in Laravel lose real FK constraints, making orphaned references possible. Two nullable FKs with a CHECK constraint give us both referential integrity and clarity.

### 3. No prompt overrides for custom launchers

`LauncherPromptOverride` remains scoped to built-in launchers only. Custom launchers are fully owned by their creator — editing the `prompt_template` directly is equivalent to overriding it. Adding an override layer on top would be redundant and confusing.

### 4. Run visibility: `is_public` boolean on runs

Currently, `RunPolicy::view()` checks `user_id IS NULL` to determine if a run is public. With custom launchers defaulting to private runs, we need an explicit visibility flag.

**Add `is_public` boolean to `runs`:**
- Guest runs (no user): `is_public = true` by default
- Authenticated user runs: `is_public = false` by default, overridable at creation time
- Existing runs: backfill `is_public = true` for runs with `user_id IS NULL`, `false` otherwise

**Updated policy:**
```php
public function view(?User $user, Run $run): bool
{
    if ($run->is_public) return true;
    return $user !== null && $run->isOwnedBy($user);
}
```

The `POST /api/runs` request body accepts an optional `is_public` boolean (authenticated users only). The `StoreRunRequest` validates and defaults it.

### 5. API design

All endpoints under the `auth` middleware group (`/api/user/`):

| Method | Endpoint | Purpose |
|--------|----------|---------|
| `GET` | `/api/user/launchers` | List user's custom launchers |
| `POST` | `/api/user/launchers` | Create a custom launcher |
| `PUT` | `/api/user/launchers/{userLauncher}` | Update a custom launcher |
| `DELETE` | `/api/user/launchers/{userLauncher}` | Delete a custom launcher |

**Unified launcher list:** `GET /api/launchers` returns both visible built-in launchers AND the authenticated user's custom launchers. Each item includes an `is_custom` boolean. Unauthenticated requests see only built-in launchers.

**`StoreRunRequest` slug validation:** Replace the static `exists:launchers,slug` rule with a custom closure that:
1. First checks `launchers` table for the slug (active launchers only)
2. If authenticated and not found, checks `user_launchers` where `slug = $value AND user_id = Auth::id()`
3. Fails if neither matches

### 6. Frontend approach

**Dynamic metadata:** The `GET /api/launchers` response includes UI attributes for each launcher: `icon` (a Lucide icon name string), `tone` (color key), and `is_custom` (boolean). For built-in launchers, the backend maps from the existing `launcherMeta.ts` data. For custom launchers, the backend deterministically assigns an icon and tone — e.g., hash the slug to pick from a pool of 6 icons and 4 tones. This keeps the frontend free of hardcoded launcher metadata.

**LauncherSelector:** Continues to render the first N items. With custom launchers mixed in, the "first 4" approach naturally surfaces the most relevant ones (which will be built-in launchers first, since custom launchers are appended).

**Custom badge:** The home grid and selector render a small "Custom" badge on items where `is_custom: true`.

**Settings tab:** A new settings section (alongside "API keys", "Workflow prompts", "Run history") for creating and managing custom launchers. A form with fields for name, description, slug, input type, prompt template, and output schema.

### Out of scope

- Sharing custom launchers between users
- Forking built-in launchers into custom ones
- Marketplace / discovering other users' launchers
- Custom launcher input types beyond what GitHubService supports
- Editing output schema of an existing custom launcher that has runs (schema changes would break historical runs)

## Consequences

### Positive

- Users can create arbitrary workflows without code deploys or database access.
- Custom launchers use the same execution pipeline (`ExecuteLauncherJob`, `RunExecutor`, `JsonSchemaValidator`) as built-in ones — no code duplication.
- Clean separation between platform templates (admin-managed, in `launchers`) and user content (in `user_launchers`).
- Per-user slug scoping prevents namespace collisions between users.

### Negative

- The dual FK on `runs` adds complexity to queries and model relationships. Every query that loads `launcher` must handle `launcher_id IS NULL` cases.
- `RunResource` needs to resolve the launcher name/slug from either table, adding a join or conditional eager-load.
- Custom output schemas mean no UI can render the `result` JSON generically — the current `Report` component assumes `summary`, `risk`, `findings`, `verification_steps`. Custom schema results may not display well without a generic JSON renderer.
- Slug validation in `StoreRunRequest` becomes a two-table lookup instead of a simple `exists` rule.

### Related

- [0024](0024-per-user-launcher-visibility.md) — per-user built-in launcher hiding
- [0020](0020-per-user-launcher-prompt-overrides.md) — per-user prompt overrides
- [0018](0018-run-ownership-and-visibility.md) — run ownership model
- [0009](0009-launcher-classes-seeded-to-database.md) — launcher seeding pattern
