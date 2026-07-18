<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/jellydn/ai-flow/main/backend/public/logo-dark.svg">
    <img alt="AI Flow" src="https://raw.githubusercontent.com/jellydn/ai-flow/main/backend/public/logo.svg" width="220">
  </picture>
</p>

<p align="center">
  <strong>Turn GitHub URLs into structured AI workflows — without writing a prompt.</strong>
</p>

<p align="center">
  <a href="https://github.com/jellydn/ai-flow/actions/workflows/ci.yml"><img src="https://github.com/jellydn/ai-flow/actions/workflows/ci.yml/badge.svg" alt="CI"></a>
  <a href="https://github.com/jellydn/ai-flow/blob/main/LICENSE"><img src="https://img.shields.io/github/license/jellydn/ai-flow" alt="License"></a>
  <a href="https://github.com/jellydn/ai-flow/stargazers"><img src="https://img.shields.io/github/stars/jellydn/ai-flow?style=social" alt="Stars"></a>
  <a href="https://github.com/jellydn/ai-flow/pulls"><img src="https://img.shields.io/badge/PRs-welcome-brightgreen.svg" alt="PRs welcome"></a>
  <a href="https://php.net"><img src="https://img.shields.io/badge/PHP-8.4-777bb4?logo=php" alt="PHP 8.4"></a>
  <a href="https://laravel.com"><img src="https://img.shields.io/badge/Laravel-13-ff2d20?logo=laravel" alt="Laravel 13"></a>
  <a href="https://react.dev"><img src="https://img.shields.io/badge/React-19-61dafb?logo=react" alt="React 19"></a>
</p>

---

## What is AI Flow?

**AI Flow** packages common software engineering tasks into reusable **launchers**. Pick a launcher, paste a GitHub URL, and watch as the queue worker gathers context, calls an AI provider, validates the structured response, and delivers a shareable report — all in real time.

<table>
<tr>
<td width="25%"><strong>🔍 Review PR</strong></td>
<td>Identify risks, findings, and verification steps for any public pull request.</td>
</tr>
<tr>
<td width="25%"><strong>📋 Plan Issue</strong></td>
<td>Turn a GitHub issue into an actionable implementation plan with tasks.</td>
</tr>
<tr>
<td width="25%"><strong>📖 Explain Repo</strong></td>
<td>Summarize a public codebase — architecture, structure, and key patterns.</td>
</tr>
<tr>
<td width="25%"><strong>🩺 Laravel Doctor</strong></td>
<td>Inspect a Laravel project and recommend improvements across conventions, security, and performance.</td>
</tr>
</table>

Every run produces a **structured, schema-validated report** — not a wall of chat text. Each finding carries a severity label, file references, and concrete fix suggestions. Results are shareable by URL.

---

## ✨ Why AI Flow?

| | AI Flow | Chat-based AI tools |
|---|---|---|
| **Output** | Structured JSON → polished report | Free-form markdown |
| **Prompting** | Zero — pick a launcher | Must craft context manually |
| **Context** | Automatic GitHub REST fetches | Copy-paste code snippets |
| **Validation** | JSON Schema enforced | Hope the LLM follows format |
| **Sharing** | Shareable result URLs | Chat links, screenshots |
| **Real-time** | SSE progress streaming | Polling or manual refresh |

---

## 🚀 Quick Start

```bash
git clone https://github.com/jellydn/ai-flow.git
cd ai-flow/backend

cp .env.example .env
composer install
npm install
php artisan key:generate
touch database/database.sqlite && php artisan migrate --seed

# Add your API keys
# Required: OPENROUTER_API_KEY (for guest runs)
# Optional: OPENAI_API_KEY, GITHUB_TOKEN

composer run dev
```

Open **http://localhost:8000** and launch your first workflow.

> **Prerequisites:** PHP 8.4+, Composer, Node.js 24+, SQLite. See [`backend/README.md`](backend/README.md) for full setup, sign-in, credentials, and API details.

### ⚡ API in 30 seconds

```bash
# Discover available launchers
curl http://localhost:8000/api/launchers

# Start a run (returns 202 + UUID)
curl -X POST http://localhost:8000/api/runs \
  -H 'Content-Type: application/json' \
  -d '{"launcher": "explain-repository", "source_url": "https://github.com/laravel/framework"}'

# Check status or stream progress
curl http://localhost:8000/api/runs/RUN_UUID
curl -N -H 'Accept: text/event-stream' http://localhost:8000/api/runs/RUN_UUID/stream
```

