# Architecture

**Analysis Date:** 2026-07-12

## Pattern Overview

**Overall:** Single-file React SPA (prototype) + Laravel 12 MVC monolith (backend API)

**Key Characteristics:**
- **Frontend:** Single-file React application (~390 lines) with zero client-side routing, hardcoded demo data, and simulated workflow execution
- **Backend:** Laravel 12 MVC monolith with queue-backed job processing, REST API, and SSE streaming
- The frontend prototype is decoupled from the backend — no API communication layer exists yet in the React app
- The backend is fully functional with migrations, seeders, contracts, services, jobs, and tests
- Architecture decisions documented across 14 ADRs in `doc/adr/`

## Layers

### Frontend (React SPA Prototype)

**Presentation Layer:**
- Purpose: Render UI components for the launcher landing page, execution view, and report view
- Location: `src/main.jsx`
- Contains: 6 functional components — `App`, `Home`, `Running`, `Report`, `Logo`, `WorkflowIcon`
- Depends on: React, react-dom, lucide-react (icon library)
- Used by: `index.html` (mounts `<div id="root">`)

**Styling Layer:**
- Purpose: Visual presentation
- Location: `src/styles.css` (~84 lines)
- Contains: Global styles, BEM-like component classes, responsive breakpoints (800px), Google Fonts imports (DM Mono, Manrope, Playfair Display)
- Depends on: Google Fonts CDN

**Data Layer:**
- Purpose: Hardcoded demo data for UI prototyping
- Location: `src/main.jsx` (module-level constant arrays)
- Contains: `workflows` (6 items), `recentRuns` (3 items), `executionSteps` (5 steps), `findings` (3 items)
- Depends on: Nothing (inline data)

**State Management:**
- Location: `src/main.jsx` (`App` component)
- Approach: `useState` hooks for view routing (`home` | `running` | `report`), selected workflow, URL input, step progress, and copy state
- No context providers, no external state library

### Backend (Laravel API)

**Routing Layer:**
- Purpose: HTTP endpoint definitions
- Location: `backend/routes/api.php`
- Endpoints:
  - `GET /api/health` — health check
  - `GET /api/launchers` — list active launchers (from DB)
  - `POST /api/runs` — create and queue a run (throttled: 5/hour/IP)
  - `GET /api/runs/{run}` — get run details (RunResource)
  - `GET /api/runs/{run}/stream` — SSE stream (DB polling, ~55s timeout)

**Controller Layer:**
- Purpose: Handle HTTP requests, return JSON responses
- Location: `backend/app/Http/Controllers/RunController.php`
- Actions: `store` (validates + dispatches job), `show` (returns resource), `stream` (SSE)

**Validation Layer:**
- Purpose: Validate incoming HTTP requests
- Location: `backend/app/Http/Requests/StoreRunRequest.php`
- Rules: `launcher` (required, exists in DB), `source_url` (required, HTTPS github.com URL)

**Resource Layer:**
- Purpose: Shape JSON API responses
- Location: `backend/app/Http/Resources/RunResource.php`
- Fields: id, launcher slug, input, status, progress, result, error, timestamps

**Service Layer:**
- Purpose: Business logic and external API integrations
- Location: `backend/app/Services/`
- `GitHubService.php` — parses GitHub URLs, fetches context (repo info, PR/issue data, file tree, README) via REST API with 10-minute cache
- `OpenAIProvider.php` — calls OpenAI chat completions endpoint with JSON schema response format (structured output)
- `JsonSchemaValidator.php` — validates AI output against the launcher's schema

**Queue / Job Layer:**
- Purpose: Async AI workflow execution
- Location: `backend/app/Jobs/ExecuteLauncherJob.php`
- Behavior: Fetches GitHub context → runs AI analysis → validates JSON → persists result
- Config: `tries=2`, `timeout=120`

**Model Layer:**
- Purpose: Eloquent ORM models
- Location: `backend/app/Models/`
- `Run.php` — UUID primary key, JSON casting for `progress`, `input`, `source_context`, `result`, belongsTo Launcher
- `Launcher.php` — references launcher class with slug, name, description, prompt template, output schema
- `User.php` — default Laravel user model

