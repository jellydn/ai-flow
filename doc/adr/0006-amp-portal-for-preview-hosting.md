# 6. Amp portal for preview hosting

Date: 2026-07-12

## Status

Accepted

## Context

Development used Amp thread sync (`amp sync`) from `@productsway/AI-Flow`. Commit `chore: add Amp portal config for ai-launcher` added `.amp/portals/ai-launcher.json` linking label `ai-launcher` to an `onamp.dev` URL.

Vite dev server is configured for broad host access for Orb-style preview.

## Decision

Check in **Amp portal metadata** under `.amp/portals/` so CLI and teammates can open the live preview without hunting thread URLs. Use Amp sync for mirroring thread working tree into this git checkout during design iterations.

Git remotes may include both Amp (`origin`) and GitHub (`github`) as seen in project setup.

## Consequences

### Positive

- Documented entry point for demos aligned with Amp workflow.
- Portal config is small and safe to version (URL may rotate; update JSON when redeploying).

### Negative

- Preview URL in JSON can go stale; no automated update in repo.
- Contributors without Amp accounts rely on `npm run dev` locally instead of portal link.
