# 3. Client-side simulated workflow execution

Date: 2026-07-12

## Status

Accepted for root SPA only; backend uses real execution per [ADR 0008](0008-queue-backed-execute-launcher-job.md)

## Context

The PRD requires an execution timeline (metadata → context → AI → validation → report), processing time, and cost estimates. The git diff shows **no fetch calls** and **no environment variables** for AI or GitHub.

`launch()` only validates a GitHub URL regex, then sets `view` to `running` and advances `step` with `setTimeout` against static `executionSteps`.

## Decision

Implement the **running state as a timed client-side simulation** until a backend exists. Use hardcoded `executionSteps`, `findings`, and `recentRuns` arrays to populate the report view.

GitHub URL parsing (`parsedRepo` via regex) is for display only, not repository access.

## Consequences

### Positive

- Demonstrates full flow and success metrics targets (clicks, time-to-report feel) without secrets or quotas.
- Safe for public Amp preview URLs (no server-side token storage in this repo).
- Clear seam for replacement: swap `launch()` for `POST /runs` and SSE or polling on `step`.

### Negative

- Demo data can be mistaken for live analysis; copy must stay honest (“demo runs”, sample findings).
- No validation of private repos, rate limits, or malformed GitHub responses.
- Report content is static—not tied to selected workflow or URL beyond labels.