# 24. Per-user built-in launcher visibility

Date: 2026-07-18

## Status

Proposed

## Context

The home page launcher grid and `LauncherSelector` show all four built-in launchers to every user. Some users only use one or two workflows (e.g., only "Review PR" and "Explain repository"). The extra launchers add visual noise and cognitive load.

The Filament admin panel already has an `active` boolean on the `launchers` table for globally enabling/disabling launchers. But this is an operator-level toggle — it affects all users equally. We need per-user control.

Product feedback: signed-in users should be able to hide built-in launchers they never use, keeping their UI focused on the workflows that matter to them.

The feature scope from the spec interview:
- Per-user visibility toggle (personal preference, not global)
- Hidden launchers are excluded from the user's UI only — they remain available to other users and guests
- Toggle is managed in user settings

## Decision

### 1. Pivot table: `user_hidden_launchers`

A simple pivot tracking which built-in launchers a user has chosen to hide:

```php
Schema::create('user_hidden_launchers', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('launcher_id')->constrained('launchers')->cascadeOnDelete();
    $table->unique(['user_id', 'launcher_id']);
    $table->timestamps();
});
```

**Presence = hidden.** The presence of a row means "this user has hidden this launcher." This avoids needing an `is_visible` boolean — the default state (no row) means the launcher is visible, which is the common case.

### 2. Filtering at the API layer

`GET /api/launchers` is currently anonymous and returns all active launchers. When the request carries an authenticated session:

1. Query active built-in launchers
2. Exclude any where `id IN (SELECT launcher_id FROM user_hidden_launchers WHERE user_id = ?)`
3. Append the user's custom launchers (from `user_launchers`) — [ADR 0023](0023-user-custom-launchers.md)

Unauthenticated requests continue to see all active built-in launchers (no filtering).

### 3. API endpoints

Under the `auth` middleware group (`/api/user/`):

| Method | Endpoint | Purpose |
|--------|----------|---------|
| `GET` | `/api/user/hidden-launchers` | List launcher IDs the user has hidden |
| `POST` | `/api/user/hidden-launchers/{launcher}` | Hide a built-in launcher |
| `DELETE` | `/api/user/hidden-launchers/{launcher}` | Unhide (show) a built-in launcher |

The `POST` and `DELETE` endpoints use the launcher slug (not ID) for consistency with other launcher endpoints. The route model binding resolves from the `launchers` table by slug.

### 4. Settings UI

The user settings page gains a new "Launcher visibility" section. It lists all built-in launchers with a toggle switch per launcher. Hidden launchers are visually dimmed or marked. The toggle sends `POST` or `DELETE` to the visibility endpoints and optimistically updates the UI.

When the user returns to the home page, the launcher grid and selector reflect their preferences.

### 5. No impact on runs

Hiding a launcher is purely a UI preference. It does not:
- Prevent the user from viewing or retrying past runs of that launcher
- Affect `POST /api/runs` validation — a hidden launcher can still be launched by slug
- Impact other users or guests
- Delete or archive any data

### Out of scope

- Hiding custom launchers (users can delete their custom launchers instead)
- Per-launcher reordering / favorites
- "Unhide all" bulk action (can be added later)
- Hiding launchers for guest users (cookie-based preference)

## Consequences

### Positive

- Clean, focused UI for users who only use a subset of workflows.
- Minimal database footprint — one row per hidden launcher, zero rows for the common case.
- Simple API: presence = hidden, absence = visible. No boolean state to manage.
- Fully decoupled from the run lifecycle — purely a UI preference.

### Negative

- The unified launcher list endpoint now has conditional filtering logic based on authentication state.
- Frontend needs to refetch launchers after toggling visibility (or optimistically update).
- If a launcher is globally disabled (`active = false`), the hidden row for it becomes inert — but we should clean up orphaned rows when a launcher is globally deactivated.

### Related

- [0023](0023-user-custom-launchers.md) — custom launchers (which appear in the same unified list)
- [0020](0020-per-user-launcher-prompt-overrides.md) — per-user prompt customization
