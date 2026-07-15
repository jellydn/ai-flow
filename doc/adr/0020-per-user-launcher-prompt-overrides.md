# 20. Per-user launcher prompt overrides with run snapshot

Date: 2026-07-15

## Status

Accepted

## Context

Launchers are seeded from PHP classes into `launchers.prompt_template` ([ADR 0009](0009-launcher-classes-seeded-to-database.md)). `RunExecutor` builds the model prompt from that column plus GitHub context. All users share the same default text unless operators change the database.

Product feedback: presets such as **Laravel project doctor** feel hardcoded, and signed-in users want to tune instructions per workflow without forking the repo. We are not building a marketplace or arbitrary new slugs in this iteration—only overrides for the four built-in launchers, keeping the shared JSON `output_schema` from `BaseLauncher`.

Runs must remain reproducible: if a user edits their prompt after starting a workflow, completed and in-flight reports should reflect what was queued.

## Decision

1. **Storage:** Add `launcher_prompt_overrides` with `user_id`, `launcher_id`, `prompt_template` (text), unique `(user_id, launcher_id)`. Platform default stays on `launchers.prompt_template`.

2. **Resolution on create:** When an authenticated user `POST /api/runs`, effective prompt = user's override for that launcher if present, else launcher default. Persist on the run as `runs.prompt_snapshot` (nullable text; set whenever an effective prompt is chosen at queue time).

3. **Execution:** `RunExecutor` uses `$run->prompt_snapshot ?? $run->launcher->prompt_template` before appending GitHub context.

4. **API:** Authenticated `GET /api/user/launcher-prompts` (list with slug, default, override, effective preview) and `PUT /api/user/launcher-prompts/{slug}` to set or update override; `DELETE` clears override (revert to default). Validate max length and active launcher slug.

5. **UI:** Provider Settings gains a **Workflow prompts** section (one textarea per built-in launcher, reset to default). Anonymous users continue to use platform defaults only.

6. **Out of scope:** Custom slugs, per-user `output_schema`, editing `launcherMeta.ts` from API, admin-global prompt UI (can use seeder/DB ops separately).

## Consequences

### Positive

- BYOK users can align doctor/review/plan prompts with team standards without code deploys.
- Run history and retries stay consistent via `prompt_snapshot`.
- Extends declarative launcher model without breaking `StoreRunRequest` slug validation.

### Negative

- Another user-owned table and settings surface to maintain.
- Large prompts increase row size on `runs`; mitigated with reasonable max length validation.
- Drift between seed class defaults and DB until re-seed; overrides are per-user, not seed sync.

### Related

- GitHub issue: [#59](https://github.com/jellydn/ai-flow/issues/59)
- Supersedes nothing; complements [0016](0016-stored-encrypted-byok-credentials.md) (settings area UX).
