# AI Flow

[![CI](https://github.com/jellydn/ai-flow/actions/workflows/ci.yml/badge.svg)](https://github.com/jellydn/ai-flow/actions/workflows/ci.yml)
[![GitHub stars](https://img.shields.io/github/stars/jellydn/ai-flow)](https://github.com/jellydn/ai-flow/stargazers)
[![GitHub license](https://img.shields.io/github/license/jellydn/ai-flow)](LICENSE)
[![PRs welcome](https://img.shields.io/badge/PRs-welcome-brightgreen.svg)](https://github.com/jellydn/ai-flow/pulls)

> Turn a public GitHub repository, issue, or pull request into a structured AI report—without writing a prompt.

[Try the staging app](https://ai-flow-staging.itman.fyi) · [Backend and API guide](backend/README.md) · [Architecture decisions](doc/adr/README.md)

## What AI Flow does

AI Flow packages common engineering tasks as reusable **launchers**. Choose a launcher, paste a GitHub URL, and follow the run in real time while a queue worker collects context, calls an AI provider, validates the structured response, and publishes a shareable report.

Built-in launchers:

- **Review pull request** — identify risks, findings, and verification steps
- **Plan GitHub issue** — turn an issue into an actionable implementation plan
- **Explain repository** — summarize a public codebase and its structure
- **Laravel project doctor** — inspect a Laravel project and recommend improvements

Every run includes progress updates, a schema-validated result, timing information, and a dedicated result URL.

## Guest and signed-in access

| Access | Provider and model behavior |
| --- | --- |
| **Guest** | Runs use OpenRouter's [`openrouter/free`](https://openrouter.ai/openrouter/free) model router. No provider setup is required. |
| **Signed in** | Choose OpenAI, OpenRouter, Anthropic, or Gemini; select a suggested model or enter another provider model ID; use a one-time key or an encrypted saved credential. |

Only public `https://github.com/...` URLs are accepted. Provider credentials are never stored on runs, logged, or returned by the API. Saved keys are encrypted at rest and decrypted only by the worker executing the run.

## How it works

```text
Browser
  │
  ├─ POST /api/runs ──▶ Laravel API ──▶ database queue
  │                                          │
  └─ SSE /api/runs/{id}/stream               ▼
                                      ExecuteLauncherJob
                                              │
                                  GitHub context + AI provider
                                              │
                                              ▼
                                    validated structured report
```

The repository is a single Laravel 13 application. Laravel serves the React 19 SPA and queue-backed API; Vite builds the TypeScript frontend.

| Layer | Technology |
| --- | --- |
| UI | React 19, TypeScript, Vite |
| API | Laravel 13, PHP 8.4 |
| Async execution | Laravel database queue and Server-Sent Events |
| AI | OpenAI, OpenRouter, Anthropic, and Gemini adapters |
| Storage | SQLite for development; PostgreSQL/MySQL for production |
| Deployment | Docker/Dokku or Laravel Cloud |

## Local development

Prerequisites: PHP 8.4+, Composer, Node.js 24+, and SQLite.

```bash
git clone https://github.com/jellydn/ai-flow.git
cd ai-flow/backend

cp .env.example .env
composer install
npm install
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
```

Configure at least `OPENROUTER_API_KEY` for guest runs. Add provider keys such as `OPENAI_API_KEY`, `ANTHROPIC_API_KEY`, or `GEMINI_API_KEY` when you want their server-side defaults available. `GITHUB_TOKEN` is optional but recommended to avoid GitHub's low anonymous rate limits.

Start Laravel, the queue listener, application logs, and Vite together:

```bash
composer run dev
```

Open <http://localhost:8000>.

### Run services separately

```bash
php artisan serve
php artisan queue:work --sleep=1 --tries=2 --timeout=120
npm run dev
```

Do not use `QUEUE_CONNECTION=sync` in production. Runs are intentionally asynchronous so GitHub and AI requests never block the HTTP request cycle.

## Quality checks

Run commands from `backend/`:

```bash
php artisan test
./vendor/bin/pint --test

npm run typecheck
npm run lint
npm run konsistent
npm run build
npm test
```

CI runs the backend suite on PHP 8.4 and the frontend suite on Node.js 24.

## API overview

```bash
# Discover launchers and providers
curl http://localhost:8000/api/launchers
curl http://localhost:8000/api/providers

# Start a guest run (returns 202 + UUID)
curl -X POST http://localhost:8000/api/runs \
  -H 'Content-Type: application/json' \
  -d '{
    "launcher": "explain-repository",
    "source_url": "https://github.com/laravel/framework"
  }'

# Read status or stream progress
curl http://localhost:8000/api/runs/RUN_UUID
curl -N -H 'Accept: text/event-stream' http://localhost:8000/api/runs/RUN_UUID/stream
```

`/api/flows` and `/api/executions` remain backward-compatible aliases for `/api/launchers` and `/api/runs`. See [`backend/README.md`](backend/README.md) for authentication, credentials, run history, rate limits, and complete request behavior.

## Deployment

The deploy root is `backend/`, not the repository root.

- [Dokku deployment guide](backend/DOKKU_DEPLOY.md)
- [Laravel Cloud deployment guide](backend/CLOUD_DEPLOY.md)

Production requires a stable shared `APP_KEY`, durable PostgreSQL/MySQL storage, a database queue worker, and provider credentials. Keep the web and worker environments in sync so encrypted queued jobs can be decrypted after deployment.

## Project documentation

- [`backend/README.md`](backend/README.md) — application setup and API details
- [`doc/adr/`](doc/adr/README.md) — architecture decision records
- [`DESIGN.md`](DESIGN.md) — product and interface direction
- [`backend/DOKKU_DEPLOY.md`](backend/DOKKU_DEPLOY.md) — current staging deployment
- [`backend/CLOUD_DEPLOY.md`](backend/CLOUD_DEPLOY.md) — Laravel Cloud alternative

## Contributing

Issues and pull requests are welcome. Keep changes focused, include tests for behavior changes, and run the relevant checks above before opening a pull request.

## License

[MIT](LICENSE) © [jellydn](https://github.com/jellydn)
