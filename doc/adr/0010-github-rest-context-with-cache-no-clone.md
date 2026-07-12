# 10. GitHub REST context with cache, no clone

Date: 2026-07-12

## Status

Accepted

## Context

Launchers need repository, PR, or issue context. The PRD non-goals exclude repository cloning for the weekend MVP. Public HTTPS `github.com` URLs are required.

## Decision

Implement **`GitHubService`**: `parse()` validates URL shape and derives `repository` | `pull_request` | `issue`; `context()` fetches via **GitHub REST API** (repo metadata, languages, readme excerpt, truncated file tree; PR/issue files, diffs, comments with size caps). Cache keyed by URL SHA for **10 minutes** via Laravel `Cache`.

Optional **`GITHUB_TOKEN`** for rate limits; no OAuth in this API surface.

`ExecuteLauncherJob` rejects URLs whose parsed `type` does not match the launcher’s `input_type`.

## Consequences

### Positive

- Fits MVP scope: no git binary, no disk-heavy clones.
- Bounded payload sizes (`mb_substr` on readme, patches, comments) protect token limits and memory.
- Parsing rules are unit-tested (`GitHubServiceTest`).

### Negative

- **Private repositories** are out of scope without auth.
- Large monorepos only see partial tree and file lists.
- Cache staleness if the same URL is re-run within 10 minutes during active development.