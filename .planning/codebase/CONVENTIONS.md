# Coding Conventions
**Analysis Date:** 2026-07-12

## Naming Patterns

- PHP follows Laravel/PSR-4 naming: PascalCase classes in matching files and namespaces, such as `App\Http\Controllers\RunController` in `backend/app/Http/Controllers/RunController.php` and `App\Services\GitHubService` in `backend/app/Services/GitHubService.php`.
- Contracts use an `Interface` suffix (`AIProviderInterface`, `LauncherInterface`, `RunExecutorInterface`) and implementations drop the suffix (`OpenAIProvider`, `RunExecutor`). Bindings are centralized in `backend/app/Providers/AppServiceProvider.php`.
- HTTP boundary types describe their action and resource: `StoreRunRequest`, `RunResource`, and controller methods `store`, `show`, and `stream` in `backend/app/Http/Controllers/RunController.php`.
- Queue jobs use an imperative subject plus `Job`, for example `ExecuteLauncherJob` in `backend/app/Jobs/ExecuteLauncherJob.php`.
- Workflow classes use a descriptive PascalCase name plus `Launcher`, while public IDs are kebab-case slugs such as `ReviewPullRequestLauncher` / `review-pr` in `backend/app/Launchers/ReviewPullRequestLauncher.php`.
- PHP methods and variables are camelCase (`outputSchema`, `$sourceUrl`, `$runSnapshot`); database columns and API payload keys are snake_case (`launcher_id`, `source_url`, `completed_at`).
- PHPUnit methods use descriptive snake_case names prefixed with `test_`, such as `test_run_is_validated_created_and_queued` in `backend/tests/Feature/RunApiTest.php`.
- React components are PascalCase (`App`, `Home`, `WorkflowIcon`); hooks/state and helpers are camelCase (`activeWorkflow`, `setRunSnapshot`, `parseGithubRepo`). CSS uses lowercase kebab-case component-oriented classes (`launcher-card`, `workflow-icon`, `report-layout`) in `src/styles.css`.
- Constants use uppercase snake case in both stacks: `MAX_CONTEXT_BYTES` in `backend/app/Services/RunExecutor.php` and `RUN_POLL_INTERVAL_MS` in `src/main.jsx`.

## Code Style

### Backend

- PHP targets 8.2+ and Laravel 12. Formatting is Laravel Pint's default Laravel preset; there is no custom `backend/pint.json`. Run `backend/vendor/bin/pint` after PHP edits.
- Files use `<?php`, a blank line, namespace, grouped `use` imports, then one primary class/interface. Indentation is four spaces.
- Methods have explicit return types where practical. Constructor property promotion, named arguments, readonly data objects, arrow functions, spread syntax, and `match` are used where they improve clarity.
- Laravel helpers and facades are preferred over hand-built framework plumbing (`response()->json`, `now()`, `config()`, `Http`, `Cache`, `Log`).
- Conditions use strict checks and strict collection membership (`===`, `!==`, `in_array(..., true)`). Negated calls follow Pint spacing (`! $key`).
- Small metadata and validation structures may remain compact arrays; multi-step structures use trailing commas and one item per line.

```php
// `backend/app/Services/RunExecutor.php`
public function __construct(
    private GitHubService $github,
    private AIProviderInterface $ai,
    private JsonSchemaValidator $validator,
) {}

public function execute(Run $run): void
{
    $run->loadMissing('launcher');

    try {
        $this->progress($run, 'Fetching repository', true);
        // ...
    } catch (Throwable $e) {
        // ...
    }
}
```

### Frontend

- The root UI is plain JavaScript/JSX with React functional components and hooks. Do not introduce TypeScript unless the project deliberately adopts it.
- JavaScript uses two-space indentation, single quotes, no semicolons, trailing commas in multiline literals/imports, and parentheses around arrow-function parameters.
- JSX uses double-quoted attributes, self-closing components where applicable, and optional chaining/nullish coalescing for absent API state.
- CSS remains plain CSS in `src/styles.css`, with shared custom properties in `:root`, component-oriented classes, and responsive rules under `@media`. Existing CSS is dense and often keeps short rule sets on one line; match the surrounding section rather than reformatting globally.

```jsx
// `src/main.jsx`
function WorkflowIcon({ workflow, size = 20 }) {
  const Icon = workflow.icon
  return <div className={`workflow-icon ${workflow.tone}`}><Icon size={size} strokeWidth={2} /></div>
}
```

## Import Organization

- PHP imports immediately follow the namespace. Application classes come first, then framework/vendor classes, then PHP/SPL exception types. Each `use` occupies one line; aliases are not currently needed.
- JSX imports external packages first (`react`, `react-dom`, `lucide-react`), then local modules grouped by responsibility, then side-effect CSS last. Multiline named imports are alphabetized in the larger import groups in `src/main.jsx`.
- Local frontend imports include explicit `.jsx`/`.js` extensions (`./components/ErrorBoundary.jsx`, `./lib/api.js`) because the package is ESM.

```jsx
// `src/main.jsx`
import React, { useEffect, useMemo, useState } from 'react'
import { createRoot } from 'react-dom/client'
import { ErrorBoundary } from './components/ErrorBoundary.jsx'
import { createRun, fetchRun, streamRun } from './lib/api.js'
import './styles.css'
```

## Error Handling

