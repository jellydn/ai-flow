<?php

namespace App\Contracts;

use App\Models\Run;

interface RunExecutorInterface
{
    public function execute(Run $run, string $provider = 'openai', ?string $apiKey = null): void;
}
