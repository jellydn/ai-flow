# 15. Magic-link authentication

Date: 2026-07-13

## Status

Accepted

## Context

The application needs user accounts for BYOK (bring your own key) credential storage and per-user run history. Traditional password-based auth adds friction (password creation, reset flows, credential stuffing risk) and the target audience (developers) is comfortable with email-based sign-in.

The existing codebase already uses Laravel sessions and has no authentication framework installed beyond Laravel's built-in guard system.

## Decision

Implement **passwordless email authentication using magic links**.

- User enters their email on the sign-in page.
- Application generates a random token, stores its hash with an expiration timestamp, and sends a signed URL via email.
- User clicks the link; Laravel verifies the token hash, checks expiration, marks it used, authenticates the user, and regenerates the session.
- Tokens are single-use and expire after 15 minutes.
- The request endpoint returns a generic message regardless of whether the email exists (no user enumeration).

**Implementation details:**
- `MagicLinkController` handles request, verify, and logout.
- `MagicLinkMail` Mailable renders the sign-in button + plain-text fallback.
- Rate limiting: 3 requests per minute per IP+email.
- Session regeneration on successful login prevents session fixation.
- `Auth::logout()` + session invalidation on logout.

## Consequences

### Positive
- No password storage, hashing, or reset flows needed.
- No credential stuffing or brute-force attack surface.
- Lower friction for users — no password to remember or manage.
- Reuses Laravel's native session guard, CSRF protection, and signed URLs.

### Negative
- Depends on email delivery reliability (mail provider outage = no sign-in).
- Slower than password auth (email round-trip time).
- Rate limiting is critical to prevent email bombing attacks.
- Token table requires periodic cleanup of expired entries.
- No offline sign-in capability.
