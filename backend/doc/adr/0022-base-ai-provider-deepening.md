# 0022. `BaseAIProvider` deepening — shared HTTP lifecycle behind a template-method seam

Date: 2026-07-16

## Status

Accepted (extends [ADR-0017](0017-multi-provider-registry.md))

## Context

ADR-0017 introduced `AiProviderRegistry` as the single source of truth for provider *lookup*, eliminating process-wide `config()` mutations. However, the four concrete adapters (`OpenAIProvider`, `OpenRouterProvider`, `AnthropicProvider`, `GeminiProvider`) still each carried ~80% identical HTTP lifecycle glue: key fallback, timeout reading, `->retry(2, 500, throw: false)`, 401/403 → "Invalid API key." mapping, non-success → "AI provider request failed (HTTP {status}).", `json_decode` + "invalid JSON" guard, and `ConnectionException` → "Unable to reach the AI provider..." mapping.

The adapters were **wide, not deep**: the interface was nearly as complex as the implementation, and the provider-specific knowledge (endpoint, payload shape, response JSON path) was the small remainder buried inside identical boilerplate.

An architecture review (2026-07-16) identified this as the top deepening opportunity. The deletion test confirmed it: removing the shared logic would force the boilerplate back into four files — it concentrates complexity, not merely moves it.

### Latent bugs uncovered during the review

1. **OpenAI connection-failure gap**: OpenAIProvider did not catch `ConnectionException` (unlike Anthropic, Gemini, and OpenRouter), so OpenAI users saw a raw Laravel exception instead of the safe "Unable to reach the AI provider..." message.
2. **Anthropic/Gemini config-fallback divergence**: OpenAI and OpenRouter fell back to server config keys when constructed without an injected key, but Anthropic and Gemini did not — throwing "not configured" even when their env keys were set.
3. **Misleading timeout key**: all four adapters read `config('services.openai.timeout')`, including Anthropic and Gemini — a single shared timeout hiding behind an OpenAI-specific config name.
4. **Dead code**: `OpenAIProvider::generate()` still contained a `str_contains($baseUrl, 'openrouter.ai')` check from before OpenRouter had its own dedicated adapter (ADR-0017).

## Decision

Introduce **`BaseAIProvider`** (abstract class in `app/Services/`) that owns the full HTTP request lifecycle shared by all providers. Each concrete adapter extends it and declares only its provider-specific *shape* via small protected hooks.

### Shared lifecycle (owned by `BaseAIProvider`)

- **Key resolution**: `$override ?? $this->apiKey ?? config($this->configKey())` — unified across all providers, fixing the Anthropic/Gemini fallback gap.
- **Timeout**: reads `config('services.ai.timeout')` with backward-compat fallback to `services.openai.timeout` / `OPENAI_TIMEOUT`.
- **Retry**: `retry(2, 500, throw: false)` — constants `RETRY_ATTEMPTS`, `RETRY_DELAY_MS`.
- **Verify timeout**: `VERIFY_TIMEOUT_SECONDS = 10` (intentionally shorter than generate — lightweight GET).
- **Status mapping**: 401/403 → "Invalid API key.", non-success → "AI provider request failed (HTTP {status}).".
- **Connection errors**: `ConnectionException` → "Unable to reach the AI provider. Check your network." — fixing the OpenAI gap.
- **JSON decode**: `extractJson()` tries direct `json_decode`, then strips a single ```` ```json ```` code fence, then slices from the first `{` to the last `}` — tolerating prose-wrapped JSON from providers without native `json_schema` enforcement (Anthropic, Gemini). Falls back to the "AI provider returned invalid JSON." guard only when all strategies fail.
- **`verifyCredential()`**: full verify lifecycle owned by the base; subclass declares only `verifyEndpoint()`.

### Template-method hooks (declared by each subclass)

| Hook | Purpose | Example |
|------|---------|---------|
| `configureRequest(PendingRequest): PendingRequest` | Auth headers + provider-specific headers | OpenAI: `withToken()`; Anthropic: `x-api-key` + `anthropic-version`; Gemini: no-op (key in URL) |
| `endpoint(string $model): string` | Full request URL | OpenAI: `{baseUrl}/chat/completions`; Gemini: `.../models/{model}:generateContent?key=...` |
| `buildPayload(string, array, string): array` | Provider-specific request body | OpenAI: `messages` + `response_format`; Anthropic: `system` + `max_tokens` |
| `extractContent(array): string` | JSON path into the response | OpenAI: `choices.0.message.content`; Gemini: `candidates.0.content.parts.0.text` |
| `verifyEndpoint(): string` | Verify GET URL | OpenRouter: `{baseUrl}/key`; Gemini: `.../models?key=...&pageSize=1` |
| `configKey(): ?string` | Which config key holds the server API key | `'services.openai.key'`, `'services.anthropic.key'` |
| `defaultModel(): string` | Configured default model | OpenAI: `config('services.openai.model')`; Gemini: `config('services.gemini.model')` |
| `systemMessage(): string` | System prompt (overridable) | Default: short form; Anthropic/Gemini: append "Output only the JSON..." |

### Registry delegation

`AiProviderRegistry::defaultModel()` now delegates to `(new ($providerClass)(null))->defaultModel()` instead of re-implementing the provider → config-key match. Single source of truth — a run's snapshotted model and the generation-time model can no longer diverge.

### Config change

New `services.ai.timeout` key (env `AI_TIMEOUT`, defaults to `OPENAI_TIMEOUT` for backward compatibility). The old `services.openai.timeout` key remains as a fallback.

### `AIProviderInterface` change

Added `defaultModel(): string` to the interface so the registry can delegate.

## Consequences

### Positive

- **Leverage**: changing retry/timeout/error policy touches one module, not four.
- **Locality**: "how we talk to an LLM HTTP API" lives in one place; "what Anthropic's payload looks like" lives in another — no longer interleaved.
- **Test surface**: `BaseAIProvider`'s shared lifecycle (connection errors, key fallback, timeout resolution) is tested once, not four times through different lenses.
- **Bug fixes**: OpenAI connection-failure gap and Anthropic/Gemini config-fallback divergence are fixed as part of the deepening.
- **Adapter size**: each concrete adapter shrinks from ~60-80 lines of mixed glue + shape to ~30-40 lines of pure shape declarations.
- **Single source of truth for default models**: registry delegates to adapters, eliminating the snapshot/generation divergence risk.

### Negative

- Inheritance over composition — but the "is-a" relationship (each adapter *is* an AI provider with a specific dialect) is genuine, matching the existing `BaseLauncher` pattern.
- The protected `$resolvedKey` property is mutable state set before hooks run — acceptable because adapters are single-use instances (registry creates a fresh `new ($class)($apiKey)` per run).
- `AIProviderInterface` gained a method — any future implementation outside the base class must provide `defaultModel()`.

### Related

- Extends [ADR-0017](0017-multi-provider-registry.md) (multi-provider registry)
- Extends [ADR-0011](0011-ai-provider-interface-openai-json-schema.md) (AIProviderInterface)
- Architecture review: 2026-07-16
