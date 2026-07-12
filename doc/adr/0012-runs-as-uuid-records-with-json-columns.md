# 12. Runs as UUID records with JSON columns

Date: 2026-07-12

## Status

Accepted

## Context

Shareable runs need stable public identifiers. Progress and results are semi-structured and evolve with launcher schemas.

## Decision

Model **`runs`** with **UUID primary key**, `launcher_id` FK, `source_url`, `status` (`queued` | `running` | `completed` | `failed`), and JSON columns: `progress` (string message array), `input`, `source_context`, `result`. Timestamps: `started_at`, `completed_at`.

Expose via **`RunResource`** for `GET /api/runs/{run}` (route model binding on UUID).

## Consequences

### Positive

- UUIDs are safe to expose in URLs without sequential scraping of other users’ runs (auth not yet required).
- `source_context` supports debugging and future re-runs without re-fetching GitHub immediately.
- JSON columns avoid wide migrations for each new optional result field inside the schema envelope.

### Negative

- Querying inside JSON is harder for analytics until normalized tables are added.
- No row-level tenancy until users/OAuth land (`User` model exists from Laravel default only).
- Large `source_context` / `result` payloads may grow DB size quickly.