---

## 🧱 Architecture

```
Browser                                 Queue Worker
  │                                         │
  ├─ POST /api/runs ──▶ API ──▶ DB Queue ──▶ ExecuteLauncherJob
  │                         │                    │
  ├─ SSE /api/runs/{id}/stream               GitHubService
  │                         │                    │
  └─ GET /api/runs/{id}    │              AIProviderInterface
                                        (OpenAI · OpenRouter
                                         Anthropic · Gemini)
                                               │
                                          JsonSchemaValidator
                                               │
                                          runs.result ✅
```

| Layer | Stack |
|---|---|
| **UI** | React 19, TypeScript, Vite |
| **API** | Laravel 13, PHP 8.4 |
| **Async** | Laravel database queue + SSE |
| **AI** | OpenAI, OpenRouter, Anthropic, Gemini |
| **Storage** | SQLite (dev), PostgreSQL/MySQL (prod) |
| **Deploy** | [Dokku](backend/DOKKU_DEPLOY.md) · [Laravel Cloud](backend/CLOUD_DEPLOY.md) |

---

## 🔐 Authentication & Providers

| Access | Behavior |
|---|---|
| **Guest** | Uses OpenRouter's free model router. No setup needed. |
| **Signed in** | Choose any provider (OpenAI, OpenRouter, Anthropic, Gemini), pick a model, and use a one-time key or encrypted saved credential. |

Provider keys are **never stored on runs, logged, or returned by the API**. Saved credentials are encrypted at rest and decrypted only by the worker executing the run. Only public `https://github.com/...` URLs are accepted.

---

## 🧪 Quality

```bash
# Backend
php artisan test                    # 216 tests, 635 assertions
./vendor/bin/pint --test            # PSR-12

# Frontend
npm run typecheck                   # tsc --noEmit (strict)
npm run lint                        # oxlint + oxfmt
npm run konsistent                  # Structural TS conventions
npm run build                       # Production build
npm test                            # Vitest + React Testing Library
npm run test:e2e                    # Playwright (real backend)
```

CI runs the backend suite on **PHP 8.4** and the frontend suite on **Node 24**. Pre-commit hooks via `prek`.

---

## 📚 Documentation

| Document | What it covers |
|---|---|
| [`backend/README.md`](backend/README.md) | Full app setup, API reference, auth, credentials, Docker, cloud deploy |
| [`doc/adr/`](doc/adr/README.md) | Architecture decision records (21 ADRs) |
| [`DESIGN.md`](DESIGN.md) | Visual identity — colors, typography, components, spacing |
| [`AGENTS.md`](AGENTS.md) | AI coding assistant guide — conventions, gotchas, commands |
| [`.planning/codebase/`](.planning/codebase/) | Codebase map — stack, architecture, conventions, testing, concerns |

---

## 🌍 Demo

Try it live at **[ai-flow-staging.itman.fyi](https://ai-flow-staging.itman.fyi)**.

<p align="center">
  <img src="https://raw.githubusercontent.com/jellydn/ai-flow/main/backend/public/demo.png" alt="AI Flow — landing page with launcher cards and URL input" width="800">
</p>

---

## 🤝 Contributing

Issues and pull requests are welcome! Here's how:

1. **Fork** the repo and create a feature branch
2. **Make your changes** — keep them focused and include tests
3. **Run the checks** — `php artisan test`, `./vendor/bin/pint --test`, `npm run typecheck`, `npm run lint`
4. **Open a PR** — describe what, why, and how

All contributions are under the [MIT License](LICENSE). See [`AGENTS.md`](AGENTS.md) for AI-assisted development conventions.

---

## 👤 Author

**Dung Huynh** ([@jellydn](https://github.com/jellydn))

- 🔗 [GitHub](https://github.com/jellydn) · [Website](https://productsway.com) · [X](https://x.com/jellydn)

---

## 📄 License

[MIT](LICENSE) © [jellydn](https://github.com/jellydn)

---

<p align="center">
  <sub>Built with ❤️ using Laravel, React, and a lot of ☕</sub>
</p>
