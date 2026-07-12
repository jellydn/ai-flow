# 5. Workflow catalog as declarative metadata

Date: 2026-07-12

## Status

Accepted

## Context

The PRD’s “Launcher library” defines each workflow with name, description, input type, prompt, JSON schema, and result template. The diff introduces a `workflows` array with `id`, `title`, `description`, `icon`, `tone`, `time`, `accepts`, and optional `popular` / `badge`.

UI behavior (grid, quick picks, selection state) is driven entirely from this array.

## Decision

Represent launchers as **declarative in-app metadata** (today: JavaScript constants; later: API or config files). Keep presentation fields (`icon`, `tone`, `time`) alongside product fields (`accepts`, `description`) in one record per workflow.

Extend the catalog by appending objects—not by branching UI logic per workflow in multiple files (until scale requires it).

## Consequences

### Positive

- Mirrors future server-side launcher definitions and marketplace model.
- New workflows (e.g. release notes, security scan in `workflows`) are data changes first.
- Selection and `activeWorkflow` lookup stay a single `find` by `id`.

### Negative

- Prompts and JSON schemas are not in the repo yet—metadata is incomplete vs PRD.
- Icon components embedded in data couple catalog to React/lucide until abstracted.