**Contracts Layer:**
- Purpose: Interfaces for swappable implementations
- Location: `backend/app/Contracts/`
- `AIProviderInterface.php` — `generate(string $prompt, array $schema): array`
- `LauncherInterface.php` — `metadata(): array`

**Launcher Classes:**
- Purpose: Workflow definitions with metadata and prompts
- Location: `backend/app/Launchers/`
- `BaseLauncher.php` — abstract class with shared `outputSchema()` and `make()` helper
- `ReviewPullRequestLauncher.php` — input: pull_request
- `PlanIssueLauncher.php` — input: issue
- `ExplainRepositoryLauncher.php` — input: repository
- `LaravelDoctorLauncher.php` — input: repository

**Event Layer:**
- Purpose: Dispatch events on run state changes for broadcasting
- Location: `backend/app/Events/RunProgressed.php`
- Dispatched by `ExecuteLauncherJob` during progress steps and completion/failure

**Provider Layer:**
- Purpose: Service container bindings and bootstrapping
- Location: `backend/app/Providers/AppServiceProvider.php`
- Binds `AIProviderInterface` to `OpenAIProvider`
- Registers rate limiter `runs` (5 requests/hour/IP)

**Database Layer:**
- Migrations: 4 files (users, cache, jobs, launchers_and_runs)
- Seeders: `DatabaseSeeder` — seeds 4 launcher records from class metadata
- Tests: 2 feature test files (API endpoint tests + job execution tests)

## Data Flow

### Current (Frontend Prototype — hardcoded):
1. JSX renders inline arrays (`workflows`, `recentRuns`, `executionSteps`, `findings`) directly as component props
2. User pastes a GitHub URL → `GitHubInput` captures it, validated client-side (regex)
3. Clicking "Launch" triggers a simulated step progression via `useEffect` + `setTimeout`
4. After all steps complete, view switches to "report" showing hardcoded findings
5. No API calls, no state management beyond React state hooks

### Intended (Backend-Integrated Flow):
1. User pastes GitHub URL → captured by `url` state
2. Frontend sends `POST /api/runs` with `{ launcher: "review-pr", source_url: "..." }`
3. Backend validates, creates Run record (UUID), dispatches `ExecuteLauncherJob`
4. Returns `202 Accepted` with run ID
5. Frontend polls `GET /api/runs/{id}` or subscribes to SSE `GET /api/runs/{id}/stream`
6. `ExecuteLauncherJob`:
   - `GitHubService::parse()` — validates and parses URL owner/repo/type
   - `GitHubService::context()` — fetches repo metadata, PR/issue data, file diffs via REST API (cached 10 min)
   - Updates `run.progress` with status messages, dispatches `RunProgressed` event
   - `AIProviderInterface::generate()` — sends prompt + JSON schema to OpenAI
   - `JsonSchemaValidator::validate()` — ensures AI response matches schema
   - Persists result to `run.result` as JSON, sets `status=completed`
7. Frontend displays structured report with findings, severity, checklist

## Key Abstractions

**Components (Frontend):**
- `App` — root component, manages 3 views via `view` state
- `Home` — landing page (hero, launcher card, workflow grid, recent runs, how-it-works)
- `Running` — execution progress view with animated timeline
- `Report` — structured report view with sidebar nav, findings, checklist

**Backend Contracts:**
- `AIProviderInterface` — abstracts AI provider (currently OpenAI, swappable)
- `LauncherInterface` — abstracts launcher metadata (4 concrete implementations)

**Backend Services:**
- `GitHubService` — encapsulates all GitHub REST interactions with caching
- `JsonSchemaValidator` — separates schema validation from AI response handling

**Launcher Pattern:**
- Each workflow is a class extending `BaseLauncher` implementing `LauncherInterface`
- `metadata()` returns slug, name, description, input type, prompt, output schema
- Seeded to `launchers` table via `DatabaseSeeder`

## Entry Points

**Application Entry (Frontend):**
- Location: `index.html`
- Mounts: `<div id="root">` with `<script type="module" src="/src/main.jsx">`
- `ReactDOM.createRoot(document.getElementById('root')).render(<App />)`

**Backend Entry:**
- Location: `backend/public/index.php`
- Router: `backend/routes/` (api.php, web.php, console.php)

**Job Entry:**
- `backend/app/Jobs/ExecuteLauncherJob.php` — `handle()` method called by queue worker
