<?php

namespace App\Jobs;

use App\Contracts\RunExecutorInterface;
use App\Models\Run;
use App\Support\AiProviders;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExecuteLauncherJob implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(
        public string $runId,
        private string $provider = AiProviders::OPENAI,
        private ?string $apiKey = null,
    ) {}

    public function handle(RunExecutorInterface $executor): void
    {
        $run = Run::with('launcher')->findOrFail($this->runId);
        $ai = AiProviders::createProvider($this->provider, $this->apiKey);
        $executor->execute($run, $ai);
    }
}
