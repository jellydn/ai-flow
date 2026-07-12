# 1. Vite + React prototype before Laravel backend

Date: 2026-07-12

## Status

Accepted

## Context

The product PRD targets Laravel 13 on Laravel Cloud for queues, persistence, shareable `/runs/:id` URLs, and AI provider integration. The first deliverable synced from Amp (`feat: add AI Launcher workflow UI`) is a standalone frontend with no API layer.

We need a shippable demo quickly (weekend MVP) while keeping the documented long-term stack intact.

## Decision

Build and host the **launcher experience as a Vite + React SPA** in this repository first. Treat Laravel as the **planned production backend**, not part of the initial commit set (`package.json`, `vite.config.js`, `src/main.jsx`).

The README documents both: product stack (Laravel) vs **this repo** (Vite + React prototype).

## Consequences

### Positive

- Fast iteration and Amp `sync` of UI-only changes without PHP/runtime setup.
- Clear separation: validate UX, workflows, and report layout before backend contracts.
- `npm run dev` with `0.0.0.0` and `allowedHosts: true` supports remote preview (Orb/Amp).

### Negative

- Two stacks to maintain until the UI is embedded in or replaced by the Laravel app.
- No real GitHub fetch, AI calls, or persistent runs until backend exists.
- Risk of drift between prototype copy and future API-driven launcher definitions.