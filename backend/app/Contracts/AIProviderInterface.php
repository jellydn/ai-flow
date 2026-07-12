<?php

namespace App\Contracts;

interface AIProviderInterface
{
    public function generate(string $prompt, array $schema): array;
}
