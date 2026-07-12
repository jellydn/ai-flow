<?php

namespace App\Support;

final class AiProviders
{
    public const OPENAI = 'openai';

    /** @return list<string> */
    public static function ids(): array
    {
        return [self::OPENAI];
    }
}