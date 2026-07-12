# AI Launcher

[![GitHub stars](https://img.shields.io/github/stars/jellydn/ai-flow)](https://github.com/jellydn/ai-flow/stargazers)
[![GitHub license](https://img.shields.io/github/license/jellydn/ai-flow)](https://github.com/jellydn/ai-flow/blob/main/LICENSE)
[![PRs Welcome](https://img.shields.io/badge/PRs-welcome-brightgreen.svg)](https://github.com/jellydn/ai-flow/pulls)

> **Turn GitHub URLs into structured AI workflows.** Paste a repository, issue, or pull request, choose a workflow, and get a polished report—no prompt engineering.

## Vision

AI Launcher is a web application that lets developers launch predefined AI workflows from a GitHub URL instead of writing prompts. Users paste a repository, issue, or pull request URL, select a workflow, and receive a structured, shareable result.

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

Additional UI workflows in this prototype: release notes, security scan, and more.

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

| Layer | Planned / product |
|-------|-------------------|
| **Frontend** | React, Tailwind CSS (this repo: Vite + React) |
| **Backend** | Laravel 13, Laravel Cloud, queues, cache, scheduler |
| **AI** | OpenAI Responses API (initial), provider abstraction |
| **Storage** | MySQL/Postgres, Laravel cache |

## This repository

Weekend MVP **launcher UI** synced from [Amp](https://ampcode.com) (`@productsway/AI-Flow`). It demonstrates workflows, launch flow, and report layout. Backend execution, persistence, and shareable `/runs/:id` routes are product goals—not necessarily implemented here yet.

Architecture decisions (from prototype git history): [`doc/adr/`](doc/adr/README.md).

### Local development

Requires [Node.js](https://nodejs.org/) (or Bun).

```bash
git clone https://github.com/jellydn/ai-flow.git
cd ai-flow
npm install
npm run dev
```

Open the URL Vite prints (dev server binds `0.0.0.0`).

```bash
npm run build    # production build
npm run preview  # preview production build
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

The Laravel queue-backed API is in [`backend/`](backend/README.md). See its README for setup, endpoints, streaming, and deployment.
