# 19. Email/password authentication (alongside magic link)

Date: 2026-07-14

## Status

Accepted

## Context

ADR 0015 established magic-link sign-in. Users also want conventional email/password registration and login without removing the passwordless path. Some accounts exist only from magic link (`password` nullable); those users should be able to set a password on sign-up with the same email.

## Decision

Add **session-based email/password auth** that **coexists** with magic links.

- `POST /auth/register` — create an account or set a password on a magic-link-only user (same email); rejects if the email already has a password.
- `POST /auth/login` — email + password; generic errors on failure (no user enumeration).
- Laravel `Password::defaults()` for validation; `User` uses `hashed` cast and hides `password`.
- Rate limits: `auth-login` (10/min per IP+email), `auth-register` (5/min per IP).
- SPA: `SignIn` UI tabs for password sign-in, sign-up, and magic link; `fetch` uses `credentials: include`.
- API route group includes session middleware so `/api/user` works after password login from the same-origin app.

Magic-link routes, mail, and ADR 0015 behavior are unchanged.

## Consequences

### Positive

- Users choose password or magic link.
- Magic-link-only users can upgrade to password without a separate migration flow.
- Reuses the same session guard and logout path as magic link.

### Negative

- Password storage and credential-stuffing surface (mitigated by rate limits and generic login errors).
- No dedicated password-reset email flow yet (users can use magic link or re-register only when `password` is still null).