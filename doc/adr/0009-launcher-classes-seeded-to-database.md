# 9. Launcher classes seeded to database

Date: 2026-07-12

## Status

Accepted (extends [ADR 0005](0005-workflow-catalog-as-declarative-metadata.md))

## Context

The React prototype keeps workflows in a static `workflows` array. The backend must serve a catalog over HTTP, store per-launcher prompts and JSON schemas, and reference runnable configuration from `ExecuteLauncherJob`.

Four PHP classes exist: `ReviewPullRequestLauncher`, `PlanIssueLauncher`, `ExplainRepositoryLauncher`, `LaravelDoctorLauncher`.

## Decision

Each launcher is a **PHP class** extending `BaseLauncher`, exposing `metadata()` (slug, name, description, `inputType`, prompt, `outputSchema`). **`DatabaseSeeder`** calls `Launcher::updateOrCreate` for each class, persisting `prompt_template`, `output_schema`, `class_name`, and `active`.

`GET /api/launchers` reads **active rows from the database**, not hardcoded routes. Slugs: `review-pr`, `plan-issue`, `explain-repository`, `laravel-doctor`.

## Consequences

### Positive

- Prompts and schemas can be edited in DB later without redeploying class files (class still defines seed defaults).
- API catalog aligns with `StoreRunRequest` `exists:launchers,slug` validation.
- Shared `BaseLauncher::outputSchema()` enforces one report shape across workflows.

### Negative

- Drift risk between seed metadata and React workflow `id` strings until the SPA consumes `/api/launchers`.
- `class_name` column is stored but job logic uses DB prompt/schema, not dynamic class dispatch today.
- Adding a launcher requires a new class **and** seeder registration.
