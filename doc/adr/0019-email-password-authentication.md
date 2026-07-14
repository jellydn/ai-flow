# 19. Email/password authentication (alongside magic link)

Date: 2026-07-14

## Status

Accepted (amended 2026-07-14 — registration no longer upgrades magic-link accounts without verified session)

## Context

ADR 0015 established magic-link sign-in. Users also want conventional email/password registration and login without removing the passwordless path.

## Decision

Add **session-based email/password auth** that **coexists** with magic links.

- `POST /auth/register` — **new emails only**; rejects any existing user (including magic-link-only) to prevent account takeover via unverified email.
- `POST /auth/login` — email + password; generic errors on failure; dummy bcrypt work when the user is missing or has no password to reduce timing skew.
- Laravel `Password::defaults()` for validation; `User` uses `hashed` cast and hides `password`.
- Rate limits: `auth-login` (10/min per IP+email), `auth-register` (5/min per IP).
- SPA: `SignIn` UI tabs; `fetch` uses `credentials: include` and `X-XSRF-TOKEN` from the `XSRF-TOKEN` cookie on mutating requests.
- `/api/user/*` routes use the `web` + `auth` middleware (session + CSRF); public `/api/*` routes stay stateless.

Magic-link routes, mail, and ADR 0015 behavior are unchanged.

## Consequences

### Positive

- Users choose password or magic link for new sign-ups.
- Reuses the same session guard and logout path as magic link.
- Authenticated API mutations are CSRF-protected; SSE/public run APIs avoid session locking.

### Negative

- Password storage and credential-stuffing surface (mitigated by rate limits and generic login errors).
- Magic-link-only users cannot add a password via public register until an authenticated “set password” flow exists (use magic link meanwhile).
- No dedicated password-reset email flow yet.
