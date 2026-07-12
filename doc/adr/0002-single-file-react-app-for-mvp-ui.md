# 2. Single-file React app for MVP UI

Date: 2026-07-12

## Status

Accepted

## Context

The UI landed as one large addition (`src/main.jsx`, ~390 lines) with styles in `src/styles.css`. There is no component folder, router package, or state library in `package.json`.

Amp-driven sessions favor small, easy-to-sync trees over multi-file refactors during early design.

## Decision

Keep **all React UI in `src/main.jsx`**: workflow catalog, home/running/report views, validation, and static demo data. Use **plain CSS** (no Tailwind in the diff) and **lucide-react** for icons.

Refactor into components and routes only when adding real API integration or shared launcher definitions with the backend.

## Consequences

### Positive

- Single place to read the full user journey (`home` → `running` → `report`).
- Minimal dependencies: `react`, `react-dom`, `@vitejs/plugin-react`, `vite`.
- Matches `AGENTS.md` onboarding for agents and humans.

### Negative

- Harder code review and parallel edits as the file grows.
- No TypeScript or ESLint in repo yet—regressions rely on manual review.
- `latest` pins on React and lucide-react may cause non-reproducible builds.