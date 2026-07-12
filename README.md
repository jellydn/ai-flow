# AI Launcher

[![GitHub stars](https://img.shields.io/github/stars/jellydn/ai-flow)](https://github.com/jellydn/ai-flow/stargazers)
[![GitHub license](https://img.shields.io/github/license/jellydn/ai-flow)](https://github.com/jellydn/ai-flow/blob/main/LICENSE)
[![PRs Welcome](https://img.shields.io/badge/PRs-welcome-brightgreen.svg)](https://github.com/jellydn/ai-flow/pulls)

> **Turn GitHub URLs into structured AI workflows.** Paste a repository, issue, or pull request, choose a workflow, and get a polished report—no prompt engineering.

## Vision

AI Launcher lets developers launch predefined AI workflows from a GitHub URL instead of writing prompts. Users paste a repository, issue, or pull request URL, select a workflow, and receive a structured, shareable result.

## Problem

Developers repeatedly copy context into ChatGPT, Claude, or other AI tools:

1. Open GitHub  
2. Copy a PR or issue  
3. Paste into AI  
4. Write a prompt  
5. Wait  
6. Repeat for every task  

That flow is repetitive, inconsistent, and hard to share.

## Solution

```text
GitHub URL → Choose workflow → Launch → Structured result → Share
```

One-click workflows. No prompt engineering.

## Target users

- Software engineers  
- Open source maintainers  
- Engineering managers  
- Technical reviewers  

## Features (product)

| Area | Description |
|------|-------------|
| **Launcher library** | Catalog of reusable workflows (name, input type, prompt, JSON schema, result template) |
| **Execution timeline** | Live agent progress (metadata, context, analysis, validation, report) |
| **Structured reports** | Summary, findings, severity, fixes, checklists—not chat transcripts |
| **Shareable runs** | Every run gets a public URL, e.g. `/runs/abc123` |

### Built-in workflows (MVP)

- Review pull request  
- Plan GitHub issue  
- Explain repository  
- Laravel project doctor  

Marketing copy in the UI may list extra workflow ideas; the API runs the four launcher slugs seeded in the database (see [`backend/README.md`](backend/README.md)).

### Inputs (MVP)

- Public GitHub repository  
- Public GitHub pull request  
- Public GitHub issue  

### Output per run

- Executive summary  
- Key findings  
- Recommendations  
- Verification checklist  
- Estimated AI cost  
- Processing time  
- Shareable report URL  

## User flow

1. Visit AI Launcher  
2. Paste a GitHub URL  
3. Select a workflow  
4. Click **Launch**  
5. Watch live execution progress  
6. View structured report  
7. Share the result  

## Demo scenario

**Input**

```text
https://github.com/jellydn/my-ai-tools/pull/42
```

**Workflow**

```text
Review Pull Request
```

**Output**

- Risk: Medium  
- Findings with severity and suggested fixes  
- Verification checklist  
- Shareable report URL  

## Success metrics

- First report in under 60 seconds  
- Fewer than 3 clicks to launch  
- Shareable URL for every run  
- 80%+ successful workflow completion  
- Multiple workflows per session  

## Tech stack

| Layer | Stack |
|-------|-------|
| **Frontend** | React, TypeScript, Vite (served by Laravel) |
| **Backend** | Laravel 13, Laravel Cloud, queues, cache, scheduler |
| **AI** | OpenAI Responses API (initial), provider abstraction |
| **Storage** | Neon PostgreSQL in production, SQLite in development |

## This repository

AI Launcher is a single Laravel application that serves both the React UI and the queue-backed API. The UI is built from `backend/resources/ts` and served by a Blade shell plus an SPA fallback so `/runs/:id` resolves correctly. Laravel Cloud deploys `backend/` as the application root.

Architecture decisions (from prototype git history): [`doc/adr/`](doc/adr/README.md).

## Database

Production uses [Neon PostgreSQL](https://neon.com/) through Laravel's `pgsql` connection. Development defaults to local SQLite. Configure Neon with `DB_CONNECTION=pgsql`, its `DB_HOST`, `DB_PORT=5432`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, and `DB_SSLMODE=require`; credentials must remain in environment variables. Use Neon's direct hostname (without `-pooler`) while running `php artisan migrate --force`, because Laravel wraps PostgreSQL schema changes in transactions. The pooled hostname may be used by the web and worker processes after migrations complete.

## Bring Your Own API Key

Users may optionally provide their own OpenAI-compatible API key in the launch form when starting a workflow. The UI sends it as `provider.api_key` on `POST /api/runs` (with `provider.id`, default `openai`). If blank, the server's `OPENAI_API_KEY` is used. User keys are:

- used only for the current execution
- never stored in run records or plaintext queue payloads
- never logged
- never returned by the API

Queued jobs are encrypted with Laravel's `APP_KEY`, so Laravel Cloud web and worker processes must share the same stable `APP_KEY`. OpenAI is the initial provider; the request's `provider.id` contract allows additional provider adapters later.

### Local development

Requires [PHP 8.4+](https://php.net/) and [Node.js](https://nodejs.org/) (or Bun).

```bash
git clone https://github.com/jellydn/ai-flow.git
cd ai-flow/backend
composer install
npm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
```

Start the local dev stack:

```bash
composer run dev
```

This runs the PHP dev server, queue listener, logs, and Vite dev server concurrently. The app is served by Laravel at `http://localhost:8000`.

Or run them separately:

```bash
php artisan serve
php artisan queue:work --sleep=1 --tries=2 --timeout=120
npm run dev
```

Frontend checks:

```bash
npm run typecheck
npm run lint
npm run build
```

Backend checks:

```bash
php artisan test
./vendor/bin/pint --test
```

## Roadmap (not weekend MVP)

- GitHub OAuth and private repositories  
- Multiple AI providers  
- Custom workflows and marketplace  
- Team workspace  
- Markdown export, GitHub comments, Slack  
- API access  

## Non-goals (weekend MVP)

- Autonomous coding agents  
- Repository cloning  
- Pull request creation  
- Billing, authentication, private repos, team collaboration  

## Elevator pitch

**AI Launcher turns GitHub URLs into structured AI workflows. Paste a repository, issue, or pull request, choose a workflow, and get a polished report in under a minute—powered by Laravel Cloud.**

---

<p align="center">
  <sub>Product PRD · Prototype UI · <a href="https://github.com/jellydn/ai-flow">jellydn/ai-flow</a></sub>
</p>

## API backend

The Laravel queue-backed API and UI are in [`backend/`](backend/README.md). See its README for setup, endpoints, streaming, and deployment.
