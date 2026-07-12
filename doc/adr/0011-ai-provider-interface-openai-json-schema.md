# 11. `AIProviderInterface` with OpenAI strict JSON schema

Date: 2026-07-12

## Status

Accepted

## Context

Reports must be structured (ADR 0004). Chat completions without schema risk invalid or prose-only output. The PRD mentions OpenAI Responses API with provider abstraction.

## Decision

Define **`AIProviderInterface::generate(string $prompt, array $schema): array`**. Bind an **`AIProviderFactoryInterface`** that creates an execution-scoped provider by stable provider ID. `openai` is the only implemented ID; the API contract can add Anthropic, Gemini, OpenRouter, Groq, or Fireworks adapters without changing request shape.

An execution may override the server's OpenAI-compatible API key. Since ADR 0008 requires asynchronous jobs, jobs containing an override implement Laravel's `ShouldBeEncrypted`; plaintext keys are never written to run records or queue payloads. The worker decrypts the job using the shared `APP_KEY` and injects the override only into that provider instance. Authentication failures are reduced to the safe message `Invalid API key.` and provider response details are not logged.

`OpenAIProvider` calls **`/v1/chat/completions`** with `response_format.type = json_schema`, `strict: true`, and the launcher’s `output_schema`. System message instructs JSON-only output matching the schema.

After generation, **`JsonSchemaValidator`** validates the decoded array (types, `required`, `enum`, nested objects/arrays) before persisting to `runs.result`.

## Consequences

### Positive

- New providers require an adapter and factory mapping, while the API request remains stable.
- User keys override the server key without entering run persistence or plaintext queue payloads.
- Same schema drives OpenAI constraints and post-hoc validation.
- Aligns API `result` with frontend finding cards (`severity`, `title`, `recommendation`, etc.).

### Negative

- Tied to OpenAI chat completions + json_schema feature set and model config (`OPENAI_MODEL`, timeout).
- Custom validator is not a full JSON Schema implementation—edge cases may differ from OpenAI’s enforcement.
- No streaming tokens to clients; user sees progress strings only until completion.
- Web and worker processes must share a stable `APP_KEY` to process encrypted jobs.
