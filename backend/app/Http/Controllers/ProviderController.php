<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class ProviderController extends Controller
{
    /**
     * Return the list of available AI providers with their display names and models.
     */
    public function index(): JsonResponse
    {
        return response()->json(
            collect(config('services.openai.providers'))->map(
                fn (string $id) => [
                    'id' => $id,
                    'name' => $this->providers()[$id]['name'] ?? $id,
                    'models' => $this->providers()[$id]['models'] ?? [],
                ]
            )
        );
    }

    /**
     * Single source of truth for provider metadata.
     */
    private function providers(): array
    {
        return [
            'openai' => [
                'name' => 'OpenAI',
                'models' => ['gpt-4o-mini', 'gpt-4o', 'gpt-4-turbo', 'gpt-3.5-turbo'],
            ],
            'openrouter' => [
                'name' => 'OpenRouter',
                'models' => ['openai/gpt-4o-mini', 'openai/gpt-4o', 'anthropic/claude-sonnet-4'],
            ],
        ];
    }
}
