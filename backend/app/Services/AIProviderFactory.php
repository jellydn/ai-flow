<?php

namespace App\Services;

use App\Contracts\AIProviderFactoryInterface;
use App\Contracts\AIProviderInterface;
use InvalidArgumentException;

class AIProviderFactory implements AIProviderFactoryInterface
{
    public function forExecution(string $provider, ?string $apiKey = null): AIProviderInterface
    {
        return match ($provider) {
            'openai' => new OpenAIProvider($apiKey),
            default => throw new InvalidArgumentException('Unsupported AI provider.'),
        };
    }
}
