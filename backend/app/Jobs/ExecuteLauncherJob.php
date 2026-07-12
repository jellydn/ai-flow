<?php

namespace App\Jobs;

use App\Contracts\RunExecutorInterface;
use App\Models\Run;
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
        private string $provider = 'openai',
        private ?string $apiKey = null,
    ) {}

    public function handle(RunExecutorInterface $executor): void
    {
        $run = Run::with('launcher')->findOrFail($this->runId);
        $executor->execute($run, $this->provider, $this->apiKey);
    }
}
