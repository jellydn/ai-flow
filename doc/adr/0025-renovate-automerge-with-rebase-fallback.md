# 25. Renovate automerge for lockfile-only PRs

Date: 2026-07-25

## Status

Proposed

## Context

Renovate opens dependency-update PRs whose typical diff is `backend/package-lock.json` and/or `backend/composer.lock`. Two sibling Renovate PRs (#104, #106) opened minutes apart each regenerated a lockfile against fast-moving `main`; the resulting race produced 2-of-9 lockfile-churn conflicts the human team had to unwrap manually — a chore with no decision content.

Knobs available before this ADR:

1. **Renovate's built-in `git.automerge`** — works for the FIRST PR, but loses to the rebase-vs-merge race. Each subsequent bot run regenerates the lockfile against the new tip of `main`, which conflicts with the still-open predecessor PR's lockfile.
2. **Grouped lockfile maintenance (`lockFileMaintenance` with `groupName`)** — collapses the fan-out into one PR per update batch, but loses per-update traceability and makes surgical rollback harder.
3. **A custom GitHub Actions workflow with rebase-on-conflict fallback** — let the existing `ci.yml` gates run, and if merge fails because the lockfile is stale (the typical case), reset the PR branch onto `main`, regenerate the lockfile against the new base, force-push the rebuilt branch, wait for CI to re-run, then squash-merge.

Constraints:

- Must not auto-merge PRs that contain non-lockfile changes. Humans still gate those.
- Must wait for the SAME `backend`, `frontend`, `e2e` checks other PRs use.
- Must not bypass CI gates — a Renovate commit with `[skip ci]` must NOT silently merge.
- Must not destroy the head branch if `main` has moved in surprising ways (atomic force-push, `--force-with-lease`).
- Must leave an audit trail — the bot's approval references the changed files; the rebase approval records the post-rebase head SHA in the body.
- Permission scope must be the minimum needed.

## Decision

A new workflow at `.github/workflows/renovate-automerge.yml`. Acceptance gate: a 29-test Node behavior harness at `.freebuff/verify-renovate-automerge.mjs` covering every `actions/github-script@v7` branch with mocked `@actions/core` / `@actions/github` / `context`.

### 1. Author + draft filter

```yaml
if: |
  github.event.pull_request.user.login == 'app/renovate[bot]' &&
  github.event.pull_request.draft == false
```

Only PRs by the Renovate GitHub App, and only non-drafts, enter the auto-merge path. Drafts and human PRs bypass entirely. A direct merge attempt on a draft returns 422 from `pulls.merge`; the `draft == false` guard preempts that.

### 2. Strict lockfile-only allowlist

```js
const ALLOWED = ['backend/package-lock.json', 'backend/composer.lock'];
const lockfileOnly = names.length > 0 && names.every(n => ALLOWED.includes(n));
```

Renovate updates that surface `composer.json` / `package.json` constraint changes (rather than just a regenerated lockfile) are NOT processed — humans review those. The two-file cap also means `pulls.listFiles` returns the full diff in one page; pagination is not exercised but `per_page: 100` is set defensively.

### 3. Required CI = same set as `.github/workflows/ci.yml`

The wait loop polls `pulls.get` + `checks.listForRef`, gated on `[ 'backend', 'frontend', 'e2e' ].includes(c.name)`. A 30-second poll cadence, 28-minute hard deadline. If zero required checks have appeared within a 2-minute grace window (a `[skip ci]` Renovate commit, a CI-job rename, a misconfigured workflow file), the workflow `setFailed` early instead of waiting 28 minutes — preserving the safety property that we never bypass CI gates.

### 4. Two-stage merge with rebase-on-conflict + merge-method fallback

The first `pulls.merge(merge_method: 'squash')` is attempted. Failure paths:

- **HTTP 405 with "merge method"** — short-circuit and retry `pulls.merge` with no `merge_method` (repo default). This is a CONFIG issue; rebase will not help.
- **HTTP 409 / "conflict" / "not mergeable"** — outcome = `rebase_needed`. Trigger reset onto `origin/main`, regenerate lockfiles via `npm install --package-lock-only` and `composer update --no-scripts --prefer-dist` (NOT `composer install`, which is strict and doesn't regenerate against a drifted `composer.json`), force-push with `--force-with-lease` restricted to branches matching `renovate/*`, wait for CI again on the rebuilt branch, then merge.
- **HTTP 422 / 500 / other** — rethrow; workflow fails (humans intervene).

The second `pulls.merge` after rebase wraps the same `merge_method: 'squash'` call in a try/catch with the same `/merge method/i` fallback — if the first attempt short-circuited via rebase and the second attempt hits the same config error, the fallback still gets a chance before re-throwing.

### 5. Branch-and-approval defensive scoping

- Force-push and branch-delete steps both guard with `if [[ "$BRANCH" == renovate/* ]]` as a defense-in-depth check beyond the workflow-level author filter.
- The bot's APPROVE review is recorded twice when a rebase happens — once against the original head SHA, and once against the post-rebase SHA. The post-reapproval body's body interpolates `'${{ steps.ci2.outputs.head_sha }}'` so the PR timeline shows which SHA was approved at which time.
- `git push --force-with-lease` surfaces any rejection via `|| { echo "::error::..."; exit 1; }` rather than letting `set -euo pipefail` mask it.
- A 5-second pre-loop sleep in the second CI wait (`ci2`) absorbs the brief post-force-push window where GitHub's PR API can return the pre-push head SHA.

### 6. Permission scope

```yaml
permissions:
  contents: write       # checkout with token, force-push to PR branch
  pull-requests: write  # createReview + pulls.merge + delete branch
  checks: read          # listForRef to gate on backend/frontend/e2e
  statuses: read
```

`GITHUB_TOKEN` scope is the minimum needed for the four operations; no `id-token`, no `packages`, no admin.

## Consequences

### Positive

- Eliminates the manual rebase-then-merge cycle on every Renovate PR. Sub-minute merge latency after CI is green.
- Eliminates lockfile-churn merge conflicts at PR-take (already-eliminated at rebase time).
- Single source of truth for "what does CI mean here" — `REQUIRED = ['backend', 'frontend', 'e2e']` matches `ci.yml` job names.
- Behavior-verifiable: `.freebuff/verify-renovate-automerge.mjs` runs 29 assertions covering every `actions/github-script@v7` branch with mocked APIs, including the 2-min guard, the merge-method short-circuit, the fallback, and the rebase-needed propagation. The harness can be promoted to CI in a follow-up.
- Defense-in-depth on every mutating call: author filter, draft filter, branch-prefix filter on force-push and delete, `--force-with-lease`, layered try/catch around `pulls.createReview`.

### Negative / Trade-offs

- The two `pulls.merge` calls share a `/merge method/i` regex match in two places; if GitHub changes its 405 message format, both must be updated. A composite action would centralise it; kept inline for readability.
- The first-merge short-circuit + the post-rebase merge's try/catch encode subtly different downstream consequences (fallback-then-rebase vs fallback-or-throw). Intentional but worth tracking.
- The bash rebase step (`Reset onto origin/main and regenerate lockfiles`) is NOT covered by the JS harness. Closing that gap requires `act` + a fixture GitHub repo.
- Soft assumption: the repo's branch protection does NOT require reviewer-approval from non-bot users. If that changes, the `try/catch` on `pulls.createReview` already swallows the failure with a warning, but the merge itself may then block — currently a 422 is "throw, fail workflow, human review".

### Related

- `.github/workflows/ci.yml` — defines the `backend` / `frontend` / `e2e` checks this workflow gates on.
- `.github/workflows/deploy-staging.yml` — earlier author-filtered workflow in this repo (filter on `jellydn` for Dokku deploys); same pattern as the new `app/renovate[bot]` filter here.
- `.freebuff/verify-renovate-automerge.mjs` — behavior harness; the JS-side acceptance gate.
- ADR 0024 — per-user launchers; same "user-controlled knob on top of a built-in" pattern, unrelated domain.
