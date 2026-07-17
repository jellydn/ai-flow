# Verification Audit Gaps — Action Plan

Audit date: 2026-07-17
Spec: Authentication, Magic-Link Sign-In, Encrypted BYOK Management, and User Run History
Branch: `main` (already implemented, 18/18 acceptance criteria met)

---

## Gap 1: Dedicated `CREDENTIAL_ENCRYPTION_KEY` instead of `APP_KEY`

**Severity**: Low
**Spec reference**: Part 5 — "Use a dedicated environment key when practical"

**Current state**:
- `CredentialCipher` delegates to `Crypt::encryptString()` which uses `APP_KEY`
- If `APP_KEY` is rotated, all stored credentials become unrecoverable
- The spec calls for a separate `CREDENTIAL_ENCRYPTION_KEY=` env var

**What to do**:
- [ ] Add `CREDENTIAL_ENCRYPTION_KEY` to `.env.example`
- [ ] Update `CredentialCipher` to use a dedicated key via `config('credentials.encryption_key')`
- [ ] Document key rotation procedure in `backend/README.md`
  - How to re-encrypt existing credentials after rotation
  - Operational impact of losing the key
- [ ] Add a config file `config/credentials.php` with the encryption key reference

---

## Gap 2: Privacy panel in provider settings UI

**Severity**: Low
**Spec reference**: Part 12 — "Add a concise privacy panel"

**Current state**:
- Backend has full account deletion, credential deletion, data minimization
- Frontend has no in-app privacy explainer component
- The spec includes specific language about what data is stored, encrypted, and sent to external providers

**What to do**:
- [ ] Add a `<PrivacyPanel>` component to `/settings/providers` (or a dedicated `/settings/privacy` route)
- [ ] Display the spec's recommended language:
  > Your API keys are encrypted before being stored.
  > They are decrypted only when an AI request is sent to your selected provider.
  > Keys are never shown again after saving.
  > You can replace or delete them at any time.
- [ ] Add links to account deletion and external provider privacy pages
- [ ] Avoid absolute claims like "your key can never be accessed"

---

## Gap 3: `metadata` JSON column on `ProviderCredential` is unused

**Severity**: Info
**Spec reference**: Part 4 — "metadata JSON nullable"

**Current state**:
- `metadata` column exists in the model with `'array'` cast but no setter, no UI field, and no API exposure
- The spec included it for extensibility (future providers, custom config)

**What to do**:
- [ ] Decide: keep as reserved for future use, or add minimal support
- [ ] If keeping: add a comment in the migration explaining it's reserved
- [ ] If adding: add `metadata` to `StoreProviderCredentialRequest` and `UpdateProviderCredentialRequest`, expose via `ProviderCredentialResource`

---

## Gap 4: No `/auth/check-email` intermediate page

**Severity**: Info
**Spec reference**: Part 3 — Frontend routes including `/auth/check-email`

**Current state**:
- Magic link verification is server-side redirect — user is taken directly to app or error redirect
- Email is sent asynchronously — no explicit "check your email" interstitial shown
- The spec listed `/auth/check-email` as a frontend page but the current flow handles this differently

**What to do**:
- [ ] Decide: add the interstitial page or keep the current flow
- [ ] Current flow is actually better UX — fewer clicks. If keeping, document the decision in an ADR
- [ ] If adding: show a `<CheckEmail>` component after magic link request with the obfuscated email address

---

## Summary

| Gap | Severity | Effort | Priority |
|---|---|---|---|
| 1. Dedicated encryption key | Low | Small | Would be nice to have for key rotation safety |
| 2. Privacy panel UI | Low | Medium | Good for user trust, not blocking |
| 3. Unused `metadata` column | Info | None | Remove or use — either way is fine |
| 4. Check-email page | Info | None | Current redirect flow is better UX |

All gaps are **low-severity, non-blocking enhancements**. The implementation meets all 18 acceptance criteria and is production-ready.
