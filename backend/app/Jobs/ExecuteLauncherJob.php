<?php

namespace App\Jobs;

use App\Contracts\AIProviderInterface;
use App\Contracts\RunExecutorInterface;
use App\Events\RunProgressed;
use App\Models\Run;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ExecuteLauncherJob implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(
        public string $runId,
        private ?string $provider = null,
        private ?string $apiKey = null,
    ) {}

    public function handle(RunExecutorInterface $executor): void
    {
        $run = Run::with('launcher')->findOrFail($this->runId);

        if ($this->provider !== null && $this->provider !== 'openai') {
            $this->failRun($run, 'Unsupported AI provider.');

            return;
        }

        $ai = app()->make(AIProviderInterface::class, ['apiKey' => $this->apiKey]);
        $executor->execute($run, $ai);
    }

    private function failRun(Run $run, string $message): void
    {
        $run->update([
            'status' => 'failed',
            'error' => $message,
            'source_context' => null,
            'completed_at' => now(),
        ]);
        Log::error('Launcher run failed during provider setup', ['run_id' => $run->id]);
        RunProgressed::dispatch($run->fresh());
    }
}
