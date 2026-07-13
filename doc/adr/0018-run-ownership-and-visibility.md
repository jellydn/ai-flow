# 18. Run ownership and visibility

Date: 2026-07-13

## Status

Accepted

## Context

Runs were originally anonymous (no `user_id`). With authentication, runs created by authenticated users should be private — visible only to their owner. Anonymous runs remain publicly accessible via their UUID share link.

The application needs clear authorization rules for viewing, retrying, and deleting runs, enforced server-side via policies (not just frontend filtering).

## Decision

**Run ownership model:**
- `runs.user_id` is nullable: `null` = anonymous public run, non-null = owned run.
- Authenticated runs set `user_id` to the authenticated user's ID at creation time.
- Anonymous runs remain anonymous — they are not auto-claimed when a user signs in.

**Authorization rules (enforced via `RunPolicy`):**
- `view`: Public runs (`user_id = null`) are viewable by anyone. Private runs are viewable only by their owner.
- `retry`: Only the run owner can retry a run.
- `delete`: Only the run owner can delete a run.

**Data isolation:**
- `RunHistoryController::index()` scopes queries to `where('user_id', $request->user()->id)`.
- `RunResource` selectively exposes fields based on ownership — provider, model, and other metadata are only included for owned runs.
- Public result pages expose only the structured report (summary, findings, verification_steps), never email, credential IDs, internal errors, or sensitive metadata.

**Provider credential association:**
- `runs.provider_credential_id` is nullable and set when a user launches with a saved credential.
- `runs.provider` and `runs.model` are snapshotted at launch time for historical display.
- The credential itself is never serialized into the run record — only the foreign key reference.

**Account deletion:**
- Deleting a user account cascade-deletes the user's runs and provider credentials.
- Anonymous runs are not affected by any user account deletion.

## Consequences

### Positive
- Clean separation between public (anonymous) and private (authenticated) runs.
- IDOR protection via server-side policy enforcement.
- Public share links remain useful without exposing private metadata.
- Run history is scoped per-user without complex query logic.

### Negative
- Anonymous runs have no owner to manage them (retry, delete) — they persist until manually cleaned up.
- No claim flow for associating anonymous runs with a newly registered user's account.
- The `user_id` nullable column requires careful null-checking in queries and policies.
