<?php

namespace App\Support;

use App\Contracts\AIProviderInterface;
use App\Services\OpenAIProvider;
use InvalidArgumentException;

final class AiProviders
{
    public const OPENAI = 'openai';

    /** @return list<string> */
    public static function ids(): array
    {
        return [self::OPENAI];
    }

    public static function createProvider(string $provider, ?string $apiKey = null): AIProviderInterface
    {
        return match ($provider) {
            self::OPENAI => app()->make(OpenAIProvider::class, ['apiKey' => $apiKey]),
            default => throw new InvalidArgumentException('Unsupported AI provider.'),
        };
    }
}
