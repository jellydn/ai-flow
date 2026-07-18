# 16. Stored encrypted BYOK credentials

Date: 2026-07-13

## Status

Accepted

## Context

Users want to save AI provider API keys (OpenAI, Anthropic, Gemini, OpenRouter) so they don't have to paste a key on every launch. Storing plaintext keys in the database is unacceptable — a database breach would expose all user credentials.

The application already supports one-time BYOK (paste a key per run, encrypted in the queue payload via `ShouldBeEncrypted`). Saved credentials require persistent storage with a different threat model.

## Decision

Store API keys **encrypted at rest** using Laravel's `Crypt` facade (authenticated AES-256-CBC encryption via `APP_KEY`).

**Architecture:**
- `CredentialCipher` service provides `encrypt()`, `decrypt()`, and `mask()` methods.
- `ProviderCredential` model stores `encrypted_api_key` (text column, never plaintext).
- The `encrypted_api_key` and `encrypted_base_url` columns are in `$hidden` to prevent accidental JSON serialization.
- Decryption happens **only** inside `ExecuteLauncherJob::resolveApiKey()` immediately before the provider call — never in controllers, API resources, or responses.
- API responses return only a masked key (e.g., `sk-abcd...9X2A`) via `ProviderCredentialResource`.

**Security constraints:**
- Plaintext keys never appear in: database, logs, queue payloads, API responses, analytics, failed job storage, or error monitoring.
- The `ProviderCredential` model has no accessor that decrypts automatically.
- Queue jobs receive only `provider_credential_id` (a UUID), not the key itself.
- Decrypted keys live in memory for the shortest practical duration.

**Key management:**
- Uses a dedicated `CREDENTIAL_ENCRYPTION_KEY` env var for encryption when set, falling back to `APP_KEY` for backward compatibility. This decouples credential encryption from `APP_KEY` rotation.
- Losing the encryption key makes stored credentials unrecoverable.
- A documented key rotation procedure lives in `config/credentials.php` (decrypt with old key → set new key → re-encrypt → verify).
- In production, `AppServiceProvider` logs a warning when `CREDENTIAL_ENCRYPTION_KEY` is unset so operators know the `APP_KEY` fallback is in effect.

## Consequences

### Positive
- Database breach does not expose plaintext API keys.
- Keys are decrypted only at the point of use, minimizing exposure window.
- Consistent with the existing one-time BYOK encryption pattern.
- No third-party encryption library needed — uses Laravel's built-in `Crypt`.

### Negative
- Losing `APP_KEY` renders all stored credentials unrecoverable.
- Key rotation is a planned migration, not a runtime operation.
- `Crypt::encryptString` produces a different ciphertext each time (random IV), so exact-match queries on encrypted columns are impossible.
- Does not protect against server compromise where `APP_KEY` is also accessible.
