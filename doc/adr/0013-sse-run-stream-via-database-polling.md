# 13. SSE run stream via database polling

Date: 2026-07-12

## Status

Accepted

## Context

The UI product spec includes live execution progress. Laravel dispatches **`RunProgressed`** on each progress update, but there is no broadcast driver wiring in `backend/composer.json`.

Clients need updates without hammering `GET /api/runs/{id}` every few hundred milliseconds from many tabs.

## Decision

Implement **`GET /api/runs/{run}/stream`** as **Server-Sent Events** using Laravel `response()->eventStream()`. The stream loop (~55s deadline) **polls the database** every 500ms, compares serialized `RunResource` JSON, yields `progress` events on change, and emits `completed` or `failed` terminal events.

Set **`X-Accel-Buffering: no`** and disable caching for reverse proxies (documented for Laravel Cloud).

`RunProgressed` remains useful for future listeners; SSE does not subscribe to the event bus today.

## Consequences

### Positive

- Works with default stack—no Redis/Pusher required for MVP progress.
- Same `RunResource` shape as REST show endpoint keeps clients simple.
- Matches README `curl -N` SSE example.

### Negative

- DB polling per open stream adds load under many concurrent viewers.
- 55s stream window may require client reconnect for very long runs.
- Event name `RunProgressed` suggests push; actual transport is pull-based polling.