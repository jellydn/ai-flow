# 4. Structured report UX, not chat

Date: 2026-07-12

## Status

Accepted

## Context

The product problem is repetitive paste-and-prompt into chat UIs. The PRD specifies executive summary, findings with severity, recommendations, verification checklist, cost, and shareable URLs—not transcripts.

The implemented UI uses dedicated views: `Home`, `Running`, `Report`, with severity-tagged finding cards and copy-link affordances.

## Decision

Model the product as **finite views with structured report layout**, not a message thread. Findings use **severity** (`high` / `medium` / `low`), file references, body, and suggested fix fields aligned with code review output.

Share UX uses a demo run URL pattern in the report UI; persistence of `/runs/:id` is deferred to Laravel.

## Consequences

### Positive

- UX matches positioning (“workflows, not prompts”) and sales demo scenario in README.
- Report schema in static data previews the JSON shape backend should emit.
- Easier to add export (Markdown/PDF) from a fixed layout than from chat logs.

### Negative

- Less flexibility for follow-up questions until a future “continue run” feature exists.
- Static report does not prove LLM output quality or schema validation yet.