- Validate HTTP input in form requests, not controllers. `backend/app/Http/Requests/StoreRunRequest.php` authorizes and validates launcher existence, URL shape, maximum length, HTTPS, and the GitHub host.
- Throw `InvalidArgumentException` for invalid caller/domain input (`GitHubService::parse`) and `RuntimeException` for provider, schema, or execution failures (`OpenAIProvider`, `JsonSchemaValidator`, `RunExecutor`). Messages intended for `runs.error` are safe and do not expose response bodies, credentials, or stack traces.
- Slow GitHub/OpenAI work stays out of the request cycle. `RunController::store` persists a queued run, dispatches `ExecuteLauncherJob`, and returns HTTP 202.
- `RunExecutor::execute` is the failure boundary: it catches `Throwable`, preserves a domain `RuntimeException` message, substitutes `Run failed unexpectedly.` for unknown errors, clears temporary `source_context`, marks the run failed, and dispatches progress.
- Laravel HTTP clients use `throw()` for failed GitHub calls. Expected README 404s are handled explicitly. OpenAI-compatible failures are converted to a bounded status-only message.
- Frontend API helpers in `src/lib/api.js` normalize non-2xx responses into `Error` objects, preferring server messages/validation errors and falling back to status-based text. `src/main.jsx` catches async failures and presents user-facing state; `src/components/ErrorBoundary.jsx` catches render failures.
- Empty catches are limited to explicitly recoverable cases and carry rationale, such as malformed SSE events or transient polling failures.

```php
// `backend/app/Services/RunExecutor.php`
} catch (Throwable $e) {
    $message = $e instanceof RuntimeException ? $e->getMessage() : 'Run failed unexpectedly.';
    $run->update(['status' => 'failed', 'error' => $message, 'source_context' => null, 'completed_at' => now()]);
    Log::error('Launcher run failed', ['run_id' => $run->id, 'exception' => get_class($e)]);
    RunProgressed::dispatch($run->fresh());
}
```

## Logging

- Use Laravel's `Log` facade rather than direct output. Production execution failures are logged in `backend/app/Services/RunExecutor.php` with a stable message and structured context (`run_id`, exception class).
- Keep sensitive exception text and source context out of logs by default. User-visible failure details are separately bounded before storage.
- Configuration risks can emit warnings at startup; `backend/app/Providers/AppServiceProvider.php` warns if production uses `LOG_LEVEL=debug`.
- The frontend has no logging abstraction and intentionally does not use `console.*`; failures are represented in UI state or retried.

## Comments

- Comments explain rationale or non-obvious operational constraints, not the mechanics of nearby code. Examples include the Cloud build/worker guard in `backend/app/Providers/AppServiceProvider.php` and transient polling behavior in `src/main.jsx`.
- PHPDoc is used when it adds type information that native signatures cannot express, such as the array shape returned by `GitHubReference::toArray()` in `backend/app/Data/GitHubReference.php`.
- Boilerplate Laravel docblocks/comments still exist in generated files (`backend/database/factories/UserFactory.php`, `backend/tests/TestCase.php`), but new domain code generally avoids placeholder comments.
- Public frontend helpers may have concise contract comments, as with `streamRun` in `src/lib/api.js` documenting that it returns cleanup.

## Function Design

- Keep controllers thin: validation belongs to form requests, representation to resources, and I/O-heavy workflows to jobs/services.
- Use dependency injection against contracts for swappable boundaries. `ExecuteLauncherJob::handle` depends on `RunExecutorInterface`; `RunExecutor` depends on `AIProviderInterface`.
- Prefer early validation/returns and focused private helpers. `RunExecutor` separates `encodeContext` and `progress`; `JsonSchemaValidator` separates object validation.
- Preserve explicit side effects: state transitions update the `Run`, dispatch `RunProgressed`, and clear temporary GitHub context on both completion and failure.
- React components are functions with props and local hooks. Pure parsing/network helpers live in `src/lib/`; static workflow content lives in `src/data/`; the class-based `ErrorBoundary` is the React-required exception.
- Avoid nested ternaries for complex decisions. Existing short rendering/state choices use ternaries, but backend branching uses `if`/`elseif`, `match`, and early returns.

## Module Design

- Backend modules follow Laravel boundaries: `backend/app/Http/Requests/` validates, `Http/Resources/` serializes, `Jobs/` queues work, `Services/` integrates/coordinates, `Contracts/` defines substitution points, `Launchers/` supplies workflow metadata, and `Models/` persists state.
- New launchers should extend `BaseLauncher`, provide `metadata()`, reuse the shared output schema, and be registered by `backend/database/seeders/DatabaseSeeder.php`.
- Container bindings live in `backend/app/Providers/AppServiceProvider.php`; do not instantiate provider implementations in controllers/jobs.
- API resources control external shape and hide internal columns. `RunResource` exposes launcher slug and conditionally exposes `error`; launcher list responses omit `class_name` as asserted by `backend/tests/Feature/RunApiTest.php`.
- Frontend module boundaries are lightweight: orchestration and page components remain in `src/main.jsx`, reusable UI in `src/components/`, constants/content in `src/data/`, and browser/API utilities in `src/lib/`.
- The frontend and Laravel backend are separate build roots. Root commands build Vite; backend commands and tests run from `backend/`.

---
*Convention analysis: 2026-07-12*
