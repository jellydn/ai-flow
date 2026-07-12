<?php

namespace App\Jobs;

use App\Contracts\RunExecutorInterface;
use App\Events\RunProgressed;
use App\Models\Run;
use App\Support\AiProviders;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

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

        try {
            $ai = AiProviders::createProvider($this->provider, $this->apiKey);
        } catch (InvalidArgumentException $e) {
            $this->failRun($run, $e->getMessage(), $e);

            return;
        } catch (Throwable $e) {
            $this->failRun($run, 'Run failed unexpectedly.', $e);

            return;
        }

        $executor->execute($run, $ai);
    }

    private function failRun(Run $run, string $message, Throwable $e): void
    {
        $run->update([
            'status' => 'failed',
            'error' => $message,
            'source_context' => null,
            'completed_at' => now(),
        ]);
        Log::error('Launcher run failed during provider setup', ['run_id' => $run->id, 'exception' => get_class($e)]);
        RunProgressed::dispatch($run->fresh());
    }
}
