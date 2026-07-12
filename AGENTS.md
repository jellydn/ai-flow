# AGENTS.md

## Project overview

**ai-launcher** — a Vite + React prototype UI for "AI Launcher", an app that turns GitHub URLs into structured AI workflows. Currently a frontend-only landing page with hardcoded demo data; no backend, no tests, no linting.

Source entry: `src/main.jsx` (single-file React app, ~390 lines). All components live here.

## Commands

```bash
npm run dev        # Vite dev server on 0.0.0.0 (all interfaces)
npm run build      # production build → dist/
npm run preview    # preview production build on 0.0.0.0
```

No lint, typecheck, test, or formatter commands exist. If you add one, add it here too.

## Architecture notes

- **Single-file app**: everything (components, data, layout) is in `src/main.jsx`. No component directory.
- **No CSS framework**: plain CSS in `src/styles.css`. Fonts loaded from Google Fonts.
- **Hardcoded data**: workflows, recent runs, execution steps, and findings are static arrays in `src/main.jsx`. No API calls, no backend integration yet.
- **Amp portal**: `.amp/portals/ai-launcher.json` contains a deployed URL for the Amp preview.
- **npm uses `latest` tag** for react, react-dom, and lucide-react — builds may break on major bumps. Pin versions if stability matters.

## Style conventions

- No ESLint, Prettier, or Prettier config. Match existing code style manually.
- CSS uses BEM-like flat class names (`.launcher-card`, `.workflow-icon.orange`).
- JSX is functional components with hooks, no TypeScript.

## Gotchas

- `vite.config.js` sets `server.allowedHosts: true` — dev server accepts all hosts (intentional for local/remote dev).
- `package.json` has `"type": "module"` — use ES module imports everywhere.
