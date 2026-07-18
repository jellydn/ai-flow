# Privacy & Data Handling

## What we store

| Data | Stored? | Encrypted? | Purpose |
|------|---------|------------|---------|
| Email address | Yes | No | Sign-in (password or magic link) |
| Password (optional) | Yes | No (bcrypt hash) | Password sign-in only; not stored for magic-link-only users |
| User name (optional) | Yes | No | Display in dashboard |
| Saved provider API keys | Yes | **Yes** (AES-256 via `APP_KEY`) | BYOK — use your own AI provider key |
| Saved provider base URLs | Yes | **Yes** | Optional custom endpoint for OpenAI-compatible providers |
| AI run inputs (GitHub URL) | Yes | No | Run execution and history |
| AI run outputs (structured reports) | Yes | No | Run results and history |
| Execution metadata (provider, model, timestamps) | Yes | No | Run history display and filtering |
| Authentication tokens (magic links) | Yes (hashed, 15-min expiry) | Hashed | Single-use email-link sign-in verification |
| Session cookies | Yes (HTTP-only, same-site) | N/A | Authentication session maintenance |

## What is encrypted

API keys are encrypted before database storage using Laravel's `Crypt` facade (authenticated AES-256-CBC encryption). A dedicated `CREDENTIAL_ENCRYPTION_KEY` env var is used when set, falling back to `APP_KEY` for backward compatibility. Keys are decrypted **only** at the moment an AI request is sent to your selected provider — never in API responses, logs, queue payloads, or error reports.

We do not claim encryption makes the application immune to server compromise. If an attacker gains access to both the database and the encryption key, stored credentials could be decrypted.

## What is sent to external providers

When you run a flow:

1. **GitHub content** is fetched from the public GitHub API (repository metadata, file tree, README, pull request diffs, issue content, comments).
2. **That content plus your launcher's prompt** is sent to your selected AI provider (OpenAI, Anthropic, Gemini, or OpenRouter).
3. The AI provider's privacy and retention terms also apply to the data they receive.
4. **Do not submit secrets or confidential code** unless you are authorized to share them with these providers.

## User controls

- **Delete saved credentials**: Settings → API Keys → Delete
- **Delete individual runs**: Run History → Delete
- **Delete your account**: Settings → Account → Delete account (permanently removes your email, all saved credentials, and all run history)
- **Sign out**: Dashboard → Sign out (invalidates your session)

## What we do not send to analytics

- API keys (plaintext or encrypted)
- Full prompts or full outputs
- Private repository content
- Email addresses
- Authentication tokens

## Logging

We use structured logs containing IDs and statuses, not full sensitive payloads. API keys, email addresses, repository contents, prompts, provider responses, and authentication tokens are **not** logged.

## Encryption key management

- API keys are encrypted using a dedicated `CREDENTIAL_ENCRYPTION_KEY` env var when set, falling back to `APP_KEY` for backward compatibility.
- **Back up your encryption key securely.** Losing it makes all stored credentials unrecoverable.
- Key rotation requires a planned re-encryption migration (see `config/credentials.php` for the procedure).
- Staging and production must use separate keys.
- In production, the application logs a warning if `CREDENTIAL_ENCRYPTION_KEY` is unset (i.e. the `APP_KEY` fallback is in effect), so operators are alerted that rotating `APP_KEY` would invalidate stored credentials.

## Provider data policies

Each AI provider has its own data usage and retention policies:
- [OpenAI Privacy Policy](https://openai.com/privacy)
- [Anthropic Privacy Policy](https://www.anthropic.com/privacy)
- [Google Gemini Privacy Policy](https://policies.google.com/privacy)
- [OpenRouter Privacy Policy](https://openrouter.ai/privacy)

Review these policies to understand how your data is handled by each provider.
