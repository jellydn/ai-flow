# 11. `AIProviderInterface` with OpenAI strict JSON schema

Date: 2026-07-12

## Status

Accepted

## Context

Reports must be structured (ADR 0004). Chat completions without schema risk invalid or prose-only output. The PRD mentions OpenAI Responses API with provider abstraction.

## Decision

Define **`AIProviderInterface::generate(string $prompt, array $schema): array`**. Bind **`OpenAIProvider`** in `AppServiceProvider` as the default implementation.

`OpenAIProvider` calls **`/v1/chat/completions`** with `response_format.type = json_schema`, `strict: true`, and the launcher’s `output_schema`. System message instructs JSON-only output matching the schema.

After generation, **`JsonSchemaValidator`** validates the decoded array (types, `required`, `enum`, nested objects/arrays) before persisting to `runs.result`.

## Consequences

### Positive

- Provider swap (Anthropic, etc.) is a container binding change plus new adapter.
- Same schema drives OpenAI constraints and post-hoc validation.
- Aligns API `result` with frontend finding cards (`severity`, `title`, `recommendation`, etc.).

### Negative

- Tied to OpenAI chat completions + json_schema feature set and model config (`OPENAI_MODEL`, timeout).
- Custom validator is not a full JSON Schema implementation—edge cases may differ from OpenAI’s enforcement.
- No streaming tokens to clients; user sees progress strings only until completion.