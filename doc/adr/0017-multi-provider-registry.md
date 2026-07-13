# 17. Multi-provider registry

Date: 2026-07-13

## Status

Accepted

## Context

The application initially supported only OpenAI (and OpenRouter via base URL override on the OpenAI adapter). The BYOK feature requires supporting multiple providers with distinct API semantics: OpenAI, Anthropic, Gemini, and OpenRouter.

The previous approach used `app()->make(AIProviderInterface::class)` with runtime `config()` overrides to switch between OpenAI and OpenRouter. This was fragile — config changes are process-wide, so a queue worker processing an OpenRouter job could corrupt the config for a subsequent OpenAI job in the same process.

## Decision

Introduce **`AiProviderRegistry`** as the central source of truth for provider lookup.

**Architecture:**
- `AiProviderRegistry` maps provider IDs (`openai`, `openrouter`, `anthropic`, `gemini`) to concrete adapter classes.
- `get(string $providerId, ?string $apiKey): AIProviderInterface` creates a fresh adapter instance with an injected API key.
- Each adapter is self-contained: its own base URL, model list, headers, and API semantics.
- No runtime `config()` mutations — each provider reads its own config keys independently.
- `OpenRouterProvider` is a dedicated adapter (not an OpenAI base URL override) with its own model list and `HTTP-Referer`/`X-Title` headers.

**Registry replaces:**
- `app()->make(AIProviderInterface::class)` calls in `ExecuteLauncherJob` and `ProviderCredentialController`.
- `config('services.openai.providers')` as the provider list source.
- `configureProvider()` runtime config mutations in `ExecuteLauncherJob`.
- The `AIProviderInterface` container binding in `AppServiceProvider`.

**Extensibility:**
Adding a new provider (e.g., Groq, Fireworks, Mistral) requires:
1. Create an adapter class implementing `AIProviderInterface`.
2. Add an entry to `AiProviderRegistry::PROVIDERS`.
3. Add config keys if server-managed keys are supported.

## Consequences

### Positive
- Eliminates process-wide config mutation race conditions.
- Each provider is isolated and independently testable.
- Single source of truth for the provider list — no config/registry duplication.
- Provider lookup is explicit and type-safe.
- Adding providers is a simple registry entry, not a config restructuring.

### Negative
- The `list()` method instantiates each provider to call `models()` — minor overhead for 4 providers.
- The `AIProviderInterface` container binding was removed; code that depended on it must use the registry instead.
- `config('services.openai.providers')` is now redundant but kept for backward compatibility.
