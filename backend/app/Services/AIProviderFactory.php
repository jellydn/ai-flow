<?php

namespace App\Services;

use App\Contracts\AIProviderFactoryInterface;
use App\Contracts\AIProviderInterface;
use App\Support\AiProviders;
use InvalidArgumentException;

class AIProviderFactory implements AIProviderFactoryInterface
{
    public function forExecution(string $provider, ?string $apiKey = null): AIProviderInterface
    {
        return match ($provider) {
            AiProviders::OPENAI => app()->make(OpenAIProvider::class, ['apiKey' => $apiKey]),
            default => throw new InvalidArgumentException('Unsupported AI provider.'),
        };
    }
}
