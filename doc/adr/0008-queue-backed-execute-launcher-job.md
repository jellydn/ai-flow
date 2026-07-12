# 8. Queue-backed `ExecuteLauncherJob` for AI work

Date: 2026-07-12

## Status

Accepted

## Context

Workflow runs involve GitHub API calls, large prompts, and OpenAI latency (job `timeout` 120s, `tries` 2). Running that on the HTTP request thread would block clients and risk proxy timeouts.

`RunController::store` returns immediately after creating a `Run` and dispatching the job.

## Decision

Implement execution in **`App\Jobs\ExecuteLauncherJob`** implementing `ShouldQueue`. Flow: validate launcher + URL on `POST /api/runs` → persist `queued` run → `ExecuteLauncherJob::dispatch($run->id)` → worker runs GitHub fetch, AI generate, schema validate, persist `completed` or `failed`.

**Forbid** running AI work on the web process in production; `backend/README.md` explicitly rejects `QUEUE_CONNECTION=sync` for production.

## Consequences

### Positive

- HTTP **202** with UUID matches async workflow UX and PRD “under a minute” without holding connections.
- Retries and timeouts are centralized on the job class.
- Feature tests can mock GitHub and `AIProviderInterface` without hitting the network.

### Negative

- Requires a **queue worker** in every environment (documented: `queue:work` / `queue:listen`).
- Progress is only as fresh as DB updates until the client polls or uses SSE.
- Failed jobs after retries need operational monitoring (logs in `handle` catch).