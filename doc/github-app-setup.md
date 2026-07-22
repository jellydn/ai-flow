# Setting Up the ai-flow GitHub App

This guide walks through registering a GitHub App that powers the `@ai-flow` bot
in issue and PR comments.

## Overview

The GitHub App:

- Listens for `issue_comment.created` webhook events
- Posts and updates comments on issues/PRs as `ai-flow [bot]`
- Authenticates via a private key (JWT → installation token)

**Time to complete:** ~10 minutes.

---

## Step 1: Register the GitHub App

1. Go to **[GitHub → Settings → Developer settings → GitHub Apps](https://github.com/settings/apps)**.

2. Click **New GitHub App**.

3. Fill in the registration form:

   | Field | Value |
   |-------|-------|
   | **GitHub App name** | `ai-flow` (must be unique across GitHub; use `ai-flow-{org}` if taken) |
   | **Homepage URL** | `https://github.com/jellydn/ai-flow` |
   | **Callback URL** | Leave blank (not needed) |
   | **Setup URL** | Leave blank (optional) |
   | **Webhook URL** | `https://YOUR_DOMAIN/api/github/webhooks` |
   | **Webhook secret** | Generate a long random string (see [step 2](#step-2-generate-a-webhook-secret)) |
   | **SSL verification** | ✅ Enable |

4. **Permissions** — set these under "Repository permissions":

   | Permission | Access |
   |-----------|--------|
   | **Issues** | Read & Write |
   | **Metadata** | Read-only (default, always granted) |
   | **Contents** | Read-only (for reading `.github/ai-flow.yml`) |

   > **Why these?** The bot needs to read `.github/ai-flow.yml` for per-repo
   > launcher configuration, and post/update comments with results.

5. **Subscribe to events** — check these:

   - ✅ **Issue comment** (all actions — the controller filters to `created` only)

6. **Where can this GitHub App be installed?**

   - **Only on this account** — if the bot is for your own repos.
   - **Any account** — if you want others to install it on their repos.

7. Click **Create GitHub App**.

---

## Step 2: Generate a Webhook Secret

Run this to generate a cryptographically random webhook secret:

```bash
openssl rand -hex 32
```

Save the output. You'll need it:
- In the **Webhook secret** field of the GitHub App settings.
- In your `.env` as `GITHUB_WEBHOOK_SECRET`.

---

## Step 3: Generate a Private Key

After creating the app, generate a private key:

1. On your app's settings page, scroll to **Private keys**.
2. Click **Generate a private key**.
3. Save the downloaded `.pem` file securely (it cannot be recovered later).

The app ID is shown at the top of the settings page (e.g., `App ID: 123456`).

---

## Step 4: Configure Environment Variables

Add these to your `.env` file:

```bash
# GitHub App credentials
GITHUB_APP_ID=123456
GITHUB_APP_PRIVATE_KEY="-----BEGIN RSA PRIVATE KEY-----
...
-----END RSA PRIVATE KEY-----"
# Must match the secret from GitHub App → Webhook secret
GITHUB_WEBHOOK_SECRET=your-generated-hex-secret

# Optional — defaults to "ai-flow"
GITHUB_BOT_COMMENT_LABEL=ai-flow
```

> **Multi-line private keys:** Wrap the key in double quotes and keep the
> `-----BEGIN` / `-----END` lines. In `.env`, newlines inside quotes are
> preserved.

Alternatively, store the private key as a file and load it lazily so the
file is only read when `GITHUB_APP_PRIVATE_KEY` is not set. PHP evaluates
`env()`'s default argument eagerly, so use the `??` null-coalesce operator
to defer the file read until the env var is actually absent:

```php
// config/github-bot.php — an alternative to inline env var
'app_private_key' => env('GITHUB_APP_PRIVATE_KEY')
    ?? file_get_contents(storage_path('github-app-private-key.pem')),
```

The `??` null-coalesce operator only evaluates its right-hand side when the
env var is absent, so the file read is skipped when the key is provided
inline — avoiding an unnecessary disk read (and a `file_get_contents` warning
when the file doesn't exist) on every config access.

---

## Step 5: Install the App

1. Go to your app's page: `https://github.com/apps/YOUR-APP-NAME`.
2. Click **Install**.
3. Select the repositories where you want the bot active.
4. Click **Install**.

> The bot only responds in repos where it's installed AND where the repo is
> **public** (private repos are rejected at the webhook level).

---

## Step 6: Verify

1. Deploy with the new environment variables.
2. Create a test issue or PR in an installed repo.
3. Comment: `@ai-flow review`
4. The bot should reply with a progress comment and update it with results.

Check GitHub App **Advanced → Recent Deliveries** for webhook logs if something
doesn't work.

---

## Permissions Summary

| Permission | Level | Reason |
|-----------|-------|--------|
| Issues | Read & Write | Post and update issue/PR comments |
| Metadata | Read-only | Always granted; identifies repos |
| Contents | Read-only | Read `.github/ai-flow.yml` for per-repo config |

**Events:** `issue_comment` (the controller filters to `action: created` only).

---

## Troubleshooting

| Symptom | Likely cause |
|---------|-------------|
| "Invalid signature" (401) | `GITHUB_WEBHOOK_SECRET` doesn't match the app's webhook secret |
| No comment posted | App not installed on repo, or repo is private |
| 403 when posting comment | Issues permission is set to Read-only (needs Read & Write) |
| Installation token 404 | App installed on a different org than the webhook's `installation.id` |
| Bot comments are ignored | Commenter `type: Bot` is filtered out (prevents infinite loops) |

## References

- [GitHub: Registering a GitHub App](https://docs.github.com/en/apps/creating-github-apps/registering-a-github-app/registering-a-github-app)
- [GitHub: Choosing permissions](https://docs.github.com/en/apps/creating-github-apps/registering-a-github-app/choosing-permissions-for-a-github-app)
- [GitHub: Webhook events and payloads](https://docs.github.com/en/webhooks/webhook-events-and-payloads#issue_comment)
