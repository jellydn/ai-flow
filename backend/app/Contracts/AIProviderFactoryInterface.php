<?php

namespace App\Contracts;

interface AIProviderFactoryInterface
{
    public function forExecution(string $provider, ?string $apiKey = null): AIProviderInterface;
}
