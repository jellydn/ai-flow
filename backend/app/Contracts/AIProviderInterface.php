<?php

namespace App\Contracts;

interface AIProviderInterface
{
    /**
     * Return the provider identifier (e.g. "openai", "anthropic").
     */
    public function id(): string;

    /**
     * Return the list of known model identifiers for this provider.
     *
     * @return list<string>
     */
    public function models(): array;

    /**
     * Verify that a given API key is valid by making the smallest
     * practical request to the provider's API.
     *
     * Returns a result array with at least a 'valid' boolean.
     */
    public function verifyCredential(string $apiKey): array;

    /**
     * Generate a structured JSON response from the AI provider.
     */
    public function generate(string $prompt, array $schema): array;
}
