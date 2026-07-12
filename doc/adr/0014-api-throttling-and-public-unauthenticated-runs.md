# 14. API throttling and public unauthenticated runs

Date: 2026-07-12

## Status

Accepted

## Context

`POST /api/runs` triggers paid OpenAI usage and GitHub API traffic. `StoreRunRequest::authorize()` returns true for all callers. MVP targets public GitHub URLs only.

## Decision

Apply **`throttle:runs`** middleware on `POST /api/runs`: **5 requests per hour per IP** (`RateLimiter::for` in `AppServiceProvider`).

Keep endpoints **unauthenticated** for MVP: no GitHub OAuth, no API keys on create. Validate `source_url` as HTTPS `github.com` and launcher slug against active DB rows.

Document rate limit and supported slugs in `backend/README.md`; tests cover rate limiting (`RunApiTest`).

## Consequences

### Positive

- Basic abuse protection without building accounts first.
- Aligns with “public repositories only” positioning on the SPA.
- Simple curl-based demos and integration tests.

### Negative

- Shared NAT IPs (offices, mobile carriers) share one bucket.
- No per-user fairness until auth exists.
- Determined abuse can rotate IPs; throttling is a guardrail not a billing control.
