# Architecture Decision Records

| ADR                                                            | Title                                          | Status              |
| -------------------------------------------------------------- | ---------------------------------------------- | ------------------- |
| [0001](0001-vite-react-prototype-before-laravel-backend.md)    | Vite + React prototype before Laravel backend  | Accepted (see 0007) |
| [0002](0002-single-file-react-app-for-mvp-ui.md)               | Single-file React app for MVP UI               | Accepted            |
| [0003](0003-client-side-simulated-workflow-execution.md)       | Client-side simulated workflow execution       | Accepted (SPA only) |
| [0004](0004-structured-report-ux-not-chat.md)                  | Structured report UX, not chat                 | Accepted            |
| [0005](0005-workflow-catalog-as-declarative-metadata.md)       | Workflow catalog as declarative metadata       | Accepted            |
| [0006](0006-amp-portal-for-preview-hosting.md)                 | Amp portal for preview hosting                 | Accepted            |
| [0007](0007-laravel-api-in-backend-subdirectory.md)            | Laravel API in `backend/` subdirectory         | Accepted            |
| [0008](0008-queue-backed-execute-launcher-job.md)              | Queue-backed `ExecuteLauncherJob`              | Accepted            |
| [0009](0009-launcher-classes-seeded-to-database.md)            | Launcher classes seeded to database            | Accepted            |
| [0010](0010-github-rest-context-with-cache-no-clone.md)        | GitHub REST context with cache, no clone       | Accepted            |
| [0011](0011-ai-provider-interface-openai-json-schema.md)       | `AIProviderInterface` + OpenAI JSON schema     | Accepted            |
| [0012](0012-runs-as-uuid-records-with-json-columns.md)         | Runs as UUID records with JSON columns         | Accepted            |
| [0013](0013-sse-run-stream-via-database-polling.md)            | SSE run stream via database polling            | Accepted            || [0014](0014-api-throttling-and-public-unauthenticated-runs.md) | API throttling and public unauthenticated runs | Accepted |
| [0015](0015-magic-link-authentication.md) | Magic-link authentication | Accepted |
| [0016](0016-stored-encrypted-byok-credentials.md) | Stored encrypted BYOK credentials | Accepted |
| [0017](0017-multi-provider-registry.md) | Multi-provider registry | Accepted |
| [0018](0018-run-ownership-and-visibility.md) | Run ownership and visibility | Accepted |
| [0019](0019-email-password-authentication.md) | Email/password authentication (alongside magic link) | Accepted |
| [0020](0020-per-user-launcher-prompt-overrides.md) | Per-user launcher prompt overrides with run snapshot | Accepted |
| [0021](0021-super-admin-filament-panel.md) | Super admin control panel with Filament | Accepted |
| [0022](0022-base-ai-provider-deepening.md) | `BaseAIProvider` deepening — shared HTTP lifecycle behind a template-method seam | Accepted |

**Frontend / Amp prototype:** 0001–0006
**Laravel API (`backend/`):** 0007–0019 — see [`backend/README.md`](../../backend/README.md)
