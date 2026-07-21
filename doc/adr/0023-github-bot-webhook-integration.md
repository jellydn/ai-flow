# 23. GitHub bot webhook integration

Date: 2026-07-19

## Status

Accepted

## Context

Users currently launch AI workflows by pasting GitHub URLs into the ai-flow web UI. While this works well for ad-hoc use, it adds friction for users already working inside GitHub issues and pull requests. The `jellydn/my-ai-tools` project demonstrated a comment-driven bot model (`@my-ai-bot review`, `@my-ai-bot plan`) that lets developers trigger AI analysis without leaving the GitHub interface.

We want ai-flow to support the same "trigger from GitHub" flow — a user comments `@ai-flow review` on a PR, and ai-flow runs the Review PR launcher and posts results back as a comment.

## Decision

Implement a **GitHub App webhook endpoint** that:

1. **Receives `issue_comment.created` webhook events** from GitHub
2. **Verifies webhook signatures** using HMAC-SHA256 (`X-Hub-Signature-256`)
3. **Parses `@ai-flow <command>`** from the comment body and maps commands to launcher slugs:
   - `@ai-flow review` → `review-pr`
   - `@ai-flow plan` → `plan-issue`
   - `@ai-flow explain` → `explain-repository`
   - `@ai-flow doctor` → `laravel-doctor`
4. **Dispatches a queue job** (`ProcessGitHubBotCommandJob`) that:
   - Creates a `Run` via the existing `LauncherResolutionService` + `ExecuteLauncherJob` pipeline
   - Polls for completion (reusing the existing SSE/database progress mechanism)
   - Posts a **single progress comment** that updates as the run transitions states (queued → running → completed/failed)
5. **Returns 202** immediately — GitHub webhooks expect responses within 10 seconds

### Authentication

Two modes, in priority order:

| Mode | Config | Use case |
|------|--------|----------|
| **GitHub App** (preferred) | `GITHUB_APP_ID` + `GITHUB_APP_PRIVATE_KEY` | Proper bot identity, installation-scoped tokens, appears as "ai-flow [bot]" |
| **PAT fallback** | `GITHUB_TOKEN` | Simple setup for single-repo usage; comments appear as the token owner |

The bot posts comments using the same `GitHubService::client()` helper extended with installation-token support.

### Configuration

Per-repository configuration via `.github/ai-flow.yml`:

```yaml
# Which launchers are enabled for this repo (default: all)
enabled:
  - review-pr
  - plan-issue
  - explain-repository
  - laravel-doctor

# Custom label applied to bot comments (default: "ai-flow")
label: ai-flow
```

### Security

- **Webhook signature verification** is required — requests without valid `X-Hub-Signature-256` are rejected with 401
- **Command allowlist** — only known launcher slugs are accepted; unknown commands get a helpful reply
- **Public repos only** (matching existing ai-flow constraint) — the bot checks `repository.private` in the webhook payload
- **No write access to repos** — the bot only reads (via the API) and posts comments; it never pushes code or creates branches

### Architecture

```
GitHub issue_comment event
  → POST /api/github/webhooks
    → verify signature (hmac-sha256)
    → extract installation.id from payload
    → parse @ai-flow <command> from comment body
    → dispatch ProcessGitHubBotCommandJob
    → 202 Accepted

ProcessGitHubBotCommandJob (two-phase, non-blocking):
  Phase 1 — Initialization:
    → post progress comment ("⏳ Queued — waiting to start…")
    → resolve launcher + create Run (via LauncherResolutionService)
    → dispatch ExecuteLauncherJob
    → re-dispatch THIS job as a delayed continuation (5 s later)
    → returns immediately (worker is free)
  Phase 2 — Continuation (re-dispatched until terminal or deadline):
    → find Run by id
    → if terminal  → update comment with results, return
    → if deadline exceeded (github-bot.poll_max_seconds) → update
      comment with generic timeout message, return
    → else → re-dispatch continuation with 5 s delay

Each phase has a short timeout (~60 s, well below the 120 s worker
limit), preventing SIGKILL and freeing workers between polls.
```

## Consequences

### Positive

- **Zero-UI trigger** — developers stay in GitHub, no context switch
- **Reuses existing pipeline** — `LauncherResolutionService`, `ExecuteLauncherJob`, `Run` model, `GitHubService::context()` are all shared
- **Async by design** — webhook returns 202 immediately, queue handles the long-running AI call
- **Progress visibility** — single updating comment prevents notification spam
- **GitHub App identity** — comments appear as "ai-flow [bot]" with proper branding

### Negative

- **Requires GitHub App setup** — registering a GitHub App, generating a private key, installing on repos. The PAT fallback reduces this friction for simple use cases.
- **Delayed-results model** — results arrive via a self-updating comment on a ~5-second polling cadence rather than an instant callback. The two-phase continuation pattern avoids blocking queue workers (the initial implementation's `while`/`usleep` loop would starve workers).
- **No PR/issue-level authentication** — the bot uses a single installation token or PAT; all comments appear as the bot, not as individual users. Acceptable for MVP given the public-repo scope.
- **Comment parsing is regex-based** — minimal overhead but fragile against unusual formatting. Future iteration could add slash-command support.

## Alternatives considered

### Polling-based bot (GitHub Actions schedule)
- **Rejected**: high latency, wasted API calls, complex state management. Webhooks are reactive and standard for GitHub bots.

### Full agent framework (LangChain, CrewAI)
- **Rejected**: unnecessary complexity for a command→launcher→result pipeline. ai-flow already has the Launcher abstraction.

### Slash-command-only model
- **Rejected for MVP**: requires GitHub App `commands` permission and separate setup flow. Comment parsing is simpler and works with both App and PAT auth.

## References

- Setup guide: `doc/github-app-setup.md` + `scripts/setup-github-app.sh`
- Inspired by `jellydn/my-ai-tools` PR #323 (`feat: add self-hosted GitHub coding bot`)
- Existing ADRs: 0008 (queue-backed jobs), 0010 (GitHub REST context), 0011 (AI provider interface)
- GitHub docs: [Webhook events and payloads](https://docs.github.com/en/webhooks/webhook-events-and-payloads), [GitHub App authentication](https://docs.github.com/en/apps/creating-github-apps/authenticating-with-a-github-app/about-authentication-with-a-github-app)
